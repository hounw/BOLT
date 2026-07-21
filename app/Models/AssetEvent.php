<?php

namespace App\Models;

use App\Traits\RecordsAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AssetEvent extends Model
{
    use RecordsAudit;

    public const TYPES = [
        'note' => 'Note',
        'condition' => 'Condition',
        'delivered' => 'Delivered',
        'assigned' => 'Assigned',
        'returned' => 'Returned',
        'repaired' => 'Repaired',
        'audited' => 'Audited',
    ];

    protected $fillable = [
        'asset_id',
        'type',
        'occurred_at',
        'actor_id',
        'from_employee_id',
        'employee_id',
        'condition',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function fromEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'from_employee_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
