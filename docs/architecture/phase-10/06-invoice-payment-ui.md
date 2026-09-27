# 06 — Invoice & Payment UI (Phase 10F)

Antarmuka invoice & pembayaran pada portal USER dan ADMIN. Tidak ada UI refund
penuh.

## User (`/app/invoices` — UserInvoicesPage)

- **Invoice list/detail**: nomor, tipe, status (badge), total, dibayar, **saldo**,
  jatuh tempo; ekspansi menampilkan rincian line-item.
- **Payment history**: status tiap payment (`SUBMITTED / APPROVED / REJECTED`);
  payment ditolak menampilkan **alasan penolakan**.
- **Partial balance**: saldo menurun tiap payment di-approve, tetap `PARTIAL`
  sampai lunas.
- **Upload payment**: nominal, tanggal transfer, rekening tujuan (dari daftar
  bank RAFA), nama pengirim, reference, **bukti transfer** (dengan validasi
  client + server `FileSecurity`).
- **Overpayment notice**: invoice `OVERPAID` menampilkan kelebihan yang menunggu
  penyelesaian manual (refund belum otomatis).

## Admin

- **`/admin/invoices` (AdminInvoicesPage)**: list semua invoice + filter status,
  balance, indikator kelebihan bayar, deadline, dan aksi **Perpanjang Deadline**
  (0–168 jam, confirmation, audit).
- **`/admin/payments` (AdminPaymentsPage)**: **payment queue** — antrean
  SUBMITTED (filter APPROVED/REJECTED/semua), rincian (nominal, pengirim,
  reference, tanggal transfer), **lihat bukti** (private storage disajikan via
  `GET /payments/{id}/proof` dengan autentikasi, blob → pratinjau), **Setujui**
  (settlement, confirmation) dan **Tolak** (reason wajib ≥5, confirmation).

## Backend Pendukung

- `PaymentController::queue()` — `GET /api/v1/payments` (global, admin).
- `PaymentController::proof()` — `GET /api/v1/payments/{payment}/proof`
  (private storage, stream; akses owner/admin).
- `ExtendInvoiceDeadlineAction` — dicek 10E.

## UI Wajib

Responsive (grid `sm/lg`), skeleton loading, EmptyState, Alert error + toast,
validasi client&server, ConfirmDialog semua aksi komit, badge status, SPA tanpa
full page reload.

## Test Result

**Frontend — 102 passed (25 files)** (+3 file baru):
- `UserInvoicesPage` (3): render balance & overpayment notice; riwayat payment +
  reason ditolak; validasi & submit upload (bukti wajib → inline error lalu
  API dipanggil dengan payload betul).
- `AdminPaymentsPage` (3): queue tampil; approve via konfirmasi; reject wajib
  reason (validasi inline + API dipanggil).
- `AdminInvoicesPage` (2): render invoice + deadline; extend deadline 48 jam.
- Build `tsc -b + vite` sukses; lint tanpa error.

**Backend — 371 passed (1906 assertions)**, termasuk endpoint baru:
- queue global 403 untuk user (admin lihat 1); proof image dipindai; owner
  boleh lihat bukti payment-nya sendiri.
- Pint clean.

## Files

- `frontend/src/features/invoice/{pages/UserInvoicesPage,AdminInvoicesPage,AdminPaymentsPage,services/invoiceService}`
- `frontend/src/types/invoice.ts`
- `frontend/src/routes/AppRoutes.tsx`, `components/layout/AdminLayout.tsx`
- `backend/app/Http/Controllers/Api/V1/PaymentController.php` (queue, proof)
- Test: `frontend/src/features/invoice/*.test.tsx`