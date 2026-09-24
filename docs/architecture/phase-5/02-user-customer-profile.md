# Profil Pengguna & Pelanggan (User & Customer Profile) - RAFA Rental System

Dokumentasi spesifikasi API pengelolaan profil identitas penyewa (`User`) dan informasi institusi (`CustomerProfile`) pada RAFA Rental System (Phase 5B).

---

## 1. Relasi & Kepemilikan Data

- **Relasi 1:1:** Entitas `User` memiliki satu `CustomerProfile`. Profil pelanggan otomatis terbuat saat user mendaftar (`register`) atau bisa diperbarui setelahnya.
- **Data User Dasar:** Mencakup nama, email, role, phone_number, dan is_active.
- **Data Customer Profile (KYC):** Mencakup nama perusahaan, tipe identitas (KTP/NPWP/Passport), nomor identitas, alamat fisik, dan status verifikasi administrasi (`VERIFIED`, `UNVERIFIED`, `REJECTED`).

---

## 2. Kontrak Endpoint API (`/api/v1/profile/*`)

### 2.1 Lihat Profil Pribadi (`GET /api/v1/profile`)
- **Akses:** Terautentikasi (USER, ADMIN, OWNER).
- **Perilaku:** Menampilkan kombinasi data tabel `users` dan `customer_profiles` (jika ada).
- **Respons Sukses (200 OK):**
  ```json
  {
    "success": true,
    "data": {
      "id": 1,
      "name": "Budi Santoso",
      "email": "budi@example.com",
      "role": "USER",
      "phone_number": "081234567890",
      "is_active": true,
      "customer_profile": {
        "company_name": "PT Maju Terus",
        "identity_type": "KTP",
        "identity_number": "3201012345670001",
        "address": "Jl. Merdeka No. 10",
        "verification_status": "UNVERIFIED"
      }
    }
  }
  ```

### 2.2 Perbarui Profil Pribadi (`PUT / PATCH /api/v1/profile`)
- **Akses:** Terautentikasi (USER, ADMIN, OWNER). Hanya bisa mengubah milik sendiri.
- **Payload yang Diizinkan:** `name`, `phone_number`, `company_name`, `identity_type`, `identity_number`, `address`.
- **Validasi Kritis:**
  - `phone_number` dan `identity_number` harus unik secara global di luar record milik pengguna itu sendiri.
- **Keamanan (Security Guard):**
  - Upaya mengirim field `role`, `is_active`, atau `verification_status` dalam request body akan diabaikan mutlak. Elevasi *privilege* tidak mungkin dilakukan oleh user biasa.

### 2.3 Verifikasi Identitas KYC (`POST /api/v1/profile/verify`)
- **Akses:** Khusus `ADMIN` dan `OWNER` (via Gate `manage-equipment` atau Gate terdedikasi).
- **Payload:**
  ```json
  {
    "user_id": 15,
    "verification_status": "VERIFIED" // ENUM: VERIFIED, REJECTED, UNVERIFIED
  }
  ```
- **Perilaku:** Memutakhirkan status administrasi (*KYC*) pengguna agar mereka berhak menyewa unit.

---

## 3. Direktori Manajemen Pengguna (Admin Only)

### 3.1 Daftar Seluruh Pelanggan (`GET /api/v1/admin/users`)
- **Akses:** `ADMIN`, `OWNER`.
- **Perilaku:** Menampilkan list paginasi pengguna beserta ringkasan status KYC-nya.
- **Parameter Opsional:** `?role=USER`

### 3.2 Detail Pengguna Khusus (`GET /api/v1/admin/users/{id}`)
- **Akses:** `ADMIN`, `OWNER`.
- **Perilaku:** Menampilkan informasi mendalam, profil KYC, dan list lokasi proyek (`projectLocations`) yang dimiliki pelanggan tersebut.

---

## 4. Tipe Data Frontend

Struktur tipe Typescript pada klien siap merespons data profile dengan presisi:
```typescript
// frontend/src/types/user.ts
export interface CustomerProfile {
  company_name: string | null
  identity_type: 'KTP' | 'NPWP' | 'PASSPORT'
  identity_number: string | null
  address: string | null
  verification_status: 'UNVERIFIED' | 'VERIFIED' | 'REJECTED'
}

export interface UserProfile {
  id: number
  name: string
  email: string
  role: UserRole
  phone_number: string | null
  is_active: boolean
  customer_profile?: CustomerProfile | null
}
```
