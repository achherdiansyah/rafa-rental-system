<?php

namespace Tests\Feature\Recommendation;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\EquipmentModel;
use App\Models\EquipmentPrice;
use App\Models\EquipmentType;
use App\Models\EquipmentUnit;
use App\Models\ProjectLocation;
use App\Models\RecommendationCriteria;
use App\Models\RecommendationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecommendationScoringTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(string $name): EquipmentType
    {
        return EquipmentType::factory()->create(['name' => $name]);
    }

    private function makeModel(EquipmentType $type, float $capacity, float $baseRate, int $units = 1, array $overrides = []): EquipmentModel
    {
        $model = EquipmentModel::factory()->create(array_merge([
            'equipment_type_id' => $type->id,
            'capacity_value' => $capacity,
            'is_active' => true,
        ], $overrides));

        EquipmentPrice::factory()->create([
            'equipment_model_id' => $model->id,
            'is_all_in' => false,
            'base_rate' => $baseRate,
        ]);

        EquipmentUnit::factory()->count($units)->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        return $model;
    }

    private function criteria(array $overrides = []): RecommendationCriteria
    {
        $request = RecommendationRequest::factory()->create();

        return RecommendationCriteria::factory()->create(array_merge([
            'request_id' => $request->id,
            'project_type' => 'Galian Basah dan Drainase',
            'terrain_condition' => 'Lumpur / Rawa / Basah',
            'load_capacity' => 20.00,
        ], $overrides));
    }

    public function test_weighted_formula_matches_configured_weights(): void
    {
        $type = $this->makeType('Hydraulic Excavator');
        $this->makeModel($type, 20.0, 250000);

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::USER]));

        $res = $this->postJson('/api/v1/recommendations', [
            'project_type' => 'Galian Basah dan Drainase',
            'terrain_condition' => 'Lumpur / Rawa / Basah',
            'load_capacity' => 20.00,
        ])->assertCreated();

        $result = $res->json('data.results.0');
        $b = $result['score_breakdown'];

        $this->assertEqualsWithDelta(100.0, $b['capacity_match']['score'], 0.001);
        $this->assertEqualsWithDelta(98.0, $b['terrain_suitability']['score'], 0.001);
        $this->assertEqualsWithDelta(100.0, $b['project_suitability']['score'], 0.001);
        $this->assertEqualsWithDelta(95.0, $b['price_suitability']['score'], 0.001);
        $this->assertEqualsWithDelta(0.40, $b['capacity_match']['weight'], 0.0001);
        $this->assertEqualsWithDelta(0.30, $b['terrain_suitability']['weight'], 0.0001);
        $this->assertEqualsWithDelta(0.20, $b['project_suitability']['weight'], 0.0001);
        $this->assertEqualsWithDelta(0.10, $b['price_suitability']['weight'], 0.0001);

        // final_score = Σ(raw_score × normalized_weight)
        $expected = 0.0;
        foreach ($b as $c) {
            $expected += $c['score'] * $c['weight'];
            $this->assertEqualsWithDelta(round($c['score'] * $c['weight'], 2), $c['contribution'], 0.001);
        }

        $this->assertEqualsWithDelta(round($expected, 2), $result['match_score'], 0.001);
        $this->assertEqualsWithDelta(98.9, $result['match_score'], 0.001);
    }

    public function test_weights_are_normalized_when_config_sum_is_not_one(): void
    {
        config(['recommendation.criteria' => [
            'capacity_match' => ['weight' => 0.20, 'is_active' => true, 'name' => 'Capacity', 'code' => 'CAPACITY_MATCH'],
            'terrain_suitability' => ['weight' => 0.20, 'is_active' => true, 'name' => 'Terrain', 'code' => 'TERRAIN_SUITABILITY'],
            'project_suitability' => ['weight' => 0.20, 'is_active' => true, 'name' => 'Project', 'code' => 'PROJECT_SUITABILITY'],
            'price_suitability' => ['weight' => 0.20, 'is_active' => true, 'name' => 'Price', 'code' => 'PRICE_SUITABILITY'],
        ]]);

        $type = $this->makeType('Hydraulic Excavator');
        $this->makeModel($type, 20.0, 250000);

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::USER]));
        $res = $this->postJson('/api/v1/recommendations', [
            'project_type' => 'Galian Basah dan Drainase',
            'terrain_condition' => 'Lumpur / Rawa / Basah',
            'load_capacity' => 20.00,
        ])->assertCreated();

        $b = $res->json('data.results.0.score_breakdown');
        foreach ($b as $c) {
            $this->assertEqualsWithDelta(0.25, $c['weight'], 0.0001);
        }
        $this->assertEqualsWithDelta(1.0, array_sum(array_column($b, 'weight')), 0.0001);
        // (100 + 98 + 100 + 95) / 4 = 98.25
        $this->assertEqualsWithDelta(98.25, $res->json('data.results.0.match_score'), 0.001);
    }

    public function test_score_breakdown_is_persisted_and_matches_final_score(): void
    {
        $type = $this->makeType('Excavator');
        $this->makeModel($type, 20.0, 250000);

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::USER]));
        $this->postJson('/api/v1/recommendations', [
            'project_type' => 'Galian Basah dan Drainase',
            'terrain_condition' => 'Lumpur / Rawa / Basah',
            'load_capacity' => 20.00,
        ])->assertCreated();

        $row = \App\Models\RecommendationResult::firstOrFail();
        $this->assertIsArray($row->score_breakdown);
        $this->assertCount(4, $row->score_breakdown);

        $sum = 0.0;
        foreach ($row->score_breakdown as $c) {
            $sum += $c['contribution'];
        }
        $this->assertEqualsWithDelta((float) $row->match_score, round($sum, 2), 0.01);
        $this->assertNotNull($row->available_units);
        $this->assertGreaterThanOrEqual(1, $row->available_units);
    }

    public function test_user_can_only_attach_own_project_location(): void
    {
        $type = $this->makeType('Excavator');
        $this->makeModel($type, 20.0, 250000);

        $user = User::factory()->create(['role' => UserRole::USER]);
        $other = User::factory()->create(['role' => UserRole::USER]);
        $own = ProjectLocation::factory()->create(['user_id' => $user->id]);
        $foreign = ProjectLocation::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);
        $res = $this->postJson('/api/v1/recommendations', [
            'project_type' => 'Galian Basah dan Drainase',
            'terrain_condition' => 'Lumpur / Rawa / Basah',
            'project_location_id' => $own->id,
        ])->assertCreated();
        $this->assertEquals($own->id, $res->json('data.project_location.id'));

        $this->postJson('/api/v1/recommendations', [
            'project_type' => 'Galian Basah dan Drainase',
            'terrain_condition' => 'Lumpur / Rawa / Basah',
            'project_location_id' => $foreign->id,
        ])->assertStatus(403);
    }

    public function test_recommendations_for_different_projects_are_not_mixed(): void
    {
        $type = $this->makeType('Excavator');
        $this->makeModel($type, 20.0, 250000);

        $user = User::factory()->create(['role' => UserRole::USER]);
        $p1 = ProjectLocation::factory()->create(['user_id' => $user->id, 'project_name' => 'Proyek A']);
        $p2 = ProjectLocation::factory()->create(['user_id' => $user->id, 'project_name' => 'Proyek B']);

        Sanctum::actingAs($user);
        $a = $this->postJson('/api/v1/recommendations', [
            'project_type' => 'Galian Basah dan Drainase',
            'terrain_condition' => 'Lumpur / Rawa / Basah',
            'project_location_id' => $p1->id,
        ])->assertCreated();

        $b = $this->postJson('/api/v1/recommendations', [
            'project_type' => 'Konstruksi Jalan & Perataan',
            'terrain_condition' => 'Aspal / Datar / Gravel',
            'project_location_id' => $p2->id,
        ])->assertCreated();

        $this->assertEquals($p1->id, $a->json('data.project_location.id'));
        $this->assertEquals($p2->id, $b->json('data.project_location.id'));
        $this->assertNotEquals($a->json('data.id'), $b->json('data.id'));

        // Each request persists only its own results.
        $this->assertSame(
            (int) $a->json('data.id'),
            (int) \App\Models\RecommendationResult::where('request_id', $a->json('data.id'))->first()->request_id
        );
    }

    public function test_unavailable_units_are_never_recommended(): void
    {
        $type = $this->makeType('Excavator');
        $this->makeModel($type, 20.0, 250000, units: 0);

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::USER]));
        $res = $this->postJson('/api/v1/recommendations', [
            'project_type' => 'Galian Basah dan Drainase',
            'terrain_condition' => 'Lumpur / Rawa / Basah',
        ])->assertCreated();

        $this->assertCount(0, $res->json('data.results'));
    }

    public function test_period_availability_excludes_committed_units(): void
    {
        $type = $this->makeType('Excavator');
        $model = $this->makeModel($type, 20.0, 250000, units: 1);
        $unit = EquipmentUnit::where('equipment_model_id', $model->id)->first();

        $customer = User::factory()->create(['role' => UserRole::USER]);
        $admin = User::factory()->admin()->create();
        $booking = Booking::factory()->create([
            'user_id' => $customer->id,
            'project_location_id' => ProjectLocation::factory()->create(['user_id' => $customer->id])->id,
            'status' => BookingStatus::CONFIRMED->value,
            'payment_met_at' => now(),
        ]);
        $detail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-05',
        ]);
        BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $detail->id,
            'equipment_unit_id' => $unit->id,
            'status' => AssignmentStatus::ASSIGNED,
            'is_current' => true,
            'assigned_by' => $admin->id,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::USER]));

        // Overlapping period -> the only unit is committed -> model excluded.
        $overlap = $this->postJson('/api/v1/recommendations', [
            'project_type' => 'Galian Basah dan Drainase',
            'terrain_condition' => 'Lumpur / Rawa / Basah',
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-04',
        ])->assertCreated();
        $this->assertCount(0, $overlap->json('data.results'));

        // Non-overlapping period (buffer-aware, after buffer release) -> available.
        $clear = $this->postJson('/api/v1/recommendations', [
            'project_type' => 'Galian Basah dan Drainase',
            'terrain_condition' => 'Lumpur / Rawa / Basah',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-03',
        ])->assertCreated();
        $this->assertCount(1, $clear->json('data.results'));
        $this->assertSame(1, $clear->json('data.results.0.available_units'));
    }

    public function test_empty_criteria_uses_default_scores(): void
    {
        $type = $this->makeType('Excavator');
        $this->makeModel($type, 20.0, 250000);

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::USER]));
        $res = $this->postJson('/api/v1/recommendations', [
            'project_type' => 'Konstruksi Umum',
            'terrain_condition' => 'Tanah Normal / Standar',
        ])->assertCreated();

        $b = $res->json('data.results.0.score_breakdown');
        // No load capacity -> documented default 85.
        $this->assertEqualsWithDelta(85.0, $b['capacity_match']['score'], 0.001);
        $this->assertEqualsWithDelta(80.0, $b['terrain_suitability']['score'], 0.001);
    }

    public function test_ranking_and_tie_break_are_deterministic(): void
    {
        $type = $this->makeType('Excavator');
        $cheap = $this->makeModel($type, 20.0, 200000);
        $expensive = $this->makeModel($type, 20.0, 300000);

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::USER]));
        $res = $this->postJson('/api/v1/recommendations', [
            'project_type' => 'Galian Basah dan Drainase',
            'terrain_condition' => 'Lumpur / Rawa / Basah',
            'load_capacity' => 20.00,
        ])->assertCreated();

        $ids = array_column($res->json('data.results'), 'equipment_model_id');
        // Identical raw scores -> cheaper hourly rate wins.
        $this->assertSame($cheap->id, $ids[0]);
        $this->assertSame($expensive->id, $ids[1]);
        $this->assertEquals([1, 2], array_column($res->json('data.results'), 'rank'));
    }

    public function test_depth_and_reach_do_not_change_scores(): void
    {
        $type = $this->makeType('Excavator');
        $this->makeModel($type, 20.0, 250000);

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::USER]));
        $base = $this->postJson('/api/v1/recommendations', [
            'project_type' => 'Galian Basah dan Drainase',
            'terrain_condition' => 'Lumpur / Rawa / Basah',
            'load_capacity' => 20.00,
        ])->assertCreated();

        $withExtras = $this->postJson('/api/v1/recommendations', [
            'project_type' => 'Galian Basah dan Drainase',
            'terrain_condition' => 'Lumpur / Rawa / Basah',
            'load_capacity' => 20.00,
            'depth_requirement' => 8.00,
            'reach_requirement' => 12.00,
        ])->assertCreated();

        $this->assertSame(
            $base->json('data.results.0.match_score'),
            $withExtras->json('data.results.0.match_score')
        );
        $this->assertStringContainsString(
            'tidak dihitung dalam skor',
            $withExtras->json('data.results.0.reasoning_text')
        );
    }

    public function test_period_validation_requires_both_dates_in_order(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/recommendations', [
            'project_type' => 'Galian',
            'terrain_condition' => 'Lumpur',
            'start_date' => '2026-11-01',
        ])->assertStatus(422)->assertJsonValidationErrors(['end_date']);

        $this->postJson('/api/v1/recommendations', [
            'project_type' => 'Galian',
            'terrain_condition' => 'Lumpur',
            'start_date' => '2026-11-05',
            'end_date' => '2026-11-01',
        ])->assertStatus(422)->assertJsonValidationErrors(['end_date']);
    }
}
