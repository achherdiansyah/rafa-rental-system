# Performance, Responsive & Accessibility (A11y) - RAFA Rental System

Dokumentasi spesifikasi optimasi performa navigasi, adaptasi antar-perangkat (responsivitas), dan aksesibilitas frontend RAFA Rental System (Phase 4G).

---

## 1. Fondasi Responsif (Responsive Design)

Frontend dirancang dengan prinsip *Mobile-First* memanfaatkan kelas breakpoint Tailwind CSS (`sm:`, `md:`, `lg:`, `xl:`).

| Aspek Layout | Implementasi Adaptif |
|---|---|
| **Container Utama** | Maksimal lebar dibatasi hingga `max-w-7xl` (1280px) pada desktop agar terbaca nyaman. Pada mobile, lebar mengisi 100% layar dengan *padding* tepi aman (`px-4`). |
| **Navigasi (Navbar)** | Menyembunyikan teks label dan menyisakan *icon* pada layar kecil. Tombol menu *hamburger* muncul otomatis di mobile. |
| **Sidebar Portal** | Di desktop, tampil sebagai panel diam (*persistent fixed*). Di tablet/mobile, berubah menjadi laci (*off-canvas drawer*) tersembunyi dengan *backdrop blur* yang dapat di-toggle (`isMobileOpen`). |
| **Data Tabel** | Komponen `Table` dibungkus `<div className="overflow-auto">` yang memungkinkan *scroll* horizontal halus pada layar kecil tanpa merusak layout luar (No vertical/horizontal overlap). |
| **Target Sentuh** | *Touch targets* pada form (`Input`, `Select`, `Button`) dirancang memiliki tinggi minimal 40px (`h-10`) untuk akurasi ketukan di layar sentuh. |

---

## 2. Kinerja Navigasi & SPA (Performance)

Aplikasi diprogram untuk beroperasi secepat kilat tanpa *full-page reload* dan tanpa redundansi jaringan:

1. **Route Lazy Loading & Code Splitting:**
   - Seluruh Page Component (misal `HomePage`, `LoginPage`) di-import asinkron via `React.lazy()`.
   - Vite memecah (*split*) kode aplikasi menjadi chunk-chunk kecil independen. Pengunjung publik tidak akan memuat ukuran JavaScript Dashboard Admin hingga mereka benar-benar masuk.
2. **Skeleton & Suspense:**
   - Layar putih sesaat akibat unduhan chunk JavaScript dicegah oleh batas *fallback* `<Suspense>` yang menampilkan `<LoadingState />` secara instan.
3. **Debouncing (Pencegah Banjir API):**
   - Hook terdedikasi `useDebounce(value, 300ms)` tersedia di `src/hooks/useDebounce.ts`. Fitur ini wajib dipakai pada fitur *Search/Filter* agar request backend tidak terjadi setiap kali pengguna mengetik huruf, melainkan jeda 0.3 detik sesudah pengetikan usai.

---

## 3. Aksesibilitas (A11y)

| Fokus WCAG | Standar Penerapan |
|---|---|
| **Keyboard Navigation** | Modals mendukung ESC *key* untuk menutup. Seluruh elemen interaktif dapat dijangkau menggunakan tombol `TAB`. |
| **Focus Rings** | Visualisasi outline biru standar muncul otomatis (`focus-visible:ring-2`) saat elemen aktif. Outline dimatikan saat klik tetikus biasa agar estetika terjaga. |
| **Semantic HTML** | Menggunakan `<nav>`, `<main>`, `<aside>`, `<header>`, dan `<article>`. Tag `<button>` tidak dicampur dengan tag `<a>` secara fungsional. |
| **Form Attributes** | Relasi korelasi ketat via `id` & `htmlFor`. Label menggunakan properti `aria-invalid` jika gagal validasi, serta `aria-describedby` untuk instruksi *hint*. |
| **Color Contrast** | Warna primer (`blue-600` #2563eb) pada teks putih dan warna bahaya (`rose-600`) telah dievaluasi memenuhi rasio kontras standar minimal 4.5:1. |

---

## 4. Panduan Aset Media (Images)

- **Format Wajib:** Prioritaskan ekstensi **`.webp`** untuk ilustrasi dan foto unit alat berat.
- **Dimensi:** Resolusi hero banner / katalog dibatasi maksimum 1200px lebar. 
- **Loading:** Terapkan properti `loading="lazy"` secara mandiri pada elemen `<img />` yang berada di luar tangkapan layar utama (*below the fold*) (katalog panjang).

---

## 5. Ringan Saat Pengembangan (Local Development)

- Tidak memerlukan arsitektur Node.js persistent proxy.
- Konfigurasi Vite HMR (Hot Module Replacement) menyajikan pembaruan UI lokal rata-rata `< 20ms`.
- Tidak ada keharusan menghidupkan *Docker Daemon* hanya untuk mengubah CSS atau antarmuka halaman Frontend.
