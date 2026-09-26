# Phase 8: Cart & Booking Engine — RAFA Rental System

Dokumentasi arsitektur, implementasi sistem pemesanan sewa armada (*Booking Engine*), manajemen keranjang belanja (*Cart*), manajemen lokasi proyek (*Project Location*), dan validasi ketersediaan armada (*Availability Guards*).

---

## 1. Tujuan Phase 8

Phase 8 bertujuan untuk mengimplementasikan alur pemesanan sewa armada alat berat komprehensif pada RAFA Rental System:
- **Project Location Management:** Pendaftaran alamat pengiriman alat berat per pelanggan dengan informasi kontak PIC lapangan.
- **Cart & Reservation Draft:** Penambahan armada sewa ke keranjang belanja dengan skema All-in/Non All-in, estimasi durasi, dan biaya MOB/DEMOB.
- **Booking Creation & State Machine:** Pembuatan kode booking unik (`RFA-BKG-XXXX`), validasi bentrok jadwal (*overlap prevention*), dan penetapan snapshot harga.

---

## 2. Struktur Subphase

| Subphase | Fokus Modul | Status |
|---|---|---|
| **8A** | Manajemen Lokasi Proyek Pelanggan (*Project Location Management*) | Selesai (`e625c86`) |
| **8B** | Keranjang Belanja Pelanggan (*Cart Management*) | Selesai (`2b36d62`) |
| **8C** | Antarmuka Pengguna Keranjang Sewa (*Cart UI & Add-to-Cart Flow*) | Selesai (Aktif) |
| **8D** | Pembuatan Booking & Validasi Ketersediaan Armada | Pending |
| **8E** | Booking Approval & Verifikasi Admin | Pending |

---

## 3. Daftar Dokumen

- `01-project-location.md`: Spesifikasi entitas lokasi proyek (`project_locations`), aturan relasi booking, pembatasan otorisasi RBAC, REST API, dan antarmuka web pelanggan.
- `02-cart-backend.md`: Spesifikasi keranjang belanja (`carts`, `cart_items`), validasi model aktif, kepemilikan user, REST API, dan pengujian.
- `03-cart-ui.md`: Spesifikasi antarmuka keranjang sewa (`/app/cart`), alur `Add-to-Cart`, validasi form, dan penanganan state UX.
