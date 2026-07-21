<?php

namespace App\Models;

use App\Traits\RecordsAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompensationHistory extends Model
{
    use RecordsAudit;

    protected $fillable = ['employee_id', 'effective_date', 'amount', 'currency', 'type', 'notes', 'created_by_id'];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function scopeFilterType(Builder $query, ?string $type): Builder
    {
        return blank($type) ? $query : $query->where('type', $type);
    }

    public function scopeEffectiveOnOrAfter(Builder $query, ?string $date): Builder
    {
        return blank($date) ? $query : $query->whereDate('effective_date', '>=', $date);
    }

    public function scopeEffectiveOnOrBefore(Builder $query, ?string $date): Builder
    {
        return blank($date) ? $query : $query->whereDate('effective_date', '<=', $date);
    }
}
