<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobPosting extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'slug', 'department', 'location', 'type', 'experience', 'description', 'requirements', 'responsibilities', 'salary_range', 'published', 'scheduled_at'];
    protected $casts = [
        'published' => 'boolean', 
        'scheduled_at' => 'datetime',
        'requirements' => 'array',
        'responsibilities' => 'array'
    ];

    public function applications() { 
        return $this->hasMany(Application::class); 
    }
}
