<?php

namespace App\Services\Refund;

use App\Enums\RefundStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Refund;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\FileSecurity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Manual refund lifecycle: PENDING -> PROCESSING -> COMPLETED / FAILED.
 * Transfers are executed by staff via bank transfer; no payment gateway.
 * Every transition is audited; history is never deleted.
 */
class RefundLifecycleService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function process(User $staff, Refund $refund, array $data): Refund
    {
        Gate::forUser($staff)->authorize('manage', $refund);

        return DB::transaction(function () use ($staff, $refund, $data) {
            $this->assertStatus($refund, [RefundStatus::PENDING]);

            $refund->update([
                'status' => RefundStatus::PROCESSING,
                'customer_bank_info' => $data['customer_bank_info'],
                'transfer_reference' => $data['transfer_reference'] ?? null,
                'processed_by' => $staff->id,
                'processed_at' => now(),
            ]);

            $this->audit('REFUND_PROCESSING', $refund, $staff);

            return $refund->fresh()->load(['invoice.booking.projectLocation', 'attachments']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function complete(User $staff, Refund $refund, array $data, UploadedFile $proof): Refund
    {
        Gate::forUser($staff)->authorize('manage', $refund);

        return DB::transaction(function () use ($staff, $refund, $data, $proof) {
            $this->assertStatus($refund, [RefundStatus::PROCESSING]);

            $security = FileSecurity::validate($proof);
            if (! $security['valid']) {
                throw new BusinessRuleException($security['error'] ?? 'Berkas bukti validasi gagal.');
            }

            $extension = strtolower($proof->getClientOriginalExtension());
            $securePath = FileSecurity::generateSecurePath('refunds', $extension);
            $proof->storeAs('', $securePath, 'local');

            $refund->attachments()->create([
                'document_type' => 'REFUND_PROOF',
                'file_path' => $securePath,
                'file_name' => $proof->getClientOriginalName(),
                'mime_type' => $proof->getMimeType(),
                'file_size' => $proof->getSize(),
                'uploaded_by' => $staff->id,
            ]);

            $refund->update([
                'status' => RefundStatus::COMPLETED,
                'transfer_reference' => $data['transfer_reference'] ?? $refund->transfer_reference,
                'completed_at' => now(),
            ]);

            $this->audit('REFUND_COMPLETED', $refund, $staff);

            return $refund->fresh()->load(['invoice.booking.projectLocation', 'attachments']);
        });
    }

    public function fail(User $staff, Refund $refund, string $failureReason): Refund
    {
        Gate::forUser($staff)->authorize('manage', $refund);

        return DB::transaction(function () use ($staff, $refund, $failureReason) {
            $this->assertStatus($refund, [RefundStatus::PENDING, RefundStatus::PROCESSING]);

            $refund->update([
                'status' => RefundStatus::FAILED,
                'failure_reason' => trim($failureReason),
            ]);

            $this->audit('REFUND_FAILED', $refund, $staff);

            return $refund->fresh()->load(['invoice.booking.projectLocation', 'attachments']);
        });
    }

    /**
     * @param  RefundStatus[]  $allowed
     */
    private function assertStatus(Refund $refund, array $allowed): void
    {
        if (! in_array($refund->status, $allowed, true)) {
            throw new InvalidStateTransitionException(
                'Transisi refund tidak valid dari status '.$refund->status->value.'.'
            );
        }
    }

    private function audit(string $event, Refund $refund, User $actor): void
    {
        AuditLogger::log($event, $refund, [], [
            'status' => $refund->status->value,
            'amount' => $refund->amount,
            'invoice_id' => $refund->invoice_id,
            'actor_id' => $actor->id,
        ]);
    }
}
