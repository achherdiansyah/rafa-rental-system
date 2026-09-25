<?php

namespace Tests\Unit\Services;

use App\Enums\EquipmentStatus;
use App\Models\EquipmentModel;
use App\Models\EquipmentPrice;
use App\Models\EquipmentType;
use App\Models\EquipmentUnit;
use App\Models\RecommendationCriteria;
use App\Models\RecommendationRequest;
use App\Models\User;
use App\Services\Recommendation\RecommendationScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RecommendationScoringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RecommendationScoringService::class);
    }

    private function addUnit(EquipmentModel $model): void
    {
        EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);
    }

    public function test_evaluates_and_ranks_models_by_weighted_score(): void
    {
        $excavatorType = EquipmentType::factory()->create(['name' => 'Hydraulic Excavator']);
        $bulldozerType = EquipmentType::factory()->create(['name' => 'Bulldozer Crawler']);

        // Excavator model - 20 Ton (Matches 20 Ton request)
        $excavator = EquipmentModel::factory()->create([
            'equipment_type_id' => $excavatorType->id,
            'brand' => 'Komatsu',
            'model_name' => 'PC200-8',
            'capacity_value' => 20.00,
            'capacity_unit' => 'Ton',
            'is_active' => true,
        ]);
        EquipmentPrice::factory()->create(['equipment_model_id' => $excavator->id, 'base_rate' => 250000.00]);
        $this->addUnit($excavator);

        // Bulldozer model - 28 Ton
        $bulldozer = EquipmentModel::factory()->create([
            'equipment_type_id' => $bulldozerType->id,
            'brand' => 'Caterpillar',
            'model_name' => 'D85ESS',
            'capacity_value' => 28.00,
            'capacity_unit' => 'Ton',
            'is_active' => true,
        ]);
        EquipmentPrice::factory()->create(['equipment_model_id' => $bulldozer->id, 'base_rate' => 450000.00]);
        $this->addUnit($bulldozer);

        $user = User::factory()->create();
        $request = RecommendationRequest::factory()->create(['user_id' => $user->id]);
        $criteria = RecommendationCriteria::factory()->create([
            'request_id' => $request->id,
            'project_type' => 'Galian Basah dan Drainase',
            'terrain_condition' => 'Lumpur / Rawa',
            'load_capacity' => 20.00,
        ]);

        $results = $this->service->evaluate($criteria);

        $this->assertNotEmpty($results);
        $this->assertEquals($excavator->id, $results[0]['model']->id);
        $this->assertGreaterThan($results[1]['score'], $results[0]['score']);
    }

    public function test_deterministic_scoring_produces_identical_results_across_runs(): void
    {
        $type = EquipmentType::factory()->create(['name' => 'Excavator']);
        $model = EquipmentModel::factory()->create([
            'equipment_type_id' => $type->id,
            'capacity_value' => 20.00,
            'is_active' => true,
        ]);
        EquipmentPrice::factory()->create(['equipment_model_id' => $model->id]);
        $this->addUnit($model);

        $user = User::factory()->create();
        $request = RecommendationRequest::factory()->create(['user_id' => $user->id]);
        $criteria = RecommendationCriteria::factory()->create([
            'request_id' => $request->id,
            'project_type' => 'Konstruksi',
            'terrain_condition' => 'Tanah Keras',
            'load_capacity' => 20.00,
        ]);

        $run1 = $this->service->evaluate($criteria);
        $run2 = $this->service->evaluate($criteria);

        $this->assertEquals($run1[0]['score'], $run2[0]['score']);
        $this->assertEquals($run1[0]['reasoning'], $run2[0]['reasoning']);
        $this->assertEquals($run1[0]['breakdown'], $run2[0]['breakdown']);
    }

    public function test_tie_breaking_prioritizes_lowest_hourly_base_rate(): void
    {
        $type = EquipmentType::factory()->create(['name' => 'Excavator']);

        // Model A: Cheaper (200k/hr)
        $modelA = EquipmentModel::factory()->create([
            'equipment_type_id' => $type->id,
            'brand' => 'Komatsu',
            'model_name' => 'PC200-A',
            'capacity_value' => 20.00,
            'is_active' => true,
        ]);
        EquipmentPrice::factory()->create(['equipment_model_id' => $modelA->id, 'base_rate' => 200000.00]);
        $this->addUnit($modelA);

        // Model B: More expensive (300k/hr)
        $modelB = EquipmentModel::factory()->create([
            'equipment_type_id' => $type->id,
            'brand' => 'Kobelco',
            'model_name' => 'SK200-B',
            'capacity_value' => 20.00,
            'is_active' => true,
        ]);
        EquipmentPrice::factory()->create(['equipment_model_id' => $modelB->id, 'base_rate' => 300000.00]);
        $this->addUnit($modelB);

        $user = User::factory()->create();
        $request = RecommendationRequest::factory()->create(['user_id' => $user->id]);
        $criteria = RecommendationCriteria::factory()->create([
            'request_id' => $request->id,
            'project_type' => 'Konstruksi Standar',
            'terrain_condition' => 'Tanah Normal',
            'load_capacity' => 20.00,
        ]);

        $results = $this->service->evaluate($criteria);

        // Scores are equal because identical specs and types
        $this->assertEquals($results[0]['score'], $results[1]['score']);
        // But Model A ranks higher due to lower base rate
        $this->assertEquals($modelA->id, $results[0]['model']->id);
        $this->assertEquals($modelB->id, $results[1]['model']->id);
    }

    public function test_inactive_models_are_excluded_from_recommendations(): void
    {
        $type = EquipmentType::factory()->create(['name' => 'Excavator']);

        $activeModel = EquipmentModel::factory()->create([
            'equipment_type_id' => $type->id,
            'is_active' => true,
        ]);
        EquipmentPrice::factory()->create(['equipment_model_id' => $activeModel->id]);
        $this->addUnit($activeModel);

        $inactiveModel = EquipmentModel::factory()->create([
            'equipment_type_id' => $type->id,
            'is_active' => false,
        ]);
        EquipmentPrice::factory()->create(['equipment_model_id' => $inactiveModel->id]);
        $this->addUnit($inactiveModel);

        $user = User::factory()->create();
        $request = RecommendationRequest::factory()->create(['user_id' => $user->id]);
        $criteria = RecommendationCriteria::factory()->create(['request_id' => $request->id]);

        $results = $this->service->evaluate($criteria);

        $modelIds = $results->pluck('model.id')->all();
        $this->assertContains($activeModel->id, $modelIds);
        $this->assertNotContains($inactiveModel->id, $modelIds);
    }

    public function test_normalizes_weights_when_criteria_are_disabled(): void
    {
        // Temporarily disable price suitability
        config([
            'recommendation.criteria.price_suitability.is_active' => false,
        ]);

        $type = EquipmentType::factory()->create(['name' => 'Excavator']);
        $model = EquipmentModel::factory()->create([
            'equipment_type_id' => $type->id,
            'capacity_value' => 20.00,
            'is_active' => true,
        ]);
        $this->addUnit($model);

        $user = User::factory()->create();
        $request = RecommendationRequest::factory()->create(['user_id' => $user->id]);
        $criteria = RecommendationCriteria::factory()->create([
            'request_id' => $request->id,
            'load_capacity' => 20.00,
        ]);

        $results = $this->service->evaluate($criteria);

        $this->assertNotEmpty($results);
        $breakdown = $results[0]['breakdown'];

        // price_suitability should not be present in breakdown
        $this->assertArrayNotHasKey('price_suitability', $breakdown);

        // Sum of normalized weights should equal 1.0
        $sumWeights = array_sum(array_column($breakdown, 'weight'));
        $this->assertEqualsWithDelta(1.0, $sumWeights, 0.001);
    }

    public function test_handles_zero_capacity_and_edge_values_gracefully(): void
    {
        $type = EquipmentType::factory()->create(['name' => 'Excavator']);
        $model = EquipmentModel::factory()->create([
            'equipment_type_id' => $type->id,
            'capacity_value' => 20.00,
            'is_active' => true,
        ]);
        EquipmentPrice::factory()->create(['equipment_model_id' => $model->id]);
        $this->addUnit($model);

        $user = User::factory()->create();
        $request = RecommendationRequest::factory()->create(['user_id' => $user->id]);

        // Zero capacity, null depth, null reach
        $criteria = RecommendationCriteria::factory()->create([
            'request_id' => $request->id,
            'load_capacity' => 0.00,
            'depth_requirement' => null,
            'reach_requirement' => null,
            'work_volume' => null,
        ]);

        $results = $this->service->evaluate($criteria);

        $this->assertNotEmpty($results);
        $this->assertGreaterThan(50.0, $results[0]['score']);
    }

    public function test_explanation_is_traceable_to_actual_criteria(): void
    {
        $type = EquipmentType::factory()->create(['name' => 'Hydraulic Excavator']);
        $model = EquipmentModel::factory()->create([
            'equipment_type_id' => $type->id,
            'brand' => 'Komatsu',
            'model_name' => 'PC210',
            'capacity_value' => 21.00,
            'capacity_unit' => 'Ton',
            'is_active' => true,
        ]);
        EquipmentPrice::factory()->create(['equipment_model_id' => $model->id]);
        $this->addUnit($model);

        $user = User::factory()->create();
        $request = RecommendationRequest::factory()->create(['user_id' => $user->id]);
        $criteria = RecommendationCriteria::factory()->create([
            'request_id' => $request->id,
            'project_type' => 'Galian Bendungan',
            'terrain_condition' => 'Bebatuan Keras',
            'load_capacity' => 20.00,
            'depth_requirement' => 6.00,
            'reach_requirement' => 10.00,
        ]);

        $results = $this->service->evaluate($criteria);

        $reasoning = $results[0]['reasoning'];
        $this->assertStringContainsString('Komatsu PC210', $reasoning);
        $this->assertStringContainsString('21.00 Ton', $reasoning);
        $this->assertStringContainsString('Bebatuan Keras', $reasoning);
        $this->assertStringContainsString('Galian Bendungan', $reasoning);
        $this->assertStringContainsString('6.00 meter', $reasoning);
        $this->assertStringContainsString('10.00 meter', $reasoning);
    }
}
