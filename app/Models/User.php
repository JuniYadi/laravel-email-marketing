<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'is_admin',
        'google_id',
        'google_token',
        'google_refresh_token',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'google_token',
        'google_refresh_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            'is_admin' => 'boolean',
            'google_token' => 'encrypted',
            'google_refresh_token' => 'encrypted',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Check if user has a linked Google account
     */
    public function hasGoogleAccount(): bool
    {
        return ! is_null($this->google_id);
    }

    /**
     * Check if user has a password set
     */
    public function hasPassword(): bool
    {
        return ! is_null($this->password);
    }

    /**
     * Get the authentication method for this user
     */
    public function authenticationMethod(): string
    {
        if ($this->hasGoogleAccount() && $this->hasPassword()) {
            return 'both';
        }

        if ($this->hasGoogleAccount()) {
            return 'google';
        }

        if ($this->hasPassword()) {
            return 'password';
        }

        return 'none';
    }

    /**
     * Determine if this user has administrator access.
     */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }
}
