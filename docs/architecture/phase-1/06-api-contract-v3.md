# API Contract V3 - RAFA Rental System

Dokumen spesifikasi endpoint API (RESTful) RAFA Rental System (Phase 1F). Versi API yang digunakan adalah `/api/v1`. Seluruh interaksi menggunakan payload JSON.

---

## 1. Booking Module

Kumpulan endpoint pengelolaan transaksi penyewaan (Reservasi).

### 1.1 Buat Booking (Draft)
- **Method:** `POST`
- **Endpoint:** `/api/v1/bookings`
- **Actor:** USER
- **Authentication:** Bearer Token (Sanctum)
- **Authorization:** `create-booking`
- **Request:** Empty body (Diambil otomatis dari `Cart` dan `Cart Items` user).
- **Validation:** Keranjang tidak kosong, lokasi proyek sudah di-set, kuota tersedia.
- **Response:** `201 Created` | `{"data": {"id": 1, "booking_code": "RFA-...", "status": "DRAFT"}}`
- **Error:** `400 BAD_REQUEST`, `404 NOT_FOUND` (Cart empty), `409 BOOKING_CONFLICT` (Kuota habis).
- **Idempotency:** Ya (Jika cart sudah kosong, reject request berulang).
- **Audit:** Log `BOOKING_CREATED`.

### 1.2 Lihat Daftar Booking
- **Method:** `GET`
- **Endpoint:** `/api/v1/bookings`
- **Actor:** USER, ADMIN, OWNER
- **Authentication:** Bearer Token
- **Authorization:** `view-any-booking` (User hanya melihat miliknya).
- **Request:** Query Params: `?status=APPROVED&page=1&limit=10`
- **Validation:** Parameter status valid sesuai enum.
- **Response:** `200 OK` | `{"data": [...], "meta": {"total": 10}}`
- **Error:** `400 BAD_REQUEST`, `401 UNAUTHENTICATED`.
- **Idempotency:** Ya (Safe).
- **Audit:** Tidak dicatat.

### 1.3 Lihat Detail Booking
- **Method:** `GET`
- **Endpoint:** `/api/v1/bookings/{id}`
- **Actor:** USER, ADMIN, OWNER
- **Authentication:** Bearer Token
- **Authorization:** `view-booking` (User memegang hak akses record `id`).
- **Request:** Path param `id`.
- **Validation:** ID integer.
- **Response:** `200 OK` | `{"data": {"booking_code": "...", "details": [...], "project_location": {...}}}`
- **Error:** `403 FORBIDDEN`, `404 NOT_FOUND`.
- **Idempotency:** Ya (Safe).
- **Audit:** Tidak dicatat.

### 1.4 Submit Booking (Draft -> Pending Approval)
- **Method:** `POST`
- **Endpoint:** `/api/v1/bookings/{id}/submit`
- **Actor:** USER
- **Authentication:** Bearer Token
- **Authorization:** `submit-booking`
- **Request:** Empty.
- **Validation:** Status awal harus `DRAFT`. Profil user harus terverifikasi identitasnya.
- **Response:** `200 OK` | `{"message": "Booking submitted successfully", "status": "PENDING_APPROVAL"}`
- **Error:** `403 FORBIDDEN_ACTION` (Belum KYC), `409 INVALID_STATE_TRANSITION`.
- **Idempotency:** Ya (Transisi ke state yang sama diabaikan/aman).
- **Audit:** Log `BOOKING_SUBMITTED`.

### 1.5 Approve Booking
- **Method:** `POST`
- **Endpoint:** `/api/v1/bookings/{id}/approve`
- **Actor:** ADMIN
- **Authentication:** Bearer Token
- **Authorization:** `approve-booking`
- **Request:** Empty.
- **Validation:** Status awal harus `PENDING_APPROVAL`.
- **Response:** `200 OK` | `{"message": "Booking approved. Invoice generated.", "invoice_id": 99}`
- **Error:** `409 INVALID_STATE_TRANSITION`.
- **Idempotency:** Ya.
- **Audit:** Log `BOOKING_APPROVED`. Generate Invoice.

### 1.6 Reject Booking
- **Method:** `POST`
- **Endpoint:** `/api/v1/bookings/{id}/reject`
- **Actor:** ADMIN
- **Authentication:** Bearer Token
- **Authorization:** `reject-booking`
- **Request:** `{"rejection_reason": "Unit maintenance mendadak"}`
- **Validation:** Status awal harus `PENDING_APPROVAL`. Reason minimal 10 karakter.
- **Response:** `200 OK` | `{"message": "Booking rejected", "status": "REJECTED"}`
- **Error:** `400 VALIDATION_ERROR`, `409 INVALID_STATE_TRANSITION`.
- **Idempotency:** Ya.
- **Audit:** Log `BOOKING_REJECTED`. Rilis lock kuota.

### 1.7 Cancel Booking
- **Method:** `POST`
- **Endpoint:** `/api/v1/bookings/{id}/cancel`
- **Actor:** USER, ADMIN
- **Authentication:** Bearer Token
- **Authorization:** `cancel-booking`
- **Request:** `{"cancel_reason": "Proyek batal"}`
- **Validation:** Status belum `DISPATCHED`. Pasca-bayar hanya Admin yang berhak memanggil.
- **Response:** `200 OK` | `{"message": "Booking cancelled"}`
- **Error:** `403 FORBIDDEN_ACTION`, `409 INVALID_STATE_TRANSITION`.
- **Idempotency:** Ya.
- **Audit:** Log `BOOKING_CANCELLED`. Release unit/kuota, buat draft refund jika perlu.

### 1.8 Reschedule Booking
- **Method:** `POST`
- **Endpoint:** `/api/v1/bookings/{id}/reschedule`
- **Actor:** USER, ADMIN
- **Authentication:** Bearer Token
- **Authorization:** `reschedule-booking`
- **Request:** `{"new_start_date": "YYYY-MM-DD", "new_end_date": "YYYY-MM-DD", "reason": "Mundur 1 minggu"}`
- **Validation:** Ketersediaan unit di tanggal baru valid (Buffer MOB/DEMOB), status belum `DISPATCHED`.
- **Response:** `200 OK` | `{"message": "Reschedule approved/submitted"}`
- **Error:** `409 RESCHEDULE_CONFLICT` (Unit penuh), `409 INVALID_STATE_TRANSITION`.
- **Idempotency:** Ya.
- **Audit:** Log `BOOKING_RESCHEDULED`.

### 1.9 Assign Physical Units
- **Method:** `POST`
- **Endpoint:** `/api/v1/bookings/{id}/assign-units`
- **Actor:** ADMIN
- **Authentication:** Bearer Token
- **Authorization:** `assign-booking-units`
- **Request:** `{"assignments": [{"booking_detail_id": 1, "equipment_unit_id": 105}]}`
- **Validation:** Status `CONFIRMED`. `equipment_unit_id` berstatus `AVAILABLE` dan sesuai `model_id`.
- **Response:** `200 OK` | `{"message": "Units assigned"}`
- **Error:** `409 UNIT_ASSIGNMENT_CONFLICT` (Unit sedang dipakai), `404 UNIT_UNAVAILABLE`.
- **Idempotency:** Ya (Upsert behavior berdasar detail ID).
- **Audit:** Log `UNITS_ASSIGNED`.

### 1.10 Replace Unit Assignment
- **Method:** `POST`
- **Endpoint:** `/api/v1/bookings/{id}/replace-unit`
- **Actor:** ADMIN
- **Authentication:** Bearer Token
- **Authorization:** `replace-booking-unit`
- **Request:** `{"old_assignment_id": 12, "new_equipment_unit_id": 108, "reason": "Mesin rusak pra-kirim"}`
- **Validation:** Unit pengganti `AVAILABLE` dan tipe identik.
- **Response:** `200 OK` | `{"message": "Unit replaced"}`
- **Error:** `404 UNIT_UNAVAILABLE`, `409 INVALID_STATE_TRANSITION`.
- **Idempotency:** Ya.
- **Audit:** Log `UNIT_REPLACED`. Set `is_current = 0` pada assignment lama.

### 1.11 Extend Payment Deadline
- **Method:** `POST`
- **Endpoint:** `/api/v1/bookings/{id}/extend-payment-deadline`
- **Actor:** ADMIN, OWNER
- **Authentication:** Bearer Token
- **Authorization:** `extend-deadline`
- **Request:** `{"additional_hours": 24, "reason": "Toleransi corporate"}`
- **Validation:** Status masih `PAYMENT_PENDING` (`UNPAID` invoice).
- **Response:** `200 OK` | `{"message": "Deadline extended", "new_due_at": "..."}`
- **Error:** `409 BOOKING_EXPIRED`, `409 INVALID_STATE_TRANSITION`.
- **Idempotency:** Tidak (Menambah jam dari `due_at` existing). Gunakan `new_due_at` absolut untuk idempotensi penuh.
- **Audit:** Log `PAYMENT_DEADLINE_EXTENDED`.

---

## 2. Modul Lainnya (Representasi Minimal)

### 2.1 Auth Module
- **POST /api/v1/auth/login** | Actor: ALL | Login & Generate Sanctum Token.
- **GET /api/v1/auth/me** | Actor: ALL | Dapatkan profil user saat ini.

### 2.2 Profile Module
- **PUT /api/v1/profile** | Actor: USER | Update data profil.
- **POST /api/v1/profile/verify** | Actor: ADMIN | Verifikasi (KYC) dokumen `identity_type` dan file KTP. (Status -> VERIFIED).

### 2.3 Project Location Module
- **POST /api/v1/project-locations** | Actor: USER | Tambah alamat proyek baru. Request body: `project_name`, `address`, `city`, `pic_name`, `pic_phone`.
- **GET /api/v1/project-locations** | Actor: USER | List lokasi milik user.

### 2.4 Equipment Module
- **GET /api/v1/equipment/models** | Actor: ALL | List katalog beserta snapshot harga minimum. (Public).
- **POST /api/v1/equipment/units** | Actor: ADMIN | Create master data fisik unit baru.

### 2.5 Pricing Module
- **POST /api/v1/pricing/calculate** | Actor: ALL | Simulasi perhitungan estimasi nilai booking (`model_id`, `start_date`, `end_date`, `qty`, `is_all_in`). Idempotent: Ya.

### 2.6 Recommendation Module
- **POST /api/v1/recommendations/request** | Actor: USER | Minta sistem rekomendasi alat berdasar parameter `terrain_condition`, `load_capacity`. Mereturn job_id / result sinkron.

### 2.7 Cart Module
- **POST /api/v1/cart/items** | Actor: USER | Tambah alat ke keranjang. Body: `model_id`, `qty`, `start_date`, `end_date`, `is_all_in`. Validasi bentrok lokasi.

### 2.8 Rental Module
- **POST /api/v1/rentals/{id}/dispatch** | Actor: ADMIN | Ubah state assignment ke `DISPATCHED`. Audit log: `UNIT_DISPATCHED`.
- **POST /api/v1/rentals/{id}/check-in** | Actor: ADMIN | Validasi dan upload BAST Check-in (`file_id`, `start_hm`). State ke `ONGOING`.

### 2.9 Timesheet Module
- **POST /api/v1/timesheets** | Actor: USER, ADMIN | Submit form HM harian. Body: `rental_detail_id`, `report_date`, `start_hm`, `end_hm`.
- **POST /api/v1/timesheets/{id}/revise** | Actor: ADMIN | Mengubah data timesheet salah. Trigger duplikasi tabel `timesheet_revisions`.

### 2.10 Invoice Module
- **GET /api/v1/invoices/{id}** | Actor: USER, ADMIN | Lihat rincian invoice tagihan.
- **GET /api/v1/invoices/{id}/pdf** | Actor: USER, ADMIN | Unduh file PDF Tagihan resmi.

### 2.11 Bank Account Module
- **GET /api/v1/bank-accounts** | Actor: USER, ADMIN | List rekening resmi aktif perusahaan tujuan transfer.

### 2.12 Payment Module
- **POST /api/v1/invoices/{id}/payments** | Actor: USER | Upload bukti transfer. Body: `bank_account_id`, `amount`, lampirkan `file_id`. State payment `SUBMITTED`.
- **POST /api/v1/payments/{id}/approve** | Actor: ADMIN | Verifikasi mutasi. Ubah state payment `APPROVED`. Kalkulasi sisa invoice. (Jika overpay, set error/warning `OVERPAYMENT_REQUIRES_REVIEW`).
- **POST /api/v1/payments/{id}/reject** | Actor: ADMIN | Tolak bukti bayar palsu/salah. Error code `PAYMENT_REJECTED`. Tidak mereset timer.

### 2.13 Refund Module
- **POST /api/v1/refunds/{id}/complete** | Actor: ADMIN | Upload bukti transfer balik bank manual ke user. State `COMPLETED`.

### 2.14 Notification Module
- **GET /api/v1/notifications** | Actor: USER, ADMIN | Ambil list in-app notifications. Pagination.
- **POST /api/v1/notifications/{id}/read** | Actor: USER, ADMIN | Tandai notifikasi dibaca.

### 2.15 Report Module
- **GET /api/v1/reports/revenue** | Actor: OWNER | Agregasi total pendapatan per periode berdasar Invoice `PAID`.
- **GET /api/v1/reports/equipment-utilization** | Actor: OWNER | Persentase masa sewa unit vs idle.

### 2.16 Admin Dashboard Module
- **GET /api/v1/admin/dashboard/urgent-actions** | Actor: ADMIN | List antrean: `PENDING_APPROVAL` bookings, `SUBMITTED` payments, jadwal dispatch/return hari ini.

### 2.17 Owner Dashboard Module
- **GET /api/v1/owner/dashboard/financial-summary** | Actor: OWNER | Metrik Laba/Rugi, Piutang (AR) berjalan, Omzet bulanan.
