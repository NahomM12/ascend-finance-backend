<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;



class PitchDeck extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pitch_decks';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'founder_id',
        'title',
        'file_path',
        'file_type',
        'thumbnail_path',
        'status',
        'uploaded_by',
    ];

    /**
     * Get the founder that owns the pitch deck.
     */
    public function founder()
    {
        return $this->belongsTo(Founders::class, 'founder_id');
    }

    /**
     * Get the user that uploaded the pitch deck.
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
    public function downloads()
    {
        return $this->hasMany(PitchDeckDownload::class);
    }
    // public function users()
    // {
    //     return $this->belongsToMany(User::class, 'pitch_deck_downloads', 'pitch_deck_id', 'user_id')
    //                 ->withTimestamps();
    // }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    protected static function booted()
{
   static::saved(function ($pitchDeck) {
        // Only clear PUBLIC caches (for investors/users)
        try {
            Cache::forget("public_pitch_deck_{$pitchDeck->id}");
            
            $redis = Cache::getRedis();
            $keys = $redis->keys('*public_pitch_decks*');
            
            foreach ($keys as $key) {
                $cacheKey = str_replace('laravel_cache:', '', $key);
                $cacheKey = str_replace(config('cache.prefix').':', '', $cacheKey);
                Cache::forget($cacheKey);
            }
            
            \Log::info('Public pitch deck cache cleared on save', [
                'pitch_deck_id' => $pitchDeck->id,
                'keys_cleared' => count($keys)
            ]);
            
        } catch (\Exception $e) {
            \Log::warning('Public cache clear failed: ' . $e->getMessage());
        }
    });
    
    static::deleted(function ($pitchDeck) {
        // Clear public caches
        try {
            Cache::forget("public_pitch_deck_{$pitchDeck->id}");
            
            $redis = Cache::getRedis();
            $keys = $redis->keys('*public_pitch_decks*');
            
            foreach ($keys as $key) {
                $cacheKey = str_replace('laravel_cache:', '', $key);
                Cache::forget($cacheKey);
            }
        } catch (\Exception $e) {
            \Log::warning('Public cache clear failed on delete: ' . $e->getMessage());
        }
    });
}
}
