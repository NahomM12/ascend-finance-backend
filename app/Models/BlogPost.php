<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlogPost extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'slug', 'excerpt', 'content', 'author', 'read_time', 'published', 'scheduled_at', 'image_url'];
    protected $casts = ['published' => 'boolean', 'scheduled_at' => 'datetime'];
}
