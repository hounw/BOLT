<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use App\Traits\RecordsAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Employee extends Model
{
    use HasFactory, RecordsAudit;

    protected $fillable = [
        'user_id',
        'manager_id',
        'employee_number',
        'first_name',
        'last_name',
        'work_email',
        'photo_path',
        'personal_email',
        'phone',
        'status',
        'department_id',
        'department',
        'position_id',
        'title',
        'start_date',
        'end_date',
        'emergency_contact',
        'hr_metadata',
        'private_hr_data',
    ];

    protected function casts(): array
    {
        return [
            'status' => EmployeeStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'emergency_contact' => 'array',
            'hr_metadata' => 'array',
            'private_hr_data' => 'encrypted:array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function departmentRecord(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function reportsRecursive(): HasMany
    {
        return $this->reports()->with([
            'departmentRecord:id,name',
            'position:id,name',
            'reportsRecursive',
        ])->orderBy('first_name')->orderBy('last_name');
    }

    public function compensationHistories(): HasMany
    {
        return $this->hasMany(CompensationHistory::class);
    }

    public function benefitHistories(): HasMany
    {
        return $this->hasMany(BenefitHistory::class);
    }

    public function ptoBalances(): HasMany
    {
        return $this->hasMany(PtoBalance::class);
    }

    public function ptoRequests(): HasMany
    {
        return $this->hasMany(PtoRequest::class);
    }

    public function assetAssignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function auditMaskedAttributes(): array
    {
        return [
            'personal_email',
            'phone',
            'emergency_contact',
            'hr_metadata',
            'private_hr_data',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getInitialsAttribute(): string
    {
        return str($this->full_name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => str($part)->substr(0, 1)->upper()->toString())
            ->join('');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $needle = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';

        return $query->where(function (Builder $query) use ($needle): void {
            $query->where('first_name', 'like', $needle)
                ->orWhere('last_name', 'like', $needle)
                ->orWhere('employee_number', 'like', $needle)
                ->orWhere('work_email', 'like', $needle)
                ->orWhere('department', 'like', $needle)
                ->orWhere('title', 'like', $needle)
                ->orWhereHas('departmentRecord', fn (Builder $department) => $department->where('name', 'like', $needle))
                ->orWhereHas('position', fn (Builder $position) => $position->where('name', 'like', $needle));
        });
    }

    public function scopeFilterStatus(Builder $query, ?string $status): Builder
    {
        return blank($status) ? $query : $query->where('status', $status);
    }

    public function scopeFilterDepartment(Builder $query, ?string $department): Builder
    {
        return blank($department)
            ? $query
            : $query->where(function (Builder $query) use ($department): void {
                $query->where('department', $department)
                    ->orWhereHas('departmentRecord', fn (Builder $record) => $record->where('name', $department));
            });
    }

    public function scopeManagedBy(Builder $query, null|int|string $managerId): Builder
    {
        return blank($managerId) ? $query : $query->where('manager_id', (int) $managerId);
    }
}
