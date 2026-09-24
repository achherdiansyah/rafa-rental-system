# Authentication & Authorization Foundation - RAFA Rental System

Dokumentasi arsitektur keamanan akses, autentikasi, dan otorisasi (RBAC) pada RAFA Rental System (Phase 2F).

---

## 1. Authentication (Autentikasi)

Sistem menggunakan **Laravel Sanctum** untuk menangani autentikasi API (`Token-Based Authentication`).

### 1.1 Model Pengguna (`App\Models\User`)
Model utama `User` di-extend dengan trait `Laravel\Sanctum\HasApiTokens`. Ini memungkinkan model untuk membuat dan mencabut akses *Personal Access Tokens*.
Field otentikasi:
- `email` (Unique identifier)
- `password` (Hashed payload)
- `is_active` (Flag penentu user boleh login/akses API)

### 1.2 Middleware Autentikasi
Seluruh endpoint privat dilindungi oleh middleware bawaan `auth:sanctum`.
- Jika request datang tanpa header `Authorization: Bearer <token>` atau token sudah invalid, sistem melempar `AuthenticationException`.
- Exception Handler (di `bootstrap/app.php`) akan menelan exception ini dan mengonversinya menjadi respons error JSON standar dengan HTTP `401 Unauthorized` dan code `UNAUTHENTICATED`.

---

## 2. Role Based Access Control (Roles)

Per sistem, **HANYA ADA 3 (TIGA) ROLES** mutlak tanpa penambahan tiering lainnya. Role direpresentasikan menggunakan Backed Enum PHP (`App\Enums\UserRole`) yang di-*cast* otomatis pada model `User`.

1. **`USER`:** Pelanggan/penyewa (katalog, sewa, bayar).
2. **`ADMIN`:** Operator internal perusahaan (assign unit, cek fisik lapangan, verifikasi timesheet, approval reservasi).
3. **`OWNER`:** Pemilik perusahaan (dashboard finansial, audit, master harga).

### 2.1 Role Middleware
Dibuat middleware baru `App\Http\Middleware\RequireRole` (alias: `role`). Middleware ini memverifikasi array role yang diizinkan untuk rute spesifik.
Jika role user tidak memenuhi syarat, middleware mengembalikan respons JSON `403 Forbidden` dengan code `FORBIDDEN_ACTION`.

Contoh implementasi route:
```php
Route::middleware(['auth:sanctum', 'role:ADMIN,OWNER'])->group(function () {
    // Hanya bisa diakses oleh Admin atau Owner
});
```

---

## 3. Authorization (Otorisasi & Kebijakan)

Sistem memisahkan tanggung jawab antara *Autentikasi* (siapa Anda) dan *Otorisasi* (apakah Anda berhak melakukan aksi ini).

### 3.1 Base Policy (`App\Policies\BasePolicy`)
Fondasi awal (Base class) yang mengekstrak logika deteksi role untuk mengurangi pengulangan pada policy child (misal `BookingPolicy`). Menyediakan fungsi helper:
- `isAdmin(User $user)`
- `isOwner(User $user)`
- `isUser(User $user)`

### 3.2 Global Gates (Permission Matrix Foundation)
Sesuai dengan `07-permission-matrix-v3.md` di Phase 1, `AppServiceProvider` mendaftarkan Gate global untuk *capabilities* (izin) yang bersifat lintas-resource atau sistemik (tidak spesifik terikat Model ID).

Contoh Gate dan otorisasi perannya:
- `manage-equipment` -> ADMIN, OWNER
- `assign-units` -> ADMIN
- `validate-timesheet` -> ADMIN
- `view-revenue-reports` -> OWNER
- `approve-refund` -> OWNER

Pendekatan ini menjamin konsistensi 100% terhadap matriks Phase 1 dan menyediakan fungsi deklaratif `$user->can('assign-units')` untuk melindungi backend actions.

---

## 4. Keamanan Infrastruktur Lapis Luar

- **401 Unauthenticated:** Dikeluarkan seragam oleh `ApiResponse::unauthenticated()` jika token gagal di-resolve.
- **403 Forbidden:** Dikeluarkan seragam oleh `ApiResponse::forbidden()` jika evaluasi Role Middleware, Policy, atau Gate mengembalikan `false`.

Struktur error konsisten ini dapat dengan mudah di-*catch* oleh client-side axios interceptor pada SPA React untuk memaksa pengguna melakukan re-login atau menampilkan notifikasi penolakan akses yang ramah pengguna.
