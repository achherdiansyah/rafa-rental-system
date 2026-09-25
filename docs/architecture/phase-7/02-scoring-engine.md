# Spesifikasi Mesin Penilaian Rekomendasi (Phase 7B) - RAFA Rental System

Dokumentasi rancangan matematis, normalisasi bobot (*weighted scoring*), mekanisme *tie-breaking*, dan algoritma transparansi keputusan (*reasoning traceability*) pada RAFA Rental System.

---

## 1. Prinsip Dasar Penilaian (Scoring Philosophy)

Mesin rekomendasi RAFA Rental System berfungsi murni sebagai **Decision Support System (DSS)** berbasis aturan (*Rule-Based Multi-Criteria Decision Analysis*):
- **Bukan Black-Box:** Setiap poin skor dapat ditelusuri (*fully explainable*) ke parameter kriteria input pengguna.
- **Deterministic:** Input kriteria yang sama dengan data armada yang sama selalu menghasilkan skor, peringkat, dan teks penjelasan yang identik.
- **Strictly Native PHP:** Dijalankan langsung oleh Laravel/PHP tanpa dependensi library Python, FastAPI, atau external AI APIs.

---

## 2. Formula Penilaian Berbobot (Weighted Formula)

Skor akhir dihitung dari penjumlahan terbobot (*weighted sum*) terhadap seluruh kriteria aktif:

$$\text{Final Score} = \sum_{i=1}^{n} \left( \text{Raw Score}_i \times \text{Normalized Weight}_i \right)$$

Di mana normalisasi bobot memastikan total bobot selalu setara dengan $1.0$ ($100\%$):

$$\text{Normalized Weight}_i = \frac{\text{Config Weight}_i}{\sum_{j \in \text{Active}} \text{Config Weight}_j}$$

---

## 3. Matriks Evaluasi Sub-Kriteria

| Kode Kriteria | Bobot Default | Aturan Evaluasi Skor Mentah (0 - 100) |
|---|---|---|
| **`CAPACITY_MATCH`** | **40% (0.40)** | • **100.00:** Kapasitas armada $\ge$ kebutuhan beban ($1.0 \times \le \text{ratio} \le 1.25 \times$).<br>• **92.00:** Sedikit overcapacity ($1.25 \times < \text{ratio} \le 1.75 \times$).<br>• **80.00:** Overcapacity moderat ($1.75 \times < \text{ratio} \le 2.50 \times$).<br>• **65.00:** Overcapacity signifikan ($> 2.50 \times$).<br>• **$\max(20, \text{ratio} \times 75)$:** Penalti jika kapasitas armada berada di bawah target beban.<br>• **85.00:** Nilai default jika kapasitas tidak dispesifikasikan ($0$ / null). |
| **`TERRAIN_SUITABILITY`** | **30% (0.30)** | • **Medan Lumpur / Rawa / Basah:** Excavator Crawler ($98.0$), Bulldozer ($82.0$), Lainnya ($50.0$).<br>• **Medan Keras / Bebatuan / Tambang:** Bulldozer ($96.0$), Excavator ($96.0$), Lainnya ($70.0$).<br>• **Medan Aspal / Datar / Gravel:** Motor Grader / Roller / Loader ($98.0$), Excavator ($85.0$).<br>• **80.00:** Nilai standar untuk medan umum. |
| **`PROJECT_SUITABILITY`** | **20% (0.20)** | • **Galian / Drainase / Pondasi:** Tipe Excavator ($100.0$), Lainnya ($60.0$).<br>• **Jalan / Perataan / Land Clearing:** Bulldozer / Grader ($100.0$), Lainnya ($70.0$).<br>• **80.00:** Relevansi standar untuk pekerjaan konstruksi umum. |
| **`PRICE_SUITABILITY`** | **10% (0.10)** | • **95.00:** Model armada memiliki master tarif sewa aktif di database.<br>• **55.00:** Model belum memiliki tarif sewa aktif yang ditetapkan. |

---

## 4. Mekanisme Penentuan Peringkat (Deterministic Tie-Breaking)

Untuk menjamin urutan hasil rekomendasi yang adil dan konsisten bagi pelanggan, pengurutan armada menerapkan 3 lapis determinisme:

1. **Tingkat 1 — Skor Akhir (`Final Score` DESC):** Armada dengan kecocokan teknis tertinggi berada di posisi teratas.
2. **Tingkat 2 — Tarif Sewa Terendah (`Lowest Hourly Rate` ASC):** Jika dua model memiliki skor kecocokan yang sama persis, model dengan tarif per jam paling ekonomis diprioritaskan.
3. **Tingkat 3 — Model ID (`Equipment Model ID` ASC):** Fallback deterministik mutlak berbasis ID unik di basis data jika tarif dan skor identik.

---

## 5. Contoh Kalkulasi Nyata

**Input Pelanggan:**
- Jenis Proyek: *"Galian Basah dan Drainase"*
- Kondisi Medan: *"Lumpur / Rawa"*
- Kebutuhan Beban: `20.00 Ton`
- Kedalaman Galian: `4.50 Meter`

**Evaluasi Model: Komatsu PC200-8 (Excavator, Kapasitas 20 Ton, Tarif Rp 250.000/jam):**

| Sub-Kriteria | Skor Mentah | Bobot Normal | Kontribusi Skor |
|---|---|---|---|
| `CAPACITY_MATCH` | $100.00$ | $0.40$ | $40.00$ |
| `TERRAIN_SUITABILITY` | $98.00$ | $0.30$ | $29.40$ |
| `PROJECT_SUITABILITY` | $100.00$ | $0.20$ | $20.00$ |
| `PRICE_SUITABILITY` | $95.00$ | $0.10$ | $9.50$ |
| **Total Skor Akhir** | - | $\mathbf{1.00}$ | $\mathbf{98.90\%}$ |

**Teks Penjelasan (Generated Reasoning):**
> *"Model Komatsu PC200-8 (Kapasitas 20.00 Ton) memperoleh total skor kesesuaian 98.9%. Kapasitas muat unit sangat efisien dan memadai untuk beban target 20.00 Ton. Konfigurasi traksi sesuai untuk karakteristik medan 'Lumpur / Rawa'. Fungsi operasional tipe Hydraulic Excavator relevan dengan jenis pekerjaan 'Galian Basah dan Drainase'. Mendukung kedalaman operasional hingga 4.50 meter."*
