# 05 — Return & Inspection (Phase 9E)

Mengelola penyelesaian rental: pengembalian unit ke pool dan keputusan kondisi oleh Admin.

## State Machine

Rental (nilai enum `RentalStatus` dipetakan dari prinsip flow):

| Langkah | State Rental | State Unit | Makna |
|---|---|---|---|
| `POST /rentals/{id}/return` | ASSIGNED → … → **ONGOING** → **DEMOBILIZING** | `DEMOBILIZING` | Pengembalian tercatat, unit kembali ke pool namun **belum siap digunakan** |
| `POST /rentals/{id}/inspect` | **DEMOBILIZING** → **RETURN_INSPECTED** | `RETURN_INSPECTION` | Unit masuk pemeriksaan kondisi (Admin) |
| `POST /rentals/{id}/ready` | **RETURN_INSPECTED** → **COMPLETED** | lihat keputusan | Hasil inspeksi menentukan nasib unit |

Nilai enum memakai istilah domain (`DEMOBILIZING`/`RETURN_INSPECTED`) yang mewakili
konsep *RETURNING*/*INSPECTION* pada PRD.

> Catatan: `DEMOBILIZING` melambangkan RETURNING, `RETURN_INSPECTED` melambangkan INSPECTION.

## Keputusan Inspeksi (`InspectRentalAction`)

Body `POST /rentals/{id}/ready`:

```json
{
  "result": "READY | MAINTENANCE | DAMAGED",
  "condition_notes": "… (opsional)"
}
```

| `result` | State rental | State unit | Keterangan |
|---|---|---|---|
| `READY` | `COMPLETED` | `AVAILABLE` | Unit layak pakai, dilepas kembali ke pool |
| `MAINTENANCE` | `COMPLETED` | `MAINTENANCE` | Unit perlu perawatan sebelum reuse |
| `DAMAGED` | `COMPLETED` | `MAINTENANCE` | Kerusakan tercatat; **tanpa tagihan otomatis** |

## Aturan yang Ditegakkan

- **Return selalu tercatat**: transisi `ONGOING → DEMOBILIZING` diaudit (`RENTAL_DEMOBILIZING`) dan men-set status unit `DEMOBILIZING`.
- **Unit tidak langsung AVAILABLE setelah return**: hanya `READY` dari hasil inspeksi yang melepas unit ke `AVAILABLE`. Status `DEMOBILIZING`/`RETURN_INSPECTION` tidak dapat dipakai pada penyewaan baru.
- **Inspeksi dilakukan Admin**: endpoint di bawah otorisasi `RentalPolicy::operate` (ADMIN/OWNER); USER ditolak 403.
- **Histori inspeksi tersimpan**: per `rental_details` tersimpan `inspection_result`, `condition_notes`, `checked_out_at`; riwayat lengkap tercatat di `completed_at` rental dan audit log (`RENTAL_INSPECTION`).
- **Damage cost bukan charge otomatis**: `DAMAGED` hanya mencatat kondisi + memindah unit ke `MAINTENANCE`. Tidak ada baris invoice/charge dibuat. (*ponytail:* billing damage eksplisit ditambahkan bila PRD memintanya.)

## Validasi

- `result` wajib dan harus ∈ {READY, MAINTENANCE, DAMAGED} → 422 bila invalid.
- `ready` hanya boleh saat rental `RETURN_INSPECTED`; selain itu → `409 INVALID_STATE_TRANSITION`.
- `condition_notes` opsional, max 2000 karakter.

## Endpoints

```
POST /api/v1/rentals/{rental}/return     admin   ONGOING → DEMOBILIZING (return tercatat)
POST /api/v1/rentals/{rental}/inspect    admin   DEMOBILIZING → RETURN_INSPECTED
POST /api/v1/rentals/{rental}/ready      admin   RETURN_INSPECTED → COMPLETED + keputusan unit
GET  /api/v1/rentals                     admin/user  list (filter status)
```

## Frontend (Admin)

`AdminRentalsPage` (`/admin/rentals`):

- Tombol kontekstual per status: Dispatch → Arrival → Start → **Catat Pengembalian (Return)** → **Mulai Inspeksi (Inspection)**.
- Saat `RETURN_INSPECTED`: tombol **Isi Hasil Inspeksi** membuka modal pilihan kondisi (`READY`/`MAINTENANCE`/`DAMAGED`) + catatan; menampilkan peringatan bahwa unit hanya kembali tersedia bila `READY`.
- Kartu unit menampilkan hasil inspeksi (`READY` hijau, `MAINTENANCE` amber, `DAMAGED` merah).

## Files

- `app/Actions/Rental/TransitionRentalAction.php` — map transisi (hapus edge `RETURN_INSPECTED → COMPLETED`; finalisasi kini lewat inspeksi).
- `app/Actions/Rental/InspectRentalAction.php` — finalisasi + keputusan kondisi unit.
- `app/Http/Requests/Rental/ReadyRentalRequest.php`, `app/Http/Controllers/Api/V1/RentalController.php::ready`, `routes/api.php`.
- `database/migrations/2026_09_25_000007_add_inspection_to_rental_details_table.php` — `inspection_result`, `checked_out_at`.
- `tests/Feature/Rental/RentalReturnInspectionTest.php` (7 kasus).