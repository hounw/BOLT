<?php

namespace App\Enums;

enum EmployeeStatus: string
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Inactive = 'inactive';
    case Terminated = 'terminated';
}
