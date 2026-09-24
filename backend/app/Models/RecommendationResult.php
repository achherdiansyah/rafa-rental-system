<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecommendationResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'equipment_model_id',
        'match_score',
        'reasoning_text',
    ];

    protected function casts(): array
    {
        return [
            'match_score' => 'decimal:2',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(RecommendationRequest::class, 'request_id');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(EquipmentModel::class, 'equipment_model_id');
    }
}
