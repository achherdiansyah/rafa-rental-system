# Activity Diagram — Penugasan & Akses Operator (Revisi Poin 4)

Dasar: `AssignRentalOperatorAction`, `AdminRentalOperatorController`, `OperatorAssignmentController`, `RentalOperatorAssignmentPolicy`, `rental_operator_assignments`.

```mermaid
flowchart TD
  A([Mulai]) --> B[Booking CONFIRMED<br/>payment_met_at terisi]
  B --> C[ADMIN tugaskan unit fisik<br/>booking_unit_assignments is_current = true]
  C --> D[Rental otomatis dibuat<br/>RentalDetail per unit]
  D --> E[ADMIN pilih Operator aktif<br/>role OPERATOR + is_active]
  E --> F{Detail rental sudah punya<br/>Operator ACTIVE?}
  F -- Ya --> G[/Tolak: gunakan pergantian Operator/]
  F -- Tidak --> H[Lock rental_detail + cek payment_met_at]
  H --> I[Simpan assignment ACTIVE<br/>rental_id, rental_detail_id, operator_user_id, assigned_by, started_at]
  I --> J[Audit OPERATOR_ASSIGNED + notifikasi]
  J --> K[OPERATOR login]
  K --> L[Akses hanya assignment miliknya<br/>status ACTIVE]
  L --> M[Tampilkan unit + alamat/kota/koordinat proyek<br/>via rental -> booking -> project_location]
  M --> N{Koordinat tersedia?}
  N -- Ya --> O[Tautan navigasi & titik peta eksternal]
  N -- Tidak --> P[Pesan: koordinat belum tersedia<br/>tanpa titik palsu]
  O --> Z([Selesai])
  P --> Z

  %% Pergantian / pengakhiran
  I -.-> Q[ADMIN ganti Operator<br/>alasan wajib min. 5 karakter]
  Q --> R[Assignment lama ENDED<br/>ended_at + end_reason + ended_by]
  R --> S[Assignment baru ACTIVE]
  S --> T[Operator lama kehilangan akses<br/>Operator baru memperoleh akses]
  T --> Z

  I -.-> U[ADMIN akhiri penugasan<br/>alasan wajib]
  U --> V[Assignment ENDED + audit]
  V --> Z
```

Aturan keamanan:
- Endpoint `GET /operator/*` memakai middleware `role:OPERATOR`.
- Detail assignment memakai `RentalOperatorAssignmentPolicy::view` = pemilik **dan** `status=ACTIVE`; menukar ID assignment/rental/project_location Operator lain → 403.
- Daftar & dashboard selalu `where('operator_user_id', auth id)`.
- Operator tidak dapat input/validasi Timesheet (`TimesheetPolicy::create/validate` = ADMIN only).
