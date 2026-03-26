<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_user_id',
        'investor_user_id',
        'scheduled_at',
        'duration_minutes',
        'status',
        'title',
        'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function investorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investor_user_id');
    }
}

