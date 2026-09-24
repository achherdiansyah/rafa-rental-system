# Backend Exception & Error Handling - RAFA Rental System

Dokumentasi penanganan eksepsi dan format error backend Laravel untuk RAFA Rental System (Phase 2C).

---

## 1. Standar Error Envelope JSON

Seluruh respons error di sistem (HTTP 4xx dan 5xx) dijamin menggunakan struktur JSON tunggal:

```json
{
  "success": false,
  "message": "Human readable error message.",
  "errors": {},
  "code": "MACHINE_READABLE_ERROR_CODE"
}
```

### Properti Envelope:
- `success` *(boolean)*: Selalu `false`.
- `message` *(string)*: Deskripsi pesan error yang ramah pengguna.
- `errors` *(object/array)*: Rincian field validasi (pada 422) atau debug trace (pada 500 saat `APP_DEBUG=true`). Pada production, bernilai `{}` kosong.
- `code` *(string)*: Kode identifikasi unik berbentuk SCREAMING_SNAKE_CASE untuk di-parse oleh klien frontend.

---

## 2. Pemetaan Kategori Eksepsi ke HTTP Status

| Kategori Eksepsi | Kelas Eksepsi | HTTP Code | Error Code Default (`code`) |
|---|---|---|---|
| **Validation Error** | `Illuminate\Validation\ValidationException` | `422` | `VALIDATION_FAILED` |
| **Authentication Error** | `Illuminate\Auth\AuthenticationException` | `401` | `UNAUTHENTICATED` |
| **Authorization Error** | `AccessDeniedHttpException`, `AuthorizationException` | `403` | `FORBIDDEN_ACTION` |
| **Resource Not Found** | `NotFoundHttpException`, `ModelNotFoundException` | `404` | `RESOURCE_NOT_FOUND` |
| **Business Rule Violation** | `App\Exceptions\BusinessRuleException` | `409` | `BUSINESS_RULE_VIOLATION` |
| **Invalid State Transition**| `App\Exceptions\InvalidStateTransitionException`| `409` | `INVALID_STATE_TRANSITION` |
| **Resource Conflict** | `App\Exceptions\ResourceConflictException` | `409` | `CONFLICT` |
| **Rate Limit Exceeded** | `TooManyRequestsHttpException` | `429` | `RATE_LIMIT_EXCEEDED` |
| **Server Error (Unhandled)**| `Throwable` | `500` | `INTERNAL_SERVER_ERROR` |

---

## 3. Infrastruktur Kelas Eksepsi Domain

Terletak di `app/Exceptions/`:
- `DomainException`: Kelas abstrak dasar yang membawa `$errorCode`, `$errors`, dan `$httpStatus`.
- `BusinessRuleException`: Dilempar ketika aksi melanggar aturan bisnis (misal: 1 booking multi lokasi, deadline lewat).
- `InvalidStateTransitionException`: Dilempar ketika aksi melanggar state machine (misal: cancel setelah dispatch).
- `ResourceConflictException`: Dilempar saat terjadi bentrok kuota atau tumpang tindih assignment unit.

---

## 4. Keamanan Lingkungan Produksi (Production Safety)

Ketika `APP_ENV=production` dan `APP_DEBUG=false`:
- Pesan 500 di-mask menjadi: `"An internal server error occurred."`
- Objek `errors` dikosongkan (`{}`).
- Stack trace, nama file server, nomor baris, query SQL, dan kredensial basis data **DILINDUNGI PENUH** dan tidak pernah diekspos ke respon JSON.
- Ketika `APP_DEBUG=true` di lingkungan pengujian lokal, rincian file, baris, dan nama kelas eksepsi ditampilkan di `errors` untuk mempermudah debugging.

---

## 5. Mekanisme Logging

- Seluruh error 500 yang tidak tertangani otomatis dicatat ke storage log Laravel (`storage/logs/laravel.log`) lengkap dengan stack trace untuk kebutuhan audit developer.
- Logging berjalan di belakang layar tanpa memengaruhi respon balik ke pengguna.
