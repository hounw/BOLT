<?php

namespace App\Http\Resources;

use App\Enums\PermissionName;
use App\Models\BenefitHistory;
use App\Models\CompensationHistory;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $oldValues = $this->visibleValues($request, $this->old_values);
        $newValues = $this->visibleValues($request, $this->new_values);

        return [
            'id' => $this->id,
            'actor_id' => $this->actor_id,
            'event' => $this->event,
            'auditable_type' => $this->auditable_type,
            'auditable_id' => ctype_digit((string) $this->auditable_id) ? (int) $this->auditable_id : $this->auditable_id,
            'ip_address' => $this->ip_address,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'sensitive_values_redacted' => $this->valuesWereRedacted($request),
            'metadata' => $this->metadata,
            'occurred_at' => $this->occurred_at?->toISOString(),
        ];
    }

    private function visibleValues(Request $request, ?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        if ($this->auditable_type === CompensationHistory::class && ! $this->canViewCompensation($request)) {
            return null;
        }

        if ($this->auditable_type === BenefitHistory::class && ! $this->canViewBenefits($request)) {
            return null;
        }

        $values = Arr::except($values, ['password', 'remember_token', 'secret', 'token']);

        if ($this->auditable_type === Employee::class && ! $request->user()?->can(PermissionName::EmployeesManage->value)) {
            return Arr::except($values, [
                'personal_email',
                'phone',
                'emergency_contact',
                'hr_metadata',
                'private_hr_data',
            ]);
        }

        return $values;
    }

    private function valuesWereRedacted(Request $request): bool
    {
        return ($this->auditable_type === CompensationHistory::class && ! $this->canViewCompensation($request))
            || ($this->auditable_type === BenefitHistory::class && ! $this->canViewBenefits($request))
            || ($this->auditable_type === Employee::class
                && ! $request->user()?->can(PermissionName::EmployeesManage->value)
                && $this->employeeValuesContainSensitiveFields());
    }

    private function employeeValuesContainSensitiveFields(): bool
    {
        $sensitiveFields = ['personal_email', 'phone', 'emergency_contact', 'hr_metadata', 'private_hr_data'];

        return collect([$this->old_values, $this->new_values])
            ->filter(fn (?array $values): bool => $values !== null)
            ->contains(fn (array $values): bool => Arr::hasAny($values, $sensitiveFields));
    }

    private function canViewCompensation(Request $request): bool
    {
        return (bool) $request->user()?->can(PermissionName::CompensationView->value)
            || (bool) $request->user()?->can(PermissionName::CompensationManage->value);
    }

    private function canViewBenefits(Request $request): bool
    {
        return (bool) $request->user()?->can(PermissionName::BenefitsView->value)
            || (bool) $request->user()?->can(PermissionName::BenefitsManage->value);
    }
}
