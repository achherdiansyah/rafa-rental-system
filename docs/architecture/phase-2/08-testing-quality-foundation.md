# Testing & Code Quality Foundation - RAFA Rental System

Standar, konvensi, dan perintah quality gate untuk backend Laravel RAFA Rental System (Phase 2H).

---

## 1. Struktur Direktori Test

```
tests/
├── Unit/                   # Pengujian unit terisolasi (tanpa HTTP, tanpa DB)
│   ├── Enums/              # Verifikasi nilai enum sesuai state machine Phase 1
│   └── Support/            # Pengujian kelas utility (ApiResponse, AuditLogger, FileSecurity)
├── Feature/                # Pengujian integrasi request-response melalui HTTP layer
│   ├── Api/                # Endpoint API (health, exception handling)
│   └── Auth/               # Autentikasi dan otorisasi (401, 403, role matrix)
└── (Domain Phase 3+)       # BookingTest, PaymentTest, dll (akan ditambahkan pada phase domain)
```

---

## 2. Konvensi Penamaan Test

Penamaan test menggunakan snake_case dengan kata kerja deskriptif untuk memudahkan pembacaan output:

```
test_health_check_endpoint_returns_expected_json
test_unauthenticated_request_returns_standard_401
test_booking_status_values_match_phase1_state_machine
test_sanitize_redacts_sensitive_keys
```

### Kelompok Test:
- **Unit Test:** Menguji satu kelas atau satu method secara terisolasi (tanpa `RefreshDatabase`, tanpa HTTP call).
- **Feature Test:** Menguji alur request HTTP penuh menggunakan `$this->getJson()`, `$this->postJson()`, dll.

---

## 3. Cakupan Test Foundation (Phase 2)

| Test Suite | File | Kasus Diuji |
|---|---|---|
| `Unit/Enums/EnumTest` | Nilai string enum = state machine Phase 1 | 8 kasus |
| `Unit/Support/ApiResponseTest` | Struktur envelope sukses & error | 2 kasus |
| `Unit/Support/AuditLoggerTest` | Build payload, sanitize redact, guest actor | 3 kasus |
| `Unit/Support/FileSecurityTest` | Validation rules, path format, MIME constants | 5 kasus |
| `Feature/Api/HealthCheckTest` | `GET /api/v1/health` response exact | 1 kasus |
| `Feature/Api/ExceptionHandlingTest` | 404, domain 409, state 409, 500 prod & debug | 5 kasus |
| `Feature/Auth/AuthAndRoleTest` | 401, 403 role block, 200 role pass, Gate matrix | 6 kasus |
| `Feature/ExampleTest` | App boot response | 1 kasus |

**Total: 32 test, 93 assertions.**

---

## 4. Quality Gate Commands

### Jalankan seluruh test suite:
```bash
php artisan test
```

### Jalankan hanya unit test:
```bash
php artisan test --testsuite=Unit
```

### Jalankan test spesifik file:
```bash
php artisan test --filter HealthCheckTest
php artisan test --filter AuthAndRoleTest
```

### Cek formatting (Pint dry-run):
```bash
./vendor/bin/pint --test
```

### Perbaiki formatting otomatis:
```bash
./vendor/bin/pint
```

---

## 5. Aturan Quality Gate

1. `php artisan test` — **wajib 100% green** sebelum merge ke main branch.
2. `./vendor/bin/pint --test` — **wajib clean** sebelum commit. Jalankan `./vendor/bin/pint` untuk auto-fix.
3. **Static Analysis:** Larastan/PHPStan tidak diinstall karena tidak didefinisikan dalam PRD V3. Dapat ditambahkan di Phase selanjutnya jika diperlukan.
4. **Tidak ada business logic test** di Phase 2. Domain test dibuat bersamaan saat implementasi modul domain (Phase 3+).
