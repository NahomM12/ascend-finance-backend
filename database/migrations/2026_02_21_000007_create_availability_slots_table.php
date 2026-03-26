<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('availability_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->constrained('users')->onDelete('cascade');
            $table->string('day_of_week', 16);
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('increment_minutes')->default(30);
            $table->timestamps();

            $table->index(['admin_user_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_slots');
    }
};

