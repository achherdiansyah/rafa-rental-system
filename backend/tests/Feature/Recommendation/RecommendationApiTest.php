<?php

namespace Tests\Feature\Recommendation;

use App\Enums\RecommendationStatus;
use App\Enums\UserRole;
use App\Models\EquipmentModel;
use App\Models\EquipmentPrice;
use App\Models\EquipmentType;
use App\Models\RecommendationCriteria;
use App\Models\RecommendationRequest;
use App\Models\RecommendationResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecommendationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_submit_recommendation_request_and_receive_scored_results(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);

        // Setup equipment fleet
        $excavatorType = EquipmentType::factory()->create(['name' => 'Hydraulic Excavator']);
        $bulldozerType = EquipmentType::factory()->create(['name' => 'Bulldozer Crawler']);

        $model1 = EquipmentModel::factory()->create([
            'equipment_type_id' => $excavatorType->id,
            'brand' => 'Komatsu',
            'model_name' => 'PC200-8',
            'capacity_value' => 20.00,
            'is_active' => true,
        ]);
        EquipmentPrice::factory()->create(['equipment_model_id' => $model1->id]);

        $model2 = EquipmentModel::factory()->create([
            'equipment_type_id' => $bulldozerType->id,
            'brand' => 'Caterpillar',
            'model_name' => 'D85ESS',
            'capacity_value' => 28.00,
            'is_active' => true,
        ]);
        EquipmentPrice::factory()->create(['equipment_model_id' => $model2->id]);

        Sanctum::actingAs($user);

        $payload = [
            'project_type' => 'Galian Basah dan Drainase',
            'terrain_condition' => 'Lumpur / Rawa',
            'load_capacity' => 20.00,
            'work_volume' => 5000.00,
            'depth_requirement' => 4.50,
            'reach_requirement' => 9.50,
            'duration_days' => 14,
            'budget_range' => 'Rp 5.000.000 - Rp 10.000.000 / hari',
        ];

        $response = $this->postJson('/api/v1/recommendations/request', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'user_id',
                    'status',
                    'criteria' => [
                        'id',
                        'project_type',
                        'terrain_condition',
                        'load_capacity',
                        'work_volume',
                        'depth_requirement',
                        'reach_requirement',
                        'duration_days',
                    ],
                    'results' => [
                        '*' => [
                            'id',
                            'equipment_model_id',
                            'match_score',
                            'reasoning_text',
                            'model' => ['id', 'brand', 'model_name'],
                        ],
                    ],
                ],
            ]);

        $requestId = $response->json('data.id');

        // Database assertions
        $this->assertDatabaseHas('recommendation_requests', [
            'id' => $requestId,
            'user_id' => $user->id,
            'status' => RecommendationStatus::PROCESSED->value,
        ]);

        $this->assertDatabaseHas('recommendation_criteria', [
            'request_id' => $requestId,
            'project_type' => 'Galian Basah dan Drainase',
            'terrain_condition' => 'Lumpur / Rawa',
            'load_capacity' => 20.00,
        ]);

        $this->assertDatabaseHas('recommendation_results', [
            'request_id' => $requestId,
            'equipment_model_id' => $model1->id,
        ]);

        // Excavator should score higher for mud/wet excavation
        $results = $response->json('data.results');
        $this->assertNotEmpty($results);
        $topResult = $results[0];
        $this->assertEquals($model1->id, $topResult['equipment_model_id']);
        $this->assertGreaterThanOrEqual(80.0, $topResult['match_score']);
        $this->assertStringContainsString('Komatsu PC200-8', $topResult['reasoning_text']);
    }

    public function test_user_can_list_own_recommendation_history_and_not_other_users(): void
    {
        $user1 = User::factory()->create(['role' => UserRole::USER]);
        $user2 = User::factory()->create(['role' => UserRole::USER]);

        // Request belonging to user 1
        $req1 = RecommendationRequest::factory()->create(['user_id' => $user1->id]);
        RecommendationCriteria::factory()->create(['request_id' => $req1->id]);
        RecommendationResult::factory()->create(['request_id' => $req1->id]);

        // Request belonging to user 2
        $req2 = RecommendationRequest::factory()->create(['user_id' => $user2->id]);
        RecommendationCriteria::factory()->create(['request_id' => $req2->id]);
        RecommendationResult::factory()->create(['request_id' => $req2->id]);

        Sanctum::actingAs($user1);

        $response = $this->getJson('/api/v1/recommendations');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($req1->id, $response->json('data.0.id'));
    }

    public function test_user_can_view_own_recommendation_detail(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        $req = RecommendationRequest::factory()->create(['user_id' => $user->id]);
        $criteria = RecommendationCriteria::factory()->create(['request_id' => $req->id]);
        $result = RecommendationResult::factory()->create(['request_id' => $req->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/recommendations/{$req->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $req->id,
                    'user_id' => $user->id,
                    'criteria' => [
                        'id' => $criteria->id,
                    ],
                ],
            ]);
    }

    public function test_user_cannot_view_other_users_recommendation_detail(): void
    {
        $user1 = User::factory()->create(['role' => UserRole::USER]);
        $user2 = User::factory()->create(['role' => UserRole::USER]);

        $req = RecommendationRequest::factory()->create(['user_id' => $user2->id]);

        Sanctum::actingAs($user1);

        $response = $this->getJson("/api/v1/recommendations/{$req->id}");

        $response->assertStatus(403);
    }

    public function test_admin_and_owner_can_view_any_users_recommendation(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->owner()->create();
        $user = User::factory()->create(['role' => UserRole::USER]);

        $req = RecommendationRequest::factory()->create(['user_id' => $user->id]);
        RecommendationCriteria::factory()->create(['request_id' => $req->id]);

        // Admin view
        Sanctum::actingAs($admin);
        $resAdmin = $this->getJson("/api/v1/recommendations/{$req->id}");
        $resAdmin->assertStatus(200);

        // Owner view
        Sanctum::actingAs($owner);
        $resOwner = $this->getJson("/api/v1/recommendations/{$req->id}");
        $resOwner->assertStatus(200);
    }

    public function test_validation_fails_on_missing_required_fields(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        // Missing project_type and terrain_condition
        $response = $this->postJson('/api/v1/recommendations/request', [
            'load_capacity' => 15.00,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['project_type', 'terrain_condition']);
    }

    public function test_validation_fails_on_negative_numerical_inputs(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/recommendations/request', [
            'project_type' => 'Galian',
            'terrain_condition' => 'Lumpur',
            'load_capacity' => -10,
            'work_volume' => -500,
            'depth_requirement' => -2.5,
            'reach_requirement' => -5,
            'duration_days' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'load_capacity',
                'work_volume',
                'depth_requirement',
                'reach_requirement',
                'duration_days',
            ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/recommendations')->assertStatus(401);
        $this->postJson('/api/v1/recommendations/request', [])->assertStatus(401);
    }
}
