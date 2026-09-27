# 02 — Outstanding & Customer Credit Control (Phase 11C)

Kontrol kredit pelanggan berdasarkan invoice yang belum lunas.

## Definisi

- **Outstanding dihitung per customer** dari invoice **belum lunas**:
  `UNPAID`, `PARTIALLY_PAID`, `OVERDUE` (+ `ISSUED` — jendela pembayaran belum
  dibuka/ditandai unpaid; dikelompokkan "belum lunas"). `PAID`, `OVERPAID`,
  `DRAFT`, `CANCELLED` **dikecualikan**.
- **Otomatis terpisah dari status booking** — booking bisa `DISPATCHED`/`ONGOING`
  dan invoice-nya tetap dihitung; status booking tidak pernah disentuh service.
- **Semua perhitungan berbasis approved payment**: `paid = Σ payments APPROVED`;
  `balance = grand_total − paid` (payment REJECTED tidak dihitung).
- **No threshold/hari-jumlah baru** — tidak ada plafon kredit atau segmentasi
  umur piutang yang dikarang (PBD-10); service hanya melaporkan fakta.

## Service

`App\Services\Finance\CustomerOutstandingService`:

| Method | Output |
|---|---|
| `forCustomer(userId)` | `user_id`, `customer_name`, `total_outstanding`, `open_invoice_count`, `overdue_invoice_count`, `eligible`, `invoices[]` (per invoice: number, status, grand_total, paid_amount, balance) |
| `allCustomers()` | rekap per customer yang punya tagihan terbuka (admin) |
| `eligibility(userId)` | `true` bila `total_outstanding == 0` |

- `eligible` = status faktual (balance terbuka nol). Kebijakan plafon/blocking
  final ditandai `ponytail:` — ditambahkan saat manajemen memutuskan.
- **Anti N+1**: 1 query invoice + eager `booking.user` & `payments(APPROVED)`;
  diuji jumlah query < 8 untuk 5 invoice.
- Status terbuka terpusat di `CustomerOutstandingService::OPEN_STATUSES`.

## Endpoints

```
GET /api/v1/finance/outstanding/me     user   outstanding akun sendiri
GET /api/v1/finance/outstanding        admin/owner  rekap antar-pelanggan
    ? user_id → outstanding satu pelanggan
```

## Test Result — `CustomerOutstandingTest` (5 kasus, 23 assertions)

- Agregasi invoice terbuka (UNPAID 400k + OVERDUE 200k + PARTIAL 0 = 600k),
  mengecualikan PAID/OVERPAID/DRAFT/CANCELLED; payment REJECTED 50k tidak
  mengurangi saldo.
- Isolasi per customer + eligibility (clean → eligible; debtor → tidak);
  rekap admin hanya memuat customer bertagihan.
- Outstanding berbasis invoice, independen status booking (DISPATCHED tetap
  terhitung, booking tak berubah).
- No N+1 (query count dikunci < 8).
- Endpoint scoping & otorisasi (`/me` sendiri; rekap admin 403 untuk user).

## Files

- `app/Services/Finance/CustomerOutstandingService.php`
- `app/Http/Controllers/Api/V1/OutstandingController.php`
- `routes/api.php` (group `finance`)
- `tests/Feature/Finance/CustomerOutstandingTest.php`