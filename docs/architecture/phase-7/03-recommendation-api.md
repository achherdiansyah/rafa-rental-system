# Spesifikasi REST API Rekomendasi (Phase 7C) - RAFA Rental System

Dokumentasi spesifikasi REST API, kontrak payload, validasi request, struktur envelope respon, dan otorisasi untuk Modul Sistem Rekomendasi Armada (`/api/v1/recommendations`).

---

## 1. Lingkup & Alur Pemrosesan API

Modul REST API Rekomendasi mengekspos fungsi *Decision Support* secara sinkron dengan alur kerja teratur:

```text
HTTP Client (USER / ADMIN / OWNER)
       │
       ▼
[Route: POST /api/v1/recommendations]
       │
       ├──> 1. Authentication Check (Sanctum 401 Guard)
       ├──> 2. Input Validation (StoreRecommendationRequest 422 Guard)
       ├──> 3. Authorization Policy (RecommendationRequestPolicy 403 Guard)
       │
       ▼
[CreateRecommendationAction]
       │
       ├──> Persist [RecommendationRequest] (status: PROCESSED)
       ├──> Persist [RecommendationCriteria] (Input parameter lapangan)
       ├──> Execute [RecommendationScoringService] (Multi-Criteria Weighted Engine)
       └──> Persist [RecommendationResult] (Skor, Peringkat, & Reasoning Text)
       │
       ▼
[Resource Envelope: RecommendationRequestResource]
       │
       ▼
HTTP JsonResponse 201 CREATED (Strict API Contract V3)
```

---

## 2. Matriks Endpoints API

| Method | Endpoint URI | Otorisasi Role | Fungsi Utama |
|---|---|---|---|
| `POST` | `/api/v1/recommendations` | `USER`, `ADMIN`, `OWNER` | Mengajukan kriteria proyek baru dan menghasilkan rekomendasi armada scored & ranked secara sinkron. |
| `POST` | `/api/v1/recommendations/request` | `USER`, `ADMIN`, `OWNER` | Alias resmi sesuai API Contract Phase 1 untuk pengajuan rekomendasi. |
| `GET` | `/api/v1/recommendations` | `USER` (Own), `ADMIN`, `OWNER` (All) | Mengambil daftar riwayat permintaan rekomendasi terpaginasi. |
| `GET` | `/api/v1/recommendations/{id}` | `USER` (Own), `ADMIN`, `OWNER` (All) | Mengambil detail kriteria proyek dan hasil peringkat rekomendasi armada lengkap. |

---

## 3. Validasi Form Request (`StoreRecommendationRequest`)

| Field Input | Tipe Data | Aturan Validasi | Deskripsi Parameter |
|---|---|---|---|
| `project_type` | `string` | `required`, `max:100` | Kategori/jenis proyek (e.g. "Galian Basah", "Land Clearing"). |
| `terrain_condition` | `string` | `required`, `max:100` | Karakteristik tanah/medan (e.g. "Lumpur / Rawa", "Bebatuan Keras"). |
| `load_capacity` | `numeric` | `nullable`, `min:0` | Kebutuhan kapasitas muat target (e.g. 20.00 Ton). |
| `work_volume` | `numeric` | `nullable`, `min:0` | Volume pekerjaan yang akan diselesaikan (e.g. 5000.00 m³). |
| `depth_requirement` | `numeric` | `nullable`, `min:0` | Kedalaman galian dalam meter. |
| `reach_requirement` | `numeric` | `nullable`, `min:0` | Jangkauan boom/arm dalam meter. |
| `target_productivity` | `string` | `nullable`, `max:100` | Target produktivitas operasional. |
| `location_access` | `string` | `nullable`, `max:100` | Akses lokasi pengiriman. |
| `duration_days` | `integer` | `nullable`, `min:1` | Estimasi durasi sewa proyek dalam hari. |
| `budget_range` | `string` | `nullable`, `max:50` | Batas/kisaran estimasi anggaran. |

---

## 4. Contoh HTTP Request & Response Payload

### `POST /api/v1/recommendations`

**Request Body:**
```json
{
  "project_type": "Galian Basah dan Drainase",
  "terrain_condition": "Lumpur / Rawa",
  "load_capacity": 20.00,
  "depth_requirement": 4.50,
  "duration_days": 14
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Rekomendasi armada alat berat berhasil diproses.",
  "data": {
    "id": 15,
    "user_id": 2,
    "status": "PROCESSED",
    "criteria": {
      "id": 15,
      "request_id": 15,
      "project_type": "Galian Basah dan Drainase",
      "terrain_condition": "Lumpur / Rawa",
      "load_capacity": 20.0,
      "depth_requirement": 4.5,
      "duration_days": 14
    },
    "results": [
      {
        "id": 42,
        "request_id": 15,
        "equipment_model_id": 1,
        "rank": 1,
        "match_score": 98.9,
        "reasoning_text": "Model Komatsu PC200-8 (Kapasitas 20.00 Ton) memperoleh total skor kesesuaian 98.9%. Kapasitas muat unit sangat efisien dan memadai untuk beban target 20.00 Ton. Konfigurasi traksi sesuai untuk karakteristik medan 'Lumpur / Rawa'. Mendukung kedalaman operasional hingga 4.50 meter.",
        "model": {
          "id": 1,
          "brand": "Komatsu",
          "model_name": "PC200-8",
          "capacity_value": 20.0,
          "capacity_unit": "Ton",
          "is_active": true,
          "type": {
            "id": 1,
            "name": "Hydraulic Excavator"
          },
          "prices": [
            {
              "id": 1,
              "price_type": "HOURLY",
              "is_all_in": false,
              "base_rate": 250000.0,
              "minimum_hours": 8
            }
          ],
          "attachments": [
            {
              "id": 5,
              "document_type": "EQUIPMENT_PHOTO",
              "url": "http://localhost/storage/equipment/pc200.jpg"
            }
          ],
          "units_count": 3
        }
      }
    ],
    "created_at": "2026-09-25T17:50:00.000000Z"
  }
}
```

---

## 5. Matriks Penanganan Error (Error Handling Envelope)

| Kode HTTP | Error Code | Skenario Pemicu | Payload Envelope |
|---|---|---|---|
| `401 Unauthorized` | `UNAUTHENTICATED` | Request dikirim tanpa header Sanctum Bearer Token. | `{ "success": false, "message": "Unauthenticated.", "code": "UNAUTHENTICATED" }` |
| `403 Forbidden` | `FORBIDDEN` | Pelanggan mencoba membuka detail rekomendasi milik pengguna lain. | `{ "success": false, "message": "This action is unauthorized.", "code": "FORBIDDEN" }` |
| `422 Unprocessable` | `VALIDATION_ERROR` | Parameter wajib (`project_type`, `terrain_condition`) kosong atau bernilai negatif. | `{ "success": false, "message": "The given data was invalid.", "errors": { "project_type": ["The project type field is required."] }, "code": "VALIDATION_ERROR" }` |
| `404 Not Found` | `RESOURCE_NOT_FOUND` | ID `recommendationRequest` tidak ditemukan di database. | `{ "success": false, "message": "Resource not found.", "code": "RESOURCE_NOT_FOUND" }` |
