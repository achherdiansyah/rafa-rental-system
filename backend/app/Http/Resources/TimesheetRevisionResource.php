<?php

namespace App\Http\Resources;

use App\Models\TimesheetRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TimesheetRevision
 */
class TimesheetRevisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'timesheet_id' => $this->timesheet_id,
            'version' => $this->version,
            'old_start_time' => $this->old_start_time,
            'old_end_time' => $this->old_end_time,
            'old_start_hm' => $this->old_start_hm !== null ? (float) $this->old_start_hm : null,
            'old_end_hm' => $this->old_end_hm !== null ? (float) $this->old_end_hm : null,
            'revision_reason' => $this->revision_reason,
            'revised_by' => $this->revised_by,
            'revised_by_name' => $this->revisedByUser?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
