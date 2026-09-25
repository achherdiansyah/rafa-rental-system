# Phase 6: Master Data & Fleet Management - RAFA Rental System

Dokumentasi implementasi modul master data armada, inventaris unit fisik, dan skema penentuan harga sewa untuk RAFA Rental System.

## Daftar Dokumen

- `01-equipment-type-model.md`: Implementasi backend REST API dan antarmuka manajemen Admin untuk master data tipe alat berat (`equipment_types`) dan seri model armada (`equipment_models`).
- `02-equipment-unit.md`: Spesifikasi REST API dan antarmuka manajemen Admin untuk inventaris nomor seri unit fisik (`equipment_units`), aturan transisi status keselamatan (*status safety guards*), dan pelacakan Hour Meter.
- `03-equipment-specification-media.md`: Spesifikasi lampiran foto model alat berat (`attachments`), arsitektur storage lokal/private, validasi MIME gambar, dan antarmuka kelola foto Admin.
- `04-pricing-version.md`: Implementasi master tarif sewa (`equipment_prices`), skema All-in/Non All-in, presisi `DECIMAL(15,2)`, imutabilitas audit versi harga (`equipment_price_versions`), dan pembatasan wewenang khusus Owner.
- `05-price-scheme-mob-demob.md`: Spesifikasi skema sewa per-equipment line (All-in vs Non All-in), perhitungan logistik MOB/DEMOB per physical unit, dan mesin kalkulasi harga rental (`PricingCalculatorService`).
- `06-bank-account.md`: Implementasi master rekening bank perusahaan (`bank_accounts`), perlindungan otorisasi Admin/Owner, penyaringan rekening aktif untuk instruksi pembayaran pelanggan, dan antarmuka manajemen.
- `07-admin-master-data-ui.md`: Standar implementasi komponen antarmuka admin, filter paginasi sisi server, optimasi pencarian debounced, dan pengujian UI master data.
