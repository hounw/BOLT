<?php

namespace App\Models;

use App\Traits\RecordsAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BenefitHistory extends Model
{
    use RecordsAudit;

    protected $fillable = ['employee_id', 'type', 'value', 'starts_on', 'ends_on', 'notes', 'created_by_id'];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
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

    public function scopeStartingOnOrAfter(Builder $query, ?string $date): Builder
    {
        return blank($date) ? $query : $query->whereDate('starts_on', '>=', $date);
    }

    public function scopeStartingOnOrBefore(Builder $query, ?string $date): Builder
    {
        return blank($date) ? $query : $query->whereDate('starts_on', '<=', $date);
    }
}
