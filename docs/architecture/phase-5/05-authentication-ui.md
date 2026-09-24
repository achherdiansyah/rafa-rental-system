# Antarmuka Pengguna Autentikasi (Authentication UI) - RAFA Rental System

Dokumentasi spesifikasi rancangan dan implementasi antarmuka halaman otentikasi serta profil pengguna pada RAFA Rental System (Phase 5E).

---

## 1. Halaman yang Diimplementasikan

| Rute | Komponen Halaman | Deskripsi |
|---|---|---|
| `/login` | `LoginPage.tsx` | Formulir masuk akun dengan email, `PasswordInput` (toggle show/hide), penanganan error backend 422/403, dan auto-redirect sesuai role. |
| `/register` | `RegisterPage.tsx` | Formulir pendaftaran lengkap mencakup nama lengkap, email unik, nomor telepon unik, nama perusahaan, NIK/KTP, dan konfirmasi password. |
| `/forgot-password` | `ForgotPasswordPage.tsx` | Formulir permintaan pemulihan kata sandi dengan konfirmasi banner pesan berhasil. |
| `/reset-password` | `ResetPasswordPage.tsx` | Formulir pembaharuan kata sandi baru berbasis token URL dengan validasi panjang minimum. |
| `/app/profile` | `ProfilePage.tsx` | Halaman portal pelanggan untuk melihat status verifikasi KYC, data akun, dan memperbarui informasi institusi/kontak. |

---

## 2. Komponen Form Reusable Khusus Autentikasi

### `PasswordInput` (`src/components/form/PasswordInput.tsx`)
- Komponen input kata sandi terstandarisasi dengan tombol toggle ikon mata (`Eye` / `EyeOff`) untuk menampilkan/menyembunyikan karakter.
- Terintegrasi dengan label, penanda bintang wajib (`required`), dan teks pesan galat (`error`).
- Mendukung atribut aksesibilitas penuh (`aria-invalid`, `aria-describedby`, `aria-label`).

---

## 3. Integrasi UX & Respon Galat

1. **Pencegahan Klik Ganda:** Seluruh tombol submit form beralih ke state `Button.isLoading` saat request HTTP sedang berlangsung.
2. **Penanganan Status Sesi Kedaluwarsa:** Halaman `/login` mendeteksi parameter URL `?expired=1` dan otomatis menampilkan banner peringatan: *"Sesi Anda telah kedaluwarsa. Silakan masuk kembali."*
3. **Pemberitahuan Sukses:** Pembaruan profil memicu notifikasi mengambang (*Toast Notification*) hijau via `useToast().success()`.
4. **Validasi Klien vs Server:** Pengecekan awal kesamaan konfirmasi password dilakukan langsung di browser sebelum request dikirim, sementara validasi keunikan email/telepon dipetakan dari respons HTTP 422 backend ke field input terkait.

---

## 4. Pengujian Komponen Frontend (`AuthPages.test.tsx`)

Infrastruktur pengujian Vitest & Testing Library menguji skenario:
- Render elemen form login dan tombol submit.
- Validasi ketidakcocokan konfirmasi password pada pendaftaran.
- Pengiriman formulir lupa kata sandi.
- Validasi panjang minimal 8 karakter pada form reset kata sandi.
