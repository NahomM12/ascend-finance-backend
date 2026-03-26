<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Founders extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'sector',
        'location',
        'funding_stage',//enum: pre-seed, seed, series A, series B, series C, IPO
        'valuation',//enum: pre-seed, seed, series A, series B, series C, IPO
        'years_of_establishment',
        'funding_amount',
        'description',
        'file_path',
        'status',
        'number_of_employees',//enum: 1-10, 11-50, 51-200, 201-500, 501-1000, 1001+
    ];

    public function reviews()
    {
        return $this->hasMany(AdminReview::class);
    }
    public function pitchdecks()
    {
        return $this->hasMany(PitchDeck::class,'founder_id');
    }
    protected static function booted()
{
    static::saved(function () {
        Cache::flush(); // Simple but effective - clears ALL cache
        // OR more targeted approach:
        // Cache::tags(['founders'])->flush(); // If using tags
    });
    
    static::deleted(function () {
        Cache::flush();
    });
}
}
