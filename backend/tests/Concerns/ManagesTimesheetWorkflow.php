<?php

namespace Tests\Concerns;

use App\Models\Attachment;
use App\Models\RentalDetail;
use App\Models\RentalOperatorAssignment;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

trait ManagesTimesheetWorkflow
{
    protected function attachTimesheetPaper(int $timesheetId, User $actor): Attachment
    {
        return Attachment::create([
            'attachable_type' => Timesheet::class,
            'attachable_id' => $timesheetId,
            'document_type' => 'TIMESHEET_PAPER',
            'file_path' => 'tests/paper-'.$timesheetId.'.jpg',
            'file_name' => 'paper.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
            'uploaded_by' => $actor->id,
        ]);
    }

    protected function operatorForRentalDetail(int $rentalDetailId): User
    {
        $detail = RentalDetail::findOrFail($rentalDetailId);

        $existing = RentalOperatorAssignment::where('rental_detail_id', $detail->id)
            ->where('status', RentalOperatorAssignment::ACTIVE)
            ->first();

        if ($existing) {
            return User::findOrFail($existing->operator_user_id);
        }

        $admin = User::where('role', 'ADMIN')->first() ?? User::factory()->admin()->create();
        $operator = User::factory()->operator()->create();

        RentalOperatorAssignment::create([
            'rental_id' => $detail->rental_id,
            'rental_detail_id' => $detail->id,
            'operator_user_id' => $operator->id,
            'assigned_by' => $admin->id,
            'started_at' => now(),
            'status' => RentalOperatorAssignment::ACTIVE,
        ]);

        return $operator;
    }

    /**
     * Existing admin-created test requests are executed through the real
     * Operator creation endpoint (with a real active assignment). Unauthorized
     * USER/guest requests are left untouched so authorization tests stay valid.
     */
    protected function createTimesheetAsOperator(array $payload, ?User $operator = null): TestResponse
    {
        $current = auth()->user();

        if ($current && ! $current->isAdmin()) {
            return $this->postJson('/api/v1/timesheets', $payload);
        }

        $operator ??= $this->operatorForRentalDetail((int) $payload['rental_detail_id']);
        Sanctum::actingAs($operator);

        return $this->postJson('/api/v1/timesheets', $payload);
    }

    protected function submitTimesheet(int $timesheetId, ?User $actor = null): void
    {
        $timesheet = Timesheet::findOrFail($timesheetId);
        $operator = $actor?->isOperator()
            ? $actor
            : $this->operatorForRentalDetail((int) $timesheet->rental_detail_id);

        if (! $timesheet->attachments()->where('document_type', 'TIMESHEET_PAPER')->exists()) {
            $this->attachTimesheetPaper($timesheetId, $operator);
        }

        Sanctum::actingAs($operator);
        $this->postJson("/api/v1/timesheets/{$timesheetId}/submit")->assertOk();
    }

    protected function approveTimesheet(int $timesheetId, User $admin): void
    {
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/timesheets/{$timesheetId}/approve")->assertOk();
    }

    /**
     * DRAFT -> paper -> SUBMIT (Operator) -> APPROVE (Admin) -> exactly one invoice.
     */
    protected function finalizeTimesheet(int $timesheetId, User $admin): void
    {
        $this->submitTimesheet($timesheetId);
        $this->approveTimesheet($timesheetId, $admin);
    }
}

