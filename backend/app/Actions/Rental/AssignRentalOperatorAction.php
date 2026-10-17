<?php

namespace App\Actions\Rental;

use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Exceptions\BusinessRuleException;
use App\Models\Rental;
use App\Models\RentalDetail;
use App\Models\RentalOperatorAssignment;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\NotificationService;
use Illuminate\Support\Facades\DB;

class AssignRentalOperatorAction
{
    public function __construct(
        private readonly NotificationService $notifications
    ) {}

    public function assign(User $admin, Rental $rental, int $rentalDetailId, int $operatorId): RentalOperatorAssignment
    {
        return DB::transaction(function () use ($admin, $rental, $rentalDetailId, $operatorId) {
            $this->assertRentalOperable($rental);
            $operator = $this->operator($operatorId);

            /** @var RentalDetail|null $detail */
            $detail = RentalDetail::with('assignment')
                ->whereKey($rentalDetailId)
                ->where('rental_id', $rental->id)
                ->lockForUpdate()
                ->first();

            if (! $detail) {
                throw new BusinessRuleException('Detail rental tidak sesuai dengan rental ini.');
            }

            if (! $detail->assignment || ! $detail->assignment->is_current) {
                throw new BusinessRuleException('Unit fisik untuk detail rental ini belum ditugaskan.');
            }

            $active = RentalOperatorAssignment::where('rental_detail_id', $detail->id)
                ->where('status', RentalOperatorAssignment::ACTIVE)
                ->lockForUpdate()
                ->first();

            if ($active) {
                throw new BusinessRuleException(
                    'Unit rental ini sudah memiliki Operator aktif. Gunakan pergantian Operator.'
                );
            }

            $assignment = RentalOperatorAssignment::create([
                'rental_id' => $rental->id,
                'rental_detail_id' => $detail->id,
                'operator_user_id' => $operator->id,
                'assigned_by' => $admin->id,
                'started_at' => now(),
                'status' => RentalOperatorAssignment::ACTIVE,
            ]);

            AuditLogger::log('OPERATOR_ASSIGNED', $assignment, [], [
                'rental_id' => $rental->id,
                'rental_detail_id' => $detail->id,
                'operator_user_id' => $operator->id,
                'assigned_by' => $admin->id,
            ]);

            $this->notifications->send(
                $operator,
                'OPERATOR_ASSIGNED',
                $assignment,
                'Anda ditugaskan sebagai Operator untuk unit rental.'
            );

            return $assignment->load(['rental.booking.projectLocation', 'rentalDetail.assignment.unit', 'operator']);
        });
    }

    public function replace(User $admin, RentalOperatorAssignment $current, int $operatorId, string $reason): RentalOperatorAssignment
    {
        if (trim($reason) === '') {
            throw new BusinessRuleException('Alasan pergantian Operator wajib diisi.');
        }

        return DB::transaction(function () use ($admin, $current, $operatorId, $reason) {
            $this->assertRentalOperable($current->rental);
            $operator = $this->operator($operatorId);

            // Lock the rental detail to serialize concurrent replacements.
            RentalDetail::whereKey($current->rental_detail_id)->lockForUpdate()->first();

            /** @var RentalOperatorAssignment $active */
            $active = RentalOperatorAssignment::whereKey($current->id)
                ->where('status', RentalOperatorAssignment::ACTIVE)
                ->lockForUpdate()
                ->first();

            if (! $active) {
                throw new BusinessRuleException('Penugasan Operator tersebut sudah tidak aktif.');
            }

            $active->update([
                'status' => RentalOperatorAssignment::ENDED,
                'ended_at' => now(),
                'end_reason' => $reason,
                'ended_by' => $admin->id,
            ]);

            $replacement = RentalOperatorAssignment::create([
                'rental_id' => $active->rental_id,
                'rental_detail_id' => $active->rental_detail_id,
                'operator_user_id' => $operator->id,
                'assigned_by' => $admin->id,
                'started_at' => now(),
                'status' => RentalOperatorAssignment::ACTIVE,
            ]);

            AuditLogger::log('OPERATOR_REPLACED', $replacement, [
                'previous_assignment_id' => $active->id,
                'previous_operator_id' => $active->operator_user_id,
            ], [
                'new_operator_id' => $operator->id,
                'reason' => $reason,
                'assigned_by' => $admin->id,
            ]);

            $this->notifications->send(
                $operator,
                'OPERATOR_ASSIGNED',
                $replacement,
                'Anda ditugaskan sebagai Operator pengganti untuk unit rental.'
            );

            return $replacement->load(['rental.booking.projectLocation', 'rentalDetail.assignment.unit', 'operator']);
        });
    }

    public function end(User $admin, RentalOperatorAssignment $assignment, string $reason): RentalOperatorAssignment
    {
        if (trim($reason) === '') {
            throw new BusinessRuleException('Alasan pengakhiran penugasan wajib diisi.');
        }

        return DB::transaction(function () use ($admin, $assignment, $reason) {
            RentalDetail::whereKey($assignment->rental_detail_id)->lockForUpdate()->first();

            /** @var RentalOperatorAssignment|null $active */
            $active = RentalOperatorAssignment::whereKey($assignment->id)
                ->where('status', RentalOperatorAssignment::ACTIVE)
                ->lockForUpdate()
                ->first();

            if (! $active) {
                throw new BusinessRuleException('Penugasan Operator tersebut sudah tidak aktif.');
            }

            $active->update([
                'status' => RentalOperatorAssignment::ENDED,
                'ended_at' => now(),
                'end_reason' => $reason,
                'ended_by' => $admin->id,
            ]);

            AuditLogger::log('OPERATOR_ASSIGNMENT_ENDED', $active, [], [
                'reason' => $reason,
                'ended_by' => $admin->id,
            ]);

            return $active->fresh();
        });
    }

    private function assertRentalOperable(Rental $rental): void
    {
        if (in_array($rental->status, [RentalStatus::COMPLETED, RentalStatus::CANCELLED], true)) {
            throw new BusinessRuleException('Rental sudah selesai/dibatalkan; Operator tidak dapat ditugaskan.');
        }

        $rental->loadMissing('booking');

        if (! $rental->booking?->payment_met_at) {
            throw new BusinessRuleException(
                'Pembayaran MOB/DEMOB belum diverifikasi; Operator hanya dapat ditugaskan setelah pembayaran sah.'
            );
        }
    }

    private function operator(int $operatorId): User
    {
        $operator = User::whereKey($operatorId)->where('role', UserRole::OPERATOR->value)->first();

        if (! $operator || ! $operator->is_active) {
            throw new BusinessRuleException('Akun Operator tidak valid atau tidak aktif.');
        }

        return $operator;
    }
}