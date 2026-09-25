# Media & Upload Foto Armada - RAFA Rental System

Dokumentasi spesifikasi lampiran media foto peralatan (*equipment media*), keamanan storage, dan validasi berkas pada RAFA Rental System (Phase 6C).

---

## 1. Aturan Spesifikasi Model & Foto

- **Spesifikasi Model (`equipment_models`):** Merujuk mutlak pada tabel Data Dictionary Phase 1 (`brand`, `model_name`, `capacity_value`, `capacity_unit`, `is_active`). Tidak menambahkan kolom spesifikasi di luar schema Phase 1.
- **Relasi Polimorfik Media:** Foto fisik alat dihubungkan sebagai lampiran polimorfik (`attachments`) dengan properti:
  - `attachable_type`: `App\Models\EquipmentModel`
  - `attachable_id`: ID Model
  - `document_type`: `'EQUIPMENT_PHOTO'`

---

## 2. Kontrak Endpoint REST API Media (`/api/v1/equipment/models/{model}/photos`)

| Method | Endpoint | Akses / Role | Deskripsi |
|---|---|---|---|
| `POST` | `/api/v1/equipment/models/{model}/photos` | `ADMIN`, `OWNER` | Upload foto alat berat baru |
| `DELETE` | `/api/v1/equipment/models/{model}/photos/{attachment}` | `ADMIN`, `OWNER` | Hapus foto dari model dan dari disk |
| `GET` | `/api/v1/equipment/models/{id}` | Publik / Guest | Menampilkan detail model beserta array `attachments` yang berisi URL foto publik |

---

## 3. Keamanan Penyimpanan & Validasi File (`FileSecurity`)

1. **MIME & Extension Validation:**
   - Hanya menerima file gambar dengan MIME: `image/jpeg`, `image/png`, `image/webp`.
   - Ekstensi file yang diperbolehkan: `.jpg`, `.jpeg`, `.png`, `.webp`.
   - Mencegah file dokumen lain (PDF, PHP, EXE) diupload sebagai foto armada.
2. **Ukuran File Maksimum:**
   - Dibatasi maksimum **5 MB (5120 KB)** per file (`max:5120`).
3. **Penyimpanan Hashed & Isolatif:**
   - Nama file diubah secara acak menggunakan hash acak 40 karakter (`bin2hex(random_bytes(20))`) di direktori tanggal (`equipment/YYYY/MM/hash.ext`).
   - Mencegah *path traversal* atau penebakan nama file di server.
4. **Distribusi URL Publik:**
   - Foto publik diekspos melalui symbolic link `Storage::disk('public')->url($path)` tanpa membocorkan struktur direktori server internal.

---

## 4. Hasil Pengujian Keamanan File (`EquipmentMediaApiTest.php`)

- `admin_can_upload_valid_equipment_photo`: PASSED (upload foto JPG/PNG 1MB sukses).
- `cannot_upload_invalid_mime_file_as_photo`: PASSED (PDF ditolak dengan HTTP 422).
- `cannot_upload_photo_exceeding_max_size`: PASSED (File > 5MB ditolak dengan HTTP 422).
- `regular_user_cannot_upload_equipment_photo`: PASSED (User biasa ditolak dengan HTTP 403).
- `admin_can_delete_equipment_photo`: PASSED (Hapus attachment & hapus file di disk).
- `public_can_view_model_details_with_photos`: PASSED (Public dapat melihat URL foto model).
