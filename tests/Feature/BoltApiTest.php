<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\SystemRole;
use App\Jobs\DeliverWebhook;
use App\Models\Asset;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\CompensationPackage;
use App\Models\Department;
use App\Models\Employee;
use App\Models\KnowledgeArticle;
use App\Models\Position;
use App\Models\PtoBalance;
use App\Models\PtoPolicy;
use App\Models\PtoRequest;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\AuditLogger;
use Database\Seeders\CoreAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class BoltApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requires_authentication_with_standard_error_shape(): void
    {
        $this->getJson('/api/v1/employees')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_owner_can_create_employee_and_replay_idempotent_response(): void
    {
        $this->actingAsRole(SystemRole::OwnerAdmin, ['employees:read', 'employees:write']);

        $payload = [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'work_email' => 'ada@example.test',
            'department' => 'Operations',
            'title' => 'Systems Lead',
        ];

        $first = $this->withHeader('Idempotency-Key', 'employee-create-1')
            ->postJson('/api/v1/employees', $payload)
            ->assertSuccessful()
            ->assertJsonPath('data.first_name', 'Ada');

        $this->withHeader('Idempotency-Key', 'employee-create-1')
            ->postJson('/api/v1/employees', $payload)
            ->assertSuccessful()
            ->assertExactJson($first->json());

        $this->assertDatabaseCount('employees', 1);
        $this->assertDatabaseHas('audit_logs', ['event' => 'employee.created']);
    }

    public function test_employees_can_be_filtered_for_directory_retrieval(): void
    {
        $this->actingAsRole(SystemRole::OwnerAdmin, ['employees:read']);

        $manager = Employee::create([
            'first_name' => 'Morgan',
            'last_name' => 'Manager',
            'status' => 'active',
            'department' => 'Operations',
            'title' => 'Ops Lead',
        ]);
        Employee::create([
            'manager_id' => $manager->id,
            'employee_number' => 'EMP-OPS-001',
            'first_name' => 'Avery',
            'last_name' => 'Operator',
            'work_email' => 'avery@example.test',
            'status' => 'active',
            'department' => 'Operations',
            'title' => 'Field Coordinator',
        ]);
        Employee::create([
            'first_name' => 'Riley',
            'last_name' => 'Finance',
            'work_email' => 'riley@example.test',
            'status' => 'inactive',
            'department' => 'Finance',
            'title' => 'Controller',
        ]);

        $response = $this->getJson('/api/v1/employees?q=field&status=active&department=Operations&manager_id='.$manager->id)
            ->assertSuccessful()
            ->assertJsonPath('data.0.first_name', 'Avery');

        $this->assertCount(1, $response->json('data'));
    }

    public function test_people_reference_api_crud_respects_scopes_and_policies(): void
    {
        $this->actingAsRole(SystemRole::OwnerAdmin, ['employees:read', 'employees:write', 'hr:read', 'hr:write']);

        $department = $this->withHeader('Idempotency-Key', 'department-create-1')
            ->postJson('/api/v1/departments', [
                'name' => 'Operations',
                'description' => 'Ops team',
                'is_active' => true,
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.name', 'Operations')
            ->assertJsonPath('data.parent_id', null)
            ->assertJsonPath('data.path', 'Operations');

        $childDepartment = $this->withHeader('Idempotency-Key', 'department-child-create-1')
            ->postJson('/api/v1/departments', [
                'parent_id' => $department->json('data.id'),
                'name' => 'Field operations',
                'is_active' => true,
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.parent_id', $department->json('data.id'))
            ->assertJsonPath('data.parent_name', 'Operations')
            ->assertJsonPath('data.path', 'Operations / Field operations');

        $this->patchJson('/api/v1/departments/'.$department->json('data.id'), [
            'name' => 'Operations Team',
            'is_active' => true,
        ])
            ->assertSuccessful()
            ->assertJsonPath('data.name', 'Operations Team');

        $this->patchJson('/api/v1/departments/'.$department->json('data.id'), [
            'parent_id' => $childDepartment->json('data.id'),
            'name' => 'Operations Team',
            'is_active' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.fields.parent_id.0', 'A department cannot be inside itself or one of its child departments.');

        $position = $this->withHeader('Idempotency-Key', 'position-create-1')
            ->postJson('/api/v1/positions', [
                'name' => 'Coordinator',
                'is_active' => true,
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.name', 'Coordinator');

        $this->getJson('/api/v1/positions?q=coord')
            ->assertSuccessful()
            ->assertJsonPath('data.0.id', $position->json('data.id'));

        $package = $this->withHeader('Idempotency-Key', 'package-create-1')
            ->postJson('/api/v1/compensation-packages', [
                'name' => 'Coordinator salary',
                'amount' => 72000,
                'currency' => 'USD',
                'amount_basis' => 'annual',
                'payment_frequency' => 'monthly',
                'type' => 'salary',
                'notes' => 'Base pay',
                'is_active' => true,
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.amount', '72000.00')
            ->assertJsonPath('data.amount_basis', 'annual')
            ->assertJsonPath('data.amount_basis_label', 'Per year')
            ->assertJsonPath('data.payment_frequency', 'monthly')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.payment_frequency_label', 'Monthly');

        $this->patchJson('/api/v1/compensation-packages/'.$package->json('data.id'), [
            'name' => 'Coordinator salary',
            'amount' => 73000,
            'currency' => 'USD',
            'amount_basis' => 'monthly',
            'payment_frequency' => 'bimonthly',
            'type' => 'salary',
            'is_active' => true,
        ])
            ->assertSuccessful()
            ->assertJsonPath('data.amount', '73000.00')
            ->assertJsonPath('data.amount_basis', 'monthly')
            ->assertJsonPath('data.payment_frequency', 'bimonthly');
    }

    public function test_employee_api_accepts_reference_ids_and_legacy_department_title_strings(): void
    {
        $this->actingAsRole(SystemRole::OwnerAdmin, ['employees:read', 'employees:write']);

        $department = Department::create(['name' => 'Finance', 'is_active' => true]);
        $position = Position::create(['name' => 'Controller', 'is_active' => true]);

        $this->withHeader('Idempotency-Key', 'employee-reference-create-1')
            ->postJson('/api/v1/employees', [
                'first_name' => 'Reference',
                'last_name' => 'Employee',
                'department_id' => $department->id,
                'position_id' => $position->id,
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.department_id', $department->id)
            ->assertJsonPath('data.department', 'Finance')
            ->assertJsonPath('data.position_id', $position->id)
            ->assertJsonPath('data.title', 'Controller');

        $this->withHeader('Idempotency-Key', 'employee-legacy-create-1')
            ->postJson('/api/v1/employees', [
                'first_name' => 'Legacy',
                'last_name' => 'Employee',
                'department' => 'Support',
                'title' => 'Support Lead',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.department', 'Support')
            ->assertJsonPath('data.title', 'Support Lead');

        $this->assertDatabaseHas('departments', ['name' => 'Support']);
        $this->assertDatabaseHas('positions', ['name' => 'Support Lead']);
    }

    public function test_employee_api_private_hr_fields_are_permission_gated(): void
    {
        $owner = $this->actingAsRole(SystemRole::OwnerAdmin, ['employees:read', 'employees:write']);

        $employee = Employee::create([
            'first_name' => 'Private',
            'last_name' => 'Api',
            'status' => 'active',
            'personal_email' => 'private@example.test',
            'phone' => '+1 555 0100',
            'private_hr_data' => ['tax_id' => 'API-TAX'],
            'emergency_contact' => ['name' => 'API Contact'],
        ]);

        Passport::actingAs($owner, ['employees:read'], 'api');
        $this->getJson('/api/v1/employees/'.$employee->id)
            ->assertOk()
            ->assertJsonPath('data.personal_email', 'private@example.test')
            ->assertJsonPath('data.phone', '+1 555 0100')
            ->assertJsonPath('data.private_hr_data.tax_id', 'API-TAX')
            ->assertJsonPath('data.emergency_contact.name', 'API Contact');

        $auditor = User::factory()->create();
        $auditor->assignRole(SystemRole::Auditor->value);
        Passport::actingAs($auditor, ['employees:read'], 'api');

        $this->getJson('/api/v1/employees/'.$employee->id)
            ->assertOk()
            ->assertJsonMissingPath('data.personal_email')
            ->assertJsonMissingPath('data.phone')
            ->assertJsonMissingPath('data.private_hr_data')
            ->assertJsonMissingPath('data.emergency_contact');

        $audit = AuditLog::where('event', 'employee.created')->where('auditable_id', $employee->id)->firstOrFail();
        $this->assertSame('[REDACTED]', $audit->new_values['personal_email']);
        $this->assertSame('[REDACTED]', $audit->new_values['private_hr_data']);
    }

    public function test_employee_api_onboarding_can_seed_compensation_and_pto_when_scoped(): void
    {
        $this->actingAsRole(SystemRole::OwnerAdmin, ['employees:read', 'employees:write', 'hr:write', 'pto:write']);

        $package = CompensationPackage::create([
            'name' => 'API salary',
            'amount' => 88000,
            'currency' => 'USD',
            'type' => 'salary',
            'is_active' => true,
        ]);
        $policy = PtoPolicy::firstOrFail();

        $response = $this->withHeader('Idempotency-Key', 'employee-api-onboarding-1')
            ->postJson('/api/v1/employees', [
                'first_name' => 'Api',
                'last_name' => 'Onboarding',
                'compensation_package_id' => $package->id,
                'compensation_effective_date' => '2026-07-01',
                'starting_pto_policy_id' => $policy->id,
                'starting_pto_available_days' => 2,
                'starting_pto_period_start' => '2026-01-01',
                'starting_pto_period_end' => '2026-12-31',
            ])
            ->assertCreated();

        $employeeId = $response->json('data.id');

        $this->assertDatabaseHas('compensation_histories', [
            'employee_id' => $employeeId,
            'amount' => '88000.00',
        ]);
        $this->assertDatabaseHas('pto_balances', [
            'employee_id' => $employeeId,
            'available_days' => '2.00',
        ]);
    }

    public function test_idempotent_post_requires_key(): void
    {
        $this->actingAsRole(SystemRole::OwnerAdmin, ['employees:write']);

        $this->postJson('/api/v1/employees', [
            'first_name' => 'No',
            'last_name' => 'Key',
        ])
            ->assertStatus(428)
            ->assertJsonPath('error.code', 'idempotency_key_required');
    }

    public function test_missing_api_scope_returns_standard_forbidden_error(): void
    {
        $this->actingAsRole(SystemRole::OwnerAdmin, ['knowledge:read']);

        $this->getJson('/api/v1/employees')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_api_rate_limit_returns_headers_and_standard_error_shape(): void
    {
        config(['bolt.api.rate_limit' => '1,1']);
        RateLimiter::clear('1');

        $this->actingAsRole(SystemRole::OwnerAdmin, ['employees:read']);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '1')
            ->assertHeader('X-RateLimit-Remaining', '0');

        $this->getJson('/api/v1/me')
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('error.code', 'rate_limited');
    }

    public function test_audit_logs_can_be_filtered_for_operational_review(): void
    {
        $actor = $this->actingAsRole(SystemRole::OwnerAdmin, ['audit:read']);
        $employee = Employee::create([
            'first_name' => 'Audit',
            'last_name' => 'Target',
            'status' => 'active',
        ]);

        AuditLog::create([
            'actor_id' => $actor->id,
            'event' => 'employee.updated',
            'auditable_type' => Employee::class,
            'auditable_id' => $employee->id,
            'occurred_at' => '2026-08-10 12:00:00',
        ]);
        AuditLog::create([
            'event' => 'webhook.delivery_failed',
            'occurred_at' => '2026-08-12 12:00:00',
        ]);

        $query = http_build_query([
            'event' => 'employee.updated',
            'actor_id' => $actor->id,
            'auditable_type' => Employee::class,
            'auditable_id' => $employee->id,
            'occurred_from' => '2026-08-01',
            'occurred_until' => '2026-08-31',
        ]);

        $response = $this->getJson('/api/v1/audit-logs?'.$query)
            ->assertOk()
            ->assertJsonPath('data.0.event', 'employee.updated');

        $this->assertCount(1, $response->json('data'));
    }

    public function test_hr_history_is_restricted_to_hr_permissions(): void
    {
        $employee = Employee::create(['first_name' => 'Grace', 'last_name' => 'Hopper']);

        $this->actingAsRole(SystemRole::Employee, ['hr:read']);
        $this->getJson("/api/v1/employees/{$employee->id}/compensation-history")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');

        $this->actingAsRole(SystemRole::HrManager, ['hr:read', 'hr:write']);
        $this->withHeader('Idempotency-Key', 'comp-create-1')
            ->postJson("/api/v1/employees/{$employee->id}/compensation-history", [
                'effective_date' => '2026-07-01',
                'amount' => 90000,
                'currency' => 'USD',
                'type' => 'salary',
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '90000.00');
    }

    public function test_hr_history_can_be_filtered_for_authorized_api_clients(): void
    {
        $employee = Employee::create(['first_name' => 'Filter', 'last_name' => 'Subject']);
        $this->actingAsRole(SystemRole::HrManager, ['hr:read']);

        $employee->compensationHistories()->create([
            'effective_date' => '2026-01-01',
            'amount' => 90000,
            'currency' => 'USD',
            'type' => 'salary',
        ]);
        $employee->compensationHistories()->create([
            'effective_date' => '2026-03-01',
            'amount' => 5000,
            'currency' => 'USD',
            'type' => 'bonus',
        ]);
        $employee->benefitHistories()->create([
            'type' => 'Health stipend',
            'value' => 500,
            'starts_on' => '2026-02-01',
        ]);
        $employee->benefitHistories()->create([
            'type' => 'Relocation bonus',
            'value' => 2500,
            'starts_on' => '2026-04-01',
        ]);

        $compensation = $this->getJson("/api/v1/employees/{$employee->id}/compensation-history?type=bonus&effective_from=2026-02-01&effective_until=2026-03-31")
            ->assertOk()
            ->assertJsonPath('data.0.type', 'bonus');

        $this->assertCount(1, $compensation->json('data'));

        $benefits = $this->getJson("/api/v1/employees/{$employee->id}/benefit-history?type=Health+stipend&starts_from=2026-01-01&starts_until=2026-02-28")
            ->assertOk()
            ->assertJsonPath('data.0.type', 'Health stipend');

        $this->assertCount(1, $benefits->json('data'));
    }

    public function test_auditor_cannot_read_sensitive_hr_history(): void
    {
        $employee = Employee::create(['first_name' => 'Audit', 'last_name' => 'Subject']);

        $this->actingAsRole(SystemRole::Auditor, ['hr:read']);

        $this->getJson("/api/v1/employees/{$employee->id}/compensation-history")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');

        $this->getJson("/api/v1/employees/{$employee->id}/benefit-history")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_audit_api_redacts_sensitive_values_without_hiding_operational_events(): void
    {
        $owner = $this->actingAsRole(SystemRole::OwnerAdmin, ['audit:read']);
        $employee = Employee::create([
            'first_name' => 'Audit',
            'last_name' => 'Privacy',
            'personal_email' => 'private-audit@example.test',
        ]);
        $compensation = $employee->compensationHistories()->create([
            'effective_date' => '2026-07-01',
            'amount' => 123456,
            'currency' => 'USD',
            'type' => 'salary',
            'notes' => 'Sensitive compensation note',
        ]);

        Passport::actingAs($owner, ['audit:read'], 'api');
        $this->getJson('/api/v1/audit-logs?event=compensation_history.created')
            ->assertOk()
            ->assertJsonPath('data.0.auditable_id', $compensation->id)
            ->assertJsonPath('data.0.new_values.amount', 123456)
            ->assertJsonPath('data.0.sensitive_values_redacted', false);

        $auditor = User::factory()->create();
        $auditor->assignRole(SystemRole::Auditor->value);
        Passport::actingAs($auditor, ['audit:read'], 'api');

        $this->getJson('/api/v1/audit-logs?event=compensation_history.created')
            ->assertOk()
            ->assertJsonPath('data.0.auditable_id', $compensation->id)
            ->assertJsonPath('data.0.new_values', null)
            ->assertJsonPath('data.0.sensitive_values_redacted', true);

        $this->getJson('/api/v1/audit-logs?event=employee.created&auditable_id='.$employee->id)
            ->assertOk()
            ->assertJsonPath('data.0.new_values.first_name', 'Audit')
            ->assertJsonMissingPath('data.0.new_values.personal_email')
            ->assertJsonPath('data.0.sensitive_values_redacted', true);
    }

    public function test_employee_attachment_download_is_limited_to_own_or_managed_records(): void
    {
        Storage::fake('local');
        $this->seed(CoreAccessSeeder::class);

        $ownerUser = User::factory()->create();
        $ownerUser->assignRole(SystemRole::Employee->value);
        $otherUser = User::factory()->create();
        $otherUser->assignRole(SystemRole::Employee->value);
        $managerUser = User::factory()->create();
        $managerUser->assignRole(SystemRole::Manager->value);

        $managerEmployee = Employee::create([
            'user_id' => $managerUser->id,
            'first_name' => 'Manager',
            'last_name' => 'User',
        ]);
        $ownerEmployee = Employee::create([
            'user_id' => $ownerUser->id,
            'manager_id' => $managerEmployee->id,
            'first_name' => 'File',
            'last_name' => 'Owner',
        ]);

        Storage::disk('local')->put('attachments/private-note.txt', 'private');
        $attachment = Attachment::create([
            'attachable_type' => $ownerEmployee->getMorphClass(),
            'attachable_id' => $ownerEmployee->id,
            'disk' => 'local',
            'path' => 'attachments/private-note.txt',
            'original_name' => 'private-note.txt',
            'size' => 7,
            'uploaded_by_id' => $managerUser->id,
        ]);

        Passport::actingAs($ownerUser, ['files:read'], 'api');
        $this->getJson("/api/v1/attachments/{$attachment->id}/download")->assertOk();

        Passport::actingAs($managerUser, ['files:read'], 'api');
        $this->getJson("/api/v1/attachments/{$attachment->id}/download")->assertOk();

        Passport::actingAs($otherUser, ['files:read'], 'api');
        $this->getJson("/api/v1/attachments/{$attachment->id}/download")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_attachment_upload_requires_access_to_target_record(): void
    {
        Storage::fake('local');
        $this->seed(CoreAccessSeeder::class);

        $uploader = User::factory()->create();
        $uploader->givePermissionTo('files.manage');
        $employee = Employee::create([
            'first_name' => 'Private',
            'last_name' => 'Employee',
        ]);

        Passport::actingAs($uploader, ['files:write'], 'api');

        $this->withHeader('Idempotency-Key', 'attachment-target-denied-1')
            ->postJson('/api/v1/attachments', [
                'attachable_type' => 'employees',
                'attachable_id' => $employee->id,
                'file' => UploadedFile::fake()->createWithContent('private.txt', 'private'),
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_attachment_upload_rejects_missing_target_without_storing_a_file(): void
    {
        Storage::fake('local');
        $uploader = $this->actingAsRole(SystemRole::HrManager, ['files:write']);
        Passport::actingAs($uploader, ['files:write'], 'api');

        $this->withHeader('Idempotency-Key', 'attachment-missing-target-1')
            ->postJson('/api/v1/attachments', [
                'file' => UploadedFile::fake()->createWithContent('orphan.txt', 'must not persist'),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.fields.attachable_type.0', 'The attachable type field is required.')
            ->assertJsonPath('error.fields.attachable_id.0', 'The attachable id field is required.');

        $this->assertDatabaseCount('attachments', 0);
        Storage::disk('local')->assertDirectoryEmpty('attachments');
    }

    public function test_pto_request_approval_tracks_balance(): void
    {
        $employeeUser = $this->actingAsRole(SystemRole::Employee, ['pto:write']);
        $policy = PtoPolicy::first();
        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'first_name' => 'Sam',
            'last_name' => 'Rivera',
        ]);

        $response = $this->withHeader('Idempotency-Key', 'pto-create-1')
            ->postJson('/api/v1/pto-requests', [
                'employee_id' => $employee->id,
                'pto_policy_id' => $policy->id,
                'starts_at' => '2026-08-01 09:00:00',
                'ends_at' => '2026-08-01 17:00:00',
                'days' => 1,
                'reason' => 'Family day',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $manager = $this->actingAsRole(SystemRole::Manager, ['pto:write']);
        $managerEmployee = Employee::create([
            'user_id' => $manager->id,
            'first_name' => 'Mara',
            'last_name' => 'Manager',
        ]);
        $employee->update(['manager_id' => $managerEmployee->id]);

        $this->withHeader('Idempotency-Key', 'pto-approve-1')
            ->postJson('/api/v1/pto-requests/'.$response->json('data.id').'/approve', [
                'decision_notes' => 'Approved',
            ])
            ->assertOk()
            ->assertJsonPath('data.approver_id', $manager->id)
            ->assertJsonPath('data.status', 'approved');

        $balance = PtoBalance::first();

        $this->assertSame('0.25', $balance->available_days);
        $this->assertSame('0.00', $balance->pending_days);
        $this->assertSame('1.00', $balance->used_days);
        $this->assertSame('2026-08-01', $balance->period_start->toDateString());
        $this->assertSame('2026-08-31', $balance->period_end->toDateString());

        Passport::actingAs($manager, ['pto:read'], 'api');

        $this->getJson('/api/v1/pto-balances')
            ->assertOk()
            ->assertJsonPath('data.0.employee_id', $employee->id)
            ->assertJsonPath('data.0.remaining_days', '0.25');
    }

    public function test_pto_manager_access_is_limited_to_direct_reports(): void
    {
        $this->seed(CoreAccessSeeder::class);

        $manager = User::factory()->create();
        $manager->assignRole(SystemRole::Manager->value);
        $managerEmployee = Employee::create([
            'user_id' => $manager->id,
            'first_name' => 'Report',
            'last_name' => 'Manager',
        ]);
        $directReport = Employee::create([
            'manager_id' => $managerEmployee->id,
            'first_name' => 'Direct',
            'last_name' => 'Report',
        ]);
        $otherEmployee = Employee::create([
            'first_name' => 'Other',
            'last_name' => 'Employee',
        ]);
        $policy = PtoPolicy::firstOrFail();
        $directRequest = PtoRequest::create([
            'employee_id' => $directReport->id,
            'pto_policy_id' => $policy->id,
            'starts_at' => '2026-08-10 09:00:00',
            'ends_at' => '2026-08-10 17:00:00',
            'days' => 1,
            'status' => 'pending',
            'reason' => 'Direct report day',
        ]);
        $otherRequest = PtoRequest::create([
            'employee_id' => $otherEmployee->id,
            'pto_policy_id' => $policy->id,
            'starts_at' => '2026-08-11 09:00:00',
            'ends_at' => '2026-08-11 17:00:00',
            'days' => 1,
            'status' => 'pending',
            'reason' => 'Unrelated day',
        ]);
        PtoBalance::create([
            'employee_id' => $directReport->id,
            'pto_policy_id' => $policy->id,
            'available_days' => 15,
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
        ]);
        PtoBalance::create([
            'employee_id' => $otherEmployee->id,
            'pto_policy_id' => $policy->id,
            'available_days' => 15,
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
        ]);

        Passport::actingAs($manager, ['pto:read', 'pto:write'], 'api');

        $response = $this->getJson('/api/v1/pto-requests')
            ->assertOk()
            ->assertJsonPath('data.0.reason', 'Direct report day');

        $this->assertCount(1, $response->json('data'));

        $this->getJson('/api/v1/pto-balances')
            ->assertOk()
            ->assertJsonPath('data.0.employee_id', $directReport->id);

        $this->withHeader('Idempotency-Key', 'pto-report-approve-1')
            ->postJson('/api/v1/pto-requests/'.$directRequest->id.'/approve', [
                'decision_notes' => 'Approved by manager',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->withHeader('Idempotency-Key', 'pto-other-approve-1')
            ->postJson('/api/v1/pto-requests/'.$otherRequest->id.'/approve', [
                'decision_notes' => 'Not my report',
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_pto_request_cannot_exceed_remaining_balance(): void
    {
        $employeeUser = $this->actingAsRole(SystemRole::Employee, ['pto:write']);
        $policy = PtoPolicy::first();
        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'first_name' => 'Balance',
            'last_name' => 'Guard',
        ]);

        $this->withHeader('Idempotency-Key', 'pto-overdraw-1')
            ->postJson('/api/v1/pto-requests', [
                'employee_id' => $employee->id,
                'pto_policy_id' => $policy->id,
                'starts_at' => '2026-08-01 09:00:00',
                'ends_at' => '2026-08-20 17:00:00',
                'days' => 2,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertDatabaseCount('pto_requests', 0);
        $this->assertDatabaseCount('pto_balances', 0);
    }

    public function test_pto_requests_can_be_filtered_for_review(): void
    {
        $this->actingAsRole(SystemRole::OwnerAdmin, ['pto:read']);

        $policy = PtoPolicy::firstOrFail();
        $employee = Employee::create([
            'first_name' => 'Filter',
            'last_name' => 'Requester',
            'status' => 'active',
        ]);
        $otherEmployee = Employee::create([
            'first_name' => 'Other',
            'last_name' => 'Requester',
            'status' => 'active',
        ]);
        PtoRequest::create([
            'employee_id' => $employee->id,
            'pto_policy_id' => $policy->id,
            'starts_at' => '2026-08-05 09:00:00',
            'ends_at' => '2026-08-05 17:00:00',
            'days' => 1,
            'status' => 'pending',
            'reason' => 'Filtered family day',
        ]);
        PtoRequest::create([
            'employee_id' => $otherEmployee->id,
            'pto_policy_id' => $policy->id,
            'starts_at' => '2026-09-05 09:00:00',
            'ends_at' => '2026-09-05 17:00:00',
            'days' => 1,
            'status' => 'approved',
            'reason' => 'Other day',
        ]);

        $response = $this->getJson('/api/v1/pto-requests?status=pending&employee_id='.$employee->id.'&pto_policy_id='.$policy->id.'&starts_from=2026-08-01&starts_until=2026-08-31')
            ->assertOk()
            ->assertJsonPath('data.0.reason', 'Filtered family day');

        $this->assertCount(1, $response->json('data'));
    }

    public function test_pto_policies_can_be_managed_through_api(): void
    {
        $this->actingAsRole(SystemRole::HrManager, ['pto:read', 'pto:write']);

        $defaultPolicy = PtoPolicy::firstOrFail();

        $created = $this->withHeader('Idempotency-Key', 'pto-policy-create-1')
            ->postJson('/api/v1/pto-policies', [
                'name' => 'Flexible PTO',
                'annual_allowance_days' => 20,
                'accrual_type' => 'monthly_accrual',
                'accumulation_frequency' => 'monthly',
                'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'holidays' => ['2026-01-01'],
                'allow_negative_balance' => true,
                'carryover_days' => 3,
                'approval_strategy' => 'manager_only',
                'is_default' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Flexible PTO')
            ->assertJsonPath('data.allow_negative_balance', true)
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse($defaultPolicy->fresh()->is_default);

        $this->getJson('/api/v1/pto-policies')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Flexible PTO');

        $this->putJson('/api/v1/pto-policies/'.$created->json('data.id'), [
            'name' => 'Flexible PTO',
            'annual_allowance_days' => 21,
            'accrual_type' => 'annual_grant',
            'accumulation_frequency' => 'biweekly',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday'],
            'holidays' => [],
            'allow_negative_balance' => false,
            'carryover_days' => 4,
            'approval_strategy' => 'hr_only',
            'is_default' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.annual_allowance_days', '21.00')
            ->assertJsonPath('data.accumulation_frequency', 'biweekly')
            ->assertJsonPath('data.working_days.3', 'thursday')
            ->assertJsonPath('data.allow_negative_balance', false)
            ->assertJsonPath('data.approval_strategy', 'hr_only');

        $this->assertDatabaseHas('audit_logs', ['event' => 'pto_policy.created']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'pto_policy.updated']);
    }

    public function test_pto_policy_writes_require_manage_permission(): void
    {
        $this->actingAsRole(SystemRole::Employee, ['pto:read', 'pto:write']);

        $this->getJson('/api/v1/pto-policies')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Default PTO');

        $this->withHeader('Idempotency-Key', 'pto-policy-denied-1')
            ->postJson('/api/v1/pto-policies', [
                'name' => 'Denied PTO',
                'annual_allowance_days' => 5,
                'accrual_type' => 'manual',
                'accumulation_frequency' => 'monthly',
                'working_days' => ['monday'],
                'holidays' => [],
                'carryover_days' => 0,
                'approval_strategy' => 'hr_only',
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_knowledge_assets_and_attachments_work_through_policy_checked_api(): void
    {
        Storage::fake('local');
        $this->actingAsRole(SystemRole::OwnerAdmin, [
            'knowledge:read',
            'knowledge:write',
            'assets:read',
            'assets:write',
            'files:read',
            'files:write',
        ]);

        $article = $this->withHeader('Idempotency-Key', 'kb-create-1')
            ->postJson('/api/v1/knowledge-articles', [
                'title' => 'Laptop setup',
                'body_markdown' => '# Setup',
                'status' => 'published',
                'tags' => ['it', 'onboarding'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'laptop-setup')
            ->json('data');

        $asset = $this->withHeader('Idempotency-Key', 'asset-create-1')
            ->postJson('/api/v1/assets', [
                'name' => 'MacBook Pro',
                'tags' => ['Laptop', 'Onboarding'],
                'serial_number' => 'SERIAL-001',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', AssetStatus::Available->value)
            ->assertJsonPath('data.tags.0', 'Laptop')
            ->json('data');

        $this->withHeader('Idempotency-Key', 'attachment-create-1')
            ->postJson('/api/v1/attachments', [
                'attachable_type' => 'knowledge_articles',
                'attachable_id' => $article['id'],
                'file' => UploadedFile::fake()->create('setup.md', 4, 'text/markdown'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.original_name', 'setup.md');

        $this->assertDatabaseHas('assets', ['id' => $asset['id'], 'category' => 'Laptop']);
        $this->assertDatabaseCount('attachments', 1);
        $this->assertInstanceOf(KnowledgeArticle::class, KnowledgeArticle::first());
        $this->assertInstanceOf(Asset::class, Asset::first());
        $this->assertInstanceOf(Attachment::class, Attachment::first());
    }

    public function test_knowledge_articles_can_be_filtered_for_agent_retrieval(): void
    {
        $this->actingAsRole(SystemRole::OwnerAdmin, ['knowledge:read']);

        KnowledgeArticle::create([
            'title' => 'VPN setup',
            'slug' => 'vpn-setup',
            'body_markdown' => '# VPN setup'.PHP_EOL.'WireGuard steps for remote workers.',
            'status' => 'published',
            'category' => 'IT',
            'tags' => ['it', 'remote'],
        ]);
        KnowledgeArticle::create([
            'title' => 'Payroll SOP',
            'slug' => 'payroll-sop',
            'body_markdown' => '# Payroll',
            'status' => 'published',
            'category' => 'Finance',
            'tags' => ['finance'],
        ]);
        KnowledgeArticle::create([
            'title' => 'Draft IT note',
            'slug' => 'draft-it-note',
            'body_markdown' => '# Draft',
            'status' => 'draft',
            'category' => 'IT',
            'tags' => ['it'],
        ]);

        $response = $this->getJson('/api/v1/knowledge-articles?q=wireguard&status=published&category=IT&tag=remote')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'VPN setup');

        $this->assertCount(1, $response->json('data'));

        $tagged = $this->getJson('/api/v1/knowledge-articles?tag=finance')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Payroll SOP');

        $this->assertCount(1, $tagged->json('data'));
    }

    public function test_assets_can_be_filtered_for_inventory_retrieval(): void
    {
        $this->actingAsRole(SystemRole::OwnerAdmin, ['assets:read']);

        $employee = Employee::create([
            'first_name' => 'Casey',
            'last_name' => 'Ops',
            'status' => 'active',
        ]);
        $assigned = Asset::create([
            'asset_tag' => 'LAP-OPS-001',
            'name' => 'Operations laptop',
            'category' => 'Laptop',
            'tags' => ['Laptop', 'Operations'],
            'serial_number' => 'OPS-SERIAL',
            'status' => AssetStatus::Assigned,
            'vendor' => 'Apple',
            'currency' => 'USD',
        ]);
        $assigned->assignments()->create([
            'employee_id' => $employee->id,
            'assigned_at' => now(),
        ]);
        Asset::create([
            'asset_tag' => 'MON-001',
            'name' => 'Warehouse monitor',
            'category' => 'Monitor',
            'tags' => ['Monitor'],
            'status' => AssetStatus::Available,
            'currency' => 'USD',
        ]);

        $response = $this->getJson('/api/v1/assets?q=operations&status=assigned&tag=Laptop&assigned_to='.$employee->id)
            ->assertOk()
            ->assertJsonPath('data.0.asset_tag', 'LAP-OPS-001')
            ->assertJsonPath('data.0.tags.0', 'Laptop');

        $this->assertCount(1, $response->json('data'));
    }

    public function test_asset_history_can_be_managed_through_api(): void
    {
        $this->actingAsRole(SystemRole::OwnerAdmin, ['assets:read', 'assets:write']);

        $employee = Employee::create([
            'first_name' => 'History',
            'last_name' => 'Holder',
            'status' => 'active',
        ]);
        $asset = Asset::create([
            'asset_tag' => 'HIST-001',
            'name' => 'History laptop',
            'status' => AssetStatus::Available,
            'currency' => 'USD',
        ]);

        $assignResponse = $this->withHeader('Idempotency-Key', 'asset-history-assign-1')
            ->postJson('/api/v1/assets/'.$asset->id.'/assign', [
                'employee_id' => $employee->id,
                'condition' => 'Mint condition',
                'notes' => 'Employee received the laptop in mint condition.',
            ]);

        $assignResponse
            ->assertSuccessful()
            ->assertJsonPath('data.current_holder.id', $employee->id)
            ->assertJsonPath('data.photo.has_photo', false);

        $this->assertDatabaseHas('asset_events', [
            'asset_id' => $asset->id,
            'type' => 'assigned',
            'employee_id' => $employee->id,
            'condition' => 'Mint condition',
        ]);

        $historyResponse = $this->withHeader('Idempotency-Key', 'asset-history-note-1')
            ->postJson('/api/v1/assets/'.$asset->id.'/history', [
                'type' => 'delivered',
                'employee_id' => $employee->id,
                'condition' => 'Mint condition',
                'notes' => 'Delivered in mint condition with charger.',
                'metadata' => ['source' => 'api-test'],
            ]);

        $historyResponse
            ->assertSuccessful()
            ->assertJsonPath('data.type', 'delivered')
            ->assertJsonPath('data.employee.id', $employee->id)
            ->assertJsonPath('data.metadata.source', 'api-test');

        $history = $this->getJson('/api/v1/assets/'.$asset->id.'/history')
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $history);
        $this->assertContains('Delivered in mint condition with charger.', collect($history)->pluck('notes')->all());

        $returnResponse = $this->withHeader('Idempotency-Key', 'asset-history-return-1')
            ->postJson('/api/v1/assets/'.$asset->id.'/return', [
                'condition' => 'Good condition',
                'notes' => 'Returned during offboarding.',
            ]);

        $returnResponse
            ->assertSuccessful()
            ->assertJsonPath('data.current_holder', null);

        $this->assertDatabaseHas('asset_events', [
            'asset_id' => $asset->id,
            'type' => 'returned',
            'from_employee_id' => $employee->id,
            'condition' => 'Good condition',
        ]);
    }

    public function test_idempotency_conflict_detects_different_file_upload_payloads(): void
    {
        Storage::fake('local');
        $this->actingAsRole(SystemRole::OwnerAdmin, [
            'knowledge:write',
            'files:write',
        ]);

        $article = $this->withHeader('Idempotency-Key', 'kb-file-conflict-article')
            ->postJson('/api/v1/knowledge-articles', [
                'title' => 'File conflict article',
                'body_markdown' => '# Files',
            ])
            ->assertCreated()
            ->json('data');

        $this->withHeader('Idempotency-Key', 'file-conflict-1')
            ->postJson('/api/v1/attachments', [
                'attachable_type' => 'knowledge_articles',
                'attachable_id' => $article['id'],
                'file' => UploadedFile::fake()->createWithContent('first.md', '# First'),
            ])
            ->assertCreated();

        $this->withHeader('Idempotency-Key', 'file-conflict-1')
            ->postJson('/api/v1/attachments', [
                'attachable_type' => 'knowledge_articles',
                'attachable_id' => $article['id'],
                'file' => UploadedFile::fake()->createWithContent('second.md', '# Second'),
            ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'idempotency_key_conflict');
    }

    public function test_webhook_endpoints_create_deliveries_from_core_events(): void
    {
        Queue::fake();
        $this->actingAsRole(SystemRole::OwnerAdmin, ['webhooks:write', 'employees:write']);

        $this->withHeader('Idempotency-Key', 'webhook-create-1')
            ->postJson('/api/v1/webhook-endpoints', [
                'name' => 'Ops listener',
                'url' => 'https://example.test/webhooks/bolt',
                'secret' => 'a-very-long-secret-for-signing',
                'events' => ['employee.created'],
            ])
            ->assertCreated();

        $this->withHeader('Idempotency-Key', 'employee-webhook-1')
            ->postJson('/api/v1/employees', [
                'first_name' => 'Webhook',
                'last_name' => 'Target',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('webhook_deliveries', ['event' => 'employee.created']);
        Queue::assertPushed(DeliverWebhook::class);
    }

    public function test_webhook_event_catalog_is_exposed_and_subscriptions_are_validated(): void
    {
        $this->actingAsRole(SystemRole::OwnerAdmin, ['webhooks:write']);

        $this->getJson('/api/v1/webhook-events')
            ->assertOk()
            ->assertJsonPath('data.0.name', '*')
            ->assertJsonPath('data.1.name', 'employee.created');

        $response = $this->withHeader('Idempotency-Key', 'webhook-invalid-event-1')
            ->postJson('/api/v1/webhook-endpoints', [
                'name' => 'Invalid listener',
                'url' => 'https://example.test/webhooks/bolt',
                'secret' => 'a-very-long-secret-for-signing',
                'events' => ['not.real'],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertArrayHasKey('events.0', $response->json('error.fields'));
    }

    public function test_webhook_test_and_replay_api_actions_create_deliveries(): void
    {
        Queue::fake();
        $this->actingAsRole(SystemRole::OwnerAdmin, ['webhooks:write']);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Ops listener',
            'url' => 'https://example.test/webhooks/bolt',
            'secret' => 'a-very-long-secret-for-signing',
            'events' => ['employee.created'],
            'is_active' => true,
        ]);

        $testResponse = $this->withHeader('Idempotency-Key', 'webhook-test-1')
            ->postJson("/api/v1/webhook-endpoints/{$endpoint->id}/test")
            ->assertCreated()
            ->assertJsonPath('data.event', 'webhook.test')
            ->assertJsonPath('data.status', 'pending');

        $testDelivery = WebhookDelivery::findOrFail($testResponse->json('data.id'));
        $this->assertEqualsCanonicalizing([
            'webhook_endpoint_id' => $endpoint->id,
            'message' => 'BOLT webhook test delivery.',
        ], $testDelivery->payload);
        Queue::assertPushed(DeliverWebhook::class, 1);

        $replayResponse = $this->withHeader('Idempotency-Key', 'webhook-replay-1')
            ->postJson("/api/v1/webhook-deliveries/{$testDelivery->id}/replay")
            ->assertCreated()
            ->assertJsonPath('data.event', 'webhook.test')
            ->assertJsonPath('data.status', 'pending');

        $replayDelivery = WebhookDelivery::findOrFail($replayResponse->json('data.id'));
        $this->assertNotTrue($testDelivery->is($replayDelivery));
        $this->assertSame($testDelivery->payload, $replayDelivery->payload);
        Queue::assertPushed(DeliverWebhook::class, 2);
    }

    public function test_webhook_endpoints_and_deliveries_can_be_filtered_for_operations(): void
    {
        $this->actingAsRole(SystemRole::OwnerAdmin, ['webhooks:write']);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Ops receiver',
            'url' => 'https://example.test/webhooks/ops',
            'secret' => 'a-very-long-secret-for-signing',
            'events' => ['employee.created'],
            'is_active' => true,
        ]);
        WebhookEndpoint::create([
            'name' => 'Disabled receiver',
            'url' => 'https://example.test/webhooks/disabled',
            'secret' => 'a-very-long-secret-for-signing',
            'events' => ['asset.created'],
            'is_active' => false,
        ]);

        $failed = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'employee.created',
            'payload' => ['employee_id' => 1],
            'status' => 'failed',
            'attempts' => 2,
        ]);
        $failed->forceFill(['created_at' => '2026-08-10 10:00:00', 'updated_at' => '2026-08-10 10:00:00'])->save();
        $delivered = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'webhook.test',
            'payload' => ['test' => true],
            'status' => 'delivered',
        ]);
        $delivered->forceFill(['created_at' => '2026-09-10 10:00:00', 'updated_at' => '2026-09-10 10:00:00'])->save();

        $endpoints = $this->getJson('/api/v1/webhook-endpoints?q=ops&is_active=1&event=employee.created')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Ops receiver');

        $this->assertCount(1, $endpoints->json('data'));

        $deliveries = $this->getJson('/api/v1/webhook-endpoints/'.$endpoint->id.'/deliveries?status=failed&event=employee.created&created_from=2026-08-01&created_until=2026-08-31')
            ->assertOk()
            ->assertJsonPath('data.0.id', $failed->id);

        $this->assertCount(1, $deliveries->json('data'));
    }

    public function test_webhook_delivery_detail_api_is_policy_checked(): void
    {
        $this->actingAsRole(SystemRole::OwnerAdmin, ['webhooks:write']);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Detail receiver',
            'url' => 'https://example.test/webhooks/detail',
            'secret' => 'a-very-long-secret-for-signing',
            'events' => ['employee.created'],
            'is_active' => true,
        ]);
        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'employee.created',
            'payload' => ['employee_id' => 99],
            'status' => 'failed',
            'attempts' => 2,
            'response_status' => 500,
            'response_body' => '{"error":"nope"}',
            'error' => 'HTTP 500',
        ]);

        $this->getJson('/api/v1/webhook-deliveries/'.$delivery->id)
            ->assertOk()
            ->assertJsonPath('data.id', $delivery->id)
            ->assertJsonPath('data.response_body', '{"error":"nope"}')
            ->assertJsonPath('data.error', 'HTTP 500');

        $employeeUser = User::factory()->create();
        $employeeUser->assignRole(SystemRole::Employee->value);
        Passport::actingAs($employeeUser, ['webhooks:write'], 'api');

        $this->getJson('/api/v1/webhook-deliveries/'.$delivery->id)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_webhook_api_secret_preserves_blank_and_rotates_when_supplied(): void
    {
        $this->actingAsRole(SystemRole::OwnerAdmin, ['webhooks:write']);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Secret receiver',
            'url' => 'https://example.test/webhooks/secret',
            'secret' => 'original-secret-value-for-signing',
            'events' => ['employee.created'],
            'is_active' => true,
        ]);
        $originalEncryptedSecret = $endpoint->getRawOriginal('secret');

        $this->putJson('/api/v1/webhook-endpoints/'.$endpoint->id, [
            'name' => 'Secret receiver',
            'url' => 'https://example.test/webhooks/secret',
            'secret' => '',
            'events' => ['employee.created'],
            'is_active' => true,
        ])->assertOk();

        $this->assertSame($originalEncryptedSecret, $endpoint->fresh()->getRawOriginal('secret'));

        $this->putJson('/api/v1/webhook-endpoints/'.$endpoint->id, [
            'name' => 'Secret receiver',
            'url' => 'https://example.test/webhooks/secret',
            'secret' => 'rotated-secret-value-for-signing',
            'events' => ['employee.created'],
            'is_active' => true,
        ])->assertOk();

        $this->assertSame('rotated-secret-value-for-signing', $endpoint->fresh()->secret);
    }

    public function test_disabled_webhook_endpoints_cannot_test_or_replay(): void
    {
        Queue::fake();
        $this->actingAsRole(SystemRole::OwnerAdmin, ['webhooks:write']);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Disabled receiver',
            'url' => 'https://example.test/webhooks/disabled',
            'secret' => 'a-very-long-secret-for-signing',
            'events' => ['webhook.test'],
            'is_active' => false,
        ]);
        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'webhook.test',
            'payload' => ['test' => true],
            'status' => 'failed',
        ]);

        $this->withHeader('Idempotency-Key', 'disabled-test-1')
            ->postJson('/api/v1/webhook-endpoints/'.$endpoint->id.'/test')
            ->assertUnprocessable();

        $this->withHeader('Idempotency-Key', 'disabled-replay-1')
            ->postJson('/api/v1/webhook-deliveries/'.$delivery->id.'/replay')
            ->assertUnprocessable();

        Queue::assertNothingPushed();
    }

    public function test_webhook_delivery_job_signs_and_records_delivery(): void
    {
        Http::fake(['https://example.test/*' => Http::response(['ok' => true])]);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Receiver',
            'url' => 'https://example.test/webhooks/bolt',
            'secret' => 'a-very-long-secret-for-signing',
            'events' => ['webhook.test'],
            'is_active' => true,
        ]);

        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'webhook.test',
            'payload' => ['hello' => 'world'],
        ]);

        (new DeliverWebhook($delivery))->handle(app(AuditLogger::class));

        $this->assertDatabaseHas('webhook_deliveries', [
            'id' => $delivery->id,
            'status' => 'delivered',
            'response_status' => 200,
        ]);

        $this->assertDatabaseHas('audit_logs', ['event' => 'webhook.delivery_attempted']);
    }

    public function test_exception_webhook_failures_disable_endpoint_at_max_attempts(): void
    {
        config(['bolt.webhooks.max_attempts' => 2]);
        Http::fake(fn () => throw new ConnectionException('Connection failed'));

        $endpoint = WebhookEndpoint::create([
            'name' => 'Exception receiver',
            'url' => 'https://example.test/webhooks/exception',
            'secret' => 'a-very-long-secret-for-signing',
            'events' => ['webhook.test'],
            'is_active' => true,
            'failure_count' => 1,
        ]);
        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'webhook.test',
            'payload' => ['hello' => 'world'],
            'attempts' => 1,
        ]);

        (new DeliverWebhook($delivery))->handle(app(AuditLogger::class));

        $this->assertDatabaseHas('webhook_deliveries', [
            'id' => $delivery->id,
            'status' => 'failed',
            'attempts' => 2,
        ]);
        $this->assertFalse($endpoint->fresh()->is_active);
        $this->assertSame(2, $endpoint->fresh()->failure_count);
    }

    public function test_due_webhook_retry_command_dispatches_only_eligible_deliveries(): void
    {
        Queue::fake();

        $activeEndpoint = WebhookEndpoint::create([
            'name' => 'Active receiver',
            'url' => 'https://example.test/webhooks/bolt',
            'secret' => 'a-very-long-secret-for-signing',
            'events' => ['webhook.test'],
            'is_active' => true,
        ]);

        $inactiveEndpoint = WebhookEndpoint::create([
            'name' => 'Inactive receiver',
            'url' => 'https://example.test/webhooks/bolt',
            'secret' => 'a-very-long-secret-for-signing',
            'events' => ['webhook.test'],
            'is_active' => false,
        ]);

        $due = WebhookDelivery::create([
            'webhook_endpoint_id' => $activeEndpoint->id,
            'event' => 'webhook.test',
            'payload' => ['retry' => true],
            'status' => 'failed',
            'attempts' => 1,
            'next_attempt_at' => now()->subMinute(),
        ]);

        WebhookDelivery::create([
            'webhook_endpoint_id' => $activeEndpoint->id,
            'event' => 'webhook.test',
            'payload' => ['retry' => false],
            'status' => 'failed',
            'attempts' => 1,
            'next_attempt_at' => now()->addMinute(),
        ]);

        WebhookDelivery::create([
            'webhook_endpoint_id' => $inactiveEndpoint->id,
            'event' => 'webhook.test',
            'payload' => ['retry' => false],
            'status' => 'failed',
            'attempts' => 1,
            'next_attempt_at' => now()->subMinute(),
        ]);

        WebhookDelivery::create([
            'webhook_endpoint_id' => $activeEndpoint->id,
            'event' => 'webhook.test',
            'payload' => ['retry' => false],
            'status' => 'failed',
            'attempts' => config('bolt.webhooks.max_attempts'),
            'next_attempt_at' => now()->subMinute(),
        ]);

        $this->artisan('bolt:retry-webhooks')
            ->expectsOutputToContain('Dispatched 1 webhook retry deliveries.')
            ->assertSuccessful();

        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->delivery->is($due));

        $this->assertDatabaseHas('webhook_deliveries', [
            'id' => $due->id,
            'status' => 'pending',
        ]);
    }

    public function test_webhook_delivery_prune_command_uses_configured_total_limit(): void
    {
        SystemSetting::putInteger(SystemSetting::WEBHOOK_DELIVERY_HISTORY_LIMIT, 2);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Prune receiver',
            'url' => 'https://example.test/webhooks/prune',
            'secret' => 'a-very-long-secret-for-signing',
            'events' => ['webhook.test'],
            'is_active' => true,
        ]);
        $oldest = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'webhook.test',
            'payload' => ['oldest' => true],
        ]);
        $oldest->forceFill(['created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)])->save();
        $middle = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'webhook.test',
            'payload' => ['middle' => true],
        ]);
        $middle->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)])->save();
        $fresh = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'webhook.test',
            'payload' => ['fresh' => true],
        ]);

        $this->artisan('bolt:prune-webhook-deliveries --dry-run')
            ->expectsOutputToContain('Would prune 1 webhook deliveries beyond the 2 record limit.')
            ->assertSuccessful();
        $this->assertDatabaseHas('webhook_deliveries', ['id' => $oldest->id]);

        $this->artisan('bolt:prune-webhook-deliveries')
            ->expectsOutputToContain('Pruned 1 webhook deliveries beyond the 2 record limit.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('webhook_deliveries', ['id' => $oldest->id]);
        $this->assertDatabaseHas('webhook_deliveries', ['id' => $middle->id]);
        $this->assertDatabaseHas('webhook_deliveries', ['id' => $fresh->id]);
    }

    public function test_operational_log_prune_command_supports_dry_run_and_prunes_old_records(): void
    {
        config([
            'bolt.retention.audit_days' => 30,
            'bolt.retention.webhook_delivery_days' => 10,
        ]);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Retention receiver',
            'url' => 'https://example.test/webhooks/bolt',
            'secret' => 'a-very-long-secret-for-signing',
            'events' => ['webhook.test'],
            'is_active' => true,
        ]);

        $oldAudit = AuditLog::create([
            'event' => 'old.audit',
            'occurred_at' => now()->subDays(31),
        ]);
        $freshAudit = AuditLog::create([
            'event' => 'fresh.audit',
            'occurred_at' => now()->subDays(5),
        ]);
        $oldDelivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'webhook.test',
            'payload' => ['old' => true],
        ]);
        $oldDelivery->forceFill([
            'created_at' => now()->subDays(11),
            'updated_at' => now()->subDays(11),
        ])->save();

        $freshDelivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'webhook.test',
            'payload' => ['fresh' => true],
        ]);
        $freshDelivery->forceFill([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ])->save();

        $this->artisan('bolt:prune-operational-logs --dry-run')->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', ['id' => $oldAudit->id]);
        $this->assertDatabaseHas('webhook_deliveries', ['id' => $oldDelivery->id]);

        $this->artisan('bolt:prune-operational-logs')->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['id' => $oldAudit->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $freshAudit->id]);
        $this->assertDatabaseMissing('webhook_deliveries', ['id' => $oldDelivery->id]);
        $this->assertDatabaseHas('webhook_deliveries', ['id' => $freshDelivery->id]);
    }

    private function actingAsRole(SystemRole $role, array $scopes): User
    {
        $this->seed(CoreAccessSeeder::class);

        $user = User::factory()->create();
        $user->assignRole($role->value);

        Passport::actingAs($user, $scopes, 'api');

        return $user;
    }
}
