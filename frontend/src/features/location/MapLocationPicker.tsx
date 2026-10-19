import React, { useEffect, useRef, useState } from 'react'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { LocateFixed, MapPin, Search } from 'lucide-react'
import { Input } from '@/components/form/Input'
import { Button } from '@/components/ui/Button'

interface Props {
  latitude: number | null
  longitude: number | null
  onChange: (lat: number, lng: number) => void
  error?: string
  disabled?: boolean
}

const DEFAULT_CENTER: [number, number] = [-2.5489, 118.0149]

export const MapLocationPicker: React.FC<Props> = ({ latitude, longitude, onChange, error, disabled }) => {
  const containerRef = useRef<HTMLDivElement | null>(null)
  const mapRef = useRef<L.Map | null>(null)
  const markerRef = useRef<L.Marker | null>(null)
  const onChangeRef = useRef(onChange)
  onChangeRef.current = onChange

  const [query, setQuery] = useState('')
  const [searching, setSearching] = useState(false)
  const [searchError, setSearchError] = useState<string | null>(null)
  const [geoError, setGeoError] = useState<string | null>(null)

  const setPoint = (lat: number, lng: number, pan = false) => {
    markerRef.current?.setLatLng([lat, lng])
    if (pan) mapRef.current?.setView([lat, lng], 13)
    onChangeRef.current(lat, lng)
  }

  useEffect(() => {
    if (!containerRef.current || mapRef.current) return
    const center: [number, number] = latitude !== null && longitude !== null ? [latitude, longitude] : DEFAULT_CENTER
    const map = L.map(containerRef.current, { scrollWheelZoom: false }).setView(center, latitude !== null ? 13 : 5)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19,
    }).addTo(map)
    mapRef.current = map

    if (latitude !== null && longitude !== null) {
      markerRef.current = L.marker([latitude, longitude], {
        draggable: true,
        icon: L.divIcon({ className: 'rafa-map-marker', html: '<div style="font-size:28px;line-height:1">📍</div>', iconSize: [28, 28], iconAnchor: [14, 26] }),
      }).addTo(map)
      markerRef.current.on('dragend', () => {
        const p = markerRef.current?.getLatLng()
        if (p) setPoint(p.lat, p.lng)
      })
    }

    map.on('click', (event: L.LeafletMouseEvent) => {
      if (disabled) return
      if (!markerRef.current) {
        markerRef.current = L.marker(event.latlng, {
          draggable: true,
          icon: L.divIcon({ className: 'rafa-map-marker', html: '<div style="font-size:28px;line-height:1">📍</div>', iconSize: [28, 28], iconAnchor: [14, 26] }),
        }).addTo(map)
        markerRef.current.on('dragend', () => {
          const p = markerRef.current?.getLatLng()
          if (p) setPoint(p.lat, p.lng)
        })
      }
      setPoint(event.latlng.lat, event.latlng.lng)
    })

    setTimeout(() => map.invalidateSize(), 50)
    return () => {
      map.remove()
      mapRef.current = null
      markerRef.current = null
    }
  }, [disabled])

  useEffect(() => {
    if (latitude === null || longitude === null) return
    if (!mapRef.current) return
    if (!markerRef.current) {
      markerRef.current = L.marker([latitude, longitude], {
        draggable: true,
        icon: L.divIcon({ className: 'rafa-map-marker', html: '<div style="font-size:28px;line-height:1">📍</div>', iconSize: [28, 28], iconAnchor: [14, 26] }),
      }).addTo(mapRef.current)
      markerRef.current.on('dragend', () => {
        const p = markerRef.current?.getLatLng()
        if (p) setPoint(p.lat, p.lng)
      })
    } else {
      markerRef.current.setLatLng([latitude, longitude])
    }
  }, [latitude, longitude])

  const search = async () => {
    if (!query.trim()) return
    setSearching(true)
    setSearchError(null)
    try {
      const res = await fetch(
        `https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(query)}`,
        { headers: { Accept: 'application/json' } }
      )
      if (!res.ok) throw new Error('Pencarian alamat tidak tersedia.')
      const data = await res.json()
      if (!Array.isArray(data) || data.length === 0) {
        setSearchError('Alamat tidak ditemukan. Pilih titik langsung pada peta.')
        return
      }
      setPoint(Number(data[0].lat), Number(data[0].lon), true)
    } catch (err: any) {
      setSearchError(err?.message || 'Gagal mencari alamat.')
    } finally {
      setSearching(false)
    }
  }

  const useMyLocation = () => {
    setGeoError(null)
    if (!navigator.geolocation) {
      setGeoError('GPS tidak tersedia. Pilih titik manual pada peta.')
      return
    }
    navigator.geolocation.getCurrentPosition(
      (pos) => setPoint(pos.coords.latitude, pos.coords.longitude, true),
      () => setGeoError('Izin lokasi ditolak atau GPS gagal. Pilih titik manual pada peta.'),
      { enableHighAccuracy: true, timeout: 10000 }
    )
  }

  return (
    <div className="space-y-2">
      <div className="flex flex-col sm:flex-row gap-2">
        <div className="flex-1">
          <Input
            label="Cari alamat / tempat"
            placeholder="Contoh: Alun-alun Bandung"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault()
                search()
              }
            }}
            disabled={disabled || searching}
          />
        </div>
        <div className="flex items-end gap-2">
          <Button type="button" variant="outline" onClick={search} isLoading={searching} className="gap-1.5">
            <Search size={15} /> Cari
          </Button>
          <Button type="button" variant="outline" onClick={useMyLocation} disabled={disabled} className="gap-1.5">
            <LocateFixed size={15} /> Gunakan Lokasi Saya
          </Button>
        </div>
      </div>

      <div ref={containerRef} className="h-64 w-full rounded-xl border border-slate-200 overflow-hidden z-0" aria-label="Peta lokasi" />
      <p className="text-xs text-slate-500 flex items-center gap-1.5">
        <MapPin size={13} /> Klik peta atau geser marker. Titik wajib dipilih.
      </p>
      {searchError && <p className="text-xs text-rose-600">{searchError}</p>}
      {geoError && <p className="text-xs text-amber-700">{geoError}</p>}
      {latitude !== null && longitude !== null ? (
        <p className="font-mono text-xs text-slate-700">Latitude: {latitude} • Longitude: {longitude}</p>
      ) : (
        <p className="text-xs text-amber-700">Koordinat belum ditentukan. Pilih titik pada peta.</p>
      )}
      {error && <p className="text-xs text-rose-600">{error}</p>}
    </div>
  )
}

export default MapLocationPicker
