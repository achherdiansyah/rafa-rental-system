/**
 * Compress/reshape an image client-side before upload to keep stored files
 * small (landing thumbnails only need ~browser-res display sizes). Reduces
 * reload latency significantly for camera-origin JPEGs (2–5 MB → ~150–300 KB).
 * Returns a File-like Blob; caller keeps the same MIME/type.
 */
export async function compressImage(file: File, maxDim = 1200, quality = 0.75): Promise<File | Blob> {
  if (!file.type.startsWith('image/')) return file

  try {
    const bitmap = await createImageBitmap(file)
    const { width, height } = bitmap
    const scale = Math.min(1, maxDim / Math.max(width, height))
    if (scale >= 1 && file.size < 512 * 1024) {
      bitmap.close()
      return file // already small enough
    }

    const canvas = document.createElement('canvas')
    canvas.width = Math.max(1, Math.round(width * scale))
    canvas.height = Math.max(1, Math.round(height * scale))
    const ctx = canvas.getContext('2d')
    if (!ctx) {
      bitmap.close()
      return file
    }
    ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height)
    bitmap.close()

    const ext = file.type === 'image/webp' ? 'image/webp' : file.type === 'image/png' ? 'image/png' : 'image/jpeg'
    const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, ext, quality))
    if (!blob) return file
    return new File([blob], file.name, { type: blob.type || file.type })
  } catch {
    return file // createImageBitmap unavailable/unsupported → upload original
  }
}

const THUMB_PREFIX = 'imgth_'

/** Tiny thumb cache keyed by raw url (kept small per entry). */
export function getThumb(url: string): string | null {
  if (typeof localStorage === 'undefined') return null
  try {
    return localStorage.getItem(THUMB_PREFIX + encodeURIComponent(url))
  } catch {
    return null
  }
}

/**
 * After an image has fully loaded, downscale it to a small display thumb and
 * store it as a compressed dataURL. Subsequent reloads render instantly from
 * this cache instead of re-fetching the (potentially large) original.
 */
export async function cacheThumb(url: string): Promise<void> {
  if (typeof localStorage === 'undefined' || url.startsWith('data:')) return
  try {
    if (localStorage.getItem(THUMB_PREFIX + encodeURIComponent(url))) return
    const img = new Image()
    img.crossOrigin = 'anonymous'
    img.src = url
    await img.decode().catch(() => undefined)
    if (!img.naturalWidth) return
    const scale = Math.min(1, 480 / Math.max(img.naturalWidth, img.naturalHeight))
    const canvas = document.createElement('canvas')
    canvas.width = Math.max(1, Math.round(img.naturalWidth * scale))
    canvas.height = Math.max(1, Math.round(img.naturalHeight * scale))
    const ctx = canvas.getContext('2d')
    if (!ctx) return
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height)
    const dataUrl = canvas.toDataURL('image/webp', 0.72)
    if (dataUrl.length > 320 * 1024) return // too large to bother caching
    localStorage.setItem(THUMB_PREFIX + encodeURIComponent(url), dataUrl)
  } catch {
    /* ignore quota / taint errors */
  }
}