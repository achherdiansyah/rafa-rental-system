# 06 — UAT & Final Regression (Phase 13G)

Simulasi UAT seluruh journey pelanggan lewat public API + validasi
authorization/business-rules/error-handling, kemudian regression penuh.

## Skenario (UatWorkflowTest — 61 assertions)

1. **Register + Login** (auth API).
2. KYC VERIFIED dibutuhkan untuk submit booking (gate fase 6).
3. **Project → Cart → Booking**: buat lokasi proyek, tambah item keranjang,
   set lokasi, buat booking, submit (PENDING_APPROVAL).
4. **Admin Approval + Assign**: USER approve → 403; ADMIN approve → APPROVED;
   assign unit → unit ASSIGNED; booking → CONFIRMED (catatan: transisi
   payment-driven dikover Phase 10D).
5. **Dispatch → Arrival → Ongoing** (rental).
6. **Timesheet → Validation** (submit + APPROVED).
7. **Return → Inspection → READY** (rental COMPLETED).
8. **Invoice DAILY_WORK** (8h × 150k = 1.2jt).
9. **Payment partial → verification → full → PAID** (PARTIALLY_PAID → PAID).
10. **Overpayment (MOB/DEMOB 650k, bayar 900k) → OVERPAID → refund 250k**;
    owner approve (admin approve → 403), admin process + complete.
11. **Notification** (unread ≥2, event invoice/payment).
12. **Dashboard / report / export** (admin); exports membawa proyek.
13. **Error handling**: guest 401 financial; duplicate timesheet → 409
    BUSINESS_RULE_VIOLATION.

Issue ditemukan selama UAT dan diperbaiki di test (tidak ada defect sistem,
hanya asumsi test): project-location butuh `pic_*`, `customer_profiles`
dibuat sekali pe register (update-or-create utk VERIFIED), assign unit
menetapkan unit = ASSIGNED (bukan AVAILABLE), guest harus `forgetGuards()`.

## Hasil Eksekusi

| Command | Hasil |
|---|---|
| `php artisan test` | **452 passed (2526 assertions)** |
| `./vendor/bin/pint --test` | passed |
| `npm run build` | sukses |
| Frontend tests | 121 passed (tidak berubah 13A) |

Tidak ada critical/high issue tersisa; tidak ada regression pada suite
lama.

## Files

- `tests/Feature/Integration/UatWorkflowTest.php`
- Docs: `docs/architecture/phase-13/06-uat-regression.md`