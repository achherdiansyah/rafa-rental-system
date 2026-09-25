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
        'work_volume',
        'terrain_condition',
        'depth_requirement',
        'reach_requirement',
        'load_capacity',
        'target_productivity',
        'location_access',
        'duration_days',
        'budget_range',
        'additional_params',
    ];

    protected function casts(): array
    {
        return [
            'work_volume' => 'decimal:2',
            'depth_requirement' => 'decimal:2',
            'reach_requirement' => 'decimal:2',
            'load_capacity' => 'decimal:2',
            'duration_days' => 'integer',
            'additional_params' => 'array',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(RecommendationRequest::class, 'request_id');
    }
}
