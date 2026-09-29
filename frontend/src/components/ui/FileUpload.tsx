import React, { useEffect, useRef, useState } from 'react'
import { Upload, X, RefreshCw } from 'lucide-react'
import { Button } from '../ui/Button'

export interface FileUploadProps {
  label?: string
  valueUrl?: string | null
  accept?: string
  uploading?: boolean
  onSelect: (file: File) => void
  onRemove?: () => void
  hint?: string
  disabled?: boolean
}

const ALLOWED = ['image/jpeg', 'image/png', 'image/webp']
const MAX_SIZE = 5 * 1024 * 1024

/**
 * Image upload with local preview + persisted URL. Preview is transient; the
 * persisted URL (backend) is the source of truth after save.
 */
export const FileUpload: React.FC<FileUploadProps> = ({
  label,
  valueUrl,
  accept = 'image/jpeg,image/png,image/webp',
  uploading = false,
  onSelect,
  onRemove,
  hint,
  disabled = false,
}) => {
  const inputRef = useRef<HTMLInputElement>(null)
  const [preview, setPreview] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [fileName, setFileName] = useState<string | null>(null)

  useEffect(() => {
    return () => {
      if (preview) URL.revokeObjectURL(preview)
    }
  }, [preview])

  const onFile = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    e.target.value = ''
    if (!file) return
    if (!ALLOWED.includes(file.type)) {
      setError('Format tidak didukung. Gunakan JPG, PNG, atau WebP.')
      return
    }
    if (file.size > MAX_SIZE) {
      setError('Ukuran file maksimal 5 MB.')
      return
    }
    setError(null)
    setFileName(file.name)
    setPreview(URL.createObjectURL(file))
    onSelect(file)
  }

  const src = preview ?? valueUrl

  return (
    <div className="space-y-1.5">
      {label && <p className="text-sm font-medium text-slate-900">{label}</p>}
      <div className="flex items-start gap-3">
        <div className="w-24 h-24 rounded-xl border border-slate-200 bg-white overflow-hidden flex items-center justify-center shrink-0">
          {src ? (
            <img src={src} alt={label ?? 'preview'} className="w-full h-full object-cover" />
          ) : (
            <Upload size={20} className="text-slate-300" />
          )}
        </div>
        <div className="space-y-1">
          <input ref={inputRef} type="file" accept={accept} className="hidden" onChange={onFile} disabled={disabled} />
          <Button size="sm" variant="outline" disabled={disabled || uploading} onClick={() => inputRef.current?.click()} leftIcon={<RefreshCw size={13} />}>
            {fileName ? 'Ganti' : 'Unggah'}
          </Button>
          {onRemove && src && (
            <Button size="sm" variant="ghost" className="text-rose-600 block" disabled={disabled || uploading} onClick={onRemove} leftIcon={<X size={13} />}>
              Hapus
            </Button>
          )}
          {uploading && <p className="text-xs text-slate-400">Mengunggah…</p>}
          {fileName && !preview && <p className="text-xs text-slate-400 break-all">{fileName}</p>}
        </div>
      </div>
      {error ? <p className="text-xs text-rose-600">{error}</p> : hint ? <p className="text-xs text-slate-400">{hint}</p> : null}
    </div>
  )
}
export default FileUpload