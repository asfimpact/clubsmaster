<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, \Laravel\Sanctum\HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'two_factor_verified_at',
        'two_factor_code',
        'two_factor_expires_at',
        'email_verified_at',
        'last_activity_at',
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
     * The attributes to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['computed_status'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_verified_at' => 'datetime',
            'two_factor_expires_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's computed status based on security flow.
     */
    public function getComputedStatusAttribute()
    {
        if ($this->status === 'suspended') {
            return 'Suspended';
        }

        if (!$this->email_verified_at) {
            return 'Pending';
        }

        // Check global 2FA setting
        $is2faEnabled = DB::table('settings')->where('key', '2fa_enabled')->value('value') === '1';

        if ($is2faEnabled && !$this->two_factor_verified_at) {
            return 'Inactive';
        }

        return 'Active';
    }

    /**
     * Relationships
     */
    public function subscription()
    {
        return $this->hasOne(Subscription::class);
    }
}
