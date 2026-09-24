<?php

namespace Tests\Feature\Domain;

use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\BusinessCalendar;
use App\Models\CustomerProfile;
use App\Models\EquipmentModel;
use App\Models\Payment;
use App\Models\RecommendationCriteria;
use App\Models\RecommendationRequest;
use App\Models\RecommendationResult;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportingDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendation_request_has_criteria_and_results(): void
    {
        $user = User::factory()->create();
        $request = RecommendationRequest::factory()->create(['user_id' => $user->id]);

        $criteria = RecommendationCriteria::factory()->create(['request_id' => $request->id]);

        $model1 = EquipmentModel::factory()->create();
        $model2 = EquipmentModel::factory()->create();

        $res1 = RecommendationResult::factory()->create([
            'request_id' => $request->id,
            'equipment_model_id' => $model1->id,
            'match_score' => 95.50,
        ]);

        $res2 = RecommendationResult::factory()->create([
            'request_id' => $request->id,
            'equipment_model_id' => $model2->id,
            'match_score' => 88.00,
        ]);

        $this->assertTrue($request->criteria->is($criteria));
        $this->assertCount(2, $request->results);
        $this->assertEquals('95.50', $res1->match_score);
        $this->assertEquals('88.00', $res2->match_score);
    }

    public function test_attachment_polymorphic_relation(): void
    {
        $user = User::factory()->create();
        $profile = CustomerProfile::factory()->create(['user_id' => $user->id]);

        $attachment = Attachment::factory()->create([
            'attachable_type' => CustomerProfile::class,
            'attachable_id' => $profile->id,
            'document_type' => 'KTP',
            'uploaded_by' => $user->id,
        ]);

        $this->assertInstanceOf(CustomerProfile::class, $attachment->attachable);
        $this->assertTrue($attachment->attachable->is($profile));
        $this->assertTrue($attachment->uploadedByUser->is($user));
    }

    public function test_activity_log_polymorphic_relation(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->create();

        $log = ActivityLog::factory()->create([
            'log_name' => 'financial',
            'description' => 'Payment approved',
            'subject_type' => Payment::class,
            'subject_id' => $payment->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
            'properties' => ['old_status' => 'PENDING', 'new_status' => 'APPROVED'],
        ]);

        $this->assertInstanceOf(Payment::class, $log->subject);
        $this->assertTrue($log->subject->is($payment));
        $this->assertInstanceOf(User::class, $log->causer);
        $this->assertTrue($log->causer->is($user));
        $this->assertEquals('PENDING', $log->properties['old_status']);
    }

    public function test_business_calendar_date_is_unique(): void
    {
        BusinessCalendar::factory()->create(['calendar_date' => '2026-08-17']);

        $this->expectException(QueryException::class);
        BusinessCalendar::factory()->create(['calendar_date' => '2026-08-17']);
    }

    public function test_business_calendar_holiday_state(): void
    {
        $calendar = BusinessCalendar::factory()->holiday()->create([
            'calendar_date' => '2026-08-17',
            'holiday_name' => 'Hari Kemerdekaan RI',
        ]);

        $this->assertFalse($calendar->is_working_day);
        $this->assertEquals('Hari Kemerdekaan RI', $calendar->holiday_name);
    }
}
