# Phase 6: Master Data & Fleet Management — RAFA Rental System

Dokumentasi arsitektur komprehensif, implementasi teknis, dan verifikasi kualitas untuk modul Master Data Armada, Inventaris Fisik Alat Berat, Rekening Bank Perusahaan, dan Mesin Kalkulasi Tarif Sewa pada RAFA Rental System.

---

## 1. Tujuan

Phase 6 bertujuan untuk membangun fondasi data operasional dan skema komersial penyewaan alat berat RAFA Rental System sesuai spesifikasi PRD V3, Phase 1 System Architecture, dan Phase 4 UI Standards:
- Memodelkan hirarki master data armada yang terstruktur: Kategori/Tipe (`equipment_types`), Seri Model (`equipment_models`), dan Inventaris Unit Fisik (`equipment_units`).
- Menerapkan sistem penentuan tarif sewa berpresisi tinggi (`DECIMAL(15,2)`), skema *All-in* (Unit + BBM + Operator) vs *Non All-in* (Bare Rental), logistik mobilisasi/demobilisasi (*MOB/DEMOB*), dan pencatatan riwayat versi harga yang imutabel (`equipment_price_versions`).
- Menyediakan master rekening bank resmi perusahaan (`bank_accounts`) untuk instruksi pembayaran pelanggan.
- Menyediakan antarmuka manajemen Admin/Owner yang responsif dan katalog penjelajahan armada pelanggan yang informatif tanpa mengekspos nomor seri fisik maupun data internal bengkel.

---

## 2. Subphase

| Subphase | Fokus Modul | Status |
|---|---|---|
| **6A** | Master Data Kategori Tipe Alat Berat & Seri Model Armada | Selesai (`366e5da`) |
| **6B** | Inventaris Unit Fisik, Status Lifecycle Safety, & Hour Meter | Selesai (`3a92e95`) |
| **6C** | Spesifikasi Armada & Media Lampiran Foto Terverifikasi | Selesai (`04d052d`) |
| **6D** | Master Tarif Sewa, Imutabilitas Audit Versi Harga, Otoritas Owner | Selesai (`d102340`) |
| **6E** | Skema Sewa All-in vs Non All-in & Mesin Kalkulasi MOB/DEMOB | Selesai (`ea82db8`) |
| **6F** | Master Rekening Bank Perusahaan & Proteksi Otorisasi | Selesai (`9a29777`) |
| **6G** | Antarmuka Manajemen Master Data Admin & Owner Pricing UI | Selesai (`32d3332`) |
| **6H** | Antarmuka Penjelajahan Katalog Armada Pelanggan | Selesai (`e814ced`) |
| **6I** | Audit Pengujian Integrasi Menyeluruh & Quality Gate | Selesai (`0134e89`) |
| **6J** | Final Architecture Review, Dokumentasi Sign-off, & Git Merge | Selesai (Aktif) |

---

## 3. Equipment Architecture

Hirarki armada dirancang mengikuti normalisasi 3 tingkat yang ketat:

```text
[EquipmentType] (e.g. Excavator, Bulldozer, Crane)
       │ 1:N (Restricted delete on child presence)
       ▼
[EquipmentModel] (e.g. Komatsu PC200-8, Cat 320D)
   ├── 1:N ── [Attachment] (Polymorphic Media: Foto Alat, max 5MB, JPEG/PNG/WebP)
   ├── 1:N ── [EquipmentPrice] (Master Tarif: All-in / Non All-in)
   └── 1:N ── [EquipmentUnit] (Nomor Seri Fisik, Plat Nomor Lambung)
                   │
                   ▼ (State Machine Guards)
          [AVAILABLE] <──> [MAINTENANCE]
               │                │
               ▼                ▼
          [ASSIGNED]      [DECOMMISSIONED]
               │
          [MOBILIZING] ──> [ON_SITE] (Protected: Locked from manual override)
                               │
                          [DEMOBILIZING] ──> [RETURN_INSPECTION]
```

### Aturan Keselamatan Status (Safety Guards)
1. Unit berstatus `ON_SITE` (sedang dalam kontrak sewa lapangan) dikunci secara ketat di tingkat controller/service; tidak dapat dipaksa menjadi `AVAILABLE` atau dihapus.
2. Unit fisik hanya dapat dihapus (*soft delete*) bila berstatus `AVAILABLE` atau `DECOMMISSIONED`.
3. Tipe alat tidak dapat dihapus jika masih menaungi model armada (`409 CONFLICT`). Model armada tidak dapat dihapus jika masih memiliki unit fisik terdaftar (`409 CONFLICT`).

---

## 4. Pricing Architecture

Arsitektur komersial dirancang dengan prinsip imutabilitas finansial dan kepatuhan audit:

1. **Presisi Finansial:** Seluruh tarif sewa disimpan dalam tipe data `DECIMAL(15,2)` untuk mencegah kesalahan pembulatan *floating-point*.
2. **Skema Tarif:**
   - **Non All-in (Bare Rental):** Sewa unit fisik murni. Bahan bakar solar, akomodasi operator, dan pelumas ditanggung penyewa.
   - **All-in:** Sewa komprehensif mencakup unit alat berat, jatah BBM solar harian standar, dan upah operator bersertifikat SIO.
3. **Logika Kalkulasi Waktu:**
   - Basis sewa harian standar dihitung minimal **8 jam per hari kalender**.
   - Kelebihan jam kerja dihitung berdasarkan tarif lembur (*overtime rate*) per jam.
4. **Logistik MOB / DEMOB:**
   - Tarif mobilisasi (pengantaran ke lokasi proyek) dan demobilisasi (pemulangan dari lokasi proyek) dihitung strictly per **unit fisik mandiri**.
5. **Audit Trail Versi Harga:**
   - Setiap mutasi `base_rate` pada master harga secara otomatis mencatat riwayat ke tabel `equipment_price_versions`.
   - Data riwayat versi bersifat permanen (*append-only*), merekam tarif sebelum perubahan, tarif baru, timestamp per detik, dan user ID Owner yang mengesahkan.

---

## 5. Bank Account

- Menyimpan data rekening bank resmi penerima dana sewa (`bank_accounts`).
- Pengguna umum dan penyewa hanya dapat melihat rekening yang berstatus aktif (`is_active = true`) untuk instruksi transfer manual atau konfirmasi invoice.
- Pembuatan, pembaruan nomor rekening, dan deaktivasi rekening hanya dapat dilakukan oleh role `ADMIN` dan `OWNER`.
- Validasi nomor rekening bersifat unik di tingkat basis data.

---

## 6. Admin UI

Antarmuka portal Admin (`/admin/*`) dan Owner (`/owner/*`) dirancang modular:
- **`AdminEquipmentMasterPage` (`/admin/equipment`):** Navigasi tab terpisah antara Model Armada dan Kategori Tipe Alat, modal pendaftaran armada baru, filter pencarian ter-debounce, dan indikator kesiapan sewa.
- **`AdminEquipmentUnitsPage` (`/admin/units`):** Manajemen unit fisik, pelacakan Hour Meter (HM) kumulatif, modal ubah status pemeliharaan bengkel (*MAINTENANCE*), dan perlindungan penghapusan unit aktif.
- **`AdminBankAccountsPage` (`/admin/banks` / `/owner/settings`):** Pengelolaan rekening penampung, switch toggle aktif/nonaktif seketika dengan dialog konfirmasi.
- **`OwnerPricingPage` (`/owner/pricing`):** Wewenang eksklusif Owner untuk menetapkan tarif per jam, skema All-in vs Non All-in, dan inspeksi modal riwayat versi harga (*price version audit history*).

---

## 7. User Catalog

Antarmuka katalog pelanggan (`/app/equipment` dan `/app/equipment/:id`) dirancang murni untuk eksplorasi spesifikasi:
- **Perlindungan Privasi Data Internal:** Nomor seri fisik armada (`serial_number`), plat lambung, dan catatan servis mekanik disembunyikan dari pelanggan.
- **Pencarian & Filter Ter-debounce:** Pencarian nama/merk ter-debounce 300ms serta filter kategori tipe alat.
- **Perbandingan Skema Tarif:** Menampilkan perbandingan kartu harga Non All-in vs All-in dengan rincian basis 8 jam.
- **Galeri Foto:** Pratinjau gambar multi-sudut dengan pemuatan gambar berbasis *lazy-loading*.
- **Guard Batasan Scope:** Modul ini tidak menyediakan alur keranjang belanja (*cart*) atau checkout sewa (dialokasikan pada fase selanjutnya).

---

## 8. Testing

Verifikasi menyeluruh dilakukan pada sisi Backend dan Frontend:

### Hasil Pengujian Backend (PHPUnit)
- **188 Tests Passed, 668 Assertions, 0 Failures** (Durasi: ~41s)
- **Cakupan Pengujian:**
  - `EquipmentTypeAndModelApiTest`: CRUD tipe & model, filter, search, soft delete, FK guard.
  - `EquipmentUnitApiTest`: Manajemen nomor seri, validasi keunikan, transisi status, pembaruan HM.
  - `EquipmentMediaApiTest`: Upload foto model, validasi MIME/ukuran berkas, isolasi hapus antar-model.
  - `EquipmentPriceApiTest`: Pembuatan master tarif, pembatasan wewenang Owner, riwayat versi.
  - `PricingCalculationTest`: Mesin simulasi harga, skema sewa All-in/Non All-in, MOB/DEMOB, proteksi model inaktif.
  - `BankAccountApiTest`: Penyaringan rekening aktif, pembatasan hak mutasi Admin/Owner.
  - `MasterDataAndPricingIntegrationTest`: Pengujian alur hidup terintegrasi end-to-end dari pendaftaran tipe hingga simulasi sewa.

### Hasil Pengujian Frontend (Vitest)
- **52 Tests Passed, 14 Test Files, 0 Failures** (Durasi: ~16s)
- Menguji rendering antarmuka, interaksi modal, filter dan pencarian, paginasi, state *loading skeleton*, *empty state*, penanganan error banner, dan *route protection*.

### Standar Kualitas Kode (Code Style & Type Safety)
- **Laravel Pint:** 100% Passed (`./vendor/bin/pint --test`).
- **Frontend Build:** 100% Clean (`tsc -b && vite build` selesai dalam <1.5s tanpa error TypeScript).

---

## 9. Performance

1. **Eliminasi Masalah N+1 Query:** Seluruh pemanggilan koleksi model dan unit fisik menerapkan *eager loading* relasi (`with(['type', 'attachments', 'prices'])`, `withCount('units')`).
2. **Paginasi Sisi Server (Server-Side Pagination):** Batas `per_page` konsisten (10 untuk admin, 9 untuk kartu grid katalog pelanggan).
3. **Debounced Search:** Jeda 300ms mencegah banjir *network requests* ke API server saat pengguna mengetik.
4. **Image Lazy Loading:** Penanda `loading="lazy"` disematkan pada seluruh elemen `<img>` armada.

---

## 10. Security

1. **Prinsip Otorisasi Minimum (Principle of Least Privilege):**
   - Role `USER` dibatasi pada operasi *read-only* data publik; seluruh upaya mutasi master data ditolak dengan status `403 Forbidden`.
   - Mutasi master tarif sewa (`POST/PUT /equipment/prices`) dikunci secara eksklusif untuk role `OWNER`. Role `ADMIN` diblokir.
2. **Keamanan Berkas Unggahan:**
   - Validasi ketat terhadap ukuran file (maksimal 5MB) dan MIME type (`image/jpeg`, `image/png`, `image/webp`). Berkas executable dan script dilarang keras.
   - Penamaan berkas acak yang aman (*secure hashed path*) untuk mencegah eksploitasi *path traversal*.
3. **Audit Trail Mutasi Finansial:** Seluruh perubahan tarif merekam ID pengguna pengubah dan waktu perubahan tanpa kemampuan manipulasi data lampau.

---

## 11. Known Limitations

1. **Unggahan Foto Tunggal:** Unggahan foto armada saat ini diproses satu berkas per request HTTP (belum mendukung batch multi-file upload langsung).
2. **Pilihan Skema Harga:** Master harga saat ini berfokus pada tarif per jam (*HOURLY*); skema tarif harian/bulanan tetap dikonversi berbasis 8 jam kerja standar harian.
3. **Storage Penyimpanan:** Berkas media disimpan pada disk lokal (`storage/app/public`); belum menggunakan CDN eksternal (sesuai target deployment shared hosting/cPanel).

---

## 12. Recommendation Phase 7

1. **Modul Keranjang Belanja (Cart) & Validasi Ketersediaan Jadwal:** Memanfaatkan struktur model dan skema harga Phase 6 untuk perhitungan subtotal sewa dan estimasi MOB/DEMOB di keranjang belanja pelanggan.
2. **Slot Waktu Booking & Unit Availability Engine:** Mengintegrasikan status unit fisik (`AVAILABLE`, `ASSIGNED`, `ON_SITE`) dengan kalender proyek untuk mencegah *double-booking* armada.
3. **Penyimpanan Snapshot Harga pada Booking:** Saat pelanggan melakukan checkout booking, tarif sewa dan biaya MOB/DEMOB wajib di-snapshot ke tabel detail pesanan agar tidak terpengaruh oleh kenaikan master tarif di masa depan.
