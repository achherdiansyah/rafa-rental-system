<?php

namespace App\Enums;

enum UserRole: string
{
    case USER = 'USER';
    case ADMIN = 'ADMIN';
    case SUPERVISOR = 'SUPERVISOR';
    case OPERATOR = 'OPERATOR';
    case OWNER = 'OWNER';
}

