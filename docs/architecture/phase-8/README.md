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
| **8A** | Manajemen Lokasi Proyek Pelanggan (*Project Location Management*) | Selesai (Aktif) |
| **8B** | Keranjang Belanja Pelanggan (*Cart Management*) | Pending |
| **8C** | Pembuatan Booking & Validasi Ketersediaan Armada | Pending |
| **8D** | Booking Approval & Verifikasi Admin | Pending |

---

## 3. Daftar Dokumen

- `01-project-location.md`: Spesifikasi entitas lokasi proyek (`project_locations`), aturan relasi booking, pembatasan otorisasi RBAC, REST API, dan antarmuka web pelanggan.
