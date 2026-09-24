<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecommendationCriteria extends Model
{
    use HasFactory;

    protected $table = 'recommendation_criteria';

    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'project_type',
        'terrain_condition',
        'load_capacity',
        'budget_range',
    ];

    protected function casts(): array
    {
        return [
            'load_capacity' => 'decimal:2',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(RecommendationRequest::class, 'request_id');
    }
}
