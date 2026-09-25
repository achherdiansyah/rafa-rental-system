# Master Data Armada: Tipe & Model Alat Berat - RAFA Rental System

Dokumentasi spesifikasi implementasi backend API dan antarmuka manajemen Admin untuk master data `equipment_types` dan `equipment_models` pada RAFA Rental System (Phase 6A).

---

## 1. Lingkup Modul Master Data Armada

Modul ini menyediakan dasar katalog armada dan penentuan harga sewa dengan struktur berjenjang:

$$\text{EquipmentType (Kategori)} \longrightarrow \text{EquipmentModel (Seri Model)} \longrightarrow \text{EquipmentUnit (Unit Fisik)}$$

- **`equipment_types`:** Mengelompokkan jenis fungsional alat berat (contoh: Excavator, Bulldozer, Wheel Loader, Vibro Compactor, Motor Grader).
- **`equipment_models`:** Seri spesifik pabrikan alat berat yang membawa atribut kapasitas teknis (contoh: Komatsu PC200-8 20.0 Ton, Caterpillar CAT 320D 20.0 Ton).

---

## 2. Kontrak Endpoint REST API (`/api/v1/equipment/*`)

| Method | Endpoint | Akses / Role | Deskripsi |
|---|---|---|---|
| `GET` | `/api/v1/equipment/types` | Publik / Guest | Daftar kategori alat berat dengan dukungan `?search=` dan paginasi |
| `GET` | `/api/v1/equipment/types/{id}` | Publik / Guest | Detail informasi kategori tipe alat |
| `POST` | `/api/v1/equipment/types` | `ADMIN`, `OWNER` | Tambah master kategori baru |
| `PUT` | `/api/v1/equipment/types/{id}` | `ADMIN`, `OWNER` | Perbarui data kategori tipe alat |
| `DELETE` | `/api/v1/equipment/types/{id}` | `ADMIN`, `OWNER` | Soft delete kategori (Ditolak 409 jika memiliki model terkait) |
| `GET` | `/api/v1/equipment/models` | Publik / Guest | Katalog model alat dengan filter `?equipment_type_id=`, `?brand=`, `?is_active=` |
| `GET` | `/api/v1/equipment/models/{id}` | Publik / Guest | Detail model armada lengkap beserta relasi tipe dan master harga |
| `POST` | `/api/v1/equipment/models` | `ADMIN`, `OWNER` | Tambah seri model armada baru |
| `PUT` | `/api/v1/equipment/models/{id}` | `ADMIN`, `OWNER` | Perbarui spesifikasi seri model armada |
| `DELETE` | `/api/v1/equipment/models/{id}` | `ADMIN`, `OWNER` | Soft delete seri model (Ditolak 409 jika memiliki unit fisik aktif) |

---

## 3. Aturan Validasi & Integritas Bisnis

1. **Keunikan Nama (Unique Constraint):**
   - `name` pada `equipment_types` harus unik secara global.
   - `model_name` pada `equipment_models` harus unik secara global.
2. **Integritas Relasi (Referential Protection):**
   - Tipe alat tidak dapat dihapus jika masih memiliki model armada yang terikat (`409 CONFLICT`).
   - Model armada tidak dapat dihapus jika masih memiliki unit fisik aktif di database (`409 CONFLICT`).
3. **Format Kapasitas:**
   - Kolom `capacity_value` divalidasi sebagai nilai desimal positif numerik (`min: 0.01`).
   - Kolom `capacity_unit` dibatasi pada satuan yang disetujui (Ton, m3, HP, Liter).
4. **Hak Akses Otorisasi:**
   - Pengguna umum (`USER`) hanya memiliki akses baca (*read-only*) untuk browsing katalog sewa.
   - Seluruh mutasi data (`store`, `update`, `destroy`) dilindungi Gate `manage-equipment` (khusus role `ADMIN` dan `OWNER`).

---

## 4. Antarmuka Meja Kerja Admin (`AdminEquipmentMasterPage.tsx`)

Antarmuka terintegrasi pada portal Admin (`/admin/equipment`) yang menyediakan:
- **Tab Navigasi WAI-ARIA:** Beralih mulus antara tab *Model Armada* dan *Kategori / Tipe Alat*.
- **Pencarian & Penyaringan Ter-debounce:** Filter teks real-time dengan jeda 300ms, filter dropdown kategori tipe, dan filter merk pabrikan.
- **Form Modal Terpadu:** Form pembuatan dan pengeditan dengan penanganan pesan error per-field dari respons HTTP 422 backend.
- **Konfirmasi Aksi Destruktif:** Pemicu `ConfirmDialog` sebelum penghapusan master data.
- **Umpan Balik Responsif:** Pemuatan tabel menggunakan `TableSkeleton` dan notifikasi mengambang `Toast` saat operasi berhasil atau gagal.
