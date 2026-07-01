<?php

namespace App\Models;

use App\Traits\HasUUID;
use Cmgmyr\Messenger\Traits\Messagable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * @property string $id
 * @property string $name
 * @property string $provider_name
 * @property string $provider_id
 * @property string|null $email
 * @property string|null $one_time_password
 * @property Carbon|null $password_expires_at
 * @property array<int, string> $notify_via
 * @property string $access_token
 * @property string|null $refresh_token
 * @property array<string, mixed> $profile
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class User extends Authenticatable implements OAuthenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUUID;
    use Messagable;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'one_time_password',
        'password_expires_at',
        'provider_name',
        'provider_id',
        'notify_via',
        'access_token',
        'refresh_token',
        'profile',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var list<string>
     */
    protected $hidden = [
        'one_time_password',
        'password_expires_at',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
        'access_token',
        'refresh_token',
        'profile',
        'provider_name',
        'provider_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'notify_via' => 'array',
        'profile' => 'array',
        'password_expires_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        // 'profile_photo_url',
    ];
}
