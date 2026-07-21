<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('auditable_id', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        $hasStringIds = DB::connection()->getDriverName() === 'mysql'
            ? DB::table('audit_logs')->whereNotNull('auditable_id')->whereRaw("auditable_id REGEXP '[^0-9]'")->exists()
            : DB::table('audit_logs')->whereNotNull('auditable_id')->whereRaw("auditable_id GLOB '*[^0-9]*'")->exists();

        if ($hasStringIds) {
            throw new RuntimeException('Cannot narrow audit target IDs while string-backed audit records exist.');
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('auditable_id')->nullable()->change();
        });
    }
};
