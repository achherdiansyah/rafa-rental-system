# Design System & UI Foundation - RAFA Rental System

Dokumentasi spesifikasi desain sistem, token desain, tipografi, dan katalog komponen antarmuka pengguna *(UI Components)* untuk RAFA Rental System (Phase 4C).

---

## 1. Karakter Visual & Filosofi Desain

- **Corporate, Modern, & Clean:** Dominasi warna Slate netral dipadu aksen Biru Korporat (`primary`) untuk menciptakan citra profesional sistem rental alat berat.
- **Accessible (A11y):** Dukungan penuh *keyboard navigation*, kontras warna memenuhi standar WCAG AA, penanda *required field*, dan visual *focus ring* (`*:focus-visible`).
- **Responsive-First:** Layout dan komponen dirancang adaptif untuk perangkat ponsel cerdas (*mobile*), tablet, laptop, dan layar monitor desktop lebar.
- **Business-Agnostic:** Seluruh komponen dasar di `src/components/` bebas dari ketergantungan logika transaksi bisnis.

---

## 2. Design Tokens

### 2.1 Palet Warna (Color Tokens)
- **Primary (Corporate Blue):** `--color-primary-50` s.d `--color-primary-950` (Brand, tombol utama, tautan aktif, border seleksi).
- **Neutral (Slate):** `--color-slate-50` (background dasar) s.d `--color-slate-950` (teks utama, border, shadow).
- **Semantic Feedback:**
  - `Success` (Emerald): Tagihan lunas, verifikasi sukses, unit `AVAILABLE`.
  - `Warning` (Amber): Menunggu persetujuan, deadline bayar mendekat.
  - `Danger` (Rose): Booking batal, penolakan pembayaran, unit `MAINTENANCE`, aksi destruktif.

### 2.2 Tipografi (Typography Tokens)
- **Font Family:** `Inter, system-ui, sans-serif` (Antarmuka aplikasi), `ui-monospace` (Nomor invoice, serial unit, Hour Meter).
- **Scale:**
  - `Heading 1`: 24px - 32px (font-bold, tracking-tight)
  - `Heading 2`: 20px - 24px (font-semibold)
  - `Heading 3`: 16px - 18px (font-semibold)
  - `Body Regular`: 14px (text-sm, text-slate-700)
  - `Caption / Hint`: 12px (text-xs, text-slate-500)
  - `Button Text`: 14px (text-sm, font-medium)

---

## 3. Katalog Komponen Reusable

### 3.1 Form Elements (`src/components/form/`)
- **`Input`:** Mendukung properti `label`, `error`, `hint`, `required`, dan penanganan `aria-invalid`.
- **`Textarea`:** Multiline text dengan counter baris dan state error.
- **`Select`:** Dropdown selector dengan placeholder dan opsi terstruktur.
- **`Checkbox`:** Checkbox dengan label dan deskripsi sub-teks.
- **`Radio`:** Radio option dengan styling terpadu.
- **`Switch`:** Toggle switch interaktif (Aria-checked switch role).

### 3.2 General UI Components (`src/components/ui/`)
- **`Button`:** Varian `primary`, `secondary`, `outline`, `ghost`, `danger`. Mendukung `isLoading` (spinner terintegrasi), `leftIcon`, dan `rightIcon`.
- **`Badge`:** Varian `default`, `secondary`, `success`, `warning`, `danger`, `outline`.
- **`Card`:** Komposisi modular (`CardHeader`, `CardTitle`, `CardDescription`, `CardContent`, `CardFooter`).
- **`Modal`:** Dialog overlay dengan backdrop blur, penutup tombol escape (ESC), dan scroll lock.
- **`ConfirmDialog`:** Konfirmasi aksi bahaya/penting (Cancel, Rejection, Deletion).
- **`Skeleton`:** Placeholder animasi pulsa abu-abu saat pemuatan data.
- **`Tabs`:** Navigasi horizontal dengan penanda badge jumlah data.
- **`Breadcrumb`:** Navigasi hirarki halaman.
- **`Tooltip`:** Keterangan melayang saat hover/fokus.
- **`Dropdown`:** Menu aksi kontekstual dengan deteksi klik luar (*click outside*).

### 3.3 Data Display & Feedback (`src/components/data-display/` & `src/components/feedback/`)
- **`Table`:** Komponen tabel (`TableHeader`, `TableBody`, `TableRow`, `TableHead`, `TableCell`) dengan wrapper responsif horizontal scroll.
- **`Pagination`:** Navigasi halaman dengan tombol previous/next dan penanda posisi aktif.
- **`Alert`:** Kotak pesan informatif (info, success, warning, danger) dengan opsi tutup.
- **`EmptyState`:** Tampilan ilustratif saat tabel/katalog tidak memiliki data.
- **`ErrorState`:** Tampilan penanganan galat jaringan/server dengan tombol coba lagi (*Retry*).
- **`LoadingState`:** Indikator pemuatan layar penuh atau bagian kontainer.
