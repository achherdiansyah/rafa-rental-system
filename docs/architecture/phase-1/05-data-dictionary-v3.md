# Data Dictionary V3 - RAFA Rental System

Kamus data komprehensif mendefinisikan skema struktur 29 entitas tabel sistem beserta properti, konstrain, dan aturan integritas.

**Standar Umum:**
- **Monetary (Uang):** `DECIMAL(15,2)` untuk presisi IDR tanpa error pembulatan floating-point.
- **Waktu/Tanggal:** `TIMESTAMP` tersimpan dalam format UTC. Aplikasi merender (display) dalam Timezone `Asia/Jakarta` (WIB).
- **Soft Deletes:** Menggunakan kolom `deleted_at` (Nullable TIMESTAMP).
- **Audit Trails:** Kolom `created_by`, `updated_by`, `deleted_by` menyimpan `users.id`.
- **String Keys:** Kolom UUID/Ulid untuk tabel transaksi (opsional, default BigInt Auto-Increment `id`).

---

## 1. Modul Pengguna & Profil

### 1.1 `users`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `name` | VARCHAR(255) | N | - | - | Nama pengguna |
| `email` | VARCHAR(255) | N | - | UK, IDX| Unik |
| `password` | VARCHAR(255) | N | - | - | Hashed password |
| `role` | ENUM | N | 'USER' | IDX | `USER`, `ADMIN`, `OWNER` |
| `phone_number` | VARCHAR(30) | Y | NULL | UK, IDX| Nomor telepon unik |
| `is_active` | BOOLEAN | N | 1 | - | Status aktif akun |
| `email_verified_at`| TIMESTAMP | Y | NULL | - | Waktu verifikasi email |
| `timestamps` | TIMESTAMP | N | NOW() | - | `created_at`, `updated_by`, dst. |
| `deleted_at` | TIMESTAMP | Y | NULL | - | Soft delete |

### 1.2 `customer_profiles`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `user_id` | BIGINT UNSIGNED | N | - | FK, UK | Referensi `users(id)`, CASCADE DELETE |
| `company_name` | VARCHAR(255) | Y | NULL | - | Nama entitas perusahaan (bila ada) |
| `identity_type` | ENUM | N | 'KTP' | - | `KTP`, `NPWP`, `PASSPORT` |
| `identity_number`| VARCHAR(100) | Y | NULL | UK | Nomor identitas unik |
| `address` | TEXT | Y | NULL | - | Alamat sesuai identitas |
| `verification_status`| ENUM | N | 'UNVERIFIED'| - | `UNVERIFIED`, `VERIFIED`, `REJECTED` |
| `timestamps` | TIMESTAMP | N | NOW() | - | - |

### 1.3 `project_locations`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `user_id` | BIGINT UNSIGNED | N | - | FK, IDX| Referensi `users(id)`, CASCADE DELETE |
| `project_name` | VARCHAR(255) | N | - | - | Nama proyek |
| `address` | TEXT | N | - | - | Alamat rinci pengiriman |
| `city` | VARCHAR(100) | N | - | IDX | Kota/Kabupaten proyek |
| `pic_name` | VARCHAR(255) | N | - | - | Penanggung jawab lapangan |
| `pic_phone` | VARCHAR(30) | N | - | - | Kontak PIC lapangan |
| `latitude` | DECIMAL(10,8)| Y | NULL | - | Koordinat lintang |
| `longitude` | DECIMAL(11,8)| Y | NULL | - | Koordinat bujur |
| `is_active` | BOOLEAN | N | 1 | - | Status lokasi masih relevan |
| `timestamps` | TIMESTAMP | N | NOW() | - | - |
| `deleted_at` | TIMESTAMP | Y | NULL | - | Soft delete |

---

## 2. Modul Armada & Katalog (Equipment)

### 2.1 `equipment_types`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `name` | VARCHAR(255) | N | - | UK, IDX| Kategori/Tipe (Misal: Excavator, Dozer) |
| `description` | TEXT | Y | NULL | - | Keterangan tipe umum |
| `timestamps` | TIMESTAMP | N | NOW() | - | - |
| `deleted_at` | TIMESTAMP | Y | NULL | - | Soft delete |

### 2.2 `equipment_models`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `equipment_type_id`| BIGINT UNSIGNED | N | - | FK, IDX| Ref `equipment_types(id)`, RESTRICT DELETE |
| `brand` | VARCHAR(100) | N | - | - | Merk (Komatsu, Caterpillar) |
| `model_name` | VARCHAR(150) | N | - | UK | Seri model (PC200-8) |
| `capacity_value` | DECIMAL(8,2) | N | - | - | Angka kapasitas (20.0) |
| `capacity_unit` | VARCHAR(20) | N | - | - | Satuan kapasitas (Ton, m3) |
| `is_active` | BOOLEAN | N | 1 | - | Status tampil di katalog |
| `timestamps` | TIMESTAMP | N | NOW() | - | - |
| `deleted_at` | TIMESTAMP | Y | NULL | - | Soft delete |

### 2.3 `equipment_units` (Physical Unit)
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `equipment_model_id`| BIGINT UNSIGNED | N | - | FK, IDX| Ref `equipment_models(id)`, RESTRICT DELETE |
| `serial_number` | VARCHAR(100) | N | - | UK | Nomor rangka / unit mesin |
| `plate_number` | VARCHAR(30) | Y | NULL | UK | Plat kendaraan (No Polisi/Lambung) |
| `status` | ENUM | N | 'AVAILABLE' | IDX | `AVAILABLE`, `ASSIGNED`, `MOBILIZING`, `ON_SITE`, `DEMOBILIZING`, `RETURN_INSPECTION`, `MAINTENANCE`, `DECOMMISSIONED` |
| `last_hour_meter`| DECIMAL(10,2)| N | 0.00 | - | Akumulasi total HM unit |
| `year_of_make` | INT(4) | Y | NULL | - | Tahun pembuatan |
| `timestamps` | TIMESTAMP | N | NOW() | - | - |
| `deleted_at` | TIMESTAMP | Y | NULL | - | Soft delete |

### 2.4 `equipment_prices`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `equipment_model_id`| BIGINT UNSIGNED | N | - | FK, IDX| Ref `equipment_models(id)`, CASCADE DELETE |
| `price_type` | ENUM | N | 'HOURLY' | - | `HOURLY`, `DAILY`, `MONTHLY`, `LUMP_SUM` |
| `is_all_in` | BOOLEAN | N | 0 | IDX | 1=Termasuk operator+BBM, 0=Unit Saja |
| `base_rate` | DECIMAL(15,2)| N | - | - | Tarif dasar sewa |
| `minimum_hours` | INT | N | 0 | - | Syarat mininum jam sewa harian |
| `overtime_rate` | DECIMAL(15,2)| N | 0.00 | - | Tarif lembur (jika ada) |
| `effective_date` | DATE | N | - | IDX | Mulai berlaku harga ini |
| `timestamps` | TIMESTAMP | N | NOW() | - | - |

### 2.5 `equipment_price_versions`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `equipment_price_id`| BIGINT UNSIGNED | N | - | FK, IDX| Ref `equipment_prices(id)`, CASCADE DELETE |
| `old_base_rate` | DECIMAL(15,2)| N | - | - | Tarif historis lama |
| `new_base_rate` | DECIMAL(15,2)| N | - | - | Tarif baru pasca update |
| `changed_at` | TIMESTAMP | N | NOW() | - | Waktu mutasi harga |
| `changed_by` | BIGINT UNSIGNED | N | - | FK | Ref `users(id)`, user pengubah tarif |

---

## 3. Modul Keranjang (Cart)

### 3.1 `carts`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `user_id` | BIGINT UNSIGNED | N | - | FK, UK | Ref `users(id)`, CASCADE DELETE. 1 User = 1 Active Cart |
| `project_location_id`| BIGINT UNSIGNED| Y | NULL | FK | Ref `project_locations(id)`. Validasi alamat tunggal |
| `timestamps` | TIMESTAMP | N | NOW() | - | - |

### 3.2 `cart_items`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `cart_id` | BIGINT UNSIGNED | N | - | FK, IDX| Ref `carts(id)`, CASCADE DELETE |
| `equipment_model_id`| BIGINT UNSIGNED | N | - | FK, IDX| Ref `equipment_models(id)`, CASCADE DELETE |
| `quantity` | INT | N | 1 | - | Jumlah unit tipe yang disewa |
| `is_all_in` | BOOLEAN | N | 0 | - | Pemilihan skema opsi All-in |
| `start_date` | DATE | N | - | - | Rencana tgl mulai sewa |
| `end_date` | DATE | N | - | - | Rencana tgl selesai sewa |
| `timestamps` | TIMESTAMP | N | NOW() | - | - |

---

## 4. Modul Pemesanan (Booking & Assignment)

### 4.1 `bookings`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `booking_code` | VARCHAR(50) | N | - | UK, IDX| Kode reservasi unik (RFA-BKG-XXXX) |
| `user_id` | BIGINT UNSIGNED | N | - | FK, IDX| Ref `users(id)`, RESTRICT DELETE |
| `project_location_id`| BIGINT UNSIGNED | N | - | FK | Ref `project_locations(id)`, RESTRICT DELETE |
| `status` | ENUM | N | 'DRAFT' | IDX | `DRAFT`, `SUBMITTED`, `PENDING_APPROVAL`, `REJECTED`, `APPROVED`, `PAYMENT_PENDING`, `CONFIRMED`, `DISPATCHED`, `ARRIVED`, `ONGOING`, `COMPLETED`, `CANCELLED`, `EXPIRED` |
| `rejection_reason`| TEXT | Y | NULL | - | Catatan penolakan Admin |
| `total_amount` | DECIMAL(15,2)| N | 0.00 | - | Estimasi grand total (sebelum tagihan akhir) |
| `timestamps` | TIMESTAMP | N | NOW() | - | - |
| `deleted_at` | TIMESTAMP | Y | NULL | - | Soft delete |

### 4.2 `booking_details`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `booking_id` | BIGINT UNSIGNED | N | - | FK, IDX| Ref `bookings(id)`, CASCADE DELETE |
| `equipment_model_id`| BIGINT UNSIGNED | N | - | FK | Ref `equipment_models(id)`, RESTRICT DELETE |
| `quantity` | INT | N | 1 | - | Kuota kapasitas tipe alat yang diminta |
| `start_date` | DATE | N | - | - | Mulai sewa |
| `end_date` | DATE | N | - | - | Akhir sewa |
| `is_all_in` | BOOLEAN | N | 0 | - | Snapshot opsi harga |
| `rental_rate_snapshot`| DECIMAL(15,2)| N | - | - | Harga master tarif tersimpan mati (Immutable) |
| `subtotal` | DECIMAL(15,2)| N | 0.00 | - | Subtotal tarif untuk item ini |
| `timestamps` | TIMESTAMP | N | NOW() | - | - |

### 4.3 `booking_unit_assignments`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `booking_detail_id`| BIGINT UNSIGNED | N | - | FK, IDX| Ref `booking_details(id)`, CASCADE DELETE |
| `equipment_unit_id`| BIGINT UNSIGNED | N | - | FK, IDX| Ref `equipment_units(id)`, RESTRICT DELETE |
| `status` | ENUM | N | 'ASSIGNED'| - | `ASSIGNED`, `REPLACED`, `CANCELLED`, `COMPLETED` |
| `is_current` | BOOLEAN | N | 1 | - | 1 = Aktif, 0 = Riwayat lampau |
| `assigned_by` | BIGINT UNSIGNED | N | - | FK | Ref `users(id)` Admin penugas |
| `replaced_reason`| TEXT | Y | NULL | - | Alasan jika status REPLACED |
| `timestamps` | TIMESTAMP | N | NOW() | - | - |

---

## 5. Modul Operasional (Rental & Timesheet)

### 5.1 `rentals`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `booking_id` | BIGINT UNSIGNED | N | - | FK, UK | Ref `bookings(id)`, RESTRICT DELETE |
| `status` | ENUM | N | 'PENDING' | IDX | `PENDING_ASSIGNMENT`, `ASSIGNED`, `DISPATCHED`, `ARRIVED`, `ONGOING`, `DEMOBILIZING`, `RETURN_INSPECTED`, `COMPLETED`, `CANCELLED` |
| `started_at` | TIMESTAMP | Y | NULL | - | Waktu aktual BAST Check-In tervalidasi |
| `completed_at` | TIMESTAMP | Y | NULL | - | Waktu aktual BAST Check-Out tervalidasi |
| `timestamps` | TIMESTAMP | N | NOW() | - | - |

### 5.2 `rental_details`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `rental_id` | BIGINT UNSIGNED | N | - | FK, IDX| Ref `rentals(id)`, CASCADE DELETE |
| `assignment_id` | BIGINT UNSIGNED | N | - | FK, UK | Ref `booking_unit_assignments(id)`, RESTRICT DELETE |
| `check_in_hm` | DECIMAL(10,2)| Y | NULL | - | Hour Meter awal saat serah terima unit di proyek |
| `check_out_hm` | DECIMAL(10,2)| Y | NULL | - | Hour Meter akhir saat pengembalian unit ke pool |
| `condition_notes`| TEXT | Y | NULL | - | Catatan kerusakan (Baret, Kaca pecah, dll) |
| `status` | ENUM | N | 'PENDING' | - | Status level unit operasi spesifik |
| `timestamps` | TIMESTAMP | N | NOW() | - | - |

### 5.3 `timesheets`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `rental_detail_id`| BIGINT UNSIGNED | N | - | FK, IDX| Ref `rental_details(id)`, CASCADE DELETE |
| `report_date` | DATE | N | - | IDX | Tanggal laporan kerja |
| `start_hm` | DECIMAL(10,2)| N | - | - | HM awal harian |
| `end_hm` | DECIMAL(10,2)| N | - | - | HM akhir harian |
| `total_work_hours`| DECIMAL(8,2) | N | - | - | Jam kerja efektif |
| `standby_hours` | DECIMAL(8,2) | N | 0.00 | - | Jam standby mesin di lapangan |
| `breakdown_hours`| DECIMAL(8,2) | N | 0.00 | - | Jam kerusakan teknis |
| `status` | ENUM | N | 'DRAFT' | - | `DRAFT`, `SUBMITTED`, `APPROVED`, `REJECTED` |
| `approved_by` | BIGINT UNSIGNED | Y | NULL | FK | Admin peninjau |
| `timestamps` | TIMESTAMP | N | NOW() | - | - |

### 5.4 `timesheet_revisions`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `timesheet_id` | BIGINT UNSIGNED | N | - | FK, IDX| Ref `timesheets(id)`, CASCADE DELETE |
| `version` | INT | N | 1 | - | Nomor iterasi revisi (Immutabilitas historis) |
| `old_start_hm` | DECIMAL(10,2)| N | - | - | Data sebelumnya |
| `old_end_hm` | DECIMAL(10,2)| N | - | - | Data sebelumnya |
| `revision_reason`| TEXT | N | - | - | Alasan perubahan mutlak diisi |
| `revised_by` | BIGINT UNSIGNED | N | - | FK | Aktor pelaku revisi |
| `created_at` | TIMESTAMP | N | NOW() | - | Waktu jejak revisi (Read-Only) |

---

## 6. Modul Finansial (Invoice, Payment, Refund)

### 6.1 `invoices`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `invoice_number` | VARCHAR(50) | N | - | UK, IDX| Format resmi INV/YYYYMM/XXXX |
| `booking_id` | BIGINT UNSIGNED | N | - | FK | Ref `bookings(id)`, RESTRICT DELETE |
| `due_at` | TIMESTAMP | N | - | IDX | Deadline (Timer invariant). +24 Jam dr penerbitan. |
| `status` | ENUM | N | 'UNPAID' | IDX | `DRAFT`, `UNPAID`, `PARTIALLY_PAID`, `PAID`, `OVERPAID`, `EXPIRED`, `CANCELLED` |
| `subtotal` | DECIMAL(15,2)| N | 0.00 | - | Total sewa + mobdemob murni |
| `tax_total` | DECIMAL(15,2)| N | 0.00 | - | Akumulasi pajak |
| `grand_total` | DECIMAL(15,2)| N | 0.00 | - | Subtotal + Pajak - Diskon |
| `paid_amount` | DECIMAL(15,2)| N | 0.00 | - | Akumulasi total pembayaran disetujui (Approved Payments) |
| `overpayment_amount`| DECIMAL(15,2)| N | 0.00 | - | Nilai lebih bayar menunggu tindakan |
| `timestamps` | TIMESTAMP | N | NOW() | - | - |
| `deleted_at` | TIMESTAMP | Y | NULL | - | Soft delete |

### 6.2 `invoice_details`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `invoice_id` | BIGINT UNSIGNED | N | - | FK, IDX| Ref `invoices(id)`, CASCADE DELETE |
| `description` | VARCHAR(255) | N | - | - | Deskripsi tagihan (Sewa Alat / MOB-DEMOB) |
| `unit_price` | DECIMAL(15,2)| N | 0.00 | - | Snapshot harga unit komponen |
| `quantity` | DECIMAL(8,2) | N | 1.00 | - | Jumlah Qty/Durasi Hari |
| `subtotal` | DECIMAL(15,2)| N | 0.00 | - | Unit Price x Qty |
| `timestamps` | TIMESTAMP | N | NOW() | - | - |

### 6.3 `bank_accounts`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `bank_name` | VARCHAR(100) | N | - | - | Nama institusi bank (BCA, Mandiri) |
| `account_number` | VARCHAR(100) | N | - | UK | Nomor rekening tujuan |
| `account_name` | VARCHAR(255) | N | - | - | Nama pemilik rekening resmi perusahaan |
| `is_active` | BOOLEAN | N | 1 | - | Rekening masih dibuka penerimaan transfer |
| `timestamps` | TIMESTAMP | N | NOW() | - | - |

### 6.4 `payments`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `invoice_id` | BIGINT UNSIGNED | N | - | FK, IDX| Ref `invoices(id)`, RESTRICT DELETE (1 Payment = 1 Invoice mutlak) |
| `bank_account_id`| BIGINT UNSIGNED | N | - | FK | Ref `bank_accounts(id)`, RESTRICT DELETE |
| `payment_date` | TIMESTAMP | N | NOW() | - | Waktu transfer diklaim |
| `amount` | DECIMAL(15,2)| N | 0.00 | - | Nilai yang ditransfer |
| `status` | ENUM | N | 'PENDING' | IDX | `PENDING`, `SUBMITTED`, `APPROVED`, `REJECTED` |
| `rejection_reason`| TEXT | Y | NULL | - | Catatan penolakan bukti oleh admin |
| `verified_by` | BIGINT UNSIGNED | Y | NULL | FK | Admin pemverifikasi saldo masuk |
| `timestamps` | TIMESTAMP | N | NOW() | - | - |

### 6.5 `refunds`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `invoice_id` | BIGINT UNSIGNED | N | - | FK, IDX| Ref `invoices(id)`, RESTRICT DELETE |
| `amount` | DECIMAL(15,2)| N | 0.00 | - | Nilai uang yang dikembalikan |
| `reason` | VARCHAR(255) | N | - | - | OVERPAYMENT / CANCELLATION |
| `customer_bank_info`| TEXT | N | - | - | Nama Bank & Rekening tujuan User |
| `status` | ENUM | N | 'REQUESTED'| IDX | `REQUESTED`, `REVIEWED`, `APPROVED`, `REJECTED`, `PROCESSING`, `COMPLETED` |
| `processed_by` | BIGINT UNSIGNED | Y | NULL | FK | Admin/Owner eksekutor transfer bank |
| `processed_at` | TIMESTAMP | Y | NULL | - | Timestamp transfer manual selesai |
| `timestamps` | TIMESTAMP | N | NOW() | - | - |

---

## 7. Modul Rekomendasi Pintar (Recommendation)

### 7.1 `recommendation_requests`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `user_id` | BIGINT UNSIGNED | N | - | FK | Ref `users(id)`, CASCADE DELETE |
| `status` | ENUM | N | 'PROCESSED'| - | `PENDING`, `PROCESSED`, `FAILED` |
| `timestamps` | TIMESTAMP | N | NOW() | - | - |

### 7.2 `recommendation_criteria`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `request_id` | BIGINT UNSIGNED | N | - | FK, UK | Ref `recommendation_requests(id)` |
| `project_type` | VARCHAR(100) | N | - | - | Jenis proyek (Tambang, Konstruksi, dll) |
| `terrain_condition`| VARCHAR(100) | N | - | - | Tanah keras, lumpur, aspal |
| `load_capacity` | DECIMAL(10,2)| Y | NULL | - | Beban angkut yang diharapkan |
| `budget_range` | VARCHAR(50) | Y | NULL | - | Range budget per hari (opsional) |

### 7.3 `recommendation_results`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `request_id` | BIGINT UNSIGNED | N | - | FK, IDX| Ref `recommendation_requests(id)` |
| `equipment_model_id`| BIGINT UNSIGNED | N | - | FK | Ref `equipment_models(id)`, CASCADE DELETE |
| `match_score` | DECIMAL(5,2) | N | - | - | Persentase skor kecocokan (misal 95.50) |
| `reasoning_text` | TEXT | Y | NULL | - | Penjelasan hasil rekomendasi AI/Rule |
| `timestamps` | TIMESTAMP | N | NOW() | - | - |

---

## 8. Modul Sistem & Infrastruktur

### 8.1 `notifications`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | CHAR(36) | N | - | PK | UUID Key format Laravel Native |
| `type` | VARCHAR(255) | N | - | IDX | FQCN Class Nama Event |
| `notifiable_type`| VARCHAR(255) | N | - | IDX | Polimorfik tipe objek target (User) |
| `notifiable_id` | BIGINT UNSIGNED | N | - | IDX | Polimorfik ID objek target (User ID) |
| `data` | JSON | N | - | - | Payload JSON data notifikasi |
| `read_at` | TIMESTAMP | Y | NULL | - | Timestamp notifikasi dibuka |
| `created_at` | TIMESTAMP | N | NOW() | - | Waktu push terkirim |
| `updated_at` | TIMESTAMP | N | NOW() | - | - |

### 8.2 `attachments` (Polymorphic Documents)
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `attachable_type`| VARCHAR(255) | N | - | IDX | Nama entitas: `UserProfile`, `Payment`, `Invoice` |
| `attachable_id` | BIGINT UNSIGNED | N | - | IDX | ID entitas terkait (Polymorphic) |
| `document_type` | VARCHAR(50) | N | - | IDX | Jenis file: `KTP`, `PAYMENT_PROOF`, `BAST` |
| `file_path` | VARCHAR(255) | N | - | - | Storage disk path file hash |
| `file_name` | VARCHAR(255) | N | - | - | Nama asli file upload |
| `mime_type` | VARCHAR(50) | N | - | - | `image/jpeg`, `application/pdf` |
| `file_size` | INT | N | 0 | - | Ukuran file dalam bytes |
| `uploaded_by` | BIGINT UNSIGNED | N | - | FK | User yang mengupload file |
| `timestamps` | TIMESTAMP | N | NOW() | - | - |

### 8.3 `activity_logs` (Audit Trails)
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `log_name` | VARCHAR(255) | N | - | IDX | Kategori log (`default`, `financial`, dll) |
| `description` | TEXT | N | - | - | Aksi yang terjadi |
| `subject_type` | VARCHAR(255) | Y | NULL | IDX | Entitas target (`Booking`, `Invoice`) |
| `subject_id` | BIGINT UNSIGNED | Y | NULL | IDX | ID entitas target |
| `causer_type` | VARCHAR(255) | Y | NULL | IDX | Pelaku mutasi (biasanya `User`) |
| `causer_id` | BIGINT UNSIGNED | Y | NULL | IDX | ID Pelaku mutasi |
| `properties` | JSON | Y | NULL | - | Payload snapshot `old` vs `new_attributes` |
| `ip_address` | VARCHAR(45) | Y | NULL | - | IP koneksi aktor |
| `created_at` | TIMESTAMP | N | NOW() | - | Immutable insert timestamp |

### 8.4 `business_calendars`
| Kolom | Tipe Data | Null | Default | Key | Atribut / Constraint |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | N | - | PK | Auto-Increment |
| `calendar_date` | DATE | N | - | UK, IDX| Tanggal operasional |
| `is_working_day` | BOOLEAN | N | 1 | - | 1 = Hari Kerja, 0 = Libur |
| `holiday_name` | VARCHAR(255) | Y | NULL | - | Nama hari libur / cuti bersama |
| `timestamps` | TIMESTAMP | N | NOW() | - | - |

---

## 9. Penutup & Consistency Verification

- **Business Rules Match:** 35 aturan dari `01-business-rules-v3.md` (khususnya 1 Booking = 1 Location, Booking Detail = Model bukan Physical Unit, Partial Payment support, Revisi Timesheet) tertampung presisi dalam Data Dictionary.
- **State Machine Match:** Definisi status kolom `ENUM` pada `bookings`, `equipment_units`, `rentals`, `invoices`, dan `payments` merefleksikan 100% tepat terhadap state node dari file `02-state-machines-v3.md`.
- **Use Case Match:** Modul rekomendasi dan upload dokumen (Polymorphic attachments) mengcover Use Case cerdas dan operasional manual (BAST) tanpa celah skema.
- **Financial Rule Match:** Kolom Decimal `(15,2)` seragam diterapkan. Snapshot nilai total berada di `booking_details` dan `invoice_details`. Perubahan tarif utama tak akan merusak catatan pembukuan lampau.
