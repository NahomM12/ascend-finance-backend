<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
 use Illuminate\Support\Facades\Auth;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'oauth_provider',
        'oauth_id',
        'role',
        'is_active',
        'passcode_hash',
        'failed_passcode_attempts',
        'passcode_attempts_at',
    ];

    /**
     * API token expiration time in minutes.
     */
    public function accessTokenExpirationMinutes(): int
    {
        return 30; // 30 minutes
    }

     const ROLES = [
        'admin' => 'Admin',
        'superadmin'=> 'Super Admin',
        'investors'=>'Investors',
     ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'passcode_attempts_at' => 'datetime',
        ];
    }
     public function pitchDecks()
    {
        return $this->hasMany(PitchDeck::class);
    }
}
