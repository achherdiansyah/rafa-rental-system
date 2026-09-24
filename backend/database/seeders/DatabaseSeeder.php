<?php

namespace Database\Seeders;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Models\BankAccount;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\BusinessCalendar;
use App\Models\CustomerProfile;
use App\Models\EquipmentModel;
use App\Models\EquipmentPrice;
use App\Models\EquipmentType;
use App\Models\EquipmentUnit;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Payment;
use App\Models\ProjectLocation;
use App\Models\Rental;
use App\Models\RentalDetail;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Admin & Owner
        $admin = User::firstOrCreate(
            ['email' => 'admin@rafarental.com'],
            [
                'name' => 'Admin Operasional',
                'password' => Hash::make('password'),
                'role' => UserRole::ADMIN,
                'phone_number' => '08110000001',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $owner = User::firstOrCreate(
            ['email' => 'owner@rafarental.com'],
            [
                'name' => 'Owner Bisnis',
                'password' => Hash::make('password'),
                'role' => UserRole::OWNER,
                'phone_number' => '08110000002',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // 2. Customers
        $customer1 = User::firstOrCreate(
            ['email' => 'budi@kontraktor.com'],
            [
                'name' => 'Budi Santoso',
                'password' => Hash::make('password'),
                'role' => UserRole::USER,
                'phone_number' => '081234567801',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        CustomerProfile::firstOrCreate(
            ['user_id' => $customer1->id],
            [
                'company_name' => 'PT Maju Konstruksi Jaya',
                'identity_type' => 'KTP',
                'identity_number' => '3201012345670001',
                'address' => 'Jl. Sudirman No. 45, Bandung',
                'verification_status' => 'VERIFIED',
            ]
        );

        $customer2 = User::firstOrCreate(
            ['email' => 'siti@tambang.com'],
            [
                'name' => 'Siti Rahmawati',
                'password' => Hash::make('password'),
                'role' => UserRole::USER,
                'phone_number' => '081234567802',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        CustomerProfile::firstOrCreate(
            ['user_id' => $customer2->id],
            [
                'company_name' => 'CV Prima Mandiri Mineral',
                'identity_type' => 'KTP',
                'identity_number' => '3201012345670002',
                'address' => 'Jl. Soekarno Hatta No. 12, Tasikmalaya',
                'verification_status' => 'VERIFIED',
            ]
        );

        // 3. Project Locations
        $loc1 = ProjectLocation::firstOrCreate(
            ['project_name' => 'Proyek Tol Cisumdawu Seksi 4', 'user_id' => $customer1->id],
            [
                'address' => 'Desa Ciptasari, Kec. Pamulihan, Kab. Sumedang',
                'city' => 'Sumedang',
                'pic_name' => 'Hendra Setiawan',
                'pic_phone' => '081399887701',
                'latitude' => -6.85840000,
                'longitude' => 107.82170000,
                'is_active' => true,
            ]
        );

        $loc2 = ProjectLocation::firstOrCreate(
            ['project_name' => 'Proyek Bendungan Leuwikeris', 'user_id' => $customer1->id],
            [
                'address' => 'Kecamatan Ciamis, Kabupaten Ciamis',
                'city' => 'Ciamis',
                'pic_name' => 'Agus Priyanto',
                'pic_phone' => '081399887702',
                'latitude' => -7.32750000,
                'longitude' => 108.35330000,
                'is_active' => true,
            ]
        );

        // 4. Equipment Types
        $excavatorType = EquipmentType::firstOrCreate(
            ['name' => 'Excavator'],
            ['description' => 'Alat berat pengeruk tanah dan bebatuan']
        );

        $bulldozerType = EquipmentType::firstOrCreate(
            ['name' => 'Bulldozer'],
            ['description' => 'Alat berat perata tanah dan pembukaan lahan']
        );

        $loaderType = EquipmentType::firstOrCreate(
            ['name' => 'Wheel Loader'],
            ['description' => 'Alat berat pemindah material curah']
        );

        $compactorType = EquipmentType::firstOrCreate(
            ['name' => 'Vibro Compactor'],
            ['description' => 'Alat berat pemadat tanah dan aspal']
        );

        // 5. Equipment Models & Pricing
        $models = [
            [
                'type' => $excavatorType,
                'brand' => 'Komatsu',
                'model_name' => 'PC200-8',
                'capacity_value' => 20.00,
                'capacity_unit' => 'Ton',
                'prices' => [
                    ['is_all_in' => false, 'base_rate' => 225000.00, 'min_hours' => 8, 'overtime' => 275000.00],
                    ['is_all_in' => true, 'base_rate' => 350000.00, 'min_hours' => 8, 'overtime' => 400000.00],
                ],
                'units' => [
                    ['serial_number' => 'KM-PC200-001', 'plate_number' => 'B 9101 RFA', 'status' => EquipmentStatus::AVAILABLE, 'hm' => 1250.50, 'year' => 2021],
                    ['serial_number' => 'KM-PC200-002', 'plate_number' => 'B 9102 RFA', 'status' => EquipmentStatus::AVAILABLE, 'hm' => 2400.00, 'year' => 2020],
                    ['serial_number' => 'KM-PC200-003', 'plate_number' => 'B 9103 RFA', 'status' => EquipmentStatus::ASSIGNED, 'hm' => 3100.25, 'year' => 2019],
                ],
            ],
            [
                'type' => $excavatorType,
                'brand' => 'Caterpillar',
                'model_name' => 'CAT 320D',
                'capacity_value' => 20.00,
                'capacity_unit' => 'Ton',
                'prices' => [
                    ['is_all_in' => false, 'base_rate' => 240000.00, 'min_hours' => 8, 'overtime' => 290000.00],
                    ['is_all_in' => true, 'base_rate' => 365000.00, 'min_hours' => 8, 'overtime' => 415000.00],
                ],
                'units' => [
                    ['serial_number' => 'CAT-320D-001', 'plate_number' => 'B 9201 RFA', 'status' => EquipmentStatus::AVAILABLE, 'hm' => 980.00, 'year' => 2022],
                    ['serial_number' => 'CAT-320D-002', 'plate_number' => 'B 9202 RFA', 'status' => EquipmentStatus::MAINTENANCE, 'hm' => 4500.00, 'year' => 2018],
                ],
            ],
            [
                'type' => $bulldozerType,
                'brand' => 'Komatsu',
                'model_name' => 'D85ESS-2',
                'capacity_value' => 21.00,
                'capacity_unit' => 'Ton',
                'prices' => [
                    ['is_all_in' => false, 'base_rate' => 300000.00, 'min_hours' => 8, 'overtime' => 350000.00],
                    ['is_all_in' => true, 'base_rate' => 450000.00, 'min_hours' => 8, 'overtime' => 500000.00],
                ],
                'units' => [
                    ['serial_number' => 'KM-D85-001', 'plate_number' => 'B 9301 RFA', 'status' => EquipmentStatus::AVAILABLE, 'hm' => 1800.00, 'year' => 2020],
                ],
            ],
            [
                'type' => $loaderType,
                'brand' => 'Komatsu',
                'model_name' => 'WA380-6',
                'capacity_value' => 18.00,
                'capacity_unit' => 'Ton',
                'prices' => [
                    ['is_all_in' => false, 'base_rate' => 275000.00, 'min_hours' => 8, 'overtime' => 325000.00],
                    ['is_all_in' => true, 'base_rate' => 400000.00, 'min_hours' => 8, 'overtime' => 450000.00],
                ],
                'units' => [
                    ['serial_number' => 'KM-WA380-001', 'plate_number' => 'B 9401 RFA', 'status' => EquipmentStatus::AVAILABLE, 'hm' => 800.00, 'year' => 2023],
                ],
            ],
            [
                'type' => $compactorType,
                'brand' => 'Dynapac',
                'model_name' => 'CA250D',
                'capacity_value' => 11.00,
                'capacity_unit' => 'Ton',
                'prices' => [
                    ['is_all_in' => false, 'base_rate' => 200000.00, 'min_hours' => 8, 'overtime' => 250000.00],
                    ['is_all_in' => true, 'base_rate' => 320000.00, 'min_hours' => 8, 'overtime' => 370000.00],
                ],
                'units' => [
                    ['serial_number' => 'DY-CA250-001', 'plate_number' => 'B 9501 RFA', 'status' => EquipmentStatus::AVAILABLE, 'hm' => 1500.00, 'year' => 2021],
                ],
            ],
        ];

        $createdUnits = [];

        foreach ($models as $item) {
            $eqModel = EquipmentModel::firstOrCreate(
                ['model_name' => $item['model_name']],
                [
                    'equipment_type_id' => $item['type']->id,
                    'brand' => $item['brand'],
                    'capacity_value' => $item['capacity_value'],
                    'capacity_unit' => $item['capacity_unit'],
                    'is_active' => true,
                ]
            );

            foreach ($item['prices'] as $p) {
                EquipmentPrice::firstOrCreate(
                    [
                        'equipment_model_id' => $eqModel->id,
                        'is_all_in' => $p['is_all_in'],
                    ],
                    [
                        'price_type' => 'HOURLY',
                        'base_rate' => $p['base_rate'],
                        'minimum_hours' => $p['min_hours'],
                        'overtime_rate' => $p['overtime'],
                        'effective_date' => '2026-01-01',
                    ]
                );
            }

            foreach ($item['units'] as $u) {
                $unitObj = EquipmentUnit::firstOrCreate(
                    ['serial_number' => $u['serial_number']],
                    [
                        'equipment_model_id' => $eqModel->id,
                        'plate_number' => $u['plate_number'],
                        'status' => $u['status'],
                        'last_hour_meter' => $u['hm'],
                        'year_of_make' => $u['year'],
                    ]
                );
                $createdUnits[$u['serial_number']] = $unitObj;
            }
        }

        // 6. Bank Accounts
        $bankBca = BankAccount::firstOrCreate(
            ['account_number' => '1234567890'],
            [
                'bank_name' => 'BCA',
                'account_name' => 'PT RAFA RENTAL NUSANTARA',
                'is_active' => true,
            ]
        );

        BankAccount::firstOrCreate(
            ['account_number' => '1300098765432'],
            [
                'bank_name' => 'Mandiri',
                'account_name' => 'PT RAFA RENTAL NUSANTARA',
                'is_active' => true,
            ]
        );

        // 7. Business Calendars (2026 Sample Holidays)
        $holidays = [
            ['date' => '2026-01-01', 'name' => 'Tahun Baru 2026 Masehi'],
            ['date' => '2026-05-01', 'name' => 'Hari Buruh Internasional'],
            ['date' => '2026-08-17', 'name' => 'Hari Kemerdekaan Republik Indonesia'],
            ['date' => '2026-12-25', 'name' => 'Hari Raya Natal'],
        ];

        foreach ($holidays as $h) {
            BusinessCalendar::firstOrCreate(
                ['calendar_date' => $h['date']],
                [
                    'is_working_day' => false,
                    'holiday_name' => $h['name'],
                ]
            );
        }

        // 8. Sample Confirmed Booking with Invoice, Payment, and Rental
        $sampleBooking = Booking::firstOrCreate(
            ['booking_code' => 'RFA-BKG-20260901-0001'],
            [
                'user_id' => $customer1->id,
                'project_location_id' => $loc1->id,
                'status' => BookingStatus::CONFIRMED,
                'total_amount' => 14000000.00,
            ]
        );

        $pc200Model = EquipmentModel::where('model_name', 'PC200-8')->first();
        $sampleDetail = BookingDetail::firstOrCreate(
            [
                'booking_id' => $sampleBooking->id,
                'equipment_model_id' => $pc200Model->id,
            ],
            [
                'quantity' => 1,
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-07',
                'is_all_in' => false,
                'rental_rate_snapshot' => 225000.00,
                'subtotal' => 12600000.00,
            ]
        );

        $assignedUnit = $createdUnits['KM-PC200-003'] ?? null;
        if ($assignedUnit) {
            $assignment = BookingUnitAssignment::firstOrCreate(
                [
                    'booking_detail_id' => $sampleDetail->id,
                    'equipment_unit_id' => $assignedUnit->id,
                ],
                [
                    'status' => AssignmentStatus::ASSIGNED,
                    'is_current' => true,
                    'assigned_by' => $admin->id,
                ]
            );

            // Rental
            $rental = Rental::firstOrCreate(
                ['booking_id' => $sampleBooking->id],
                [
                    'status' => RentalStatus::ASSIGNED,
                ]
            );

            RentalDetail::firstOrCreate(
                ['rental_id' => $rental->id, 'assignment_id' => $assignment->id],
                [
                    'status' => RentalStatus::ASSIGNED,
                ]
            );
        }

        // Invoice & Payment
        $invoice = Invoice::firstOrCreate(
            ['invoice_number' => 'INV/20260901/0001'],
            [
                'booking_id' => $sampleBooking->id,
                'due_at' => now()->addHours(24),
                'status' => InvoiceStatus::PARTIALLY_PAID,
                'subtotal' => 14000000.00,
                'tax_total' => 0.00,
                'grand_total' => 14000000.00,
                'paid_amount' => 7000000.00,
                'overpayment_amount' => 0.00,
            ]
        );

        InvoiceDetail::firstOrCreate(
            ['invoice_id' => $invoice->id, 'description' => 'Sewa Excavator Komatsu PC200-8 (7 Hari @ 8 Jam)'],
            [
                'unit_price' => 1800000.00,
                'quantity' => 7.00,
                'subtotal' => 12600000.00,
            ]
        );

        InvoiceDetail::firstOrCreate(
            ['invoice_id' => $invoice->id, 'description' => 'Biaya MOB/DEMOB Truk Tronton (Unit KM-PC200-003)'],
            [
                'unit_price' => 1400000.00,
                'quantity' => 1.00,
                'subtotal' => 1400000.00,
            ]
        );

        Payment::firstOrCreate(
            [
                'invoice_id' => $invoice->id,
                'amount' => 7000000.00,
            ],
            [
                'bank_account_id' => $bankBca->id,
                'payment_date' => now(),
                'status' => PaymentStatus::APPROVED,
                'verified_by' => $admin->id,
            ]
        );
    }
}
