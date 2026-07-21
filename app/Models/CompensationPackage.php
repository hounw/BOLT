<?php

namespace App\Models;

use App\Traits\RecordsAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CompensationPackage extends Model
{
    use RecordsAudit;

    public const AMOUNT_BASES = [
        'annual' => 'Per year',
        'monthly' => 'Per month',
        'hourly' => 'Hourly rate',
    ];

    public const PAYMENT_FREQUENCIES = [
        'monthly' => 'Monthly',
        'bimonthly' => 'Bi-monthly',
        'biweekly' => 'Every other week',
    ];

    protected $fillable = ['name', 'amount', 'currency', 'amount_basis', 'payment_frequency', 'type', 'notes', 'is_active'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function amountBasisLabel(): string
    {
        return self::AMOUNT_BASES[$this->amount_basis] ?? $this->amount_basis;
    }

    public function paymentFrequencyLabel(): string
    {
        return self::PAYMENT_FREQUENCIES[$this->payment_frequency] ?? $this->payment_frequency;
    }

    public function optionLabel(): string
    {
        return "{$this->name} - {$this->currency} {$this->amount} {$this->amountBasisLabel()} - {$this->paymentFrequencyLabel()}";
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $needle = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';

        return $query->where(function (Builder $query) use ($needle): void {
            $query->where('name', 'like', $needle)
                ->orWhere('type', 'like', $needle)
                ->orWhere('notes', 'like', $needle);
        });
    }
}
