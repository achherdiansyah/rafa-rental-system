# Business Rules Specification V3 - RAFA Rental System

Dokumen spesifikasi aturan bisnis resmi untuk RAFA Rental System (Phase 1B).

---

## 1. User & Profile

### BR-001: Pendaftaran dan Verifikasi Profil Pengguna
- **ID:** BR-001
- **Nama Rule:** Pendaftaran Akun dan Verifikasi Identitas
- **Actor:** USER, ADMIN
- **Trigger:** Pengguna mendaftar akun baru dan melengkapi profil identitas/perusahaan.
- **Kondisi:** Format data valid, nomor telepon dan email belum terdaftar.
- **Aturan:** Pengguna wajib melengkapi data profil (KTP/NPWP/nama perusahaan/alamat) sebelum dapat melakukan checkout booking. Admin dapat memverifikasi keabsahan dokumen identitas pengguna.
- **Hasil:** Status profil menjadi aktif/terverifikasi.
- **Exception:** Data duplikat atau dokumen tidak terbaca; sistem menolak pendaftaran/pengajuan verifikasi.
- **Dampak Teknis:** Tabel `users`, `user_profiles`, validasi upload file dokumen identitas di local disk storage.

### BR-002: Manajemen Hak Akses Multi-Role
- **ID:** BR-002
- **Nama Rule:** Isolasi Role (USER, ADMIN, OWNER)
- **Actor:** Sistem, USER, ADMIN, OWNER
- **Trigger:** Setiap request API yang membutuhkan autentikasi.
- **Kondisi:** Token autentikasi valid.
- **Aturan:**
  - **USER:** Hanya dapat mengakses data booking, invoice, timesheet, dan profil miliknya sendiri.
  - **ADMIN:** Mengelola operasional booking, assignment unit fisik, validasi payment, validasi timesheet, dan data master armada.
  - **OWNER:** Memiliki visibilitas penuh terhadap data finansial, audit log, laporan agregat bisnis, dan supervisi operasional.
- **Hasil:** Akses diberikan sesuai hak role atau dikembalikan HTTP 403 Forbidden.
- **Dampak Teknis:** Laravel Middleware & Gate/Policy Authorization (Sanctum).

---

## 2. Project Location

### BR-003: Single Project Location per Booking
- **ID:** BR-003
- **Nama Rule:** Satu Booking Terikat Satu Lokasi Proyek
- **Actor:** USER, Sistem
- **Trigger:** Pengguna membuat booking dari keranjang belanja.
- **Kondisi:** Keranjang berisi satu atau lebih item equipment.
- **Aturan:** Satu transaksi booking hanya boleh memiliki tepat satu alamat/lokasi proyek (Single Destination Project Location). Jika pengguna membutuhkan alat untuk lokasi berbeda, pengguna wajib membuat booking terpisah.
- **Hasil:** Entitas `booking` memiliki foreign key tunggal ke `project_locations` atau menyimpan data snapshot alamat proyek secara spesifik.
- **Exception:** Tidak ada pengecualian. Multi-lokasi dalam 1 booking dilarang keras.
- **Dampak Teknis:** Kolom `project_location_id` / snapshot kolom alamat di tabel `bookings`.

### BR-004: Detail Alamat dan Kontak Lokasi Proyek
- **ID:** BR-004
- **Nama Rule:** Kelengkapan Data Lokasi Proyek
- **Actor:** USER
- **Trigger:** Input data lokasi pengiriman/kerja unit.
- **Kondisi:** Alamat proyek diisi.
- **Aturan:** Lokasi proyek wajib mencantumkan nama proyek, alamat lengkap, kota/kabupaten, nama penanggung jawab lapangan (PIC), dan nomor telepon PIC lapangan.
- **Hasil:** Data lokasi proyek tersimpan dan diikatkan pada booking.
- **Exception:** PIC tidak valid atau nomor kontak tidak aktif.
- **Dampak Teknis:** Validasi field request store booking/location.

---

## 3. Equipment

### BR-005: Pemisahan Equipment Model dan Physical Unit
- **ID:** BR-005
- **Nama Rule:** Abstraksi Katalog vs Unit Fisik
- **Actor:** Sistem, ADMIN, USER
- **Trigger:** Penayangan katalog dan pencatatan inventaris.
- **Kondisi:** Data master unit dibuat.
- **Aturan:**
  - Pengguna hanya melihat dan menyewa berdasarkan model/tipe peralatan (*Equipment Type / Model*), spesifikasi, dan kuota ketersediaan.
  - Admin mengelola inventaris unit fisik (*Physical Unit / Serial Number / No Polisi / No Lambung*).
- **Hasil:** Katalog menampilkan spesifikasi umum tipe alat dan ketersediaan kuota tanpa mengekspos nomor seri unit individual ke user.
- **Exception:** Tidak ada.
- **Dampak Teknis:** Relasi `equipment_types` (1) ke (N) `physical_units`.

### BR-006: Status Lifecycle Unit Fisik
- **ID:** BR-006
- **Nama Rule:** State Lifecycle Unit Fisik
- **Actor:** ADMIN, Sistem
- **Trigger:** Perubahan fase operasional unit.
- **Kondisi:** Unit berpindah status (Tersedia, Dialokasikan, Operasi, Servis, Rusak).
- **Aturan:** Unit fisik harus memiliki status mutlak: `AVAILABLE`, `ASSIGNED`, `MOBILIZING`, `ON_SITE`, `DEMOBILIZING`, `MAINTENANCE`, `DECOMMISSIONED`. Unit berstatus selain `AVAILABLE` tidak dapat di-assign ke booking baru pada rentang waktu yang sama.
- **Hasil:** State mesin unit terjaga konsisten.
- **Exception:** Admin force-maintenance unit saat kondisi darurat.
- **Dampak Teknis:** ENUM/Status column di tabel `physical_units` + log perpindahan status.

---

## 4. Cart

### BR-007: Keranjang Sewa Terikat Scope Lokasi Tunggal
- **ID:** BR-007
- **Nama Rule:** Validasi Keranjang Belanja
- **Actor:** USER
- **Trigger:** Menambahkan item sewa ke keranjang belanja.
- **Kondisi:** Sesi belanja aktif.
- **Aturan:** Item dalam keranjang belanja mencatat tipe alat, jumlah unit (kuota), rentang tanggal mulai-selesai sewa, opsi All-in / Non All-in per line item, dan target lokasi proyek tunggal.
- **Hasil:** Item tersimpan dalam cart session / cart database user.
- **Exception:** Jika user mengubah lokasi proyek utama, sistem memberikan konfirmasi bahwa seluruh item akan disesuaikan ke lokasi baru.
- **Dampak Teknis:** Tabel `cart_items` dengan kolom `equipment_type_id`, `quantity`, `start_date`, `end_date`, `is_all_in`.

---

## 5. Booking

### BR-008: Abstraksi Pemilihan Unit (User Dilarang Memilih Physical Unit)
- **ID:** BR-008
- **Nama Rule:** User Tidak Memilih Physical Unit
- **Actor:** USER
- **Trigger:** Checkout booking dari keranjang belanja.
- **Kondisi:** Kuota ketersediaan tipe alat mencukupi.
- **Aturan:** Pengguna **DILARANG** dan **TIDAK DAPAT** memilih nomor seri/unit fisik individual. Pengguna hanya memesan kuota kapasitas tipe alat (`equipment_type_id` + `quantity`).
- **Hasil:** Booking tercatat dalam status `PENDING_APPROVAL` dengan item berupa tipe alat dan kuota.
- **Exception:** Tidak ada pengecualian.
- **Dampak Teknis:** Tabel `booking_items` hanya menyimpan `equipment_type_id`, bukan `physical_unit_id`.

### BR-009: Persetujuan Booking oleh Admin
- **ID:** BR-009
- **Nama Rule:** Approval Booking Admin
- **Actor:** ADMIN
- **Trigger:** Review booking masuk berstatus `PENDING_APPROVAL`.
- **Kondisi:** Verifikasi kelayakan sewa, jadwal ketersediaan unit, dan kesesuaian lokasi.
- **Aturan:**
  - Admin dapat menyetujui (`APPROVE`) atau menolak (`REJECT`) booking.
  - Saat booking disetujui, sistem secara otomatis menerbitkan Invoice dan mengunci reservasi kuota.
- **Hasil:** Status booking berubah menjadi `APPROVED` dan record invoice terbuat.
- **Exception:** Jika ditolak (`REJECTED`), admin wajib memasukkan alasan penolakan dan kuota reservasi dilepas.
- **Dampak Teknis:** State machine booking: `PENDING_APPROVAL` -> `APPROVED` / `REJECTED`. Trigger create record di tabel `invoices`.

### BR-010: Auto-Expiration Booking yang Tidak Dibayar
- **ID:** BR-010
- **Nama Rule:** Booking Expired Otomatis 24 Jam
- **Actor:** Sistem (Scheduled Worker)
- **Trigger:** Scheduler cron job berjalan (`php artisan schedule:run`).
- **Kondisi:** Invoice telah terbit lebih dari 24 jam dan belum ada pembayaran valid/lunas (atau minimal DP terverifikasi).
- **Aturan:** Sistem secara otomatis mengubah status booking dan invoice menjadi `EXPIRED`.
- **Hasil:** Booking dibatalkan otomatis dan kuota ketersediaan alat langsung dilepas kembali ke pool publik.
- **Exception:** Admin memberikan perpanjangan toleransi manual (PENDING BUSINESS DECISION).
- **Dampak Teknis:** Cron job artisan query `invoices WHERE status = 'UNPAID' AND due_at < NOW()`.

---

## 6. Availability

### BR-011: Perhitungan Ketersediaan dengan Buffer Waktu
- **ID:** BR-011
- **Nama Rule:** Kuota Ketersediaan dan Buffer Operasional
- **Actor:** Sistem
- **Trigger:** Pengecekan ketersediaan saat browse katalog, add to cart, checkout, atau reschedule.
- **Kondisi:** Rentang tanggal sewa (`start_date` s.d. `end_date`).
- **Aturan:** Ketersediaan dihitung dari: `Total Physical Units Aktif` - `Unit yang Dibooking/Disewa/Maintenance pada rentang tanggal + Buffer Waktu MOB/DEMOB/Inspeksi`.
- **Hasil:** Jumlah unit yang dapat disewa (*available capacity*) tampil akurat.
- **Exception:** Jika kuota = 0, user tidak dapat melakukan checkout untuk tanggal tersebut.
- **Dampak Teknis:** Query agregasi ketersediaan berbasis interval waktu pada tabel `booking_items` + `unit_assignments` + `maintenance_logs`.

### BR-012: Pelepasan Otomatis Unit/Kuota yang Expired
- **ID:** BR-012
- **Nama Rule:** Auto-Release Quota on Expiration/Cancellation
- **Actor:** Sistem
- **Trigger:** Booking berstatus `EXPIRED` atau `CANCELLED`.
- **Kondisi:** Booking sebelumnya memegang kuota reservasi.
- **Aturan:** Seluruh kuota dan assignment unit fisik yang terikat pada booking yang expired/dibatalkan harus segera dilepas secara real-time ke pool availability.
- **Hasil:** Kapasitas inventaris kembali tersedia untuk disewa user lain.
- **Exception:** Tidak ada.
- **Dampak Teknis:** Database transaction saat update status expired/cancelled melepaskan lock kuota.

---

## 7. Unit Assignment

### BR-013: Alokasi Unit Fisik Eksklusif oleh Admin
- **ID:** BR-013
- **Nama Rule:** Admin Unit Assignment
- **Actor:** ADMIN
- **Trigger:** Booking telah berstatus `APPROVED` / `PAID`.
- **Kondisi:** Unit fisik berstatus `AVAILABLE` pada rentang jadwal proyek.
- **Aturan:** Admin menentukan dan mengalokasikan unit fisik spesifik (`physical_unit_id`) untuk setiap item pada booking sebelum proses mobilisasi/handover dilakukan.
- **Hasil:** Terbentuk record `unit_assignments` yang mengikat `booking_item_id` dengan `physical_unit_id`.
- **Exception:** Unit fisik yang dipilih mengalami kendala teknis saat inspeksi pra-kirim; Admin dapat melakukan re-assignment ke unit fisik lain dengan tipe yang sama.
- **Dampak Teknis:** Tabel `unit_assignments` (`id`, `booking_item_id`, `physical_unit_id`, `assigned_by`, `assigned_at`, `status`).

---

## 8. Rental Operations

### BR-014: Mobilisasi dan Demobilisasi (MOB/DEMOB) Berbasis Unit Fisik
- **ID:** BR-014
- **Nama Rule:** Biaya dan Eksekusi MOB/DEMOB per Physical Unit
- **Actor:** ADMIN, Sistem
- **Trigger:** Persetujuan booking dan dispatch unit ke lokasi proyek.
- **Kondisi:** Unit fisik telah di-assign.
- **Aturan:** Biaya dan pencatatan mobilisasi (pengiriman unit ke lokasi) serta demobilisasi (penarikan unit kembali ke pool) dihitung dan dieksekusi **per physical unit** yang dikirim, bukan per line item agregat.
- **Hasil:** Log MOB/DEMOB dan biaya terkait terikat pada nomor unit fisik masing-masing.
- **Exception:** Pengambilan unit mandiri oleh penyewa (Self-pickup) jika diizinkan (PENDING BUSINESS DECISION).
- **Dampak Teknis:** Tabel `mobilization_logs` terhubung ke `unit_assignments` dan `physical_units`.

### BR-015: Berita Acara Serah Terima (BAST) & Handover
- **ID:** BR-015
- **Nama Rule:** Validasi BAST Check-in dan Check-out
- **Actor:** ADMIN, USER
- **Trigger:** Unit tiba di lokasi (Check-in) dan unit ditarik dari lokasi (Check-out).
- **Kondisi:** Inspeksi kondisi fisik unit dan pencatatan jam kerja awal (hour meter / HM awal).
- **Aturan:** Serah terima unit wajib disertai dokumen BAST dan foto kondisi fisik serta angka HM/KM awal. Pengembalian wajib mencatat HM/KM akhir dan catatan kerusakan jika ada.
- **Hasil:** Status rental berjalan (`ACTIVE`) saat check-in, dan berubah menjadi `COMPLETED` saat check-out disetujui.
- **Exception:** Terjadi kerusakan saat pengembalian; admin menerbitkan klausa denda/biaya perbaikan.
- **Dampak Teknis:** Tabel `rental_handovers` menyimpan checklist kondisi, HM start/end, foto dokumen BAST.

---

## 9. Timesheet

### BR-016: Pencatatan Jam Operasional Unit (Timesheet)
- **ID:** BR-016
- **Nama Rule:** Input Timesheet Harian/Periodik
- **Actor:** USER, ADMIN (Operator)
- **Trigger:** Periode sewa berjalan aktif.
- **Kondisi:** Unit berstatus `ON_SITE` / `ACTIVE`.
- **Aturan:** Jam kerja operasional unit (Hour Meter harian / jam standby / jam breakdown) dicatat secara periodik per unit fisik.
- **Hasil:** Data pemakaian jam tercatat pada sistem untuk perhitungan overtime atau validasi minimum rental hours.
- **Exception:** Rental berbasis durasi hari murni non-HM (PENDING BUSINESS DECISION).
- **Dampak Teknis:** Tabel `timesheets` (`id`, `unit_assignment_id`, `date`, `start_hm`, `end_hm`, `total_hours`, `breakdown_hours`, `status`).

### BR-017: Immutability Histori Revisi Timesheet
- **ID:** BR-017
- **Nama Rule:** Histori Revisi Timesheet Tidak Boleh Hilang
- **Actor:** USER, ADMIN
- **Trigger:** Pengajuan koreksi atau perubahan data timesheet yang sudah pernah diinput.
- **Kondisi:** Ditemukan ketidaksesuaian jam kerja lapangan.
- **Aturan:** Data timesheet dapat direvisi, tetapi record histori lama **TIDAK BOLEH DIHAPUS / DIOVERWRITE**. Sistem wajib menyimpan snapshot versi sebelumnya beserta alasan perubahan, tanggal revisi, dan user pengubah.
- **Hasil:** Seluruh log versi timesheet tersimpan rapi untuk kebutuhan audit.
- **Exception:** Tidak ada pengecualian.
- **Dampak Teknis:** Tabel `timesheet_revisions` / `timesheet_audit_logs` atau skema versioning `version_number` dan `is_current`.

### BR-018: Validasi dan Approval Timesheet oleh Admin
- **ID:** BR-018
- **Nama Rule:** Admin Validasi Timesheet
- **Actor:** ADMIN
- **Trigger:** Timesheet disubmit oleh user/operator.
- **Kondisi:** Timesheet berstatus `SUBMITTED`.
- **Aturan:** Admin wajib memverifikasi kecocokan data fisik/bukti lapangan dan menyetujui (`APPROVED`) atau menolak (`REJECTED`) timesheet sebelum dapat dikonversi menjadi tagihan tambahan / penutupan sewa.
- **Hasil:** Status timesheet menjadi `APPROVED` dan siap di-invoicing jika ada selisih jam kerja.
- **Exception:** Penolakan timesheet mewajibkan admin mencantumkan catatan revisi.
- **Dampak Teknis:** State machine timesheet: `DRAFT` -> `SUBMITTED` -> `APPROVED` / `REJECTED`.

---

## 10. Pricing

### BR-019: Skema All-in vs Non All-in per Equipment Line
- **ID:** BR-019
- **Nama Rule:** Opsi All-in / Non All-in per Item Alat
- **Actor:** USER, Sistem
- **Trigger:** Penentuan konfigurasi sewa di keranjang/booking.
- **Kondisi:** Tipe alat mendukung kedua opsi skema harga.
- **Aturan:**
  - Pilihan skema sewa ditentukan secara independen **per equipment line** (bukan global 1 booking seragam).
  - **All-in:** Biaya sewa sudah mencakup operator, bahan bakar (BBM), dan maintenance rutin harian.
  - **Non All-in:** Biaya sewa hanya unit saja; BBM dan akomodasi/biaya operator ditanggung penyewa.
- **Hasil:** Komponen perhitungan rate per jam/hari disesuaikan dengan formula yang dipilih pada masing-masing item.
- **Exception:** Alat tertentu yang wajib disewa dengan skema All-in saja (misal alat berat khusus berisiko tinggi).
- **Dampak Teknis:** Flag `is_all_in` (boolean) dan `rate_type` pada tabel `booking_items`.

### BR-020: Struktur Perhitungan Total Biaya Sewa
- **ID:** BR-020
- **Nama Rule:** Formula Kalkulasi Nilai Booking
- **Actor:** Sistem
- **Trigger:** Perhitungan total tagihan booking dan invoice.
- **Kondisi:** Booking item dan lokasi terdefinisi.
- **Aturan:** `Total Tagihan = Sum(Equipment Rental Price x Durasi x Qty) + Sum(MOB/DEMOB per physical unit) + Biaya Tambahan/Deposit (jika ada) - Diskon + Pajak (PPN jika berlaku)`.
- **Hasil:** Nilai tagihan subtotal, pajak, dan total akhir terkalkulasi presisi.
- **Exception:** Nilai PPN / PPh: PENDING BUSINESS DECISION.
- **Dampak Teknis:** Helper/Service kalkulasi kalkulator harga server-side di Laravel.

---

## 11. Invoice

### BR-021: Penerbitan Invoice Pasca Persetujuan Booking
- **ID:** BR-021
- **Nama Rule:** Penerbitan Invoice Setelah Booking Disetujui
- **Actor:** Sistem, ADMIN
- **Trigger:** Admin menyetujui (`APPROVE`) booking.
- **Kondisi:** Booking berpindah status ke `APPROVED`.
- **Aturan:** Invoice resmi sistem diterbitkan **HANYA SETELAH** booking disetujui admin. Sebelum approval, invoice tidak boleh dibuat/diterbitkan.
- **Hasil:** Record invoice terbentuk dengan nomor invoice unik (`INV/YYYYMMDD/XXXX`), status `UNPAID`, dan tertera rincian item, total nominal, serta batas waktu bayar.
- **Exception:** Tidak ada. User dilarang bayar sebelum ada invoice resmi.
- **Dampak Teknis:** Model Event / Listener `BookingApproved` men-trigger generation invoice.

### BR-022: Batas Waktu Pembayaran (Payment Deadline 24 Jam)
- **ID:** BR-022
- **Nama Rule:** Deadline Pembayaran 24 Jam
- **Actor:** Sistem
- **Trigger:** Invoice berhasil diterbitkan.
- **Kondisi:** Status invoice `UNPAID` / `PARTIALLY_PAID`.
- **Aturan:** Batas waktu pembayaran adalah tepat **24 jam** sejak timestamp penerbitan invoice (`created_at` invoice + 24 jam).
- **Hasil:** Kolom `due_at` pada invoice terisi otomatis `created_at + 24 hours`.
- **Exception:** PENDING BUSINESS DECISION untuk perpanjangan custom oleh Admin.
- **Dampak Teknis:** `due_at = Carbon::now()->addHours(24)`.

---

## 12. Payment

### BR-023: Kardinalitas Relasi Invoice dan Payment
- **ID:** BR-023
- **Nama Rule:** Relasi 1-to-Many Invoice ke Payment
- **Actor:** Sistem, USER, ADMIN
- **Trigger:** Pengguna melakukan transaksi pembayaran.
- **Kondisi:** Invoice aktif.
- **Aturan:**
  - Satu Invoice dapat memiliki **banyak record pembayaran** (*Multi Payment / Partial*).
  - Satu record Payment **hanya boleh terikat pada tepat satu Invoice** (Tidak boleh 1 bukti bayar digabung untuk multi invoice).
- **Hasil:** Relasi database `invoices (1) -> payments (N)`.
- **Exception:** Pembayaran multi-invoice dalam 1 transfer dilarang; user harus upload bukti terpisah per invoice.
- **Dampak Teknis:** Foreign key `invoice_id` (NOT NULL) di tabel `payments`.

### BR-024: Pembayaran Bertahap (Partial Payment)
- **ID:** BR-024
- **Nama Rule:** Dukungan Pembayaran Sebagian / DP
- **Actor:** USER, ADMIN
- **Trigger:** User mengupload bukti bayar dengan nominal lebih kecil dari total tagihan invoice.
- **Kondisi:** Nominal pembayaran > 0.
- **Aturan:**
  - Sistem mencatat pembayaran parsial setelah diverifikasi admin.
  - Sisa tagihan dihitung dari: `Total Invoice` - `Total Payment Terverifikasi (APPROVED)`.
  - Status invoice menjadi `PARTIALLY_PAID` jika sisa tagihan > 0, dan berubah menjadi `PAID` jika sisa tagihan = 0.
- **Hasil:** Saldo terbayar terakumulasi bertahap.
- **Exception:** Ketentuan minimum DP: PENDING BUSINESS DECISION.
- **Dampak Teknis:** Kolom agregat `paid_amount` pada invoice dan status `PARTIALLY_PAID`.

### BR-025: Penolakan Pembayaran dan Upload Ulang Bukti
- **ID:** BR-025
- **Nama Rule:** Rejection Bukti Bayar & Re-upload
- **Actor:** ADMIN, USER
- **Trigger:** Admin menolak bukti pembayaran (misal: mutasi bank tidak masuk, nominal salah, struk palsu).
- **Kondisi:** Payment berstatus `PENDING_VERIFICATION`.
- **Aturan:**
  - Admin menolak record payment dengan mengisi alasan penolakan. Status payment menjadi `REJECTED`.
  - Pengguna **dapat mengupload ulang** bukti pembayaran baru selama invoice belum melewati deadline (`due_at`).
  - **Deadline pembayaran TIDAK BERESET / TIDAK DIPERPANJANG** akibat adanya penolakan bukti bayar (Batas waktu tetap 24 jam dari awal terbit invoice).
- **Hasil:** Payment lama tetap berstatus `REJECTED` (histori tidak dihapus), payment baru dibuat dengan status `PENDING_VERIFICATION`.
- **Exception:** Tidak ada perpanjangan otomatis.
- **Dampak Teknis:** Record payment baru dibuat, timer deadline invoice mengacu pada `invoices.due_at` awal.

### BR-026: Penanganan Kelebihan Bayar (Overpayment)
- **ID:** BR-026
- **Nama Rule:** Verifikasi Wajib Kelebihan Bayar
- **Actor:** ADMIN, OWNER
- **Trigger:** Pembayaran terverifikasi yang nominalnya melebihi total tagihan invoice (`total_paid > invoice_amount`).
- **Kondisi:** Terjadi transfer berlebih oleh penyewa.
- **Aturan:** Kelebihan bayar **wajib diverifikasi terlebih dahulu oleh Admin/Owner**. Sistem menandai transaksi sebagai `OVERPAID`. Dana lebih tidak otomatis dipotong tanpa approval manual dan dialokasikan untuk refund manual atau deposit sewa berikutnya.
- **Hasil:** Record payment disetujui sebesar total invoice, dan selisihnya dicatat sebagai `overpayment_amount` yang menunggu keputusan manual.
- **Exception:** PENDING BUSINESS DECISION untuk konversi otomatis ke saldo kredit/deposit.
- **Dampak Teknis:** Kolom `overpayment_amount` di tabel `invoices` / `payments`.

---

## 13. Refund

### BR-027: Pelaksanaan Refund Manual
- **ID:** BR-027
- **Nama Rule:** Eksekusi Refund Non-Otomatis (Manual)
- **Actor:** ADMIN, OWNER
- **Trigger:** Pengajuan refund disetujui akibat pembatalan sewa, overpayment, atau pemulangan deposit.
- **Kondisi:** Ada hak dana yang harus dikembalikan ke penyewa.
- **Aturan:** Pengembalian dana (refund) **DILAKUKAN SECARA MANUAL** melalui transfer bank operasional perusahaan oleh Admin/Owner. Sistem tidak melakukan refund otomatis via payment gateway.
- **Hasil:** Admin mengunggah bukti transfer refund dan mencatat nomor referensi bank pada sistem. Status refund menjadi `COMPLETED`.
- **Exception:** Pemotongan biaya administrasi/denda refund: PENDING BUSINESS DECISION.
- **Dampak Teknis:** Tabel `refunds` (`id`, `invoice_id`, `amount`, `bank_account_info`, `proof_document`, `status`, `processed_by`, `processed_at`).

---

## 14. Cancellation

### BR-028: Pembatalan Booking oleh Pengguna
- **ID:** BR-028
- **Nama Rule:** Alur Pembatalan Booking
- **Actor:** USER, ADMIN
- **Trigger:** Pengguna mengajukan pembatalan booking.
- **Kondisi:** Booking belum berstatus `IN_OPERATION` / `COMPLETED`.
- **Aturan:**
  - Jika booking belum disetujui atau belum dibayar: Booking langsung berstatus `CANCELLED` dan kuota dilepas seketika.
  - Jika booking sudah berstatus `PAID` / Unit sudah di-assign: Pembatalan membutuhkan verifikasi admin dan mengikuti aturan penalti pembatalan.
- **Hasil:** Booking dan invoice dibatalkan, unit assignment ditarik kembali.
- **Exception:** Besaran persentase penalti/potongan pembatalan: PENDING BUSINESS DECISION.
- **Dampak Teknis:** State machine `CANCELLED` dan trigger release unit assignment.

---

## 15. Reschedule

### BR-029: Pengajuan Reschedule Jadwal Sewa
- **ID:** BR-029
- **Nama Rule:** Reschedule dengan Validasi Availability dan Buffer
- **Actor:** USER, ADMIN, Sistem
- **Trigger:** Permintaan perubahan tanggal mulai/selesai sewa.
- **Kondisi:** Permintaan diajukan sebelum unit dimobilisasi.
- **Aturan:** Perubahan jadwal **WAJIB** mengecek ulang ketersediaan kuota tipe alat pada rentang tanggal baru beserta buffer waktu operasional (MOB/DEMOB). Jika kuota pada tanggal baru tidak mencukupi, permintaan reschedule ditolak.
- **Hasil:** Jika disetujui, jadwal booking diperbarui dan assignment unit disesuaikan.
- **Exception:** Biaya selisih/denda reschedule: PENDING BUSINESS DECISION.
- **Dampak Teknis:** Pengecekan overlap ketersediaan pada interval waktu baru.

---

## 16. Notification

### BR-030: Notifikasi Perubahan Status Booking dan Pembayaran
- **ID:** BR-030
- **Nama Rule:** Notifikasi Transaksi Pengguna
- **Actor:** Sistem
- **Trigger:** Terjadinya perubahan status booking (Approved, Rejected, Expired), penerbitan invoice, dan status verifikasi payment.
- **Kondisi:** Trigger event aktif.
- **Aturan:** Sistem mengirimkan notifikasi kepada user terkait status tagihan dan instruksi tindakan selanjutnya.
- **Hasil:** Record notifikasi tersimpan di database dan dikirimkan ke saluran komunikasi.
- **Exception:** Kanal notifikasi eksternal (Email / WhatsApp Gateway): PENDING BUSINESS DECISION.
- **Dampak Teknis:** Laravel Database Notifications (`notifications` table).

### BR-031: Pengingat Deadline Pembayaran (Payment Reminder)
- **ID:** BR-031
- **Nama Rule:** Notifikasi Pengingat Jatuh Tempo
- **Actor:** Sistem (Cron Scheduler)
- **Trigger:** Sisa waktu pembayaran mendekati batas 24 jam (misal: H-6 jam dan H-1 jam).
- **Kondisi:** Invoice berstatus `UNPAID` dan `due_at` belum lewat.
- **Aturan:** Sistem membuat notifikasi pengingat pembayaran kepada pengguna.
- **Hasil:** Pengguna menerima notifikasi peringatan sebelum booking dibatalkan otomatis.
- **Exception:** Tidak ada.
- **Dampak Teknis:** Query database di scheduled task mencari invoice `due_at BETWEEN now() AND now() + 6 hours`.

---

## 17. Document

### BR-032: Manajemen dan Retensi Dokumen Digital
- **ID:** BR-032
- **Nama Rule:** Penyimpanan Dokumen dan Bukti Transaksi
- **Actor:** Sistem, USER, ADMIN
- **Trigger:** Upload KTP, NPWP, BAST, foto unit fisik, dan bukti transfer pembayaran.
- **Kondisi:** File berekstensi valid (PDF, JPG, PNG, WebP) dengan ukuran maksimal yang ditentukan.
- **Aturan:** Seluruh dokumen disimpan pada disk lokal penyimpanan Laravel (`storage/app/public`) yang terlindungi dan diakses via symbolic link. Dokumen BAST dan invoice tidak boleh dapat ditimpa sembarangan setelah berstatus final.
- **Hasil:** File tersimpan dengan nama unik (*hash filename*) dan URL path tercatat di database.
- **Exception:** File korup atau format tidak didukung; ditolak saat validasi upload.
- **Dampak Teknis:** Request validation `file|mimes:pdf,jpg,png|max:5120` dan storage driver `public`.

---

## 18. Audit

### BR-033: Pencatatan Jejak Audit (Audit Trail Log)
- **ID:** BR-033
- **Nama Rule:** Audit Log Perubahan Data Kritis
- **Actor:** Sistem
- **Trigger:** Perubahan status booking, approval invoice, verifikasi/rejection payment, approval/revisi timesheet, dan perubahan unit assignment.
- **Kondisi:** Transaksi mutasi data terjadi.
- **Aturan:** Sistem secara otomatis mencatat jejak audit mencakup: ID Aktor, Role, IP Address, Nama Aksi, Nilai Sebelum (*Old Values*), Nilai Sesudah (*New Values*), dan Timestamp.
- **Hasil:** Log tersimpan permanen dan **TIDAK DAPAT DIHAPUS ATAU DIUBAH** oleh role apapun termasuk Admin/Owner.
- **Exception:** Tidak ada.
- **Dampak Teknis:** Tabel `audit_logs` (`id`, `user_id`, `action`, `auditable_type`, `auditable_id`, `old_values`, `new_values`, `ip_address`, `created_at`).

---

## 19. Security

### BR-034: Autentikasi dan Perlindungan Akses Data
- **ID:** BR-034
- **Nama Rule:** Standar Keamanan Autentikasi & Otorisasi API
- **Actor:** Sistem
- **Trigger:** Setiap request masuk ke API endpoint.
- **Kondisi:** Header request memuat Bearer Token Sanctum.
- **Aturan:**
  - Token harus valid dan belum kedaluwarsa.
  - Setiap endpoint operasional wajib menerapkan Policy Authorization untuk mencegah *Insecure Direct Object Reference* (IDOR).
  - Rate limiting diterapkan untuk mencegah brute-force login dan flood request.
- **Hasil:** Akses disetujui atau diblokir dengan kode HTTP 401/403/429.
- **Exception:** Public endpoints (Katalog publik, login, register).
- **Dampak Teknis:** Laravel Sanctum middleware, ThrottleRequests, Policy classes.

---

## 20. Business Calendar

### BR-035: Jam Layanan dan Hari Operasional
- **ID:** BR-035
- **Nama Rule:** Kalender Operasional Mobilisasi & Dispatch
- **Actor:** ADMIN, Sistem
- **Trigger:** Pemilihan jadwal pengiriman unit / serah terima di lokasi.
- **Kondisi:** Penjadwalan tanggal sewa.
- **Aturan:** Mobilisasi dan demobilisasi unit mengikuti jam operasional pool dan kalender kerja standar.
- **Hasil:** Jadwal operasional tervalidasi.
- **Exception:** Ketentuan biaya lembur hari libur nasional / tarif akhir pekan: PENDING BUSINESS DECISION.
- **Dampak Teknis:** Setting konfigurasi kalender bisnis operasional.

---

## Pemeriksaan & Rekonsiliasi: Business Rule ↔ PRD V3

### 1. Kesesuaian Scope Inti
| Area | Status | Catatan Rekonsiliasi |
|---|---|---|
| Single Location per Booking | **LOCKED & MATCH** | Aturan 1 booking = 1 project location (BR-003) konsisten dengan PRD V3. |
| Abstraksi Unit Fisik | **LOCKED & MATCH** | User hanya pilih tipe alat (BR-008), Admin yang melakukan assignment unit fisik (BR-013). |
| Invoice Pasca-Approval | **LOCKED & MATCH** | Invoice terbit setelah booking disetujui Admin (BR-021). |
| Deadline Pembayaran 24 Jam | **LOCKED & MATCH** | Jatuh tempo 24 jam dengan auto-expired dan auto-release kuota (BR-010, BR-012, BR-022). |
| Rejection & No Reset Timer | **LOCKED & MATCH** | Rejection payment tidak me-reset batas waktu 24 jam (BR-025). |
| Relasi 1 Invoice - N Payments | **LOCKED & MATCH** | Mendukung partial payment (BR-023, BR-024). |
| Overpayment Verification | **LOCKED & MATCH** | Overpayment wajib verifikasi manual (BR-026). |
| Manual Refund | **LOCKED & MATCH** | Refund dilakukan manual via transfer bank (BR-027). |
| All-in / Non All-in | **LOCKED & MATCH** | Opsi All-in ditentukan independen per equipment line (BR-019). |
| MOB/DEMOB per Physical Unit | **LOCKED & MATCH** | MOB/DEMOB dihitung berbasis unit fisik (BR-014). |
| Timesheet Immutability | **LOCKED & MATCH** | Revisi timesheet mencatat riwayat versi lengkap tanpa menghapus data lama (BR-017, BR-018). |

### 2. Isu & Aturan Berstatus `PENDING BUSINESS DECISION`
1. **Formula Denda & Penalti Pembatalan (BR-028):** Persentase potongan refund dan penalti pembatalan sepihak pasca bayar menunggu konfirmasi kebijakan komersial.
2. **Besaran Minimal Uang Muka / DP (BR-024):** Batas minimal nominal/persentase DP untuk partial payment belum diputuskan.
3. **Penyesuaian Biaya Reschedule (BR-029):** Regulasi biaya administrasi atau tarif kompensasi reschedule belum ditetapkan.
4. **Kalender Tarif Hari Libur / Overtime (BR-035):** Kebijakan surcharge pada hari libur nasional berstatus pending.
5. **Kanal Notifikasi Eksternal (BR-030):** Integrasi WhatsApp/SMS Gateway berbayar vs Notifikasi Email/In-App berstatus pending.

### 3. Kesimpulan Kesiapan Desain
Aturan bisnis inti (35 Business Rules) telah terkunci dan konsisten. Tidak ditemukan konflik arsitektur terhadap batasan sistem. Modul dasar dapat dilanjutkan ke tahap perancangan teknis berikutnya.
