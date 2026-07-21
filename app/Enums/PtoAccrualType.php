<?php

namespace App\Enums;

enum PtoAccrualType: string
{
    case AnnualGrant = 'annual_grant';
    case MonthlyAccrual = 'monthly_accrual';
    case Manual = 'manual';
}
