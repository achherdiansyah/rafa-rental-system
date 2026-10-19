<?php

namespace Tests\Concerns;

use App\Models\Attachment;
use App\Models\Timesheet;
use App\Models\User;
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

    protected function submitTimesheet(int $timesheetId, User $actor): void
    {
        $this->attachTimesheetPaper($timesheetId, $actor);
        Sanctum::actingAs($actor);
        $this->postJson("/api/v1/timesheets/{$timesheetId}/submit")->assertOk();
    }

    protected function approveTimesheet(int $timesheetId, User $admin): void
    {
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/timesheets/{$timesheetId}/approve")->assertOk();
    }

    /**
     * DRAFT -> paper -> SUBMIT -> ADMIN APPROVE (creates exactly one invoice).
     */
    protected function finalizeTimesheet(int $timesheetId, User $admin): void
    {
        $this->submitTimesheet($timesheetId, $admin);
        $this->approveTimesheet($timesheetId, $admin);
    }
}
