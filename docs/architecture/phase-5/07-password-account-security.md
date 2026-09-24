# Reset Kata Sandi & Keamanan Akun (Password Reset & Account Security) - RAFA Rental System

Dokumentasi spesifikasi alur pemulihan kata sandi (*Password Reset Flow*), kebijakan keamanan akun, perlindungan laju request (*Rate Limiting*), dan pencegahan kebocoran kredensial pada RAFA Rental System (Phase 5G).

---

## 1. Alur Pemulihan Kata Sandi (Password Reset Flow)

Sistem menggunakan mekanisme bawaan Laravel Password Broker yang terhubung dengan tabel `password_reset_tokens`:

```
1. [User / Klien] POST /api/v1/auth/forgot-password { email }
      │
      ▼
2. [Backend] Buat token reset aman (SHA-256 hashed di DB)
      │
      ▼
3. [Email Service] Kirim tautan ke User:
   ${FRONTEND_URL}/reset-password?token=HASH&email=user@domain.com
      │
      ▼
4. [User / Klien] POST /api/v1/auth/reset-password { token, email, password, password_confirmation }
      │
      ├── Validasi token & masa kedaluwarsa (60 menit)
      ├── Hash kata sandi baru (Bcrypt)
      ├── Revoke seluruh token Sanctum aktif (Security Invalidation)
      └── Emit PasswordReset Event
```

---

## 2. Kontrak Endpoint API

### 2.1 Permintaan Tautan Reset (`POST /api/v1/auth/forgot-password`)
- **Akses:** Publik (Dilindungi Rate Limiter `throttle:auth`)
- **Request Body:**
  ```json
  {
    "email": "budi@perusahaan.com"
  }
  ```
- **Pencegahan Email Enumeration:** 
  Sistem selalu mengembalikan pesan sukses generik: *"Tautan reset kata sandi telah dikirimkan ke email Anda jika terdaftar di sistem."* sekalipun email tersebut tidak ditemukan di basis data. Hal ini mencegah peretas memetakan keberadaan akun aktif.

### 2.2 Eksekusi Reset Kata Sandi (`POST /api/v1/auth/reset-password`)
- **Akses:** Publik (Dilindungi Rate Limiter `throttle:auth`)
- **Request Body:**
  ```json
  {
    "token": "token-string-dari-url",
    "email": "budi@perusahaan.com",
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
  }
  ```
- **Respons Berhasil (200 OK):**
  ```json
  {
    "success": true,
    "message": "Kata sandi berhasil diperbarui. Silakan masuk menggunakan kata sandi baru Anda.",
    "data": null
  }
  ```
- **Respons Token Tidak Valid / Kedaluwarsa (400 Bad Request):**
  ```json
  {
    "success": false,
    "message": "Token reset kata sandi tidak valid atau telah kedaluwarsa.",
    "errors": {
      "token": ["Token tidak valid atau kedaluwarsa."]
    },
    "code": "BUSINESS_RULE_VIOLATION"
  }
  ```

---

## 3. Kebijakan Keamanan Akun (Account Security Standards)

1. **Pencabutan Sesi Seketika (Token Invalidation on Reset):**
   Saat kata sandi berhasil direset, baris `$user->tokens()->delete()` dieksekusi untuk langsung memutuskan seluruh sesi Sanctum yang sedang aktif di perangkat lain. Pengguna wajib login ulang dengan kata sandi baru.
2. **Kebijakan Kata Sandi Standar:**
   Panjang minimal kata sandi adalah **8 karakter** dengan konfirmasi wajib (`confirmed`). Tidak diterapkan aturan karakter aneh/ekstrem yang menghambat operasional lapangan.
3. **Penyembunyian Field Sensitif:**
   Data kata sandi (`password`), remember token, atau hash token reset tidak pernah diekspos di log aplikasi maupun respons API JSON.

---

## 4. Perlindungan Laju Request (Rate Limiting Protection)

Endpoint autentikasi publik (`/register`, `/login`, `/forgot-password`, `/reset-password`) diikat ke rate limiter:
- **Batas Maksimal:** **5 request per menit per IP**.
- Melewati batas ini menghasilkan HTTP status `429 Too Many Requests` dengan error code `RATE_LIMIT_EXCEEDED` guna memblokir serangan *brute-force* atau *email spamming*.

---

## 5. Antarmuka Klien (Frontend UX)

- **`ForgotPasswordPage.tsx`:** Mengirim email via `authService.forgotPassword()` dan menampilkan notifikasi instruksi berhasil.
- **`ResetPasswordPage.tsx`:** Mengambil parameter `token` dan `email` langsung dari query URL (`location.search`), memvalidasi panjang password di klien, dan memanggil `authService.resetPassword()`.
