<?php

namespace App\Enums;

enum PtoRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Canceled = 'canceled';
}
