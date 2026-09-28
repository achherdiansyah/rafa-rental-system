# 02 — Security Hardening (Phase 13B)

Audit & perbaikan keamanan menyeluruh, plus test pembuktian.

## Hasil Audit

| Area | Temuan | Aksi |
|---|---|---|
| Auth (Sanctum) | token tidak pernah kedaluwarsa | `sanctum.expiration` default **7 hari** (`SANCTUM_EXPIRATION`) |
| Rate limiting | API utama & upload tidak dibatasi | `throttle:api` (60/min) di grup `auth:sanctum`; `throttle:auth` (5/min) login; `throttle:file-upload` (10/min) di upload bukti/signature/foto/refund |
| Production error response | `HttpException` bisa bocor message | message hanya diekspos saat `app.debug=true`; default generic; fallback 500 tetap tanpa stack/file/line |
| Validation / mass assignment | tidak ada `$guarded=[]`; masukan create semuanya `validated()` | tanpa perubahan kode; ditambah tes non-escalation role |
| IDOR/BOLA | seluruh akses objek via policy (`Rental/Timesheet/Payment/Refund/Invoice`); laporan scoped | tanpa celah; tes matriks & scope sudah ada |
| SQL injection | semua parameterized / whitelist sort & filter | tanpa perubahan |
| XSS/CSRF | React escape default; API token stateless (no cookie CSRF surface); web tetap CSRF | tanpa perubahan |
| File upload | semua upload via `FileSecurity` (mime/ekstensi/5MB) + disk privat utk bukti/tanda tangan/refund | tanpa perubahan; ditambah throttle |
| Private file access | `AttachmentResource.url` null utk privat; hanya endpoint `proof` berpolicy | tanpa perubahan |
| Sensitive data / secrets | `.env*` di-gitignore; `.env.example` tanpa secret riil | tanpa perubahan |
| Audit log | `AuditLogger` di seluruh aksi bisnis | tanpa perubahan |
| Financial endpoint protection | policies (Invoice/Payment/Refund) + scope | tanpa perubahan |

## Perbaikan Diterapkan

1. `config/sanctum.php` → `expiration` default 10080 menit.
2. `routes/api.php` → `throttle:api` pada grup autentikasi; `throttle:file-upload`
   pada signature timesheet, submit payment, complete refund, upload foto.
3. `bootstrap/app.php` → `HttpException` non-debug memakai pesan generik.

## Test — `SecurityHardeningTest` (6 kasus, 27 assertions)

- Token kedaluwarsa default (SANCTUM_EXPIRATION).
- Login dibatasi (6 percobaan → 429); upload payment dibatasi (11 → 429).
- Error 500 di environment non-debug: tanpa stack trace / pesan internal.
- Mass assignment tidak bisa meng-upgrade role.
- Bukti transfer tetap private (`url:null`).

**Backend total 439 passed (2409 assertions)**; Pint clean. Frontend tak tersentuh.

## Files

- `config/sanctum.php`
- `routes/api.php` (throttle api + upload)
- `bootstrap/app.php` (error response)
- `tests/Feature/Security/SecurityHardeningTest.php`