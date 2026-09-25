# Manajemen Unit Fisik & Lifecycle - RAFA Rental System

Dokumentasi spesifikasi implementasi backend API dan antarmuka manajemen Admin untuk inventaris `equipment_units` pada RAFA Rental System (Phase 6B).

---

## 1. Lingkup Modul Inventaris Unit Fisik

Modul ini mengelola aset fisik/mesin berat yang diidentifikasi secara unik berdasarkan nomor seri pabrikan (*serial number*) dan plat nomor operasional (No. Lambung / Polisi).

- Setiap unit fisik harus merujuk pada `equipment_model` tertentu (contoh: Unit KM-PC200-001 dari model Komatsu PC200-8).
- Siklus hidup (*lifecycle*) unit ini mendikte apakah ia boleh disewakan (AVAILABLE), sedang dialokasikan (ASSIGNED), dikirim ke proyek (MOBILIZING), hingga masuk perbaikan (MAINTENANCE).

---

## 2. Kontrak Endpoint REST API (`/api/v1/equipment/units`)

| Method | Endpoint | Akses / Role | Deskripsi |
|---|---|---|---|
| `GET` | `/api/v1/equipment/units` | `ADMIN`, `OWNER` | Daftar unit dengan filter `?equipment_model_id=`, `?status=`, `?search=` |
| `GET` | `/api/v1/equipment/units/{id}` | `ADMIN`, `OWNER` | Detail lengkap satu unit fisik beserta model dan Hour Meter |
| `POST` | `/api/v1/equipment/units` | `ADMIN`, `OWNER` | Pendaftaran unit fisik baru (default status `AVAILABLE`) |
| `PUT` | `/api/v1/equipment/units/{id}` | `ADMIN`, `OWNER` | Perbarui data unit (Hour Meter, Serial Number, Plate) |
| `POST` | `/api/v1/equipment/units/{id}/status` | `ADMIN`, `OWNER` | Ubah status manual (mis. ke `MAINTENANCE` atau `DECOMMISSIONED`) |
| `DELETE` | `/api/v1/equipment/units/{id}` | `ADMIN`, `OWNER` | Soft delete unit (hanya diizinkan jika `AVAILABLE` atau `DECOMMISSIONED`) |

---

## 3. Aturan Validasi & Integritas Bisnis

1. **Keunikan Identifikasi:**
   - `serial_number` wajib unik di seluruh inventaris.
   - `plate_number` bersifat opsional (nullable), namun jika diisi harus unik.
2. **Proteksi Akses (Security Guard):**
   - Modul ini ditutup total untuk pengguna publik atau `USER`.
   - Hanya peran yang memiliki kapabilitas Gate `manage-equipment` (`ADMIN`, `OWNER`) yang diizinkan mengakses CRUD inventaris ini.
3. **Pola Transisi Status Keselamatan (Status Safety Guard):**
   - Transisi manual melalui endpoint `/status` dilarang keras saat unit sedang berada dalam siklus operasional lapangan (`ASSIGNED`, `MOBILIZING`, `ON_SITE`, `DEMOBILIZING`). Status tersebut hanya bisa berubah via modul Rental Handover.
   - Perubahan manual hanya diizinkan untuk isolasi perbaikan mekanik (ke `MAINTENANCE` / `DECOMMISSIONED`) atau pengembalian layak pakai ke `AVAILABLE`.
4. **Proteksi Penghapusan (Delete Behavior):**
   - Unit fisik tidak dapat di-soft-delete jika sedang dalam kontrak sewa atau inspeksi (Status harus `AVAILABLE` atau `DECOMMISSIONED`).

---

## 4. Antarmuka Manajemen Inventaris Admin (`AdminEquipmentUnitsPage.tsx`)

Antarmuka terintegrasi pada rute `/admin/units` menyediakan fitur:
- **Filter Cerdas:** Kotak pencarian (search) debounce 300ms untuk serial/plat nomor, dikombinasikan dengan Dropdown Status Enum dan Dropdown Filter Model.
- **Formulir Pendaftaran:** Menangkap Hour Meter (HM) angka awal (*baseline HM*) dan tahun rakit.
- **Tabel Responsif:** Membedakan indikator status secara visual menggunakan komponen `Badge` (Hijau untuk `AVAILABLE`, Merah untuk `MAINTENANCE`, Abu untuk `ON_SITE`).
- **Modal Perubahan Status:** Antarmuka ringkas khusus untuk mencatat log pemeliharaan bengkel saat Admin mengubah status ke `MAINTENANCE`.
- **Integrasi Paginasi:** Menghindari lonjakan data berlebihan (default 10 unit per halaman) dari Backend.
