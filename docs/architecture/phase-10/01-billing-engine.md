# 01 — Billing Engine (Phase 10A)

Mesin tagihan sewa berbasis jam aktual. Logika billing **tidak** ada di Controller —
berada pada layanan/aksi reusable.

## Domain baru

```
equipment_prices        + mob_cost        DECIMAL(15,2) default 0 (per unit fisik)
                        + demob_cost      DECIMAL(15,2) default 0 (per unit fisik)
booking_details         + mob_cost_snapshot   DECIMAL(15,2) nullable
                        + demob_cost_snapshot DECIMAL(15,2) nullable
```

Snapshot MOB/DEMOB diambil saat booking dibuat dari keranjang (bersama
`rental_rate_snapshot`) — tarif historis kebal terhadap revisi master.

## Arsitektur

| Komponen | Peran |
|---|---|
| `app/Services/Billing/RentalBillingService.php` | **Engine** — kalkulasi per rental: daily work, tariff efektif, MOB/DEMOB, agregasi |
| `app/DTOs/Billing/{BillingLine,RentalBill}.php` | Hasil (value object read-only) |
| `app/Actions/Billing/GenerateRentalBillAction.php` | Orchestrator: otorisasi (`RentalPolicy::view`) + audit |
| `PricingCalculatorService` + `CreateBookingFromCartAction` | Sumber & snapshot tariff |

## Aturan yang Ditegakkan

- **Daily Work = actual hours × applicable hourly rate**
  - `actual hours` = Σ `total_work_hours` dari timesheet **APPROVED** saja
    (`total_work_hours = end_hm − start_hm − break`, per Phase 9).
  - **No rounding**: tidak ada pembulatan jam maupun nilai; presisi 2 desimal
    ditangani media penyimpanan `DECIMAL`.
- **Hourly rate / skema per equipment line** — setiap `rental_detail` memakai
  snapshot `rental_rate_snapshot` + `is_all_in` dari `booking_detail`-nya
  (skema All-in/Non All-in dapat berbeda antar baris dalam satu booking).
- **No tax, no discount, no overtime tariff** — engine tidak menambahkan/turunkan
  nilai apa pun.
- **Actual hours > 8**: keputusan bisnis masih *pending* (PBD) → engine TIDAK
  menerapkan cap/surcharge. Dikomentari `ponytail:` — bercabang di lapisan
  pricing bila aturan diputuskan.
- **MOB/DEMOB per physical unit** — setiap unit fisik (1 `rental_detail` ↔ 1
  assignment) dikenakan `mob_cost` & `demob_cost` snapshot; **MOB dan DEMOB
  boleh berbeda** (kolom terpisah).
- **Price snapshot/version** — snapshot tersimpan di `booking_detail`; bila
  master tarif berubah setelah booking, tagihan tetap memakai nilai snapshot.
  Fallback ke master tarif terbaru hanya untuk data lama tanpa snapshot
  (MOB/DEMOB nullable; `rental_rate_snapshot` non-null sejak awal).

## Contoh Kalkulasi (tanpa rounding)

```
Line: Excavator All-in, rate snapshot 150.000/jam
  Approved timesheet: 8.33 jam
  work    = 8.33 × 150.000        = 1.249.500,00
  MOB     = 500.000 (unit fisikal)
  DEMOB   = 350.000
  lineTotal = 2.099.500,00
```

## Akses & Audit

- `GenerateRentalBillAction` menggunakan `RentalPolicy::view` (owner pemilik
  rental / admin / owner).
- Setiap generasi dicatat audit `RENTAL_BILL_GENERATED` (jam, rincian, grand
  total).

## Batas (TIDAK diimplementasikan)

Invoice, payment, refund, outstanding — ditunda subphase berikutnya (10B+).

## Files

- `app/Services/Billing/RentalBillingService.php`, `app/DTOs/Billing/*`,
  `app/Actions/Billing/GenerateRentalBillAction.php`
- migration `2026_09_25_000008_add_billing_mob_demob_fields_table.php`
- `app/Models/{EquipmentPrice,BookingDetail}.php` (kolom baru)
- `app/Services/Pricing/PricingCalculatorService.php` (MOB/DEMOB bersumber
  master tarif, override keranjang tetap didukung)
- `app/Actions/Booking/CreateBookingFromCartAction.php` (snapshot MOB/DEMOB)
- `tests/Feature/Billing/BillingEngineTest.php` (7 kasus, 92 assertions)

## Test Result

- **Billing jam/rate tanpa rounding** — 8.33 jam × 150.000 = 1.249.500,00
- All-in vs Non All-in per line (flags & rate terpisah)
- MOB/DEMOB per unit fisik, qty 2 → tagih 2×(MOB) + 2×(DEMOB), MOB ≠ DEMOB
- Snapshot immutability terhadap kenaikan master tarif
- Fallback master tarif untuk snapshot kosong (MOB/DEMOB)
- Hanya timesheet APPROVED yang ditagih (DRAFT dikecualikan)
- Otorisasi `GenerateRentalBillAction` (pemilik boleh, user lain 403)