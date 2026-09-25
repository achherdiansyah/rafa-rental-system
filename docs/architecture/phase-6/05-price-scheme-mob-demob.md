# Skema Harga Sewa & Logistik MOB/DEMOB - RAFA Rental System

Dokumentasi spesifikasi kalkulasi skema sewa (*All-in vs Non All-in*), penghitungan logistik mobilisasi dan demobilisasi (*MOB/DEMOB*), dan fondasi kalkulasi harga (*Pricing Calculator Engine*) pada RAFA Rental System (Phase 6E).

---

## 1. Skema Harga Independen per Line Item (Per-Line Pricing Scheme)

Skema harga ditentukan secara **independen per baris pesanan alat (equipment line)**, bukan diseragamkan satu invoice global:

| Skema Sewa | Cakupan Biaya | Kewajiban Penyewa di Lapangan |
|---|---|---|
| **`ALL_IN`** | Unit Alat + Honor Operator + Bahan Bakar Minyak (BBM) + Perawatan Preventif Harian | Penyewa hanya menyediakan lokasi kerja dan izin proyek. |
| **`NON_ALL_IN` (Bare Rental)** | Hak Guna Aset Unit Mesin Saja | Bahan bakar (solar), akomodasi, dan upah operator ditanggung mandiri oleh penyewa. |

---

## 2. Penghitungan Logistik MOB & DEMOB per Physical Unit

Biaya mobilisasi (pengiriman unit tronton ke lokasi proyek) dan demobilisasi (penarikan unit tronton kembali ke pool) dihitung **per unit fisik yang dipesan**:

$$\text{Total MOB} = \text{Tarif MOB per Unit} \times \text{Quantity Unit Fisik}$$
$$\text{Total DEMOB} = \text{Tarif DEMOB per Unit} \times \text{Quantity Unit Fisik}$$

- Tarif MOB dan DEMOB bersifat independen dan tidak harus bernilai sama (misal DEMOB gratis jika sewa jangka panjang, atau rute pulang memiliki selisih jarak).

---

## 3. Rumus Kalkulasi Tagihan Sewa Dasar (Pricing Engine)

Sesuai aturan PRD V3 dan Phase 1:
1. **Durasi Kalender:** Dihitung inklusif tanggal mulai hingga tanggal selesai sewa ($\text{Durasi} = (\text{End Date} - \text{Start Date}) + 1\text{ hari}$, min. 1 hari).
2. **Biaya Minimum Harian (Standard Daily 8 Hours):** 
   $$\text{Tarif Harian per Unit} = \text{Base Hourly Rate} \times 8\text{ Jam}$$
3. **Subtotal Sewa per Line:**
   $$\text{Rental Subtotal} = \text{Tarif Harian} \times \text{Durasi Hari} \times \text{Quantity}$$
4. **Total per Line:**
   $$\text{Line Total} = \text{Rental Subtotal} + \text{MOB Subtotal} + \text{DEMOB Subtotal}$$
5. **Grand Total Estimasi Booking:**
   $$\text{Grand Total} = \sum (\text{Line Total}) + \text{Pajak (0.00)} - \text{Diskon (0.00)}$$

*Catatan: Sesuai kesepakatan Phase 1, sistem tidak menambahkan komponen pajak (tax inclusive), diskon semu, maupun attachment charge.*

---

## 4. Endpoint Simulasi & Kalkulasi Harga (`POST /api/v1/pricing/calculate`)

- **Akses:** Publik (Guest & Authenticated).
- **Request Body:**
  ```json
  {
    "items": [
      {
        "equipment_model_id": 1,
        "quantity": 2,
        "start_date": "2026-10-01",
        "end_date": "2026-10-07",
        "is_all_in": true,
        "mob_rate_per_unit": 1000000.00,
        "demob_rate_per_unit": 1000000.00
      },
      {
        "equipment_model_id": 2,
        "quantity": 1,
        "start_date": "2026-10-01",
        "end_date": "2026-10-05",
        "is_all_in": false,
        "mob_rate_per_unit": 500000.00,
        "demob_rate_per_unit": 0.00
      }
    ]
  }
  ```
- **Respons Sukses (200 OK):**
  ```json
  {
    "success": true,
    "message": "Perhitungan estimasi sewa berhasil.",
    "data": {
      "items": [
        {
          "equipment_model_id": 1,
          "model_name": "Komatsu PC200-8",
          "quantity": 2,
          "duration_days": 7,
          "is_all_in": true,
          "hourly_rate": 250000.00,
          "daily_rate": 2000000.00,
          "rental_subtotal": 28000000.00,
          "mob_rate_per_unit": 1000000.00,
          "mob_subtotal": 2000000.00,
          "demob_rate_per_unit": 1000000.00,
          "demob_subtotal": 2000000.00,
          "line_total": 32000000.00
        },
        { ... }
      ],
      "total_rental_amount": 34000000.00,
      "total_mob_amount": 2500000.00,
      "total_demob_amount": 2000000.00,
      "tax_amount": 0.00,
      "discount_amount": 0.00,
      "grand_total": 38500000.00
    }
  }
  ```

---

## 5. Hasil Pengujian Backend (`PricingCalculationTest.php`)

- `can_calculate_booking_price_for_all_in_and_non_all_in_lines`: PASSED (Multi-line dengan skema harga berbeda terkalkulasi presisi).
- `pricing_calculation_fails_if_master_price_not_set`: PASSED (Menolak kalkulasi dengan pesan jelas jika tarif master belum diset).
- `pricing_calculation_uses_most_recent_effective_date`: PASSED (Menerapkan tarif efektif terbaru sesuai tanggal mulai sewa).
- `validation_fails_on_missing_fields_or_wrong_dates`: PASSED (Validasi tanggal akhir sebelum tanggal mulai ditolak HTTP 422).
