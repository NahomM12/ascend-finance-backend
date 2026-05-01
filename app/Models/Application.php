<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory;

    protected $fillable = ['job_posting_id', 'fullName', 'email', 'phone', 'location', 'currentRole', 'experience', 'coverLetter', 'resumeUrl', 'status', 'trackingCode'];

    protected $appends = ['jobTitle', 'jobSlug'];

    public function getJobTitleAttribute()
    {
        return $this->jobPosting?->title ?? 'General Position';
    }

    public function getJobSlugAttribute()
    {
        return $this->jobPosting?->slug ?? 'general';
    }

    public function jobPosting() { 
        return $this->belongsTo(JobPosting::class); 
    }
}
