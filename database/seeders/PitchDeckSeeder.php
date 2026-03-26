<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PitchDeck;
use App\Models\Founders;

class PitchDeckSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $founders = Founders::all();

        if ($founders->isEmpty()) {
            $this->command->info('No founders found, skipping pitch deck seeding.');
            return;
        }

        foreach ($founders as $founder) {
            PitchDeck::create([
                'founder_id' => $founder->id,
                'title' => 'Pitch Deck for ' . $founder->company_name,
                'file_path' => 'pitch_decks/' . \Illuminate\Support\Str::slug($founder->company_name) . '.pdf',
                'file_type' => 'pdf',
                'thumbnail_path' => 'thumbnails/' . \Illuminate\Support\Str::slug($founder->company_name) . '.jpg',
                'status' => 'published',
                'uploaded_by' => 1, // Assuming admin user with ID 1
            ]);
        }
    }
}
