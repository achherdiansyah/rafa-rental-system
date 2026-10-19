import { api } from '@/lib/api'

export interface RegionOption {
  id: string
  name: string
}

const cache = new Map<string, RegionOption[]>()

const load = async (path: string): Promise<RegionOption[]> => {
  if (cache.has(path)) return cache.get(path)!
  const res = await api.get<RegionOption[]>(path)
  const data = res.data ?? []
  cache.set(path, data)
  return data
}

export const regionService = {
  provinces: () => load('/regions/provinces'),
  regencies: (provinceCode: string) => load(`/regions/provinces/${encodeURIComponent(provinceCode)}/regencies`),
  districts: (regencyCode: string) => load(`/regions/regencies/${encodeURIComponent(regencyCode)}/districts`),
  villages: (districtCode: string) => load(`/regions/districts/${encodeURIComponent(districtCode)}/villages`),
}

export interface RegionValue {
  province_code: string
  province_name: string
  regency_code: string
  regency_name: string
  district_code: string
  district_name: string
  village_code: string
  village_name: string
}

export const emptyRegion: RegionValue = {
  province_code: '',
  province_name: '',
  regency_code: '',
  regency_name: '',
  district_code: '',
  district_name: '',
  village_code: '',
  village_name: '',
}
