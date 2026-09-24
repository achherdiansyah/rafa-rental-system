<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EquipmentModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'equipment_type_id',
        'brand',
        'model_name',
        'capacity_value',
        'capacity_unit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity_value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(EquipmentType::class, 'equipment_type_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(EquipmentUnit::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(EquipmentPrice::class);
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
