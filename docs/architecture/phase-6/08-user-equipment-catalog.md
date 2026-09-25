# Antarmuka Katalog Armada Pelanggan (User Equipment Catalog) - RAFA Rental System

Dokumentasi spesifikasi rancangan dan implementasi antarmuka katalog alat berat pelanggan (`/app/equipment` dan `/app/equipment/:id`) pada RAFA Rental System (Phase 6H).

---

## 1. Lingkup Modul Katalog Pelanggan

Antarmuka katalog pelanggan dirancang murni untuk **penjelajahan dan inspeksi spesifikasi teknis**:
- **Dukungan Fitur:** Browsing kartu armada, pencarian ter-debounce (300ms), filter kategori dan merk pabrikan, paginasi sisi server, tampilan detail spesifikasi teknis, galeri foto interaktif, perbandingan skema sewa (*All-in* vs *Non All-in*).
- **Larangan Scope (Strict Guard):** Modul ini **TIDAK MEMBUAT** alur keranjang belanja (*cart*), booking checkout, atau alur pembayaran (modul tersebut diimplementasikan pada fase berikutnya).

---

## 2. Halaman yang Diimplementasikan

| Rute Frontend | Komponen Halaman | Akses / Role | Deskripsi |
|---|---|---|---|
| `/app/equipment` | `EquipmentCatalogPage.tsx` | `USER` | Grid kartu armada dengan thumbnail foto, merk/model, kapasitas, tarif terendah, dan filter real-time. |
| `/app/equipment/:id` | `EquipmentDetailPage.tsx` | `USER` | Halaman detail armada: galeri foto multi-angle, tabel spesifikasi teknis, badge kesiapan BAST, dan perbandingan kartu tarif All-in vs Non All-in. |

---

## 3. Keamanan & Penyembunyian Data Internal (Information Privacy)

Sesuai arahan PRD V3 dan Phase 1:
1. **Nomor Seri & Plat Fisik Disembunyikan:** Pelanggan hanya melihat level seri model (`Komatsu PC200-8`), bukan nomor seri individual unit fisik di pool (`KM-PC200-001`).
2. **Riwayat Versi Tarif Lampau Disembunyikan:** Pelanggan hanya melihat tarif aktif saat ini yang berlaku publik.
3. **Catatan Servis Internal Disembunyikan:** Catatan mekanik bengkel atau log pemeliharaan tidak diekspos ke antarmuka katalog.

---

## 4. Performa & Optimasi Visual

- **Lazy-Loaded Images:** Gambar utama dan thumbnail foto armada menggunakan atribut `loading="lazy"` untuk menghemat bandwidth.
- **Card Skeletons:** Komponen `CardSkeleton` disajikan secara instan selama request HTTP berlangsung untuk mencegah layout shift (*CLS*).
- **Responsive-First:** Grid beradaptasi dari 1 kolom (mobile), 2 kolom (tablet), hingga 3 kolom (desktop/laptop).

---

## 5. Hasil Pengujian Antarmuka (`EquipmentCatalog.test.tsx`)

- `renders_user_equipment_catalog_grid_with_cards_and_lowest_price`: PASSED (Menampilkan kartu alat dan tarif terendah per jam).
- `filters_catalog_by_search_input`: PASSED (Pencarian teks ter-debounce memfilter request API secara efisien).
- `renders_equipment_detail_page_with_specifications_and_pricing_schemes`: PASSED (Halaman detail menampilkan spesifikasi kapasitas dan komparasi skema sewa).
