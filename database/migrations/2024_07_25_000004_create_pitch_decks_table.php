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
        Schema::create('pitch_decks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('founder_id')
                  ->constrained('founders')
                  ->cascadeOnDelete();
            $table->string('title');
            $table->string('file_path');
            $table->enum('file_type', ['pdf', 'ppt', 'pptx']);
            $table->string('thumbnail_path')->nullable();
            $table->enum('status', ['draft', 'published', 'archived']);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();

            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pitch_decks');
    }
};
