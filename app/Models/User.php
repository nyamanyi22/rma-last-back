<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'phone',
        'country',
        'address',
        'city',
        'postal_code',
        'is_active',
        'created_by',
        'email_verified_at',
        'verification_token',
        'last_login_at',
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
     * The attributes that should be appended to the model's JSON form.
     */
    protected $appends = [
        'full_name',
        'initials',
        'role_label',      // 👈 Add this for UI
        'short_role_label',// 👈 Add this for UI
        'is_staff',         // 👈 Add this for frontend
        'is_admin',         // 👈 Add this for frontend
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
            'last_login_at' => 'datetime',
            'role' => UserRole::class, // 👈 CRITICAL: Cast to enum!
        ];
    }

    /**
     * The model's default values for attributes.
     */
    protected $attributes = [
        'role' => UserRole::CUSTOMER->value, // 👈 Use enum value
        'is_active' => true,
    ];

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn() => trim("{$this->first_name} {$this->last_name}"),
        );
    }

    protected function initials(): Attribute
    {
        return Attribute::make(
            get: fn() => strtoupper(
                substr($this->first_name, 0, 1) .
                substr($this->last_name, 0, 1)
            ),
        );
    }

    /**
     * Get role label for UI (automatically appended)
     */
    protected function getRoleLabelAttribute(): string
    {
        return $this->role?->label() ?? 'Unknown';
    }

    /**
     * Get short role label for UI (e.g. CSR, Admin)
     */
    protected function getShortRoleLabelAttribute(): string
    {
        return $this->role?->shortLabel() ?? 'User';
    }

    /**
     * Check if user is staff (for frontend)
     */
    protected function getIsStaffAttribute(): bool
    {
        return $this->role && in_array($this->role, [\App\Enums\UserRole::CSR, \App\Enums\UserRole::ADMIN, \App\Enums\UserRole::SUPER_ADMIN]);
    }

    /**
     * Check if user is admin (for frontend)
     */
    protected function getIsAdminAttribute(): bool
    {
        return $this->role && in_array($this->role, [\App\Enums\UserRole::ADMIN, \App\Enums\UserRole::SUPER_ADMIN]);
    }

    // =========================================================================
    // ROLE HELPERS (Now using ENUMS)
    // =========================================================================

    public function isCustomer(): bool
    {
        return $this->role === UserRole::CUSTOMER;
    }

    public function isStaff(): bool
    {
        return $this->role && in_array($this->role, [\App\Enums\UserRole::CSR, \App\Enums\UserRole::ADMIN, \App\Enums\UserRole::SUPER_ADMIN]);
    }

    public function isAdmin(): bool
    {
        return $this->role && in_array($this->role, [\App\Enums\UserRole::ADMIN, \App\Enums\UserRole::SUPER_ADMIN]);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SUPER_ADMIN;
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roles): bool
    {
        $roleValues = array_map(fn($role) => $role instanceof UserRole ? $role->value : $role, $roles);
        return in_array($this->role?->value, $roleValues);
    }

    // =========================================================================
    // QUERY SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCustomers($query)
    {
        return $query->where('role', UserRole::CUSTOMER->value);
    }

    public function scopeStaff($query)
    {
        return $query->whereIn('role', array_map(fn($role) => $role->value, UserRole::staffRoles()));
    }

    public function scopeAdmins($query)
    {
        return $query->whereIn('role', array_map(fn($role) => $role->value, UserRole::adminRoles()));
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdUsers(): HasMany
    {
        return $this->hasMany(User::class, 'created_by');
    }

    public function rmaRequests(): HasMany
    {
        return $this->hasMany(RMARequest::class, 'customer_id');
    }

    public function assignedRMAs(): HasMany
    {
        return $this->hasMany(RMARequest::class, 'assigned_to');
    }

    public function approvedRMAs(): HasMany
    {
        return $this->hasMany(RMARequest::class, 'approved_by');
    }

    public function rmaComments(): HasMany
    {
        return $this->hasMany(RMAComment::class, 'user_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'customer_id');
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    public function markEmailAsVerified(): void
    {
        $this->email_verified_at = \Illuminate\Support\Carbon::now();
        $this->save();
    }

    public function updateLastLogin(): void
    {
        $this->last_login_at = \Illuminate\Support\Carbon::now();
        $this->save();
    }

    public function activate(): void
    {
        $this->is_active = true;
        $this->save();
    }

    public function deactivate(): void
    {
        $this->is_active = false;
        $this->save();
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }

    // =========================================================================
    // BOOT METHOD
    // =========================================================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if (empty($user->role)) {
                $user->role = UserRole::CUSTOMER->value;
            }
        });

        // Auto-link existing sales to new user by email
        static::created(function ($user) {
            \App\Models\Sale::where('customer_email', trim($user->email))
                ->whereNull('customer_id')
                ->update(['customer_id' => $user->id]);
        });
    }
}