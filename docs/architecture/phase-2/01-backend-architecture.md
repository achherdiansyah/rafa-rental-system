# Backend Architecture Specification - RAFA Rental System

Dokumen rujukan standar arsitektur backend Laravel 12 untuk RAFA Rental System (Phase 2).

---

## 1. Folder Structure

```
backend/app/
├── Actions/          # Single-purpose business operation classes
├── DTOs/             # Data Transfer Objects (immutable value objects)
├── Enums/            # PHP 8.1+ native enums (status, role, type)
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── ApiController.php       # Base controller with response helpers
│   │       └── V1/                     # Versioned API controllers
│   ├── Requests/     # Form Request validation classes
│   └── Resources/    # API Resource / transformer classes
├── Models/           # Eloquent models
├── Policies/         # Authorization policy classes
├── Providers/        # Service providers
├── Rules/            # Custom validation rules
├── Services/         # Stateless domain service classes
└── Support/          # Reusable utilities (ApiResponse, helpers)
```

---

## 2. Tanggung Jawab Setiap Folder

### `Actions/`
Kelas aksi tunggal (*single-purpose*) yang mengeksekusi satu operasi bisnis. Setiap Action memiliki satu public method `execute()` atau `handle()`.
- Contoh: `ApproveBookingAction`, `AssignUnitAction`, `ExpireInvoicesAction`.
- Boleh memanggil Service, Model, atau Action lain.
- Mengandung database transaction jika diperlukan.

### `DTOs/`
Data Transfer Object — kelas PHP readonly sederhana yang membungkus data terstruktur tanpa logika bisnis.
- Contoh: `CreateBookingData`, `PriceCalculationResult`.
- Digunakan untuk mentransfer data antar layer (Controller -> Action -> Service).

### `Enums/`
PHP 8.1+ backed enums untuk mendefinisikan nilai status dan tipe yang tersegel (*sealed values*).
- Contoh: `BookingStatus`, `PaymentStatus`, `UserRole`, `EquipmentUnitStatus`.
- Digunakan pada kolom ENUM database dan validasi input.

### `Http/Controllers/Api/V1/`
Controller tipis (*thin controllers*) yang hanya bertanggung jawab untuk:
1. Menerima request dan mendelegasikan validasi ke Form Request.
2. Memanggil satu Action atau Service.
3. Mengembalikan respons JSON standar via `ApiResponse` atau method bawaan `ApiController`.

### `Http/Requests/`
Form Request classes untuk validasi input.
- Setiap endpoint mutasi (POST/PUT/PATCH) wajib memiliki Form Request tersendiri.
- Aturan validasi ditulis di method `rules()`, otorisasi di `authorize()`.

### `Http/Resources/`
API Resource classes untuk transformasi data model menjadi format respons JSON publik.
- Mencegah exposur kolom sensitif (password, internal IDs jika diperlukan).

### `Models/`
Eloquent Model classes. Setiap model mendefinisikan:
- `$fillable` / `$guarded`
- `$casts` (termasuk Enum cast)
- Relationships
- Scopes (jika sering dipakai di query)

### `Policies/`
Laravel Policy classes untuk otorisasi akses per resource.
- Contoh: `BookingPolicy`, `InvoicePolicy`.
- Di-register pada `AuthServiceProvider` atau auto-discovery.

### `Rules/`
Custom validation rules yang reusable.
- Contoh: `ValidStateTransition`, `AvailableEquipmentUnit`.

### `Services/`
Stateless domain service classes untuk logika yang digunakan oleh lebih dari satu Action, atau logika yang terlalu kompleks untuk satu Action.
- Contoh: `AvailabilityService`, `PricingService`.
- Tidak boleh mengakses `Request` atau `Response` secara langsung.

### `Support/`
Utility dan helper classes yang bersifat business-independent.
- `ApiResponse`: Static helper untuk format JSON envelope standar.

---

## 3. Coding Principles

### 3.1 Single Responsibility
Setiap kelas memiliki satu alasan untuk berubah. Controller tidak menghitung harga. Service tidak menangani HTTP response.

### 3.2 DRY (Don't Repeat Yourself)
Logika yang dipakai di 2+ tempat diekstrak ke Service, Action, atau helper.

### 3.3 Separation of Concerns
- **Controller:** HTTP layer (input/output).
- **Form Request:** Validasi input.
- **Action:** Orchestrasi satu operasi bisnis.
- **Service:** Logika domain yang reusable.
- **Model:** Data access dan relationships.
- **Policy:** Otorisasi.

### 3.4 Dependency Injection
Gunakan constructor injection. Hindari `app()` atau `resolve()` kecuali di service provider.

### 3.5 Strict Typing
- Semua method harus memiliki return type declaration.
- Parameter method harus typed.
- Gunakan `declare(strict_types=1)` pada file PHP kritis.

### 3.6 Naming Conventions
- Controller: `{Resource}Controller` (contoh: `BookingController`)
- Action: `{Verb}{Resource}Action` (contoh: `ApproveBookingAction`)
- Service: `{Domain}Service` (contoh: `PricingService`)
- Form Request: `{Verb}{Resource}Request` (contoh: `StoreBookingRequest`)
- Resource: `{Resource}Resource` (contoh: `BookingResource`)
- Policy: `{Resource}Policy` (contoh: `BookingPolicy`)
- Enum: `{Resource}{Attribute}` (contoh: `BookingStatus`)

---

## 4. Controller Rules

Controller **DILARANG** berisi:
- Kalkulasi bisnis (pricing, availability, billing)
- Logika state transition
- Database transaction kompleks (multi-table)
- Payment settlement logic
- Direct query builder chains panjang

Controller **HARUS**:
- Menerima validated input dari Form Request
- Memanggil **satu** Action atau Service method
- Mengembalikan JSON response via `ApiResponse` atau `$this->success()`

Contoh pattern controller tipis:
```php
public function approve(ApproveBookingRequest $request, Booking $booking): JsonResponse
{
    $result = $this->approveBookingAction->execute($booking);
    return $this->success($result, 'Booking approved');
}
```

---

## 5. Service & Action Rules

### Actions
- Satu public method: `execute()` atau `handle()`.
- Boleh membungkus `DB::transaction()`.
- Boleh memanggil Service atau Action lain.
- Return tipe eksplisit (DTO, Model, atau void).

### Services
- Stateless (tidak menyimpan state antar panggilan).
- Tidak bergantung pada HTTP Request/Response.
- Boleh dipanggil dari Action, Artisan Command, atau Job.
- Contoh: `PricingService::calculate(BookingData $data): PriceResult`

---

## 6. Dependency Rules

### Production Dependency DILARANG:
- Redis (wajib)
- Horizon
- MinIO / S3 (wajib)
- Docker runtime
- Custom Nginx config
- Persistent Node.js server

### Production Stack WAJIB Compatible:
- PHP 8.2+ (cPanel PHP selector)
- MySQL 8.4 (cPanel MySQL)
- Apache / LiteSpeed (cPanel default)
- `storage:link` untuk public file access
- `QUEUE_CONNECTION=database`
- `CACHE_STORE=database` atau `file`
- cPanel Cron Job: `php artisan schedule:run`

---

## 7. Testing Expectations

- Testing framework: Pest (sudah terinstall via `phpunit/phpunit` + `nunomaduro/collision`).
- Test files berada di `tests/Feature/` dan `tests/Unit/`.
- Setiap Action dan Service kritis wajib memiliki unit test.
- State machine transitions wajib di-test (valid dan forbidden).
- Monetary calculations wajib di-test untuk presisi DECIMAL.
- Gunakan `RefreshDatabase` trait untuk feature tests.

---

## 8. Deployment Considerations (cPanel)

### Laravel Backend
1. Upload seluruh isi folder `backend/` ke server (kecuali `node_modules`, `.env`, `vendor`).
2. Jalankan `composer install --no-dev --optimize-autoloader` di server.
3. Set document root cPanel ke folder `backend/public/`.
4. Buat symbolic link: `php artisan storage:link`.
5. Konfigurasi `.env` production (APP_ENV=production, APP_DEBUG=false, DB credentials).
6. Run `php artisan migrate --force`.
7. Setup cPanel Cron Job: `* * * * * cd /home/user/backend && php artisan schedule:run >> /dev/null 2>&1`

### React Frontend
1. Build: `npm run build` → menghasilkan folder `dist/`.
2. Upload `dist/` ke subdomain atau subdirectory cPanel.
3. Konfigurasi `.htaccess` untuk SPA routing fallback.
