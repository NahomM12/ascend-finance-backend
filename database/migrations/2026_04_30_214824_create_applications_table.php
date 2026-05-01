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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained('job_postings')->cascadeOnDelete();
            $table->string('fullName');
            $table->string('email');
            $table->string('phone');
            $table->string('location')->nullable();
            $table->string('currentRole');
            $table->string('experience');
            $table->text('coverLetter')->nullable();
            $table->string('resumeUrl');
            $table->string('status')->default('Pending');
            $table->string('trackingCode')->unique()->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
