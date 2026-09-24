# Application Layer Patterns Specification - RAFA Rental System

Dokumentasi standar implementasi lapisan aplikasi (*Application Layer*), meliputi pola DTO, Action, Service, Form Request, Controller, dan Database Transaction untuk RAFA Rental System (Phase 2D).

---

## 1. Alur Eksekusi Permintaan (Request Lifecycle Flow)

Setiap request HTTP yang masuk ke backend Laravel melalui alur terstruktur:

```
[ HTTP Request ]
       │
       ▼
[ Form Request ] ────────► Gagal? ──► HTTP 422 (VALIDATION_FAILED via ApiResponse)
       │ (Validasi Lolos)
       ▼
[ Controller (Tipis) ]
       │
       ├──► Ekstraksi data ke [ DTO (Readonly) ]
       │
       ▼
[ Action (Single Purpose) ] ◄── (Injeksi Dependency)
       │
       ├──► Bungkus transaksi: DB::transaction()
       ├──► Panggil [ Domain Service ] (Jika ada kalkulasi kompleks / multi-usecase)
       ├──► Mutasi [ Eloquent Model ] (dengan lockForUpdate jika alokasi kuota)
       └──► Catat [ Activity Log ]
       │
       ▼
[ Controller ]
       │
       ├──► Transformasi via [ API Resource ]
       │
       ▼
[ JSON Response (Envelope Konsisten) ]
```

---

## 2. Pola Data Transfer Object (DTO)

### 2.1 Karakteristik DTO
1. **Immutable & Typed:** Didefinisikan sebagai `readonly class` (PHP 8.2+) dengan seluruh properti memiliki tipe data eksplisit (*strictly typed*).
2. **Terisolasi dari HTTP:** DTO tidak boleh menerima instance `Illuminate\Http\Request` secara langsung di dalam konstruktor dasarnya agar dapat di-instantiate dari CLI / Queue Job / Unit Test tanpa simulasi HTTP.
3. **Static Factory Method:** DTO menyediakan factory method `fromArray()` atau `fromRequest()` untuk mempermudah instansiasi.

### 2.2 Standar Struktur Kode DTO
```php
<?php

namespace App\DTOs;

use App\Http\Requests\SampleRequest;

readonly class SampleData
{
    public function __construct(
        public int $userId,
        public int $equipmentModelId,
        public string $startDate,
        public string $endDate,
        public bool $isAllIn = false,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) $data['user_id'],
            equipmentModelId: (int) $data['equipment_model_id'],
            startDate: (string) $data['start_date'],
            endDate: (string) $data['end_date'],
            isAllIn: (bool) ($data['is_all_in'] ?? false),
        );
    }

    public static function fromRequest(SampleRequest $request): self
    {
        return self::fromArray($request->validated());
    }
}
```

---

## 3. Pola Action (Single-Purpose Use-Case)

### 3.1 Aturan & Tanggung Jawab Action
1. **Satu Aksi = Satu Kelas:** Setiap kelas Action hanya merepresentasikan tepat satu operasi use-case bisnis (contoh penamaan: `ApproveBookingAction`, `AssignPhysicalUnitAction`, `SubmitTimesheetAction`).
2. **Satu Public Method:** Memiliki satu titik masuk utama: `execute()` atau `handle()`.
3. **Atomic Execution:** Action bertanggung jawab membungkus mutasi data multi-tabel di dalam `DB::transaction()`.
4. **Dependency Injection:** Menerima service atau repository pendukung melalui constructor injection.

### 3.2 Standar Struktur Kode Action
```php
<?php

namespace App\Actions;

use App\DTOs\SampleData;
use Illuminate\Support\Facades\DB;

class SampleAction
{
    public function __construct(
        // Injeksi service/helper jika dibutuhkan
    ) {}

    public function execute(SampleData $data): mixed
    {
        return DB::transaction(function () use ($data) {
            // 1. Validasi business rule / state machine
            // 2. Eksekusi mutasi model
            // 3. Catat audit trail / activity log
            
            return true;
        });
    }
}
```

---

## 4. Pola Domain Service

### 4.1 Kapan Menggunakan Service vs Action?
| Aspek | Action | Domain Service |
|---|---|---|
| **Tujuan** | Menjalankan 1 use-case bisnis spesifik (misal: Approve Booking). | Menyediakan logika kalkulasi atau algoritma domain yang dipakai di banyak tempat. |
| **Pemicu** | Dipanggil langsung oleh Controller, Artisan Command, atau Queue Job. | Dipanggil oleh Action atau Service lain. |
| **Karakteristik** | Menghasilkan efek samping (*side-effects*: mutasi DB, dispatch event, log). | Bersifat stateless, fokus pada komputasi (misal: `AvailabilityService`, `PricingService`). |
| **Jumlah Method** | 1 method utama (`execute()`). | Beberapa method terkait domain yang sama (misal: `calculateDailyRate()`, `calculateMobDemob()`). |

### 4.2 Larangan Service
- Service **DILARANG** mengakses global helper `request()` atau mengembalikan `JsonResponse`.
- Service **DILARANG** melakukan *render* tampilan.

---

## 5. Pola Form Request Validation

### 5.1 Aturan Validasi
1. Seluruh validasi input HTTP wajib ditempatkan pada kelas `Illuminate\Foundation\Http\FormRequest` terpisah di `app/Http/Requests/`.
2. Validasi gagal otomatis ditangkap oleh Exception Handler global dan mereturn HTTP `422 Unprocessable Entity` dengan error code `VALIDATION_FAILED`.
3. Form Request menyediakan helper `toDTO()` untuk mempermudah transfer data ke Action.

### 5.2 Standar Struktur Kode Form Request
```php
<?php

namespace App\Http\Requests;

use App\DTOs\SampleData;
use Illuminate\Foundation\Http\FormRequest;

class SampleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi granular tetap didelegasikan ke Policy
    }

    public function rules(): array
    {
        return [
            'equipment_model_id' => ['required', 'integer'],
            'start_date' => ['required', 'date', 'after:today'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_all_in' => ['sometimes', 'boolean'],
        ];
    }

    public function toDTO(): SampleData
    {
        return SampleData::fromRequest($this);
    }
}
```

---

## 6. Kaidah Controller Tipis (Thin Controller Rule)

Controller bertindak murni sebagai koordinator HTTP (pengatur lalu lintas).

### 6.1 Tanggung Jawab Controller (Hanya 3 Langkah):
1. Menerima request yang telah divalidasi oleh Form Request.
2. Memanggil Action / Service terkait dengan mengoper DTO.
3. Mengembalikan respons JSON menggunakan `ApiResponse` atau `JsonResource`.

### 6.2 Contoh Implementasi Controller Standar
```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SampleAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\SampleRequest;
use Illuminate\Http\JsonResponse;

class SampleController extends ApiController
{
    public function __construct(
        private readonly SampleAction $action
    ) {}

    public function store(SampleRequest $request): JsonResponse
    {
        $result = $this->action->execute($request->toDTO());

        return $this->created($result, 'Resource created successfully.');
    }
}
```

---

## 7. Pola Database Transaction & Concurrency

### 7.1 Standar Penggunaan Transaksi
- Gunakan closure `DB::transaction()` langsung di dalam Action untuk seluruh operasi yang memutasi lebih dari 1 baris/tabel.
- Jangan membuat layer abstraksi transaksi yang tidak perlu (misal: Unit of Work kustom). Fitur native Laravel `DB::transaction()` sudah teruji dan kompatibel penuh dengan MySQL 8.4 InnoDB.

### 7.2 Pessimistic Locking untuk Ketersediaan Unit
Saat melakukan penguncian kuota atau penetapan unit fisik:
```php
$unit = EquipmentUnit::where('id', $unitId)
    ->where('status', 'AVAILABLE')
    ->lockForUpdate()
    ->first();

if (! $unit) {
    throw new ResourceConflictException('Unit is no longer available.');
}

$unit->update(['status' => 'ASSIGNED']);
```

---

## 8. Anti-Patterns yang DILARANG

1. **Fat Controller:** Menuliskan `DB::table(...)` atau kalkulasi loop berbaris-baris di method controller.
2. **Overengineered Repository Pattern:** Membuat Interface + Repository Class untuk setiap Model Eloquent padahal hanya melakukan pemanggilan standar `Model::find()` atau `Model::create()`. Eloquent sudah merupakan Active Record ORM yang lengkap.
3. **Direct Request in Service:** Mengoper `$request` ke dalam domain service. Gunakan DTO atau parameter primitif ter-type.
4. **Silent Exception Catching:** Menangkap exception dengan `try-catch` kosong tanpa re-throw atau logging.
