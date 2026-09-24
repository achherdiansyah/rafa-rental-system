# Pending Business Decisions V3 - RAFA Rental System

Dokumen register resmi seluruh isu, parameter kebijakan komersial, dan aturan bisnis yang **BELUM FINAL / MASIH MENUNGGU KEPUTUSAN MANAJEMEN** (Phase 1G).

*Catatan: Isu-isu di bawah ini tidak memblokir perancangan skema database dasar maupun arsitektur API Phase 1, namun wajib difinalisasi sebelum implementasi logika kalkulasi detail di Phase 2.*

---

## Daftar Keputusan Bisnis Terbuka (Open Decisions)

| ID | Topik Keputusan | Deskripsi Pertanyaan Bisnis | Opsi yang Tersedia | Dampak Arsitektur & Teknis |
|---|---|---|---|---|
| **PBD-01** | **Formula Lembur (Actual Hours > 8 Jam)** | Bagaimana mekanisme penagihan jika jam kerja aktual mesin di lapangan melebihi 8 jam standar per hari? | 1. Flat Prorata per jam (`base_rate / 8`).<br>2. Tarif Lembur Khusus (`overtime_rate`).<br>3. Dihitung paket 2-shift kerja. | Logika kalkulasi invoice penyesuaian timesheet di Laravel Service. |
| **PBD-02** | **Kebijakan Billing Hari Minggu & Libur Nasional** | Apakah jam operasional di hari Minggu/Libur Nasional dikenakan tarif standar atau *surcharge* lembur khusus? | 1. Tarif sama dengan hari kerja normal.<br>2. Tambahan biaya lembur persentase (Surcharge). | Kolom tarif diferensial pada `business_calendars` dan `equipment_prices`. |
| **PBD-03** | **Pengaruh Hari Libur terhadap Operational Buffer** | Apakah hari Minggu/Libur memperpanjang jeda waktu buffer mobilisasi & demobilisasi, atau pool logistik tetap jalan 24/7? | 1. Pool logistik buka 7 hari seminggu.<br>2. Buffer logistik hanya menghitung hari kerja (Senin-Sabtu). | Algoritma query availability interval waktu pada `09-availability-and-concurrency-v3.md`. |
| **PBD-04** | **Pengelolaan Data Master Operator** | Apakah sistem perlu memiliki entitas tabel khusus untuk data personil operator, atau cukup disimpan sebagai catatan teks snapshot? | 1. Tabel master `operators` terpisah (nama, sertifikasi, KTP).<br>2. Cukup string nama di BAST dan Timesheet. | Penambahan entitas tabel `operators` jika opsi 1 dipilih. |
| **PBD-05** | **Batas Wewenang Approval Refund** | Apakah seluruh pengembalian dana (refund) wajib melalui persetujuan OWNER, ataukah ADMIN memiliki wewenang batas nominal tertentu? | 1. Seluruh refund wajib approval OWNER.<br>2. Refund < Rp 1.000.000 cukup approval ADMIN. | Policy Gate authorization pada endpoint `/api/v1/refunds/{id}/approve`. |
| **PBD-06** | **Saluran Integrasi Notifikasi Pengguna** | Apakah sistem akan mengintegrasikan gateway WhatsApp API pihak ketiga (berbayar) atau mengandalkan Email SMTP & Notifikasi In-App? | 1. Integrasi Fonnte / Wablas API Gateway.<br>2. Menggunakan Email SMTP + In-App DB Notifications (Zero external cost). | Penambahan service adapter notifikasi eksternal di Laravel. |
| **PBD-07** | **Toleransi Perpanjangan Deadline Pembayaran** | Apakah perpanjangan batas waktu bayar 24 jam oleh Admin (`extend-payment-deadline`) dibatasi jumlahnya atau bebas? | 1. Maksimal 1 kali perpanjangan (maks +24 jam).<br>2. Fleksibel tanpa batas sesuai diskresi Admin. | Kolom `extension_count` pada tabel `invoices` dan validasi Form Request. |
| **PBD-08** | **Biaya Administrasi & Sanksi Pembatalan (Cancellation Fee)** | Berapa persentase potongan administrasi/denda jika penyewa membatalkan booking yang sudah dibayar (sebelum dispatch)? | 1. Potongan tetap (misal Rp 200.000).<br>2. Potongan persentase (misal 10%-25% dari nilai DP).<br>3. Tanpa potongan (Full Refund). | Helper kalkulasi nominal bersih pada modul `Refund`. |
| **PBD-09** | **Threshold Minimal Down Payment (DP)** | Berapa persentase minimal DP yang wajib dibayarkan penyewa agar status booking dapat disetujui menjadi `CONFIRMED`? | 1. Minimal 30% dari total invoice.<br>2. Minimal 50% dari total invoice.<br>3. Fleksibel sesuai kesepakatan Admin. | Validasi form request pada persetujuan pembayaran di backend. |
| **PBD-10** | **Penanganan Saldo Kelebihan Bayar (Overpayment Balance)** | Apakah sisa overpayment dapat dikonversi menjadi saldo deposit dompet user (Wallet Credit) untuk sewa berikutnya? | 1. Wajib ditransfer balik manual (Manual Refund).<br>2. Disediakan opsi konversi menjadi saldo kredit user. | Penambahan modul ledger `user_credit_balances` jika opsi 2 dipilih. |
