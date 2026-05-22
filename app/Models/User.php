<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['organization_id', 'branch_id', 'role', 'name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_OWNER = 'owner';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_SELLER = 'seller';
    public const ROLE_VIEWER = 'viewer';

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
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isSeller(): bool
    {
        return $this->role === self::ROLE_SELLER;
    }

    public function isViewer(): bool
    {
        return $this->role === self::ROLE_VIEWER;
    }

    public function canViewAllBranches(): bool
    {
        return $this->isOwner() || $this->isAdmin();
    }

    public function canViewAllBranchesForRead(): bool
    {
        return $this->canViewAllBranches() || $this->isViewer();
    }

    public function visibleBranchIds(): array
    {
        if ($this->canViewAllBranchesForRead()) {
            return $this->organization?->branches()->pluck('id')->all() ?? [];
        }

        return $this->branch_id ? [$this->branch_id] : [];
    }

    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'owner_user_id');
    }

    public function confirmedRequests(): HasMany
    {
        return $this->hasMany(IntakeRequest::class, 'confirmed_by');
    }

    public function rejectedRequests(): HasMany
    {
        return $this->hasMany(IntakeRequest::class, 'rejected_by');
    }

    public function closedDailyClosures(): HasMany
    {
        return $this->hasMany(BranchDailyClosure::class, 'closed_by');
    }
}
