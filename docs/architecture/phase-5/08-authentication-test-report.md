# Laporan Pengujian Autentikasi & Otorisasi - RAFA Rental System

Dokumentasi hasil komprehensif pengujian otomatis (*automated testing*), verifikasi integritas otentikasi & otorisasi, penanganan galat keamanan (*negative security tests*), dan quality gate pada RAFA Rental System (Phase 5H).

---

## 1. Rangkuman Eksekusi Pengujian

| Lapisan | Alat Pengujian | Jumlah Berkas Test | Total Kasus Uji | Status |
|---|---|---|---|---|
| **Backend API (Laravel)** | PHPUnit / Pest CLI | 10 Test Suites | **120 Tests (375 Assertions)** | **100% PASSED** |
| **Frontend UI (React)** | Vitest + React Testing Library | 7 Test Suites | **29 Tests** | **100% PASSED** |
| **Code Style Backend** | Laravel Pint | Seluruh direktori `app/`, `tests/` | Format Standar PSR-12 / Laravel | **100% CLEAN** |
| **Code Style Frontend** | Oxlint + TypeScript `tsc -b` | Seluruh direktori `src/` | Strict Type checking & Linting | **100% CLEAN** |

---

## 2. Rincian Cakupan Test Suite Backend (`tests/Feature/Auth/`)

### 2.1 `AuthenticationTest.php` (12 Tests)
- Pendaftaran akun baru dengan peran default `USER`.
- Penolakan registrasi dengan format input tidak valid (HTTP 422).
- Penolakan duplikasi email dan nomor telepon unik.
- Proteksi injeksi privilege: User publik tidak dapat memanipulasi field `role` menjadi `ADMIN` atau `OWNER`.
- Login dengan kredensial valid dan penerbitan token Sanctum.
- Penolakan login dengan password keliru.
- Penolakan login untuk akun yang dinonaktifkan (`is_active = false`, HTTP 403 `FORBIDDEN_ACTION`).
- Pengambilan profil user aktif via `GET /api/v1/auth/me`.
- Penolakan akses unauthenticated (HTTP 401 `UNAUTHENTICATED`).
- Pencabutan token saat logout (`POST /api/v1/auth/logout`).
- Verifikasi keamanan: field `password` dan `remember_token` tidak pernah bocor pada respons JSON.

### 2.2 `PasswordResetTest.php` (7 Tests)
- Permintaan tautan reset kata sandi (`POST /api/v1/auth/forgot-password`).
- Perlindungan email enumeration: Mengembalikan respons sukses generik untuk email yang tidak terdaftar.
- Pembaruan kata sandi menggunakan token valid (`POST /api/v1/auth/reset-password`).
- Penolakan reset kata sandi dengan token palsu atau kedaluwarsa (HTTP 400).
- Login menggunakan kata sandi baru pasca reset dan kegagalan login dengan kata sandi lama.
- **Pencabutan Token Otomatis (Security Revocation):** Seluruh sesi dan token Sanctum aktif sebelumnya dicabut seketika saat kata sandi direset.
- Perlindungan Rate Limiting: Memblokir request reset ke-6 dalam interval 1 menit (HTTP 429 `RATE_LIMIT_EXCEEDED`).

### 2.3 `PermissionMatrixTest.php` (3 Tests)
- Matriks Hak Akses `USER`: Boleh kelola data pribadi; dilarang melihat daftar seluruh user, mengakses data user lain, memverifikasi KYC, atau menonaktifkan akun.
- Matriks Hak Akses `ADMIN`: Boleh melihat seluruh user dan memverifikasi KYC; dilarang menonaktifkan akun user lain.
- Matriks Hak Akses `OWNER`: Boleh melihat seluruh user, memverifikasi KYC, dan menonaktifkan akun user lain; dilarang menonaktifkan akunnya sendiri.

### 2.4 `AuthWorkflowIntegrationTest.php` (3 Tests)
- **User Complete Journey:** Register -> Ambil Profil -> Update Profil Kontak -> Percobaan Akses Terlarang -> Logout -> Token Lama Gagal (401).
- **Administrative Lifecycle:** Login Admin -> Verifikasi KYC Pelanggan -> Percobaan Akses Owner (403) -> Login Owner -> Nonaktifkan Pelanggan (200) -> Larangan Nonaktifkan Diri Sendiri (400).
- **Negative Attack Vectors:** Manipulasi role via form update profil, inspeksi IDOR profil user lain, pemalsuan request KYC verification, dan penggunaan token korup/palsu.

---

## 3. Rincian Cakupan Test Suite Frontend (`frontend/src/`)

### 3.1 `AuthService.test.ts` (4 Tests)
- Pemanggilan endpoint `/auth/login`, `/auth/register`, `/auth/logout`, `/auth/me` via centralized API client.
- Pemanggilan endpoint `/auth/forgot-password` dan `/auth/reset-password`.

### 3.2 `AuthPages.test.tsx` (4 Tests)
- Render halaman Login dengan input email dan password.
- Validasi ketidakcocokan konfirmasi kata sandi pada halaman Registrasi.
- Pengiriman permohonan reset password pada halaman Lupa Password.
- Validasi batas minimal 8 karakter pada halaman Reset Password.

### 3.3 `RouteGuard.test.tsx` (8 Tests)
- Pengalihan pengunjung belum login ke `/login`.
- Penahanan tampilan dan pencegahan kebocoran konten saat status `isChecking` aktif.
- Akses portal pelanggan `/app` bagi peran `USER`.
- Penolakan akses peran `USER` ke `/admin` dan pengalihan ke `/forbidden`.
- Akses meja operasional `/admin` bagi peran `ADMIN`.
- Penolakan akses peran `ADMIN` ke `/owner` dan pengalihan ke `/forbidden`.
- Akses executive governance `/owner` bagi peran `OWNER`.
- Pengalihan pengguna yang sudah login menjauhi halaman `/login` ke portal masing-masing via `GuestRoute`.

### 3.4 `AuthFlowIntegration.test.tsx` (3 Tests)
- Simulasi alur penuh pengguna dari pengisian form login -> mendarat di portal pelanggan -> tampilan identitas navbar -> eksekusi logout -> pembersihan session storage.
- Simulasi login Admin langsung mendarat di `/admin`.
- Simulasi login Owner langsung mendarat di `/owner`.

---

## 4. Masalah yang Ditemukan dan Terselesaikan (Resolved Issues)

1. **Test Runner Auth Guard Cache:**
   - *Masalah:* Dalam satu method test integrasi berurutan, token yang dihapus saat logout masih mengembalikan user lama karena cache instance request guard Laravel di memori proses test runner.
   - *Solusi:* Ditambahkan pemanggilan `$this->app['auth']->forgetGuards()` antar request dalam skenario multi-user di dalam `AuthWorkflowIntegrationTest`.
2. **Asinkronitas Suspense pada Uji Komponen UI:**
   - *Masalah:* Uji integrasi frontend gagal menemukan elemen input karena halaman login di-load secara asinkron (*React.lazy*) dan berada di dalam state Suspense fallback pada tick awal.
   - *Solusi:* Menggunakan query asinkron `await screen.findByLabelText(...)` yang menunggu transisi pemuatan chunk komponen selesai sempurna.

---

## 5. Kesimpulan Kesiapan Kualitas (Quality Verdict)

Seluruh komponen autentikasi, manajemen sesi token Sanctum, proteksi rute RBAC (User, Admin, Owner), alur pemulihan kata sandi, dan perlindungan keamanan negatif telah teruji secara menyeluruh tanpa kegagalan.
