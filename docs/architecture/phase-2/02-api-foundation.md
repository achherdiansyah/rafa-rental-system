# API Foundation Specification - RAFA Rental System

Dokumen standar API REST `/api/v1` untuk RAFA Rental System (Phase 2B).

---

## 1. API Versioning

Base URL seluruh endpoint: `/api/v1`

Route dikelompokkan menggunakan `Route::prefix('v1')->name('api.v1.')`. Jika ada breaking change di masa depan, versi baru (`/api/v2`) ditambahkan berdampingan tanpa memodifikasi `v1`.

---

## 2. Respons Standar (Envelope JSON)

Seluruh endpoint menggunakan envelope JSON yang konsisten, diproduksi oleh `App\Support\ApiResponse`.

### 2.1 Sukses
```json
{
  "success": true,
  "message": "Booking approved.",
  "data": { "id": 1, "status": "APPROVED" }
}
```

### 2.2 Sukses dengan Paginasi
```json
{
  "success": true,
  "message": "Success",
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 100,
    "last_page": 7
  }
}
```

### 2.3 Error (Bisnis / State)
```json
{
  "success": false,
  "message": "Booking cannot be cancelled after dispatching.",
  "error_code": "INVALID_STATE_TRANSITION",
  "errors": null
}
```

### 2.4 Error Validasi (422)
```json
{
  "success": false,
  "message": "Validation failed",
  "error_code": "VALIDATION_ERROR",
  "errors": {
    "start_date": ["The start date must be a future date."]
  }
}
```

---

## 3. HTTP Verbs & Naming Conventions

| Operasi | Verb | Contoh URL | Route Name |
|---|---|---|---|
| List collection | GET | `/api/v1/bookings` | `api.v1.bookings.index` |
| Show single | GET | `/api/v1/bookings/{id}` | `api.v1.bookings.show` |
| Create resource | POST | `/api/v1/bookings` | `api.v1.bookings.store` |
| Update (full) | PUT | `/api/v1/bookings/{id}` | `api.v1.bookings.update` |
| Update (partial) | PATCH | `/api/v1/bookings/{id}` | `api.v1.bookings.update` |
| Delete | DELETE | `/api/v1/bookings/{id}` | `api.v1.bookings.destroy` |
| Custom action | POST | `/api/v1/bookings/{id}/approve` | `api.v1.bookings.approve` |

Nama route: `api.v1.{resource}.{action}`. Selalu snake_case pada URL, camelCase tidak digunakan.

---

## 4. Pagination Standard

Endpoint yang mengembalikan koleksi menggunakan Laravel `paginate()`. Response selalu menyertakan `meta` block.

Query params standar:
- `?page=1` — halaman aktif
- `?per_page=15` — jumlah item per halaman (default 15, max 100)

---

## 5. Resource Pattern (API Resource)

Seluruh transformasi data model ke JSON menggunakan `Illuminate\Http\Resources\Json\JsonResource` atau `ResourceCollection`. Model Eloquent tidak dikembalikan mentah dari controller.

Contoh struktur:
```
app/Http/Resources/
├── BookingResource.php
├── BookingCollection.php
├── InvoiceResource.php
└── ...
```

Setiap Resource class menentukan field mana yang ditampilkan ke publik dan field mana yang disembunyikan. Tidak ada `password`, `remember_token`, atau data internal yang diekspos.

---

## 6. Module Route Blueprint (Conceptual, Phase 2D+)

| No | Modul | Prefix URL | Auth |
|---|---|---|---|
| 1 | Auth | `/api/v1/auth` | Public |
| 2 | Profile | `/api/v1/profile` | Sanctum |
| 3 | Project Location | `/api/v1/project-locations` | Sanctum |
| 4 | Equipment | `/api/v1/equipment` | Public + Sanctum |
| 5 | Pricing | `/api/v1/pricing` | Public + Sanctum |
| 6 | Recommendation | `/api/v1/recommendations` | Sanctum |
| 7 | Cart | `/api/v1/cart` | Sanctum |
| 8 | Booking | `/api/v1/bookings` | Sanctum |
| 9 | Rental | `/api/v1/rentals` | Sanctum |
| 10 | Timesheet | `/api/v1/timesheets` | Sanctum |
| 11 | Invoice | `/api/v1/invoices` | Sanctum |
| 12 | Payment | `/api/v1/payments` | Sanctum |
| 13 | Refund | `/api/v1/refunds` | Sanctum |
| 14 | Notification | `/api/v1/notifications` | Sanctum |
| 15 | Report | `/api/v1/reports` | Sanctum (OWNER) |
| 16 | Admin Dashboard | `/api/v1/admin/dashboard` | Sanctum (ADMIN) |
| 17 | Owner Dashboard | `/api/v1/owner/dashboard` | Sanctum (OWNER) |

---

## 7. Health Endpoint

**`GET /api/v1/health`** — public, tidak butuh autentikasi.

Respons:
```json
{
  "success": true,
  "message": "RAFA Rental System API is running.",
  "version": "v1"
}
```

Digunakan untuk verifikasi availability backend oleh frontend dan monitoring eksternal.

---

## 8. Error Code Registry

Kode error domain bisnis distandardisasi di `App\Support\ApiResponse`. Katalog lengkap di `docs/architecture/phase-1/08-error-handling-v3.md`.

| Error Code | HTTP | Konteks |
|---|---|---|
| `VALIDATION_ERROR` | 422 | Field input tidak valid |
| `NOT_FOUND` | 404 | Resource tidak ditemukan |
| `FORBIDDEN_ACTION` | 403 | Role tidak memiliki izin |
| `UNAUTHENTICATED` | 401 | Token tidak ada / invalid |
| `INVALID_STATE_TRANSITION` | 409 | Aksi tidak valid pada state saat ini |
| `BOOKING_CONFLICT` | 409 | Kuota unit habis |
| `BOOKING_EXPIRED` | 409 | Invoice deadline terlewati |
| `UNIT_UNAVAILABLE` | 404 | Unit fisik tidak tersedia |
| `UNIT_ASSIGNMENT_CONFLICT` | 409 | Unit fisik sudah terikat jadwal lain |
| `PAYMENT_REJECTED` | 409 | Bukti bayar ditolak Admin |
| `PAYMENT_DEADLINE_EXCEEDED` | 409 | Upload bukti bayar pasca deadline |
| `OVERPAYMENT_REQUIRES_REVIEW` | 409 | Kelebihan bayar butuh tindak lanjut |
| `RESCHEDULE_CONFLICT` | 409 | Kuota habis di jadwal baru |
| `TIMESHEET_REQUIRES_REVISION` | 409 | Timesheet ditolak, revisi diperlukan |
| `RATE_LIMIT_EXCEEDED` | 429 | Request terlalu banyak |
