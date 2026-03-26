<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'pitch_deck_id',
        'admin_id',
        'decision',
        'notes',
        'reviewed_at',
    ];

    // public function pitchDeck()
    // {
    //     return $this->belongsTo(Founders::class);
    // }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
