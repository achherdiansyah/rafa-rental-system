<?php

namespace App\Actions\ProjectLocation;

use App\Exceptions\ResourceConflictException;
use App\Models\ProjectLocation;
use Illuminate\Support\Facades\DB;

class DeleteProjectLocationAction
{
    public function execute(ProjectLocation $projectLocation): void
    {
        DB::transaction(function () use ($projectLocation) {
            // Check if there are active bookings associated with this location.
            // Based on ERD, bookings belong to a project location.
            // We soft-delete the location if it is not heavily tied to an active rental,
            // or we might restrict it entirely. The migration restricts deletion natively.
            // Let's rely on native restricted constraint and catch the exception if needed,
            // but for soft deletes, Laravel doesn't trigger SQL FK violation by default.
            // Let's implement a manual check to prevent deleting locations with ACTIVE bookings

            $hasActiveBookings = DB::table('bookings')
                ->where('project_location_id', $projectLocation->id)
                ->whereNull('deleted_at')
                ->whereNotIn('status', ['CANCELLED', 'REJECTED', 'EXPIRED'])
                ->exists();

            if ($hasActiveBookings) {
                throw new ResourceConflictException(
                    'Tidak dapat menghapus lokasi proyek yang memiliki pesanan sewa aktif.'
                );
            }

            $projectLocation->delete();
        });
    }
}
