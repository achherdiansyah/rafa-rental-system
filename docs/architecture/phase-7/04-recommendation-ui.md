# Spesifikasi Antarmuka Pengguna Rekomendasi (Phase 7D) - RAFA Rental System

Dokumentasi rancangan antarmuka pengguna (*User Interface & UX*), validasi formulir interaktif, hierarki visual hasil peringkat, dan integrasi katalog pada Modul Rekomendasi Armada (`/app/recommendations`).

---

## 1. Tujuan Antarmuka & Filosofi UX

Antarmuka rekomendasi armada pada Customer Portal (`/app/recommendations`) dirancang sebagai **Asisten Pemilihan Armada Cerdas** bagi pelanggan penyewa:
- **Clean & Professional:** Mengadopsi Design System Phase 4 dengan hierarki visual yang jelas dan tata letak responsif (1 kolom di mobile, 2 kolom split di desktop/laptop).
- **Zero Ambiguity:** Setiap rekomendasi menyajikan alasan teknis (*reasoning*) yang transparan dan dapat dipahami oleh manajer proyek maupun staf pengadaan.
- **Strict Decision Support (No Automatic Booking):** Sistem rekomendasi tidak pernah secara otomatis membuat pesanan sewa (*booking*), keranjang belanja (*cart*), maupun invoice. Pelanggan tetap memegang kendali penuh untuk meninjau spesifikasi dan menyewa secara mandiri via katalog resmi.

---

## 2. Alur Pengguna (User Flow)

```text
1. Navigasi ke Menu "Rekomendasi Alat" (/app/recommendations)
       │
       ▼
2. Pengisian Kriteria Proyek
   ├── Jenis Pekerjaan (e.g. Galian Basah & Drainase)
   ├── Karakteristik Medan (e.g. Lumpur / Rawa)
   ├── Beban Target (e.g. 20 Ton) & Volume Pekerjaan
   └── Kedalaman (m), Jangkauan (m), & Durasi Sewa
       │
       ▼
3. Validasi Client-Side & Submit
       │
       ▼
4. State Loading (Skeleton Placeholder Animatif)
       │
       ▼
5. Tampilan Hasil Rekomendasi Terurut (Ranked Cards)
   ├── Badge Peringkat (#1 Pilihan Utama, #2 Alternatif)
   ├── Badge Skor Kecocokan (% Match)
   ├── Ringkasan Spesifikasi (Kapasitas, Tarif Mulai, Unit Tersedia)
   └── Box Penjelasan Teknis (Mengapa unit ini direkomendasikan)
       │
       ▼
6. Tindakan Eksplisit Pelanggan
   └── Klik tombol "Lihat Detail Armada" ──> Navigasi ke /app/equipment/:id
```

---

## 3. Komponen Form Input

Formulir dirancang menggunakan komponen modular (`Input`, `Select`, `Button`):

| Elemen Form | Tipe Kontrol | Wajib / Opsional | Validasi Client-Side |
|---|---|---|---|
| **Jenis Pekerjaan** | `<Select>` / Dropdown | **Wajib** | Tidak boleh kosong |
| **Kondisi Tanah / Medan** | `<Select>` / Dropdown | **Wajib** | Tidak boleh kosong |
| **Target Beban (Ton)** | `<Input type="number">` | Opsional | Nilai $\ge 0$ |
| **Volume Pekerjaan (m³)** | `<Input type="number">` | Opsional | Nilai $\ge 0$ |
| **Kedalaman Galian (m)** | `<Input type="number">` | Opsional | Nilai $\ge 0$ |
| **Jangkauan Arm (m)** | `<Input type="number">` | Opsional | Nilai $\ge 0$ |
| **Estimasi Durasi (Hari)** | `<Input type="number">` | Opsional | Nilai $\ge 1$ |

---

## 4. Penanganan State UX (UX States Matrix)

| State UX | Komponen Tampilan | Perilaku Antarmuka |
|---|---|---|
| **Initial** | `EmptyState` ("Kalkulator Siap Digunakan") | Menampilkan instruksi ramah di panel kanan sebelum pengguna melakukan submit. |
| **Loading** | `Skeleton` Card Placeholder | Mencegah layout shift (CLS) saat kalkulasi scoring engine berlangsung. |
| **Success** | Ranked Recommendation Cards | Daftar kartu model armada terurut dengan skor, foto thumbnail, dan box eksplanasi. |
| **Empty** | `EmptyState` ("Tidak Ada Armada Terpilih") | Ditampilkan jika tidak ada armada yang memenuhi ambang batas skor minimum (50%). |
| **Validation Error** | Inline Error Text (Merah) | Pesan peringatan di bawah input jika field wajib belum terisi. |
| **API / Network Error**| `Alert variant="danger"` | Banner error dengan pesan kegagalan server yang informatif. |
| **History Tab** | Riwayat Card List | Tab "Riwayat" menyajikan riwayat pengajuan rekomendasi lampau milik pengguna. |

---

## 5. Hasil Pengujian Otomatis (`RecommendationUI.test.tsx`)

- `renders_recommendation_calculator_form_with_initial_state`: **PASSED** (Form dan banner awal dirender).
- `validates_required_fields_before_submission`: **PASSED** (Pencegahan submit saat field wajib kosong).
- `submits_valid_form_and_renders_ranked_recommendation_cards_with_explanation`: **PASSED** (Kartu peringkat armada, skor %, dan teks eksplanasi dirender).
- `handles_api_error_when_recommendation_submission_fails`: **PASSED** (Pesan error API ditampilkan pada banner).
- `switches_to_history_tab_and_lists_past_recommendation_requests`: **PASSED** (Daftar riwayat rekomendasi lampau berhasil disajikan).
