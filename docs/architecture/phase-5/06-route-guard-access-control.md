# Route Guard & Access Control - RAFA Rental System

Dokumentasi spesifikasi sistem proteksi rute (*Route Guards*), pembatasan akses berbasis peran (*Role-Based Access Control*), dan penanganan status sesi pada aplikasi React SPA RAFA Rental System (Phase 5F).

---

## 1. Kategori Rute (Route Categories)

Rute dalam aplikasi diklasifikasikan ke dalam 4 tingkatan:

| Kategori | Komponen Pelindung | Akses | Perilaku Jika Tidak Memenuhi Syarat |
|---|---|---|---|
| **Public** | `PublicLayout` | Semua (Guest / Authenticated) | Dapat diakses bebas oleh semua pengunjung. |
| **Guest-Only** | `GuestRoute` | Hanya Pengunjung Belum Login | Pengguna yang sudah login dialihkan otomatis ke dashboard perannya (`/app`, `/admin`, atau `/owner`). |
| **Authenticated** | `ProtectedRoute` | Seluruh Pengguna Login (`USER`, `ADMIN`, `OWNER`) | Pengunjung belum login dialihkan ke `/login` dengan menyimpan rute tujuan awal (`state.from`). |
| **Role-Specific** | `RoleRoute` | Peran Tertentu Sesuai Kebijakan | Pengguna login yang tidak memiliki peran yang diizinkan dialihkan ke `/forbidden` (HTTP 403 Page). |

---

## 2. Struktur Pohon Rute & Hak Akses

```
AppRoutes
├── [Public] / (Landing Page, /unauthorized, /forbidden)
│
├── [Guest-Only via GuestRoute]
│   ├── /login
│   ├── /register
│   ├── /forgot-password
│   └── /reset-password
│
└── [Protected via ProtectedRoute]
    │
    ├── [RoleRoute: USER]
    │   └── /app/* (Portal Pelanggan: /app/equipment, /app/bookings, /app/invoices, /app/profile)
    │
    ├── [RoleRoute: ADMIN, OWNER]
    │   └── /admin/* (Meja Operasional: /admin/bookings, /admin/units, /admin/timesheets, /admin/payments, /admin/refunds)
    │
    └── [RoleRoute: OWNER]
        └── /owner/* (Executive Governance: /owner/revenue, /owner/pricing, /owner/audit, /owner/settings)
```

---

## 3. Penanganan Siklus Pengecekan Autentikasi (Auth Check State)

Untuk mencegah kebocoran visual (*content flashing*) atau pengalihan rute prematur:

1. **State `checking`:**
   Saat aplikasi pertama kali dibuka (*cold boot*), `AuthContext` memverifikasi token tersimpan ke backend `/api/v1/auth/me`. Selama proses ini, `ProtectedRoute` dan `GuestRoute` menahan render halaman dan menampilkan `LoadingState` ("Memeriksa autentikasi...").
2. **State `authenticated`:**
   Rute mengizinkan render komponen anak (`<Outlet />`).
3. **State `unauthenticated`:**
   Pengguna ditolak dan diarahkan ke `/login`.
4. **State `forbidden`:**
   Jika pengguna mencoba mengakses rute di luar perannya (misal `USER` mengetikkan URL `/owner`), sistem mengalihkan ke `/forbidden` tanpa menghapus token atau memutus sesi login.

---

## 4. Preservasi Destinasi Asal (Intended Destination Redirect)

Ketika pengguna belum login mencoba membuka tautan tertutup (misal `/app/bookings`):
1. `ProtectedRoute` menyimpan lokasi URL awal ke dalam state navigasi:
   ```typescript
   <Navigate to="/login" state={{ from: location }} replace />
   ```
2. Setelah login berhasil di `LoginPage`, sistem memeriksa keberadaan `location.state.from` dan langsung mengarahkan pengguna ke halaman yang semula dituju.

---

## 5. Sumber Kebenaran Otorisasi (Source of Truth)

- **Frontend Route Guards** bertindak murni sebagai pengatur pengalaman pengguna (*UX navigation & layout protection*).
- **Backend Laravel** tetap menjadi *single source of truth* untuk seluruh otorisasi data (via Middleware `role:ADMIN,OWNER`, Laravel Policies, dan Database Gates).
- Manipulasi state di sisi browser tidak akan pernah mampu menembus data transaksi di backend.
