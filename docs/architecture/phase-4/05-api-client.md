# Centralized API Client & Data Layer Foundation - RAFA Rental System

Dokumentasi rancangan client HTTP terpusat, penanganan token, normalisasi error, dan lapisan komunikasi data frontend ke REST API Laravel (Phase 4E).

---

## 1. Konfigurasi Client Terpusat (`src/lib/api.ts`)

Seluruh komunikasi jaringan dari antarmuka React menuju backend `/api/v1` wajib melalui instance `apiClient` Axios tunggal:

```typescript
import { api } from '@/lib/api'

// Contoh penggunaan:
const response = await api.get<BookingDetail>('/bookings/123')
```

### Karakteristik:
- **Base URL Dinamis:** Mengonsumsi `import.meta.env.VITE_API_BASE_URL` (default: `http://127.0.0.1:8000/api/v1`).
- **Timeout Protection:** Request otomatis terputus (*timeout*) setelah 10 detik jika server tidak merespons, mencegah UI menggantung selamanya.
- **Standar Header:** Secara default mengirimkan `Content-Type: application/json` dan `Accept: application/json`.

---

## 2. Request & Response Interceptors

### 2.1 Request Interceptor (Auth Token Attachment)
- Mengecek keberadaan token JWT/Sanctum di `localStorage` (`rafa_token`).
- Jika token ada, otomatis menyematkan header:
  `Authorization: Bearer <token>`
- Komponen atau fitur tidak perlu repot menyertakan header manual pada setiap request.

### 2.2 Response Interceptor (Error Handling & Session Expiry)
- **HTTP 401 Unauthorized:**
  Jika backend menolak request karena sesi token kedaluwarsa atau invalid, interceptor secara otomatis menghapus token lokal dan melakukan pengalihan (*redirect*) ke `/login?expired=1`.
- **Error Normalization:**
  Seluruh galat (*AxiosError*) dinormalisasi menjadi objek standar `ApiError` sebelum dilempar kembali ke pemanggil.

---

## 3. Skema Normalisasi Error (`ApiError`)

Frontend mengonversi format respons error backend Laravel (Phase 2C) menjadi struktur yang mudah ditampilkan oleh komponen UI:

```typescript
export interface ApiError {
  message: string          // Pesan ramah pengguna
  code: string             // Kode error bisnis (misal: 'INVALID_STATE_TRANSITION', 'BOOKING_CONFLICT')
  status: number           // Kode HTTP (400, 401, 403, 404, 409, 422, 500)
  errors: Record<string, string[]> // Rincian validasi field per input
  isNetworkError: boolean  // True jika koneksi internet terputus
}
```

---

## 4. Tipe Data Respons API Standar (`src/types/api.ts`)

| Tipe | Deskripsi |
|---|---|
| `ApiResponse<T>` | Kontrak envelope respons tunggal `{ success, message, data: T, meta?, errors?, code? }` |
| `PaginatedResponse<T>` | Kontrak respons koleksi yang menyertakan objek `meta: PaginationMeta` |
| `PaginationMeta` | Rincian paginasi: `current_page`, `per_page`, `total`, `last_page` |
| `ApiError` | Representasi typed error untuk penanganan di UI/Form |

---

## 5. Hook Konsumsi Data (`src/hooks/useApi.ts`)

Menyediakan fungsi pembungkus reaktif untuk menangani state pemanggilan API tanpa boilerplate `useState` berulang:

```typescript
const { data, error, isLoading, execute } = useApi(api.get)

// Di dalam handler komponen:
await execute('/bookings')
```

Menghasilkan state: `data`, `error`, `isLoading`, dan `isSuccess`.
