# Spesifikasi Backend Keranjang Sewa (Phase 8B) - RAFA Rental System

Dokumentasi arsitektur backend, skema data, aturan bisnis otorisasi & isolasi kepemilikan, REST API contract, dan validasi untuk modul Keranjang Belanja (*Shopping Cart*).

---

## 1. Tujuan Modul Keranjang (Cart)

Modul Keranjang Sewa (`/api/v1/cart`) berfungsi sebagai area penampungan sementara (*shopping cart / draft items*) bagi pelanggan sebelum melanjutkan ke tahap pembuatan booking resmi (`Booking`).

Prinsip Utama:
- **No Physical Unit Selection:** Pelanggan hanya memilih seri model armada (`equipment_models`), kuota unit (`quantity`), skema sewa (*All-in* / *Non All-in*), dan tanggal sewa. Penugasan unit fisik dilakukan oleh Admin pada tahap *Booking Assignment*.
- **No Unit Reservation:** Keranjang tidak mengunci (*lock*) atau mereservasi unit fisik di basis data. Ketersediaan resmi diperiksa ulang pada tahap checkout booking.
- **Strict User Ownership:** Setiap pengguna memiliki tepat 1 entitas `Cart` aktif (1 User = 1 Cart). Pengguna lain dilarang mengakses, menambah, merubah, atau menghapus item keranjang milik pengguna lain (`403 Forbidden`).
- **Single Location Scope:** Keranjang mencatat referensi `project_location_id` tunggal.

---

## 2. Struktur Data Basis Data

### Tabel `carts`
- `id` (`BIGINT UNSIGNED`, PK)
- `user_id` (`BIGINT UNSIGNED`, FK -> `users.id`, UNIQUE, CASCADE DELETE)
- `project_location_id` (`BIGINT UNSIGNED`, FK -> `project_locations.id`, NULLABLE, NULL ON DELETE)
- `created_at`, `updated_at` (`TIMESTAMP`)

### Tabel `cart_items`
- `id` (`BIGINT UNSIGNED`, PK)
- `cart_id` (`BIGINT UNSIGNED`, FK -> `carts.id`, CASCADE DELETE)
- `equipment_model_id` (`BIGINT UNSIGNED`, FK -> `equipment_models.id`, CASCADE DELETE)
- `quantity` (`INT`, default 1)
- `is_all_in` (`BOOLEAN`, default false)
- `start_date` (`DATE`)
- `end_date` (`DATE`)
- `created_at`, `updated_at` (`TIMESTAMP`)

---

## 3. Matriks REST API Endpoints

| Method | URI | Actor | Deskripsi |
|---|---|---|---|
| `GET` | `/api/v1/cart` | `USER` | Mengambil keranjang sewa aktif milik pengguna. Otomatis membuat entri `Cart` kosong jika belum ada. |
| `POST` | `/api/v1/cart/items` | `USER` | Menambahkan armada ke keranjang sewa. Jika item dengan model, skema All-in, dan tanggal yang sama sudah ada, kuantitas akan ditambahkan (*increment*). |
| `PUT/PATCH` | `/api/v1/cart/items/{id}` | `USER` | Pembaruan kuantitas, skema sewa, atau rentang tanggal item keranjang. |
| `DELETE` | `/api/v1/cart/items/{id}` | `USER` | Menghapus item dari keranjang sewa. |
| `DELETE` | `/api/v1/cart` | `USER` | Mengosongkan keranjang sewa dan mengosongkan referensi `project_location_id`. |
| `PUT` | `/api/v1/cart/location` | `USER` | Memperbarui lokasi proyek utama pada keranjang sewa. |

---

## 4. Aturan Validasi & Penanganan Error

1. **`equipment_model_id`:** Harus berupa ID valid dari model armada yang berstatus **aktif** (`is_active = true`). Jika model inaktif, sistem mengembalikan `409 BUSINESS_RULE_VIOLATION`.
2. **`quantity`:** Wajib bernilai numerik minimal 1 (`min:1`).
3. **`start_date` & `end_date`:** `start_date` wajib sama dengan atau setelah hari ini (`after_or_equal:today`). `end_date` wajib sama dengan atau setelah `start_date` (`after_or_equal:start_date`).
4. **`project_location_id`:** Lokasi proyek yang dikirimkan wajib terdaftar milik pengguna yang bersangkutan. Jika milik pengguna lain, sistem menolak dengan `409 BUSINESS_RULE_VIOLATION`.
5. **Otorisasi Akses:** Upaya mengubah/menghapus item keranjang milik pengguna lain ditolak oleh `CartItemPolicy` dengan respon `403 Forbidden`.

---

## 5. Hasil Pengujian Backend (`CartApiTest`)

- `test_user_can_view_own_cart_and_it_auto_creates_if_missing`: **PASSED**
- `test_user_can_add_item_to_cart_and_increment_quantity_on_duplicate`: **PASSED**
- `test_user_can_update_cart_item`: **PASSED**
- `test_user_can_delete_cart_item`: **PASSED**
- `test_user_can_clear_entire_cart`: **PASSED**
- `test_user_can_update_cart_location`: **PASSED**
- `test_user_cannot_access_other_users_cart_items`: **PASSED**
- `test_validation_fails_for_inactive_equipment`: **PASSED**
- `test_validation_fails_for_other_users_project_location`: **PASSED**
