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
        Schema::table('users', function (Blueprint $table) {
            $table->string('passcode_hash')->nullable()->after('password');
            $table->integer('failed_passcode_attempts')->default(0)->after('passcode_hash');
            $table->timestamp('passcode_attempts_at')->nullable()->after('failed_passcode_attempts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['passcode_hash', 'failed_passcode_attempts', 'passcode_attempts_at']);
        });
    }
};
