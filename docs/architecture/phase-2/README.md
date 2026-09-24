# Phase 2: Backend Foundation Summary - RAFA Rental System

Dokumentasi akhir rangkuman arsitektur, fondasi infrastruktur backend Laravel 12, dan standar kualitas untuk RAFA Rental System (Phase 2I Final Review).

---

## 1. Tujuan Phase 2
Membangun fondasi backend Laravel 12 yang bersih, modular, mudah diuji, dapat dipelihara (*maintainable*), dan siap dideploy ke shared hosting cPanel tanpa dependensi eksternal berat (No Redis/Docker/Horizon/MinIO), serta menyiapkan *quality gates* sebelum implementasi domain bisnis dimulai.

---

## 2. Architecture

### 2.1 Struktur Direktori Backend
```
backend/app/
├── Actions/                  # Single-purpose business actions (ApproveBookingAction, etc.)
├── DTOs/                     # Readonly, typed Data Transfer Objects
├── Enums/                    # PHP 8.1+ backed enums (BookingStatus, UserRole, etc.)
├── Exceptions/               # Domain exception hierarchy (DomainException, BusinessRuleException)
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── ApiController.php        # Base controller with standard response helpers
│   │       └── V1/
│   │           └── HealthCheckController.php # Infrastructure health endpoint
│   ├── Middleware/
│   │   └── RequireRole.php              # RBAC middleware (USER, ADMIN, OWNER)
│   ├── Requests/             # Form Request validation classes
│   └── Resources/            # API Resources for serialization
├── Models/
│   └── User.php              # User model with Sanctum tokens and role casting
├── Policies/
│   └── BasePolicy.php        # Reusable authorization base policy
├── Providers/
│   └── AppServiceProvider.php# Global Gates and Rate Limiters
├── Rules/                    # Custom validation rules
├── Services/                 # Stateless domain calculation services
└── Support/
    ├── ApiResponse.php       # Standard JSON envelope formatter
    ├── AuditLogger.php       # Sanitized audit logging helper
    └── FileSecurity.php      # MIME, size, extension, and secure path validation
```

### 2.2 Dependency Direction
`HTTP Request` → `Form Request` → `Controller (Tipis)` → `DTO` → `Action` → `Domain Service` → `Eloquent Model` → `Database`.
- Controller dilarang memuat logika bisnis, kalkulasi harga, query kompleks, atau transisi state liar.
- Service tidak mengakses HTTP request/response.
- DTO murni terisolasi dari framework HTTP.

---

## 3. API Foundation
- **Base URL:** `/api/v1`
- **Route Names:** `api.v1.{resource}.{action}`
- **Standard Success Envelope:**
  ```json
  {
    "success": true,
    "message": "Success message.",
    "data": {},
    "meta": {}
  }
  ```
- **Health Endpoint:** `GET /api/v1/health` (Public, HTTP 200).
- **Serialization:** Menggunakan Laravel API Resource, tidak mengekspos model Eloquent secara mentah.

---

## 4. Error Handling
- **Standard Error Envelope:**
  ```json
  {
    "success": false,
    "message": "Human readable message.",
    "errors": {},
    "code": "MACHINE_READABLE_CODE"
  }
  ```
- **Mapping HTTP Status:**
  - `401` → `UNAUTHENTICATED`
  - `403` → `FORBIDDEN_ACTION`
  - `404` → `RESOURCE_NOT_FOUND`
  - `409` → `BUSINESS_RULE_VIOLATION`, `INVALID_STATE_TRANSITION`, `CONFLICT`
  - `422` → `VALIDATION_FAILED`
  - `429` → `RATE_LIMIT_EXCEEDED`
  - `500` → `INTERNAL_SERVER_ERROR`
- **Production Safety:** Pada `APP_DEBUG=false`, pesan 500 disamarkan menjadi generic message, stack trace dan path internal disembunyikan.

---

## 5. Application Layer
- **DTO:** `readonly class` PHP 8.2 dengan factory `fromArray()` / `fromRequest()`.
- **Action:** 1 kelas = 1 use case, 1 public method `execute()`, membungkus `DB::transaction()`.
- **Domain Service:** Stateless, injeksi dependensi via constructor, menangani logika multi-use case (misal availability & pricing kalkulasi).
- **Form Request:** Validasi input terisolasi, helper `toDTO()`.

---

## 6. Enum / State Foundation
Seluruh 11 enum resmi dari Phase 1 diimplementasikan sebagai string-backed enum:
1. `BookingStatus` (13 states: DRAFT → EXPIRED)
2. `RentalStatus` (9 states: PENDING_ASSIGNMENT → CANCELLED)
3. `EquipmentStatus` (8 states: AVAILABLE → DECOMMISSIONED)
4. `InvoiceStatus` (7 states: DRAFT → CANCELLED)
5. `PaymentStatus` (4 states: PENDING → REJECTED)
6. `RefundStatus` (6 states: REQUESTED → COMPLETED)
7. `UserRole` (3 roles: USER, ADMIN, OWNER)
8. `PriceScheme` (ALL_IN, NON_ALL_IN)
9. `InvoiceType` (RENTAL, MOB_DEMOB, ADDITIONAL_CHARGE, PENALTY, DAMAGE)
10. `AssignmentStatus` (ASSIGNED, REPLACED, CANCELLED, COMPLETED)
11. `TimesheetStatus` (DRAFT, SUBMITTED, APPROVED, REJECTED)
- **State Transition Pattern:** Transisi dilarang dilakukan sembarangan (`$model->status = ...`), melainkan dikontrol eksklusif via Domain Actions yang memvalidasi precondition dan melempar `InvalidStateTransitionException` jika melanggar.

---

## 7. Auth / RBAC
- **Autentikasi:** Laravel Sanctum token-based authentication.
- **Roles:** Tepat 3 role (`USER`, `ADMIN`, `OWNER`).
- **Middleware:** `RequireRole` (`role:ADMIN,OWNER` / `role:OWNER`).
- **Gates:** 13 global capability gates terdaftar di `AppServiceProvider` sesuai matriks Phase 1:
  - Admin/Owner: `approve-booking`, `verify-payment`, `manage-equipment`, `view-admin-dashboard`.
  - Admin-only: `assign-units`, `dispatch-unit`, `validate-bast`, `validate-timesheet`, `process-refund`.
  - Owner-only: `view-owner-dashboard`, `view-revenue-reports`, `manage-pricing-master`, `approve-refund`, `manage-bank-accounts`, `decommission-equipment`.

---

## 8. Security & Audit
- **AuditLogger:** Format payload terstandarisasi (actor, entity, action, old_state, new_state, metadata, ip, user_agent). Redaksi otomatis kunci sensitif (`password`, `token`, `secret`).
- **FileSecurity:** Validasi MIME (`pdf`, `jpeg`, `png`, `webp`), ekstensi, ukuran maks 5 MB (5120 KB), dan penamaan hash unik berbasis tanggal (`category/YYYY/MM/hash.ext`).
- **Financial Immutability:** Invoice, payment, refund, dan activity log dilarang di-hard delete.
- **Rate Limiting:**
  - `api`: 60 req/menit per user/IP.
  - `auth`: 5 req/menit per IP.
  - `file-upload`: 10 req/menit per user/IP.

---

## 9. Testing & Quality Gate
- **Test Suite:** **32 test passed (93 assertions)** via `php artisan test`.
  - Unit Tests: Enum (8), ApiResponse (2), AuditLogger (3), FileSecurity (5).
  - Feature Tests: HealthCheck (1), ExceptionHandling (5), AuthAndRole (6), AppBoot (2).
- **Code Style:** **Laravel Pint 100% clean** (`./vendor/bin/pint --test` passed).
- **Quality Rule:** 100% green tests & clean Pint wajib terpenuhi sebelum commit.

---

## 10. Production Compatibility
- Target: Shared hosting / cPanel (PHP 8.2+, MySQL 8.4).
- **Zero Heavy Dependency:**
  - TIDAK butuh Docker di production.
  - TIDAK butuh Redis / Horizon (Queue & Cache menggunakan MySQL/Database).
  - TIDAK butuh MinIO (File storage menggunakan disk lokal Laravel via `storage:link`).
  - TIDAK butuh custom Nginx (Kompatibel Apache / LiteSpeed `.htaccess`).
  - TIDAK butuh Node.js daemon persistent.

---

## 11. Daftar File yang Dibuat / Diubah di Phase 2

### Kode Sumber (`backend/app/`)
| File | Deskripsi |
|---|---|
| `app/Http/Controllers/Api/ApiController.php` | Base API Controller |
| `app/Http/Controllers/Api/V1/HealthCheckController.php` | Health check endpoint |
| `app/Http/Middleware/RequireRole.php` | Role verification middleware |
| `app/Policies/BasePolicy.php` | Authorization base policy |
| `app/Models/User.php` | User model (Sanctum + UserRole enum cast) |
| `app/Support/ApiResponse.php` | Static JSON envelope helper |
| `app/Support/AuditLogger.php` | Audit trail payload builder & sanitizer |
| `app/Support/FileSecurity.php` | Secure upload validator & path generator |
| `app/Enums/ErrorCode.php` | 9 core machine-readable error codes |
| `app/Enums/BookingStatus.php` | 13 booking states |
| `app/Enums/RentalStatus.php` | 9 rental states |
| `app/Enums/EquipmentStatus.php` | 8 physical unit states |
| `app/Enums/InvoiceStatus.php` | 7 invoice states |
| `app/Enums/PaymentStatus.php` | 4 payment states |
| `app/Enums/RefundStatus.php` | 6 refund states |
| `app/Enums/UserRole.php` | 3 roles (USER, ADMIN, OWNER) |
| `app/Enums/PriceScheme.php` | ALL_IN, NON_ALL_IN |
| `app/Enums/InvoiceType.php` | 5 invoice categories |
| `app/Enums/AssignmentStatus.php` | 4 unit assignment states |
| `app/Enums/TimesheetStatus.php` | 4 timesheet states |
| `app/Exceptions/DomainException.php` | Base domain exception |
| `app/Exceptions/BusinessRuleException.php` | 409 business rule violation |
| `app/Exceptions/InvalidStateTransitionException.php` | 409 invalid state transition |
| `app/Exceptions/ResourceConflictException.php` | 409 concurrency/quota conflict |
| `app/Providers/AppServiceProvider.php` | Global Gates & Rate Limiters |
| `bootstrap/app.php` | Global API exception renderer & middleware alias |
| `routes/api.php` | Versioned route grouping `/api/v1` |
| `.env.example` | Clean cPanel-compatible environment template |

### Tests (`backend/tests/`)
| File | Deskripsi |
|---|---|
| `tests/Unit/Enums/EnumTest.php` | 8 test kasus kesesuaian enum |
| `tests/Unit/Support/ApiResponseTest.php` | 2 test kasus envelope respons |
| `tests/Unit/Support/AuditLoggerTest.php` | 3 test kasus audit payload & sanitasi |
| `tests/Unit/Support/FileSecurityTest.php` | 5 test kasus validasi file & path |
| `tests/Feature/Api/HealthCheckTest.php` | 1 test kasus endpoint `/api/v1/health` |
| `tests/Feature/Api/ExceptionHandlingTest.php` | 5 test kasus penanganan error HTTP & production masking |
| `tests/Feature/Auth/AuthAndRoleTest.php` | 6 test kasus autentikasi Sanctum & otorisasi Gate/Role |

### Dokumentasi Arsitektur (`docs/architecture/phase-2/`)
| File | Deskripsi |
|---|---|
| `01-backend-architecture.md` | Standar struktur folder, kaidah controller tipis, dan deployment cPanel |
| `02-api-foundation.md` | API versioning, standard envelope, dan blueprint 17 modul |
| `03-error-handling.md` | Error envelope, exception mapping, dan proteksi leak data |
| `04-application-layer-patterns.md` | Pola DTO, Action, Service, Form Request, dan DB transaction |
| `05-enum-state-foundation.md` | Spesifikasi enum dan state transition pattern |
| `06-auth-authorization-foundation.md` | Sanctum token auth, RBAC 3 role, dan global gates |
| `07-security-audit-foundation.md` | Audit logger, file security, rate limiting, dan financial immutability |
| `08-testing-quality-foundation.md` | Quality gates, test naming conventions, dan Pint checklist |
| `README.md` | Dokumen ini (Rangkuman final Phase 2) |

---

## 12. Issues & Resolusi
- **Issue:** Framework default `.env` tidak memiliki `APP_KEY`.
  - **Resolusi:** Dijalankan `php artisan key:generate`.
- **Issue:** Formatting awal memiliki perbedaan gaya sintaks strict types.
  - **Resolusi:** Dijalankan `./vendor/bin/pint` dan diverifikasi dengan `./vendor/bin/pint --test` (100% clean).
- **Issue:** Scope Guard Verifikasi.
  - **Resolusi:** Diverifikasi tidak ada model, migrasi, atau controller bisnis yang dibuat mendahului jadwal (hanya `User.php` dan `HealthCheckController.php`).

---

## 13. Rekomendasi untuk Phase 3 (Database Schema & Migrations)
1. **Urutan Migrasi:** Buat migrasi mengikuti hirarki dependensi tabel dari Data Dictionary Phase 1 (`05-data-dictionary-v3.md`):
   - Level 0 (Master bebas): `equipment_types`, `bank_accounts`, `business_calendars`.
   - Level 1 (Tergantung User): `customer_profiles`, `project_locations`, `carts`, `equipment_models`.
   - Level 2 (Inventaris & Detail): `equipment_units`, `equipment_prices`, `cart_items`, `equipment_price_versions`.
   - Level 3 (Transaksi): `bookings`, `booking_details`, `booking_unit_assignments`.
   - Level 4 (Operasional & Finansial): `rentals`, `rental_details`, `timesheets`, `timesheet_revisions`, `invoices`, `invoice_details`, `payments`, `refunds`.
   - Level 5 (Sistem): `attachments`, `activity_logs`, `recommendation_*`.
2. **Kekakuan Tipe Finansial:** Pastikan semua kolom moneter menggunakan `$table->decimal(..., 15, 2)` tanpa float.
3. **Foreign Key Integrity:** Terapkan onDelete `RESTRICT` pada relasi finansial/booking untuk mencegah data terhapus diam-diam.
4. **Model Casts:** Setiap model baru langsung dipasangkan enum casting ke backed enum yang sudah dibuat di Phase 2E.
