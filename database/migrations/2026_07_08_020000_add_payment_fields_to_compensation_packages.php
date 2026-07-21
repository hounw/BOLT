<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compensation_packages', function (Blueprint $table) {
            $table->string('amount_basis')->default('annual')->index();
            $table->string('payment_frequency')->default('monthly')->index();
        });
    }

    public function down(): void
    {
        Schema::table('compensation_packages', function (Blueprint $table) {
            $table->dropColumn(['amount_basis', 'payment_frequency']);
        });
    }
};
