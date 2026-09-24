# Phase 3: Database & Domain Foundation Summary - RAFA Rental System

Dokumentasi akhir rangkuman implementasi skema basis data, model Eloquent, relasi, factory, seeder, dan pengujian integritas referensial untuk RAFA Rental System (Phase 3I Final Review).

---

## 1. Tujuan Phase 3
Mengimplementasikan seluruh skema basis data relasional (MySQL 8.4) yang mencakup 29 entitas tabel sesuai ERD V3 dan Data Dictionary Phase 1, menerapkan aturan tipe data moneter `DECIMAL(15,2)`, integritas referensial (RESTRICT/CASCADE), enum casts, factory, seeder data pengembangan, dan pengujian relasi tanpa mengimplementasikan alur logika bisnis tingkat tinggi.

---

## 2. Entitas yang Diimplementasikan (29 Tabel)

| No | Modul | Tabel | Model Eloquent | Karakteristik Utama |
|---|---|---|---|---|
| 1 | Identity | `users` | `User` | Sanctum tokens, UserRole enum cast, soft delete |
| 2 | Identity | `customer_profiles` | `CustomerProfile` | 1:1 user, nomor KTP unique, cascade on delete |
| 3 | Project | `project_locations` | `ProjectLocation` | 1:N user, koordinat decimal(10/11,8), soft delete, restrict on delete |
| 4 | Fleet | `equipment_types` | `EquipmentType` | Master kategori, soft delete |
| 5 | Fleet | `equipment_models` | `EquipmentModel` | Spesifikasi kapasitas decimal(8,2), soft delete, restrict on delete |
| 6 | Fleet | `equipment_units` | `EquipmentUnit` | Serial number unique, EquipmentStatus enum cast, soft delete, restrict on delete |
| 7 | Pricing | `equipment_prices` | `EquipmentPrice` | Hourly rate, All-in/Non All-in, `DECIMAL(15,2)`, minimum hours |
| 8 | Pricing | `equipment_price_versions` | `EquipmentPriceVersion` | Historical pricing immutability, changed_by user FK |
| 9 | Cart | `carts` | `Cart` | 1:1 user active cart, optional single project location |
| 10 | Cart | `cart_items` | `CartItem` | Model, quantity, is_all_in, start_date, end_date |
| 11 | Booking | `bookings` | `Booking` | `booking_code` unique, BookingStatus enum cast, 1 project location, soft delete |
| 12 | Booking | `booking_details` | `BookingDetail` | Multiple lines, `rental_rate_snapshot DECIMAL(15,2)`, is_all_in per line |
| 13 | Booking | `booking_unit_assignments` | `BookingUnitAssignment` | AssignmentStatus enum cast, `is_current`, `replaced_reason`, assigned_by FK |
| 14 | Rental | `rentals` | `Rental` | 1:1 booking, RentalStatus enum cast (DISPATCHED != ARRIVED != ONGOING) |
| 15 | Rental | `rental_details` | `RentalDetail` | 1:1 assignment, check_in/out HM decimal(10,2) |
| 16 | Timesheet | `timesheets` | `Timesheet` | Daily HM, total/standby/breakdown hours decimal(8,2), TimesheetStatus cast |
| 17 | Timesheet | `timesheet_revisions` | `TimesheetRevision` | Versioned immutable history, old start/end HM, revision_reason, revised_by |
| 18 | Finance | `invoices` | `Invoice` | `invoice_number` unique, InvoiceStatus enum cast, `due_at` 24h, `DECIMAL(15,2)`, soft delete |
| 19 | Finance | `invoice_details` | `InvoiceDetail` | Snapshot unit_price `DECIMAL(15,2)`, quantity decimal(8,2), subtotal |
| 20 | Finance | `bank_accounts` | `BankAccount` | `account_number` unique, bank_name, account_name, is_active |
| 21 | Finance | `payments` | `Payment` | 1 Invoice to N Payments, PaymentStatus cast, `amount DECIMAL(15,2)`, rejection_reason |
| 22 | Finance | `refunds` | `Refund` | RefundStatus cast, manual bank transfer tracking, processed_by user FK |
| 23 | Recommendation | `recommendation_requests` | `RecommendationRequest` | User request tracking |
| 24 | Recommendation | `recommendation_criteria` | `RecommendationCriteria` | 1:1 request, project_type, terrain_condition, load_capacity decimal(10,2) |
| 25 | Recommendation | `recommendation_results` | `RecommendationResult` | Model recommendation, match_score decimal(5,2), reasoning_text |
| 26 | System | `notifications` | - | Laravel database notifications (UUID) |
| 27 | Supporting | `attachments` | `Attachment` | Polymorphic (`attachable`), document_type, MIME, size, uploaded_by |
| 28 | Supporting | `activity_logs` | `ActivityLog` | Polymorphic (`subject`, `causer`), audit properties JSON, IP, timestamp |
| 29 | Supporting | `business_calendars` | `BusinessCalendar` | `calendar_date` unique, is_working_day, holiday_name |

---

## 3. Ringkasan Migrasi (33 Migrations)

Urutan migrasi terstruktur rapi untuk menjaga integritas dependensi Foreign Key:
1. `0001_01_01_000000_create_users_table.php` (Laravel infrastructure)
2. `0001_01_01_000001_create_cache_table.php`
3. `0001_01_01_000002_create_jobs_table.php`
4. `2026_09_23_124411_create_personal_access_tokens_table.php` (Sanctum)
5. `2026_09_23_131701_create_notifications_table.php`
6. `2026_09_24_000001_add_roles_and_profile_fields_to_users_table.php`
7. `2026_09_24_000002_create_customer_profiles_table.php`
8. `2026_09_24_000003_create_project_locations_table.php`
9. `2026_09_24_000004_create_equipment_types_table.php`
10. `2026_09_24_000005_create_equipment_models_table.php`
11. `2026_09_24_000006_create_equipment_units_table.php`
12. `2026_09_24_000007_create_equipment_prices_table.php`
13. `2026_09_24_000008_create_equipment_price_versions_table.php`
14. `2026_09_24_000009_create_carts_table.php`
15. `2026_09_24_000010_create_cart_items_table.php`
16. `2026_09_24_000011_create_bookings_table.php`
17. `2026_09_24_000012_create_booking_details_table.php`
18. `2026_09_24_000013_create_booking_unit_assignments_table.php`
19. `2026_09_24_000014_create_rentals_table.php`
20. `2026_09_24_000015_create_rental_details_table.php`
21. `2026_09_24_000016_create_timesheets_table.php`
22. `2026_09_24_000017_create_timesheet_revisions_table.php`
23. `2026_09_24_000018_create_invoices_table.php`
24. `2026_09_24_000019_create_invoice_details_table.php`
25. `2026_09_24_000020_create_bank_accounts_table.php`
26. `2026_09_24_000021_create_payments_table.php`
27. `2026_09_24_000022_create_refunds_table.php`
28. `2026_09_24_000023_create_recommendation_requests_table.php`
29. `2026_09_24_000024_create_recommendation_criteria_table.php`
30. `2026_09_24_000025_create_recommendation_results_table.php`
31. `2026_09_24_000026_create_attachments_table.php`
32. `2026_09_24_000027_create_activity_logs_table.php`
33. `2026_09_24_000028_create_business_calendars_table.php`

---

## 4. Ringkasan Relasi Eloquent
- **User:** `customerProfile` (1:1), `projectLocations` (1:N), `cart` (1:1), `bookings` (1:N), `recommendationRequests` (1:N).
- **Equipment:** `EquipmentType` (1:N) `EquipmentModel` (1:N) `EquipmentUnit`, `EquipmentModel` (1:N) `EquipmentPrice` (1:N) `EquipmentPriceVersion`.
- **Cart:** `Cart` (1:N) `CartItem` (N:1) `EquipmentModel`.
- **Booking:** `Booking` (1:1) `ProjectLocation`, `Booking` (1:N) `BookingDetail` (1:N) `BookingUnitAssignment` (N:1) `EquipmentUnit`.
- **Rental:** `Booking` (1:1) `Rental` (1:N) `RentalDetail` (1:1) `BookingUnitAssignment`, `RentalDetail` (1:N) `Timesheet` (1:N) `TimesheetRevision`.
- **Finance:** `Booking` (1:N) `Invoice` (1:N) `InvoiceDetail`, `Invoice` (1:N) `Payment` (N:1) `BankAccount`, `Invoice` (1:N) `Refund`.
- **Polymorphic:** `Attachment` (`attachable`), `ActivityLog` (`subject`, `causer`).

---

## 5. Ringkasan Factory & Seeder
- **21 Factories:** Terdistribusi di `database/factories/` dengan states relevan (`admin()`, `owner()`, `paid()`, `partiallyPaid()`, `allIn()`, `dispatched()`, `ongoing()`, `holiday()`, dll).
- **DatabaseSeeder:** Menghasilkan data realistis untuk development:
  - 1 Admin (`admin@rafarental.com`), 1 Owner (`owner@rafarental.com`), 2 Pelanggan terverifikasi.
  - 2 Lokasi proyek (Sumedang & Ciamis).
  - 4 Tipe alat berat, 5 Model spesifik (Komatsu, CAT, Dynapac), 10 Unit fisik bernomor seri & plat nomor.
  - Master harga All-in & Non All-in.
  - 2 Rekening bank (BCA, Mandiri).
  - 4 Hari libur nasional 2026.
  - 1 Contoh transaksi booking terkonfirmasi lengkap dengan invoice, payment DP, dan penugasan unit fisik.

---

## 6. Hasil Pengujian (Test Results)
- **Total Test:** **86 passed (203 assertions)**.
- **Code Style:** **Laravel Pint 100% clean** (`./vendor/bin/pint --test` passed).
- **Cakupan Domain Test:**
  - `CustomerAndProjectDomainTest` (8 tests)
  - `EquipmentDomainTest` (12 tests)
  - `PricingDomainTest` (6 tests)
  - `BookingDomainTest` (8 tests)
  - `RentalAndTimesheetDomainTest` (7 tests)
  - `FinanceDomainTest` (7 tests)
  - `SupportingDomainTest` (5 tests)
  - `SeederTest` (1 test)
  - Foundation Tests: Enum (8), ApiResponse (2), AuditLogger (3), FileSecurity (5), HealthCheck (1), ExceptionHandling (5), AuthAndRole (6), AppBoot (2).

---

## 7. Temuan Integritas Data (Integrity Verification)
1. **Financial Immutability:** Kolom snapshot `rental_rate_snapshot` di `booking_details` dan `unit_price` di `invoice_details` menjamin perubahan harga master tidak merusak data historis.
2. **Cardinality Rules:** Aturan 1 booking = 1 project location ditegakkan melalui FK tunggal NOT NULL `bookings.project_location_id`.
3. **Payment Isolation:** 1 Payment strictly terikat ke 1 Invoice (`payments.invoice_id` NOT NULL), mendukung partial payment (1 Invoice : N Payments) dan overpayment.
4. **Delete Behavior:** Operasi delete pada entitas induk yang memiliki transaksi aktif dilindungi dengan `RESTRICT` di database level.
5. **Timesheet Versioning:** Revisi timesheet disimpan append-only di `timesheet_revisions` tanpa menghapus record asli.
6. **Unit Replacement History:** Penggantian unit fisik mencatat status `REPLACED` dengan `is_current = false` pada assignment lama.

---

## 8. Known Limitations (Batasan Phase 3)
- Logika bisnis domain (seperti kalkulasi availability, algoritma pricing calculator, orkestrasi checkout, validasi BAST, dan payment approval automation) belum diimplementasikan di service layer.
- Endpoint API domain bisnis belum di-bind ke route `/api/v1` (hanya `HealthCheckController` yang aktif).

---

## 9. Rekomendasi untuk Phase 4 (Domain Services & Business Actions)
1. **Implementasikan Services Utama:**
   - `AvailabilityService`: Menangani ketersediaan kuota model dan ketersediaan unit fisik dengan operational buffer (MOB/DEMOB/Inspeksi) menggunakan `lockForUpdate()`.
   - `PricingService`: Menangani kalkulasi harga rental (1–8 jam basis harian, overtime, MOB/DEMOB per physical unit, All-in/Non All-in).
   - `RecommendationService`: Weighted scoring algorithm berdasarkan kriteria medan, kapasitas, dan budget.
2. **Implementasikan Actions Utama:**
   - `CreateBookingAction`, `SubmitBookingAction`, `ApproveBookingAction`, `RejectBookingAction`, `CancelBookingAction`, `RescheduleBookingAction`.
   - `AssignUnitAction`, `ReplaceUnitAction`.
   - `DispatchRentalAction`, `CheckInRentalAction`, `CheckOutRentalAction`.
   - `SubmitTimesheetAction`, `ApproveTimesheetAction`, `ReviseTimesheetAction`.
   - `VerifyPaymentAction`, `RejectPaymentAction`, `ProcessRefundAction`.
3. **Form Requests & API Resources:** Buat Form Request dan Resource transformer untuk setiap endpoint modul sesuai `06-api-contract-v3.md`.
