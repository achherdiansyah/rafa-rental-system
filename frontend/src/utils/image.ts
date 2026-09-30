/**
 * Compress/reshape an image client-side before upload to keep stored files
 * small (landing thumbnails only need ~browser-res display sizes). Reduces
 * reload latency significantly for camera-origin JPEGs (2–5 MB → ~150–300 KB).
 * Returns a File-like Blob; caller keeps the same MIME/type.
 */
export async function compressImage(file: File, maxDim = 1600, quality = 0.82): Promise<File | Blob> {
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