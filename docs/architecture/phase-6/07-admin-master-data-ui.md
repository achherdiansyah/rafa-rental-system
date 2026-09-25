# Standardisasi Antarmuka Manajemen Master Data Admin (Admin Master Data UI) - RAFA Rental System

Dokumentasi spesifikasi arsitektur antarmuka pengguna *(Admin UI/UX)* untuk seluruh modul master data (Equipment Type, Equipment Model, Equipment Unit, Pricing, dan Bank Account) pada RAFA Rental System (Phase 6G).

---

## 1. Konsistensi Pola Antarmuka (Standard UI Patterns)

Seluruh modul master data menerapkan pola desain terpadu berbasis *Design System Phase 4*:

```
[ Master Data Page Header (Title + CTA Button) ]
       │
       ▼
[ Filter & Search Toolbar (Debounced Search + Dropdowns) ]
       │
       ▼
[ Data Display (Table / TableSkeleton / EmptyState / ErrorState) ]
       │
       ▼
[ Pagination Controls (Current Page, Total Pages, Next/Prev) ]
       │
       ▼
[ Modals (Form Create/Edit, Action Status, ConfirmDialog) ]
```

---

## 2. Ringkasan Modul Antarmuka Admin & Owner

| Modul Master Data | Rute Frontend | Akses Otorisasi | Komponen Utama |
|---|---|---|---|
| **Equipment Type & Model** | `/admin/equipment` | `ADMIN`, `OWNER` | Tabs `models` & `types`, Filter Merk/Kategori, Form Modal Model & Tipe, Galeri Foto Modal. |
| **Physical Equipment Unit** | `/admin/units` | `ADMIN`, `OWNER` | Filter Model/Status, Badge Status Operasional, Modal Ubah Status Servis (`MAINTENANCE`). |
| **Master Pricing & Version** | `/owner/pricing` | `OWNER` Only | Filter Skema All-in/Non All-in, Form Penetapan Tarif, Modal Audit Riwayat Versi Harga. |
| **Company Bank Account** | `/admin/banks` & `/owner/settings` | `ADMIN`, `OWNER` | Toggle Switch `is_active`, Form Modal Rekening Perusahaan, Badge Status Penerimaan. |

---

## 3. Efisiensi Performa & Pengalaman Pengguna (Performance & UX)

1. **Server-Side Pagination & Filtering:**
   - Tidak memuat seluruh tabel database ke browser sekaligus. Seluruh query mendukung paginasi server-side (`per_page=10`).
   - Pencarian teks terlindungi oleh hook `useDebounce` (300ms) untuk mencegah banjir pemanggilan API backend.
2. **Penebalan Keamanan Otorisasi di Sisi Klien:**
   - Tombol penetapan dan pembaruan tarif master hanya ditampilkan untuk pengguna ber-peran `OWNER`.
   - Modul `EquipmentUnit` dan `BankAccounts` memvalidasi peran sebelum mengirim mutasi ke backend.
   - Backend Laravel tetap menjadi *single source of truth* untuk seluruh otorisasi data (403 Forbidden via Gate).
3. **Penanganan Validasi Form:**
   - Galat validasi server-side (HTTP 422 `VALIDATION_FAILED`) dipetakan secara presisi ke masing-masing input form tanpa menghilangkan isian pengguna.

---

## 4. Hasil Pengujian Antarmuka (`AdminMasterDataUI.test.tsx`)

- `renders_equipment_master_page_with_search_filter_and_table`: PASSED.
- `renders_physical_units_empty_state_when_no_units_registered`: PASSED.
- `renders_owner_pricing_empty_state_and_filter_options`: PASSED.
- `renders_bank_accounts_empty_state_and_create_action`: PASSED.
