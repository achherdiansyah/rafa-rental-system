import React, { useEffect, useState } from 'react'
import { Save, Upload, Trash2, Image as ImageIcon, RefreshCw } from 'lucide-react'
import { cmsService } from '../services/cmsService'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/form/Input'
import { Textarea } from '@/components/form/Textarea'
import { Card } from '@/components/ui/Card'
import { Skeleton } from '@/components/ui/Skeleton'
import { Alert } from '@/components/feedback/Alert'
import { useToast } from '@/hooks/useToast'

const DEFAULT_TEXT: Array<{ key: string; label: string; group: string; multiline?: boolean; hint?: string }> = [
  { key: 'brand_name', label: 'Nama Brand', group: 'brand' },
  { key: 'navbar', label: 'Navbar (menu JSON)', group: 'navbar', multiline: true, hint: 'Array link menu, mis. [{"label":"Beranda","href":"/"}]' },
  { key: 'hero_title', label: 'Judul Hero', group: 'hero' },
  { key: 'hero_subtitle', label: 'Sub Judul Hero', group: 'hero' },
  { key: 'hero_cta_text', label: 'CTA Teks', group: 'hero' },
  { key: 'hero_cta_link', label: 'CTA Link', group: 'hero' },
  { key: 'about', label: 'Tentang Perusahaan', group: 'content', multiline: true },
  { key: 'services', label: 'Layanan / Keunggulan', group: 'content', multiline: true },
  { key: 'cta_section', label: 'Section CTA', group: 'content', multiline: true },
  { key: 'footer', label: 'Footer', group: 'content', multiline: true },
]

const MEDIA = [
  { key: 'brand_logo', label: 'Logo Brand' },
  { key: 'brand_favicon', label: 'Favicon' },
  { key: 'hero_image', label: 'Gambar Hero' },
]

export const AdminCmsPage: React.FC = () => {
  const { success: toastSuccess, error: toastError } = useToast()

  const [loading, setLoading] = useState(true)
  const [apiError, setApiError] = useState<string | null>(null)
  const [values, setValues] = useState<Record<string, string>>({})
  const [media, setMedia] = useState<Record<string, string>>({})
  const [saving, setSaving] = useState(false)

  const load = async () => {
    setLoading(true)
    setApiError(null)
    try {
      const list = await cmsService.listAdmin()
      const text: Record<string, string> = {}
      const mediaMap: Record<string, string> = {}
      for (const rec of list) {
        if (rec.is_media) {
          if (rec.url) mediaMap[rec.key] = rec.url
        } else {
          text[rec.key] = rec.value ?? ''
        }
      }
      setValues(text)
      setMedia(mediaMap)
    } catch (err: any) {
      setApiError(err?.message || 'Gagal memuat konfigurasi CMS.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  const saveText = async () => {
    setSaving(true)
    try {
      let saved = 0
      for (const field of DEFAULT_TEXT) {
        const value = (values[field.key] ?? '').trim()
        // Backend `value` is required — skip empty fields so fallback stays
        // in place instead of sending '' which 422s "value field is required".
        if (value === '') continue
        await cmsService.update(field.key, value)
        saved++
      }
      toastSuccess(saved > 0 ? 'Konten landing page berhasil disimpan.' : 'Tidak ada perubahan konten untuk disimpan.')
    } catch (err: any) {
      toastError(err?.message || 'Gagal menyimpan konten.')
    } finally {
      setSaving(false)
    }
  }

  const upload = async (key: string, file: File) => {
    try {
      if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        toastError('Format gambar tidak didukung. Gunakan JPG, PNG, atau WebP.')
        return
      }
      if (file.size > 5 * 1024 * 1024) {
        toastError('Ukuran gambar maksimal 5 MB.')
        return
      }
      const preview = URL.createObjectURL(file)
      setMedia((prev) => ({ ...prev, [key]: preview }))
      const url = await cmsService.uploadMedia(key, file)
      URL.revokeObjectURL(preview)
      setMedia((prev) => ({ ...prev, [key]: url }))
      toastSuccess('Media berhasil diunggah.')
    } catch (err: any) {
      toastError(err?.message || 'Gagal mengunggah media.')
    }
  }

  const clearMedia = async (key: string) => {
    try {
      await cmsService.disable(key)
      setMedia((prev) => ({ ...prev, [key]: '' }))
      toastSuccess('Media dinonaktifkan dari landing page.')
    } catch (err: any) {
      toastError(err?.message || 'Gagal menonaktifkan media.')
    }
  }

  if (loading) {
    return <div className="space-y-4 p-6 max-w-4xl"><Skeleton className="h-10 w-64" /><Skeleton className="h-40 w-full rounded-2xl" /></div>
  }

  const group: Record<string, typeof DEFAULT_TEXT> = {
    brand: DEFAULT_TEXT.filter((f) => f.group === 'brand' || f.group === 'navbar'),
    hero: DEFAULT_TEXT.filter((f) => f.group === 'hero'),
    content: DEFAULT_TEXT.filter((f) => f.group === 'content'),
  }

  return (
    <div className="space-y-6 max-w-4xl">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight">CMS Landing Page</h2>
          <p className="text-sm text-slate-500 mt-1">Kelola brand, navbar, hero, dan konten halaman publik. Perubahan langsung tampil di landing page setelah disimpan.</p>
        </div>
        <Button isLoading={saving} onClick={saveText} leftIcon={<Save size={16} />}>
          Simpan Konten
        </Button>
      </div>

      {apiError && <Alert variant="danger" title="Gagal Memuat Data">{apiError}</Alert>}

      <Card className="p-5 space-y-3">
        <h3 className="font-semibold text-slate-900">Brand</h3>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          {MEDIA.map((slot) => (
            <div key={slot.key} className="rounded-xl border border-slate-200 p-3 space-y-2">
              <p className="text-xs font-medium text-slate-600">{slot.label}</p>
              {media[slot.key] ? (
                <img src={media[slot.key]} alt={slot.label} className="h-16 object-contain border border-slate-100 rounded-lg bg-slate-50" />
              ) : (
                <div className="h-16 flex items-center justify-center text-slate-300 border border-dashed border-slate-200 rounded-lg">
                  <ImageIcon size={20} />
                </div>
              )}
              <div className="flex gap-2">
                <label className="flex-1">
                  <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    className="hidden"
                    onChange={(e) => {
                      const f = e.target.files?.[0]
                      if (f) upload(slot.key, f)
                      e.target.value = ''
                    }}
                  />
                  <span className="flex items-center justify-center gap-1 text-xs font-medium text-primary-600 border border-primary-200 rounded-lg px-2 py-1.5 cursor-pointer">
                    <Upload size={13} /> Ganti
                  </span>
                </label>
                <Button variant="outline" size="sm" className="text-rose-600" onClick={() => clearMedia(slot.key)} aria-label={`Hapus ${slot.label}`}>
                  <Trash2 size={13} />
                </Button>
              </div>
            </div>
          ))}
        </div>
      </Card>

      {Object.entries(group).map(([g, fields]) => (
        <Card key={g} className="p-5 space-y-4">
          <h3 className="font-semibold capitalize text-slate-900">{g === 'content' ? 'Section Content' : g}</h3>
          {fields.map((field) =>
            field.multiline ? (
              <Textarea
                key={field.key}
                label={field.label}
                hint={field.hint}
                rows={3}
                value={values[field.key] ?? ''}
                onChange={(e) => setValues((prev) => ({ ...prev, [field.key]: e.target.value }))}
              />
            ) : (
              <Input
                key={field.key}
                label={field.label}
                hint={field.hint}
                value={values[field.key] ?? ''}
                onChange={(e) => setValues((prev) => ({ ...prev, [field.key]: e.target.value }))}
              />
            )
          )}
        </Card>
      ))}

      <div className="flex items-center gap-2 text-xs text-slate-400 pb-4">
        <RefreshCw size={13} /> Halaman publik membaca endpoint <code>/api/v1/cms/public</code> — tanpa refresh manual setelah Simpan.
      </div>
    </div>
  )
}
export default AdminCmsPage