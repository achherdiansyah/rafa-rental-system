<?php

namespace Tests\Unit\Enums;

use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\RentalStatus;
use Tests\TestCase;

class EnumTest extends TestCase
{
    public function test_booking_status_values_match_phase1_state_machine(): void
    {
        $expected = [
            'DRAFT', 'SUBMITTED', 'PENDING_APPROVAL', 'REJECTED',
            'APPROVED', 'PAYMENT_PENDING', 'CONFIRMED', 'DISPATCHED',
            'ARRIVED', 'ONGOING', 'COMPLETED', 'CANCELLED', 'EXPIRED',
        ];

        $actual = array_column(BookingStatus::cases(), 'value');

        $this->assertEqualsCanonicalizing($expected, $actual);
    }

    public function test_rental_status_values_match_phase1_state_machine(): void
    {
        $expected = [
            'PENDING_ASSIGNMENT', 'ASSIGNED', 'DISPATCHED', 'ARRIVED',
            'ONGOING', 'DEMOBILIZING', 'RETURN_INSPECTED', 'COMPLETED', 'CANCELLED',
        ];

        $actual = array_column(RentalStatus::cases(), 'value');

        $this->assertEqualsCanonicalizing($expected, $actual);
    }

    public function test_equipment_status_values_match_phase1_state_machine(): void
    {
        $expected = [
            'AVAILABLE', 'ASSIGNED', 'MOBILIZING', 'ON_SITE',
            'DEMOBILIZING', 'RETURN_INSPECTION', 'MAINTENANCE', 'DECOMMISSIONED',
        ];

        $actual = array_column(EquipmentStatus::cases(), 'value');

        $this->assertEqualsCanonicalizing($expected, $actual);
    }

    public function test_invoice_status_values_match_phase1_state_machine(): void
    {
        $expected = ['DRAFT', 'UNPAID', 'PARTIALLY_PAID', 'PAID', 'OVERPAID', 'EXPIRED', 'CANCELLED'];

        $actual = array_column(InvoiceStatus::cases(), 'value');

        $this->assertEqualsCanonicalizing($expected, $actual);
    }

    public function test_payment_status_values_match_phase1_state_machine(): void
    {
        $expected = ['PENDING', 'SUBMITTED', 'APPROVED', 'REJECTED'];

        $actual = array_column(PaymentStatus::cases(), 'value');

        $this->assertEqualsCanonicalizing($expected, $actual);
    }

    public function test_refund_status_values_match_phase1_state_machine(): void
    {
        $expected = ['REQUESTED', 'REVIEWED', 'APPROVED', 'REJECTED', 'PROCESSING', 'COMPLETED'];

        $actual = array_column(RefundStatus::cases(), 'value');

        $this->assertEqualsCanonicalizing($expected, $actual);
    }

    public function test_enums_are_backed_by_strings(): void
    {
        $this->assertEquals('APPROVED', BookingStatus::APPROVED->value);
        $this->assertEquals('ONGOING', RentalStatus::ONGOING->value);
        $this->assertEquals('AVAILABLE', EquipmentStatus::AVAILABLE->value);
        $this->assertEquals('PAID', InvoiceStatus::PAID->value);
        $this->assertEquals('SUBMITTED', PaymentStatus::SUBMITTED->value);
        $this->assertEquals('COMPLETED', RefundStatus::COMPLETED->value);
    }

    public function test_enum_from_string(): void
    {
        $this->assertEquals(BookingStatus::CONFIRMED, BookingStatus::from('CONFIRMED'));
        $this->assertEquals(EquipmentStatus::MAINTENANCE, EquipmentStatus::from('MAINTENANCE'));
    }
}
