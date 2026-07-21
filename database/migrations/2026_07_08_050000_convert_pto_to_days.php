<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pto_policies', function (Blueprint $table): void {
            $table->renameColumn('annual_allowance_hours', 'annual_allowance_days');
            $table->renameColumn('carryover_hours', 'carryover_days');
            $table->string('accumulation_frequency')->default('monthly')->after('accrual_type');
        });

        Schema::table('pto_balances', function (Blueprint $table): void {
            $table->renameColumn('available_hours', 'available_days');
            $table->renameColumn('used_hours', 'used_days');
            $table->renameColumn('pending_hours', 'pending_days');
        });

        Schema::table('pto_requests', function (Blueprint $table): void {
            $table->renameColumn('hours', 'days');
        });

        DB::table('pto_policies')->update([
            'annual_allowance_days' => DB::raw('annual_allowance_days / 8'),
            'carryover_days' => DB::raw('carryover_days / 8'),
        ]);

        DB::table('pto_balances')->update([
            'available_days' => DB::raw('available_days / 8'),
            'used_days' => DB::raw('used_days / 8'),
            'pending_days' => DB::raw('pending_days / 8'),
        ]);

        DB::table('pto_requests')->update([
            'days' => DB::raw('days / 8'),
        ]);
    }

    public function down(): void
    {
        DB::table('pto_policies')->update([
            'annual_allowance_days' => DB::raw('annual_allowance_days * 8'),
            'carryover_days' => DB::raw('carryover_days * 8'),
        ]);

        DB::table('pto_balances')->update([
            'available_days' => DB::raw('available_days * 8'),
            'used_days' => DB::raw('used_days * 8'),
            'pending_days' => DB::raw('pending_days * 8'),
        ]);

        DB::table('pto_requests')->update([
            'days' => DB::raw('days * 8'),
        ]);

        Schema::table('pto_policies', function (Blueprint $table): void {
            $table->dropColumn('accumulation_frequency');
            $table->renameColumn('annual_allowance_days', 'annual_allowance_hours');
            $table->renameColumn('carryover_days', 'carryover_hours');
        });

        Schema::table('pto_balances', function (Blueprint $table): void {
            $table->renameColumn('available_days', 'available_hours');
            $table->renameColumn('used_days', 'used_hours');
            $table->renameColumn('pending_days', 'pending_hours');
        });

        Schema::table('pto_requests', function (Blueprint $table): void {
            $table->renameColumn('days', 'hours');
        });
    }
};
