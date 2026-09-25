# Phase 7: Intelligent Decision Support & Recommendation System — RAFA Rental System

Dokumentasi arsitektur, algoritma penilaian berbobot (weighted rule-based scoring), dan integrasi sistem rekomendasi alat berat untuk RAFA Rental System.

---

## 1. Tujuan Phase 7

Membangun modul **Recommendation System** sebagai *Decision Support Tool* bagi pelanggan dan admin dalam memilih armada alat berat yang optimal berdasarkan kriteria teknis lapangan, volume pekerjaan, jenis proyek, dan kondisi medan.

Prinsip Utama:
- **Rule-Based Recommendation & Weighted Scoring:** Menghitung skor kecocokan armada berdasarkan formula bobot terkonfigurasi.
- **Strictly In-App (No Python / ML / External AI):** Seluruh evaluasi diproses secara native dalam ekosistem PHP/Laravel untuk efisiensi performa dan kompatibilitas shared hosting / cPanel.
- **Audit & Riwayat Rekomendasi:** Setiap permintaan rekomendasi dan hasil penilaian armada disimpan secara permanen untuk referensi riwayat pengguna.

---

## 2. Struktur Subphase

| Subphase | Fokus Modul | Status |
|---|---|---|
| **7A** | Domain Foundation, Entity Relasi, Input Kriteria, & Scoring Engine | Selesai (`1d757b6`) |
| **7B** | Implementasi Bobot & Aturan Evaluasi Detail Multi-Kriteria | Selesai (`1647307`) |
| **7C** | REST API Endpoints, Sanitasi Response, Paginasi, & Otorisasi RBAC | Selesai (`978f476`) |
| **7D** | Antarmuka Rekomendasi Pelanggan, Wizard Input, & Integrasi Katalog | Selesai (`646abc7`) |
| **7E** | Integrasi Ketersediaan Armada (*Availability-Aware Recommendations*) | Selesai (`ee99832`) |
| **7F** | Pengujian Integrasi Menyeluruh, Skenario Bisnis Kritis, & Quality Gate | Selesai (Aktif) |
| **7G** | Final Architecture Review & Git Merge | Pending |

---

## 3. Daftar Dokumen

- `01-recommendation-domain.md`: Spesifikasi entitas basis data (`recommendation_requests`, `recommendation_criteria`, `recommendation_results`), relasi model, arsitektur *Rule-Based Scoring Service*, dan REST API.
- `02-scoring-engine.md`: Spesifikasi matematis formula *Weighted Scoring*, normalisasi bobot, mekanisme penentuan peringkat deterministik (*tie-breaking*), dan pelacakan teks eksplanasi.
- `03-recommendation-api.md`: Spesifikasi REST API (`/api/v1/recommendations`), validasi *StoreRecommendationRequest*, struktur *resource envelope*, paginasi, dan matriks otorisasi RBAC.
- `04-recommendation-ui.md`: Spesifikasi antarmuka pengguna (`/app/recommendations`), alur wizard kriteria, kartu peringkat armada, dan penanganan state UX.
- `05-availability-aware-recommendation.md`: Spesifikasi penyaringan ketersediaan armada, deteksi bentrok jadwal, dan layanan bersama `EquipmentAvailabilityService`.
- `06-recommendation-test-report.md`: Laporan pengujian integrasi komprehensif, validasi 10 skenario bisnis kritis, uji performa, dan hasil verifikasi kualitas Phase 7.
