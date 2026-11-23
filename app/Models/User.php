<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

// The role and the active flag are never mass assignable: no form can grant itself a role.
#[Fillable(['name', 'email', 'password', 'department_id'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Mirror the database defaults so a freshly created model is complete in memory.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => UserRole::User->value,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * A token of the mobile application must not survive a new password,
     * a change of role or the deactivation of the account.
     */
    protected static function booted(): void
    {
        static::updated(function (User $user): void {
            $deactivated = $user->wasChanged('is_active') && ! $user->is_active;

            if ($deactivated || $user->wasChanged(['password', 'role'])) {
                $user->tokens()->delete();
            }
        });
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Tickets this user asked for.
     *
     * @return HasMany<Ticket, $this>
     */
    public function requestedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'requester_id');
    }

    /**
     * Tickets this technician is working on.
     *
     * @return HasMany<Ticket, $this>
     */
    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assignee_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isTechnician(): bool
    {
        return $this->role === UserRole::Technician;
    }

    /**
     * Staff members are the people who work on the support side.
     */
    public function isStaff(): bool
    {
        return $this->isTechnician() || $this->isAdmin();
    }

    /**
     * What the user must do before the account can be used, or null when nothing is pending:
     * replace a password chosen by an administrator, or enable two-factor authentication
     * when the application requires it from the support staff.
     */
    public function pendingSecurityStep(): ?string
    {
        if ($this->must_change_password) {
            return 'Votre mot de passe a été choisi par un administrateur : remplacez-le pour continuer.';
        }

        if (config('security.require_two_factor_for_staff') && $this->isStaff() && ! $this->hasEnabledTwoFactorAuthentication()) {
            return 'La double authentification est obligatoire pour l\'équipe du support : activez-la pour continuer.';
        }

        return null;
    }
}
