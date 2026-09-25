<?php

namespace App\Actions\ProjectLocation;

use App\Models\ProjectLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateProjectLocationAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, array $data): ProjectLocation
    {
        return DB::transaction(function () use ($user, $data) {
            $data['user_id'] = $user->id;

            return ProjectLocation::create($data);
        });
    }
}
