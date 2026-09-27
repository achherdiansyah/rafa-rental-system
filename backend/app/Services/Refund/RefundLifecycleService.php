<?php

namespace App\Services\Refund;

use App\Enums\PaymentStatus;
use App\Enums\RefundSource;
use App\Enums\RefundStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Refund;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppNotifier;
use App\Support\AuditLogger;
use App\Support\FileSecurity;
use App\Support\NotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Manual refund lifecycle with approval:
 * PENDING --approve(OWNER)--> APPROVED --process(ADMIN)--> PROCESSING
 *          --complete--> COMPLETED | --fail--> FAILED.
 *
 * The nominal is validated against the refund base (source-derived); it is
 * NEVER mutated by any transition. Every change is audited; history is kept.
 */
class RefundLifecycleService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly WhatsAppNotifier $whatsapp
    ) {}

    /**
     * OWNER approval (Phase 1 permission matrix: approve-refund).
     *
     * @param  array<string, mixed>  $data
     */
    public function approve(User $owner, Refund $refund, array $data): Refund
    {
        Gate::forUser($owner)->authorize('approve', $refund);

        return DB::transaction(function () use ($owner, $refund, $data) {
            $this->assertStatus($refund, [RefundStatus::PENDING]);

            $this->assertWithinBase($refund);

            $refund->update([
                'status' => RefundStatus::APPROVED,
                'approval_reason' => $data['approval_reason'] ?? null,
                'approved_by' => $owner->id,
                'approved_at' => now(),
            ]);

            $this->audit('REFUND_APPROVED', $refund, $owner);
            $this->notifyCustomer('REFUND_APPROVED', $refund, 'Pengembalian dana Anda disetujui.');

            return $refund->fresh()->load(['invoice.booking.projectLocation', 'attachments']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function process(User $staff, Refund $refund, array $data): Refund
    {
        Gate::forUser($staff)->authorize('manage', $refund);

        return DB::transaction(function () use ($staff, $refund, $data) {
            $this->assertStatus($refund, [RefundStatus::APPROVED]);
            $this->assertWithinBase($refund);

            $refund->update([
                'status' => RefundStatus::PROCESSING,
                'customer_bank_info' => $data['customer_bank_info'],
                'transfer_reference' => $data['transfer_reference'] ?? null,
                'processed_by' => $staff->id,
                'processed_at' => now(),
            ]);

            $this->audit('REFUND_PROCESSING', $refund, $staff);
            $this->notifyCustomer('REFUND_PROCESSING', $refund, 'Refund Anda sedang diproses via transfer bank.');

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
            $this->notifyCustomer('REFUND_COMPLETED', $refund, 'Refund Anda telah ditransfer dan selesai.');

            $refund->loadMissing(['invoice.booking.user']);
            if ($customer = $refund->invoice?->booking?->user) {
                $this->whatsapp->notify(
                    $customer,
                    'REFUND_COMPLETED',
                    'Refund Anda telah dikirim dan selesai (invoice #'.$refund->invoice?->invoice_number.').'
                );
            }

            return $refund->fresh()->load(['invoice.booking.projectLocation', 'attachments']);
        });
    }

    public function fail(User $staff, Refund $refund, string $failureReason): Refund
    {
        Gate::forUser($staff)->authorize('manage', $refund);

        return DB::transaction(function () use ($staff, $refund, $failureReason) {
            $this->assertStatus($refund, [RefundStatus::PENDING, RefundStatus::APPROVED, RefundStatus::PROCESSING]);

            $refund->update([
                'status' => RefundStatus::FAILED,
                'failure_reason' => trim($failureReason),
            ]);

            $this->audit('REFUND_FAILED', $refund, $staff);
            $this->notifyCustomer('REFUND_FAILED', $refund, 'Refund Anda gagal: '.trim($failureReason).'.');

            return $refund->fresh()->load(['invoice.booking.projectLocation', 'attachments']);
        });
    }

    /**
     * Valid refund base for a source: overpayment = parked excess on invoice;
     * cancellation = sum of APPROVED payments on the invoice.
     */
    public function validBase(Refund $refund): float
    {
        $invoice = $refund->invoice;

        if ($refund->source === RefundSource::OVERPAYMENT) {
            return max(0.0, (float) $invoice?->overpayment_amount);
        }

        if ($refund->source === RefundSource::CANCELLATION) {
            if (! $invoice) {
                return 0.0;
            }

            return (float) $invoice->payments()
                ->where('status', PaymentStatus::APPROVED)
                ->sum('amount');
        }

        return 0.0;
    }

    /**
     * A refund can never exceed its valid base.
     */
    private function assertWithinBase(Refund $refund): void
    {
        $base = $this->validBase($refund);
        if ($base <= 0) {
            throw new BusinessRuleException('Dasar refund tidak valid: tidak ada nilai yang dapat dikembalikan.');
        }

        if ((float) $refund->amount > $base) {
            throw new BusinessRuleException(
                'Nominal refund melebihi dasar refund yang valid ('.number_format($base, 2).').'
            );
        }
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

    private function notifyCustomer(string $event, Refund $refund, string $message): void
    {
        $refund->loadMissing(['invoice.booking.user']);
        $this->notifications->send(
            $refund->invoice?->booking?->user,
            $event,
            $refund,
            $message
        );
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
