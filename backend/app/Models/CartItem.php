<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'equipment_model_id',
        'quantity',
        'is_all_in',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'is_all_in' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(EquipmentModel::class, 'equipment_model_id');
    }
}
