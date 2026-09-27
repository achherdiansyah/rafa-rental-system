# Spesifikasi Antarmuka Pengguna Keranjang Sewa (Phase 8C) - RAFA Rental System

Dokumentasi rancangan antarmuka web keranjang sewa pelanggan (`/app/cart`), alur *Add-to-Cart* dari katalog/detail armada, dan penanganan state UX pada RAFA Rental System.

---

## 1. Tujuan Antarmuka & Prinsip Desain

Keranjang sewa dirancang sebagai area peninjauan sementara (*draft review*) sebelum pelanggan mengajukan booking resmi:
- **Backend as Source of Truth:** Seluruh nilai tarif yang ditampilkan berasal langsung dari respons API (`base_rate` per jam). Frontend **tidak pernah** menghitung ulang total biaya bisnis (durasi, MOB/DEMOB, pajak) — penghitungan resmi dilakukan oleh mesin backend pada tahap booking.
- **No Physical Unit Selection:** Pelanggan hanya menentukan seri model, kuota (`quantity`), skema sewa (*All-in* / *Non All-in*), dan rentang tanggal. Penugasan unit fisik menjadi wewenang Admin.
- **SPA Navigation:** Semua interaksi berbasis navigasi SPA React Router (tanpa *full page reload*).

---

## 2. Alur Pengguna (User Flow)

```text
1. Catalog / Equipment Detail Page
       │
2. Klik "Sewa Skema Ini" (Non All-in / All-in)
       │
3. AddToCartModal terbuka
   ├── Skema sewa (toggles)
   ├── Jumlah unit (min 1)
   ├── Tanggal mulai & selesai (validasi: mulai >= hari ini, selesai >= mulai)
   └── Lokasi proyek (dropdown lokasi milik user, opsional)
       │
4. Submit -> POST /api/v1/cart/items -> Toast Sukses
       │
5. Keranjang Sewa (/app/cart)
   ├── Dropdown lokasi proyek (PUT /api/v1/cart/location)
   ├── Daftar item (qty stepper, tanggal editable, badge skema)
   └── CTA "Lanjut ke Booking" (disiapkan untuk Phase 8D)
```

---

## 3. Komponen Antarmuka

### 3.1 `AddToCartModal`
- Dibuka dari halaman detail armada. Menampilkan tombol pemilihan skema di samping kartu tarif terkait.
- Memvalidasi input pada klien: `quantity >= 1`, `start_date >= today`, `end_date >= start_date`.
- Mengirim `AddCartItemInput` dan menampilkan *toast* sukses setelah berhasil ditambahkan.
- Menyediakan tautan `Lihat Keranjang Sewa →` untuk navigasi cepat.

### 3.2 `UserCartPage` (`/app/cart`)
- **Localization Selector:** Dropdown daftar lokasi proyek milik pengguna (via `GET /project-locations`), dengan aksi `PUT /cart/location`.
- **Cart Item Cards:** Thumbnail foto, merk/model, badge skema sewa, tarif satuan `/jam` (read-only dari API), input kuantitas, dan dua input tanggal yang dapat diedit.
- **Aksi Per-Item:** Tombol `Simpan Perubahan` (memanggil `PUT /cart/items/{id}`) dan `Hapus` (memunculkan `ConfirmDialog`).
- **Clear Cart:** Tombol `Kosongkan Keranjang` dengan konfirmasi.
- **Cta Booking:** Tombol `Lanjut ke Booking` dinonaktifkan jika lokasi proyek belum dipilih atau keranjang kosong (menjadi aktif pada Phase 8D).

---

## 4. Penanganan State UX (UX States Matrix)

| State UX | Komponen | Perilaku |
|---|---|---|
| **Loading** | `Skeleton` card | Placeholder animatif tanpa layout shift (CLS). |
| **Empty** | `EmptyState` + CTA katalog | Ajakan menjelajah katalog armada. |
| **Validation Error** | Inline error text | Indikasi merah di bawah input yang tidak valid. |
| **Network / API Error** | `Alert variant="danger"` | Banner error saat `GET /cart` gagal. |
| **Delete Confirmation** | `ConfirmDialog` | Konfirmasi sebelum menghapus item atau mengosongkan keranjang. |
| **Success Feedback** | Toast notification | Umpan balik sukses setiap mutasi CRUD. |

---

## 5. Hasil Pengujian Otomatis (`CartUI.test.tsx`)

- `shows_empty_state_when_cart_is_empty`: **PASSED**
- `lists_cart_items_with_equipment_quantity_and_scheme_badge`: **PASSED**
- `updates_item_quantity_and_calls_service_on_save`: **PASSED**
- `confirms_before_deleting_an_item`: **PASSED**
- `handles_api_error_when_loading_cart`: **PASSED**
- `add_to_cart_modal_validates_dates_before_submit`: **PASSED**
- `add_to_cart_modal_submits_successful_payload`: **PASSED**