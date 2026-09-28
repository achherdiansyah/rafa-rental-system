<?php

namespace App\Console\Commands;

use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\Finance\CustomerOutstandingService;
use App\Services\WhatsApp\WhatsAppNotifier;
use Illuminate\Console\Command;

/**
 * Outstanding reminder via WhatsApp (channel decoupled; SKIPPED if provider
 * not available). Sends to customers who have overdue open invoices.
 */
class SendOutstandingRemindersCommand extends Command
{
    protected $signature = 'outstanding:remind';

    protected $description = 'Send WhatsApp reminders to customers with overdue invoices';

    public function handle(CustomerOutstandingService $outstanding, WhatsAppNotifier $whatsapp): int
    {
        $sent = 0;

        foreach ($outstanding->allCustomers() as $customer) {
            if (($customer['overdue_invoice_count'] ?? 0) <= 0) {
                continue;
            }

            /** @var User|null $recipient */
            $recipient = User::find($customer['user_id']);
            if (! $recipient) {
                continue;
            }

            // Same-day idempotency: avoid duplicate reminders per customer/day.
            $alreadySent = NotificationDelivery::where('event', 'OUTSTANDING_REMINDER')
                ->where('recipient_id', $recipient->id)
                ->whereDate('created_at', today())
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $whatsapp->notify(
                $recipient,
                'OUTSTANDING_REMINDER',
                'Anda memiliki tagihan melewati jatuh tempo sebesar '.number_format($customer['total_outstanding'], 0, ',', '.').'. Mohon segera diselesaikan.'
            );

            $sent++;
        }

        $this->info("{$sent} reminder outstanding dikirim/dicatat.");

        return self::SUCCESS;
    }
}
