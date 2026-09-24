<?php

namespace App\Enums;

enum EquipmentStatus: string
{
    case AVAILABLE = 'AVAILABLE';
    case ASSIGNED = 'ASSIGNED';
    case MOBILIZING = 'MOBILIZING';
    case ON_SITE = 'ON_SITE';
    case DEMOBILIZING = 'DEMOBILIZING';
    case RETURN_INSPECTION = 'RETURN_INSPECTION';
    case MAINTENANCE = 'MAINTENANCE';
    case DECOMMISSIONED = 'DECOMMISSIONED';
}
