<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'telegram_chat_id',
        'receive_in_app_notifications',
        'receive_telegram_notifications',
        'browser_notifications_enabled',
        'two_factor_enabled',
        'two_factor_code',
        'two_factor_expires_at',
        'facebook_id',
        'terms_accepted_at',
        'terms_version',
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
            'receive_in_app_notifications' => 'boolean',
            'receive_telegram_notifications' => 'boolean',
            'browser_notifications_enabled' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'two_factor_expires_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return in_array((string) $this->role, ['admin', 'super-admin'], true)
            || $this->hasRole('admin')
            || $this->hasRole('super-admin');
    }

    public function canDo(string $permission): bool
    {
        try {
            return $this->can($permission);
        } catch (\Throwable $exception) {
            return false;
        }
    }

    public function activities(): HasMany
    {
        return $this->hasMany(UserActivity::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(Suggestion::class);
    }

    public function ipoPositions(): HasMany
    {
        return $this->hasMany(IpoPosition::class);
    }

    public function goldPositions(): HasMany
    {
        return $this->hasMany(GoldPosition::class);
    }
}
