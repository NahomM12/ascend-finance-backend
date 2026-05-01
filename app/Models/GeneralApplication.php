<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralApplication extends Model
{
    use HasFactory;
    
    protected $fillable = ['fullName', 'email', 'phone', 'resumeUrl', 'message', 'status', 'trackingCode'];
}
