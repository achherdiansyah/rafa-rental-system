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
| **7A** | Domain Foundation, Entity Relasi, Input Kriteria, & Scoring Engine | Selesai (Aktif) |
| **7B** | Implementasi Bobot & Aturan Evaluasi Detail Multi-Kriteria | Pending |
| **7C** | Penjelasan Hasil Penilaian (Reasoning & Decision Traceability) | Pending |
| **7D** | Antarmuka Wizard Rekomendasi Pelanggan & Integrasi Katalog | Pending |

---

## 3. Daftar Dokumen

- `01-recommendation-domain.md`: Spesifikasi entitas basis data (`recommendation_requests`, `recommendation_criteria`, `recommendation_results`), relasi model, arsitektur *Rule-Based Scoring Service*, dan REST API.
