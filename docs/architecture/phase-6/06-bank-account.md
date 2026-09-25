# Master Rekening Bank Perusahaan (Bank Account Master) - RAFA Rental System

Dokumentasi spesifikasi implementasi backend API dan antarmuka manajemen Admin/Owner untuk rekening resmi penampungan pembayaran (`bank_accounts`) pada RAFA Rental System (Phase 6F).

---

## 1. Lingkup Modul Rekening Bank

Modul ini mengelola nomor rekening resmi atas nama perusahaan (PT RAFA RENTAL NUSANTARA) yang digunakan sebagai tujuan transfer manual pembayaran invoice sewa oleh pelanggan:

- **Atribut Data:** `bank_name` (nama institusi bank), `account_number` (nomor rekening unik), `account_name` (nama pemilik rekening), `is_active` (status penerimaan pembayaran).
- **Keamanan Informasi:** Nomor rekening dilindungi otorisasi, disanitasi dari error detail/log, dan pelanggan umum (`USER`) hanya diperbolehkan melihat rekening yang berstatus aktif (`is_active = true`).

---

## 2. Kontrak Endpoint REST API (`/api/v1/bank-accounts`)

| Method | Endpoint | Akses / Role | Deskripsi |
|---|---|---|---|
| `GET` | `/api/v1/bank-accounts` | Terautentikasi (Semua) | Daftar rekening bank resmi (Pelanggan hanya melihat `is_active=true`; Admin/Owner melihat semua) |
| `GET` | `/api/v1/bank-accounts/{id}` | Terautentikasi (Semua) | Detail rekening spesifik (Ditolak 404 bagi pelanggan jika rekening nonaktif) |
| `POST` | `/api/v1/bank-accounts` | `ADMIN`, `OWNER` | Pendaftaran rekening bank baru |
| `PUT` | `/api/v1/bank-accounts/{id}` | `ADMIN`, `OWNER` | Perbarui data rekening atau ubah status aktif/nonaktif |

---

## 3. Aturan Validasi & Otorisasi

1. **Keunikan Nomor Rekening:**
   - `account_number` wajib unik di seluruh pangkalan data (`unique:bank_accounts,account_number`).
2. **Otorisasi Pengelolaan:**
   - Pengguna biasa (`USER`) dilarang melakukan penambahan atau modifikasi rekening (HTTP 403 `FORBIDDEN_ACTION`).
   - Gate `manage-bank-accounts` mengizinkan peran `ADMIN` dan `OWNER` mengelola rekening perusahaan.
3. **Filter Sisi Server Otomatis:**
   - Query pada `BankAccountController::index` secara otomatis menyaring `is_active = true` jika pemanggil bukan Admin/Owner, mencegah eksposur rekening internal yang sudah ditutup ke invoice pelanggan.

---

## 4. Antarmuka Manajemen Rekening (`AdminBankAccountsPage.tsx`)

Antarmuka terintegrasi pada portal Admin/Owner (`/owner/settings`):
- **Tabel Daftar Rekening:** Menampilkan nama bank, nomor rekening, nama pemilik rekening, dan badge status (`Aktif` vs `Nonaktif`).
- **Modal Pendaftaran & Pengeditan:** Form entri cepat dengan input toggle switch `is_active`.
- **Umpan Balik Responsif:** Pemuatan tabel `TableSkeleton`, notifikasi mengambang `Toast`, dan penanganan error validasi HTTP 422.
