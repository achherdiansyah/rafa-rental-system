# Backend Authentication (Sanctum) Specification - RAFA Rental System

Dokumentasi spesifikasi implementasi autentikasi API berbasis token Laravel Sanctum pada RAFA Rental System (Phase 5A).

---

## 1. Arsitektur Autentikasi

- **Mekanisme:** *Personal Access Token* (Laravel Sanctum).
- **Format Header:** `Authorization: Bearer <token>`
- **Prinsip Keamanan:**
  - Password di-hash menggunakan algoritma Bcrypt (`rounds=12`).
  - Respons API **DILARANG** mengembalikan field sensitif (`password`, `remember_token`).
  - Serialisasi data user diformat terpusat melalui `UserResource`.
  - Endpoint publik (`/register`, `/login`) dilindungi rate limiter `throttle:auth` (maksimal 5 request per menit per IP).

---

## 2. Endpoint Autentikasi (`/api/v1/auth/*`)

### 2.1 Pendaftaran Akun (`POST /api/v1/auth/register`)
- **Akses:** Publik (Guest)
- **Request Body:**
  ```json
  {
    "name": "Budi Santoso",
    "email": "budi@perusahaan.com",
    "password": "password123",
    "password_confirmation": "password123",
    "phone_number": "081234567890",
    "company_name": "PT Maju Konstruksi Jaya",
    "identity_type": "KTP",
    "identity_number": "3201012345670001",
    "address": "Jl. Sudirman No. 45, Bandung"
  }
  ```
- **Aturan Bisnis & Keamanan:**
  - Role dipaksa (*hardcoded*) ke `USER`. Payload `role: ADMIN` atau `role: OWNER` dari request publik diabaikan.
  - Otomatis membuat profil pelanggan di tabel `customer_profiles` berstatus `UNVERIFIED`.
- **Response Success (`201 Created`):**
  ```json
  {
    "success": true,
    "message": "Pendaftaran akun berhasil.",
    "data": {
      "user": {
        "id": 1,
        "name": "Budi Santoso",
        "email": "budi@perusahaan.com",
        "role": "USER",
        "phone_number": "081234567890",
        "is_active": true,
        "customer_profile": {
          "company_name": "PT Maju Konstruksi Jaya",
          "identity_type": "KTP",
          "identity_number": "3201012345670001",
          "address": "Jl. Sudirman No. 45, Bandung",
          "verification_status": "UNVERIFIED"
        },
        "created_at": "2026-09-24T08:00:00Z"
      },
      "token": "1|abcdef123456..."
    }
  }
  ```

---

### 2.2 Masuk Akun (`POST /api/v1/auth/login`)
- **Akses:** Publik (Guest)
- **Request Body:**
  ```json
  {
    "email": "budi@perusahaan.com",
    "password": "password123"
  }
  ```
- **Aturan Bisnis & Keamanan:**
  - Verifikasi email dan password via `Hash::check`.
  - Jika user nonaktif (`is_active = false`), sistem menolak login dengan HTTP 403 `FORBIDDEN_ACTION`.
  - Jika kredensial salah, sistem mengembalikan HTTP 422 `VALIDATION_FAILED` tanpa membocorkan apakah email terdaftar atau tidak.
- **Response Success (`200 OK`):**
  ```json
  {
    "success": true,
    "message": "Login berhasil.",
    "data": {
      "user": { ... },
      "token": "2|fedcba654321..."
    }
  }
  ```

---

### 2.3 Keluar Akun (`POST /api/v1/auth/logout`)
- **Akses:** Terautentikasi (`auth:sanctum`)
- **Perilaku:** Menghapus token aktif yang digunakan saat request (`$user->currentAccessToken()->delete()`).
- **Response Success (`200 OK`):**
  ```json
  {
    "success": true,
    "message": "Logout berhasil.",
    "data": null
  }
  ```

---

### 2.4 Info Profil Aktif (`GET /api/v1/auth/me`)
- **Akses:** Terautentikasi (`auth:sanctum`)
- **Response Success (`200 OK`):** Mengembalikan data user aktif beserta `customer_profile`.
- **Response Unauthenticated (`401 Unauthorized`):**
  ```json
  {
    "success": false,
    "message": "Unauthenticated.",
    "errors": {},
    "code": "UNAUTHENTICATED"
  }
  ```

---

## 3. Struktur Kode Terorganisir
- **Form Requests:** `app/Http/Requests/Auth/RegisterRequest.php`, `app/Http/Requests/Auth/LoginRequest.php`
- **Actions:** `app/Actions/Auth/RegisterUserAction.php`, `app/Actions/Auth/LoginUserAction.php`, `app/Actions/Auth/LogoutUserAction.php`
- **Resource:** `app/Http/Resources/UserResource.php`
- **Controller:** `app/Http/Controllers/Api/V1/AuthController.php` (Thin controller)
- **Tests:** `tests/Feature/Auth/AuthenticationTest.php` (12 automated test cases)
