# Activity Diagram — Validasi Supervisor (Revisi Poin 4)

Dasar: `SupervisorBookingController`, `ValidateBookingAction`, `ReturnBookingAction`, `UpdateBookingAction`, `SubmitBookingAction`, `BookingPolicy`.

```mermaid
flowchart TD
  A([Mulai]) --> B[USER submit booking<br/>status PENDING_APPROVAL]
  B --> C[SUPERVISOR buka antrean validasi<br/>hanya PENDING_APPROVAL]
  C --> D[Periksa data booking:<br/>pelanggan, lokasi proyek, armada, periode]

  D --> E{Data sesuai?}
  E -- Tidak --> F[Isi catatan perbaikan wajib<br/>min. 5 karakter]
  F --> G[supervisor_decision = REVISION<br/>supervisor_validated_at = null]
  G --> H[USER perbaiki project_location/tanggal<br/>PUT /bookings/{id}]
  H --> I[USER ajukan ulang<br/>decision direset null]
  I --> C

  E -- Ya --> J[Catatan validasi opsional]
  J --> K[supervisor_decision = VALIDATED<br/>supervisor_user_id + supervisor_validated_at]
  K --> L[Booking masuk antrean Admin]
  L --> M{ADMIN approve/reject}
  M -- Approve --> N[APPROVED + Invoice MOB/DEMOB]
  M -- Reject --> O[REJECTED + alasan]
  N --> Z([Selesai])
  O --> Z
```

Catatan:
- Supervisor **tidak** dapat approve/reject booking (`BookingPolicy::approve/reject` = ADMIN/OWNER).
- Supervisor hanya melihat detail booking berstatus `PENDING_APPROVAL` (`BookingPolicy::view`); booking `APPROVED` ke atas → 403.
- Validasi/penolakan tetap tercatat di audit log dan memicu notifikasi user.
