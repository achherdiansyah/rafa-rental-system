<?php

namespace App\Enums;

enum RecommendationStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSED = 'PROCESSED';
    case FAILED = 'FAILED';
}
