# 02 — Invoice Lifecycle (Phase 10B)

Penerbitan & siklus hidup invoice, snapshot detail, penomoran, deadline 24 jam,
dokumen PDF. **Payment verification ditunda (10D).**

## Status Invoice

```text
DRAFT → (issue) → ISSUED → (mark-unpaid) → UNPAID
                                                 │ (invoice:expire, due_at lewat)
                                                 ▼
                                      OVERDUE  (PRD: EXPIRED)
DRAFT / ISSUED / UNPAID / OVERDUE → (void) → CANCELLED (riwayat tetap tersimpan)

Payment-flow (10D, belum di sini): UNPAID → PARTIALLY_PAID → PAID | OVERPAID
```

| Status | Makna |
|---|---|
| `DRAFT` | Dibuat, belum diterbitkan (issued_at/due_at kosong) |
| `ISSUED` | Diterbitkan; `issued_at` terisi, `due_at = issued_at + 24 jam` |
| `UNPAID` | Jendela pembayaran dimulai (transisi dari ISSUED) |
| `PARTIALLY_PAID` / `PAID` / `OVERPAID` | Dikenalkan di 10D (payment verification) |
| `OVERDUE` | Deadline 24 jam lewat tanpa pembayaran (cron `invoices:expire`) |
| `CANCELLED` | Dibatalkan (void); baris & riwayat keuangan TIDAK dihapus |

## Tipe Invoice

`DAILY_WORK`, `MOB_DEMOB`, `ADJUSTMENT`, `OTHER`.
- `DAILY_WORK`: jam aktual (timesheet APPROVED) × tarif snapshot per unit.
- `MOB_DEMOB`: biaya per unit fisik; MOB dan DEMOB bisa berbeda; satu baris detail
  masing-masing per unit.
- `ADJUSTMENT` / `OTHER`: nilai enum disiapkan untuk input manual admin (belum ada
  aksi pembuatan).

## Arsitektur

| Komponen | Peran |
|---|---|
| `CreateInvoiceAction` | Prerequisite Phase 1 (booking CONFIRMED + rental COMPLETED + unit ditugaskan + snapshot tarif lengkap) → hitung lewat `RentalBillingService` → invoice DRAFT + snapshot `invoice_details` + audit |
| `IssueInvoiceAction` | DRAFT → ISSUED; `issued_at`, `due_at = +24h` (timer invariant) |
| `MarkUnpaidInvoiceAction` | ISSUED → UNPAID |
| `VoidInvoiceAction` | void → CANCELLED (tanpa delete; riwayat abadi) |
| `ExpireInvoicesCommand` (`invoices:expire`) | ISSUED/UNPAID + due_at lewat → OVERDUE (jadwal tiap menit) |
| `InvoiceNumberGenerator` | Format `INV/YYYYMM/XXXX`, sekuens bulanan, `lockForUpdate` + `withTrashed` |
| `InvoicePdfGenerator` | PDF A4 bebas-dependensi (xref akurat); PRD API `GET /invoices/{id}/pdf` |
| `InvoiceController` / `InvoicePolicy` | API + otorisasi (manage = ADMIN; view = owner/admin) |

## Aturan

- Booking boleh memiliki **banyak invoice** (mis. `DAILY_WORK` + `MOB_DEMOB`).
- Invoice terhubung ke project location lewat `booking.projectLocation`.
- **Snapshot historis**: `invoice_details` menyimpan `description`, `unit_price`,
  `quantity`, `subtotal` — perubahan master tarif tidak mengubah invoice lama
  (diuji).
- `issued_at` = sumber hitungan deadline; `due_at` terkunci `+24 jam` sejak
  penerbitan (timer invariance, tidak pernah dihitung ulang).
- **Financial history tidak boleh dihapus**: tanpa endpoint delete; void hanya
  mengubah status → `CANCELLED`.
- Tidak ada tax/discount dalam nilai (tax_total 0).
- **Batas**: payment verification, overpayment, refund, outstanding → 10D/10E.

## Test Result — `InvoiceLifecycleTest` (10 kasus, 128 assertions)

- Pembuatan invoice DAILY_WORK: status DRAFT, nomor `INV/YYYYMM/XXXX`, detail
  snapshot (unit_price/quantity/subtotal), total = balance (paid 0).
- Invoice MOB_DEMOB per unit fisik qty 2 → 4 baris detail (MOB ≠ DEMOB).
- Banyak invoice per booking dengan nomor unik.
- Issue: `issued_at` terisi, `due_at = issued_at + 24h` (delta ≈ 24 jam), single-shot
  (issue berulang → 409).
- Mark-unpaid → UNPAID; `invoices:expire` saat deadline lewat → OVERDUE.
- Void → CANCELLED, baris + detail tetap tersimpan.
- Historical price integrity: kenaikan master tarif pasca-invoice tidak mengubah
  nilai invoice.
- Prerequisite belum terpenuhi (rental belum COMPLETED) → 409 BUSINESS_RULE_VIOLATION.
- Otorisasi: USER dilarang create/issue/void; pandangan invoice orang lain → 403.
- PDF: content-type application/pdf, header `%PDF-1.4`.