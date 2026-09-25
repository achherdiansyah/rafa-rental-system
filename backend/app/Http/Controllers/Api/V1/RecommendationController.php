<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Recommendation\CreateRecommendationAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Recommendation\StoreRecommendationRequest;
use App\Http\Resources\RecommendationRequestResource;
use App\Models\RecommendationRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RecommendationController extends ApiController
{
    /**
     * List recommendation history.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = RecommendationRequest::with([
            'criteria',
            'results.model' => function ($q) {
                $q->with(['type', 'prices', 'attachments'])->withCount('units');
            },
        ])->latest();

        // Regular users can only access their own history
        if (! $user->isAdmin() && ! $user->isOwner()) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);
        $paginated = $query->paginate($perPage);

        return $this->success(
            RecommendationRequestResource::collection($paginated->items()),
            'Daftar riwayat rekomendasi berhasil dimuat.',
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
     * Request new equipment recommendation using rule-based scoring engine.
     */
    public function requestRecommendation(
        StoreRecommendationRequest $request,
        CreateRecommendationAction $action
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $recommendationRequest = $action->execute($user, $request->validated());

        return $this->created(
            new RecommendationRequestResource($recommendationRequest),
            'Rekomendasi armada alat berat berhasil diproses.'
        );
    }

    /**
     * Show detail of a recommendation request and its scored results.
     */
    public function show(Request $request, RecommendationRequest $recommendationRequest): JsonResponse
    {
        Gate::authorize('view', $recommendationRequest);

        $recommendationRequest->load([
            'criteria',
            'results.model' => function ($q) {
                $q->with(['type', 'prices', 'attachments'])->withCount('units');
            },
        ]);

        return $this->success(
            new RecommendationRequestResource($recommendationRequest),
            'Detail rekomendasi alat berat berhasil dimuat.'
        );
    }
}
