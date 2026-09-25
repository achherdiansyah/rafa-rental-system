# Spesifikasi Domain & Kriteria Rekomendasi (Phase 7A) - RAFA Rental System

Dokumentasi rancangan basis data, pemodelan entitas, aturan otorisasi, dan fondasi mesin penilaian (*scoring engine*) sistem rekomendasi armada pada RAFA Rental System.

---

## 1. Arsitektur Entitas & Relasi

Sistem rekomendasi alat berat memodelkan relasi terstruktur:

```text
[User]
  │ 1:N
  ▼
[RecommendationRequest] (status: PENDING | PROCESSED | FAILED)
  ├── 1:1 ── [RecommendationCriteria] (Parameter Input: Medan, Kapasitas, Volume, Jangkauan)
  └── 1:N ── [RecommendationResult] (Skor Kecocokan, Reasoning Text, Relasi Model Armada)
```

### Tabel Basis Data

1. **`recommendation_requests`:**
   - `id` (BIGINT UNSIGNED, PK)
   - `user_id` (BIGINT UNSIGNED, FK -> `users(id)`, CASCADE)
   - `status` (VARCHAR(20), ENUM: `PENDING`, `PROCESSED`, `FAILED`)
   - `created_at`, `updated_at` (TIMESTAMP)

2. **`recommendation_criteria`:**
   - `id` (BIGINT UNSIGNED, PK)
   - `request_id` (BIGINT UNSIGNED, FK -> `recommendation_requests(id)`, UK, CASCADE)
   - `project_type` (VARCHAR(100), e.g. "Galian Basah", "Konstruksi Jalan")
   - `work_volume` (DECIMAL(12,2), nullable)
   - `terrain_condition` (VARCHAR(100), e.g. "Lumpur / Rawa", "Tanah Keras / Bebatuan")
   - `depth_requirement` (DECIMAL(8,2), nullable)
   - `reach_requirement` (DECIMAL(8,2), nullable)
   - `load_capacity` (DECIMAL(10,2), nullable)
   - `target_productivity` (VARCHAR(100), nullable)
   - `location_access` (VARCHAR(100), nullable)
   - `duration_days` (INTEGER, nullable)
   - `budget_range` (VARCHAR(50), nullable)
   - `additional_params` (JSON, nullable)

3. **`recommendation_results`:**
   - `id` (BIGINT UNSIGNED, PK)
   - `request_id` (BIGINT UNSIGNED, FK -> `recommendation_requests(id)`, CASCADE)
   - `equipment_model_id` (BIGINT UNSIGNED, FK -> `equipment_models(id)`, CASCADE)
   - `match_score` (DECIMAL(5,2), e.g. 95.50%)
   - `reasoning_text` (TEXT, nullable)
   - `created_at`, `updated_at` (TIMESTAMP)

---

## 2. Mesin Penilaian Berbobot (Weighted Rule-Based Engine)

Sistem menggunakan layanan terpisah `RecommendationScoringService` dengan konfigurasi bobot terpusat di `config/recommendation.php`:

| Komponen Kriteria | Bobot Default | Penjelasan Evaluasi |
|---|---|---|
| **`capacity_match`** | **40%** | Kesesuaian kapasitas muat armada terhadap kebutuhan beban target |
| **`terrain_suitability`** | **30%** | Kecocokan sistem penggerak (crawler track / roda) terhadap kondisi tanah |
| **`project_suitability`** | **20%** | Relevansi tipe alat terhadap jenis pekerjaan sipil/tambang |
| **`price_suitability`** | **10%** | Ketersediaan tarif sewa aktif untuk kesiapan komersial |

---

## 3. Matriks Otorisasi (RBAC)

- **USER:**
  - Dapat mengirimkan permintaan rekomendasi (`POST /api/v1/recommendations/request`).
  - Dapat melihat riwayat rekomendasi miliknya sendiri (`GET /api/v1/recommendations`).
  - Dilarang mengakses riwayat rekomendasi milik pengguna lain (`403 Forbidden`).
- **ADMIN / OWNER:**
  - Dapat melihat seluruh riwayat rekomendasi dari semua pengguna (`GET /api/v1/recommendations`).
  - Dapat melihat detail lengkap rekomendasi beserta kriteria dan skor armada (`GET /api/v1/recommendations/{id}`).

---

## 4. Endpoints REST API

| Method | URI | Actor | Deskripsi |
|---|---|---|---|
| `POST` | `/api/v1/recommendations/request` | Authenticated (`USER`) | Submit kriteria proyek dan jalankan mesin rekomendasi sinkron. |
| `GET` | `/api/v1/recommendations` | Authenticated | Ambil daftar riwayat rekomendasi (milik sendiri bagi User, semua bagi Admin/Owner). |
| `GET` | `/api/v1/recommendations/{id}` | Authenticated | Ambil rincian kriteria dan daftar armada yang direkomendasikan beserta skor. |
