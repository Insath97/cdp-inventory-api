<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Branch;
use App\Models\ReportingManager;


class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    use HasFactory, Notifiable, HasRoles;

    protected $guard_name = 'api';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'phone',
        'date_of_birth',
        'alt_phone',
        'user_type',
        'is_active',
        'can_login',
        'reporting_manager_id',
        'is_reporting_manager',
        'profile_image',
        'last_login_at',
        'last_login_ip',
        'email_verified_at',
        'email_verification_token',
        'email_verification_token_expires_at',
        'branch_id',
        'parent_user_id',
        'zone_id',
        'region_id',
        'province_id',
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
            'email_verification_token_expires_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'can_login' => 'boolean',
            'is_reporting_manager' => 'boolean',
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
        ];
    }

    public function reportingManager(): BelongsTo
    {
        return $this->belongsTo(ReportingManager::class, 'reporting_manager_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_user_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_user_id');
    }

    public function reportingSubordinates(): HasMany
    {
        return $this->hasMany(self::class, 'reporting_manager_id');
    }

     public function canLogin(): bool
    {
        $canLogin = $this->is_active && $this->can_login;
        if ($this->employee_id && $this->relationLoaded('employee')) {
            return $canLogin && $this->employee && $this->employee->is_active;
        }
        // If not loaded, check existence
        if ($this->employee_id) {
            return $canLogin && $this->load('employee')->employee->is_active;
        }
        return $canLogin;
    }

    public function updateLastLogin($ipAddress = null)
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress
        ]);
    }

    /**
     * Generate a unique email verification token
     */
    public function generateEmailVerificationToken(): string
    {
        $token = bin2hex(random_bytes(32));

        $this->update([
            'email_verification_token' => $token,
            'email_verification_token_expires_at' => now()->addHours(24)
        ]);

        return $token;
    }

    /**
     * Mark the user's email as verified
     */
    public function markEmailAsVerifiedcheck(string $token)
    {
        $this->update([
            'email_verified_at' => now(),
            'email_verification_token' => $token,
            'email_verification_token_expires_at' => null
        ]);
    }

    /**
     * Mark the user's email as verified without a token
     */
    public function markEmailAsVerified()
    {
        $this->update([
            'email_verified_at' => now(),
            'email_verification_token' => null,
            'email_verification_token_expires_at' => null
        ]);
    }

    /**
     * Check if the user's email verification token is valid
     */
    public function isEmailVerificationTokenValid(string $token): bool
    {
        if ($this->email_verification_token !== $token) {
            return false;
        }

        if (!$this->email_verification_token_expires_at) {
            return false;
        }

        return now()->lessThan($this->email_verification_token_expires_at);
    }

    /**
     * Get all direct and indirect subordinate IDs (descendants).
     */
    public function getAllDescendantIds(): array
    {
        $descendants = [];
        $children = self::query()->where('parent_user_id', $this->id)->get();

        foreach ($children as $child) {
            $descendants[] = $child->id;
            $descendants = array_merge($descendants, $child->getAllDescendantIds());
        }

        return $descendants;
    }

    /**
     * Check if the user has verified their email
     */
    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }

}
