<?php

namespace App\Models;

use App\Http\Controllers\Admin\DashboardController;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'role',
        'phone_number',
        'service_provider',
        'service_provider_id',
        'auth_token',
        'otp',
        'otp_status',
        'otp_expires_at',
        'email_verified_at',
        'profile_image',
        'bio',
        'city',
        'state',
        'country',
        'address_one',
        'address_two',
        'latitude',
        'longitude',
        'hasActiveSubscription',
        'payment_status',
        'skills',
        'cv',
        'password_reset_token',
        'password_reset_token_expires_at',
        'last_password_reset_at',
        'fcm_token',
        'device_type',
        'device_token',
        'notifications_enabled',
        'last_login_at',
        'last_activity_at',
        'last_logout_at',
        'timezone',
        'online_status',
        'status',
        'is_guest',
        'guest_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'auth_token',
        'otp',
        'password_reset_token'
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['profile_image_url', 'cv_url'];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'password_reset_token_expires_at' => 'datetime',
            'last_password_reset_at' => 'datetime',
            'password' => 'hashed',
            'skills' => 'array',
            'last_login_at'    => 'datetime',
            'last_activity_at' => 'datetime',
            'last_logout_at'   => 'datetime',
            'is_guest'          => 'boolean',
            'guest_expires_at'  => 'datetime',
            'created_at' => 'datetime:Y-m-d H:i:s',
            'updated_at' => 'datetime:Y-m-d H:i:s',
            'deleted_at' => 'datetime:Y-m-d H:i:s'
        ];
    }

    /**
     * Get the role associated with the user.
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole($role)
    {
        return $this->role === $role ||
            ($this->roleRelation && $this->roleRelation->name === $role);
    }

    /**
     * Get the password reset tokens for the user.
     */


    // User.php

    public function tasks()
    {
        return $this->hasMany(Task::class, 'user_id');
    }

    public function payments()
    {
        return $this->hasMany(TaskPayment::class, 'user_id');
    }

    public function lists()
    {
        return $this->hasMany(ListStory::class, 'user_id');
    }


    public function recentTasks()
    {
        return $this->hasMany(Task::class, 'user_id')->latest()->take(5);
    }

    public function recentPayments()
    {
        return $this->hasMany(TaskPayment::class, 'user_id')->latest()->take(5);
    }

    public function recentLists()
    {
        return $this->hasMany(ListStory::class, 'user_id')->latest()->take(5);
    }


    public function getLastLoginAttribute()
    {
        return $this->last_login_at ?? $this->updated_at;
    }

    public function isOnline($thresholdMinutes = 5)
    {
        return $this->last_activity_at && $this->last_activity_at->gt(now()->subMinutes($thresholdMinutes));
    }


    public function lastSeen()
    {
        return $this->last_activity_at ? $this->last_activity_at->diffForHumans() : 'Never';
    }

    public function employer()
{
    return $this->belongsTo(User::class, 'user_id');

}

    /**
     * Get the full URL for profile image
     */
    public function getProfileImageUrlAttribute()
    {
        if (!$this->profile_image || $this->profile_image === 'default.jpg') {
            return null;
        }
        return Storage::disk('public')->url('profile_images/' . $this->profile_image);
    }

    /**
     * Get the full URL for CV
     */
    public function getCvUrlAttribute()
    {
        if (!$this->cv) {
            return null;
        }
        return Storage::disk('public')->url('cv/' . $this->cv);
    }
}
