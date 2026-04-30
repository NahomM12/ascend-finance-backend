<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Founders;

class FounderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Founders::create([
            'company_name' => 'Tech Solutions Inc.',
            'sector' => 'Technology',
            'location' => 'addis ababa',
            'operational_stage' => 'pre-operational',
            'valuation' => 'seed 1M$ - 5M$',
            'years_of_establishment' => '2',
            'investment_size' => 500000.00,
            'description' => 'Innovative tech solutions for modern problems.',
            'number_of_employees' => '11-50',
            'status' => 'active',
        ]);

        Founders::create([
            'company_name' => 'Green Energy Co.',
            'sector' => 'Energy',
            'location' => 'hawassa',
            'operational_stage' => 'revenue-generating',
            'valuation' => 'series A 5M$ - 10M$',
            'years_of_establishment' => '5',
            'investment_size' => 2000000.00,
            'description' => 'Sustainable and renewable energy sources.',
            'number_of_employees' => '51-200',
            'status' => 'active',
        ]);
    }
}
