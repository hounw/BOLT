<?php

namespace App\Models;

use App\Enums\PtoRequestStatus;
use App\Traits\RecordsAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PtoRequest extends Model
{
    use RecordsAudit;

    protected $fillable = [
        'employee_id',
        'pto_policy_id',
        'approver_id',
        'starts_at',
        'ends_at',
        'days',
        'status',
        'reason',
        'decision_notes',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'days' => 'decimal:2',
            'status' => PtoRequestStatus::class,
            'decided_at' => 'datetime',
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function scopeFilterStatus(Builder $query, ?string $status): Builder
    {
        return blank($status) ? $query : $query->where('status', $status);
    }

    public function scopeForEmployee(Builder $query, null|int|string $employeeId): Builder
    {
        return blank($employeeId) ? $query : $query->where('employee_id', (int) $employeeId);
    }

    public function scopeForPolicy(Builder $query, null|int|string $policyId): Builder
    {
        return blank($policyId) ? $query : $query->where('pto_policy_id', (int) $policyId);
    }

    public function scopeStartingOnOrAfter(Builder $query, ?string $date): Builder
    {
        return blank($date) ? $query : $query->whereDate('starts_at', '>=', $date);
    }

    public function scopeStartingOnOrBefore(Builder $query, ?string $date): Builder
    {
        return blank($date) ? $query : $query->whereDate('starts_at', '<=', $date);
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
