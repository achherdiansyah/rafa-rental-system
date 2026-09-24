# Feedback, Error, Loading & Confirmation System - RAFA Rental System

Dokumentasi spesifikasi sistem umpan balik pengguna *(feedback system)*, penanganan galat jaringan, status pemuatan *(loading states)*, dan dialog konfirmasi pada RAFA Rental System (Phase 4F).

---

## 1. Hirarki Pemuatan (Loading States)

Untuk menjaga responsivitas dan menghindari layar putih penuh (*blank screen*), pemuatan data dibagi menjadi beberapa tingkatan:

| Tingkat | Komponen | Kapan Digunakan |
|---|---|---|
| **Page-Level** | `LoadingState` | Transisi rute besar via `Suspense` saat modul chunk JS sedang diunduh. |
| **Section-Level** | `TableSkeleton`, `CardSkeleton` | Pemuatan tabel armada, riwayat booking, atau kartu statistik tanpa menyembunyikan header/sidebar. |
| **Form-Level** | `FormSkeleton` | Pemuatan awal formulir yang memerlukan pre-fetch data (misal: Edit Profil, Form Reschedule). |
| **Action-Level** | `Button.isLoading` | Tombol berubah menjadi status loading dengan spinner terintegrasi saat user menekan aksi (Submit, Bayar, Approve) untuk mencegah klik ganda (*double submission*). |
| **Inline** | Spinner kecil | Indikator ringan saat pembaruan status minor (misal auto-save atau polling status unit). |

---

## 2. Sistem Notifikasi Sukses & Toast (`ToastProvider` & `useToast`)

Sistem menyediakan notifikasi mengambang *(floating toast)* non-blocking untuk konfirmasi aksi:

```typescript
import { useToast } from '@/hooks/useToast'

const { success, error, warning, info } = useToast()

// Contoh pemanggilan:
success('Bukti pembayaran berhasil diunggah.')
error('Format file tidak didukung. Unggah PDF atau JPG.')
```

### Karakteristik Toast:
- Muncul di pojok kanan atas layar (`top-right`) secara mulus.
- Durasi otomatis tertutup 4 detik (dapat disesuaikan).
- Dapat ditutup secara manual oleh user via tombol `X`.
- Terintegrasi global via `ToastProvider` di root aplikasi.

---

## 3. Penanganan Galat Jaringan & Server (Error Feedback)

Sistem membedakan perlakuan terhadap galat aplikasi:

1. **Galat Jaringan (Network Failure / Offline):**
   - Komponen `NetworkErrorBanner` dimunculkan jika request API gagal terhubung ke backend (status 0 / timeout).
   - Memberikan tombol **"Coba Hubungkan Ulang"** (`onRetry`) tanpa menampilkan pesan error teknis mentah seperti *ERR_CONNECTION_REFUSED*.
2. **Galat Server / HTTP 500:**
   - Komponen `ErrorState` menampilkan pesan ramah pengguna: *"Terjadi kendala pada server kami. Silakan coba beberapa saat lagi."*
3. **Galat Validasi Form (HTTP 422):**
   - Ditampilkan langsung di bawah field input terkait menggunakan prop `error` pada komponen Form (`Input`, `Select`, `Textarea`).

---

## 4. Dialog Konfirmasi Aksi Destruktif (`ConfirmDialog`)

Aksi yang mengubah status penting atau bersifat tidak dapat dibatalkan secara sepihak (seperti *Tolak Booking*, *Batalkan Sewa*, *Hapus Lokasi*) **WAJIB** meminta konfirmasi pengguna:

```typescript
<ConfirmDialog
  isOpen={isConfirmOpen}
  onClose={() => setIsConfirmOpen(false)}
  onConfirm={handleCancelBooking}
  title="Konfirmasi Pembatalan Booking"
  message="Apakah Anda yakin ingin membatalkan booking ini? Kuota unit akan dilepaskan kembali ke publik."
  confirmText="Ya, Batalkan"
  variant="danger"
  isLoading={isCancelling}
/>
```

---

## 5. Status Kosong Terstandarisasi (`EmptyState`)

Ketika sebuah tabel, daftar reservasi, atau notifikasi belum memiliki entri data, komponen `EmptyState` menyajikan ilustrasi netral dan ajakan bertindak (*call to action* / CTA) yang jelas tanpa membingungkan pengguna:

```typescript
<EmptyState
  title="Belum Ada Transaksi Booking"
  description="Anda belum memiliki riwayat reservasi alat berat yang aktif."
  action={
    <Link to="/app/equipment">
      <Button size="sm">Jelajahi Katalog Alat</Button>
    </Link>
  }
/>
```
