# Spesifikasi Booking Core & Submission (Phase 8D) - RAFA Rental System

Dokumentasi arsitektur backend inti pemesanan sewa armada (*Booking Core*), transisi status (DRAFT → PENDING_APPROVAL), validasi ketersediaan pra-booking, otorisasi kepemilikan, dan antarmuka pengguna pengajuan sewa.

---

## 1. Lingkup (Scope)

Implementasi Phase 8D berfokus pada alur **Cart → Booking → Submission**:

```text
Shopping Cart / Keranjang Sewa
        │
        ▼
POST /api/v1/bookings  ──►  Booking DRAFT
        │                    (detail snapshot + kode unik RFA-BKG-YYYYMMDD-XXXX)
        ▼
POST /api/v1/bookings/{id}/submit
        │                    (gate KYC + availability pre-check ulang)
        ▼
Booking PENDING_APPROVAL (menunggu telaah Admin)
```

**Di luar lingkup:** Payment, Invoice, Refund, Rental, Timesheet (diimplementasikan pada fase lanjutan). Booking belum melakukan reservasi unit fisik; semuanya bersifat *pre-check*.

---

## 2. Arsitektur Backend

| Layer | Komponen | Tanggung Jawab |
|---|---|---|
| **Controller** | `BookingController` | HTTP entrypoint tipis; delegasi ke Action; serialisasi via Resource. |
| **Action** | `CreateBookingFromCartAction` | Membentuk DRAFT dari cart, snapshot harga via `PricingCalculatorService`, konsumsi cart. |
| **Action** | `SubmitBookingAction` | Validasi KYC, pre-check availability, transisi DRAFT → PENDING_APPROVAL, audit. |
| **Service** | `BookingAvailabilityPrecheckService` | Pre-check ketersediaan agregat per model (bulk query, tanpa reservasi). |
| **Policy** | `BookingPolicy` | Otorisasi create/view/submit (ownership + role). |
| **Resources** | `BookingResource`, `BookingDetailResource` | Respons API standar (envelope `{success, message, data, meta?}`). |

---

## 3. State Machine yang Diterapkan (Phase 8D)

| Transisi | Actor | Preconditions | Hasil |
|---|---|---|---|
| *Cart* → **DRAFT** | `USER` | Cart tidak kosong (BR-007), lokasi proyek ter-set (BR-003), availability pre-check lolos. | Pencatatan header booking + detail snapshot; cart dikonsumsi (idempotent). |
| **DRAFT** → **PENDING_APPROVAL** | `USER` | Profil identitas terverifikasi (KYC), availability pre-check ulang lolos (T-B01). | `status = PENDING_APPROVAL`; audit `BOOKING_SUBMITTED`. |

**Transisi tidak valid** (dari status selain DRAFT, atau data bukan milik user) ditolak oleh `InvalidStateTransitionException` → `409 INVALID_STATE_TRANSITION`.

---

## 4. API Endpoints (sesuai API Contract V3 Booking Module)

| Method | URI | Actor | Deskripsi |
|---|---|---|---|
| `POST` | `/api/v1/bookings` | `USER` | Buat booking DRAFT dari keranjang sewa; body kosong (dibaca dari cart). |
| `GET` | `/api/v1/bookings` | `USER`, `ADMIN`, `OWNER` | List booking (User hanya miliknya; filter `?status=&user_id=`; paginasi). |
| `GET` | `/api/v1/bookings/{id}` | `USER` (Own), `ADMIN`, `OWNER` | Detail booking + detail line items + lokasi proyek. |
| `POST` | `/api/v1/bookings/{id}/submit` | `USER` (Own) | DRAFT → PENDING_APPROVAL dengan re-validasi availability. |

### Snapshot Harga (Backend Source of Truth)
Detail booking menyimpan:
- `rental_rate_snapshot`: tarif per jam (base rate) sesuai skema All-in / Non All-in pada tanggal booking.
- `subtotal`: `dailyRate × durationDays × quantity` (8 jam standar per hari), dihitung `PricingCalculatorService`.

---

## 5. Otorisasi & Isolasi Kepemilikan (RBAC)

- **USER:** Membuat booking dari cart miliknya; melihat & mensubmit hanya booking miliknya sendiri. Akses lintas-user → `403 FORBIDDEN`.
- **ADMIN / OWNER:** `viewAny` seluruh booking (sesuai permission matrix); mutasi approval di fase 8E.

---

## 6. Antarmuka Pengguna (`/app/bookings`)

- **`UserBookingsPage`:** Daftar kartu booking (kode unik, badge status, lokasi proyek, ringkasan detail line items, total estimasi).
- **Tombol "Ajukan ke Approval":** Terlihat hanya untuk status `DRAFT`; menampilkan dialog konfirmasi sebelum memanggil `POST /bookings/{id}/submit`.
- **CTA "Lanjut ke Booking" di halaman cart:** Memanggil `POST /bookings` (membuat DRAFT dari cart) lalu navigasi ke daftar booking.

---

## 7. Hasil Pengujian Backend (`BookingApiTest`) & Frontend (`BookingUI.test.tsx`)

**Backend (10 kasus):**
- Create DRAFT dari cart (+ snapshot detail, kode unik, cart terkonsumsi).
- Create gagal jika cart kosong / lokasi belum dipilih / ketersediaan tidak mencukupi (`409 BUSINESS_RULE_VIOLATION`).
- List hanya milik sendiri; View & Submit lintas-user ditolak (`403`).
- Submit DRAFT → PENDING_APPROVAL (profil terverifikasi).
- Submit gagal jika bukan DRAFT (`409 INVALID_STATE_TRANSITION`).
- Submit gagal jika profil belum terverifikasi (gate KYC).
- Request tanpa autentikasi ditolak (`401`).

**Frontend (4 kasus):** render list + detail, submit via dialog konfirmasi, empty state, handling error API.

**Total Quality Gate:** Backend **244 tests passed (893 assertions)**; Laravel Pint **100% clean**.