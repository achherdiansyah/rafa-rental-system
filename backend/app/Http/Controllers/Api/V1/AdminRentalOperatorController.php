<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Rental\AssignRentalOperatorAction;
use App\Http\Controllers\Api\ApiController;
use App\Models\Rental;
use App\Models\RentalOperatorAssignment;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminRentalOperatorController extends ApiController
{
    public function index(Request $request, Rental $rental): JsonResponse
    {
        $assignments = $rental->operatorAssignments()
            ->with(['operator:id,name,phone_number', 'rentalDetail.assignment.unit', 'assignedByUser:id,name'])
            ->latest()
            ->get()
            ->map(fn (RentalOperatorAssignment $a) => [
                'id' => $a->id,
                'rental_id' => $a->rental_id,
                'rental_detail_id' => $a->rental_detail_id,
                'operator_user_id' => $a->operator_user_id,
                'operator_name' => $a->operator?->name,
                'operator_phone' => $a->operator?->phone_number,
                'unit_serial' => $a->rentalDetail?->assignment?->unit?->serial_number,
                'assigned_by_name' => $a->assignedByUser?->name,
                'started_at' => $a->started_at?->toIso8601String(),
                'ended_at' => $a->ended_at?->toIso8601String(),
                'status' => $a->status,
                'end_reason' => $a->end_reason,
            ]);

        return $this->success($assignments, 'Riwayat penugasan Operator berhasil dimuat.');
    }

    public function store(Request $request, Rental $rental, AssignRentalOperatorAction $action): JsonResponse
    {
        $validated = $request->validate([
            'rental_detail_id' => ['required', 'integer', 'exists:rental_details,id'],
            'operator_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $assignment = $action->assign(
            $request->user(),
            $rental,
            (int) $validated['rental_detail_id'],
            (int) $validated['operator_user_id']
        );

        return $this->created($this->payload($assignment), 'Operator berhasil ditugaskan.');
    }

    public function replace(Request $request, RentalOperatorAssignment $assignment, AssignRentalOperatorAction $action): JsonResponse
    {
        $validated = $request->validate([
            'operator_user_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $replacement = $action->replace(
            $request->user(),
            $assignment,
            (int) $validated['operator_user_id'],
            $validated['reason']
        );

        return $this->success($this->payload($replacement), 'Operator berhasil diganti.');
    }

    public function end(Request $request, RentalOperatorAssignment $assignment, AssignRentalOperatorAction $action): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $ended = $action->end($request->user(), $assignment, $validated['reason']);

        return $this->success($this->payload($ended), 'Penugasan Operator diakhiri.');
    }

    public function operators(): JsonResponse
    {
        $operators = User::where('role', UserRole::OPERATOR->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone_number', 'email'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'phone_number' => $u->phone_number,
                'email' => $u->email,
            ]);

        return $this->success($operators, 'Daftar Operator berhasil dimuat.');
    }

    private function payload(RentalOperatorAssignment $assignment): array
    {
        $assignment->loadMissing(['operator', 'rentalDetail.assignment.unit']);

        return [
            'id' => $assignment->id,
            'rental_id' => $assignment->rental_id,
            'rental_detail_id' => $assignment->rental_detail_id,
            'operator_user_id' => $assignment->operator_user_id,
            'operator_name' => $assignment->operator?->name,
            'unit_serial' => $assignment->rentalDetail?->assignment?->unit?->serial_number,
            'status' => $assignment->status,
            'started_at' => $assignment->started_at?->toIso8601String(),
            'ended_at' => $assignment->ended_at?->toIso8601String(),
            'end_reason' => $assignment->end_reason,
        ];
    }
}