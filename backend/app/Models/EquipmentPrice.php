<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'equipment_model_id',
        'price_type',
        'is_all_in',
        'base_rate',
        'minimum_hours',
        'overtime_rate',
        'effective_date',
    ];

    protected function casts(): array
    {
        return [
            'is_all_in' => 'boolean',
            'base_rate' => 'decimal:2',
            'minimum_hours' => 'integer',
            'overtime_rate' => 'decimal:2',
            'effective_date' => 'date',
        ];
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(EquipmentModel::class, 'equipment_model_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(EquipmentPriceVersion::class);
    }
}
