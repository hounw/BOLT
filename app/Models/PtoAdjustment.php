<?php

namespace App\Models;

use App\Traits\RecordsAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PtoAdjustment extends Model
{
    use RecordsAudit;

    protected $fillable = [
        'employee_id',
        'pto_policy_id',
        'adjusted_by_id',
        'effective_date',
        'days',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'days' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(PtoPolicy::class, 'pto_policy_id');
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by_id');
    }
}
