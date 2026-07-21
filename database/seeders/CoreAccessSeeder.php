<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\PtoAccrualType;
use App\Enums\SystemRole;
use App\Models\PtoPolicy;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CoreAccessSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['web', 'api'] as $guard) {
            $permissions = collect(PermissionName::cases())
                ->mapWithKeys(fn (PermissionName $permission): array => [
                    $permission->value => Permission::findOrCreate($permission->value, $guard),
                ]);

            Role::findOrCreate(SystemRole::OwnerAdmin->value, $guard)
                ->syncPermissions($permissions->values());

            Role::findOrCreate(SystemRole::HrManager->value, $guard)->syncPermissions([
                PermissionName::EmployeesView->value,
                PermissionName::EmployeesManage->value,
                PermissionName::CompensationView->value,
                PermissionName::CompensationManage->value,
                PermissionName::BenefitsView->value,
                PermissionName::BenefitsManage->value,
                PermissionName::PtoView->value,
                PermissionName::PtoManage->value,
                PermissionName::PtoApprove->value,
                PermissionName::FilesView->value,
                PermissionName::FilesManage->value,
                PermissionName::KnowledgeView->value,
                PermissionName::KnowledgeManage->value,
                PermissionName::AssetsView->value,
                PermissionName::AssetsManage->value,
                PermissionName::AuditView->value,
            ]);

            Role::findOrCreate(SystemRole::Manager->value, $guard)->syncPermissions([
                PermissionName::EmployeesView->value,
                PermissionName::PtoView->value,
                PermissionName::PtoApprove->value,
                PermissionName::FilesView->value,
                PermissionName::KnowledgeView->value,
                PermissionName::AssetsView->value,
            ]);

            Role::findOrCreate(SystemRole::Employee->value, $guard)->syncPermissions([
                PermissionName::PtoView->value,
                PermissionName::FilesView->value,
                PermissionName::KnowledgeView->value,
            ]);

            Role::findOrCreate(SystemRole::Auditor->value, $guard)->syncPermissions([
                PermissionName::EmployeesView->value,
                PermissionName::PtoView->value,
                PermissionName::FilesView->value,
                PermissionName::KnowledgeView->value,
                PermissionName::AssetsView->value,
                PermissionName::AuditView->value,
            ]);

            Role::findOrCreate(SystemRole::ApiClient->value, $guard)->syncPermissions([
                PermissionName::EmployeesView->value,
                PermissionName::PtoView->value,
                PermissionName::FilesView->value,
                PermissionName::KnowledgeView->value,
                PermissionName::AssetsView->value,
            ]);
        }

        PtoPolicy::firstOrCreate(
            ['name' => 'Default PTO'],
            [
                'annual_allowance_days' => 15,
                'accrual_type' => PtoAccrualType::AnnualGrant,
                'accumulation_frequency' => 'monthly',
                'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'holidays' => [],
                'allow_negative_balance' => false,
                'carryover_days' => 5,
                'approval_strategy' => 'manager_then_hr',
                'is_default' => true,
            ],
        );
    }
}
