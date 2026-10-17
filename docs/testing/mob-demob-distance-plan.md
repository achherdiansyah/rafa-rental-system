# MOB/DEMOB Berdasarkan Jarak Rute — Rencana & Status

## Aturan bisnis
- Jarak = jarak **rute jalan** satu arah dari pool asal unit ke lokasi proyek.
- `MOB = jarak × tarif_per_km`, `DEMOB = jarak × tarif_per_km`, `Total = MOB + DEMOB`.
- Tarif per km dikelola Admin di Master Tarif & Harga (`equipment_prices.distance_rate_per_km`).
- Harga sewa per jam **tidak** masuk total booking; hanya untuk Daily Work Invoice dari Timesheet.

## Schema (additive, data lama dipertahankan)
- `equipment_pools`: `id, name, address, city, latitude, longitude, is_active, timestamps, softDeletes`.
- `equipment_models.equipment_pool_id` (nullable): pool asal default per model (booking memilih model sebelum unit ditugaskan).
- `equipment_units.equipment_pool_id` (nullable): pool asal aktual per unit fisik.
- `equipment_prices.distance_rate_per_km` (decimal 12,2, default 0).
- `equipment_price_versions.old_distance_rate_per_km` / `new_distance_rate_per_km` (riwayat perubahan).
- `booking_details` snapshot: `origin_pool_id, origin_name, origin_address, origin_latitude, origin_longitude,
  destination_project_location_id, destination_name, destination_address, destination_latitude, destination_longitude,
  route_distance_km (decimal 10,2), distance_rate_per_km (decimal 12,2)` + snapshot MOB/DEMOB yang sudah ada.

Field `mob_cost` / `demob_cost` lama **tidak dihapus** agar transaksi & snapshot existing tetap kompatibel.

## Perilaku saat ini
- Jika pool + koordinat tujuan valid dan provider routing tersedia → harga memakai jarak × tarif/km.
- Jika routing belum dikonfigurasi dan `distance_rate_per_km > 0` → booking **ditolak** dengan pesan estimasi belum tersedia (tidak mengarang jarak/harga). Estimasi API mengembalikan `distance_available=false` tanpa angka.
- Jika `distance_rate_per_km = 0` (belum diatur) → sistem memakai MOB/DEMOB tetap lama (kompatibilitas data existing).

## Provider routing (belum dipilih — blocker)
Sistem memakai kontrak `App\Services\Routing\RouteDistanceProvider`. Default `NullRouteDistanceProvider` (tidak menghitung apa pun).
Pilihan provider yang dapat dipertimbangkan (perlu persetujuan + API key):
- Google Routes API (berbayar, key + billing).
- Mapbox Directions API (key + kuota).
- OpenRouteService (key, kuota gratis terbatas).
- OSRM self-hosted (tanpa biaya API, butuh server + data peta).

Konfigurasi yang diperlukan (belum diisi):
```
ROUTING_PROVIDER=            # label provider (dokumentasi)
ROUTING_API_KEY=             # API key provider
ROUTING_BASE_URL=            # endpoint provider
ROUTING_PROVIDER_CLASS=      # FQCN class implementasi RouteDistanceProvider
```
Selama `ROUTING_PROVIDER_CLASS` kosong, kalkulasi jarak rute aktual tidak berjalan.
