<?php

namespace App\Models;

use App\Enums\EquipmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EquipmentUnit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'equipment_model_id',
        'serial_number',
        'plate_number',
        'status',
        'last_hour_meter',
        'year_of_make',
    ];

    protected function casts(): array
    {
        return [
            'status' => EquipmentStatus::class,
            'last_hour_meter' => 'decimal:2',
            'year_of_make' => 'integer',
        ];
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(EquipmentModel::class, 'equipment_model_id');
    }
}
