# Spesifikasi Availability Engine (Phase 8E) - RAFA Rental System

Dokumentasi arsitektur mesin ketersediaan armada (*EquipmentAvailabilityService*) sebagai **single source of truth** untuk validasi kapasitas sewa, penjadwalan slot, dan pencegahan *double-booking*.

---

## 1. Prinsip Sumber Kebenaran (Source of Truth)

1. **MySQL Database = Satu-Satunya Kebenaran:** Seluruh perhitungan ketersediaan dibaca langsung dari basis data relasional MySQL 8.4. Tidak ada dependensi Redis (atau cache eksternal apa pun) untuk integritas transaksional.
2. **Tanpa Cache untuk Correctness:** Jika caching diterapkan di masa depan, cache hanya berperan sebagai *read-cache* yang dapat di-evict setiap saat tanpa risiko *overselling* atau *double-booking*.
3. **Dua Lapisan:** Lapisan *Model-Level* (kapasitas agregat untuk katalog/cart/checkout) dan lapisan *Physical-Unit Level* (alokasi unit fisik spesifik oleh Admin).

---

## 2. Operational Buffer

Buffer diterapkan simetris pada slot sewa yang disetujui serta pada jendela permintaan baru saat pengecekan overlap:

| Buffer | Default | Kapan Berlaku |
|---|---|---|
| **Mobilization** | `0` hari | Sebelum `start_date` (persiapan pool, loading, perjalanan). |
| **Extension / Demobilization** | `3` hari | Setelah `end_date` (masa tenggang perluasan & penarikan unit). |
| **Return / Inspection** | `2` hari | Setelah buffer extension (unit dalam `RETURN_INSPECTION`, dilarang langsung di-allocate). |

Konfigurasi tersentralisasi pada `config/availability.php` (`buffers.*`). Total *post-end buffer* default = **5 hari** — unit bebas dialokasikan mulai `end_date + 6` hari.

---

## 3. Status Booking & Unit yang Memengaruhi Kapasitas

### Committed Booking Statuses (memakan kapasitas)
`APPROVED`, `PAYMENT_PENDING`, `CONFIRMED`, `DISPATCHED`, `ARRIVED`, `ONGOING`.

### Status yang Melepas Kapasitas Langsung
`CANCELLED`, `REJECTED`, `EXPIRED` — slot dilepas kembali ke pool publik.

### Unit Fisik yang Tidak Dapat Dialokasikan
`DECOMMISSIONED`, `MAINTENANCE`, `RETURN_INSPECTION`.

---

## 4. Alur Deteksi Bentrok (Overlap Detection)

Untuk jendela permintaan `[reqStart, reqEnd]` dan slot komitmen `[bStart, bEnd]`, konflik terjadi jika memenuhi **kedua** kondisi berikut (buffer diterapkan pada kedua sisi):

```text
1. bStart - mob             <= reqEnd + ext + insp      (awal komitmen mulai lebih awal dari akhir permintaan)
2. bEnd + ext + insp        >= reqStart - mob           (akhir komitmen berbuffer melewati awal permintaan)
```

Implementasi SQL di `getAvailabilityMap()` dan `getCandidateUnits()`:

```sql
WHERE bd.start_date <= :reqEndPlusSpan
  AND DATE_ADD(bd.end_date, INTERVAL :extInsp DAY) >= :reqStartMinusMob
```

---

## 5. API Service (`EquipmentAvailabilityService`)

| Metode | Deskripsi |
|---|---|
| `getAvailabilityMap(modelIds, start?, end?)` | Peta `[model_id => kapasitas bebas]` via 2 query agregasi (tanpa N+1). |
| `getAvailableUnitsCount(model, start?, end?)` | Kapasitas bebas satu model. |
| `isModelAvailable(model, start?, end?)` | Boolean ketersediaan tingkat model. |
| `getCandidateUnits(modelId, start, end, limit?)` | Daftar unit fisik `AVAILABLE` yang bebas pada periode (tingkat unit). |
| `isUnitAvailable(unit, start, end)` | Boolean ketersediaan satu unit fisik. |
| `lockAvailableUnits(modelId, start, end, qty)` | **`SELECT ... FOR UPDATE`** dalam transaksi; mengembalikan unit terkunci untuk alokasi. Melempar `BusinessRuleException` bila kuota tidak cukup. |

### Pencegahan Double-Booking (Pessimistic Locking)
- `lockAvailableUnits()` mengeksekusi *pessimistic row lock* (`lockForUpdate`) pada baris unit kandidat.
- Transaksi bersamaan yang mencoba mengalokasikan unit yang sama akan **memblokir (antre)** sampai transaksi pertama *commit*/*rollback*.
- Setelah *commit*, status unit berubah menjadi `ASSIGNED` + terikat `booking_unit_assignments` `is_current = true` sehingga alokasi kedua ditolak.

---

## 6. Indeks Pendukung (Query Performance)

Migrasi `2026_09_25_000003_add_availability_indexes_table` menambahkan:
- `equipment_units(equipment_model_id, status)` — filter unit operasional per model.
- `booking_details(equipment_model_id, start_date, end_date)` — pencarian overlap rentang waktu.
- `booking_unit_assignments(equipment_unit_id, is_current)` — pengecekan assignment aktif per unit.

---

## 7. Hasil Pengujian (`EquipmentAvailabilityEngineTest`)

| Skenario | Hasil |
|---|---|
| Unit tersedia menjadi kandidat | **PASSED** |
| Booking *overlapping* mengurangi kapasitas | **PASSED** |
| Booking non-overlap tetap tersedia | **PASSED** |
| Bentrok *buffer* (mulai di dalam jendela 5 hari setelah sewa) diblokir | **PASSED** |
| Booking `EXPIRED` melepas kapasitas | **PASSED** |
| Unit `MAINTENANCE` / `RETURN_INSPECTION` / `DECOMMISSIONED` dieksklusi | **PASSED** |
| *Pessimistic lock* mencegah alokasi ganda dalam transaksi konkuren | **PASSED** |
| *Unit replacement* melepas unit lama (`is_current=false`) & mengalokasikan unit lain | **PASSED** |
| *Reschedule* menjalankan kalkulasi ulang & menolak bentrok | **PASSED** |

**Total Backend Suite:** 253 tests passed (913 assertions) • Laravel Pint 100% clean.