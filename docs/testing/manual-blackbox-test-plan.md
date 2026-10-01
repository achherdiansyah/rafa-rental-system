# TEST PLAN BLACK-BOX MANUAL SISTEM RAFA RENTAL

Dokumen ini adalah panduan pengujian fungsional black-box manual end-to-end untuk sistem RAFA Rental (Frontend React 19 + Tailwind v4 & Backend Laravel 12 API). Seluruh pengujian dilakukan langsung melalui browser oleh tester manusia tanpa mengacu pada unit test atau source code.

---

# A. PERSIAPAN TEST & DATA TEST REPRODUSIBEL

### 1. Lingkungan Pengujian
- **URL Frontend:** `http://localhost:5173`
- **URL Backend API:** `http://127.0.0.1:8000/api/v1`
- **Database Engine:** MySQL 8.4 (Port 3307)
- **Browser:** Google Chrome / Microsoft Edge (Desktop viewport 1920x1080 & Mobile responsive 390x844)

### 2. Akun Uji (Pre-seeded & Dynamic)
| Role | Email | Password | Nama Lengkap | Entitas / Deskripsi |
|---|---|---|---|---|
| **ADMIN** | `admin@rafarental.com` | `password` | Admin Operasional | Pengelola operasional & master data |
| **OWNER** | `owner@rafarental.com` | `password` | Owner Bisnis | Monitoring eksekutif & audit trail |
| **USER 1** | `budi@kontraktor.com` | `password` | Budi Santoso | PT Maju Konstruksi Jaya (Verified KYC) |
| **USER 2** | `siti@tambang.com` | `password` | Siti Rahmawati | CV Prima Mandiri Mineral (Verified KYC) |
| **USER BARU** | `tester.manual@gmail.com` | `Password123!` | Doni Pratama | Akun dinamis untuk uji pendaftaran baru |

### 3. Master Data Awal (Pre-seeded)
- **Tipe Alat:** `Excavator`, `Bulldozer`, `Wheel Loader`, `Vibro Compactor`
- **Model Alat:**
  - `Komatsu PC200-8` (Excavator 20 Ton) — Non All-in: Rp 225.000/jam, All-in: Rp 350.000/jam (Min 8 jam)
  - `Caterpillar CAT 320D` (Excavator 20 Ton) — Non All-in: Rp 240.000/jam, All-in: Rp 365.000/jam (Min 8 jam)
  - `Komatsu D85ESS-2` (Bulldozer 21 Ton) — Non All-in: Rp 300.000/jam, All-in: Rp 450.000/jam (Min 8 jam)
  - `Komatsu WA380-6` (Wheel Loader 18 Ton) — Non All-in: Rp 275.000/jam, All-in: Rp 400.000/jam (Min 8 jam)
  - `Dynapac CA250D` (Vibro Compactor 11 Ton) — Non All-in: Rp 200.000/jam, All-in: Rp 320.000/jam (Min 8 jam)
- **Unit Fisik Terdaftar:**
  - `KM-PC200-001` (B 9101 RFA, status: AVAILABLE, HM: 1250.50)
  - `KM-PC200-002` (B 9102 RFA, status: AVAILABLE, HM: 2400.00)
  - `KM-PC200-003` (B 9103 RFA, status: ASSIGNED, HM: 3100.25)
  - `CAT-320D-001` (B 9201 RFA, status: AVAILABLE, HM: 980.00)
  - `CAT-320D-002` (B 9202 RFA, status: MAINTENANCE, HM: 4500.00)
  - `KM-D85-001` (B 9301 RFA, status: AVAILABLE, HM: 1800.00)
  - `KM-WA380-001` (B 9401 RFA, status: AVAILABLE, HM: 800.00)
  - `DY-CA250-001` (B 9501 RFA, status: AVAILABLE, HM: 1500.00)
- **Rekening Perusahaan:**
  - Bank BCA: `1234567890` a.n. PT RAFA RENTAL NUSANTARA
  - Bank Mandiri: `1300098765432` a.n. PT RAFA RENTAL NUSANTARA
- **Lokasi Proyek Awal:**
  - `Proyek Tol Cisumdawu Seksi 4` (Sumedang, PIC: Hendra Setiawan / 081399887701)
  - `Proyek Bendungan Leuwikeris` (Ciamis, PIC: Agus Priyanto / 081399887702)

---

# B. USER — TEST CASES END-TO-END (33 Langkah)

Setiap test case menggunakan format standar:
`TEST ID | ROLE | MODULE | SCENARIO | PRECONDITION | STEPS | EXPECTED | ACTUAL | PASS/FAIL | BUG ID | SEVERITY | NOTES`

```
TEST ID: USR-01
ROLE: USER (Guest)
MODULE: Authentication
SCENARIO: Registrasi akun baru customer melalui browser
PRECONDITION: Browser berada di halaman publik, belum login. Email tester belum pernah terdaftar.
STEPS:
1. Buka browser dan arahkan ke http://localhost:5173/
2. Klik tombol "Daftar" pada Navbar atas kanan.
3. Pada form /register, isi:
   - Nama Lengkap: Doni Pratama
   - Email: tester.manual@gmail.com
   - Nomor Telepon: 081299887766
   - Password: Password123!
   - Konfirmasi Password: Password123!
4. Klik tombol "Daftar Sekarang".
EXPECTED: Form berhasil tersubmit, muncul toast notifikasi registrasi berhasil, sistem otomatis melakukan autentikasi dan me-redirect pengguna ke halaman dashboard customer (/app). Sesi pengguna tersimpan.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 

TEST ID: USR-02
ROLE: USER
MODULE: Authentication
SCENARIO: Login akun customer yang sudah terdaftar
PRECONDITION: Akun budi@kontraktor.com sudah terdaftar dan aktif. Browser tidak dalam keadaan login.
STEPS:
1. Buka http://localhost:5173/login
2. Masukkan Email: budi@kontraktor.com
3. Masukkan Password: password
4. Klik tombol "Masuk".
EXPECTED: Tombol menampilkan indikator loading sesaat, autentikasi sukses, redirect ke /app. Navbar internal customer menampilkan nama "Budi Santoso", dan menu navigasi customer muncul di sidebar.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 

TEST ID: USR-03
ROLE: USER
MODULE: Authentication
SCENARIO: Logout dari sistem dan login ulang
PRECONDITION: Pengguna login sebagai Budi Santoso di /app.
STEPS:
1. Klik tombol "Keluar" (ikon merah) pada navbar atau bagian bawah sidebar.
2. Konfirmasi logout jika muncul dialog konfirmasi.
3. Pastikan browser diarahkan kembali ke landing page (/) atau /login.
4. Coba akses URL http://localhost:5173/app melalui address bar.
5. Login kembali menggunakan budi@kontraktor.com / password.
EXPECTED: Sesi dibersihkan total. Akses langsung ke /app setelah logout langsung diredirect ke /login. Setelah login ulang, pengguna kembali berhasil masuk ke /app tanpa error session stale.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: USR-04
ROLE: USER
MODULE: Profile & KYC
SCENARIO: Melengkapi dan memperbarui profil perusahaan serta identitas diri
PRECONDITION: Pengguna login sebagai budi@kontraktor.com.
STEPS:
1. Pada sidebar customer, klik menu "Profil" (/app/profile).
2. Periksa tampilan status verifikasi (misal: VERIFIED).
3. Ubah nomor telepon menjadi 081234567899.
4. Ubah alamat perusahaan menjadi "Kawasan Industri MM2100 Blok C-3, Cikarang".
5. Klik tombol "Simpan Perubahan".
6. Refresh browser (F5).
EXPECTED: Muncul notifikasi "Profil berhasil diperbarui". Setelah refresh browser, perubahan nomor telepon dan alamat tetap tersimpan dan tidak kembali ke nilai lama.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: USR-05
ROLE: USER
MODULE: Equipment Catalog
SCENARIO: Menjelajahi katalog alat berat dengan tampilan grid/list
PRECONDITION: Pengguna berada di /app. Master data model alat berat tersedia.
STEPS:
1. Pada sidebar customer, klik menu "Katalog Alat" (/app/equipment).
2. Amati kartu-kartu alat berat yang ditampilkan (gambar, nama model, kapasitas, tarif sewa per jam).
3. Scroll halaman hingga ke bawah untuk memeriksa pagination atau lazy load.
EXPECTED: Katalog menampilkan seluruh model aktif (PC200-8, CAT 320D, D85ESS-2, WA380-6, CA250D). Kartu alat berat menampilkan foto, badge tipe, spesifikasi kapasitas, dan tarif dasar dengan rapi tanpa layout pecah.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: USR-06
ROLE: USER
MODULE: Equipment Catalog
SCENARIO: Pencarian dan pemfilteran katalog alat berat
PRECONDITION: Berada di halaman /app/equipment.
STEPS:
1. Masukkan kata kunci "Komatsu" pada kolom pencarian.
2. Amati hasil pencarian yang difilter di layar.
3. Hapus teks pencarian, lalu pilih dropdown Filter Tipe: "Excavator".
4. Pilih filter kapasitas atau status ketersediaan jika ada.
5. Klik tombol "Reset Filter".
EXPECTED: Saat mengetik "Komatsu", hanya model PC200-8, D85ESS-2, dan WA380-6 yang muncul. Saat filter Excavator aktif, hanya PC200-8 dan CAT 320D yang muncul. Tombol Reset mengembalikan katalog ke tampilan seluruh armada.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: MEDIUM
NOTES: 

TEST ID: USR-07
ROLE: USER
MODULE: Equipment Catalog
SCENARIO: Melihat halaman rincian spesifikasi teknis dan ketentuan sewa model alat
PRECONDITION: Berada di /app/equipment.
STEPS:
1. Klik pada kartu alat "Komatsu PC200-8".
2. Browser berpindah ke rincian alat (/app/equipment/:id).
3. Periksa foto utama, galeri thumbnail, spesifikasi mesin, kapasitas bucket/tonase, opsi tarif All-in vs Non All-in, dan ketentuan minimum sewa 8 jam.
EXPECTED: Seluruh informasi spesifikasi model tertampil akurat. Tombol "Tambah ke Keranjang" aktif. Tidak ada broken image icon atau placeholder text yang kosong.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: USR-08
ROLE: USER
MODULE: Recommendation Engine
SCENARIO: Menjalankan wizard rekomendasi alat berat berdasarkan parameter proyek lapangan
PRECONDITION: Login sebagai USER, berada di /app/recommendations.
STEPS:
1. Klik menu sidebar "Rekomendasi".
2. Pada form rekomendasi, pilih parameter:
   - Jenis Pekerjaan: Galian Basah / Saluran Air
   - Kondisi Medan: Tanah Lunak / Rawa
   - Target Volume: 1500 m³
   - Kedalaman Galian Maksimal: 5 meter
   - Jangkauan Kerja: 9 meter
   - Estimasi Durasi Proyek: 14 hari
3. Klik tombol "Dapatkan Rekomendasi".
EXPECTED: Sistem memproses input tanpa error 500/422. Layar menampilkan daftar alat yang direkomendasikan dengan skor kecocokan (match score), penjelasan keunggulan armada, dan tombol aksi langsung "Pilih Alat" / "Masukkan Keranjang".
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: USR-09
ROLE: USER
MODULE: Shopping Cart
SCENARIO: Memasukkan model alat berat ke dalam keranjang sewa
PRECONDITION: Berada di halaman detail alat Komatsu PC200-8 atau hasil rekomendasi.
STEPS:
1. Tentukan opsi paket: Non All-in.
2. Tentukan estimasi kuantitas unit: 1 unit.
3. Klik tombol "Tambah ke Keranjang".
4. Amati perubahan badge counter keranjang pada sidebar/header.
5. Klik menu sidebar "Keranjang" (/app/cart).
EXPECTED: Muncul toast "Item berhasil ditambahkan ke keranjang". Halaman /app/cart memuat item Komatsu PC200-8 dengan rincian tarif dasar Rp 225.000/jam dan minimum 8 jam/hari.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: USR-10
ROLE: USER
MODULE: Project Location
SCENARIO: Memeriksa daftar lokasi proyek tersimpan milik pengguna
PRECONDITION: Berada di /app, akun Budi Santoso telah memiliki data seeder lokasi proyek.
STEPS:
1. Klik menu sidebar "Lokasi Proyek" (/app/locations).
2. Periksa tabel atau daftar kartu lokasi proyek yang muncul.
EXPECTED: Tampil minimal 2 lokasi yang sudah terdaftar: "Proyek Tol Cisumdawu Seksi 4" dan "Proyek Bendungan Leuwikeris", lengkap dengan nama PIC, nomor telepon, kota, dan status Aktif.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: USR-11
ROLE: USER
MODULE: Project Location
SCENARIO: Melakukan Create, Read, Update, Delete (CRUD) lokasi proyek
PRECONDITION: Berada di /app/locations.
STEPS:
1. Klik tombol "Tambah Lokasi Proyek".
2. Isi form modal:
   - Nama Proyek: Proyek Dermaga Patimban Tahap 2
   - Alamat Lengkap: Jl. Raya Pelabuhan Patimban KM 5
   - Kota/Kabupaten: Subang
   - Nama PIC Lapangan: Bambang Hermanto
   - Nomor Telepon PIC: 081288776655
   - Titik Koordinat: -6.2412, 107.9011
3. Klik "Simpan Lokasi".
4. Verifikasi item baru langsung muncul di tabel.
5. Klik tombol "Edit" pada item Patimban, ubah Nama PIC menjadi "Bambang H. Sutanto", klik "Simpan".
6. Refresh browser (F5) dan verifikasi data nama PIC tetap ter-update.
7. Klik tombol "Hapus" pada lokasi yang baru dibuat, konfirmasi dialog penghapusan.
EXPECTED: Operasi Create berhasil (status aktif langsung true tanpa delay). Update tersimpan persist setelah refresh. Delete berhasil menghapus data tanpa merusak daftar lokasi lainnya.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: USR-12
ROLE: USER
MODULE: Booking Availability
SCENARIO: Memilih rentang tanggal sewa dan memeriksa availability alat di keranjang
PRECONDITION: Item Komatsu PC200-8 berada di keranjang (/app/cart).
STEPS:
1. Buka halaman /app/cart.
2. Pada item alat berat, tentukan Tanggal Mulai: Besok (H+1) dan Tanggal Selesai: H+7 (durasi 7 hari kalender).
3. Pilih Lokasi Proyek pengiriman: "Proyek Tol Cisumdawu Seksi 4".
4. Periksa indikator status ketersediaan armada yang divalidasi sistem.
EXPECTED: Sistem memvalidasi ketersediaan armada untuk rentang tanggal tersebut. Muncul label "Tersedia" berwarna hijau. Ringkasan perhitungan estimasi sewa otomatis terhitung (7 hari x 8 jam x tarif per jam).
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 

TEST ID: USR-13
ROLE: USER
MODULE: Booking Submission
SCENARIO: Melakukan submit pengajuan sewa (booking) dari keranjang belanja
PRECONDITION: Cart terisi model PC200-8, tanggal sewa valid H+1 s/d H+7, lokasi proyek terpilih, profil customer verified.
STEPS:
1. Pada halaman /app/cart, periksa kembali ringkasan pesanan.
2. Centang persetujuan Syarat & Ketentuan Sewa Alat Berat RAFA Rental.
3. Klik tombol "Ajukan Sewa Sekarang" / "Submit Booking".
4. Amati proses pengiriman dan respons sistem.
EXPECTED: Muncul loading state sesaat. Muncul toast sukses pengajuan sewa. Keranjang belanja otomatis dikosongkan. Pengguna diarahkan ke halaman daftar pemesanan (/app/bookings) dan melihat pesanan baru dengan status "PENDING_APPROVAL".
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 

TEST ID: USR-14
ROLE: USER
MODULE: Booking Management
SCENARIO: Memeriksa rincian pemesanan dan status pemesanan di menu Sewa Saya
PRECONDITION: Booking baru saja disubmit pada test case USR-13.
STEPS:
1. Masuk ke menu sidebar "Sewa Saya" (/app/bookings).
2. Cari kode booking yang baru terbentuk (misal: RFA-BKG-...).
3. Klik tombol "Detail" pada baris booking tersebut.
4. Periksa detail: Tanggal Pengajuan, Lokasi Pengantaran, Periode Sewa, Model Alat, dan Kuantitas Unit.
EXPECTED: Rincian booking terbuka dalam modal atau halaman detail. Kode booking tertera jelas, status tampil "PENDING_APPROVAL" dengan badge kuning/oranye. Informasi unit fisik spesifik belum tertampil karena menunggu persetujuan dan penetapan unit oleh Admin.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: USR-15
ROLE: USER
MODULE: Booking Management
SCENARIO: Memeriksa hasil persetujuan (approval) pemesanan oleh Admin
PRECONDITION: Admin telah menyetujui booking yang diajukan (status APPROVED).
STEPS:
1. Buka kembali halaman /app/bookings.
2. Cari baris booking yang bersangkutan.
3. Periksa badge status pesanan.
EXPECTED: Status booking telah berubah dari "PENDING_APPROVAL" menjadi "APPROVED" dengan badge hijau. Terdapat catatan persetujuan dari Admin.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: USR-16
ROLE: USER
MODULE: Booking Management
SCENARIO: Memeriksa identitas unit fisik alat berat yang ditetapkan oleh Admin
PRECONDITION: Admin telah melakukan assignment unit fisik ke booking.
STEPS:
1. Pada /app/bookings, klik "Detail" pada booking yang sudah APPROVED.
2. Masuk ke bagian "Unit Alat Berat Ditugaskan".
3. Periksa informasi unit: Nomor Seri / Serial Number, Pelat Nomor, dan Tahun Pembuatan.
EXPECTED: Muncul informasi unit fisik yang konkret (misal: Komatsu PC200-8, Serial: KM-PC200-001, Pelat: B 9101 RFA). Unit fisik dipilih dan ditetapkan oleh Admin, bukan oleh pengguna.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: USR-17
ROLE: USER
MODULE: Invoice & Billing
SCENARIO: Memeriksa tagihan sewa awal (Invoice Rental / Booking) yang diterbitkan Admin
PRECONDITION: Admin telah menerbitkan invoice sewa untuk booking tersebut.
STEPS:
1. Klik menu sidebar "Tagihan & Bayar" (/app/invoices).
2. Temukan invoice yang terasosiasi dengan kode booking tadi (misal: INV/2026...).
3. Klik tombol "Lihat Invoice".
4. Periksa komponen rincian biaya: Biaya Sewa Pokok, Biaya Mobilisasi/Demobilisasi (MOB/DEMOB), Pajak (jika ada), dan Grand Total.
EXPECTED: Invoice menampilkan status "UNPAID" / "PENDING". Nilai total biaya tagihan sesuai dengan kesepakatan pemesanan dan komponen biaya MOB/DEMOB tercantum jelas. Nomor rekening tujuan pembayaran milik RAFA Rental tertampil.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 

TEST ID: USR-18
ROLE: USER
MODULE: Invoice & Billing
SCENARIO: Memeriksa batas waktu pembayaran (payment deadline / due date)
PRECONDITION: Invoice telah diterbitkan Admin.
STEPS:
1. Buka detail invoice di /app/invoices.
2. Amati tanggal & jam jatuh tempo (Due Date / Deadline).
EXPECTED: Tampil batas waktu pembayaran tepat 24 jam setelah invoice di-issue secara resmi oleh Admin. Jika mendekati deadline, terdapat penanda warna peringatan (misal teks merah/kuning).
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: USR-19
ROLE: USER
MODULE: Payment
SCENARIO: Melakukan pembayaran dan mengunggah bukti transfer bank (Payment Proof)
PRECONDITION: Invoice berada dalam status UNPAID. Pengguna memiliki file gambar bukti transfer (JPG/PNG).
STEPS:
1. Pada detail invoice di /app/invoices, klik tombol "Konfirmasi Pembayaran" / "Upload Bukti Bayar".
2. Pada modal pembayaran:
   - Pilih Rekening Tujuan: Bank BCA (1234567890 a.n. PT RAFA RENTAL NUSANTARA)
   - Masukkan Nominal Transfer: Rp 14.000.000 (sesuai nominal tagihan)
   - Tanggal Transfer: Hari ini
   - Nomor Referensi Transfer: TRX-BCA-987654
   - Upload Berkas Bukti: Pilih file `bukti_transfer.jpg` (< 2MB)
3. Klik tombol "Kirim Bukti Pembayaran".
EXPECTED: Berkas terunggah sukses, modal tertutup, muncul toast "Bukti pembayaran berhasil dikirim". Status tagihan atau riwayat pembayaran berubah menjadi "SUBMITTED" (menunggu verifikasi Admin).
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 

TEST ID: USR-20
ROLE: USER
MODULE: Payment
SCENARIO: Memeriksa riwayat dan status verifikasi pembayaran oleh Admin
PRECONDITION: Pembayaran telah disubmit pada USR-19. Admin telah melakukan verifikasi dan klik APPROVE.
STEPS:
1. Buka /app/invoices.
2. Buka tab atau accordion "Riwayat Pembayaran" pada invoice terkait.
3. Periksa status pembayaran yang baru diunggah.
EXPECTED: Status pembayaran tercatat "APPROVED" / "PAID" dengan tanggal dan waktu verifikasi. Status invoice utama otomatis ter-update menjadi "PAID" dengan badge hijau. Sisa tagihan menjadi Rp 0.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 

TEST ID: USR-21
ROLE: USER
MODULE: Payment
SCENARIO: Mengunggah ulang bukti pembayaran jika pembayaran sebelumnya ditolak (REJECTED) Admin
PRECONDITION: Admin menolak bukti pembayaran pengguna karena foto blur atau nominal tidak sesuai di mutasi rekening (status: REJECTED).
STEPS:
1. Buka /app/invoices.
2. Periksa invoice yang pembayarannya ditolak.
3. Baca catatan alasan penolakan dari Admin (misal: "Bukti transfer tidak terbaca/buram").
4. Klik tombol "Upload Ulang Bukti Bayar".
5. Isi data transfer valid dan upload berkas gambar baru yang jelas.
6. Klik "Kirim Bukti Pembayaran".
EXPECTED: Pengguna diizinkan mengunggah ulang bukti bayar tanpa perlu membuat booking baru. Payment baru berstatus "SUBMITTED" dan riwayat penolakan sebelumnya tetap tercatat rapi sebagai audit trail. Batas waktu invoice asli tidak ter-reset secara liar.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: USR-22
ROLE: USER
MODULE: Payment
SCENARIO: Melakukan pembayaran bertahap (Partial Payment) jika invoice mengizinkan DP / cicilan
PRECONDITION: Invoice total Rp 14.000.000 berstatus UNPAID.
STEPS:
1. Buka modal pembayaran invoice di /app/invoices.
2. Masukkan nominal transfer separuh dari tagihan: Rp 7.000.000.
3. Upload bukti transfer DP.
4. Klik "Kirim Bukti".
5. Simulasikan Admin menyetujui pembayaran tersebut.
6. Buka kembali halaman /app/invoices.
EXPECTED: Status invoice berubah menjadi "PARTIALLY_PAID". Sistem menampilkan informasi: Total Tagihan Rp 14.000.000, Telah Dibayar Rp 7.000.000, Sisa Tagihan (Outstanding) Rp 7.000.000. Tombol untuk melakukan pelunasan sisa tagihan tetap tersedia.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: USR-23
ROLE: USER
MODULE: Rental Tracking
SCENARIO: Memeriksa status operasional sewa alat berat di menu Rental Saya
PRECONDITION: Booking sudah PAID dan Admin telah menjalankan alur Dispatch -> Arrival -> ONGOING.
STEPS:
1. Klik menu sidebar "Rental Saya" (/app/rentals).
2. Temukan kontrak sewa aktif untuk proyek Tol Cisumdawu.
3. Periksa status sewa di tabel.
4. Klik tombol "Detail Rental".
EXPECTED: Status sewa menampilkan label "ONGOING" (Alat Beroperasi). Rincian menampilkan tanggal mulai operasional nyata, unit fisik yang bekerja, dan nama operator yang ditugaskan.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: USR-24
ROLE: USER
MODULE: Timesheet
SCENARIO: Memeriksa daftar lembar kerja harian (Timesheet) yang diinput oleh Admin
PRECONDITION: Alat berstatus ONGOING dan Admin telah menginput timesheet operasional hari ke-1.
STEPS:
1. Klik menu sidebar "Timesheet Harian" (/app/timesheets).
2. Periksa daftar timesheet yang membutuhkan peninjauan customer.
3. Klik tombol "Detail" pada baris timesheet terkait.
4. Periksa komponen: Tanggal Kerja, Jam Mulai, Jam Selesai, Jam Istirahat, Total Jam Efektif Bekerja (Actual Hours), Keterangan Pekerjaan Operator, dan Lampiran Foto Bukti Fisik/Hour Meter.
EXPECTED: Data lembar kerja tampil lengkap dan transparan. Tidak ada form penginputan timesheet baru karena customer tidak memiliki kewenangan membuat timesheet. Status timesheet adalah "SUBMITTED" (Menunggu Konfirmasi Customer).
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 

TEST ID: USR-25
ROLE: USER
MODULE: Timesheet
SCENARIO: Menyetujui dan menandatangani (Confirm & Sign) timesheet pekerjaan harian
PRECONDITION: Berada di modal detail timesheet USR-24 dengan status SUBMITTED.
STEPS:
1. Review rincian 8 jam kerja operator di lapangan.
2. Pada bagian Konfirmasi & Tanda Tangan:
   - Gambar tanda tangan digital pada kanvas tanda tangan interaktif ATAU upload file scan tanda tangan PIC proyek (`signature.png`).
   - Masukkan Nama Penandatangan: Hendra Setiawan (PIC Lapangan).
3. Klik tombol "Setujui & Tandatangani Timesheet".
EXPECTED: Muncul loading state, toast "Timesheet berhasil ditandatangani dan disetujui". Status timesheet ter-update menjadi terkonfirmasi (siap divalidasi final oleh Admin). Tanda tangan tersimpan pada rincian timesheet.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 

TEST ID: USR-26
ROLE: USER
MODULE: Invoice & Billing
SCENARIO: Memeriksa invoice penagihan kerja harian (Daily Work Invoice) hasil timesheet
PRECONDITION: Admin telah memvalidasi timesheet dan menerbitkan invoice Daily Work berdasarkan actual hours yang telah disetujui.
STEPS:
1. Buka menu sidebar "Tagihan & Bayar" (/app/invoices).
2. Cari invoice berkategori "DAILY_WORK" / Pemakaian Harian.
3. Klik tombol "Lihat Detail".
4. Verifikasi bahwa perhitungan tagihan didasarkan pada Jam Kerja Aktual (Actual Hours) dari timesheet yang sudah disahkan, tanpa pembulatan liar dan sesuai tarif per jam perjanjian.
EXPECTED: Rincian invoice Daily Work memuat jam kerja aktual x tarif per jam. Tertera nomor timesheet referensi. Status invoice "UNPAID" dengan deadline pembayaran 24 jam.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 

TEST ID: USR-27
ROLE: USER
MODULE: Payment
SCENARIO: Mengunggah bukti pembayaran untuk Daily Work Invoice
PRECONDITION: Berada pada Daily Work Invoice di USR-26.
STEPS:
1. Klik "Upload Bukti Bayar".
2. Masukkan nominal pembayaran pelunasan invoice harian.
3. Unggah file bukti transfer bank Mandiri/BCA.
4. Klik "Kirim Pembayaran".
EXPECTED: Pembayaran tersubmit dengan status SUBMITTED, invoice menunggu approval verifikasi kasir/admin.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: USR-28
ROLE: USER
MODULE: Payment History
SCENARIO: Memeriksa riwayat seluruh pembayaran yang pernah dilakukan customer
PRECONDITION: Customer telah melakukan beberapa kali pembayaran (DP sewa, pelunasan booking, dan daily work).
STEPS:
1. Buka menu /app/invoices.
2. Buka tab "Riwayat Semua Pembayaran".
3. Periksa daftar transaksi: Tanggal, Nomor Invoice, Bank Tujuan, Nominal, dan Status Verifikasi.
EXPECTED: Seluruh riwayat transaksi pembayaran tampil kronologis dan lengkap. Pengguna dapat mengklik bukti transfer untuk melihat gambar berkas yang pernah diunggah.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: MEDIUM
NOTES: 

TEST ID: USR-29
ROLE: USER
MODULE: Outstanding Balance
SCENARIO: Memeriksa total saldo tagihan berjalan yang belum dilunasi (Outstanding)
PRECONDITION: Customer memiliki transaksi yang berstatus UNPAID atau PARTIALLY_PAID.
STEPS:
1. Klik menu sidebar "Outstanding" (/app/outstanding).
2. Amati kartu ringkasan: Total Tagihan Tertunggak, Tagihan Jatuh Tempo, dan Daftar Invoice Terbuka.
EXPECTED: Nilai total outstanding mencerminkan akumulasi akurat dari seluruh sisa invoice yang belum lunas milik akun bersangkutan. Hanya data milik customer login yang ditampilkan (tidak bocor ke data customer lain).
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: USR-30
ROLE: USER
MODULE: Refund
SCENARIO: Memeriksa status pengembalian dana deposit atau kelebihan bayar (Refund Saya)
PRECONDITION: Terdapat pencatatan kelebihan bayar atau pembatalan sewa yang dialokasikan ke proses refund manual oleh Admin.
STEPS:
1. Klik menu sidebar "Refund Saya" (/app/refunds).
2. Periksa tabel permohonan pengembalian dana.
3. Klik "Detail" pada baris refund (status misal: APPROVED, PROCESSING, atau COMPLETED).
4. Jika status COMPLETED, periksa lampiran bukti transfer balik dari kasir perusahaan.
EXPECTED: Riwayat refund tampil dengan nominal, bank tujuan rekening customer, tanggal pengajuan, status proses, serta berkas bukti transfer pengembalian dana jika sudah selesai.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: USR-31
ROLE: USER
MODULE: Notification
SCENARIO: Memeriksa pemberitahuan sistem di lonceng notifikasi dan halaman notifikasi
PRECONDITION: Berbagai aksi sistem telah terjadi (booking disetujui, invoice diterbitkan, pembayaran diverifikasi, timesheet diterbitkan).
STEPS:
1. Amati ikon lonceng (🔔) pada navbar atas kanan. Periksa angka counter merah.
2. Klik ikon lonceng untuk membuka popup dropdown.
3. Klik salah satu item notifikasi (misal: "Invoice INV/... telah diterbitkan").
4. Verifikasi bahwa sistem me-redirect pengguna ke halaman detail yang relevan (/app/invoices).
5. Buka menu sidebar "Notifikasi" (/app/notifications) untuk melihat riwayat lengkap.
6. Klik tombol "Tandai Semua Sudah Dibaca".
EXPECTED: Notifikasi tersinkronisasi secara real-time. Angka badge counter pada lonceng hilang/berkurang setelah ditandai dibaca. Seluruh link notifikasi mengarah ke URL portal pengguna (/app/...) bukan URL admin.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: MEDIUM
NOTES: 

TEST ID: USR-32
ROLE: USER
MODULE: Profile
SCENARIO: Memeriksa ringkasan identitas dan keamanan kata sandi pada menu Profil
PRECONDITION: Login sebagai USER.
STEPS:
1. Klik menu sidebar "Profil" (/app/profile).
2. Periksa informasi akun login: Nama, Email, Peran (CUSTOMER/USER).
3. Buka tab "Keamanan / Ganti Password".
4. Masukkan Password Saat Ini: password
5. Masukkan Password Baru: PasswordBaru123!
6. Masukkan Konfirmasi Password Baru: PasswordBaru123!
7. Klik "Perbarui Password".
EXPECTED: Sistem memvalidasi dan berhasil memperbarui kata sandi. Muncul notifikasi sukses. Kata sandi baru langsung aktif saat sesi login berikutnya.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: MEDIUM
NOTES: 

TEST ID: USR-33
ROLE: USER
MODULE: Authentication
SCENARIO: Melakukan logout akhir sesi pengguna
PRECONDITION: Selesai menjalankan seluruh alur pengguna.
STEPS:
1. Klik menu "Keluar" pada navbar atas kanan atau bagian footer sidebar.
2. Konfirmasi tindakan keluar.
EXPECTED: Sesi token Sanctum dihapus dari browser, pengguna diarahkan kembali ke landing page utama (/), cache data sensitif dibersihkan dari penyimpanan lokal.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 
```

---

# C. ADMIN — TEST CASES END-TO-END (33 Langkah)

```
TEST ID: ADM-01
ROLE: ADMIN
MODULE: Authentication
SCENARIO: Login akun Admin Operasional
PRECONDITION: Browser berada di halaman login http://localhost:5173/login. Akun admin@rafarental.com tersedia.
STEPS:
1. Masukkan Email: admin@rafarental.com
2. Masukkan Password: password
3. Klik tombol "Masuk".
EXPECTED: Berhasil login, diarahkan otomatis ke dashboard operasional Admin (/admin). Navbar internal admin menampilkan identitas "Admin Operasional" dan menu navigasi landing page disembunyikan.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 

TEST ID: ADM-02
ROLE: ADMIN
MODULE: Master Data - Equipment Type
SCENARIO: Pengelolaan CRUD Master Tipe Alat Berat
PRECONDITION: Login sebagai ADMIN di /admin/equipment (Tab Tipe Alat).
STEPS:
1. Buka /admin/equipment dan pilih tab "Tipe Alat".
2. Klik tombol "Tambah Tipe Alat".
3. Masukkan Nama: "Crawler Crane", Deskripsi: "Alat pengangkat material berbobot berat dengan roda rantai". Klik "Simpan".
4. Verifikasi tipe "Crawler Crane" muncul di tabel.
5. Klik "Edit" pada Crawler Crane, ubah deskripsi menjadi: "Alat pengangkat kapasitas 50-100 ton". Klik "Simpan".
6. Klik tombol "Hapus" pada Crawler Crane yang belum memiliki model. Verifikasi berhasil terhapus.
7. Coba klik "Hapus" pada tipe "Excavator" yang sudah memiliki relasi model PC200-8.
EXPECTED: Create dan Update berhasil. Penghapusan tipe tanpa relasi berhasil. Penghapusan tipe "Excavator" ditolak oleh sistem dengan pesan error 409 Conflict bahwa tipe sedang digunakan oleh model alat.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: ADM-03
ROLE: ADMIN
MODULE: Master Data - Equipment Model
SCENARIO: Pengelolaan CRUD Master Model Alat Berat
PRECONDITION: Berada di /admin/equipment (Tab Model Alat).
STEPS:
1. Klik tab "Model Alat".
2. Klik "Tambah Model Alat".
3. Isi: Tipe: Bulldozer, Brand: Shantui, Nama Model: SD22, Kapasitas: 22, Satuan: Ton, Status: Aktif.
4. Klik "Simpan Model".
5. Verifikasi model baru muncul di daftar.
6. Klik tombol "Edit" pada Shantui SD22, ubah kapasitas menjadi 23 Ton, klik "Simpan".
7. Verifikasi perubahan tersimpan setelah refresh browser.
EXPECTED: Model berhasil dibuat dan diperbarui. Data baru langsung tersedia untuk pengikatan unit fisik dan tarif sewa.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: ADM-04
ROLE: ADMIN
MODULE: Master Data - Equipment Units
SCENARIO: Pengelolaan CRUD Unit Fisik Alat Berat (Physical Unit)
PRECONDITION: Berada di /admin/units. Model PC200-8 tersedia.
STEPS:
1. Buka menu sidebar "Unit Fisik" (/admin/units).
2. Klik "Tambah Unit Fisik".
3. Form: Pilih Model: Komatsu PC200-8, Nomor Seri: KM-PC200-TEST-99, Pelat Nomor: B 9999 RFA, Status: AVAILABLE, Hour Meter: 150.0, Tahun Pembuatan: 2023.
4. Klik "Simpan Unit".
5. Verifikasi unit muncul di tabel dengan status AVAILABLE.
6. Klik "Edit Unit", ubah Hour Meter menjadi 175.5, klik "Simpan".
7. Hapus unit yang baru dibuat jika belum pernah dipakai transaksi rental.
EXPECTED: CRUD unit fisik berjalan lancar. Status unit AVAILABLE siap dialokasikan ke booking.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: ADM-05
ROLE: ADMIN
MODULE: Master Data - Equipment Media
SCENARIO: Mengunggah dan mengganti foto model alat berat
PRECONDITION: Berada di /admin/equipment (Tab Model Alat).
STEPS:
1. Pada baris model Komatsu PC200-8, klik tombol "Kelola Foto" / "Upload Foto".
2. Pilih berkas gambar `pc200_foto.jpg`.
3. Klik "Unggah Berkas".
4. Verifikasi foto muncul di thumbnail dan preview.
5. Buka tab baru di browser dan akses landing page publik `http://localhost:5173/#equipment`.
6. Periksa carousel Armada Unggulan di landing page.
EXPECTED: Berkas foto terunggah sukses ke server. Landing page publik otomatis menampilkan foto model yang baru diunggah pada kartu armada tanpa delay atau broken link.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: ADM-06
ROLE: ADMIN
MODULE: Master Data - Pricing
SCENARIO: Pengelolaan Master Tarif & Harga Sewa Alat Berat
PRECONDITION: Admin memiliki kewenangan penuh atas master tarif. Buka /admin/pricing.
STEPS:
1. Buka menu sidebar "Tarif & Harga" (/admin/pricing).
2. Klik tombol "Buat Skema Tarif Baru".
3. Form: Model: Komatsu PC200-8, Paket: All-in, Tarif Dasar: Rp 360.000 / jam, Minimum Jam: 8 jam, Tarif Lembur: Rp 420.000 / jam, Tanggal Efektif: Hari ini.
4. Klik "Simpan Tarif".
5. Periksa riwayat versi tarif pada model tersebut.
EXPECTED: Tarif baru berhasil tersimpan. Sistem mencatat versi tarif secara append-only. Booking baru akan menggunakan tarif teranyar, sedangkan pesanan lama tetap menjaga historical tarif aslinya.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: ADM-07
ROLE: ADMIN
MODULE: Master Data - Bank Accounts
SCENARIO: Pengelolaan CRUD Rekening Bank Perusahaan
PRECONDITION: Buka menu sidebar "Rekening Perusahaan" (/admin/banks).
STEPS:
1. Klik "Tambah Rekening Bank".
2. Isi Nama Bank: "Bank BNI", Nomor Rekening: "0987654321", Nama Pemilik: "PT RAFA RENTAL NUSANTARA", Status: Aktif.
3. Klik "Simpan".
4. Verifikasi rekening BNI muncul di daftar.
5. Edit nomor rekening menjadi "0987654322", simpan.
6. Hapus rekening BNI yang baru dibuat (belum ada transaksi).
7. Coba hapus rekening BCA 1234567890 yang sudah memiliki data transaksi pembayaran sewa.
EXPECTED: Create, update, dan delete rekening baru berhasil. Penghapusan rekening yang sudah terkait transaksi pembayaran ditolak oleh validasi integrity business rule (409 Conflict).
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: ADM-08
ROLE: ADMIN
MODULE: Booking Operations
SCENARIO: Memeriksa dan memfilter daftar permohonan booking masuk dari pelanggan
PRECONDITION: Pengguna customer telah mengajukan booking baru (status: PENDING_APPROVAL).
STEPS:
1. Buka menu sidebar "Approval Booking" (/admin/bookings).
2. Amati daftar booking masuk.
3. Gunakan filter status: pilih "PENDING_APPROVAL".
4. Verifikasi data pemesan: Nama Perusahaan, Lokasi Proyek, Tanggal Pengajuan, Model Alat, dan Kuantitas Unit.
EXPECTED: Booking yang diajukan customer tampil pada baris teratas antrean approval lengkap dengan detail tanggal dan kebutuhan armada.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: ADM-09
ROLE: ADMIN
MODULE: Booking Operations
SCENARIO: Meninjau rincian kelayakan booking dan profil customer (Review Booking)
PRECONDITION: Berada di /admin/bookings.
STEPS:
1. Klik tombol "Review" / "Detail" pada booking yang berstatus PENDING_APPROVAL.
2. Periksa kelengkapan data: Dokumen profil perusahaan customer, verifikasi identitas, lokasi pekerjaan, ketersediaan unit fisik pada rentang tanggal tersebut.
EXPECTED: Seluruh informasi audit kelayakan sewa ditampilkan komprehensif, mencakup status ketersediaan armada fisik di gudang/pool.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: ADM-10
ROLE: ADMIN
MODULE: Booking Operations
SCENARIO: Menyetujui (Approve) atau Menolak (Reject) pemesanan sewa
PRECONDITION: Berada pada modal review booking USR-13 (PENDING_APPROVAL).
STEPS:
1. Untuk skenario Approve: Klik tombol "Setujui Booking" (Approve).
2. Masukkan catatan admin jika diperlukan.
3. Klik "Konfirmasi Persetujuan".
4. Untuk skenario Reject (uji di booking kedua): Klik tombol "Tolak Booking" (Reject), masukkan alasan penolakan wajib, klik "Konfirmasi Tolak".
EXPECTED: Pada booking pertama, status berubah menjadi "APPROVED". Notifikasi dikirimkan ke customer. Pada skenario tolak, status berubah menjadi "REJECTED" dan alasan penolakan tercatat.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 

TEST ID: ADM-11
ROLE: ADMIN
MODULE: Unit Assignment
SCENARIO: Menetapkan unit fisik alat berat spesifik (Assign Physical Unit) ke booking yang disetujui
PRECONDITION: Booking berstatus APPROVED. Terdapat unit PC200-8 dengan status AVAILABLE di sistem.
STEPS:
1. Pada booking yang sudah APPROVED di /admin/bookings, klik tombol "Tugaskan Unit" (Assign Unit).
2. Sistem menampilkan daftar unit fisik model PC200-8 yang AVAILABLE.
3. Pilih unit dengan Nomor Seri: `KM-PC200-001` (B 9101 RFA).
4. Klik tombol "Konfirmasi Penetapan Unit".
EXPECTED: Unit `KM-PC200-001` berhasil dialokasikan ke booking tersebut. Status unit fisik di master unit otomatis berubah dari "AVAILABLE" menjadi "ASSIGNED". Unit tersebut tidak dapat ditugaskan ke booking lain pada jadwal yang sama.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 

TEST ID: ADM-12
ROLE: ADMIN
MODULE: Unit Assignment
SCENARIO: Mengganti unit fisik yang telah ditugaskan sebelum pengiriman (Replace Unit)
PRECONDITION: Unit KM-PC200-001 telah ditugaskan, namun mengalami kendala teknis mendadak sebelum dikirim. Unit KM-PC200-002 AVAILABLE.
STEPS:
1. Pada detail booking /admin/bookings, buka bagian penugasan unit.
2. Klik tombol "Ganti Unit" (Replace Unit) pada baris KM-PC200-001.
3. Pilih unit pengganti: `KM-PC200-002` (B 9102 RFA).
4. Masukkan alasan penggantian: "Unit 001 memerlukan servis berkala mendadak".
5. Klik "Simpan Penggantian".
EXPECTED: Unit penugasan aktif berganti ke `KM-PC200-002`. Unit `KM-PC200-001` dilepas kembali ke status siap/servis, dan histori penggantian unit tercatat di audit log.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: ADM-13
ROLE: ADMIN
MODULE: Rental Lifecycle
SCENARIO: Melakukan pengiriman armada ke lokasi proyek pelanggan (Dispatch)
PRECONDITION: Booking berstatus CONFIRMED (pembayaran awal lunas) dan unit fisik telah ditetapkan. Buka /admin/rentals.
STEPS:
1. Buka menu sidebar "Eksekusi Rental" (/admin/rentals).
2. Temukan pesanan rental yang siap dikirim.
3. Klik tombol aksi "Kirim Armada" (Dispatch).
4. Masukkan nama transporter / supir truk tronton, nomor polisi truk pengangkut, dan estimasi waktu sampai.
5. Klik "Konfirmasi Dispatch".
EXPECTED: Status rental berubah dari ASSIGNED menjadi "DISPATCHED" (Dalam Perjalanan). Status unit fisik mencerminkan armada sedang dalam mobilisasi.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 

TEST ID: ADM-14
ROLE: ADMIN
MODULE: Rental Lifecycle
SCENARIO: Mengonfirmasi kedatangan armada di lokasi proyek (Confirm Arrival)
PRECONDITION: Rental berstatus DISPATCHED. Truk tronton telah tiba di lokasi proyek Tol Cisumdawu.
STEPS:
1. Pada /admin/rentals, temukan rental dengan status DISPATCHED.
2. Klik tombol "Konfirmasi Tiba di Lokasi" (Arrival).
3. Masukkan Waktu Kedatangan Aktual dan Nama Penerima PIC Lapangan.
4. Klik "Simpan Kedatangan".
EXPECTED: Status rental berpindah ke "ARRIVED". Sistem mencatat unit telah sampai di lokasi tujuan pelanggan dengan selamat dan siap diserahterimakan untuk beroperasi.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 

TEST ID: ADM-15
ROLE: ADMIN
MODULE: Rental Lifecycle
SCENARIO: Memulai masa operasional kerja sewa alat berat (Start / Confirm Ongoing)
PRECONDITION: Rental berstatus ARRIVED. Berita acara serah terima ditandatangani.
STEPS:
1. Pada /admin/rentals, klik tombol "Mulai Operasional" (Start Ongoing).
2. Masukkan Hour Meter (HM) Awal alat saat mulai beroperasi: 1250.50.
3. Masukkan Nama Operator yang ditugaskan: "Suryono".
4. Klik "Konfirmasi Mulai Kerja".
EXPECTED: Status rental resmi berubah menjadi "ONGOING" (Sedang Beroperasi). Unit tercatat aktif bekerja dan kini siap menerima pencatatan lembar kerja harian (Timesheet).
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 

TEST ID: ADM-16
ROLE: ADMIN
MODULE: Timesheet Management
SCENARIO: Menginput lembar kerja operasional harian berdasarkan laporan kerja operator lapangan
PRECONDITION: Rental berada dalam status ONGOING. Admin menerima formulir kerja fisik dari operator.
STEPS:
1. Buka menu sidebar "Validasi Timesheet" (/admin/timesheets).
2. Klik tombol "Input Timesheet Harian".
3. Form Input:
   - Pilih Rental Aktif: Komatsu PC200-8 di Tol Cisumdawu
   - Tanggal Kerja: Hari ini
   - Jam Mulai: 08:00
   - Jam Selesai: 17:00
   - Jam Istirahat (Break): 1.0 jam
   - Total Jam Efektif: Sistem otomatis menghitung 8.0 jam
   - Nama Operator: Suryono
   - Catatan Lapangan: "Pekerjaan galian lereng STA 14+200 berjalan lancar, cuaca cerah"
   - Upload Foto Form Fisik / HM Alat: Pilih gambar `timesheet_lapangan.jpg`
4. Klik "Simpan & Ajukan ke Customer" (Submit for Confirmation).
EXPECTED: Timesheet tersimpan dengan status "SUBMITTED". Lembar kerja otomatis muncul di portal customer untuk ditinjau dan ditandatangani oleh PIC proyek.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 

TEST ID: ADM-17
ROLE: ADMIN
MODULE: Timesheet Management
SCENARIO: Melakukan revisi / koreksi data lembar kerja timesheet (Edit & Correction)
PRECONDITION: Terdapat kesalahan ketik pada catatan atau jam istirahat sebelum customer menandatangani.
STEPS:
1. Pada baris timesheet terkait di /admin/timesheets, klik tombol "Koreksi / Revisi".
2. Ubah Jam Istirahat dari 1.0 jam menjadi 1.5 jam (sehingga Total Jam Efektif menjadi 7.5 jam).
3. Masukkan Alasan Koreksi: "Koreksi jam istirahat tambahan salat Jumat".
4. Klik "Simpan Koreksi".
EXPECTED: Data jam efektif terbarui menjadi 7.5 jam. Sistem mencatat versi perubahan dan alasan revisi.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: ADM-18
ROLE: ADMIN
MODULE: Timesheet Management
SCENARIO: Memeriksa riwayat revisi data lembar kerja (Revision History)
PRECONDITION: Timesheet telah mengalami koreksi pada ADM-17.
STEPS:
1. Klik tombol "Riwayat Revisi" (Revision Log) pada detail timesheet di /admin/timesheets.
2. Amati riwayat log yang ditampilkan.
EXPECTED: Tabel riwayat revisi menampilkan jejak audit yang immutable: Waktu Revisi, Nama Admin Pengubah, Nilai Lama (8.0 Jam), Nilai Baru (7.5 Jam), dan Alasan Koreksi.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: ADM-19
ROLE: ADMIN
MODULE: Timesheet Management
SCENARIO: Memvalidasi final atau menolak timesheet setelah ditandatangani customer
PRECONDITION: Customer telah menyetujui dan menandatangani timesheet pada USR-25.
STEPS:
1. Buka /admin/timesheets.
2. Buka detail timesheet yang telah memuat tanda tangan digital PIC customer.
3. Klik tombol "Validasi & Setujui" (Approve Timesheet).
4. Untuk skenario penolakan (uji di timesheet lain): Klik tombol "Tolak Timesheet" (Reject), masukkan alasan penolakan.
EXPECTED: Pada timesheet pertama, status berubah menjadi "APPROVED". Jam kerja aktual resmi terkunci dan siap dijadikan dasar penagihan Daily Work Invoice.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 

TEST ID: ADM-20
ROLE: ADMIN
MODULE: Invoice Management
SCENARIO: Menerbitkan tagihan sewa / tagihan pemakaian harian (Create & Issue Invoice)
PRECONDITION: Booking APPROVED untuk tagihan awal ATAU Timesheet APPROVED untuk tagihan Daily Work.
STEPS:
1. Buka menu sidebar "Invoice" (/admin/invoices).
2. Klik tombol "Buat Invoice Baru".
3. Pilih sumber penagihan: Booking awal atau Berdasarkan Timesheet yang sudah APPROVED.
4. Periksa baris rincian: Jam kerja aktual x tarif per jam yang berlaku historis, tanpa pembulatan desimal.
5. Klik tombol "Terbitkan Invoice" (Issue Invoice).
EXPECTED: Nomor invoice resmi terbentuk (misal: INV/20261001/0002). Status invoice berubah dari DRAFT menjadi "UNPAID". Jatuh tempo otomatis ditetapkan 24 jam dari waktu terbit. Invoice langsung muncul di portal customer.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 

TEST ID: ADM-21
ROLE: ADMIN
MODULE: Payment Verification
SCENARIO: Meninjau bukti transfer pembayaran yang dikirimkan customer (Review Payment Proof)
PRECONDITION: Customer telah mengunggah bukti bayar (status SUBMITTED). Buka /admin/payments.
STEPS:
1. Buka menu sidebar "Verifikasi Pembayaran" (/admin/payments).
2. Temukan pembayaran masuk dari PT Maju Konstruksi Jaya.
3. Klik "Lihat Detail Bukti Bayar".
4. Periksa: Berkas gambar transfer, nama bank pengirim, nomor rekening tujuan, nominal transfer, tanggal transaksi, dan nomor referensi.
EXPECTED: Berkas bukti transfer tampil jelas (dapat di-zoom atau dibuka di tab baru). Rincian transaksi cocok dengan tagihan invoice terkait.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: ADM-22
ROLE: ADMIN
MODULE: Payment Verification
SCENARIO: Menyetujui (Approve) atau Menolak (Reject) pembayaran customer
PRECONDITION: Berada di modal verifikasi pembayaran ADM-21.
STEPS:
1. Cocokkan nominal transfer dengan mutasi bank riil.
2. Jika valid: Klik tombol "Setujui Pembayaran" (Approve).
3. Jika tidak valid / dana belum masuk: Klik tombol "Tolak Pembayaran" (Reject), isi alasan wajib, klik konfirmasi.
EXPECTED: Saat Approve, status pembayaran berubah menjadi "APPROVED". Dana terakumulasi ke invoice. Saat Reject, status menjadi "REJECTED", customer mendapatkan notifikasi alasan, dan batas jatuh tempo invoice asli tetap mengacu pada issued_at + 24 jam.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 

TEST ID: ADM-23
ROLE: ADMIN
MODULE: Payment Verification
SCENARIO: Memverifikasi pembayaran sebagian (Partial Payment) dan pembaruan status invoice
PRECONDITION: Customer membayar DP Rp 7.000.000 untuk invoice Rp 14.000.000.
STEPS:
1. Buka /admin/payments, review pembayaran Rp 7.000.000.
2. Klik tombol "Setujui Pembayaran".
3. Buka menu /admin/invoices dan periksa status invoice terkait.
EXPECTED: Invoice ter-update menjadi "PARTIALLY_PAID". Kolom Paid Amount tercatat Rp 7.000.000 dan Kolom Outstanding Balance tercatat sisa Rp 7.000.000.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: ADM-24
ROLE: ADMIN
MODULE: Payment Verification
SCENARIO: Memverifikasi kelebihan pembayaran (Overpayment) dan pencatatan surplus dana
PRECONDITION: Tagihan invoice Rp 14.000.000. Customer mentransfer Rp 15.000.000 (surplus Rp 1.000.000).
STEPS:
1. Pada /admin/payments, temukan pembayaran dengan nominal Rp 15.000.000.
2. Klik tombol "Setujui Pembayaran".
3. Periksa status invoice di /admin/invoices.
EXPECTED: Status invoice berubah menjadi "OVERPAID". Sistem mencatat Paid: Rp 15.000.000, Overpayment Amount: Rp 1.000.000. Kelebihan dana dicatat secara transparan dan tidak melakukan auto-refund liar tanpa instruksi.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: ADM-25
ROLE: ADMIN
MODULE: Credit Control
SCENARIO: Memantau saldo piutang tertunggak seluruh customer di menu Outstanding
PRECONDITION: Buka /admin/outstanding.
STEPS:
1. Buka menu sidebar "Outstanding" (/admin/outstanding).
2. Periksa tabel rekap piutang per pelanggan.
3. Filter berdasarkan nama pelanggan: "PT Maju Konstruksi Jaya".
4. Klik tombol "Rincian Tagihan Tertunggak".
EXPECTED: Menampilkan total piutang berjalan yang belum lunas per entitas bisnis, daftar invoice tertunggak, umur piutang, dan kontak penagihan PIC.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: ADM-26
ROLE: ADMIN
MODULE: Refund Processing
SCENARIO: Memproses dan menyelesaikan pengembalian dana manual (Refund Process & Complete)
PRECONDITION: Terdapat refund yang disetujui (status APPROVED). Buka /admin/refunds.
STEPS:
1. Buka menu sidebar "Refund" (/admin/refunds).
2. Pilih refund dari PT Maju Konstruksi Jaya (misal kelebihan bayar Rp 1.000.000).
3. Klik tombol "Proses Transfer Bank" (status menjadi PROCESSING).
4. Setelah kasir melakukan transfer manual melalui internet banking, klik tombol "Selesaikan Refund" (Complete).
5. Unggah berkas bukti transfer pengembalian dana dari bank (`bukti_refund.jpg`).
6. Masukkan nomor referensi pengembalian. Klik "Simpan".
EXPECTED: Status refund berubah menjadi "COMPLETED". Bukti transfer tersimpan dan dapat dilihat oleh customer dan owner. Riwayat audit keuangan immutable.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: ADM-27
ROLE: ADMIN
MODULE: Rental Lifecycle - Demobilization
SCENARIO: Melakukan proses pengembalian armada dari lokasi proyek (Return Demobilization)
PRECONDITION: Masa sewa proyek telah berakhir. Rental berstatus ONGOING.
STEPS:
1. Buka menu /admin/rentals.
2. Pada rental proyek Tol Cisumdawu, klik tombol "Proses Pengembalian" (Return).
3. Masukkan Waktu Pengambilan, Nama Transporter Penjemput, dan HM Akhir alat di lapangan: 1306.50.
4. Klik "Konfirmasi Pengembalian".
EXPECTED: Status rental berpindah ke "RETURNED" / "INSPECTION_PENDING". Unit fisik TIDAK BOLEH langsung berstatus AVAILABLE, melainkan berstatus pemeriksaan (INSPECTION).
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 

TEST ID: ADM-28
ROLE: ADMIN
MODULE: Equipment Inspection
SCENARIO: Melakukan inspeksi fisik pasca-sewa dan menentukan status ketersediaan unit
PRECONDITION: Unit alat berat baru kembali dari proyek dan tiba di pool (status inspeksi pending).
STEPS:
1. Buka menu /admin/rentals atau /admin/units.
2. Klik tombol "Lakukan Inspeksi Unit" pada armada Komatsu PC200-8 (KM-PC200-001).
3. Isi checklist inspeksi teknis:
   - Kondisi Engine / Mesin: Baik
   - Sistem Hidrolik: Baik, tidak ada kebocoran oli
   - Undercarriage / Track: Keausan normal 10%
   - Bucket & Boom: Utuh tanpa keretakan
   - Hour Meter Terakhir Terverifikasi: 1306.50
4. Tentukan Keputusan Kesiapan Unit:
   - Opsi A: "AVAILABLE" (Alat Prima & Siap Disewa Kembali)
   - Opsi B: "MAINTENANCE" (Perlu Servis Rutin / Penggantian Oli)
   - Opsi C: "DAMAGED" (Ada Kerusakan yang Memerlukan Klaim)
5. Pilih Opsi A: "AVAILABLE" dan klik "Simpan Hasil Inspeksi".
EXPECTED: Unit fisik KM-PC200-001 resmi kembali berstatus "AVAILABLE" di master data dan siap dialokasikan ke booking berikutnya. Seluruh data jam kerja dan catatan teknis tersimpan di riwayat armada.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 

TEST ID: ADM-29
ROLE: ADMIN
MODULE: Notification
SCENARIO: Memeriksa notifikasi operasional pada panel Admin
PRECONDITION: Terjadi event pemesanan baru, submit bukti bayar, dan tanda tangan timesheet oleh customer.
STEPS:
1. Amati counter lonceng notifikasi navbar admin (/admin).
2. Klik lonceng notifikasi, klik salah satu notifikasi operasional.
3. Klik tombol "Tandai Dibaca".
EXPECTED: Notifikasi internal operasional tertampil lengkap dengan pengelompokan yang jelas. Navigasi link langsung membuka modul admin yang bersangkutan (misal /admin/payments).
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: MEDIUM
NOTES: 

TEST ID: ADM-30
ROLE: ADMIN
MODULE: Reports
SCENARIO: Memeriksa laporan rekapitulasi operasional dan laporan finansial Admin
PRECONDITION: Transaksi sewa dan pembayaran telah berlangsung di sistem.
STEPS:
1. Buka sidebar "Laporan" -> "Laporan Operasional" (/admin/reports/operational).
2. Periksa rekap: Utilisasi Armada, Jam Operasi per Unit, dan Riwayat Timesheet.
3. Buka "Laporan Finansial" (/admin/reports/financial).
4. Periksa rekap: Total Penagihan, Total Pembayaran Diterima, Piutang Berjalan.
EXPECTED: Laporan menampilkan data agregat operasional dan finansial secara akurat berdasarkan catatan transaksi aktual.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: ADM-31
ROLE: ADMIN
MODULE: Dashboard
SCENARIO: Memeriksa seluruh metrik KPI operasional dan grafik pada Dashboard Admin
PRECONDITION: Login sebagai ADMIN di /admin.
STEPS:
1. Buka dashboard utama Admin (/admin).
2. Periksa 8 kartu KPI: Booking Pending, Booking Berjalan, Unit Disewa, Unit Ready, Total Pendapatan, dsb.
3. Periksa visualisasi grafik SVG/CSS: Status Booking, Utilisasi Unit, dan Perbandingan Billing vs Payment.
4. Ubah filter rentang tanggal dashboard (misal: Bulan Ini).
EXPECTED: Dashboard beroperasi cepat tanpa library chart eksternal yang lambat. Nilai KPI dan grafik memperbarui data secara konsisten mengikuti filter tanggal.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: ADM-32
ROLE: ADMIN
MODULE: CMS Management
SCENARIO: Mengubah konten landing page publik melalui modul CMS Admin
PRECONDITION: Buka /admin/cms.
STEPS:
1. Buka menu sidebar "CMS Landing Page" (/admin/cms).
2. Pada Bagian Brand: Ubah Slogan Perusahaan.
3. Pada Bagian Hero: Ubah Headline Utama menjadi "Solusi Sewa Alat Berat Terpercaya & Berpengalaman di Jawa Barat".
4. Upload gambar Hero baru atau icon logo baru.
5. Klik tombol "Simpan Perubahan CMS".
6. Buka tab baru browser dan akses landing page publik `http://localhost:5173/`.
EXPECTED: Muncul toast "Perubahan CMS berhasil disimpan". Halaman publik langsung mencerminkan teks headline baru dan gambar baru tanpa perlu restart server atau hard clear cache.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: ADM-33
ROLE: ADMIN
MODULE: Authentication
SCENARIO: Melakukan logout dari akun Admin
PRECONDITION: Berada di /admin.
STEPS:
1. Klik tombol "Keluar" pada navbar atas kanan admin.
2. Konfirmasi logout.
3. Coba akses kembali /admin melalui URL address bar.
EXPECTED: Sesi admin dibersihkan total. Akses langsung ke /admin dialihkan paksa ke halaman login (/login).
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 
```

---

# D. OWNER — TEST CASES END-TO-END (15 Langkah)

```
TEST ID: OWN-01
ROLE: OWNER
MODULE: Dashboard - Executive Summary
SCENARIO: Login Owner dan memeriksa tampilan ringkasan eksekutif bisnis
PRECONDITION: Akun owner@rafarental.com tersedia. Browser di /login.
STEPS:
1. Login dengan email `owner@rafarental.com` dan password `password`.
2. Sistem otomatis mengarahkan ke portal Owner (/owner).
3. Periksa tampilan banner selamat datang bernuansa aksen emas/kuning, ketiadaan tombol lonceng notifikasi, dan menu sidebar khusus Owner (Executive Summary, Laporan Pendapatan, Audit Trail Logs, Rekening & Kontrol).
EXPECTED: Pengguna berhasil masuk ke dashboard eksekutif Owner. Tampilan clean, profesional, dan berorientasi pengawasan manajerial.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 

TEST ID: OWN-02
ROLE: OWNER
MODULE: Dashboard - Date Filter
SCENARIO: Menggunakan filter rentang tanggal pada ringkasan eksekutif Owner
PRECONDITION: Berada di /owner.
STEPS:
1. Klik tombol dropdown filter tanggal (Hari Ini, 7 Hari Terakhir, Bulan Ini, Tahun Ini, atau Kustom Dari - Sampai).
2. Pilih filter: "Bulan Ini".
3. Amati pembaruan seluruh komponen angka KPI dan visualisasi grafik.
EXPECTED: Data metrik dan grafik otomatis memuat ulang angka sesuai periode yang dipilih tanpa error network atau blank page.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: OWN-03
ROLE: OWNER
MODULE: Dashboard - KPI Cards
SCENARIO: Memeriksa akurasi kartu indikator kinerja utama (KPI Metrics)
PRECONDITION: Berada di /owner.
STEPS:
1. Periksa kartu KPI Pendapatan Bersih (Net Revenue).
2. Periksa kartu KPI Total Pembayaran Diterima (Cash Inflow).
3. Periksa kartu KPI Piutang Belum Tertagih (Outstanding Receivables).
4. Periksa kartu KPI Tingkat Utilisasi Alat Berat (% Unit Bekerja vs Total Armada).
5. Periksa kartu KPI Booking Aktif & Selesai.
EXPECTED: Seluruh indikator KPI menampilkan nilai numerik nyata (format Rupiah dan Persentase). Tidak ada indikator yang bernilai "NaN", "undefined", atau teks placeholder palsu.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: OWN-04
ROLE: OWNER
MODULE: Dashboard - Visual Charts
SCENARIO: Memeriksa grafik tren pendapatan, utilisasi alat, dan komposisi rental
PRECONDITION: Berada di /owner.
STEPS:
1. Amati Grafik Tren Pendapatan Bulanan (Area / Bar SVG).
2. Arahkan kursor mouse (hover) pada baris titik grafik untuk melihat tooltip nominal.
3. Amati Diagram Donat Utilisasi Armada (Bekerja, Siap, Servis).
4. Amati Grafik Komposisi Kategori Alat Terlaris.
EXPECTED: Grafik SVG ter-render tajam, responsif mengikuti ukuran layar, tooltip interaktif memunculkan angka nominal yang presisi.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: OWN-05
ROLE: OWNER
MODULE: Dashboard - Data Summary
SCENARIO: Memeriksa ringkasan bisnis komparatif 3-kolom
PRECONDITION: Berada di /owner.
STEPS:
1. Scroll ke bagian bawah dashboard Owner.
2. Periksa kolom Ringkasan Operasional Armada.
3. Periksa kolom Rekapitulasi Tagihan & Pembayaran Terbesar.
4. Periksa kolom Kinerja Proyek Berjalan.
EXPECTED: Ketiga kolom menyajikan insight data bisnis yang padat, akurat, dan mudah dipahami dalam pengambilan keputusan level direksi.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: OWN-06
ROLE: OWNER
MODULE: Revenue Report
SCENARIO: Membuka dan meninjau Laporan Pendapatan komprehensif
PRECONDITION: Berada di portal Owner.
STEPS:
1. Klik menu sidebar "Laporan Pendapatan" (/owner/revenue).
2. Periksa tampilan tabel laporan pendapatan: Nomor Invoice, Tanggal Terbit, Pelanggan, Proyek, Total Tagihan Pokok, Nilai Terbayar, Sisa Piutang, dan Status Pembayaran.
EXPECTED: Laporan tersaji dalam format tabel finansial formal. Seluruh transaksi penagihan dan pelunasan tercantum transparan.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: OWN-07
ROLE: OWNER
MODULE: Revenue Report - Filter
SCENARIO: Melakukan pencarian dan penyaringan data laporan pendapatan
PRECONDITION: Berada di /owner/revenue.
STEPS:
1. Masukkan kata kunci nama pelanggan pada kolom cari: "PT Maju".
2. Pilih filter status pembayaran: "PAID".
3. Tentukan rentang tanggal transaksi.
4. Klik tombol "Terapkan Filter".
5. Klik tombol "Reset Filter".
EXPECTED: Tabel seketika memfilter hanya transaksi lunas dari PT Maju Konstruksi Jaya. Tombol Reset mengembalikan tabel ke daftar utuh.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: MEDIUM
NOTES: 

TEST ID: OWN-08
ROLE: OWNER
MODULE: Revenue Report - Detail
SCENARIO: Memeriksa modal rincian transaksi penagihan pada laporan pendapatan
PRECONDITION: Berada di /owner/revenue.
STEPS:
1. Klik ikon mata (Lihat Detail) pada salah satu baris invoice.
2. Periksa modal rincian pendapatan: Breakdown jam kerja actual, tarif per jam, detail MOB/DEMOB, riwayat pembayaran, serta bukti transfer bank yang divalidasi admin.
EXPECTED: Modal menyajikan audit keuangan menyeluruh hingga ke berkas bukti transfer tanpa ada yang disembunyikan.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: OWN-09
ROLE: OWNER
MODULE: Revenue Report - Export
SCENARIO: Mengekspor laporan pendapatan ke format berkas spreadsheet / PDF jika tersedia
PRECONDITION: Berada di /owner/revenue. Tombol Export tersedia.
STEPS:
1. Klik tombol "Export Laporan" (CSV / Excel / PDF).
2. Tunggu browser mengunduh berkas.
3. Buka berkas yang diunduh.
EXPECTED: Berkas terunduh dengan nama yang sesuai (misal `laporan-pendapatan-2026.csv`). Isi tabel di berkas identik dengan data yang disaring di layar.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: MEDIUM
NOTES: 

TEST ID: OWN-10
ROLE: OWNER
MODULE: Audit Trail Logs
SCENARIO: Membuka halaman log jejak audit operasional dan sistem (Audit Trail Logs)
PRECONDITION: Buka menu sidebar "Audit Trail Logs" (/owner/audit).
STEPS:
1. Klik menu sidebar "Audit Trail Logs" (/owner/audit).
2. Periksa tampilan tabel: Waktu Kejadian, Aktor / Customer, Tindakan / Perubahan Status, Entitas Terkait, Jam Operasi, Nilai Pembayaran, dan Tombol Detail.
EXPECTED: Halaman memuat riwayat aktivitas operasional nyata dari database (bukan data mock atau placeholder kosong). Menampilkan seluruh kronologi perubahan dari waktu ke waktu.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: OWN-11
ROLE: OWNER
MODULE: Audit Trail Logs - Filter
SCENARIO: Menyaring jejak audit berdasarkan pencarian teks, status tindakan, dan tanggal
PRECONDITION: Berada di /owner/audit.
STEPS:
1. Masukkan kata kunci pada search box (misal: "Budi" atau kode booking).
2. Pilih filter dropdown Status / Tipe Aksi (misal: "CONFIRMED" atau "ASSIGNED").
3. Pilih rentang tanggal.
4. Klik tombol navigasi halaman (Pagination) ke halaman 2, lalu kembali ke halaman 1.
EXPECTED: Filter menyaring baris log secara responsif. Pagination 10 data per halaman berfungsi mulus tanpa me-reset filter pencarian.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: MEDIUM
NOTES: 

TEST ID: OWN-12
ROLE: OWNER
MODULE: Audit Trail Logs - Detail Modal
SCENARIO: Membuka modal inspeksi detail jejak audit rekaman aktivitas
PRECONDITION: Berada di /owner/audit.
STEPS:
1. Klik ikon mata (Detail) pada salah satu baris log audit.
2. Amati modal pop-up inspeksi detail.
3. Periksa atribut rekaman: ID Log, Waktu Lengkap, Pengguna Terkait, Status Awal vs Status Baru (jika ada), Nomor Dokumen, dan Nilai Nominal Terkait.
4. Klik tombol "Tutup".
EXPECTED: Modal membuka rincian lengkap tanpa error. Informasi audit transparan dan modal dapat ditutup dengan mulus.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: OWN-13
ROLE: OWNER
MODULE: Control & Governance
SCENARIO: Memeriksa daftar rekening penampung dan kontrol rekening perusahaan
PRECONDITION: Buka menu sidebar "Rekening & Kontrol" (/owner/settings).
STEPS:
1. Klik menu sidebar "Rekening & Kontrol".
2. Periksa daftar rekening bank resmi yang digunakan perusahaan untuk menerima setoran sewa.
3. Periksa informasi Nama Bank, Nomor Rekening, Atas Nama Rekening, dan Status Keaktifan.
EXPECTED: Owner dapat memantau seluruh rekening bank penerima kas perusahaan secara transparan.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: HIGH
NOTES: 

TEST ID: OWN-14
ROLE: OWNER
MODULE: Authorization & Security
SCENARIO: Memastikan peran Owner memiliki akses penuh membaca dan memonitor seluruh data sistem
PRECONDITION: Login sebagai Owner.
STEPS:
1. Jelajahi menu Executive Summary, Laporan Pendapatan, Audit Trail, dan Rekening Bank.
2. Akses laporan agregasi finansial dan operasional.
EXPECTED: Seluruh data terbaca lengkap tanpa ada pembatasan data finansial atau operasional (Full Read Authority).
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 

TEST ID: OWN-15
ROLE: OWNER
MODULE: Authorization & Security
SCENARIO: Memastikan peran Owner TIDAK DAPAT melakukan mutasi operasional (Read-Only Governance)
PRECONDITION: Login sebagai Owner.
STEPS:
1. Buka URL langsung modul operasional admin melalui address bar: `http://localhost:5173/admin/bookings` atau `http://localhost:5173/admin/timesheets`.
2. Periksa apakah Owner memiliki tombol untuk meng-approve booking, menginput timesheet, atau mengubah data armada.
3. Coba lakukan HTTP POST mutasi tarif atau penugasan unit secara paksa.
EXPECTED: Sistem membatasi peran Owner pada fungsi pengawasan (Governance/Monitoring). Akses mutasi operasional harian ditolak secara tegas (403 Forbidden / Read-Only), mencegah benturan kepentingan antara fungsi kepemilikan dan eksekusi operasional.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 
```

---

# E. CROSS-ROLE END-TO-END WORKFLOW (1 Transaksi Terhubung)

Pengujian satu siklus hidup sewa terpadu menggunakan ID transaksi yang sama dari awal pendaftaran hingga armada siap disewa kembali.

```
TEST ID: CROSS-01
ROLE: MULTI-ROLE (USER -> ADMIN -> USER -> ADMIN -> USER -> ADMIN)
MODULE: Full Rental Lifecycle E2E
SCENARIO: Eksekusi transaksi sewa tunggal Komatsu PC200-8 dari pengajuan sewa, pembayaran DP, pengiriman, operasional harian, konfirmasi timesheet, billing pemakaian, pelunasan, hingga return dan inspeksi unit.
PRECONDITION: Database dalam keadaan bersih atau siap menerima transaksi baru. Pengguna customer budi@kontraktor.com siap di browser window A. Admin admin@rafarental.com siap di browser window B (Incognito).
STEPS:
1. [USER - Window A] 
   - Login sebagai budi@kontraktor.com.
   - Buka Katalog Alat, pilih "Komatsu PC200-8".
   - Pilih paket "Non All-in", kuantitas 1 unit.
   - Masukkan ke keranjang sewa.
   - Di keranjang sewa (/app/cart), pilih Lokasi Proyek: "Proyek Tol Cisumdawu Seksi 4".
   - Pilih Tanggal Mulai: 2026-10-10, Selesai: 2026-10-16 (7 hari).
   - Klik "Submit Booking".
   - Catat KODE BOOKING yang terbentuk (Contoh: RFA-BKG-20261010-0099). Status: PENDING_APPROVAL.

2. [ADMIN - Window B]
   - Buka /admin/bookings.
   - Temukan booking dengan kode RFA-BKG-20261010-0099.
   - Klik "Review", lalu klik "Setujui Booking" (Approve). Status menjadi APPROVED.
   - Klik "Tugaskan Unit" (Assign Unit).
   - Pilih unit fisik: `KM-PC200-001` (B 9101 RFA). Konfirmasi. Status unit menjadi ASSIGNED.
   - Buka /admin/invoices, buat dan terbitkan Invoice Pemesanan Awal (INV/RENTAL/0099) senilai Rp 14.000.000 (Sewa Pokok + MOB/DEMOB). Jatuh tempo 24 jam.

3. [USER - Window A]
   - Buka /app/invoices.
   - Verifikasi Invoice INV/RENTAL/0099 muncul dengan status UNPAID.
   - Klik "Upload Bukti Bayar".
   - Pilih Bank BCA, masukkan nominal Rp 14.000.000, unggah file bukti transfer `transfer_bca_lunas.jpg`.
   - Submit pembayaran. Status invoice: PENDING / SUBMITTED.

4. [ADMIN - Window B]
   - Buka /admin/payments.
   - Temukan pembayaran dari Budi Santoso untuk INV/RENTAL/0099.
   - Periksa bukti bayar, klik "Setujui Pembayaran" (Approve).
   - Status invoice menjadi PAID. Status booking menjadi CONFIRMED.
   - Buka /admin/rentals.
   - Temukan rental Tol Cisumdawu, klik "Kirim Armada" (Dispatch). Status: DISPATCHED.
   - Klik "Konfirmasi Tiba" (Arrival). Status: ARRIVED.
   - Klik "Mulai Operasional" (Start Ongoing). Masukkan HM Awal: 1250.50, Operator: Suryono. Status: ONGOING.

5. [ADMIN - Window B]
   - Buka /admin/timesheets.
   - Klik "Input Timesheet Harian".
   - Masukkan jam kerja hari ke-1: 08:00 s/d 17:00, break 1 jam (Total: 8.0 jam).
   - Upload foto jam kerja operator.
   - Klik "Simpan & Ajukan ke Customer". Status: SUBMITTED.

6. [USER - Window A]
   - Buka /app/timesheets.
   - Temukan timesheet hari ke-1 yang diajukan Admin.
   - Klik "Detail", periksa jam kerja 8.0 jam.
   - Tanda tangani kanvas digital atas nama Hendra Setiawan (PIC Lapangan).
   - Klik "Setujui & Tandatangani Timesheet".

7. [ADMIN - Window B]
   - Buka /admin/timesheets.
   - Buka timesheet yang sudah ditandatangani user.
   - Klik "Validasi & Setujui" (Approve Timesheet). Status: APPROVED.
   - Buka /admin/invoices.
   - Terbitkan Daily Work Invoice berdasarkan timesheet approved (8 jam x Rp 225.000 = Rp 1.800.000).
   - Issue invoice resmi (INV/DAILY/0099).

8. [USER - Window A]
   - Buka /app/invoices.
   - Lihat invoice INV/DAILY/0099 sebesar Rp 1.800.000.
   - Unggah bukti pembayaran pelunasan daily work transfer Mandiri.

9. [ADMIN - Window B]
   - Buka /admin/payments, verifikasi dan Approve pembayaran INV/DAILY/0099. Status: PAID.
   - Buka /admin/rentals.
   - Selesaikan masa rental, klik "Proses Pengembalian" (Return Demobilization).
   - Masukkan HM Akhir: 1258.50. Status rental: RETURNED. Status unit: INSPECTION.
   - Lakukan inspeksi alat berat: Periksa hidrolik, mesin, track, tidak ada kerusakan.
   - Pilih Keputusan: "AVAILABLE".
   - Simpan hasil inspeksi. Status unit KM-PC200-001 kembali AVAILABLE.

10. [OWNER - Window C]
    - Login sebagai owner@rafarental.com.
    - Buka /owner dan /owner/revenue.
    - Verifikasi transaksi sewa RFA-BKG-20261010-0099, invoice INV/RENTAL/0099, dan INV/DAILY/0099 tercermin akurat pada grafik cash inflow, KPI pendapatan, dan laporan audit trail log.
EXPECTED: Seluruh alur multi-role berjalan tanpa diskoneksi data. Data yang diinput pada peran satu langsung muncul dan sinkron pada peran berikutnya tanpa delay, tanpa korupsi data, dan memenuhi seluruh aturan operasional.
ACTUAL: 
PASS/FAIL: 
BUG ID: 
SEVERITY: CRITICAL
NOTES: 
```

---

# F. NEGATIVE TESTING (15 Kasus Uji Ekstrem)

```
TEST ID: NEG-01
ROLE: Guest / USER
MODULE: Authentication
SCENARIO: Percobaan login dengan password salah
STEPS: Masukkan email terdaftar budi@kontraktor.com dan password salah "Salah123!". Klik Masuk.
EXPECTED: Login ditolak dengan pesan error yang jelas ("Kredensial tidak sesuai"). Tidak ada crash aplikasi.
ACTUAL: 
PASS/FAIL: 

TEST ID: NEG-02
ROLE: Guest / USER
MODULE: Authentication
SCENARIO: Registrasi dengan email yang sudah terdaftar
STEPS: Pada form register, masukkan email budi@kontraktor.com yang sudah ada di database. Klik Daftar.
EXPECTED: Ditolak dengan pesan validasi "Email sudah digunakan" (HTTP 422).
ACTUAL: 
PASS/FAIL: 

TEST ID: NEG-03
ROLE: USER
MODULE: Form Validation
SCENARIO: Submit form pendaftaran dengan field wajib dikosongkan
STEPS: Kosongkan nama lengkap dan password, klik Submit.
EXPECTED: Form menolak pengiriman di sisi klien; input yang kosong ditandai border merah dengan teks pesan wajib diisi.
ACTUAL: 
PASS/FAIL: 

TEST ID: NEG-04
ROLE: USER
MODULE: Form Validation
SCENARIO: Format input nomor telepon dan email tidak valid
STEPS: Masukkan format email "budi.bukan.email" dan nomor telepon "abcde".
EXPECTED: Validasi format memblokir pengiriman dan menampilkan pesan format tidak sesuai.
ACTUAL: 
PASS/FAIL: 

TEST ID: NEG-05
ROLE: USER
MODULE: Authorization / IDOR
SCENARIO: Customer A mencoba mengakses detail booking milik Customer B melalui URL langsung
STEPS: Login sebagai siti@tambang.com, lalu arahkan browser ke URL booking milik Budi: `/app/bookings/:id_milik_budi`.
EXPECTED: Sistem memblokir akses dan mengembalikan respons 403 Forbidden atau 404 Not Found. Tidak ada data milik Budi yang bocor.
ACTUAL: 
PASS/FAIL: 

TEST ID: NEG-06
ROLE: USER
MODULE: Authorization / Privilege Escalation
SCENARIO: Customer mencoba mengakses URL panel Admin
STEPS: Login sebagai USER biasa, ketikkan URL `http://localhost:5173/admin` pada address bar.
EXPECTED: RoleRoute mencegat akses, pengguna otomatis diredirect ke halaman /forbidden atau /app.
ACTUAL: 
PASS/FAIL: 

TEST ID: NEG-07
ROLE: USER
MODULE: Booking Integrity
SCENARIO: Submit booking berkali-kali secara simultan (Double Submit)
STEPS: Pada halaman /app/cart, klik tombol "Ajukan Sewa" berkali-kali secara cepat (rapid click).
EXPECTED: Tombol langsung disabled saat klik pertama (loading state). Hanya satu booking yang terbentuk di database; tidak ada duplikasi pesanan ganda.
ACTUAL: 
PASS/FAIL: 

TEST ID: NEG-08
ROLE: USER
MODULE: Payment Upload Validation
SCENARIO: Mengunggah berkas bukti bayar dengan format terlarang (.exe / .pdf)
STEPS: Pada modal upload bukti bayar, pilih berkas `script_jahat.exe` atau dokumen tidak didukung.
EXPECTED: Validasi berkas menolak unggahan dengan pesan "Hanya berkas gambar (JPG, JPEG, PNG, WEBP) yang diperbolehkan".
ACTUAL: 
PASS/FAIL: 

TEST ID: NEG-09
ROLE: USER
MODULE: Payment Upload Validation
SCENARIO: Mengunggah berkas bukti bayar dengan ukuran melebihi batas maksimal (> 5MB)
STEPS: Pilih berkas gambar `foto_ukuran_8mb.png` untuk upload bukti bayar.
EXPECTED: Sistem menolak unggahan dan menampilkan peringatan bahwa batas maksimal ukuran berkas adalah 2MB/5MB.
ACTUAL: 
PASS/FAIL: 

TEST ID: NEG-10
ROLE: USER
MODULE: Payment Validation
SCENARIO: Memasukkan nominal pembayaran nol (0) atau angka negatif
STEPS: Pada modal pembayaran invoice, ketikkan nominal: "0" atau "-500000". Klik Kirim.
EXPECTED: Validasi memblokir pengiriman dengan pesan nominal pembayaran harus lebih besar dari 0.
ACTUAL: 
PASS/FAIL: 

TEST ID: NEG-11
ROLE: USER
MODULE: Booking Availability
SCENARIO: Memilih tanggal sewa yang sudah lewat di masa lalu (Historical Date)
STEPS: Di keranjang sewa, pilih Tanggal Mulai: 2020-01-01 dan Selesai: 2020-01-05.
EXPECTED: Komponen kalender membatasi tanggal minimal adalah hari ini/besok (H+1). Form menolak tanggal masa lalu.
ACTUAL: 
PASS/FAIL: 

TEST ID: NEG-12
ROLE: ADMIN
MODULE: Unit Assignment Conflict
SCENARIO: Menugaskan unit fisik yang sama ke dua booking berbeda pada rentang tanggal bersamaan
STEPS: Booking 1 dan Booking 2 menyewa PC200-8 pada tanggal yang sama. Admin menugaskan unit KM-PC200-001 ke Booking 1. Coba tugaskan lagi unit KM-PC200-001 ke Booking 2.
EXPECTED: Unit KM-PC200-001 tidak muncul di daftar pilihan unit available untuk Booking 2 (atau sistem melempar error conflict 409).
ACTUAL: 
PASS/FAIL: 

TEST ID: NEG-13
ROLE: ADMIN
MODULE: Lifecycle Transition Guard
SCENARIO: Memaksa transisi status ilegal (contoh: Dispatch sebelum unit ditetapkan, atau Return sebelum Arrived)
STEPS: Coba lakukan dispatch pada rental yang belum memiliki unit assignment melalui manipulasi permintaan.
EXPECTED: State machine backend menolak transisi status ilegal dengan exception "Invalid status transition".
ACTUAL: 
PASS/FAIL: 

TEST ID: NEG-14
ROLE: USER
MODULE: Timesheet Authority
SCENARIO: Customer mencoba membuat atau menginput timesheet secara langsung
STEPS: Periksa antarmuka customer di /app/timesheets untuk mencari tombol form input timesheet. Coba tembak endpoint POST /api/v1/timesheets dengan token user.
EXPECTED: Di browser tidak ada form atau tombol input timesheet bagi customer. Permintaan API ditolak 403 Forbidden.
ACTUAL: 
PASS/FAIL: 

TEST ID: NEG-15
ROLE: OWNER
MODULE: Governance Authorization
SCENARIO: Owner mencoba melakukan mutasi perubahan harga sewa atau persetujuan rental
STEPS: Login sebagai Owner, coba lakukan aksi POST/PUT pada tarif sewa atau approval booking.
EXPECTED: Sistem membatasi Owner pada mode Read-Only / Monitoring. Aksi mutasi operasional ditolak dengan kode 403 Forbidden.
ACTUAL: 
PASS/FAIL: 
```

---

# G. UX BLACK-BOX CHECKLIST (15 Item)

| No | Komponen UX yang Diperiksa Manual | Kriteria Expected | PASS/FAIL | Catatan Pengujian |
|---|---|---|---|---|
| UX-01 | **Loading State & Skeleton** | Skeleton/spinner muncul saat pengambilan data asynchronous dan hilang seketika setelah data diterima. | | |
| UX-02 | **Anti-Infinite Loading** | Tidak ada bug infinite loading atau infinite skeleton saat berpindah halaman atau dalam mode React StrictMode. | | |
| UX-03 | **Empty State** | Saat tabel/daftar tidak memiliki data (kosong), muncul ilustrasi dan teks panduan yang jelas (misal: "Belum ada pesanan aktif"). | | |
| UX-04 | **Error State** | Saat terjadi kegagalan jaringan atau server 500, antarmuka menampilkan kartu error yang user-friendly disertai tombol "Coba Lagi". | | |
| UX-05 | **Toast Notifications** | Toast feedback (sukses/gagal) muncul di pojok layar dengan durasi 3-4 detik dan otomatis menghilang. | | |
| UX-06 | **Anti-False Notification** | Tidak ada notifikasi palsu (misal: toast "Gagal memuat" padahal data di layar berhasil tertampil sempurna). | | |
| UX-07 | **Modal & Dialogs** | Modal terbuka dengan backdrop lembut, fokus trap terjaga, dapat ditutup dengan tombol [X], tombol Batal, atau klik di luar modal. | | |
| UX-08 | **Button States** | Tombol menampilkan teks loading dan atribut `disabled` saat proses request berlangsung untuk mencegah double action. | | |
| UX-09 | **Form Validation Feedback** | Input yang tidak valid menampilkan highlight border merah dan teks panduan tepat di bawah bidang input yang bersangkutan. | | |
| UX-10 | **Sidebar Navigation** | Sidebar menampilkan menu grouped yang collapsible, link aktif tersorot jelas (aksen warna role), dan scroll independen dari konten. | | |
| UX-11 | **Browser Back / Forward** | Menekan tombol Back dan Forward browser tidak merusak state aplikasi dan navigasi URL sinkron. | | |
| UX-12 | **Page Refresh (F5 Persistence)** | Melakukan refresh browser pada halaman manapun tidak memicu logout mendadak atau kehilangan data form yang sedang aktif. | | |
| UX-13 | **Mobile Responsive Layout** | Tampilan pada layar ponsel (390px lebar) tidak mengalami horizontal overflow / scrolling patah, kartu menyusun ke bawah dengan rapi. | | |
| UX-14 | **Mobile Navigation Drawer** | Menu mobile hamburger membuka drawer navigasi secara mulus dan tertutup otomatis saat tautan diklik. | | |
| UX-15 | **Role Accent Mapping** | Warna aksen antarmuka konsisten: Admin dan Owner menampilkan aksen kuning/emas pada tombol dan header sesuai design token. | | |

---

# H. BUSINESS RULE VALIDATION CHECKLIST (22 Aturan Bisnis)

| No | Aturan Bisnis Inti (Business Rule) | Validasi Perilaku Aplikasi | PASS/FAIL |
|---|---|---|---|
| BR-01 | **Booking Approval Workflow** | Alur status booking harus berjalan ketat: DRAFT -> PENDING_APPROVAL -> APPROVED (atau REJECTED) -> CONFIRMED. Status SUBMITTED ditiadakan sebagai dead-state. | |
| BR-02 | **1 Booking = 1 Project Location** | Setiap dokumen booking hanya diperbolehkan terikat pada tepat 1 lokasi proyek tujuan pengiriman. | |
| BR-03 | **Model vs Physical Unit Separation** | Customer hanya memilih Model Alat (spesifikasi), sedangkan hak memilih Unit Fisik konkret sepenuhnya berada di tangan Admin. | |
| BR-04 | **Availability Pre-Check** | Sistem harus memvalidasi ketersediaan kuantitas unit model sebelum booking diizinkan tersubmit. | |
| BR-05 | **Dispatch ≠ Ongoing** | Armada yang sedang dikirim (DISPATCHED) tidak boleh dihitung sebagai masa operasional kerja sampai tiba (ARRIVED) dan dimulai (ONGOING). | |
| BR-06 | **Admin Confirms Arrival** | Konfirmasi kedatangan armada di lokasi proyek harus diverifikasi secara eksplisit oleh Admin/Operator. | |
| BR-07 | **Timesheet from Actual Field Work** | Data lembar kerja timesheet wajib bersumber dari jam kerja nyata di lapangan dan disertai catatan operator. | |
| BR-08 | **Admin Input / Validation Timesheet** | Pembuatan dan pengesahan final lembar kerja timesheet dilakukan oleh Admin. | |
| BR-09 | **User Confirm & Sign Timesheet** | Customer/PIC proyek wajib meninjau dan membubuhkan tanda tangan konfirmasi sebelum timesheet dapat disahkan. | |
| BR-10 | **Actual Hours as Billing Basis** | Dasar perhitungan penagihan tagihan harian (Daily Work) wajib bersumber dari Total Jam Kerja Aktual yang telah di-APPROVED. | |
| BR-11 | **No Rounding on Actual Hours** | Tidak boleh ada pembulatan sepihak terhadap jam kerja aktual (misal: 7.5 jam dihitung tepat 7.5 jam, bukan dibulatkan ke 8 jam). | |
| BR-12 | **No Overtime Tariff** | Tarif lembur tidak diberlakukan jika perjanjian kontrak menetapkan tarif flat per jam kerja efektif. | |
| BR-13 | **All-in vs Non All-in Enforcement** | Ketentuan paket All-in (termasuk BBM & Operator) dan Non All-in (alat saja) harus diterapkan konsisten per rincian sewa. | |
| BR-14 | **MOB/DEMOB per Physical Unit** | Biaya mobilisasi dan demobilisasi dibebankan spesifik per unit fisik yang dikirim menggunakan truk tronton pengangkut. | |
| BR-15 | **Historical Price Snapshot Immutability**| Perubahan master tarif di kemudian hari TIDAK BOLEH mengubah harga sewa pada invoice atau booking yang telah diterbitkan sebelumnya. | |
| BR-16 | **1 Invoice to Multiple Payments** | Satu dokumen invoice dapat menerima beberapa kali pembayaran (pembayaran bertahap / cicilan / DP). | |
| BR-17 | **1 Payment to Exactly 1 Invoice** | Satu transaksi bukti pembayaran hanya dapat dialokasikan pada tepat satu dokumen invoice. | |
| BR-18 | **Re-upload on Rejected Payment** | Jika pembayaran ditolak Admin karena bukti tidak jelas, customer berhak mengunggah ulang bukti bayar tanpa batas waktu invoice ter-reset. | |
| BR-19 | **Payment Deadline Fixed 24h** | Batas waktu pembayaran invoice awal dihitung tetap 24 jam sejak invoice diterbitkan pertama kali. | |
| BR-20 | **Overpayment Non-Auto-Refund** | Kelebihan pembayaran tidak boleh ditransfer balik otomatis oleh sistem tanpa proses audit verifikasi manual kasir. | |
| BR-21 | **Unit Non-Available After Return** | Unit yang baru kembali dari proyek (RETURNED) tidak boleh langsung berstatus AVAILABLE sebelum melalui tahap inspeksi teknis. | |
| BR-22 | **Inspection Dictates Unit Readiness** | Status kesiapan unit selanjutnya (AVAILABLE, MAINTENANCE, atau DAMAGED) ditentukan sepenuhnya oleh formulir hasil inspeksi. | |
| BR-23 | **Audit & Financial History Immutability**| Dokumen finansial (Invoice, Payment, Refund) dan rekaman log audit tidak boleh dapat di-hard-delete oleh siapapun. | |

---

# I. FORMAT LAPORAN PENGUJIAN BLACK-BOX

Setiap temuan pengujian manual wajib dicatat mengikuti format standar berikut:

```
TEST ID: [Kode Test Case, Contoh: USR-13 atau BUG-ADM-04]
ROLE: [USER / ADMIN / OWNER / GUEST]
MODULE: [Nama Modul, Contoh: Booking, Timesheet, Payment]
SCENARIO: [Deskripsi skenario yang sedang diuji]
PRECONDITION: [Kondisi awal lingkungan dan data sebelum langkah dimulai]
STEPS:
1. [Langkah 1]
2. [Langkah 2]
3. [Langkah 3]
EXPECTED: [Hasil yang seharusnya terjadi menurut aturan bisnis]
ACTUAL: [Hasil nyata yang terjadi di browser saat pengujian manual]
PASS/FAIL: [PASS / FAIL / BLOCKED]
BUG ID: [Diisi jika FAIL, Contoh: BUG-001]
SEVERITY: [CRITICAL / HIGH / MEDIUM / LOW]
NOTES: [Catatan tambahan tester, respons jaringan, atau pesan konsol]
SCREENSHOT: [Tautan atau nama berkas tangkapan layar jika ada]
```

### Definisi Tingkat Keparahan (Severity Level):
- **CRITICAL:** Sistem crash, data hilang/korup, alur transaksi utama terputus total tanpa jalan keluar (blocker), atau celah keamanan fatal (bypass role / IDOR).
- **HIGH:** Fitur utama gagal berfungsi sesuai aturan bisnis, perhitungan tagihan salah, atau mutasi data ditolak tanpa alasan yang valid.
- **MEDIUM:** Fitur sekunder bermasalah (misal: filter pencarian tidak responsif, format tampilan tanggal tidak rapi), namun ada alternatif solusi.
- **LOW:** Masalah kosmetik minor, salah ketik teks label (typo), sedikit inkonsistensi padding/margin yang tidak mengganggu alur operasional.
