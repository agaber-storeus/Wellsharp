<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPublicUlid, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['wellsharp_id', 'email', 'password'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'password_ciphertext',
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
            'status' => UserStatus::class,
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function currentRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'current_role_id');
    }

    public function examControlCredential(): HasOne
    {
        return $this->hasOne(ExamControlCredential::class);
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'student_user_id');
    }

    public function classStaffAssignments(): HasMany
    {
        return $this->hasMany(ClassStaffAssignment::class);
    }

    public function proctoredClasses(): HasMany
    {
        return $this->hasMany(TrainingClass::class, 'proctor_id');
    }

    public function instructedClasses(): HasMany
    {
        return $this->hasMany(TrainingClass::class, 'instructor_id');
    }

    public function groupMemberships(): HasMany
    {
        return $this->hasMany(GroupMembership::class, 'student_user_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_memberships', 'student_user_id', 'group_id')
            ->wherePivot('status', 'active')
            ->withPivot(['public_id', 'status', 'joined_at', 'removed_at']);
    }

    public function examAttempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class, 'student_user_id');
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active && is_null($this->archived_at);
    }

    public function isAdmin(): bool
    {
        return $this->currentRole?->key === Role::ADMIN;
    }

    public function hasRole(string $role): bool
    {
        return $this->currentRole?->key === $role;
    }

    /**
     * Sets the login password (hashed via the model cast) and, for Students only, a
     * separately encrypted copy so Admin/Proctor/Instructor can look it up later.
     * Reason: Student accounts are shared by staff at check-in; students frequently
     * cannot recover a forgotten password themselves. Not applied to staff roles.
     */
    public function setPasswordAndCiphertext(string $plainPassword, string $roleKey): void
    {
        $this->password = $plainPassword;
        $this->password_ciphertext = Crypt::encryptString($plainPassword);
    }

    public function revealPassword(): ?string
    {
        if (blank($this->password_ciphertext)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->password_ciphertext);
        } catch (DecryptException) {
            return null;
        }
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->profile?->fullName() ?: $this->wellsharp_id;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
