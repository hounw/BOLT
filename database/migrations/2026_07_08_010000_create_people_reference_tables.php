<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('compensation_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->string('type')->default('salary');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('status')->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->after('department')->constrained()->nullOnDelete();
            $table->longText('private_hr_data')->nullable()->after('hr_metadata');
        });

        DB::table('employees')
            ->whereNotNull('department')
            ->distinct()
            ->orderBy('department')
            ->pluck('department')
            ->filter()
            ->each(function (string $name): void {
                $departmentId = DB::table('departments')->insertGetId([
                    'name' => $name,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('employees')->where('department', $name)->update(['department_id' => $departmentId]);
            });

        DB::table('employees')
            ->whereNotNull('title')
            ->distinct()
            ->orderBy('title')
            ->pluck('title')
            ->filter()
            ->each(function (string $name): void {
                $positionId = DB::table('positions')->insertGetId([
                    'name' => $name,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('employees')->where('title', $name)->update(['position_id' => $positionId]);
            });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('position_id');
            $table->dropColumn('private_hr_data');
        });

        Schema::dropIfExists('compensation_packages');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
    }
};
