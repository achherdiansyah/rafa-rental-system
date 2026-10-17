# Activity Diagram — Booking sampai Rental Selesai (Revisi Poin 4)

Dasar alur: `SubmitBookingAction`, `ValidateBookingAction`, `ReturnBookingAction`, `UpdateBookingAction`, `ApproveBookingAction`, `RejectBookingAction`, `VerifyPaymentAction`, `AssignBookingUnitsAction`, `AssignRentalOperatorAction`, `TransitionRentalAction`, `CreateTimesheetAction`, `CreateDailyInvoiceAction`, `InspectRentalAction`, scheduler `bookings:expire` / `invoices:expire`.

## Diagram utama (Mermaid)

```mermaid
flowchart TD
  A([Mulai]) --> B[USER membuat booking dari keranjang<br/>DRAFT]
  B --> C{Profil terverifikasi<br/>dan tersedia pada periode?}
  C -- Tidak --> C1[/Tolak: akun belum terverifikasi<br/>atau armada tidak tersedia/] --> Z([Selesai])
  C -- Ya --> D[USER submit booking<br/>PENDING_APPROVAL]
  D --> E[SUPERVISOR meninjau pengajuan]
  E --> F{Keputusan Supervisor}
  F -- Kembalikan --> F1[Simpan catatan REVISION<br/>supervisor_decision = REVISION]
  F1 --> F2[USER perbaiki lokasi proyek/tanggal<br/>lalu ajukan ulang]
  F2 --> E
  F -- Validasi --> G[supervisor_decision = VALIDATED<br/>supervisor_validated_at terisi]

  G --> H{ADMIN persetujuan akhir}
  H -- Tolak --> H1[REJECTED + alasan wajib] --> Z
  H -- Setujui --> I[APPROVED<br/>terbit Invoice MOB/DEMOB<br/>deadline 24 jam]

  I --> J{USER membayar?}
  J -- Lewat deadline --> J1[Scheduler: EXPIRED<br/>slot armada dilepas] --> Z
  J -- Upload bukti --> K[Payment SUBMITTED]
  K --> L{ADMIN verifikasi pembayaran}
  L -- Tolak --> L1[Payment REJECTED + alasan<br/>USER dapat upload ulang] --> K
  L -- Setujui --> M{Invoice MOB/DEMOB lunas?}
  M -- Belum / parsial --> M2[Invoice PARTIALLY_PAID<br/>booking tetap APPROVED/PAYMENT_PENDING] --> J
  M -- Lunas / overpaid --> M3[booking CONFIRMED<br/>payment_met_at terisi]

  M3 --> N[ADMIN tugaskan unit fisik<br/>hanya setelah pembayaran terverifikasi]
  N --> O[Rental dibuat otomatis<br/>status ASSIGNED]
  O --> P[ADMIN tugaskan Operator per unit<br/>satu Operator aktif per rental_detail]
  P --> Q[ADMIN dispatch<br/>Rental DISPATCHED, unit MOBILIZING]
  Q --> R[ADMIN konfirmasi arrival<br/>Rental ARRIVED, unit ON_SITE]
  R --> S[ADMIN konfirmasi mulai<br/>Rental ONGOING, started_at]

  S --> T[ADMIN input Timesheet harian<br/>berdasarkan catatan kertas Operator]
  T --> U[Timesheet APPROVED +<br/>Daily Work Invoice otomatis<br/>1-8 jam normal, >8 jam overtime]
  U --> V{USER membayar tagihan harian?}
  V -- Belum --> V1[Outstanding bertambah<br/>maksimum 3 hari unpaid] --> V
  V -- Upload bukti --> V2[ADMIN verifikasi pembayaran Daily Work<br/>PAID / PARTIALLY_PAID / OVERPAID]
  V2 --> W[ADMIN catat pengembalian<br/>Rental DEMOBILIZING, unit DEMOBILIZING]
  U -.-> W2[Pengembalian dapat dicatat saat pekerjaan selesai<br/>outstanding tetap dilacak] -.-> W
  W --> X[ADMIN mulai inspeksi<br/>Rental RETURN_INSPECTED]
  X --> Y{Hasil inspeksi}
  Y -- READY --> Y1[Unit AVAILABLE kembali]
  Y -- MAINTENANCE --> Y2[Unit MAINTENANCE]
  Y -- DAMAGED --> Y3[Unit MAINTENANCE<br/>tercatat damaged, tanpa tagihan otomatis]
  Y1 --> Z([Rental COMPLETED])
  Y2 --> Z
  Y3 --> Z

  %% Jalur operasional tambahan
  S -.-> S1[ADMIN ganti unit / ganti Operator<br/>riwayat tetap tersimpan]
  S1 -.-> S
  M3 -.-> M1[USER batal/reschedule sebelum dispatch<br/>refund manual bila ada pembayaran]
```

## Penjelasan singkat
- **Validasi Supervisor adalah gerbang wajib**: `ApproveBookingAction`/`RejectBookingAction` menolak (409) booking yang belum `VALIDATED`.
- **Invoice MOB/DEMOB** hanya terbit saat `APPROVED`; Total = MOB + DEMOB (bukan tarif sewa).
- **Pembayaran parsial tidak mengonfirmasi booking**: `CONFIRMED` hanya saat invoice MOB/DEMOB lunas/overpaid; parsial tetap `APPROVED`/`PAYMENT_PENDING`.
- **Penugasan unit dan Operator** di backend mensyaratkan `payment_met_at` (pembayaran terverifikasi) dan unit `is_current`.
- **Timesheet bukan milik Operator digital**: Admin input dari kertas operator; tersimpan langsung `APPROVED` dan memicu Daily Work Invoice.
- **Rental** berjalan `ASSIGNED → DISPATCHED → ARRIVED → ONGOING → DEMOBILIZING → RETURN_INSPECTED`, lalu selesai lewat inspeksi (bukan transisi `COMPLETED` langsung).
- **Booking expired / ditolak** adalah terminal dan melepaskan slot armada.
- **Payment ditolak** tidak menghapus data; user dapat upload bukti baru dan invoice deadline tidak di-reset.
- **Daily Work Invoice** diverifikasi Admin seperti invoice lain. Pengembalian unit dapat dicatat saat pekerjaan selesai; outstanding harian tetap dipantau (`max_outstanding_days=3`) dan tidak menjadi gate transisi rental di implementasi saat ini.
- **Inspeksi** adalah langkah penutup: `InspectRentalAction` mengubah rental menjadi `COMPLETED` dan menentukan unit `AVAILABLE` atau `MAINTENANCE`.

## Aturan yang diterapkan diagram
- MOB = jarak rute satu arah × tarif/km; DEMOB = jarak rute satu arah × tarif/km; Total MOB/DEMOB = MOB + DEMOB (snapshot pada `booking_details`).
- Tarif rental per jam **hanya** dipakai untuk Daily Work Invoice dari Timesheet aktual.
- Routing Mapbox **belum tervalidasi live** (token + data pool belum tersedia) — diagram menampilkan konfigurasi sebagai prasyarat, bukan hasil tervalidasi.
