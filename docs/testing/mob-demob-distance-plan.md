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

## Provider routing
Sistem memakai kontrak `App\Services\Routing\RouteDistanceProvider` dengan:
- `MapboxRouteDistanceProvider` — Mapbox Directions API, profil **driving**, jarak meter → km (round 2 desimal).
- `NullRouteDistanceProvider` — fallback default, selalu `null` (tidak memakai garis lurus).

### Konfigurasi Mapbox (backend saja)
```
ROUTING_PROVIDER=mapbox
ROUTING_API_KEY=pk.xxxxx          # JANGAN commit; isi di backend/.env
ROUTING_TIMEOUT=8                 # detik
ROUTING_CACHE_TTL=86400           # cache jarak sukses (detik); 0 = nonaktif
```
`ROUTING_PROVIDER_CLASS` opsional untuk provider kustom (FQCN). Tanpa `ROUTING_PROVIDER=mapbox` + token,
provider Null aktif dan estimasi mengembalikan `distance_available=false`.

Cache memakai key hash koordinat tervalidasi (~1 m) + class provider; hanya jarak sukses yang di-cache.
Timeout/HTTP error/rute kosong/koordinat invalid → `null` → UI menampilkan estimasi belum tersedia; booking
dengan tarif/km > 0 ditolak (tidak ada harga palsu).
