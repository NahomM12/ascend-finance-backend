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
    Schema::create('founders', function (Blueprint $table) {
        $table->id();
        $table->string('company_name')->nullable();
        $table->enum('sector', [
    'Agriculture',
    'Manufacturing',
    'Finance',
    'Healthcare',
    'Education',
    'Energy',
    'Technology',
    'Transportation',
    'Tourism'
])->nullable();
        $table->enum('location',['addis ababa', 'diredawa', 'hawassa','bahirdar','gondar','mekele'])->nullable(); // New field
        $table->enum('operational_stage', [
            'pre-operational', 
            'early-operations', 
            'revenue-generating', 
            'profitable/cash-flow positive'
        ])->nullable();
        $table->enum('valuation', [
            'pre seed under 1M$', 
            'seed 1M$ - 5M$', 
            'series A 5M$ - 10M$', 
            'series B 10M$ - 50M$', 
            'series C 50M$ - 100M$', 
            'IPO 100M$+'
        ])->nullable(); // New field
        $table->string('years_of_establishment')->nullable(); // Renamed from 'years of establishment'
        $table->decimal('investment_size', 15, 2)->nullable();
        $table->text('description')->nullable();
        $table->string('file_path')->nullable();
        $table->enum('status', [
            'pending',
            'active',
            'funded',
            'archived',
        ])->default('pending');
        $table->enum('number_of_employees', [ // Renamed from 'number of employees'
            '1-10', 
            '11-50', 
            '51-200', 
            '201-500', 
            '501-1000', 
            '1001+'
        ])->nullable(); // New field
        $table->timestamps();
        
        // Optional: Add index for better performance
        //$table->index('user_id');
        $table->index('sector');
        $table->index('operational_stage');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('founders');
    }
};
