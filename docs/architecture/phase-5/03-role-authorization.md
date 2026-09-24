# Otorisasi & Pembatasan Hak Akses (Role & Authorization) - RAFA Rental System

Dokumentasi spesifikasi kebijakan otorisasi (*Authorization Policies*), pemisahan peran (*RBAC*), dan penegakan izin operasional pada RAFA Rental System (Phase 5C).

---

## 1. Prinsip 3 Role Eksklusif

Sistem secara ketat **HANYA MENDEFINISIKAN 3 (TIGA) PERAN**:
1. **`USER`:** Pelanggan atau entitas penyewa armada.
2. **`ADMIN`:** Operator pelaksana operasional lapangan (verifikasi KYC, assignment unit fisik, validasi BAST, verifikasi timesheet harian).
3. **`OWNER`:** Pemilik usaha / direksi pengambil kebijakan bisnis (supervisi finansial, laporan laba/rugi, audit log, dekomisi aset, penonaktifan akun).

*Catatan: Tidak ada role tambahan seperti Super Admin, Operator, Finance, atau Dispatcher. Operator lapangan bukan application user.*

---

## 2. Pemisahan Autentikasi vs Otorisasi

- **Autentikasi (Sanctum):** Memastikan keabsahan identitas pengguna melalui Bearer Token (`auth:sanctum`). Gagal = `401 UNAUTHENTICATED`.
- **Otorisasi (Gate & Policy):** Memeriksa apakah aktor yang telah diautentikasi memiliki hak melakukan tindakan pada resource tertentu. Gagal = `403 FORBIDDEN_ACTION`.

---

## 3. Matriks Kebijakan Resource (Policies)

### 3.1 `UserPolicy` (`app/Policies/UserPolicy.php`)
| Aksi | USER | ADMIN | OWNER | Aturan Logika |
|---|---|---|---|---|
| `viewAny` | N (403) | Y (200) | Y (200) | Hanya Admin/Owner yang boleh melihat daftar seluruh akun. |
| `view` | Own Only | Y (200) | Y (200) | User hanya bisa melihat profilnya sendiri (`$user->id === $target->id`). |
| `update` | Own Only | Own Only | Own Only | Tidak ada role yang bisa mengubah akun pengguna lain secara langsung. |
| `deactivate` | N (403) | N (403) | Y (200) | Hanya Owner yang berhak menonaktifkan akun (`is_active = false`). |

### 3.2 `CustomerProfilePolicy` (`app/Policies/CustomerProfilePolicy.php`)
| Aksi | USER | ADMIN | OWNER | Aturan Logika |
|---|---|---|---|---|
| `view` | Own Only | Y (200) | Y (200) | User hanya melihat status profil miliknya. |
| `update` | Own Only | Own Only | Own Only | User mengunggah kelengkapan identitasnya. |
| `verify` | N (403) | Y (200) | Y (200) | Verifikasi KYC (`VERIFIED` / `REJECTED`) hanya oleh Admin dan Owner. |

---

## 4. Perlindungan Endpoint Berlapis

1. **Layer 1 - Role Middleware (`role:ADMIN,OWNER` / `role:OWNER`):** Melindungi kelompok rute tingkat tinggi di `routes/api.php`.
2. **Layer 2 - Policy & Gate (`$this->authorize()` / `Gate::authorize()`):** Memeriksa kepemilikan objek individual (*ownership validation*) untuk mencegah serangan *IDOR (Insecure Direct Object Reference)*.
3. **Layer 3 - Form Request (`authorize()`):** Menolak eksekusi form input sebelum payload mencapai controller jika izin aktor tidak valid.

---

## 5. Ringkasan Respons Kesalahan Otorisasi

- **401 Unauthorized:**
  ```json
  {
    "success": false,
    "message": "Unauthenticated.",
    "errors": {},
    "code": "UNAUTHENTICATED"
  }
  ```
- **403 Forbidden:**
  ```json
  {
    "success": false,
    "message": "You do not have the required role permissions.",
    "errors": {},
    "code": "FORBIDDEN_ACTION"
  }
  ```
