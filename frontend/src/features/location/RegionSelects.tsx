import React, { useEffect, useState } from 'react'
import { Select } from '@/components/form/Select'
import { Button } from '@/components/ui/Button'
import { regionService } from './regionService'
import type { RegionOption, RegionValue } from './regionService'

interface Props {
  value: RegionValue
  onChange: (value: RegionValue) => void
  errors?: Partial<Record<keyof RegionValue, string>>
  disabled?: boolean
}

export const RegionSelects: React.FC<Props> = ({ value, onChange, errors = {}, disabled = false }) => {
  const [provinces, setProvinces] = useState<RegionOption[]>([])
  const [regencies, setRegencies] = useState<RegionOption[]>([])
  const [districts, setDistricts] = useState<RegionOption[]>([])
  const [villages, setVillages] = useState<RegionOption[]>([])
  const [loading, setLoading] = useState<string | null>('province')
  const [error, setError] = useState<string | null>(null)

  const run = async (key: string, loader: () => Promise<RegionOption[]>) => {
    setLoading(key)
    setError(null)
    try {
      return await loader()
    } catch (err: any) {
      setError(err?.message || 'Gagal memuat data wilayah.')
      return []
    } finally {
      setLoading(null)
    }
  }

  const loadProvinces = async () => setProvinces(await run('province', regionService.provinces))

  useEffect(() => {
    loadProvinces()
  }, [])

  useEffect(() => {
    if (!value.province_code) {
      setRegencies([])
      return
    }
    run('regency', () => regionService.regencies(value.province_code)).then(setRegencies)
  }, [value.province_code])

  useEffect(() => {
    if (!value.regency_code) {
      setDistricts([])
      return
    }
    run('district', () => regionService.districts(value.regency_code)).then(setDistricts)
  }, [value.regency_code])

  useEffect(() => {
    if (!value.district_code) {
      setVillages([])
      return
    }
    run('village', () => regionService.villages(value.district_code)).then(setVillages)
  }, [value.district_code])

  const selectProvince = (code: string) => {
    const item = provinces.find((p) => p.id === code)
    onChange({
      ...value,
      province_code: code,
      province_name: item?.name ?? '',
      regency_code: '', regency_name: '',
      district_code: '', district_name: '',
      village_code: '', village_name: '',
    })
  }

  const selectRegency = (code: string) => {
    const item = regencies.find((p) => p.id === code)
    onChange({
      ...value,
      regency_code: code,
      regency_name: item?.name ?? '',
      district_code: '', district_name: '',
      village_code: '', village_name: '',
    })
  }

  const selectDistrict = (code: string) => {
    const item = districts.find((p) => p.id === code)
    onChange({
      ...value,
      district_code: code,
      district_name: item?.name ?? '',
      village_code: '', village_name: '',
    })
  }

  const selectVillage = (code: string) => {
    const item = villages.find((p) => p.id === code)
    onChange({ ...value, village_code: code, village_name: item?.name ?? '' })
  }

  const disable = disabled || loading !== null

  return (
    <div className="space-y-3">
      {error && (
        <div className="flex items-center justify-between gap-2 rounded-lg border border-rose-200 bg-rose-50 p-2.5 text-xs text-rose-700">
          <span>{error}</span>
          <Button type="button" size="sm" variant="outline" onClick={loadProvinces}>Coba Lagi</Button>
        </div>
      )}

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Select label="Provinsi *" value={value.province_code} onChange={(e) => selectProvince(e.target.value)} error={errors.province_code} disabled={disable}>
          <option value="">{loading === 'province' ? 'Memuat provinsi...' : '-- Pilih Provinsi --'}</option>
          {provinces.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
        </Select>

        <Select label="Kabupaten/Kota *" value={value.regency_code} onChange={(e) => selectRegency(e.target.value)} error={errors.regency_code} disabled={disable || !value.province_code}>
          <option value="">{loading === 'regency' ? 'Memuat kabupaten/kota...' : '-- Pilih Kabupaten/Kota --'}</option>
          {regencies.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
        </Select>

        <Select label="Kecamatan *" value={value.district_code} onChange={(e) => selectDistrict(e.target.value)} error={errors.district_code} disabled={disable || !value.regency_code}>
          <option value="">{loading === 'district' ? 'Memuat kecamatan...' : '-- Pilih Kecamatan --'}</option>
          {districts.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
        </Select>

        <Select label="Desa/Kelurahan *" value={value.village_code} onChange={(e) => selectVillage(e.target.value)} error={errors.village_code} disabled={disable || !value.district_code}>
          <option value="">{loading === 'village' ? 'Memuat desa/kelurahan...' : '-- Pilih Desa/Kelurahan --'}</option>
          {villages.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
        </Select>
      </div>
    </div>
  )
}

export default RegionSelects
