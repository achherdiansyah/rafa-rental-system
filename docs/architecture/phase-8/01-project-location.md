# Spesifikasi Manajemen Lokasi Proyek (Phase 8A) - RAFA Rental System

Dokumentasi rancangan basis data, aturan bisnis relasi lokasi, otorisasi RBAC, REST API, dan antarmuka pengelolaan lokasi proyek pelanggan (`/app/locations`).

---

## 1. Aturan Bisnis Lokasi Proyek (Business Rules)

1. **Relasi Pelanggan (1 : N):** Satu pelanggan (`USER`) dapat mendaftarkan banyak lokasi proyek untuk berbagai proyek konstruksi yang sedang ditangani.
2. **Relasi Booking (1 : 1):** Setiap satu pesanan sewa (`Booking`) hanya terhubung ke **satu lokasi proyek tunggal** sebagai titik tujuan pengiriman armada dan kalkulasi mobilisasi/demobilisasi.
3. **Isolasi Kepemilikan (Strict Ownership):**
   - Pelanggan hanya dapat melihat, menambah, mengubah, dan menghapus lokasi proyek miliknya sendiri.
   - Upaya akses terhadap lokasi milik pelanggan lain langsung ditolak dengan `403 Forbidden`.
4. **Proteksi Penghapusan (Integrity Guard):**
   - Lokasi proyek yang sedang digunakan pada pesanan sewa aktif (`CONFIRMED`, `DISPATCHED`, `ONGOING`) tidak dapat dihapus (`409 Conflict`).
   - Penghapusan lokasi yang tidak memiliki sewa aktif menerapkan *soft delete* (`deleted_at`).

---

## 2. Struktur Basis Data (`project_locations`)

| Kolom | Tipe Data | Nullable | Deskripsi |
|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | Primary Key auto-increment. |
| `user_id` | `BIGINT UNSIGNED` | No | Foreign key ke `users(id)`, restrict on delete. |
| `project_name` | `VARCHAR(255)` | No | Nama proyek atau area kerja lapangan (e.g. "Tol Cisauk Raya"). |
| `address` | `TEXT` | No | Alamat lengkap pengiriman unit alat berat. |
| `city` | `VARCHAR(100)` | No | Kota / Kabupaten lokasi proyek. |
| `pic_name` | `VARCHAR(255)` | No | Nama Person in Charge (PIC) di lokasi proyek. |
| `pic_phone` | `VARCHAR(30)` | No | Nomor telepon/WhatsApp aktif PIC lapangan. |
| `latitude` | `DECIMAL(10,8)` | Yes | Koordinat lintang lokasi (antara -90 s/d 90). |
| `longitude` | `DECIMAL(11,8)` | Yes | Koordinat bujur lokasi (antara -180 s/d 180). |
| `is_active` | `BOOLEAN` | No | Status keaktifan lokasi (default `true`). |
| `created_at`, `updated_at` | `TIMESTAMP` | No | Waktu pencatatan. |
| `deleted_at` | `TIMESTAMP` | Yes | Waktu soft-deletion. |

---

## 3. Matriks REST API Endpoints

| Method | Endpoint | Actor | Deskripsi |
|---|---|---|---|
| `GET` | `/api/v1/project-locations` | `USER`, `ADMIN`, `OWNER` | List lokasi proyek (User hanya melihat miliknya, Admin/Owner dapat memfilter berdasarkan `user_id`). Mendukung pencarian teks & paginasi. |
| `POST` | `/api/v1/project-locations` | `USER`, `ADMIN`, `OWNER` | Daftarkan lokasi proyek baru. |
| `GET` | `/api/v1/project-locations/{id}` | `USER` (Owner), `ADMIN`, `OWNER` | Ambil detail informasi lokasi proyek. |
| `PUT/PATCH` | `/api/v1/project-locations/{id}` | `USER` (Owner), `ADMIN`, `OWNER` | Perbarui data nama proyek, PIC, kota, atau alamat. |
| `DELETE` | `/api/v1/project-locations/{id}` | `USER` (Owner), `ADMIN`, `OWNER` | Hapus lokasi proyek (dicegah bila terhubung ke booking aktif). |

---

## 4. Antarmuka Pengguna Frontend (`/app/locations`)

- **Komponen:** `UserProjectLocationsPage.tsx`.
- **Fitur Utama:**
  - Grid kartu lokasi proyek dengan informasi PIC lapangan dan nomor telepon.
  - Modal tambah & edit lokasi proyek menggunakan validasi form client-side.
  - Dialog konfirmasi sebelum melakukan penghapusan data lokasi.
  - Paginasi sisi server dan filter pencarian ter-debounce (300ms).
  - Integrasi navigasi menu "Lokasi Proyek" pada sidebar Customer Portal.

---

## 5. Hasil Pengujian Otomatis

- **Backend Feature Tests (`ProjectLocationApiTest`):** **11 Tests Passed** (CRUD, otorisasi kepemilikan, proteksi booking aktif 409, koordinat validator).
- **Frontend UI Tests (`ProjectLocations.test.tsx`):** **4 Tests Passed** (Render list, validasi form, submit modal, empty state).
- **Backend Total Suite:** **225 Passed (829 assertions)**.
- **Frontend Total Suite:** **61 Passed (16 test files)**.
- **Linter & Build:** Laravel Pint 100% Clean, Vite build clean.
