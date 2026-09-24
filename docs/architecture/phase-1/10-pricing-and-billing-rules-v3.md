# Pricing & Billing Rules V3 - RAFA Rental System

Dokumen spesifikasi formal mekanisme penentuan harga, aturan penagihan, kalkulasi nilai invoice, dan aliran pembayaran untuk sistem RAFA Rental (Phase 1G).

---

## 1. Komponen Tarif Sewa (Rental Pricing)

### 1.1 Penentuan Skema Sewa per Item Alat (Equipment Line)
Opsi skema harga diberlakukan independen **per baris item alat (equipment line)**, bukan diseragamkan satu invoice mutlak. Pengguna bisa memesan Excavator dengan paket All-in dan Dozer dengan paket Non All-in dalam 1 Booking.
- **Skema All-in:** Harga sewa sudah mengakomodir penyediaan unit fisik, honor operator alat berat, pengisian bahan bakar (BBM) operasional, serta biaya perawatan preventif harian.
- **Skema Non All-in (Bare Rental):** Tarif sewa murni hanya untuk hak guna pakai aset/unit mesin (Unit Only). Penyediaan BBM, akomodasi, dan upah operator menjadi kewajiban mandiri penyewa di lokasi proyek.

### 1.2 Harga Flat Tanpa Komponen Tambahan Semu
- **No Tax (Tanpa Pajak Terpisah):** Komponen PPN (Pajak Pertambahan Nilai) dan PPh diasumsikan belum diberlakukan atau sudah ter-include penuh (*tax-inclusive*) dari nilai Master Harga. Sistem tidak menambahkan *tax multiplier* dalam Grand Total.
- **No Discount (Tanpa Potongan Diskon):** Sistem tidak memberlakukan kupon promo, diskon loyalitas, maupun penyesuaian harga *on-the-fly*.
- **No Attachment Charge:** Harga sewa mengikat fungsi utama spesifikasi alat berat utuh. Tidak ada *surcharge* terpisah untuk aksesoris standar bawaan tipe alat (misal: Bucket, Blade standar).

---

## 2. Kalkulasi Jam Kerja (Timesheet Billing)

### 2.1 Basis Jam & Hour Meter Aktual
1. Harga dasar ditetapkan dengan parameter basis per jam (Hourly Rate).
2. Kalkulasi nilai serapan sewa murni merujuk pada jam Hour Meter (HM) aktual di lapangan (`actual hours`), bukan estimasi kalender kaku.
3. Jam Aktual = `HM Akhir - HM Awal - Breakdown Hours`.

### 2.2 Pemakaian 1 - 8 Jam
Pemakaian armada dengan durasi operasional **1 (satu) hingga 8 (delapan) jam per hari kerja** ditetapkan terhitung sebagai biaya minimum harian penuh (Tagihan 8 Jam).
- Misal: Mesin hanya menyala 4 jam aktual karena hujan, tagihan minimum harian tetap jatuh pada angka kuota 8 jam pemakaian.

### 2.3 Tanpa Pembulatan Angka Jam (No Rounding)
Pencatatan sisa jam Hour Meter di sistem dikalkulasi presisi tanpa pembulatan matematis fiktif (*no rounding*).
- Kalkulasi menggunakan data Decimal: Jika aktual lapangan terbaca 11.45 jam mesin, penagihan tarif HM dikalikan tepat sebesar angka faktual `11.45`, bukan dibulatkan paksa ke bawah menjadi `11.0` maupun ke atas `12.0`.

### 2.4 PENDING BUSINESS DECISION: Actual Hours > 8
Aturan turunan komersial untuk penagihan jika unit beroperasi di atas 8 jam per hari (Actual Hours > 8) belum diputuskan final.
- *(Keputusan Terbuka: Apakah dihitung prorata tarif per jam flat, flat overtime premium surcharge, atau sistem dua-shift? Formula ditahan hingga PRD bisnis diperbarui oleh manajemen).*

---

## 3. Komponen Biaya Mobilisasi & Demobilisasi

### 3.1 Berbasis Unit Fisik (Physical Unit Based)
Biaya logistik alat berat murni bergantung mutlak pada jumlah aset fisik yang berpindah (*Physical Unit Assignment*), bukan agregat jenis alat (*model type*).
- **Aturan MOB/DEMOB:** Apabila dalam satu booking detail pengguna memesan `Model X, Qty: 3`, sistem akan menghasilkan 3 slot penugasan unit fisik. Beban nominal MOB & DEMOB ditagihkan penuh masing-masing untuk ke-3 (tiga) unit logistik tersebut.

---

## 4. Mekanisme Proteksi Data Finansial (Financial Architecture)

### 4.1 Price Versioning & Snapshot
- Perubahan harga yang dilakukan oleh Owner/Admin pada Master Data (`equipment_prices`) menyebabkan terbentuknya riwayat versi tarif baru (`equipment_price_versions`). Master tarif lama tertutup.
- Harga yang masuk ke dalam Keranjang, Booking Detail, dan Invoice Detail mengamankan tarif tersebut lewat skema *snapshot copy* nilai moneter absolut (`rental_rate_snapshot`).
- Histori finansial tagihan masa lampau kebal/permanen terhadap seluruh kebijakan inflasi atau revisi harga master di kemudian hari.

---

## 5. Aliran Keuangan & Pembayaran (Payment Flows)

### 5.1 Kalkulasi Nilai Invoice
`Total Tagihan Invoice = (Total Snapshot Tarif Sewa Alat) + (Total Tarif MOB/DEMOB Seluruh Unit Fisik)`

### 5.2 Pembayaran Parsial (Partial Payment)
Sistem menerima pelunasan berjenjang (Uang Muka / Termin DP).
- Saat user mengunggah bukti bayar yang nilai riilnya di bawah total tagihan invoice, Admin dapat meninjau dan melakukan validasi nominal masuk.
- Pasca persetujuan, total terverifikasi ditambahkan pada kolom `paid_amount` Invoice, dan status invoice dikunci di titik `PARTIALLY_PAID` hingga `paid_amount == grand_total`.

### 5.3 Kelebihan Bayar (Overpayment)
- Jika nominal pelunasan pengguna menembus plafon nilai tagihan total invoice, mesin pembukuan mendeteksi nilai `OVERPAID` (Kelebihan Bayar).
- Nilai overpayment dicatatkan ke dalam ledger `overpayment_amount`.
- Dana tersebut ditahan status pembukuannya dalam antrean khusus dan memerlukan tindakan otorisasi/resolusi review manual oleh pihak manajemen (Admin/Owner) sebelum dicairkan kembali.

### 5.4 Eksekusi Refund Uang Kembali
Alur pengembalian uang (karena batal sewa, denda pembatalan di bawah DP yang disetor, atau klaim atas nilai overpayment) dikendalikan secara mutlak lewat proses manual di luar sistem pembayaran otomatis (Payment Gateway).
1. Owner menyetujui formulir nominal kompensasi `Refund`.
2. Staf Keuangan mengeksekusi transfer bank mutasi riil ke rekening nasabah pengguna via internet banking.
3. Staf Keuangan mendokumentasikan serta mengunggah tangkapan layar resi/bukti keberhasilan transfer balik uang tunai manual tersebut ke dalam sistem. Status pengembalian beralih permanen menjadi `COMPLETED`.
