# Scope Review V3 - RAFA Rental System

## 1. Tujuan Sistem
Sistem manajemen rental kendaraan. Otomatisasi booking, lacak unit, catat uang, hasilkan laporan.

## 2. Role
- **USER:** Lihat katalog, booking unit, bayar sewa, kelola profil.
- **ADMIN:** Kelola harian operasional, setuju/tolak booking, cek serah-terima unit (check-in/check-out), input denda.
- **OWNER:** Akses penuh bisnis, lihat metrik laporan, pantau laba/rugi, audit log.

## 3. Modul Utama
- **Autentikasi & RBAC:** Login, daftar, batasan akses (User/Admin/Owner).
- **Manajemen Armada (Fleet):** Data unit kendaraan, status ketersediaan, kategori.
- **Reservasi & Rental:** Cek jadwal, booking flow, log check-in/out.
- **Pembayaran:** Invoice, bukti transfer, pencatatan lunas/DP.
- **Pemeliharaan (Maintenance):** Jadwal servis unit, riwayat perbaikan.
- **Laporan & Dashboard:** Statistik okupansi, pendapatan.

## 4. Arsitektur & Tech Stack
- React
- Vite
- TypeScript
- Tailwind
- Laravel 12
- PHP 8.2
- MySQL 8.4

## 5. Deployment & Infrastruktur
1. **Target Production:** Shared hosting / cPanel.
2. **Backend:** Laravel murni sebagai API.
3. **Frontend:** React SPA (Static build).
4. **Database:** MySQL menjadi *source of truth*.
5. **Docker:** Hanya opsional untuk local development.
6. **Dependency Production Dilarang (Tidak wajib):**
   - Docker
   - Redis
   - Horizon
   - MinIO
   - Custom Nginx
   - Persistent Node.js server (SSR/Next/Nuxt server).

## 6. Register Isu & Analisis Konflik

| ID | Isu | Status | Dampak | Keputusan/Pending |
|---|---|---|---|---|
| ISS-01 | Detail bisnis PRD V3 (rumus denda, skema deposit/DP) belum ada | Open | Perhitungan harga tidak akurat | Pending spesifikasi dari Owner |
| ISS-02 | Background job di shared hosting | Resolved | Redis/Horizon dilarang | Keputusan: Pakai `QUEUE_CONNECTION=database` dan cPanel cron (`php artisan schedule:run`) |
| ISS-03 | File storage (Foto KTP, Bukti Bayar, Unit) | Resolved | MinIO dilarang | Keputusan: Gunakan disk `local` / `public` Laravel via `storage:link` |
| ISS-04 | Web Socket & Real-time Notifikasi | Resolved | Butuh Redis/Server eksternal | Keputusan: Gunakan Polling API & Database Notifications |
| ISS-05 | Integrasi Payment Gateway (Midtrans/dsb) vs Transfer Manual | Open | Struktur database payment beda | Pending keputusan bisnis dari Owner |

## 7. Ringkasan
- **Scope yang sudah terkunci:** Arsitektur API + SPA, MySQL 8.4, Laravel 12, React Vite TS Tailwind. Batasan cPanel mutlak (No Redis/Docker/Node.js/MinIO). Role & modul utama.
- **Scope yang masih pending:** Formula rinci denda/DP, kebijakan deposit, integrasi pihak ketiga (Payment Gateway).
- **Apakah Phase 1B dapat dimulai:** Ya. Skema database (Migration/ERD) dan kontrak API utama (Auth, Armada, Booking dasar) bisa jalan. Desain fitur denda & payment gateway *hold* tunggu konfirmasi bisnis.