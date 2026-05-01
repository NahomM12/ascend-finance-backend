<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Insight extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'slug', 'excerpt', 'content', 'icon_name', 'author', 'read_time', 'published', 'featured', 'image_url', 'scheduled_at'];
    protected $casts = ['published' => 'boolean', 'featured' => 'boolean', 'scheduled_at' => 'datetime'];
}
