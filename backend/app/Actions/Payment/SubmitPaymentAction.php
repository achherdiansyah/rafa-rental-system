<?php

namespace App\Actions\Payment;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\FileSecurity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * SUBMIT payment proof against an invoice.
 *
 * Flow: PAYMENT_PENDING (PENDING) -> SUBMITTED. The payment is NOT approved
 * here — Admin verification is a later subphase (10D). A rejected payment is
 * never deleted; this action never mutates existing payments.
 */
class SubmitPaymentAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $payer, Invoice $invoice, array $data, UploadedFile $proof): Payment
    {
        Gate::authorize('create', [Payment::class, $invoice]);

        return DB::transaction(function () use ($payer, $invoice, $data, $proof) {
            $invoice->loadMissing('booking');

            if (! in_array($invoice->status, [
                InvoiceStatus::ISSUED,
                InvoiceStatus::UNPAID,
                InvoiceStatus::PARTIALLY_PAID,
                InvoiceStatus::OVERDUE,
            ], true)) {
                throw new BusinessRuleException(
                    'Pembayaran hanya dapat diajukan untuk invoice ISSUED/UNPAID/OVERDUE yang belum lunas.'
                );
            }

            $amount = (float) $data['amount'];
            if ($amount <= 0) {
                throw new BusinessRuleException('Nominal pembayaran harus lebih besar dari 0.');
            }

            $reference = trim((string) ($data['reference'] ?? ''));
            // Anti-duplikat: referensi sama dilarang KECUALI payment sebelumnya ditolak
            // (user wajib diberi kesempatan upload bukti baru setelah penolakan).
            $duplicate = Payment::where('invoice_id', $invoice->id)
                ->where('reference', $reference)
                ->where('reference', '!=', '')
                ->where('status', '!=', PaymentStatus::REJECTED)
                ->exists();

            if ($duplicate) {
                throw new BusinessRuleException(
                    'Referensi transfer yang sama untuk invoice ini sudah pernah diajukan.'
                );
            }

            // Upload security (mime/extension/size) — storage is private.
            $security = FileSecurity::validate($proof);
            if (! $security['valid']) {
                throw new BusinessRuleException($security['error'] ?? 'Berkas bukti tidak valid.');
            }

            $extension = strtolower($proof->getClientOriginalExtension());
            $securePath = FileSecurity::generateSecurePath('payments', $extension);
            $proof->storeAs('', $securePath, 'local');

            /** @var Payment $payment */
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'payment_date' => $data['payment_date'],
                'sender_name' => $data['sender_name'] ?? null,
                'reference' => $reference ?: null,
                'amount' => $amount,
                'status' => PaymentStatus::PENDING,
            ]);

            $payment->attachments()->create([
                'document_type' => 'PAYMENT_PROOF',
                'file_path' => $securePath,
                'file_name' => $proof->getClientOriginalName(),
                'mime_type' => $proof->getMimeType(),
                'file_size' => $proof->getSize(),
                'uploaded_by' => $payer->id,
            ]);

            // PAYMENT_PENDING -> SUBMITTED (menunggu verifikasi Admin; belum approved)
            $payment->update(['status' => PaymentStatus::SUBMITTED]);

            AuditLogger::log('PAYMENT_SUBMITTED', $payment, [
                'old_status' => PaymentStatus::PENDING->value,
            ], [
                'new_status' => PaymentStatus::SUBMITTED->value,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'reference' => $reference ?: null,
                'submitted_by' => $payer->id,
            ]);

            return $payment->fresh()->load([
                'attachments',
                'invoice.booking.projectLocation',
            ]);
        });
    }
}
