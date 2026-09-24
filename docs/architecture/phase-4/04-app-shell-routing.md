# Application Shell & Routing - RAFA Rental System

Dokumentasi rancangan fondasi navigasi, routing bersarang (*nested routing*), tata letak (*layouting*), dan manajemen state otentikasi awal pada RAFA Rental System (Phase 4D).

---

## 1. Peta Rute Aplikasi (Route Map)

Sistem menggunakan `react-router-dom` dengan struktur rute yang mendukung pembagian portal.

| Path | Layout | Peran Target | Deskripsi |
|---|---|---|---|
| `/` | `PublicLayout` | Semua (Guest) | Landing page publik |
| `/login`, `/register`, `/forgot-password` | `AuthLayout` | Guest | Halaman otentikasi dengan form card terpusat |
| `/app/*` | `UserLayout` | `USER` | Portal pelanggan (katalog, booking saya, invoice) |
| `/admin/*` | `AdminLayout` | `ADMIN`, `OWNER` | Portal operasional (dispatch, verifikasi timesheet, payment) |
| `/owner/*` | `OwnerLayout` | `OWNER` | Dashboard eksekutif (laporan finansial, master harga) |

---

## 2. Struktur Layout (App Shells)

Keempat layout di atas mengusung konsep **Application Shell Architecture**, di mana cangkang navigasi statis langsung dirender sedangkan konten utama disuntikkan secara asinkron (*lazy-loaded*).

### 2.1 Komponen Cangkang:
- **`Navbar`**: Menampilkan logo, nama pengguna yang login, dan *badge role* dinamis. Memiliki responsivitas flexbox penuh.
- **`Sidebar`**: Menu navigasi samping khusus untuk portal internal (`UserLayout`, `AdminLayout`, `OwnerLayout`). Menggunakan `useLocation` untuk menyorot menu yang sedang aktif secara otomatis.
- **`AuthContext` & `useAuth`**: Penyedia state otentikasi *in-memory* yang disinkronisasi ke `localStorage` untuk keperluan persistensi token dan peran (role) pengguna.

---

## 3. Route Protection Foundation (Keamanan Rute)

Otorisasi halaman di-handle melalui dua lapis proteksi:

1. **`ProtectedRoute`**: 
   - Memastikan hanya pengguna yang telah memiliki token (telah login) yang dapat melewati rute ini.
   - Jika belum login, pengunjung langsung dilempar ke `/login` menggunakan `Navigate`.
2. **`RoleRoute`**: 
   - Komponen turunan `ProtectedRoute` yang memvalidasi array `allowedRoles`.
   - Contoh: Rute `/owner` mem-passing prop `allowedRoles={['OWNER']}`. Jika seorang Admin mencoba masuk, rute akan melempar pengguna ke `/forbidden`.

---

## 4. Mekanisme Penanganan Error Rute (Fallback Pages)

Tiga halaman *error fallback* telah diimplementasikan dalam struktur aplikasi:
1. **404 - NotFoundPage:** Untuk rute atau URL acak yang tidak terdaftar (`path="*"`).
2. **401 - UnauthorizedPage:** Dimunculkan ketika token kedaluwarsa atau hilang *(Akan diintegrasikan penuh dengan interceptor axios di Phase 5)*.
3. **403 - ForbiddenPage:** Dimunculkan ketika akses ditolak akibat limitasi *Role-Based Access Control* (RBAC).

---

## 5. Optimalisasi Performa (Lazy Loading)

Semua halaman modul (Pages) di-*import* menggunakan teknik *code-splitting* (`React.lazy()`) alih-alih import statis biasa.
Setiap halaman dikompilasi menjadi *chunk JS* yang terpisah oleh Vite, mengurangi waktu muat awal *(initial load time)* aplikasi secara signifikan.
Transisi pemuatan antar-chunk dibungkus dengan `<Suspense fallback={<LoadingState />}>` untuk menjamin tidak terjadinya layar putih (*blank screen*) berkedip selama transisi jaringan.
