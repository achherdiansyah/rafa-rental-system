# Frontend Scaffold & Tooling Specification - RAFA Rental System

Dokumentasi rancangan fondasi frontend Single Page Application (SPA) untuk RAFA Rental System (Phase 4A).

---

## 1. Stack & Tooling

- **Framework:** React 19 (SPA murni)
- **Bundler / Dev Server:** Vite 8+
- **Language:** TypeScript 6+ (Strict Mode)
- **Styling:** Tailwind CSS v4 (`@tailwindcss/vite`)
- **Icons:** Lucide React
- **HTTP Client:** Axios
- **Linting:** Oxlint (Vite native fast linter)

---

## 2. Struktur Direktori Frontend

```
frontend/
├── dist/                     # Production static build output
├── public/                   # Static public assets (favicon, icons)
├── src/
│   ├── assets/               # Local images and graphic assets
│   ├── components/           # Reusable UI components (buttons, modals, inputs)
│   ├── layouts/              # App layouts (MainLayout, AdminLayout, AuthLayout)
│   ├── lib/                  # Shared utilities (api client, formatters, cn helper)
│   │   └── api.ts            # Axios instance with VITE_API_BASE_URL
│   ├── pages/                # Route views / page components
│   ├── types/                # Shared TypeScript definitions
│   ├── App.tsx               # Root component
│   ├── index.css             # Tailwind CSS entrypoint (@import "tailwindcss")
│   └── main.tsx              # Application mount point
├── .env.example              # Environment variables template
├── index.html                # HTML entry template
├── package.json              # Dependencies and scripts
├── tsconfig.json             # Root TypeScript config
├── tsconfig.app.json         # App TypeScript config with @/* alias
└── vite.config.ts            # Vite configuration with React and Tailwind plugins
```

---

## 3. Konfigurasi Environment Variable

Frontend membaca base URL API backend melalui variabel lingkungan Vite:

```env
# .env.example
VITE_API_BASE_URL=http://127.0.0.1:8000/api/v1
```

- Seluruh pemanggilan HTTP menggunakan `apiClient` (`src/lib/api.ts`) yang merujuk pada `import.meta.env.VITE_API_BASE_URL`.
- File `.env` lokal **DILARANG DI-COMMIT** dan telah dimasukkan ke dalam `.gitignore`.

---

## 4. Kompatibilitas Deployment cPanel

- **Zero Node.js Server:** Frontend tidak menggunakan server Next.js atau SSR. Hasil `npm run build` adalah file statis murni (`dist/index.html`, `dist/assets/*.js`, `dist/assets/*.css`).
- **Deployment cPanel:**
  1. Jalankan `npm run build` secara lokal atau via CI/CD.
  2. Salin isi folder `frontend/dist/` ke direktori `public_html` atau subdomain di cPanel.
  3. Konfigurasi `.htaccess` fallback sederhana untuk React Router (Single Page Application).
- **Development Lokal:** Berjalan native via `npm run dev` tanpa dependensi Docker.

---

## 5. Development & Build Commands

```bash
# Install dependencies
npm install

# Start local development server (HMR)
npm run dev

# Run TypeScript check and build static production bundle
npm run build

# Run linter
npm run lint
```
