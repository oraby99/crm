<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'team_leader_id', 'is_active'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = ['password', 'remember_token'];

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
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────────

    /**
     * The team leader this sales user belongs to.
     */
    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_leader_id');
    }

    /**
     * Sales employees managed by this team leader.
     */
    public function salesEmployees(): HasMany
    {
        return $this->hasMany(User::class, 'team_leader_id');
    }

    /**
     * Customers assigned to this sales user.
     */
    public function assignedCustomers(): HasMany
    {
        return $this->hasMany(Customer::class, 'sales_id');
    }

    /**
     * Customers under this team leader's oversight.
     */
    public function teamCustomers(): HasMany
    {
        return $this->hasMany(Customer::class, 'team_leader_id');
    }

    /**
     * Customers created by this user.
     */
    public function createdCustomers(): HasMany
    {
        return $this->hasMany(Customer::class, 'created_by');
    }

    /**
     * Customer activities recorded by this user.
     */
    public function customerActivities(): HasMany
    {
        return $this->hasMany(CustomerActivity::class, 'user_id');
    }

    /**
     * Imports uploaded by this user.
     */
    public function imports(): HasMany
    {
        return $this->hasMany(Import::class, 'uploaded_by');
    }

    // ──────────────────────────────────────────────────────────────
    // Role helpers
    // ──────────────────────────────────────────────────────────────

    public function isAdmin(): bool
    {
        $value = $this->role instanceof UserRole ? $this->role->value : ($this->role ?? $this->getRawOriginal('role'));

        return in_array(strtolower((string) $value), ['admin', 'superadmin', 'super_admin', 'administrator']);
    }

    public function isTeamLeader(): bool
    {
        $value = $this->role instanceof UserRole ? $this->role->value : ($this->role ?? $this->getRawOriginal('role'));

        return in_array(strtolower((string) $value), ['teamleader', 'team_leader', 'leader']);
    }

    public function isSales(): bool
    {
        $value = $this->role instanceof UserRole ? $this->role->value : ($this->role ?? $this->getRawOriginal('role'));

        return in_array(strtolower((string) $value), ['sales', 'sales_rep', 'seller']);
    }

    /**
     * Check if the user can access the Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }
}
