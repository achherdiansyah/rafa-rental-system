<?php

namespace App\Actions\ProjectLocation;

use App\Models\ProjectLocation;
use Illuminate\Support\Facades\DB;

class UpdateProjectLocationAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(ProjectLocation $projectLocation, array $data): ProjectLocation
    {
        return DB::transaction(function () use ($projectLocation, $data) {
            $projectLocation->update($data);

            return $projectLocation->fresh();
        });
    }
}
