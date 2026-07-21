<?php

namespace App\Enums;

enum SystemRole: string
{
    case OwnerAdmin = 'owner-admin';
    case HrManager = 'hr-manager';
    case Manager = 'manager';
    case Employee = 'employee';
    case Auditor = 'auditor';
    case ApiClient = 'api-client';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
