# 05 — Refund, Outstanding & Notification UI (Phase 11F)

Antarmuka refund, outstanding, dan notifikasi untuk USER, ADMIN, dan OWNER.
Komponen reusable (Card/Badge/Button/Input/Textarea/Select/ConfirmDialog/
Skeleton/EmptyState/Alert), responsive, dengan guard eksposur finansial.

## User (`/app`)

- **`/app/refunds` (UserRefundsPage)** — status refund, amount, reason,
  alasan persetujuan/penolakan, timestamp (approved/processed/completed),
  referensi transfer, progress bar `Diajukan→Disetujui→Diproses→Selesai`.
- **`/app/outstanding` (UserOutstandingPage)** — ringkasan (total outstanding,
  invoice terbuka, overdue), badge eligible, saldo per invoice (deadline/balas
  semata-mata data sendiri via `/finance/outstanding/me`).
- **`/app/notifications` (UserNotificationsPage)** — centernotifikasi sendiri:
  unread count, mark-read per item (klik), mark-all; event+related entity.
  Hanya notifikasi miliknya (API scoped).

## Admin & Owner (`/admin`)

- **`/admin/refunds` (AdminRefundsPage)** — antrean refund (filter status).
  Aksi berbasis role (`useAuth`):
  - PENDING + **OWNER** → **Setujui** (alasan opsional);
  - APPROVED + ADMIN → **Proses** (rekening tujuan + referensi transfer);
  - PROCESSING + ADMIN → **Selesaikan** (upload bukti transfer wajib, PNG/JPG/PDF);
  - PENDING/APPROVED/PROCESSING + ADMIN → **Gagalkan** (alasan wajib).
  Tampilkan status/amount/reason/bank/ref/failure.
- **`/admin/outstanding` (AdminOutstandingPage)** — rekap per-pelanggan
  (nama, eligible, jumlah invoice terbuka/overdue, total), ekspansi per invoice
  (saldo). Data via `/finance/outstanding` (admin/owner).
- **`/admin/notifications` (AdminNotificationsPage)** — monitor delivery
  (WhatsApp) `SENT/FAILED/SKIPPED/QUEUED`, event, recipient, error, waktu —
  untuk audit.

## Guard Data Finansial

- Endpoint scoped: user hanya dapat data sendiri (outstanding/refund/
  notifications); rekap & monitoring admin/owner-only (403 utk user).
- Refund action per role — approve owner; process/complete/fail admin.
- Tanpa duplicate request: halaman memuat sekali per mount `Promise.all`.

## Backend Pendukung

- `RefundController::index` — owner kini ikut memonitor semua refund.
- `NotificationController::deliveries` — `GET /notifications/deliveries`
  (admin/owner), paginated.

## Test Result

**Frontend 110 passed (27 files)** (+8):
- `RefundUi` (4): user melihat status/amount/reason; owner approve; owner tak
  bisa process/fail; admin complete dengan bukti.
- `OutstandingNotificationsUi` (4): summary & saldo per invoice; admin
  outstanding (eligible + expand); notification center (unread, mark-read,
  mark-all); monitor delivery (SKIPPED + reason).
Build `tsc+vite` sukses; lint bersih.

**Backend 400 passed (2113 assertions)**; Pint clean.

## Files

- `frontend/src/features/refund/{pages/UserRefundsPage,AdminRefundsPage,UserOutstandingPage,AdminOutstandingPage,services/refundService}`
- `frontend/src/features/notification/{pages/UserNotificationsPage,AdminNotificationsPage,services/notificationService}`
- `frontend/src/types/{refund,notification}.ts`
- `frontend/src/routes/AppRoutes.tsx`, `components/layout/{UserLayout,AdminLayout}.tsx`
- backend: `RefundController` (owner scope), `NotificationController::deliveries`, routes
- Test: `RefundUi.test.tsx`, `OutstandingNotificationsUi.test.tsx`