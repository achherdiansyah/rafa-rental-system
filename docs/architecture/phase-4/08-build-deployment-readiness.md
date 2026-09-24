# Build & Deployment Readiness - RAFA Rental System

Dokumentasi standarisasi pengujian otomatis, prosedur kompilasi lokal, dan panduan peluncuran (deployment) frontend RAFA Rental System ke lingkungan produksi (Phase 4H).

---

## 1. Testing Foundation

Infrastruktur pengujian frontend menggunakan **Vitest** dan **React Testing Library**.

### 1.1 Konfigurasi Pengujian
- Engine menggunakan `jsdom` untuk menyimulasikan lingkungan browser di dalam terminal Node.
- `setupTests.ts` secara otomatis menyuntikkan `@testing-library/jest-dom/vitest` ke seluruh lingkungan test, memberikan kemampuan asersi tambahan (*custom matchers*) seperti `toBeInTheDocument()` atau `toBeDisabled()`.
- Berkas test harus berakhiran `.test.tsx` atau `.test.ts`.

### 1.2 Menjalankan Test
```bash
# Menjalankan seluruh test sekali jalan (CI/CD friendly)
npm run test

# Menjalankan test dalam mode pantau (watch) saat development
npx vitest
```

---

## 2. Pembangkitan Aset Statis (Build Process)

Aplikasi dibangun (*bundled*) menggunakan bundler **Vite** dan diompilasi tipe-nya oleh `tsc`.

### 2.1 Perintah Build
```bash
# Memeriksa TypeScript dan membangkitkan aset statis ke folder /dist
npm run build
```

### 2.2 Hasil Build (Output Folder `dist/`)
- Proses kompilasi akan melahirkan folder `dist/` yang murni berisi berkas statis (HTML, CSS, JS, SVG, dll).
- Tidak ada modul yang dijalankan melalui runtime Node.js di server (*Zero SSR*).
- File konfigurasi tambahan `.htaccess` disalin langsung dari folder `public/` agar *Client-Side Routing* tidak memunculkan `404 Not Found` di server web Apache.

---

## 3. Pratinjau Lokal (Preview)

Untuk menguji hasil build produksi secara lokal sebelum dikirim ke peladen:
```bash
npm run preview
```
Perintah ini akan menjalankan webserver statis ringan yang mensimulasikan lingkungan akhir produksi.

---

## 4. Panduan Deployment cPanel (Production)

Arsitektur aplikasi ini sengaja diformat sesuai kebutuhan target hosting (Shared Hosting cPanel standard).

### 4.1 Persyaratan Peladen
- Web server Apache atau LiteSpeed.
- Modul `mod_rewrite` aktif.
- **TIDAK** membutuhkan akses Terminal (SSH), Node.js persistent daemon, atau Container Docker.

### 4.2 Prosedur Peluncuran
1. Pada perangkat pengembangan lokal, pastikan file `.env` diisi dengan Base URL produksi:
   ```env
   VITE_API_BASE_URL=https://api.rafarental.com/v1
   ```
2. Jalankan perintah kompilasi:
   ```bash
   npm run build
   ```
3. Kompres (*ZIP*) seluruh **isi** di dalam folder `dist/`.
4. Unggah (Upload) file ZIP tersebut ke File Manager cPanel pada direktori root domain (biasanya `/public_html` atau `/public_html/subdomain`).
5. Ekstrak file ZIP di direktori tersebut.
6. Pastikan file tersembunyi `.htaccess` (yang membawa instruksi Fallback `index.html`) juga turut tersalin.

Aplikasi Frontend RAFA Rental System kini langsung tayang secara global.
