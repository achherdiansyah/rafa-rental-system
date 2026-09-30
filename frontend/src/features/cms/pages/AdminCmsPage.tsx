import React, { useEffect, useState } from 'react'
import { Save, RefreshCw } from 'lucide-react'
import { cmsService } from '../services/cmsService'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/form/Input'
import { Textarea } from '@/components/form/Textarea'
import { FileUpload } from '@/components/ui/FileUpload'
import { Skeleton } from '@/components/ui/Skeleton'
import { Alert } from '@/components/feedback/Alert'
import { useToast } from '@/hooks/useToast'

interface FieldDef {
  key: string
  label: string
  group: string
  multiline?: boolean
  hint?: string
}

const DEFAULT_TEXT: FieldDef[] = [
  { key: 'brand_name', label: 'Nama Brand', group: 'brand' },
  { key: 'navbar', label: 'Menu Navbar (JSON)', group: 'navbar', multiline: true, hint: 'Array link menu, mis. [{"label":"Beranda","href":"/"}]' },
  { key: 'hero_title', label: 'Judul Hero', group: 'hero' },
  { key: 'hero_subtitle', label: 'Subjudul Hero', group: 'hero' },
  { key: 'hero_cta_text', label: 'CTA Teks', group: 'hero' },
  { key: 'hero_cta_link', label: 'CTA Link', group: 'hero' },
  { key: 'about', label: 'Tentang Perusahaan', group: 'content', multiline: true },
  { key: 'services', label: 'Layanan / Keunggulan', group: 'content', multiline: true },
  { key: 'cta_section', label: 'Section CTA', group: 'content', multiline: true },
  { key: 'contact', label: 'Informasi Kontak (Alamat/Telepon/WhatsApp/Email/Jam Operasional)', group: 'contact', multiline: true, hint: 'Isikan satu baris per bagian, atau gunakan field Alamat/Telepon/WhatsApp/Email/Jam Operasional di bawah.' },
  { key: 'address', label: 'Alamat', group: 'contact' },
  { key: 'phone', label: 'Telepon', group: 'contact' },
  { key: 'whatsapp', label: 'WhatsApp', group: 'contact' },
  { key: 'email', label: 'Email', group: 'contact' },
  { key: 'hours', label: 'Jam Operasional', group: 'contact' },
  { key: 'whatsapp_cta_text', label: 'CTA WhatsApp Teks', group: 'contact' },
  { key: 'whatsapp_cta_link', label: 'CTA WhatsApp Link', group: 'contact' },
  { key: 'footer', label: 'Footer', group: 'footer', multiline: true },
]

const MEDIA = [
  { key: 'brand_logo', label: 'Logo Brand', hint: 'Transparan direkomendasikan' },
  { key: 'brand_favicon', label: 'Favicon', hint: 'Ikon 32x32' },
  { key: 'hero_image', label: 'Gambar Hero', hint: 'Lebar penuh untuk hero' },
]

const SECTION_TITLE: Record<string, string> = {
  brand: 'Brand',
  navbar: 'Navbar',
  hero: 'Hero',
  content: 'Section Content',
  contact: 'Contact',
  footer: 'Footer',
}

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
        if (value === '') continue
        await cmsService.update(field.key, value)
        saved++
      }
      toastSuccess(saved > 0 ? 'Konten landing page berhasil disimpan.' : 'Konten teks tidak berubah; media/gambar tersimpan otomatis saat diunggah.')
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

  const groupFields = (group: string) => DEFAULT_TEXT.filter((f) => f.group === group)

  if (loading) {
    return (
      <div className="space-y-4 max-w-5xl">
        <Skeleton className="h-10 w-64" />
        <div className="space-y-4">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="card-surface p-5 space-y-3">
              <Skeleton className="h-4 w-32" />
              <Skeleton className="h-16 w-full" />
            </div>
          ))}
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6 max-w-5xl">
      {/* Header */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="page-title">CMS Landing Page</h1>
          <p className="page-subtitle">Kelola brand, navbar, hero, dan konten website.</p>
        </div>
        <Button isLoading={saving} onClick={saveText} leftIcon={<Save size={16} />} className="shrink-0">
          Simpan Konten
        </Button>
      </div>

      {apiError && <Alert variant="danger" title="Gagal Memuat Data">{apiError}</Alert>}

      {/* Brand — 3 balanced columns */}
      <section className="card-surface p-5">
        <h2 className="font-semibold text-slate-900 mb-4">Brand</h2>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          {MEDIA.map((slot) => (
            <div key={slot.key} className="rounded-xl border border-slate-200 p-4 space-y-4 bg-white">
              <FileUpload
                label={slot.label}
                hint={slot.hint}
                valueUrl={media[slot.key]}
                uploading={false}
                onSelect={(f) => upload(slot.key, f)}
                onRemove={() => clearMedia(slot.key)}
              />
            </div>
          ))}
        </div>
      </section>

      {/* Text/groups */}
      {Object.keys(SECTION_TITLE)
        .filter((g) => g !== 'brand')
        .map((group) => {
          const fields = groupFields(group)
          if (fields.length === 0) return null
          const twoCol = group !== 'content' && group !== 'footer' && group !== 'navbar'
          return (
            <section key={group} className="card-surface p-5">
              <h2 className="font-semibold text-slate-900 mb-4">{SECTION_TITLE[group]}</h2>
              <div className={twoCol ? 'grid grid-cols-1 sm:grid-cols-2 gap-4' : 'space-y-4'}>
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
              </div>
            </section>
          )
        })}

      {/* Sticky save (desktop) */}
      <div className="sticky bottom-4 z-10 hidden sm:flex justify-end pointer-events-none">
        <div className="card-surface pointer-events-auto flex items-center gap-3 px-4 py-2 bg-white">
          <span className="text-xs text-slate-400">Perubahan tersimpan otomatis saat Simpan Konten.</span>
          <Button size="sm" isLoading={saving} onClick={saveText} leftIcon={<Save size={14} />}>
            Simpan
          </Button>
          <Button size="sm" variant="ghost" onClick={() => load()} leftIcon={<RefreshCw size={14} />} aria-label="Muat ulang data">
            Muat Ulang
          </Button>
        </div>
      </div>
    </div>
  )
}
export default AdminCmsPage