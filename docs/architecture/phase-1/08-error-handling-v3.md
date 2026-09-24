# Error Handling Specification V3 - RAFA Rental System

Standar resmi struktur respons error, kode error domain bisnis, dan mapping HTTP Status Code untuk RAFA Rental System (Phase 1F).

---

## 1. Standar Envelope Respons API

### 1.1 Envelope Sukses
```json
{
  "success": true,
  "message": "Booking submitted successfully",
  "data": {
    "id": 1,
    "booking_code": "RFA-BKG-00001",
    "status": "PENDING_APPROVAL"
  },
  "meta": {
    "page": 1,
    "per_page": 10,
    "total": 50
  }
}
```

### 1.2 Envelope Error Tunggal
```json
{
  "success": false,
  "message": "Booking cannot be submitted in the current state.",
  "error_code": "INVALID_STATE_TRANSITION",
  "errors": null
}
```

### 1.3 Envelope Error Validasi (422 Unprocessable Entity)
```json
{
  "success": false,
  "message": "Validation failed",
  "error_code": "VALIDATION_ERROR",
  "errors": {
    "new_start_date": ["The new start date must be a date after today."],
    "reason": ["The reason field is required."]
  }
}
```

---

## 2. HTTP Status Code Mapping

| HTTP Status | Digunakan Untuk |
|---|---|
| `200 OK` | Request berhasil, respons data. |
| `201 Created` | Resource berhasil dibuat. |
| `204 No Content` | Aksi berhasil tanpa body (jarang dipakai, sertakan envelope agar konsisten). |
| `400 Bad Request` | Request tidak valid; format salah. |
| `401 Unauthorized` | Token tidak ada, invalid, atau kedaluwarsa. |
| `403 Forbidden` | Token valid, role tidak memiliki izin. |
| `404 Not Found` | Resource tidak ditemukan. |
| `409 Conflict` | Konflik logika bisnis atau state machine. |
| `422 Unprocessable Entity` | Validasi field gagal. |
| `429 Too Many Requests` | Rate limit terlampaui. |
| `500 Internal Server Error` | Error server tak terduga. |

---

## 3. Katalog Error Code Domain Bisnis

### 3.1 Booking Domain
| Error Code | HTTP | Deskripsi | Kapan Terjadi |
|---|---|---|---|
| `BOOKING_CONFLICT` | 409 | Kuota unit alat tidak tersedia pada rentang tanggal yang diminta. | Submit/Checkout saat kuota inventaris habis. |
| `BOOKING_EXPIRED` | 409 | Booking telah kadaluwarsa karena deadline pembayaran 24 jam terlewati. | Percobaan approve/bayar pasca expired. |
| `INVALID_STATE_TRANSITION` | 409 | Permintaan aksi tidak dapat dieksekusi pada state booking/rental saat ini. | Approve booking yang sudah expired, cancel booking sudah dispatched. |
| `FORBIDDEN_ACTION` | 403 | Role aktif tidak memiliki izin untuk aksi ini dalam konteks ini. | USER coba memanggil API approve booking, atau cancel pasca payment tanpa ADMIN. |

### 3.2 Payment Domain
| Error Code | HTTP | Deskripsi | Kapan Terjadi |
|---|---|---|---|
| `PAYMENT_REJECTED` | 409 | Bukti pembayaran telah ditolak oleh Admin (bukti palsu/dana tidak masuk). | Admin reject payment. |
| `PAYMENT_DEADLINE_EXCEEDED` | 409 | Upaya upload bukti bayar dilakukan setelah batas waktu 24 jam invoice berakhir. | User upload bukti bayar setelah `due_at` lewat. |
| `OVERPAYMENT_REQUIRES_REVIEW` | 409 | Total pembayaran melebihi nilai tagihan dan memerlukan verifikasi manual Admin/Owner. | Jumlah approved payment > `grand_total` invoice. |

### 3.3 Unit & Assignment Domain
| Error Code | HTTP | Deskripsi | Kapan Terjadi |
|---|---|---|---|
| `UNIT_UNAVAILABLE` | 404 | Unit fisik yang dipilih tidak ditemukan atau tidak dalam status AVAILABLE. | Admin assign unit berstatus maintenance/decommissioned. |
| `UNIT_ASSIGNMENT_CONFLICT` | 409 | Unit fisik yang dipilih sudah terikat pada booking lain di rentang tanggal yang sama. | Overlap jadwal sewa unit fisik. |

### 3.4 Reschedule Domain
| Error Code | HTTP | Deskripsi | Kapan Terjadi |
|---|---|---|---|
| `RESCHEDULE_CONFLICT` | 409 | Ketersediaan unit atau kapasitas tidak cukup pada jadwal baru yang diajukan. | Tanggal baru sudah fully booked termasuk buffer MOB/DEMOB. |

### 3.5 Timesheet Domain
| Error Code | HTTP | Deskripsi | Kapan Terjadi |
|---|---|---|---|
| `TIMESHEET_REQUIRES_REVISION` | 409 | Timesheet tidak dapat disetujui; data laporan tidak valid dan perlu direvisi. | Admin menolak dengan catatan revisi. |

### 3.6 General Domain
| Error Code | HTTP | Deskripsi | Kapan Terjadi |
|---|---|---|---|
| `VALIDATION_ERROR` | 422 | Satu atau lebih field gagal validasi input. Lihat objek `errors`. | Request body tidak sesuai aturan validasi. |
| `NOT_FOUND` | 404 | Resource yang diminta tidak ada atau bukan milik user yang meminta. | ID booking/invoice salah atau tidak memiliki hak akses. |
| `UNAUTHENTICATED` | 401 | Token autentikasi tidak ada, tidak valid, atau sudah kedaluwarsa. | Permintaan tanpa Header Authorization. |
| `UNAUTHORIZED` | 403 | Token valid tapi hak role tidak mencukupi untuk aksi ini. | Role tidak masuk dalam izin Permission Matrix. |
| `RATE_LIMIT_EXCEEDED` | 429 | Terlalu banyak request dalam interval waktu tertentu. | Brute-force login atau polling berlebihan. |
| `INTERNAL_SERVER_ERROR` | 500 | Kesalahan tidak terduga di sisi server. | Exception PHP tidak tertangani di production. |

---

## 4. Contoh Respons Error per Skenario

### 4.1 Booking Conflict (Kuota Habis)
```json
HTTP 409 Conflict
{
  "success": false,
  "message": "Equipment quota is not available for the selected date range.",
  "error_code": "BOOKING_CONFLICT",
  "errors": null
}
```

### 4.2 Invalid State Transition (Cancel pasca Dispatched)
```json
HTTP 409 Conflict
{
  "success": false,
  "message": "Booking cannot be cancelled after dispatching.",
  "error_code": "INVALID_STATE_TRANSITION",
  "errors": null
}
```

### 4.3 Payment Deadline Exceeded
```json
HTTP 409 Conflict
{
  "success": false,
  "message": "Payment deadline has passed. Booking has been expired.",
  "error_code": "PAYMENT_DEADLINE_EXCEEDED",
  "errors": null
}
```

### 4.4 Overpayment Requires Review
```json
HTTP 409 Conflict
{
  "success": false,
  "message": "Payment amount exceeds invoice total. Manual review required by Admin.",
  "error_code": "OVERPAYMENT_REQUIRES_REVIEW",
  "errors": {
    "overpayment_amount": ["IDR 500,000 exceeds the invoice grand total of IDR 15,000,000."]
  }
}
```

### 4.5 Unit Assignment Conflict (Overlap Jadwal)
```json
HTTP 409 Conflict
{
  "success": false,
  "message": "Equipment unit is already assigned to another booking in the selected period.",
  "error_code": "UNIT_ASSIGNMENT_CONFLICT",
  "errors": null
}
```

### 4.6 Reschedule Conflict (Kapasitas Habis)
```json
HTTP 409 Conflict
{
  "success": false,
  "message": "No available capacity for the requested date range including mobilization buffer.",
  "error_code": "RESCHEDULE_CONFLICT",
  "errors": null
}
```

### 4.7 Forbidden Action (Role Tidak Cukup)
```json
HTTP 403 Forbidden
{
  "success": false,
  "message": "You are not authorized to perform this action.",
  "error_code": "FORBIDDEN_ACTION",
  "errors": null
}
```

### 4.8 Validation Error (Multiple Fields)
```json
HTTP 422 Unprocessable Entity
{
  "success": false,
  "message": "Validation failed",
  "error_code": "VALIDATION_ERROR",
  "errors": {
    "rejection_reason": ["The rejection reason must be at least 10 characters."],
    "new_start_date": ["The new start date is required."]
  }
}
```

### 4.9 Timesheet Requires Revision
```json
HTTP 409 Conflict
{
  "success": false,
  "message": "Timesheet rejected. Data revision required.",
  "error_code": "TIMESHEET_REQUIRES_REVISION",
  "errors": {
    "revision_note": ["HM end must be greater than HM start. Please revise and resubmit."]
  }
}
```

---

## 5. Prinsip Global Error Handling

1. **Error Code Deterministik:** Setiap error domain bisnis menggunakan `error_code` SCREAMING_SNAKE_CASE. Tidak ada pesan debug atau stack trace yang diekspos di production environment.
2. **Konsistensi Envelope:** Seluruh respons (sukses maupun error) mengikuti satu envelope JSON standar yang sama.
3. **Severity Levels (Internal):**
   - `info`: State transition normal (sukses).
   - `warning`: Overpayment, Deadline approaching.
   - `error`: Conflict, Forbidden, Validation.
   - `critical`: Internal Server Error.
4. **Logging Wajib:** Setiap 4xx dan 5xx wajib tercatat di Laravel `application.log` beserta request payload (tanpa data sensitif seperti password dan nomor kartu).
5. **Rate Limiting Header:** Respons `429` menyertakan header `X-RateLimit-Limit`, `X-RateLimit-Remaining`, dan `Retry-After`.
