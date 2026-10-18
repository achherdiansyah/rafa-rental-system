<?php

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\RentalStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Invoice;
use App\Models\Rental;
use App\Models\RentalOperatorAssignment;
use App\Models\RentalPaymentHold;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Evaluates and records PAYMENT HOLD for a rental. A hold is a separate record:
 * it never changes rental lifecycle status and never releases equipment units.
 */
class PaymentHoldService
{
    public function __construct(
        private readonly NotificationService $notifications
    ) {}

    public function isHeld(Rental $rental): bool
    {
        return RentalPaymentHold::where('rental_id', $rental->id)
            ->where('status', RentalPaymentHold::ACTIVE)
            ->exists();
    }

    public function evaluate(Rental $rental, ?User $actor = null): ?RentalPaymentHold
    {
        return DB::transaction(function () use ($rental, $actor) {
            /** @var Rental $locked */
            $locked = Rental::whereKey($rental->id)->lockForUpdate()->first();
            if (! $locked) {
                return null;
            }

            $active = RentalPaymentHold::where('rental_id', $locked->id)
                ->where('status', RentalPaymentHold::ACTIVE)
                ->first();

            if ($active) {
                return null;
            }

            $invoices = $this->unpaidDailyInvoices($locked);
            if ($invoices->isEmpty()) {
                return null;
            }

            $maxCount = (int) config('payment_hold.max_unpaid_daily_invoices', 3);
            $maxHours = (int) config('payment_hold.max_oldest_unpaid_hours', 72);
            $oldest = $invoices->first();
            $ageHours = $oldest->issued_at ? $oldest->issued_at->diffInHours(now()) : 0;

            $reason = null;
            if ($invoices->count() >= $maxCount) {
                $reason = RentalPaymentHold::REASON_INVOICE_COUNT;
            } elseif ($ageHours >= $maxHours) {
                $reason = RentalPaymentHold::REASON_INVOICE_AGE;
            }

            if (! $reason) {
                return null;
            }

            $outstanding = (float) $invoices->sum(fn (Invoice $invoice) => max(0, (float) $invoice->grand_total - (float) $invoice->paid_amount));

            /** @var RentalPaymentHold $hold */
            $hold = RentalPaymentHold::create([
                'rental_id' => $locked->id,
                'status' => RentalPaymentHold::ACTIVE,
                'reason_code' => $reason,
                'trigger_invoice_id' => $oldest->id,
                'outstanding_amount' => $outstanding,
                'started_at' => now(),
                'created_by' => $actor?->id,
            ]);

            AuditLogger::log('PAYMENT_HOLD_STARTED', $hold, [], [
                'rental_id' => $locked->id,
                'reason_code' => $reason,
                'unpaid_invoice_count' => $invoices->count(),
                'oldest_invoice_hours' => $ageHours,
                'outstanding_amount' => $outstanding,
                'created_by' => $actor?->id,
            ]);

            $recipients = User::whereIn('role', ['ADMIN'])->get();
            if ($locked->booking?->user) {
                $recipients->push($locked->booking->user);
            }
            $operatorIds = RentalOperatorAssignment::where('rental_id', $locked->id)
                ->where('status', RentalOperatorAssignment::ACTIVE)
                ->pluck('operator_user_id');
            $recipients = $recipients->merge(User::whereIn('id', $operatorIds)->get())->unique('id');

            $this->notifications->send(
                $recipients,
                'PAYMENT_HOLD_STARTED',
                $hold,
                'Pekerjaan rental dihentikan sementara karena tunggakan Daily Work Invoice.'
            );

            return $hold;
        });
    }

    public function resume(User $admin, Rental $rental, string $reason): RentalPaymentHold
    {
        if (trim($reason) === '') {
            throw new BusinessRuleException('Alasan resume wajib diisi.');
        }

        return DB::transaction(function () use ($admin, $rental, $reason) {
            Rental::whereKey($rental->id)->lockForUpdate()->first();

            /** @var RentalPaymentHold|null $hold */
            $hold = RentalPaymentHold::where('rental_id', $rental->id)
                ->where('status', RentalPaymentHold::ACTIVE)
                ->lockForUpdate()
                ->first();

            if (! $hold) {
                throw new BusinessRuleException('Rental ini tidak sedang dalam PAYMENT HOLD.');
            }

            $invoices = $this->unpaidDailyInvoices($rental);
            $maxCount = (int) config('payment_hold.max_unpaid_daily_invoices', 3);
            $maxHours = (int) config('payment_hold.max_oldest_unpaid_hours', 72);
            $oldest = $invoices->first();
            $ageHours = $oldest?->issued_at ? $oldest->issued_at->diffInHours(now()) : 0;

            if ($invoices->count() >= $maxCount || $ageHours >= $maxHours) {
                throw new BusinessRuleException(
                    'PAYMENT HOLD belum dapat di-resume: masih ada tunggakan yang memenuhi ambang batas.'
                );
            }

            $hold->update([
                'status' => RentalPaymentHold::RESOLVED,
                'resolved_at' => now(),
                'resolved_by' => $admin->id,
                'resolution_reason' => $reason,
            ]);

            AuditLogger::log('PAYMENT_HOLD_RESOLVED', $hold, [
                'status' => RentalPaymentHold::ACTIVE,
            ], [
                'status' => RentalPaymentHold::RESOLVED,
                'reason' => $reason,
                'resolved_by' => $admin->id,
            ]);

            $this->notifications->send(
                $rental->booking?->user,
                'PAYMENT_HOLD_RESOLVED',
                $hold,
                'PAYMENT HOLD rental telah dihentikan; pekerjaan dapat dilanjutkan.'
            );

            return $hold->fresh();
        });
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function unpaidDailyInvoices(Rental $rental): Collection
    {
        return Invoice::query()
            ->where('booking_id', $rental->booking_id)
            ->where('invoice_type', InvoiceType::DAILY_WORK)
            ->whereIn('status', [
                InvoiceStatus::ISSUED,
                InvoiceStatus::UNPAID,
                InvoiceStatus::PARTIALLY_PAID,
                InvoiceStatus::OVERDUE,
            ])
            ->whereColumn('paid_amount', '<', 'grand_total')
            ->whereHas('timesheet.rentalDetail', fn ($q) => $q->where('rental_id', $rental->id))
            ->orderBy('issued_at')
            ->get();
    }

    public function evaluateActiveRentals(?User $actor = null): int
    {
        $created = 0;

        Rental::whereIn('status', [
            RentalStatus::ONGOING->value,
            RentalStatus::DEMOBILIZING->value,
            RentalStatus::RETURN_INSPECTED->value,
        ])->each(function (Rental $rental) use (&$created, $actor) {
            if ($this->evaluate($rental, $actor)) {
                $created++;
            }
        });

        return $created;
    }
}
