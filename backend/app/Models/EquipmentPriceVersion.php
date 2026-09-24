<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentPriceVersion extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'equipment_price_id',
        'old_base_rate',
        'new_base_rate',
        'changed_at',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'old_base_rate' => 'decimal:2',
            'new_base_rate' => 'decimal:2',
            'changed_at' => 'datetime',
        ];
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(EquipmentPrice::class, 'equipment_price_id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
