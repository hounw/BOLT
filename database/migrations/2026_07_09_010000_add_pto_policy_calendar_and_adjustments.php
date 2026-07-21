<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pto_policies', function (Blueprint $table): void {
            $table->json('working_days')->nullable()->after('accumulation_frequency');
            $table->json('holidays')->nullable()->after('working_days');
            $table->boolean('allow_negative_balance')->default(false)->after('holidays');
        });

        Schema::create('pto_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pto_policy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('adjusted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('effective_date');
            $table->decimal('days', 8, 2);
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pto_adjustments');

        Schema::table('pto_policies', function (Blueprint $table): void {
            $table->dropColumn(['working_days', 'holidays', 'allow_negative_balance']);
        });
    }
};
