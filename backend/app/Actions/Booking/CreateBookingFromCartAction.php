<?php

namespace App\Actions\Booking;

use App\DTOs\Pricing\LineItemPricingInput;
use App\Enums\BookingStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\Cart;
use App\Models\User;
use App\Services\Booking\BookingAvailabilityPrecheckService;
use App\Services\Pricing\PricingCalculatorService;
use App\Support\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreateBookingFromCartAction
{
    public function __construct(
        protected PricingCalculatorService $pricingCalculator,
        protected BookingAvailabilityPrecheckService $precheck
    ) {}

    /**
     * Create a DRAFT booking from the user's active cart and consume the cart items.
     *
     * @throws BusinessRuleException
     */
    public function execute(User $user, Cart $cart): Booking
    {
        return DB::transaction(function () use ($user, $cart) {
            // 1. Validate cart is populated and location set (BR-007 / BR-003)
            $cart->load(['projectLocation', 'items.model.prices']);

            if ($cart->items->isEmpty()) {
                throw new BusinessRuleException('Keranjang sewa kosong. Tambahkan armada terlebih dahulu.');
            }

            if (! $cart->project_location_id) {
                throw new BusinessRuleException('Lokasi proyek wajib dipilih sebelum membuat booking.');
            }

            // 2. Build line items from cart
            $lineItems = [];
            foreach ($cart->items->all() as $item) {
                $lineItems[] = [
                    'equipment_model_id' => $item->equipment_model_id,
                    'quantity' => $item->quantity,
                    'start_date' => $item->start_date->toDateString(),
                    'end_date' => $item->end_date->toDateString(),
                    'is_all_in' => $item->is_all_in,
                ];
            }

            // 3. Availability pre-check (soft check - NOT a reservation)
            $minStart = Carbon::parse(min(array_column($lineItems, 'start_date')));
            $maxEnd = Carbon::parse(max(array_column($lineItems, 'end_date')));

            $this->precheck->assertAvailable($lineItems, $minStart, $maxEnd);

            // 4. Compute pricing snapshots via the shared pricing engine (backend source of truth)
            $pricingInputs = [];
            foreach ($lineItems as $line) {
                $pricingInputs[] = LineItemPricingInput::fromArray($line);
            }

            $pricingResult = $this->pricingCalculator->calculateBooking($pricingInputs);

            // 5. Create DRAFT booking with unique booking code
            $booking = Booking::create([
                'booking_code' => $this->generateBookingCode(),
                'user_id' => $user->id,
                'project_location_id' => $cart->project_location_id,
                'status' => BookingStatus::DRAFT,
                'total_amount' => $pricingResult->grandTotal,
            ]);

            // 6. Persist booking detail snapshots
            foreach ($pricingResult->items as $idx => $lineResult) {
                $sourceLine = $lineItems[$idx];

                $booking->details()->create([
                    'equipment_model_id' => $lineResult->equipmentModelId,
                    'quantity' => $lineResult->quantity,
                    'start_date' => $sourceLine['start_date'],
                    'end_date' => $sourceLine['end_date'],
                    'is_all_in' => $lineResult->isAllIn,
                    'rental_rate_snapshot' => $lineResult->hourlyRate,
                    'subtotal' => $lineResult->rentalSubtotal,
                ]);
            }

            // 7. Consume cart (items cleared, location reset) to enforce idempotency
            $cart->items()->delete();
            $cart->update(['project_location_id' => null]);

            // 8. Audit
            AuditLogger::log('BOOKING_CREATED', $booking, [
                'old_status' => null,
            ], [
                'new_status' => BookingStatus::DRAFT->value,
                'detail_count' => $booking->details()->count(),
            ]);

            $booking->load([
                'projectLocation',
                'details.model' => function ($q) {
                    $q->with(['type', 'prices', 'attachments']);
                },
            ]);

            return $booking;
        });
    }

    public function generateBookingCode(): string
    {
        $date = now()->format('Ymd');
        $sequence = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);

        return "RFA-BKG-{$date}-{$sequence}";
    }
}
