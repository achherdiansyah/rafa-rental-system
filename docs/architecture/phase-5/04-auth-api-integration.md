# Integrasi API Autentikasi Frontend (Sanctum) - RAFA Rental System

Dokumentasi spesifikasi integrasi antarmuka React dengan backend Laravel Sanctum API (`/api/v1/auth/*`) pada RAFA Rental System (Phase 5D).

---

## 1. Alur Komunikasi Autentikasi

```
[ Form React (LoginPage / RegisterPage) ]
       │
       ▼
[ useAuth Hook / AuthContext ]
       │
       ▼
[ authService (src/features/auth/services/authService.ts) ]
       │
       ▼
[ Centralized API Client (src/lib/api.ts) ] ── (Attach Header Bearer Token)
       │
       ▼
[ Laravel Backend (/api/v1/auth/*) ]
```

---

## 2. Layanan Autentikasi (`authService`)

Terletak pada `frontend/src/features/auth/services/authService.ts`:
- **`login(credentials: LoginCredentials)`**: Mengirim `email` & `password`, menerima payload `token` dan `user`, menyimpan token di `localStorage`.
- **`register(data: RegisterData)`**: Mengirim form pendaftaran lengkap (nama, email, password, nomor telepon, identitas KTP/perusahaan), menerima session token.
- **`logout()`**: Memanggil `POST /api/v1/auth/logout` untuk mencabut token dari database Sanctum dan membersihkan `localStorage`.
- **`getMe()`**: Mengambil data profil aktif dari `GET /api/v1/auth/me`.

---

## 3. Siklus Hidup Status Autentikasi (`AuthStatus`)

Sistem mengelola 3 status otentikasi:
1. **`checking`**: Kondisi awal saat aplikasi pertama kali dimuat (*app boot*). `AuthContext` memanggil `getMe()` di latar belakang untuk memvalidasi token tersimpan. Rute yang diproteksi menampilkan spinner ramah tanpa melempar user secara prematur.
2. **`authenticated`**: Pengguna terbukti valid dan token aktif.
3. **`unauthenticated`**: Pengguna belum login atau token telah dihapus/kedaluwarsa.

---

## 4. Penanganan Galat & Interceptor HTTP

- **HTTP 401 Unauthorized:** Interceptor Axios mendeteksi sesi habis, membersihkan token lokal, dan mengalihkan halaman ke `/login?expired=1`.
- **HTTP 422 Unprocessable Entity:** Respons pesan kesalahan per field input dipetakan langsung ke komponen form (`Input.error`) sehingga pengguna mengetahui field mana yang bermasalah (misal: password tidak cocok, nomor telepon duplikat).
- **HTTP 403 Forbidden:** Ditampilkan sebagai banner peringatan jika akun berstatus nonaktif (`is_active = false`).
- **Network Failure:** Ditransformasikan menjadi `isNetworkError: true` dengan pesan ramah instruksi memeriksa koneksi.

---

## 5. Keamanan Kredensial di Sisi Klien

- Password tidak pernah disimpan di `localStorage` atau global state.
- Hanya `Personal Access Token` bertipe string yang disimpan pada `localStorage` (`rafa_token`).
- Token dihapus secara instan saat tombol Logout ditekan.
