<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ProjectLocation\CreateProjectLocationAction;
use App\Actions\ProjectLocation\DeleteProjectLocationAction;
use App\Actions\ProjectLocation\UpdateProjectLocationAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\ProjectLocation\StoreProjectLocationRequest;
use App\Http\Requests\ProjectLocation\UpdateProjectLocationRequest;
use App\Http\Resources\ProjectLocationResource;
use App\Models\ProjectLocation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectLocationController extends ApiController
{
    /**
     * List project locations.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = ProjectLocation::query()->latest();

        // Regular users only see their own locations
        if (! $user->isAdmin() && ! $user->isOwner()) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('project_name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('pic_name', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);
        $paginated = $query->paginate($perPage);

        return $this->success(
            ProjectLocationResource::collection($paginated->items()),
            'Daftar lokasi proyek berhasil dimuat.',
            200,
            [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ]
        );
    }

    /**
     * Store new project location.
     */
    public function store(StoreProjectLocationRequest $request, CreateProjectLocationAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $location = $action->execute($user, $request->validated());

        return $this->created(
            new ProjectLocationResource($location),
            'Lokasi proyek berhasil didaftarkan.'
        );
    }

    /**
     * Show single project location detail.
     */
    public function show(Request $request, ProjectLocation $projectLocation): JsonResponse
    {
        Gate::authorize('view', $projectLocation);

        return $this->success(
            new ProjectLocationResource($projectLocation),
            'Detail lokasi proyek berhasil dimuat.'
        );
    }

    /**
     * Update existing project location.
     */
    public function update(
        UpdateProjectLocationRequest $request,
        ProjectLocation $projectLocation,
        UpdateProjectLocationAction $action
    ): JsonResponse {
        Gate::authorize('update', $projectLocation);

        $updated = $action->execute($projectLocation, $request->validated());

        return $this->success(
            new ProjectLocationResource($updated),
            'Lokasi proyek berhasil diperbarui.'
        );
    }

    /**
     * Delete / deactivate project location.
     */
    public function destroy(
        Request $request,
        ProjectLocation $projectLocation,
        DeleteProjectLocationAction $action
    ): JsonResponse {
        Gate::authorize('delete', $projectLocation);

        $action->execute($projectLocation);

        return $this->success(
            null,
            'Lokasi proyek berhasil dihapus.'
        );
    }
}
