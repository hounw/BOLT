<?php

namespace App\Models;

use App\Traits\RecordsAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PtoBalance extends Model
{
    use RecordsAudit;

    protected $fillable = [
        'employee_id',
        'pto_policy_id',
        'available_days',
        'used_days',
        'pending_days',
        'period_start',
        'period_end',
    ];

    protected function casts(): array
    {
        return [
            'available_days' => 'decimal:2',
            'used_days' => 'decimal:2',
            'pending_days' => 'decimal:2',
            'period_start' => 'date',
            'period_end' => 'date',
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

    public function scopeVisibleToUser(Builder $query, User $user): Builder
    {
        if ($user->can('pto.manage')) {
            return $query;
        }

        return $query->whereHas('employee', function (Builder $employee) use ($user): void {
            $employee->where('user_id', $user->id);

            if ($user->can('pto.approve')) {
                $employee->orWhereHas('manager', fn (Builder $manager) => $manager->where('user_id', $user->id));
            }
        });
    }
}
