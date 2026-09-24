# Enum & State Foundation - RAFA Rental System

Dokumentasi spesifikasi Backed Enum PHP 8.1+ dan pola transisi state Laravel untuk RAFA Rental System (Phase 2E).

---

## 1. Daftar Backed Enum Resmi

Seluruh enum diimplementasikan sebagai string-backed enum di namespace `App\Enums\`. Nilai setiap state identik 100% dengan dokumen `02-state-machines-v3.md`.

### 1.1 `BookingStatus` (`App\Enums\BookingStatus`)
| Case | Value | Makna Bisnis |
|---|---|---|
| `DRAFT` | `'DRAFT'` | Keranjang tersimpan, belum disubmit |
| `SUBMITTED` | `'SUBMITTED'` | Form booking disubmit pengguna |
| `PENDING_APPROVAL` | `'PENDING_APPROVAL'` | Menunggu review dan persetujuan Admin |
| `REJECTED` | `'REJECTED'` | Ditolak Admin dengan alasan wajib |
| `APPROVED` | `'APPROVED'` | Disetujui Admin, invoice terbit |
| `PAYMENT_PENDING` | `'PAYMENT_PENDING'` | Menunggu pembayaran (deadline 24 jam) |
| `CONFIRMED` | `'CONFIRMED'` | Pembayaran terverifikasi (Lunas/DP) |
| `DISPATCHED` | `'DISPATCHED'` | Unit dalam perjalanan ke lokasi proyek |
| `ARRIVED` | `'ARRIVED'` | Unit tiba di lokasi, menunggu BAST Check-in |
| `ONGOING` | `'ONGOING'` | BAST Check-in disetujui, masa sewa aktif |
| `COMPLETED` | `'COMPLETED'` | BAST Check-out tuntas, inspeksi lolos, lunas |
| `CANCELLED` | `'CANCELLED'` | Dibatalkan sebelum dispatch |
| `EXPIRED` | `'EXPIRED'` | Melewati deadline 24 jam tanpa pembayaran |

### 1.2 `RentalStatus` (`App\Enums\RentalStatus`)
| Case | Value | Makna Bisnis |
|---|---|---|
| `PENDING_ASSIGNMENT`| `'PENDING_ASSIGNMENT'` | Menunggu penetapan nomor unit fisik oleh Admin |
| `ASSIGNED` | `'ASSIGNED'` | Unit fisik spesifik telah ditetapkan |
| `DISPATCHED` | `'DISPATCHED'` | Unit diberangkatkan (di jalan ke proyek) |
| `ARRIVED` | `'ARRIVED'` | Unit tiba di proyek (handover berlangsung) |
| `ONGOING` | `'ONGOING'` | Unit resmi bekerja di proyek |
| `DEMOBILIZING` | `'DEMOBILIZING'` | Unit dalam perjalanan ditarik ke pool |
| `RETURN_INSPECTED` | `'RETURN_INSPECTED'` | Unit masuk karantina inspeksi di pool |
| `COMPLETED` | `'COMPLETED'` | Operasional selesai sempurna |
| `CANCELLED` | `'CANCELLED'` | Dibatalkan pra-dispatch |

### 1.3 `EquipmentStatus` (`App\Enums\EquipmentStatus`)
| Case | Value | Makna Bisnis |
|---|---|---|
| `AVAILABLE` | `'AVAILABLE'` | Unit di pool, kondisi prima, siap dialokasikan |
| `ASSIGNED` | `'ASSIGNED'` | Unit terkunci pada booking |
| `MOBILIZING` | `'MOBILIZING'` | Unit sedang diangkut tronton ke lokasi |
| `ON_SITE` | `'ON_SITE'` | Unit berada di lokasi proyek |
| `DEMOBILIZING` | `'DEMOBILIZING'` | Unit sedang diangkut pulang ke pool |
| `RETURN_INSPECTION` | `'RETURN_INSPECTION'` | Karantina pemeriksaan fisik & uji fungsi |
| `MAINTENANCE` | `'MAINTENANCE'` | Masuk bengkel perbaikan / servis berkala |
| `DECOMMISSIONED` | `'DECOMMISSIONED'` | Unit dihapus permanen dari operasional aktif |

### 1.4 `InvoiceStatus` (`App\Enums\InvoiceStatus`)
| Case | Value | Makna Bisnis |
|---|---|---|
| `DRAFT` | `'DRAFT'` | Draf tagihan |
| `UNPAID` | `'UNPAID'` | Diterbitkan, belum ada pembayaran sah |
| `PARTIALLY_PAID` | `'PARTIALLY_PAID'` | Pembayaran DP diverifikasi, sisa tagihan > 0 |
| `PAID` | `'PAID'` | Lunas 100% |
| `OVERPAID` | `'OVERPAID'` | Nilai transfer melebihi total tagihan |
| `EXPIRED` | `'EXPIRED'` | Melewati batas waktu 24 jam |
| `CANCELLED` | `'CANCELLED'` | Tagihan dibatalkan |

### 1.5 `PaymentStatus` (`App\Enums\PaymentStatus`)
| Case | Value | Makna Bisnis |
|---|---|---|
| `PENDING` | `'PENDING'` | Menunggu upload bukti bayar |
| `SUBMITTED` | `'SUBMITTED'` | Bukti transfer diupload pengguna |
| `APPROVED` | `'APPROVED'` | Mutasi bank valid & diverifikasi Admin |
| `REJECTED` | `'REJECTED'` | Ditolak Admin (deadline tidak reset) |

### 1.6 `RefundStatus` (`App\Enums\RefundStatus`)
| Case | Value | Makna Bisnis |
|---|---|---|
| `REQUESTED` | `'REQUESTED'` | Permintaan refund diajukan |
| `REVIEWED` | `'REVIEWED'` | Ditinjau oleh Admin (perhitungan potongan) |
| `APPROVED` | `'APPROVED'` | Disetujui Owner untuk pencairan |
| `REJECTED` | `'REJECTED'` | Ditolak |
| `PROCESSING` | `'PROCESSING'` | Proses transfer manual oleh Finance |
| `COMPLETED` | `'COMPLETED'` | Bukti transfer balik diunggah ke sistem |

### 1.7 Enum Pendukung Lainnya
- `UserRole`: `USER`, `ADMIN`, `OWNER`
- `PriceScheme`: `ALL_IN`, `NON_ALL_IN`
- `InvoiceType`: `RENTAL`, `MOB_DEMOB`, `ADDITIONAL_CHARGE`, `PENALTY`, `DAMAGE`
- `AssignmentStatus`: `ASSIGNED`, `REPLACED`, `CANCELLED`, `COMPLETED`
- `TimesheetStatus`: `DRAFT`, `SUBMITTED`, `APPROVED`, `REJECTED`

---

## 2. Pola Pengendalian Transisi State (State Transition Pattern)

### 2.1 Larangan Mutasi Liar
Status model **DILARANG** diubah secara langsung di sembarang tempat:
```php
// DILARANG:
$booking->status = BookingStatus::APPROVED;
$booking->save();
```

### 2.2 Transisi Melalui Domain Action
Setiap perpindahan status wajib dieksekusi melalui kelas Action terdedikasi:
1. Memeriksa precondition source state.
2. Melempar `InvalidStateTransitionException` jika status awal tidak valid.
3. Menjalankan mutasi status dalam `DB::transaction()`.
4. Memicu side-effects (generate invoice, lock/release slot, audit log).

Contoh pola transisi:
```php
class ApproveBookingAction
{
    public function execute(Booking $booking): Booking
    {
        if ($booking->status !== BookingStatus::PENDING_APPROVAL) {
            throw new InvalidStateTransitionException(
                "Cannot approve booking from state: {$booking->status->value}"
            );
        }

        return DB::transaction(function () use ($booking) {
            $booking->update(['status' => BookingStatus::APPROVED]);
            
            // Trigger side-effects (generate invoice, rilis event)
            return $booking;
        });
    }
}
```
