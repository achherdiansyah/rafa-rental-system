<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\WhatsApp\WhatsAppNotifier;
use App\Support\AuditLogger;
use App\Support\NotificationService;
use Illuminate\Console\Command;

class ExpireInvoicesCommand extends Command
{
    protected $signature = 'invoices:expire';

    protected $description = 'Mark issued/unpaid invoices OVERDUE when the 24h deadline passes';

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly WhatsAppNotifier $whatsapp
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $expired = 0;

        Invoice::whereIn('status', [InvoiceStatus::ISSUED, InvoiceStatus::UNPAID])
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now())
            ->each(function (Invoice $invoice) use (&$expired) {
                $invoice->update(['status' => InvoiceStatus::OVERDUE]);

                AuditLogger::log('INVOICE_OVERDUE', $invoice, [
                    'old_status' => $invoice->status->value,
                    'due_at' => $invoice->due_at?->toIso8601String(),
                ], [
                    'new_status' => InvoiceStatus::OVERDUE->value,
                ]);

                $invoice->load('booking.user');
                $this->notifications->send(
                    $invoice->booking?->user,
                    'INVOICE_OVERDUE',
                    $invoice,
                    "Invoice {$invoice->invoice_number} melewati jatuh tempo."
                );

                if ($ownerUser = $invoice->booking?->user) {
                    $this->whatsapp->notify(
                        $ownerUser,
                        'INVOICE_DEADLINE',
                        "Invoice {$invoice->invoice_number} melewati jatuh tempo pembayaran."
                    );
                }

                $expired++;
            });

        if ($expired > 0) {
            $this->info("{$expired} invoice ditandai OVERDUE.");
        }

        return self::SUCCESS;
    }
}
