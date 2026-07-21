<?php

use App\Enums\AssetStatus;
use App\Enums\EmployeeStatus;
use App\Enums\KnowledgeArticleStatus;
use App\Enums\PtoAccrualType;
use App\Enums\PtoRequestStatus;
use App\Enums\WebhookDeliveryStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('employee_number')->nullable()->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('work_email')->nullable()->unique();
            $table->string('personal_email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default(EmployeeStatus::Active->value)->index();
            $table->string('department')->nullable()->index();
            $table->string('title')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('emergency_contact')->nullable();
            $table->json('hr_metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('compensation_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('effective_date')->index();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->string('type')->default('salary');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('benefit_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->decimal('value', 12, 2)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pto_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('annual_allowance_hours', 8, 2)->default(0);
            $table->string('accrual_type')->default(PtoAccrualType::AnnualGrant->value);
            $table->decimal('carryover_hours', 8, 2)->default(0);
            $table->string('approval_strategy')->default('manager_then_hr');
            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('pto_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pto_policy_id')->constrained()->cascadeOnDelete();
            $table->decimal('available_hours', 8, 2)->default(0);
            $table->decimal('used_hours', 8, 2)->default(0);
            $table->decimal('pending_hours', 8, 2)->default(0);
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamps();
            $table->unique(['employee_id', 'pto_policy_id', 'period_start', 'period_end'], 'pto_balance_period_unique');
        });

        Schema::create('pto_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pto_policy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->decimal('hours', 8, 2);
            $table->string('status')->default(PtoRequestStatus::Pending->value)->index();
            $table->text('reason')->nullable();
            $table->text('decision_notes')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body_markdown');
            $table->string('status')->default(KnowledgeArticleStatus::Draft->value)->index();
            $table->string('category')->nullable()->index();
            $table->json('tags')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag')->unique();
            $table->string('name');
            $table->string('category')->nullable()->index();
            $table->string('serial_number')->nullable()->unique();
            $table->string('status')->default(AssetStatus::Available->value)->index();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->string('vendor')->nullable();
            $table->date('warranty_expires_on')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->dateTime('assigned_at');
            $table->dateTime('returned_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['asset_id', 'returned_at']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('attachable');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->json('metadata')->nullable();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event')->index();
            $table->string('auditable_type')->nullable();
            $table->string('auditable_id', 100)->nullable();
            $table->index(['auditable_type', 'auditable_id'], 'audit_logs_auditable_index');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();
        });

        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->text('secret');
            $table->json('events');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_delivery_at')->nullable();
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('event')->index();
            $table->json('payload');
            $table->string('status')->default(WebhookDeliveryStatus::Pending->value)->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('method', 10);
            $table->string('route')->nullable();
            $table->string('request_hash', 64);
            $table->json('response_body')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->unique(['key', 'user_id'], 'idempotency_key_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('asset_assignments');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('knowledge_articles');
        Schema::dropIfExists('pto_requests');
        Schema::dropIfExists('pto_balances');
        Schema::dropIfExists('pto_policies');
        Schema::dropIfExists('benefit_histories');
        Schema::dropIfExists('compensation_histories');
        Schema::dropIfExists('employees');
    }
};
