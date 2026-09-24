# Architecture Decision Records (ADR) V3 - RAFA Rental System

Catatan keputusan arsitektur (Architecture Decision Records) yang telah disetujui dan terkunci untuk RAFA Rental System (Phase 1G).

---

## ADR-001: Frontend React + Vite + TypeScript + Tailwind
- **Context:** Dibutuhkan frontend SPA modern yang ringan, cepat di-build, dan deployable ke hosting statis (cPanel).
- **Decision:** Menggunakan React 18+ dengan bundler Vite, TypeScript untuk type-safety, dan Tailwind CSS untuk styling utility-first.
- **Reason:** Vite build output menghasilkan file statis murni (HTML/CSS/JS) yang bisa di-upload ke direktori `public_html` cPanel tanpa membutuhkan persistent Node.js server. TypeScript mencegah bug runtime. Tailwind mengurangi CSS custom.
- **Alternative:** Next.js (butuh server Node.js), Vue + Nuxt (butuh SSR server), plain HTML/jQuery (tidak scalable).
- **Consequence:** Frontend sepenuhnya terpisah dari backend. Deployment cukup upload folder `dist/` hasil `vite build`. Tidak ada SSR.
- **Status:** ACCEPTED

---

## ADR-002: Laravel API Backend Terpisah dari Frontend
- **Context:** Sistem membutuhkan arsitektur yang memisahkan tanggung jawab antara penyajian data (API) dan presentasi (UI).
- **Decision:** Laravel 12 berfungsi murni sebagai REST API backend. Tidak menyajikan view/blade template. Frontend React mengonsumsi endpoint JSON.
- **Reason:** Decoupling frontend-backend memungkinkan deployment independen, tim frontend dan backend bekerja paralel, dan fleksibilitas penggantian frontend di masa depan tanpa mengubah API.
- **Alternative:** Laravel Monolith + Blade/Inertia.js (tight coupling, sulit split deploy di cPanel).
- **Consequence:** Butuh konfigurasi CORS. Autentikasi menggunakan token-based (Sanctum) bukan session-based.
- **Status:** ACCEPTED

---

## ADR-003: Docker Hanya Opsional untuk Development
- **Context:** Target production adalah shared hosting cPanel yang tidak mendukung Docker.
- **Decision:** Docker (`docker-compose.yml`) hanya digunakan untuk menjalankan MySQL 8.4 dan phpMyAdmin di environment development lokal. Docker bukan kebutuhan production.
- **Reason:** Shared hosting tidak menyediakan Docker runtime. Dependency wajib production hanya PHP 8.2+ dan MySQL 8.4.
- **Alternative:** Docker full-stack (App + DB + Nginx) — tidak kompatibel dengan target hosting.
- **Consequence:** Tidak ada Dockerfile untuk aplikasi Laravel/React. Developer tanpa Docker dapat menggunakan MySQL lokal native.
- **Status:** ACCEPTED

---

## ADR-004: MySQL sebagai Source of Truth
- **Context:** Sistem membutuhkan sumber data tunggal yang konsisten untuk seluruh kalkulasi ketersediaan, keuangan, dan audit.
- **Decision:** MySQL 8.4 menjadi satu-satunya *source of truth*. Tidak ada Redis, Elasticsearch, atau datastore eksternal yang bertindak sebagai sumber kebenaran primer.
- **Reason:** Shared hosting hanya menyediakan MySQL. Konsistensi data terjamin melalui ACID transactions dan foreign key constraints bawaan InnoDB.
- **Alternative:** PostgreSQL (tidak tersedia di sebagian besar cPanel), Redis sebagai primary store (volatil, tidak sesuai untuk data finansial).
- **Consequence:** Availability dihitung langsung dari query SQL. Caching layer (jika ada di masa depan) hanya bertindak sebagai read-cache yang bisa di-evict.
- **Status:** ACCEPTED

---

## ADR-005: Database Queue (Tanpa Redis/Horizon)
- **Context:** Background job processing diperlukan untuk: auto-expire booking, payment reminders, dan notifikasi. Redis dan Horizon dilarang di production.
- **Decision:** Menggunakan `QUEUE_CONNECTION=database` bawaan Laravel dengan tabel `jobs`. Scheduler dijalankan via cPanel cron job (`php artisan schedule:run`).
- **Reason:** Shared hosting mendukung cron job native. Database queue tidak membutuhkan daemon persistent dan kompatibel penuh dengan cPanel.
- **Alternative:** Redis + Horizon (butuh daemon, tidak tersedia di shared hosting), Supervisor (butuh VPS/dedicated).
- **Consequence:** Throughput queue lebih rendah dibanding Redis, namun cukup untuk volume transaksi rental alat berat (non-high-frequency). Latency notifikasi bergantung pada interval cron (biasanya setiap menit).
- **Status:** ACCEPTED

---

## ADR-006: Target Deployment Shared Hosting / cPanel
- **Context:** Keputusan bisnis menetapkan hosting production menggunakan shared hosting cPanel.
- **Decision:** Seluruh arsitektur dirancang kompatibel penuh dengan batasan shared hosting cPanel. Dependency production yang dilarang: Docker, Redis, Horizon, MinIO, custom Nginx, persistent Node.js server.
- **Reason:** Biaya operasional rendah, setup sederhana, dan sesuai skala bisnis rental awal.
- **Alternative:** VPS/Cloud (biaya lebih tinggi, butuh DevOps), PaaS seperti Laravel Forge/Vapor (overhead biaya dan kompleksitas).
- **Consequence:** File storage menggunakan disk lokal Laravel. Queue menggunakan database. Notifikasi real-time diganti polling API. Tidak ada WebSocket.
- **Status:** ACCEPTED

---

## ADR-007: Tiga Role Saja (USER, ADMIN, OWNER)
- **Context:** Sistem membutuhkan role-based access control yang sederhana namun memadai.
- **Decision:** Hanya 3 role yang didefinisikan: `USER`, `ADMIN`, `OWNER`. Disimpan sebagai ENUM column di tabel `users`.
- **Reason:** Kompleksitas bisnis saat ini tidak memerlukan granularitas lebih (misal: Finance, Operator, Supervisor). Tiga role mencukupi untuk memisahkan tanggung jawab: penyewa, operasional, dan pemilik bisnis.
- **Alternative:** RBAC penuh dengan tabel `roles` + `permissions` + pivot (over-engineering untuk skala awal).
- **Consequence:** Jika di masa depan butuh role tambahan, migrasi dari ENUM ke tabel `roles` terpisah diperlukan. Untuk saat ini, Policy/Gate Laravel cukup menangani otorisasi dengan 3 role.
- **Status:** ACCEPTED

---

## ADR-008: Admin Unit Assignment (User Dilarang Memilih Unit Fisik)
- **Context:** Pengguna menyewa berdasarkan spesifikasi model peralatan, bukan memilih nomor seri unit individual.
- **Decision:** User hanya memesan kuota kapasitas model alat. Admin yang menentukan unit fisik spesifik mana yang dikirim ke lokasi proyek melalui modul Unit Assignment.
- **Reason:** Mencegah user mendikte kondisi/usia unit tertentu. Memberikan fleksibilitas operasional Admin untuk mengatur rotasi armada, prioritas maintenance, dan efisiensi logistik.
- **Alternative:** User memilih unit (tidak praktis, membatasi fleksibilitas operasional pool).
- **Consequence:** Tabel `booking_details` hanya menyimpan `equipment_model_id`. Tabel `booking_unit_assignments` menjembatani alokasi unit fisik. Assignment dapat diganti (re-assignment) tanpa mengubah booking user.
- **Status:** ACCEPTED

---

## ADR-009: Price Versioning & Financial Snapshot Immutability
- **Context:** Harga sewa berpotensi berubah kapan saja oleh Owner. Data finansial historis tidak boleh berubah akibat update harga master.
- **Decision:** Setiap perubahan harga master dicatat di `equipment_price_versions`. Nilai moneter yang masuk ke `booking_details` dan `invoice_details` disimpan sebagai *snapshot copy* (`rental_rate_snapshot`, `unit_price`) yang kebal terhadap perubahan harga master di masa depan.
- **Reason:** Integritas audit keuangan. Invoice yang sudah terbit harus menampilkan harga pada saat transaksi terjadi, bukan harga terkini.
- **Alternative:** Hanya menyimpan FK ke harga master (data historis rusak saat harga berubah).
- **Consequence:** Duplikasi data moneter (snapshot), namun kebenaran historis terjamin mutlak. Storage overhead minimal.
- **Status:** ACCEPTED

---

## ADR-010: Audit Trail & Immutable Activity Log
- **Context:** Regulasi bisnis dan kebutuhan akuntabilitas mengharuskan pencatatan jejak perubahan data kritis yang tidak dapat dihapus atau dimanipulasi.
- **Decision:** Tabel `activity_logs` mencatat seluruh mutasi status booking, invoice, payment, timesheet, dan unit assignment. Log bersifat append-only dan tidak dapat di-update/delete oleh role manapun termasuk Owner.
- **Reason:** Mencegah manipulasi data finansial dan operasional. Menyediakan bukti forensik jika terjadi sengketa.
- **Alternative:** Logging ke file saja (mudah dihapus, sulit di-query).
- **Consequence:** Tabel `activity_logs` akan tumbuh seiring waktu. Perlu strategi archival/partitioning jika volume transaksi membesar.
- **Status:** ACCEPTED

---

## ADR-011: API Versioning Prefix `/api/v1`
- **Context:** API perlu mendukung evolusi tanpa memecah kontrak dengan frontend yang sudah terdeploy.
- **Decision:** Seluruh endpoint API menggunakan prefix versi `/api/v1/...`. Jika terjadi breaking change di masa depan, versi baru (`/api/v2/`) dibuat tanpa menghapus endpoint lama.
- **Reason:** Memungkinkan frontend lama tetap berfungsi selama masa transisi migrasi API. Standar industri REST API versioning.
- **Alternative:** Header-based versioning (`Accept: application/vnd.rafa.v1+json`) — lebih kompleks untuk di-debug dan test.
- **Consequence:** Route file Laravel terorganisir per versi. Folder controller terstruktur `App\Http\Controllers\Api\V1\`.
- **Status:** ACCEPTED
