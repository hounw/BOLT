<?php

namespace Tests\Feature;

use App\Enums\SystemRole;
use App\Jobs\DeliverWebhook;
use App\Models\Asset;
use App\Models\AssetEvent;
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
use Database\Seeders\CoreAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class BoltWebUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_admin_command_creates_owner_admin(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        $this->artisan('bolt:create-local-admin', [
            '--email' => 'admin@example.test',
            '--name' => 'Admin User',
            '--password' => 'Temporary1234!',
        ])->assertSuccessful();

        $user = User::where('email', 'admin@example.test')->firstOrFail();

        $this->assertTrue($user->hasRole(SystemRole::OwnerAdmin->value));
    }

    public function test_non_local_owner_bootstrap_is_interactive_audited_and_one_time_only(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('bolt:bootstrap-owner')->assertFailed();

        $this->artisan('bolt:bootstrap-owner', ['--confirm-production' => true])
            ->expectsConfirmation('Create the first owner-admin for this deployment?', 'yes')
            ->expectsQuestion('Owner name', 'Production Owner')
            ->expectsQuestion('Owner email', 'owner@example.test')
            ->expectsQuestion('Temporary password', 'Temporary1234!')
            ->expectsQuestion('Confirm temporary password', 'Temporary1234!')
            ->assertSuccessful();

        $owner = User::where('email', 'owner@example.test')->firstOrFail();
        $this->assertTrue($owner->hasRole(SystemRole::OwnerAdmin->value));
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'access.initial_owner_created',
            'auditable_id' => $owner->id,
        ]);

        $this->artisan('bolt:bootstrap-owner', ['--confirm-production' => true])
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_update_own_password(): void
    {
        $user = $this->ownerAdmin();

        $this->actingAs($user)
            ->get(route('account.password'))
            ->assertOk()
            ->assertSee('Update your login password');

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'password',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertRedirect(route('account.password'));

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'auth.password_updated',
            'actor_id' => $user->id,
        ]);
    }

    public function test_owner_admin_can_use_employee_ui(): void
    {
        $user = $this->ownerAdmin();
        $loginUser = User::factory()->create([
            'name' => 'Linked User',
            'email' => 'linked@example.test',
        ]);

        $this->actingAs($user)
            ->get('/employees')
            ->assertOk()
            ->assertSee('Employees');

        $this->actingAs($user)
            ->post('/employees', [
                'first_name' => 'UI',
                'last_name' => 'Person',
                'user_id' => $loginUser->id,
                'status' => 'active',
                'department' => 'Ops',
            ])
            ->assertRedirect();

        $employee = Employee::firstOrFail();

        $this->assertSame($loginUser->id, $employee->user_id);

        $this->actingAs($user)
            ->get(route('employees.show', $employee))
            ->assertOk()
            ->assertSee('UI Person')
            ->assertSee('linked@example.test');

        $this->actingAs($user)
            ->post('/employees', [
                'first_name' => 'Duplicate',
                'last_name' => 'Login',
                'user_id' => $loginUser->id,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('user_id');
    }

    public function test_owner_admin_can_filter_employees_from_web_ui(): void
    {
        $user = $this->ownerAdmin();
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

        $this->actingAs($user)
            ->get(route('employees.index', [
                'q' => 'field',
                'status' => 'active',
                'department' => 'Operations',
                'manager_id' => $manager->id,
            ]))
            ->assertOk()
            ->assertSee('Avery Operator')
            ->assertSee('Morgan Manager')
            ->assertDontSee('riley@example.test')
            ->assertSee('Any status')
            ->assertSee('Any department')
            ->assertSee('Anyone');
    }

    public function test_owner_admin_can_manage_employee_hr_history_from_ui(): void
    {
        $user = $this->ownerAdmin();
        $employee = Employee::create([
            'first_name' => 'HR',
            'last_name' => 'Subject',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->post(route('employees.compensation.store', $employee), [
                'effective_date' => '2026-07-01',
                'amount' => '120000',
                'currency' => 'USD',
                'type' => 'salary',
                'notes' => 'Annual adjustment',
            ])
            ->assertRedirect(route('employees.show', $employee));

        $this->actingAs($user)
            ->post(route('employees.benefits.store', $employee), [
                'type' => 'Health stipend',
                'value' => '500',
                'starts_on' => '2026-07-01',
                'notes' => 'Monthly allowance',
            ])
            ->assertRedirect(route('employees.show', $employee));

        $this->assertDatabaseHas('compensation_histories', [
            'employee_id' => $employee->id,
            'amount' => '120000.00',
            'created_by_id' => $user->id,
        ]);
        $this->assertDatabaseHas('benefit_histories', [
            'employee_id' => $employee->id,
            'type' => 'Health stipend',
            'created_by_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('employees.show', $employee))
            ->assertOk()
            ->assertSee('Annual adjustment')
            ->assertSee('Health stipend');
    }

    public function test_owner_admin_can_filter_employee_hr_history_from_ui(): void
    {
        $user = $this->ownerAdmin();
        $employee = Employee::create([
            'first_name' => 'HR',
            'last_name' => 'Filter',
            'status' => 'active',
        ]);

        $employee->compensationHistories()->create([
            'effective_date' => '2026-01-01',
            'amount' => 90000,
            'currency' => 'USD',
            'type' => 'salary',
            'notes' => 'Base pay',
        ]);
        $employee->compensationHistories()->create([
            'effective_date' => '2026-03-01',
            'amount' => 5000,
            'currency' => 'USD',
            'type' => 'bonus',
            'notes' => 'Quarterly bonus',
        ]);
        $employee->benefitHistories()->create([
            'type' => 'Health stipend',
            'value' => 500,
            'starts_on' => '2026-02-01',
            'notes' => 'Monthly health support',
        ]);
        $employee->benefitHistories()->create([
            'type' => 'Relocation bonus',
            'value' => 2500,
            'starts_on' => '2026-04-01',
            'notes' => 'Move support',
        ]);

        $this->actingAs($user)
            ->get(route('employees.show', [
                'employee' => $employee,
                'compensation_type' => 'bonus',
                'compensation_from' => '2026-02-01',
                'compensation_until' => '2026-03-31',
                'benefit_type' => 'Health stipend',
                'benefit_from' => '2026-01-01',
                'benefit_until' => '2026-02-28',
            ]))
            ->assertOk()
            ->assertSee('Quarterly bonus')
            ->assertDontSee('Base pay')
            ->assertSee('Monthly health support')
            ->assertDontSee('Move support')
            ->assertSee('Any type');
    }

    public function test_owner_admin_can_manage_people_reference_data_from_web_ui(): void
    {
        $user = $this->ownerAdmin();

        $this->actingAs($user)
            ->get(route('departments.index'))
            ->assertOk()
            ->assertSee('Departments');

        $this->actingAs($user)
            ->post(route('departments.store'), [
                'name' => 'Operations',
                'description' => 'Field and back office operations',
                'is_active' => '1',
            ])
            ->assertRedirect(route('departments.index'));

        $department = Department::where('name', 'Operations')->firstOrFail();

        $this->actingAs($user)
            ->post(route('departments.store'), [
                'parent_id' => $department->id,
                'name' => 'Field operations',
                'description' => 'Regional work',
                'is_active' => '1',
            ])
            ->assertRedirect(route('departments.index'));

        $this->actingAs($user)
            ->put(route('departments.update', $department), [
                'name' => 'Operations Team',
                'description' => 'Updated',
                'is_active' => '1',
            ])
            ->assertRedirect(route('departments.index'));

        $this->actingAs($user)
            ->post(route('positions.store'), [
                'name' => 'Coordinator',
                'description' => 'Coordinates work',
                'is_active' => '1',
            ])
            ->assertRedirect(route('positions.index'));

        $this->actingAs($user)
            ->post(route('compensation-packages.store'), [
                'name' => 'Ops salary',
                'amount' => '65000',
                'currency' => 'USD',
                'amount_basis' => 'annual',
                'payment_frequency' => 'monthly',
                'type' => 'salary',
                'notes' => 'Standard package',
                'is_active' => '1',
            ])
            ->assertRedirect(route('compensation-packages.index'));

        $this->assertDatabaseHas('departments', ['name' => 'Operations Team']);
        $this->assertDatabaseHas('departments', ['name' => 'Field operations', 'parent_id' => $department->id]);
        $this->assertDatabaseHas('positions', ['name' => 'Coordinator']);
        $this->assertDatabaseHas('compensation_packages', [
            'name' => 'Ops salary',
            'amount' => '65000.00',
            'amount_basis' => 'annual',
            'payment_frequency' => 'monthly',
        ]);
    }

    public function test_owner_admin_can_view_department_org_chart(): void
    {
        $user = $this->ownerAdmin();
        $sales = Department::create(['name' => 'Sales', 'is_active' => true]);
        $marketing = Department::create(['parent_id' => $sales->id, 'name' => 'Marketing', 'is_active' => true]);
        $digital = Department::create(['parent_id' => $marketing->id, 'name' => 'Digital marketing', 'is_active' => true]);

        Employee::create([
            'first_name' => 'Dana',
            'last_name' => 'Chart',
            'department_id' => $digital->id,
            'department' => $digital->name,
            'title' => 'Specialist',
        ]);

        $this->actingAs($user)
            ->get(route('departments.chart'))
            ->assertOk()
            ->assertSee('Department chart')
            ->assertSee('org-chart', false)
            ->assertSee('Sales')
            ->assertSee('Marketing')
            ->assertSee('Digital marketing')
            ->assertSee('Dana Chart')
            ->assertSee('Specialist');
    }

    public function test_owner_admin_can_view_people_org_chart(): void
    {
        $user = $this->ownerAdmin();
        $department = Department::create(['name' => 'Operations', 'is_active' => true]);
        $manager = Employee::create([
            'first_name' => 'Maria',
            'last_name' => 'Manager',
            'department_id' => $department->id,
            'department' => $department->name,
            'title' => 'Director',
            'status' => 'active',
        ]);

        Employee::create([
            'manager_id' => $manager->id,
            'first_name' => 'Rafael',
            'last_name' => 'Report',
            'department_id' => $department->id,
            'department' => $department->name,
            'title' => 'Coordinator',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('employees.chart'))
            ->assertOk()
            ->assertSee('People org chart')
            ->assertSee('org-chart', false)
            ->assertSee('Maria Manager')
            ->assertSee('Rafael Report')
            ->assertSee('Director')
            ->assertSee('Coordinator');
    }

    public function test_owner_admin_can_upload_and_view_employee_photo(): void
    {
        Storage::fake('local');

        $user = $this->ownerAdmin();

        $this->actingAs($user)
            ->post(route('employees.store'), [
                'first_name' => 'Photo',
                'last_name' => 'Person',
                'status' => 'active',
                'photo' => UploadedFile::fake()->image('avatar.jpg', 64, 64),
            ])
            ->assertRedirect();

        $employee = Employee::where('first_name', 'Photo')->firstOrFail();

        $this->assertNotNull($employee->photo_path);
        Storage::disk('local')->assertExists($employee->photo_path);

        $this->actingAs($user)
            ->get(route('employees.photo', $employee))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('employees.show', $employee))
            ->assertOk()
            ->assertSee(route('employees.photo', $employee), false);
    }

    public function test_department_parent_cannot_create_cycle_from_web_ui(): void
    {
        $user = $this->ownerAdmin();
        $sales = Department::create(['name' => 'Sales', 'is_active' => true]);
        $marketing = Department::create(['parent_id' => $sales->id, 'name' => 'Marketing', 'is_active' => true]);

        $this->actingAs($user)
            ->put(route('departments.update', $sales), [
                'parent_id' => $marketing->id,
                'name' => 'Sales',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_owner_admin_can_quick_create_people_reference_data_for_employee_form(): void
    {
        $user = $this->ownerAdmin();

        $this->actingAs($user)
            ->postJson(route('departments.store'), [
                'name' => 'Support',
                'description' => 'Customer support',
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Support')
            ->assertJsonPath('data.path', 'Support');

        $this->actingAs($user)
            ->postJson(route('positions.store'), [
                'name' => 'Support Lead',
                'description' => 'Leads support',
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Support Lead');

        $this->actingAs($user)
            ->postJson(route('compensation-packages.store'), [
                'name' => 'Support salary',
                'amount' => 64000,
                'currency' => 'USD',
                'amount_basis' => 'annual',
                'payment_frequency' => 'monthly',
                'type' => 'salary',
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Support salary')
            ->assertJsonPath('data.option_label', 'Support salary - USD 64000.00 Per year - Monthly');
    }

    public function test_owner_admin_can_onboard_employee_with_user_compensation_pto_and_private_hr_details(): void
    {
        $user = $this->ownerAdmin();
        $department = Department::create(['name' => 'Operations', 'is_active' => true]);
        $position = Position::create(['name' => 'Coordinator', 'is_active' => true]);
        $package = CompensationPackage::create([
            'name' => 'Coordinator salary',
            'amount' => 72000,
            'currency' => 'USD',
            'amount_basis' => 'annual',
            'payment_frequency' => 'monthly',
            'type' => 'salary',
            'notes' => 'Annual base pay',
            'is_active' => true,
        ]);
        $policy = PtoPolicy::firstOrFail();

        $this->actingAs($user)
            ->post(route('employees.store'), [
                'first_name' => 'Onboard',
                'last_name' => 'Person',
                'work_email' => 'onboard@example.test',
                'employee_number' => 'EMP-ON-001',
                'department_id' => $department->id,
                'position_id' => $position->id,
                'status' => 'active',
                'login_user_mode' => 'create',
                'new_user_password' => 'Webdev33##',
                'new_user_password_confirmation' => 'Webdev33##',
                'new_user_roles' => ['employee'],
                'start_date' => '2026-07-01',
                'personal_email' => 'personal@example.test',
                'phone' => '555-0100',
                'emergency_contact' => [
                    'name' => 'Emergency Contact',
                    'relationship' => 'Sibling',
                    'phone' => '555-0101',
                ],
                'private_hr_data' => [
                    'address_line_1' => '123 Main St',
                    'city' => 'San Juan',
                    'tax_id' => 'TAX-123',
                    'government_id' => 'GOV-123',
                    'medical_notes' => 'Peanut allergy',
                ],
                'compensation_package_id' => $package->id,
                'compensation_effective_date' => '2026-07-01',
                'starting_pto_policy_id' => $policy->id,
                'starting_pto_available_days' => 3,
            ])
            ->assertRedirect();

        $employee = Employee::where('work_email', 'onboard@example.test')->firstOrFail();
        $loginUser = User::where('email', 'onboard@example.test')->firstOrFail();

        $this->assertSame($loginUser->id, $employee->user_id);
        $this->assertSame($department->id, $employee->department_id);
        $this->assertSame('Operations', $employee->department);
        $this->assertSame($position->id, $employee->position_id);
        $this->assertSame('Coordinator', $employee->title);
        $this->assertSame('Onboard Person', $loginUser->name);
        $this->assertTrue($loginUser->hasRole(SystemRole::Employee->value));
        $this->assertSame('TAX-123', $employee->private_hr_data['tax_id']);
        $this->assertDatabaseHas('compensation_histories', [
            'employee_id' => $employee->id,
            'amount' => '72000.00',
            'type' => 'salary',
            'notes' => "Package: Coordinator salary (USD 72000.00 Per year, Monthly)\nAnnual base pay",
        ]);
        $startingBalance = PtoBalance::where('employee_id', $employee->id)->where('pto_policy_id', $policy->id)->firstOrFail();

        $this->assertSame('3.00', $startingBalance->available_days);
        $this->assertSame('2026-07-01', $startingBalance->period_start->toDateString());
        $this->assertSame('2026-12-31', $startingBalance->period_end->toDateString());

        $this->actingAs($user)
            ->get(route('employees.show', $employee))
            ->assertOk()
            ->assertSee('Private HR details')
            ->assertSee('Peanut allergy')
            ->assertSee('PTO balances')
            ->assertSee('Coordinator salary');

        $this->actingAs($user)
            ->get(route('employees.edit', $employee))
            ->assertOk()
            ->assertSee('Create user')
            ->assertSee('Private HR details')
            ->assertSee('Coordinator');
    }

    public function test_employee_manager_without_access_management_cannot_link_or_create_login_users_from_employee_form(): void
    {
        $this->seed(CoreAccessSeeder::class);

        $hrUser = User::factory()->create();
        $hrUser->assignRole(SystemRole::HrManager->value);
        $targetUser = User::factory()->create();

        $this->actingAs($hrUser)
            ->post(route('employees.store'), [
                'first_name' => 'No',
                'last_name' => 'Access',
                'status' => 'active',
                'login_user_mode' => 'existing',
                'user_id' => $targetUser->id,
            ])
            ->assertSessionHasErrors(['login_user_mode', 'user_id']);

        $this->assertDatabaseMissing('employees', ['first_name' => 'No', 'last_name' => 'Access']);
    }

    public function test_private_hr_details_are_hidden_from_auditor_web_ui(): void
    {
        $owner = $this->ownerAdmin();
        $employee = Employee::create([
            'first_name' => 'Private',
            'last_name' => 'Subject',
            'status' => 'active',
            'private_hr_data' => ['tax_id' => 'SECRET-TAX'],
            'emergency_contact' => ['name' => 'Secret Contact'],
        ]);

        $auditor = User::factory()->create();
        $auditor->assignRole(SystemRole::Auditor->value);

        $this->actingAs($owner)
            ->get(route('employees.show', $employee))
            ->assertOk()
            ->assertSee('SECRET-TAX')
            ->assertSee('Secret Contact');

        $this->actingAs($auditor)
            ->get(route('employees.show', $employee))
            ->assertOk()
            ->assertDontSee('Private HR details')
            ->assertDontSee('SECRET-TAX')
            ->assertDontSee('Secret Contact');
    }

    public function test_owner_admin_can_manage_user_access_from_ui(): void
    {
        $owner = $this->ownerAdmin();
        $user = User::factory()->create([
            'name' => 'Ops User',
            'email' => 'ops@example.test',
        ]);
        $employee = Employee::create([
            'first_name' => 'Ops',
            'last_name' => 'Employee',
            'work_email' => 'ops@example.test',
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->get(route('access.users.index'))
            ->assertOk()
            ->assertSee('Users &amp; roles', false)
            ->assertSee('ops@example.test');

        $this->actingAs($owner)
            ->put(route('access.users.update', $user), [
                'roles' => [SystemRole::Manager->value, SystemRole::Auditor->value],
                'employee_id' => $employee->id,
            ])
            ->assertRedirect(route('access.users.index'));

        $user->refresh();
        $employee->refresh();

        $this->assertTrue($user->hasRole(SystemRole::Manager->value));
        $this->assertTrue($user->hasRole(SystemRole::Auditor->value));
        $this->assertSame($user->id, $employee->user_id);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'access.user_updated',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);

        $this->actingAs($owner)
            ->get(route('access.users.index', [
                'q' => 'ops',
                'role' => SystemRole::Manager->value,
                'employee_link' => 'linked',
            ]))
            ->assertOk()
            ->assertSee('ops@example.test')
            ->assertSee('Ops Employee')
            ->assertDontSee($owner->email)
            ->assertSee('Any role')
            ->assertSee('Any link');
    }

    public function test_owner_admin_can_create_login_user_from_access_ui(): void
    {
        $owner = $this->ownerAdmin();
        $employee = Employee::create([
            'first_name' => 'New',
            'last_name' => 'Login',
            'work_email' => 'new-login@example.test',
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->get(route('access.users.create'))
            ->assertOk()
            ->assertSee('New user')
            ->assertSee('New Login');

        $this->actingAs($owner)
            ->post(route('access.users.store'), [
                'name' => 'New Login',
                'email' => 'new-login@example.test',
                'password' => 'Temporary1234!',
                'password_confirmation' => 'Temporary1234!',
                'roles' => [SystemRole::Employee->value],
                'employee_id' => $employee->id,
            ])
            ->assertRedirect(route('access.users.index'));

        $user = User::where('email', 'new-login@example.test')->firstOrFail();
        $employee->refresh();

        $this->assertTrue($user->hasRole(SystemRole::Employee->value));
        $this->assertSame($user->id, $employee->user_id);
        $this->assertNotSame('Temporary1234!', $user->password);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'access.user_created',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
        $auditLog = AuditLog::where('event', 'access.user_created')->firstOrFail();
        $this->assertArrayNotHasKey('password', $auditLog->new_values);
    }

    public function test_owner_admin_can_create_and_revoke_api_token_from_access_ui(): void
    {
        $owner = $this->ownerAdmin();
        $apiUser = User::factory()->create([
            'name' => 'Agent Client',
            'email' => 'agent@example.test',
        ]);
        $apiUser->assignRole(SystemRole::ApiClient->value);

        $this->actingAs($owner)
            ->get(route('access.tokens.index'))
            ->assertOk()
            ->assertSee('API tokens')
            ->assertSee('knowledge:read');

        $response = $this->actingAs($owner)
            ->post(route('access.tokens.store'), [
                'user_id' => $apiUser->id,
                'name' => 'MCP reader',
                'scopes' => ['knowledge:read'],
            ])
            ->assertRedirect(route('access.tokens.index'))
            ->assertSessionHas('plain_api_token');

        $plainToken = $response->baseResponse->getSession()->get('plain_api_token');

        $this->assertDatabaseHas('oauth_access_tokens', [
            'user_id' => $apiUser->id,
            'name' => 'MCP reader',
            'revoked' => false,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'api_token.created',
            'actor_id' => $owner->id,
        ]);

        $this->withToken($plainToken)
            ->getJson('/api/v1/knowledge-articles')
            ->assertOk();

        $tokenId = Passport::token()->newQuery()->where('name', 'MCP reader')->value('id');

        $this->actingAs($owner)
            ->put(route('access.tokens.revoke', $tokenId))
            ->assertRedirect(route('access.tokens.index'));

        $this->assertDatabaseHas('oauth_access_tokens', [
            'id' => $tokenId,
            'revoked' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'api_token.revoked',
            'actor_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('access.tokens.index', [
                'q' => 'mcp',
                'user_id' => $apiUser->id,
                'status' => 'revoked',
                'scope' => 'knowledge:read',
            ]))
            ->assertOk()
            ->assertSee('MCP reader')
            ->assertSee('agent@example.test')
            ->assertSee('Revoked')
            ->assertSee('Any user')
            ->assertSee('Any scope');

        $this->withToken($plainToken)
            ->getJson('/api/v1/knowledge-articles')
            ->assertUnauthorized();
    }

    public function test_access_user_creation_requires_strong_password_and_role(): void
    {
        $owner = $this->ownerAdmin();

        $this->actingAs($owner)
            ->post(route('access.users.store'), [
                'name' => 'Weak User',
                'email' => 'weak@example.test',
                'password' => 'password',
                'password_confirmation' => 'password',
                'roles' => [],
            ])
            ->assertSessionHasErrors(['password', 'roles']);
    }

    public function test_access_ui_prevents_last_owner_admin_demotion(): void
    {
        $owner = $this->ownerAdmin();

        $this->actingAs($owner)
            ->put(route('access.users.update', $owner), [
                'roles' => [SystemRole::Auditor->value],
            ])
            ->assertSessionHasErrors('roles');

        $this->assertTrue($owner->fresh()->hasRole(SystemRole::OwnerAdmin->value));
    }

    public function test_user_without_access_permission_cannot_manage_user_access(): void
    {
        $this->seed(CoreAccessSeeder::class);

        $manager = User::factory()->create();
        $manager->assignRole(SystemRole::Manager->value);

        $target = User::factory()->create();

        $this->actingAs($manager)
            ->get(route('access.users.index'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Users &amp; roles', false);

        $this->actingAs($manager)
            ->put(route('access.users.update', $target), [
                'roles' => [SystemRole::Auditor->value],
            ])
            ->assertForbidden();
    }

    public function test_owner_admin_can_upload_and_download_employee_attachment_from_ui(): void
    {
        Storage::fake('local');

        $user = $this->ownerAdmin();
        $employee = Employee::create([
            'first_name' => 'File',
            'last_name' => 'Subject',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->post(route('attachments.store'), [
                'attachable_type' => 'employees',
                'attachable_id' => $employee->id,
                'file' => UploadedFile::fake()->create('handbook.pdf', 8, 'application/pdf'),
            ])
            ->assertRedirect();

        $attachment = Attachment::firstOrFail();

        Storage::disk('local')->assertExists($attachment->path);
        $this->assertDatabaseHas('attachments', [
            'attachable_type' => $employee->getMorphClass(),
            'attachable_id' => $employee->id,
            'original_name' => 'handbook.pdf',
            'uploaded_by_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('employees.show', $employee))
            ->assertOk()
            ->assertSee('handbook.pdf');

        $this->actingAs($user)
            ->get(route('attachments.download', $attachment))
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'attachment.downloaded',
            'auditable_id' => $attachment->id,
        ]);
    }

    public function test_owner_admin_can_filter_audit_logs_from_web_ui(): void
    {
        $user = $this->ownerAdmin();
        $employee = Employee::create([
            'first_name' => 'Audit',
            'last_name' => 'Target',
            'status' => 'active',
        ]);
        $otherEmployee = Employee::create([
            'first_name' => 'Other',
            'last_name' => 'Target',
            'status' => 'active',
        ]);

        AuditLog::create([
            'actor_id' => $user->id,
            'event' => 'employee.updated',
            'auditable_type' => Employee::class,
            'auditable_id' => $employee->id,
            'occurred_at' => '2026-08-10 12:00:00',
        ]);
        AuditLog::create([
            'actor_id' => $user->id,
            'event' => 'employee.created',
            'auditable_type' => Employee::class,
            'auditable_id' => $otherEmployee->id,
            'occurred_at' => '2026-09-10 12:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('audit.index', [
                'event' => 'employee.updated',
                'actor_id' => $user->id,
                'auditable_type' => Employee::class,
                'auditable_id' => $employee->id,
                'occurred_from' => '2026-08-01',
                'occurred_until' => '2026-08-31',
            ]))
            ->assertOk()
            ->assertSee('employee.updated')
            ->assertSee('Employee #'.$employee->id)
            ->assertDontSee('Employee #'.$otherEmployee->id)
            ->assertSee('Any event')
            ->assertSee('Anyone')
            ->assertSee('Any target');
    }

    public function test_employee_can_submit_and_cancel_own_pto_request_from_ui(): void
    {
        $this->seed(CoreAccessSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(SystemRole::Employee->value);
        $employee = Employee::create([
            'user_id' => $user->id,
            'first_name' => 'PTO',
            'last_name' => 'Requester',
            'status' => 'active',
        ]);
        $otherEmployee = Employee::create([
            'first_name' => 'Other',
            'last_name' => 'Person',
            'status' => 'active',
        ]);
        $policy = PtoPolicy::firstOrFail();

        $this->actingAs($user)
            ->post(route('pto.store'), [
                'employee_id' => $otherEmployee->id,
                'pto_policy_id' => $policy->id,
                'starts_at' => '2026-08-03 09:00:00',
                'ends_at' => '2026-08-03 17:00:00',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('pto.store'), [
                'employee_id' => $employee->id,
                'pto_policy_id' => $policy->id,
                'starts_at' => '2026-08-04 09:00:00',
                'ends_at' => '2026-08-04 17:00:00',
                'reason' => 'Family day',
            ])
            ->assertRedirect(route('pto.index'));

        $ptoRequest = PtoRequest::firstOrFail();
        $balance = PtoBalance::firstOrFail();

        $this->assertSame('pending', $ptoRequest->status->value);
        $this->assertSame('1.25', $balance->available_days);
        $this->assertSame('1.00', $balance->pending_days);
        $this->assertSame('2026-08-01', $balance->period_start->toDateString());

        $this->actingAs($user)
            ->get(route('pto.index'))
            ->assertOk()
            ->assertSee('0.25')
            ->assertSee('Family day')
            ->assertDontSee('Other Person');

        $this->actingAs($user)
            ->post(route('pto.cancel', $ptoRequest), [
                'decision_notes' => 'Plans changed',
            ])
            ->assertRedirect(route('pto.index'));

        $this->assertSame('canceled', $ptoRequest->fresh()->status->value);
        $this->assertSame('1.25', $balance->fresh()->available_days);
        $this->assertSame('0.00', $balance->fresh()->pending_days);
    }

    public function test_pto_request_validation_preserves_input_and_blocks_overdraws(): void
    {
        $this->seed(CoreAccessSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(SystemRole::Employee->value);
        $employee = Employee::create([
            'user_id' => $user->id,
            'first_name' => 'Careful',
            'last_name' => 'Requester',
            'status' => 'active',
        ]);
        $policy = PtoPolicy::firstOrFail();

        $this->actingAs($user)
            ->get(route('pto.create'))
            ->assertOk()
            ->assertSee('Calculated PTO');

        $this->actingAs($user)
            ->from(route('pto.create'))
            ->post(route('pto.store'), [
                'employee_id' => $employee->id,
                'pto_policy_id' => $policy->id,
                'starts_at' => '2026-08-07 09:00:00',
                'ends_at' => '2026-08-06 17:00:00',
                'reason' => 'Keep this reason',
            ])
            ->assertRedirect(route('pto.create'))
            ->assertSessionHasErrors('ends_at')
            ->assertSessionHasInput('reason', 'Keep this reason');

        $this->actingAs($user)
            ->from(route('pto.create'))
            ->post(route('pto.store'), [
                'employee_id' => $employee->id,
                'pto_policy_id' => $policy->id,
                'starts_at' => '2026-08-03 09:00:00',
                'ends_at' => '2026-08-07 17:00:00',
                'reason' => 'Too much PTO',
            ])
            ->assertRedirect(route('pto.create'))
            ->assertSessionHasErrors('days');

        $this->assertDatabaseCount('pto_requests', 0);
    }

    public function test_pto_negative_policy_allows_overdraw_and_manual_adjustments(): void
    {
        $user = $this->ownerAdmin();
        $employee = Employee::create([
            'first_name' => 'Negative',
            'last_name' => 'Balance',
            'status' => 'active',
        ]);
        $policy = PtoPolicy::firstOrFail();
        $policy->update(['allow_negative_balance' => true]);

        $this->actingAs($user)
            ->post(route('pto.store'), [
                'employee_id' => $employee->id,
                'pto_policy_id' => $policy->id,
                'starts_at' => '2026-08-03 09:00:00',
                'ends_at' => '2026-08-07 17:00:00',
                'reason' => 'Approved overdraw',
            ])
            ->assertRedirect(route('pto.index'));

        $ptoRequest = PtoRequest::firstOrFail();

        $this->assertSame('5.00', $ptoRequest->days);

        $this->actingAs($user)
            ->post(route('pto.approve', $ptoRequest))
            ->assertRedirect(route('pto.index'));

        $this->assertSame('-3.75', PtoBalance::firstOrFail()->fresh()->available_days);

        $this->actingAs($user)
            ->post(route('pto.adjustments.store'), [
                'employee_id' => $employee->id,
                'pto_policy_id' => $policy->id,
                'effective_date' => '2026-08-10',
                'days' => 2.5,
                'reason' => 'Manual top-off',
            ])
            ->assertRedirect(route('pto.index'));

        $this->assertDatabaseHas('pto_adjustments', [
            'employee_id' => $employee->id,
            'pto_policy_id' => $policy->id,
            'days' => 2.5,
            'reason' => 'Manual top-off',
        ]);
        $this->assertSame('-1.25', PtoBalance::firstOrFail()->fresh()->available_days);
    }

    public function test_manager_can_adjust_direct_report_pto_only(): void
    {
        $this->seed(CoreAccessSeeder::class);

        $manager = User::factory()->create();
        $manager->assignRole(SystemRole::Manager->value);
        $managerEmployee = Employee::create([
            'user_id' => $manager->id,
            'first_name' => 'Adjusting',
            'last_name' => 'Manager',
            'status' => 'active',
        ]);
        $directReport = Employee::create([
            'manager_id' => $managerEmployee->id,
            'first_name' => 'Direct',
            'last_name' => 'Adjustee',
            'status' => 'active',
        ]);
        $otherEmployee = Employee::create([
            'first_name' => 'Other',
            'last_name' => 'Employee',
            'status' => 'active',
        ]);
        $policy = PtoPolicy::firstOrFail();

        $this->actingAs($manager)
            ->post(route('pto.adjustments.store'), [
                'employee_id' => $directReport->id,
                'pto_policy_id' => $policy->id,
                'effective_date' => '2026-08-10',
                'days' => 1,
                'reason' => 'Manager correction',
            ])
            ->assertRedirect(route('pto.index'));

        $this->actingAs($manager)
            ->post(route('pto.adjustments.store'), [
                'employee_id' => $otherEmployee->id,
                'pto_policy_id' => $policy->id,
                'effective_date' => '2026-08-10',
                'days' => 1,
                'reason' => 'Not allowed',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('pto_adjustments', [
            'employee_id' => $directReport->id,
            'reason' => 'Manager correction',
        ]);
        $this->assertDatabaseMissing('pto_adjustments', [
            'employee_id' => $otherEmployee->id,
            'reason' => 'Not allowed',
        ]);
    }

    public function test_owner_admin_can_filter_pto_requests_from_web_ui(): void
    {
        $user = $this->ownerAdmin();
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

        $this->actingAs($user)
            ->get(route('pto.index', [
                'status' => 'pending',
                'employee_id' => $employee->id,
                'pto_policy_id' => $policy->id,
                'starts_from' => '2026-08-01',
                'starts_until' => '2026-08-31',
            ]))
            ->assertOk()
            ->assertSee('Filter Requester')
            ->assertSee('Filtered family day')
            ->assertDontSee('Other day')
            ->assertSee('Any status')
            ->assertSee('Anyone')
            ->assertSee('Any policy');
    }

    public function test_manager_pto_web_queue_is_limited_to_direct_reports(): void
    {
        $this->seed(CoreAccessSeeder::class);

        $manager = User::factory()->create();
        $manager->assignRole(SystemRole::Manager->value);
        $managerEmployee = Employee::create([
            'user_id' => $manager->id,
            'first_name' => 'Queue',
            'last_name' => 'Manager',
        ]);
        $directReport = Employee::create([
            'manager_id' => $managerEmployee->id,
            'first_name' => 'Direct',
            'last_name' => 'Report',
        ]);
        $otherEmployee = Employee::create([
            'first_name' => 'Other',
            'last_name' => 'Requester',
        ]);
        $policy = PtoPolicy::firstOrFail();
        $directRequest = PtoRequest::create([
            'employee_id' => $directReport->id,
            'pto_policy_id' => $policy->id,
            'starts_at' => '2026-08-05 09:00:00',
            'ends_at' => '2026-08-05 17:00:00',
            'days' => 1,
            'status' => 'pending',
            'reason' => 'Direct report family day',
        ]);
        $otherRequest = PtoRequest::create([
            'employee_id' => $otherEmployee->id,
            'pto_policy_id' => $policy->id,
            'starts_at' => '2026-08-06 09:00:00',
            'ends_at' => '2026-08-06 17:00:00',
            'days' => 1,
            'status' => 'pending',
            'reason' => 'Unrelated family day',
        ]);

        $this->actingAs($manager)
            ->get(route('pto.index'))
            ->assertOk()
            ->assertSee('Direct report family day')
            ->assertDontSee('Unrelated family day');

        $this->actingAs($manager)
            ->post(route('pto.approve', $directRequest), [
                'decision_notes' => 'Approved',
            ])
            ->assertRedirect(route('pto.index'));

        $this->actingAs($manager)
            ->post(route('pto.approve', $otherRequest), [
                'decision_notes' => 'Not my report',
            ])
            ->assertForbidden();
    }

    public function test_owner_admin_asset_web_workflow_dispatches_webhook_events(): void
    {
        Queue::fake();
        Storage::fake('local');

        $user = $this->ownerAdmin();
        SystemSetting::putString(SystemSetting::MAIN_CURRENCY, 'GTQ');
        $employee = Employee::create([
            'first_name' => 'Asset',
            'last_name' => 'Owner',
            'status' => 'active',
        ]);
        WebhookEndpoint::create([
            'name' => 'Asset listener',
            'url' => 'https://example.test/webhooks/bolt',
            'secret' => 'a-very-long-secret-for-signing',
            'events' => ['*'],
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('assets.store'), [
                'name' => 'Web laptop',
                'tags' => 'Laptop, Onboarding',
                'status' => 'available',
                'photos' => [
                    UploadedFile::fake()->image('asset-front.jpg', 600, 400),
                    UploadedFile::fake()->image('asset-back.jpg', 600, 400),
                ],
            ])
            ->assertRedirect();

        $asset = Asset::firstOrFail();
        $this->assertNotNull($asset->asset_tag);
        $this->assertSame(['Laptop', 'Onboarding'], $asset->tags);
        $this->assertSame('Laptop', $asset->category);
        $this->assertSame('GTQ', $asset->currency);
        $this->assertNotNull($asset->photo_path);
        Storage::disk('local')->assertExists($asset->photo_path);
        $this->assertSame(2, $asset->attachments()->count());

        $this->actingAs($user)
            ->get(route('assets.photo', $asset))
            ->assertOk();

        $this->actingAs($user)
            ->patch(route('assets.update', $asset), [
                'name' => 'Web laptop updated',
                'tags' => 'Laptop, Sales',
                'status' => 'available',
            ])
            ->assertRedirect(route('assets.show', $asset));

        $this->actingAs($user)
            ->post(route('assets.assign', $asset), [
                'employee_id' => $employee->id,
                'condition' => 'Mint condition',
                'notes' => 'Issued for onboarding',
                'files' => [UploadedFile::fake()->image('handoff.jpg', 300, 300)],
            ])
            ->assertRedirect(route('assets.show', $asset));

        $this->assertDatabaseHas('asset_events', [
            'asset_id' => $asset->id,
            'type' => 'assigned',
            'employee_id' => $employee->id,
            'condition' => 'Mint condition',
            'notes' => 'Issued for onboarding',
        ]);

        $this->actingAs($user)
            ->post(route('assets.events.store', $asset), [
                'type' => 'delivered',
                'employee_id' => $employee->id,
                'condition' => 'Mint condition',
                'notes' => 'Employee confirmed laptop delivered in mint condition.',
                'files' => [UploadedFile::fake()->image('delivery.jpg', 300, 300)],
            ])
            ->assertRedirect(route('assets.show', $asset));

        $this->actingAs($user)
            ->post(route('assets.return', $asset), [
                'condition' => 'Good condition',
                'notes' => 'Returned at offboarding',
            ])
            ->assertRedirect(route('assets.show', $asset));

        $this->assertDatabaseHas('asset_events', [
            'asset_id' => $asset->id,
            'type' => 'delivered',
            'employee_id' => $employee->id,
            'condition' => 'Mint condition',
            'notes' => 'Employee confirmed laptop delivered in mint condition.',
        ]);
        $this->assertDatabaseHas('asset_events', [
            'asset_id' => $asset->id,
            'type' => 'returned',
            'from_employee_id' => $employee->id,
            'condition' => 'Good condition',
            'notes' => 'Returned at offboarding',
        ]);
        $this->assertSame(2, Attachment::where('attachable_type', AssetEvent::class)->count());

        $this->actingAs($user)
            ->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee('Asset history')
            ->assertSee('Employee confirmed laptop delivered in mint condition.')
            ->assertSee('Good condition');

        $this->assertDatabaseHas('webhook_deliveries', ['event' => 'asset.created']);
        $this->assertDatabaseHas('webhook_deliveries', ['event' => 'asset.updated']);
        $this->assertDatabaseHas('webhook_deliveries', ['event' => 'asset.assigned']);
        $this->assertDatabaseHas('webhook_deliveries', ['event' => 'asset.returned']);
        Queue::assertPushed(DeliverWebhook::class, 4);
    }

    public function test_asset_photo_is_policy_checked(): void
    {
        Storage::fake('local');
        $owner = $this->ownerAdmin();
        $viewer = User::factory()->create();
        $asset = Asset::create([
            'asset_tag' => 'PHOTO-001',
            'name' => 'Photo asset',
            'status' => 'available',
            'currency' => 'USD',
            'photo_path' => UploadedFile::fake()->image('asset.jpg')->store('asset-photos', 'local'),
        ]);

        $this->actingAs($owner)
            ->get(route('assets.photo', $asset))
            ->assertOk();

        $this->actingAs($viewer)
            ->get(route('assets.photo', $asset))
            ->assertForbidden();
    }

    public function test_owner_admin_can_filter_assets_from_web_ui(): void
    {
        $user = $this->ownerAdmin();
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
            'status' => 'assigned',
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
            'status' => 'available',
            'currency' => 'USD',
        ]);

        $this->actingAs($user)
            ->get(route('assets.create'))
            ->assertOk()
            ->assertSee('Add another photo')
            ->assertSee('Laptop')
            ->assertSee('Monitor');

        $this->actingAs($user)
            ->get(route('assets.index', [
                'q' => 'operations',
                'status' => 'assigned',
                'tag' => 'Laptop',
                'assigned_to' => $employee->id,
            ]))
            ->assertOk()
            ->assertSee('LAP-OPS-001')
            ->assertSee('Casey Ops')
            ->assertDontSee('MON-001')
            ->assertSee('Any status')
            ->assertSee('Any tag')
            ->assertSee('Anyone');
    }

    public function test_owner_admin_can_manage_asset_tags_from_web_ui(): void
    {
        $user = $this->ownerAdmin();
        $asset = Asset::create([
            'asset_tag' => 'TAG-001',
            'name' => 'Tagged laptop',
            'category' => 'Laptop',
            'tags' => ['Laptop', 'Operations'],
            'status' => 'available',
            'currency' => 'USD',
        ]);

        $this->actingAs($user)
            ->get(route('asset-tags.index'))
            ->assertOk()
            ->assertSee('Asset tags')
            ->assertSee('Laptop')
            ->assertSee('Operations');

        $this->actingAs($user)
            ->post(route('asset-tags.store'), ['name' => 'Tablet'])
            ->assertRedirect(route('asset-tags.index'));

        $this->assertContains('Tablet', SystemSetting::array(SystemSetting::ASSET_TAGS));

        $this->actingAs($user)
            ->get(route('assets.create'))
            ->assertOk()
            ->assertSee('Tablet');

        $this->actingAs($user)
            ->put(route('asset-tags.update'), [
                'current_name' => 'Laptop',
                'name' => 'Notebook',
            ])
            ->assertRedirect(route('asset-tags.index'));

        $asset->refresh();
        $this->assertSame(['Notebook', 'Operations'], $asset->tags);
        $this->assertSame('Notebook', $asset->category);

        $this->actingAs($user)
            ->delete(route('asset-tags.destroy'), ['name' => 'Operations'])
            ->assertRedirect(route('asset-tags.index'));

        $asset->refresh();
        $this->assertSame(['Notebook'], $asset->tags);
        $this->assertSame('Notebook', $asset->category);
        $this->assertDatabaseHas('audit_logs', ['event' => 'asset_tag.deleted']);
    }

    public function test_employee_and_knowledge_web_writes_dispatch_webhook_events(): void
    {
        Queue::fake();

        $user = $this->ownerAdmin();
        WebhookEndpoint::create([
            'name' => 'Core listener',
            'url' => 'https://example.test/webhooks/bolt',
            'secret' => 'a-very-long-secret-for-signing',
            'events' => ['*'],
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('employees.store'), [
                'first_name' => 'Event',
                'last_name' => 'Employee',
                'status' => 'active',
            ])
            ->assertRedirect();

        $employee = Employee::where('first_name', 'Event')->firstOrFail();

        $this->actingAs($user)
            ->patch(route('employees.update', $employee), [
                'first_name' => 'Event',
                'last_name' => 'Employee Updated',
                'status' => 'active',
            ])
            ->assertRedirect(route('employees.show', $employee));

        $this->actingAs($user)
            ->post(route('knowledge.store'), [
                'title' => 'Event article',
                'body_markdown' => '# Event article',
                'status' => 'draft',
            ])
            ->assertRedirect();

        $article = KnowledgeArticle::where('title', 'Event article')->firstOrFail();

        $this->actingAs($user)
            ->patch(route('knowledge.update', $article), [
                'title' => 'Event article updated',
                'slug' => $article->slug,
                'body_markdown' => '# Event article updated',
                'status' => 'published',
            ])
            ->assertRedirect(route('knowledge.show', $article));

        $this->assertDatabaseHas('webhook_deliveries', ['event' => 'employee.created']);
        $this->assertDatabaseHas('webhook_deliveries', ['event' => 'employee.updated']);
        $this->assertDatabaseHas('webhook_deliveries', ['event' => 'knowledge_article.created']);
        $this->assertDatabaseHas('webhook_deliveries', ['event' => 'knowledge_article.updated']);
        Queue::assertPushed(DeliverWebhook::class, 4);
    }

    public function test_owner_admin_can_filter_knowledge_articles_from_web_ui(): void
    {
        $user = $this->ownerAdmin();

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

        $this->actingAs($user)
            ->get(route('knowledge.index', [
                'q' => 'wireguard',
                'status' => 'published',
                'category' => 'IT',
                'tag' => 'remote',
            ]))
            ->assertOk()
            ->assertSee('VPN setup')
            ->assertDontSee('Payroll SOP')
            ->assertSee('Any status')
            ->assertSee('Any category')
            ->assertSee('Any tag');
    }

    public function test_owner_admin_can_send_and_replay_webhook_deliveries_from_ui(): void
    {
        Queue::fake();

        $user = $this->ownerAdmin();
        $endpoint = WebhookEndpoint::create([
            'name' => 'Ops listener',
            'url' => 'https://example.test/webhooks/bolt',
            'secret' => 'a-very-long-secret-for-signing',
            'events' => ['employee.created'],
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('webhooks.test', $endpoint))
            ->assertRedirect(route('webhooks.show', $endpoint));

        $testDelivery = WebhookDelivery::firstOrFail();

        $this->assertSame('webhook.test', $testDelivery->event);
        Queue::assertPushed(DeliverWebhook::class, 1);

        $this->actingAs($user)
            ->post(route('webhook-deliveries.replay', $testDelivery))
            ->assertRedirect(route('webhooks.show', $endpoint));

        $this->assertDatabaseCount('webhook_deliveries', 2);
        $replayDelivery = WebhookDelivery::latest('id')->firstOrFail();
        $this->assertNotTrue($testDelivery->is($replayDelivery));
        $this->assertSame('webhook.test', $replayDelivery->event);
        $this->assertSame($testDelivery->payload, $replayDelivery->payload);
        Queue::assertPushed(DeliverWebhook::class, 2);

        $this->actingAs($user)
            ->get(route('webhooks.show', $endpoint))
            ->assertOk()
            ->assertSee('webhook.test')
            ->assertSee('Replay')
            ->assertSee('Send test');
    }

    public function test_owner_admin_can_filter_webhook_endpoints_and_deliveries_from_web_ui(): void
    {
        $user = $this->ownerAdmin();
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
            'error' => 'Timeout',
        ]);
        $failed->forceFill(['created_at' => '2026-08-10 10:00:00', 'updated_at' => '2026-08-10 10:00:00'])->save();
        $delivered = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'webhook.test',
            'payload' => ['test' => true],
            'status' => 'delivered',
            'response_status' => 204,
        ]);
        $delivered->forceFill(['created_at' => '2026-09-10 10:00:00', 'updated_at' => '2026-09-10 10:00:00'])->save();

        $this->actingAs($user)
            ->get(route('webhooks.index', [
                'q' => 'ops',
                'is_active' => '1',
                'event' => 'employee.created',
            ]))
            ->assertOk()
            ->assertSee('Ops receiver')
            ->assertDontSee('Disabled receiver')
            ->assertSee('Any status')
            ->assertSee('Any event');

        $this->actingAs($user)
            ->get(route('webhooks.show', [
                'webhookEndpoint' => $endpoint,
                'status' => 'failed',
                'event' => 'employee.created',
                'created_from' => '2026-08-01',
                'created_until' => '2026-08-31',
            ]))
            ->assertOk()
            ->assertSee('employee.created')
            ->assertSee('Timeout')
            ->assertDontSee('204')
            ->assertSee('Any status')
            ->assertSee('Any event');
    }

    public function test_owner_admin_can_view_webhook_delivery_detail_from_ui(): void
    {
        $user = $this->ownerAdmin();
        $endpoint = WebhookEndpoint::create([
            'name' => 'Detail receiver',
            'url' => 'https://example.test/webhooks/detail',
            'secret' => 'a-very-long-secret-for-signing',
            'events' => ['employee.created'],
            'is_active' => true,
            'failure_count' => 2,
        ]);
        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'employee.created',
            'payload' => ['employee_id' => 123],
            'status' => 'failed',
            'attempts' => 2,
            'response_status' => 500,
            'response_body' => '{"error":"bad"}',
            'error' => 'HTTP 500',
            'next_attempt_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)
            ->get(route('webhooks.show', $endpoint))
            ->assertOk()
            ->assertSee('Failure count')
            ->assertSee('Retry eligible')
            ->assertSee('Details')
            ->assertSee('employee.created');

        $this->actingAs($user)
            ->get(route('webhook-deliveries.show', $delivery))
            ->assertOk()
            ->assertSee('Webhook delivery #'.$delivery->id)
            ->assertSee('HTTP 500')
            ->assertSee('employee_id')
            ->assertSee('bad');
    }

    public function test_disabled_webhook_endpoint_hides_test_and_replay_actions(): void
    {
        Queue::fake();

        $user = $this->ownerAdmin();
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

        $this->actingAs($user)
            ->get(route('webhooks.show', $endpoint))
            ->assertOk()
            ->assertSee('This endpoint is disabled')
            ->assertDontSee('Send test')
            ->assertDontSee('Replay');

        $this->actingAs($user)
            ->post(route('webhooks.test', $endpoint))
            ->assertStatus(422);

        $this->actingAs($user)
            ->post(route('webhook-deliveries.replay', $delivery))
            ->assertStatus(422);

        Queue::assertNothingPushed();
    }

    public function test_owner_admin_can_update_settings_from_web_ui(): void
    {
        $user = $this->ownerAdmin();

        $this->actingAs($user)
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Main currency')
            ->assertSee('Webhook delivery history limit')
            ->assertSee('10000')
            ->assertSee('Queue workers');

        $this->actingAs($user)
            ->put(route('settings.update'), [
                'main_currency' => 'gtq',
                'webhook_delivery_history_limit' => 2500,
                'queue_worker_count' => 3,
            ])
            ->assertRedirect(route('settings.edit'));

        $this->assertSame('GTQ', SystemSetting::mainCurrency());
        $this->assertSame(2500, SystemSetting::integer(SystemSetting::WEBHOOK_DELIVERY_HISTORY_LIMIT, 10000));
        $this->assertSame(3, SystemSetting::integer(SystemSetting::QUEUE_WORKER_COUNT, 1));
        $this->assertDatabaseHas('audit_logs', ['event' => 'system_settings.updated']);
    }

    public function test_webhook_form_preserves_and_rotates_secret_from_web_ui(): void
    {
        $user = $this->ownerAdmin();
        $endpoint = WebhookEndpoint::create([
            'name' => 'Secret receiver',
            'url' => 'https://example.test/webhooks/secret',
            'secret' => 'original-secret-value-for-signing',
            'events' => ['employee.created'],
            'is_active' => true,
        ]);
        $originalEncryptedSecret = $endpoint->getRawOriginal('secret');

        $this->actingAs($user)
            ->get(route('webhooks.edit', $endpoint))
            ->assertOk()
            ->assertSee('Secrets are never displayed again');

        $this->actingAs($user)
            ->put(route('webhooks.update', $endpoint), [
                'name' => 'Secret receiver',
                'url' => 'https://example.test/webhooks/secret',
                'secret' => '',
                'events' => ['employee.created'],
                'is_active' => '1',
            ])
            ->assertRedirect(route('webhooks.show', $endpoint));

        $this->assertSame($originalEncryptedSecret, $endpoint->fresh()->getRawOriginal('secret'));

        $this->actingAs($user)
            ->put(route('webhooks.update', $endpoint), [
                'name' => 'Secret receiver',
                'url' => 'https://example.test/webhooks/secret',
                'secret' => 'rotated-secret-value-for-signing',
                'events' => ['employee.created'],
                'is_active' => '1',
            ])
            ->assertRedirect(route('webhooks.show', $endpoint));

        $this->assertSame('rotated-secret-value-for-signing', $endpoint->fresh()->secret);
    }

    public function test_owner_admin_can_manage_pto_policies_from_web_ui(): void
    {
        $user = $this->ownerAdmin();
        $defaultPolicy = PtoPolicy::firstOrFail();

        $this->actingAs($user)
            ->get(route('pto-policies.index'))
            ->assertOk()
            ->assertSee('Default PTO')
            ->assertSee('New policy');

        $this->actingAs($user)
            ->post(route('pto-policies.store'), [
                'name' => 'Sabbatical',
                'annual_allowance_days' => 10,
                'accrual_type' => 'manual',
                'accumulation_frequency' => 'bimonthly',
                'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'holidays' => "2026-01-01\n2026-12-25",
                'allow_negative_balance' => '1',
                'carryover_days' => 0,
                'approval_strategy' => 'hr_only',
                'is_default' => '1',
            ])
            ->assertRedirect(route('pto-policies.index'));

        $policy = PtoPolicy::where('name', 'Sabbatical')->firstOrFail();

        $this->assertTrue($policy->is_default);
        $this->assertFalse($defaultPolicy->fresh()->is_default);

        $this->actingAs($user)
            ->put(route('pto-policies.update', $policy), [
                'name' => 'Sabbatical',
                'annual_allowance_days' => 12,
                'accrual_type' => 'annual_grant',
                'accumulation_frequency' => 'biweekly',
                'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'holidays' => '2026-01-01',
                'carryover_days' => 2,
                'approval_strategy' => 'manager_then_hr',
            ])
            ->assertRedirect(route('pto-policies.index'));

        $this->actingAs($user)
            ->get(route('pto-policies.index'))
            ->assertOk()
            ->assertSee('Sabbatical')
            ->assertSee('12.00 days')
            ->assertSee('Every other week')
            ->assertSee('Manager Then Hr');
    }

    public function test_non_manager_cannot_manage_pto_policies_from_web_ui(): void
    {
        $this->seed(CoreAccessSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(SystemRole::Employee->value);

        $this->actingAs($user)
            ->get(route('pto-policies.index'))
            ->assertOk()
            ->assertDontSee('New policy');

        $this->actingAs($user)
            ->get(route('pto-policies.create'))
            ->assertForbidden();
    }

    private function ownerAdmin(): User
    {
        $this->seed(CoreAccessSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(SystemRole::OwnerAdmin->value);

        return $user;
    }
}
