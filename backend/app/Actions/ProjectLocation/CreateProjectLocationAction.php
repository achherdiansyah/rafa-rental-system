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

            /** @var ProjectLocation $location */
            $location = ProjectLocation::create($data);

            // Reload from DB so server-side column defaults (is_active => true)
            // are reflected in the model before the resource is serialized.
            // Without refresh(), is_active is null on the instance, so the
            // create response would wrongly say is_active=false while the DB
            // row actually stores true.
            return $location->refresh();
        });
    }
}
