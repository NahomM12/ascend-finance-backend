<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PitchDeckDownload extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pitch_deck_downloads';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'pitch_deck_id',
        'downloaded_at',
        'ip_address',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the user that downloaded the pitch deck.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the pitch deck that was downloaded.
     */
    public function pitchDeck()
    {
        return $this->belongsTo(PitchDeck::class);
    }
}
