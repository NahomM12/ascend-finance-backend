<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('founders', function (Blueprint $table) {
            // Add separate fields for USD and ETB
            $table->decimal('investment_size_usd', 20, 2)->nullable()->after('investment_size');
            $table->decimal('investment_size_etb', 20, 2)->nullable()->after('investment_size_usd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('founders', function (Blueprint $table) {
            $table->dropColumn(['investment_size_usd', 'investment_size_etb']);
        });
    }
};
