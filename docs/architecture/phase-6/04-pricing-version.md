# Master Harga & Versi Harga Armada (Pricing & Price Versioning) - RAFA Rental System

Dokumentasi spesifikasi implementasi backend API dan manajemen harga untuk tabel `equipment_prices` dan `equipment_price_versions` pada RAFA Rental System (Phase 6D).

---

## 1. Arsitektur Penentuan Harga & Versi

Struktur tabel memisahkan antara tabel tarif master aktif dan tabel riwayat versi harga (*Historical Price Immutability*):

$$\text{EquipmentModel} \xrightarrow{1:N} \text{EquipmentPrice (Master)} \xrightarrow{1:N} \text{EquipmentPriceVersion (Audit Append-Only)}$$

- **Tipe Tarif:** Default `HOURLY` (Per Jam).
- **Skema Harga:**
  - `is_all_in = true` (Termasuk Operator + BBM + Maintenance preventif harian)
  - `is_all_in = false` (Sewa unit saja / Bare Rental)
- **Komponen Tarif:** `base_rate` (Tarif dasar per jam), `minimum_hours` (Batas sewa min. jam/hari, default 8), `overtime_rate` (Tarif lembur), dan `effective_date` (Tanggal mulai berlaku).

---

## 2. Kontrak Endpoint REST API (`/api/v1/equipment/prices/*`)

| Method | Endpoint | Akses / Role | Deskripsi |
|---|---|---|---|
| `GET` | `/api/v1/equipment/prices` | `ADMIN`, `OWNER` | Daftar master harga dengan filter `?equipment_model_id=`, `?is_all_in=` |
| `GET` | `/api/v1/equipment/prices/{id}` | `ADMIN`, `OWNER` | Detail master harga lengkap dengan riwayat array `versions` |
| `POST` | `/api/v1/equipment/prices` | `OWNER` Only | Penetapan master tarif baru (mencatat versi 1) |
| `PUT` | `/api/v1/equipment/prices/{id}` | `OWNER` Only | Pembaruan tarif (otomatis merekam snapshot versi baru) |

---

## 3. Presisi Tipe Data & Keamanan Audit Finansial

1. **Format Nilai Uang (DECIMAL):**
   - Kolom `base_rate`, `overtime_rate`, `old_base_rate`, dan `new_base_rate` bertipe **`DECIMAL(15,2)`**.
   - Dilarang menggunakan tipe floating point (`float`/`double`) untuk mencegah error pembulatan rupiah.
2. **Imutabilitas Riwayat (Historical Integrity):**
   - Perubahan harga yang dilakukan Owner tidak pernah mengubah (*overwrite*) baris versi lama di `equipment_price_versions`.
   - Record versi baru di-append dengan kolom `old_base_rate`, `new_base_rate`, `changed_by`, dan `changed_at`.
   - Transaksi sewa di masa depan (`booking_details` dan `invoice_details`) menyalin harga secara statis (*snapshot copy*), sehingga kenaikan tarif master di kemudian hari tidak akan pernah merusak pembukuan booking atau invoice lampau.
3. **Otorisasi Otoritas Penetapan Harga (Pricing Governance):**
   - Penetapan dan pembaruan tarif master dilindungi secara ketat oleh Gate `manage-pricing-master` yang hanya dimiliki oleh peran **`OWNER`**. Admin dan User biasa ditolak dengan HTTP 403 `FORBIDDEN_ACTION`.

---

## 4. Hasil Pengujian Backend (`EquipmentPriceApiTest.php`)

- `owner_can_create_master_price_and_initial_version_is_recorded`: PASSED (Pembuatan harga awal merekam version 1 dengan `old_base_rate: 0.00`).
- `owner_can_update_price_and_new_immutable_version_is_appended`: PASSED (Pembaruan tarif merekam baris riwayat baru dengan tarif lama dan baru).
- `regular_user_cannot_create_or_update_master_price`: PASSED (User publik ditolak 403).
- `admin_and_owner_can_list_and_view_price_version_history`: PASSED (Admin & Owner dapat meninjau histori tarif lampau).
