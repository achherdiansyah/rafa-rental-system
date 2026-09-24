# Database Conventions & Migration Standards - RAFA Rental System

Dokumentasi konvensi basis data dan standar penulisan migration Laravel untuk RAFA Rental System (Phase 3A).

---

## 1. Sumber Kebenaran Tunggal (Source of Truth)

- Sumber kebenaran skema data merujuk mutlak pada:
  - `docs/architecture/phase-1/04-erd-v3.md` (Relasi, Kardinalitas, Constraint)
  - `docs/architecture/phase-1/05-data-dictionary-v3.md` (Kamus Kolom, Tipe Data, Default, Nullable, Index)
- Dilarang membuat tabel, kolom, relasi, atau status ENUM baru di luar yang telah disetujui di Phase 1.

---

## 2. Standar Tipe Data (Datatype Standards)

### 2.1 Nilai Moneter (Money & Currency)
- **WAJIB menggunakan `DECIMAL(15, 2)`** untuk semua kolom harga, tarif sewa, subtotal, denda, pajak, dan pembayaran.
- **DILARANG** menggunakan `FLOAT` atau `DOUBLE` untuk nominal uang untuk menghindari kesalahan pembulatan (*rounding errors*).
- Contoh kolom: `base_rate`, `rental_rate_snapshot`, `unit_price`, `subtotal`, `grand_total`, `paid_amount`, `overpayment_amount`, `amount`.

### 2.2 Angka Kuantitatif & HM (Hour Meter)
- `DECIMAL(10, 2)` untuk angka Hour Meter akumulatif dan harian (`last_hour_meter`, `check_in_hm`, `start_hm`, `end_hm`).
- `DECIMAL(8, 2)` untuk jam kerja dan kuantitas durasi (`total_work_hours`, `standby_hours`, `breakdown_hours`, `capacity_value`, `quantity`).
- `DECIMAL(10, 8)` dan `DECIMAL(11, 8)` untuk koordinat geografis (`latitude`, `longitude`).

### 2.3 Tanggal & Waktu (Date vs Timestamp)
- `DATE` untuk tanggal kalender murni tanpa komponen jam (`start_date`, `end_date`, `report_date`, `calendar_date`, `effective_date`).
- `TIMESTAMP` (disimpan dalam UTC) untuk pencatatan waktu event/audit (`due_at`, `started_at`, `completed_at`, `created_at`, `updated_at`, `deleted_at`).

### 2.4 Status & Enum
- Disimpan sebagai string `VARCHAR(20)` atau `VARCHAR(50)` dengan representasi nilai UPPERCASE SNAKE_CASE yang di-*cast* otomatis ke PHP Backed Enum di level model.
- Contoh: `status` ('DRAFT', 'PENDING_APPROVAL', 'APPROVED', 'DISPATCHED', dll).

### 2.5 Boolean
- Kolom boolean menggunakan tipe data `BOOLEAN` (TINYINT(1)) dengan nilai default `0` (false) atau `1` (true).
- Naming: `is_active`, `is_all_in`, `is_current`, `is_working_day`.

---

## 3. Konvensi Penamaan (Naming Conventions)

| Komponen | Format | Contoh |
|---|---|---|
| Nama Tabel | plural, snake_case | `equipment_models`, `booking_unit_assignments` |
| Primary Key | `id` (bigIncrements) | `$table->id()` |
| Foreign Key | `{singular_table}_id` | `booking_id`, `equipment_model_id` |
| Kolom Timestamp | snake_case, lampau/adverbial | `created_at`, `assigned_at`, `processed_at`, `due_at` |
| Soft Delete | `deleted_at` | `$table->softDeletes()` |
| Index | `{table}_{column}_index` | `$table->index('status')` |
| Unique Constraint | `{table}_{column}_unique` | `$table->unique('booking_code')` |

---

## 4. Integritas Relasional & Delete Behavior

### 4.1 `RESTRICT` (Proteksi Data Bisnis)
Digunakan pada relasi penting untuk mencegah penghapusan data induk yang masih memiliki anak:
- `bookings` -> `users` (`restrictOnDelete()`)
- `bookings` -> `project_locations` (`restrictOnDelete()`)
- `booking_details` -> `equipment_models` (`restrictOnDelete()`)
- `booking_unit_assignments` -> `equipment_units` (`restrictOnDelete()`)
- `invoices` -> `bookings` (`restrictOnDelete()`)
- `payments` -> `invoices` (`restrictOnDelete()`)
- `payments` -> `bank_accounts` (`restrictOnDelete()`)
- `refunds` -> `invoices` (`restrictOnDelete()`)

### 4.2 `CASCADE` (Relasi Komposisi / Dependent Child)
Digunakan hanya pada child record yang tidak memiliki makna mandiri jika parent dihapus:
- `customer_profiles` -> `users` (`cascadeOnDelete()`)
- `cart_items` -> `carts` (`cascadeOnDelete()`)
- `booking_details` -> `bookings` (`cascadeOnDelete()`)
- `invoice_details` -> `invoices` (`cascadeOnDelete()`)
- `timesheet_revisions` -> `timesheets` (`cascadeOnDelete()`)
- `equipment_price_versions` -> `equipment_prices` (`cascadeOnDelete()`)

---

## 5. Entitas dengan Soft Delete

Soft delete (`deleted_at`) diterapkan **HANYA** pada entitas master dan entitas transaksi tingkat atas yang perlu dipulihkan jika tidak sengaja diarsipkan:
1. `users`
2. `project_locations`
3. `equipment_types`
4. `equipment_models`
5. `equipment_units`
6. `bookings`
7. `invoices`

**DILARANG MENGGUNAKAN SOFT DELETE PADA:**
- `payments` (Financial ledger - mutasi harus dicatat/ditolak via status)
- `refunds` (Financial ledger)
- `activity_logs` (Audit log append-only)
- `timesheet_revisions` (Historical version append-only)
- `booking_unit_assignments` (Historical assignment tracking via `is_current`)

---

## 6. Pola Imutabilitas Data Historis (Historical Snapshot Pattern)

1. **Harga Transaksi:** `booking_details` dan `invoice_details` menyimpan nilai harga dalam kolom snapshot (`rental_rate_snapshot`, `unit_price`). Nilai ini tidak boleh di-update jika harga master di `equipment_prices` berubah.
2. **Revisi Timesheet:** Tabel `timesheet_revisions` menyimpan snapshot versi lama (`old_start_hm`, `old_end_hm`, `revision_reason`, `revised_by`).
3. **Riwayat Unit Assignment:** Tabel `booking_unit_assignments` menandai record lama dengan `is_current = 0` dan `status = 'REPLACED'` jika unit fisik diganti, dan membuat baris assignment baru untuk unit pengganti.

---

## 7. Standar Penulisan Migration Laravel

1. Satu migration memiliki satu tanggung jawab jelas.
2. Seluruh migration wajib memiliki method `up()` dan `down()` yang simetris sehingga dapat di-rollback tanpa error.
3. Foreign key constraints dibuat menggunakan method bawaan:
   ```php
   $table->foreignId('user_id')->constrained()->restrictOnDelete();
   ```
4. Indeks ditambahkan pada kolom yang sering dicari atau difilter (`status`, `booking_code`, `invoice_number`, `city`, tanggal).
