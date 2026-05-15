<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PitchDeckView extends Model
{
    protected $fillable = [
        'pitch_deck_id',
        'user_id',
        'ip_address',
        'viewed_at',
    ];

    public function pitchDeck()
    {
        return $this->belongsTo(PitchDeck::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
