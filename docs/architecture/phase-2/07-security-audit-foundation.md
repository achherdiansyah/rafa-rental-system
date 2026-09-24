# Security, Audit & Logging Foundation - RAFA Rental System

Dokumentasi infrastruktur pencatatan audit, keamanan unggahan file, batas akses (rate limiting), dan imutabilitas finansial untuk RAFA Rental System (Phase 2G).

---

## 1. Audit Logging Foundation (`App\Support\AuditLogger`)

Sistem menyediakan fungsi pencatatan (logging) mekanik yang dapat digunakan kembali (*reusable*) untuk melacak aktivitas operasional kritis.
Komponen utama direkam:
- `actor` (Nama, Role, ID dari Auth)
- `entity_type` & `entity_id` (Model target)
- `action` (Aksi operasional, misal `BOOKING_APPROVED`)
- `old_state` & `new_state` (Kondisi sebelum dan sesudah perubahan)
- `metadata` (Informasi pendukung opsional)
- `ip_address` & `user_agent`
- `timestamp`

### Data Sanitization
Fungsi `AuditLogger::sanitize()` secara otomatis mendeteksi dan menutupi *(redact)* data yang sensitif (seperti `password`, `token`, `api_key`) menjadi `***REDACTED***` sebelum dicatatkan ke log.

---

## 2. Aturan Imutabilitas Finansial (Financial Immutability)

- Data operasional finansial seperti **Invoice**, **Payment**, **Refund**, dan **Activity Log** bersifat imutabel secara pembukuan.
- Dokumen tersebut **DILARANG DIHAPUS DIAM-DIAM** secara permanen dari pangkalan data (hard delete). 
- Setiap perubahan pada nilai moneter harus dicatat dalam bentuk *adjustment*, tabel riwayat, atau diakumulasi dalam *soft delete* (`deleted_at`) jika harus dibatalkan.
- Riwayat penggantian penugasan unit harus meninggalkan rekam jejak `is_current = 0` dan tidak menghapus relasi lampau.

---

## 3. Strategi Logging Lingkungan (Environment)

- **Application & Error Log:** Disimpan menggunakan kanal bawaan Laravel Monolog ke direktori `storage/logs/laravel.log`. Pesan 500 lengkap terekam di sini tanpa bocor ke sisi klien.
- **Audit Log Khusus:** Memanfaatkan Log info spesifik (bisa diarahkan ke channel `audit` terpisah) atau direkam ke basis data melalui Observer/Tabel ActivityLog pada saat implementasi bisnis.

---

## 4. Keamanan Unggahan File (`App\Support\FileSecurity`)

Sistem menerapkan validasi ketat pada file dokumen sensitif (KTP, NPWP, Bukti Pembayaran, BAST).

### Aturan Dasar:
- **MIME Validation:** Hanya `application/pdf`, `image/jpeg`, `image/png`, `image/webp`.
- **Extension Validation:** Pembatasan ekstensi mencegah serangan eksekusi file berbahaya (`.php`, `.exe`, dll).
- **Size Validation:** Maksimal ukuran file ditetapkan **5120 KB (5 MB)**.
- **Penyimpanan Privat/Hashed:** File tidak disimpan dengan nama asli klien yang mudah ditebak, melainkan dikonversi menjadi *hash path* yang acak di folder berstruktur tanggal (`payments/2026/09/hash.pdf`).

---

## 5. Mekanisme Rate Limiting API

Batas laju permintaan *(Rate Limiting)* diatur dalam `App\Providers\AppServiceProvider` menggunakan Laravel `RateLimiter`:

| Rate Limiter Name | Batas Akses | Target |
|---|---|---|
| `api` | 60 request / menit | Endpoint umum per IP atau ID Pengguna |
| `auth` | 5 percobaan / menit | Endpoint kritikal otentikasi (Brute-force protection login) |
| `file-upload` | 10 unggahan / menit | Endpoint unggah dokumen untuk mencegah *Storage Flood* |

Batas ini mengembalikan HTTP status `429 Too Many Requests` (didukung format respons standar di Exception Handler).
