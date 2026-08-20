<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    public const ADMIN = 'admin';

    public const PROCTOR = 'proctor';

    public const INSTRUCTOR = 'instructor';

    public const STUDENT = 'student';

    protected $fillable = ['key', 'name', 'description'];

    /**
     * Password length validation rules for the given role key. Students get
     * an exact 5-character rule; every other role (Proctor/Instructor/Admin)
     * keeps the general 5-8 range. Centralized here so StoreUserRequest and
     * UpdateUserRequest (and any future caller) resolve the same policy
     * instead of each hardcoding it.
     *
     * @return array<int, string>
     */
    public static function passwordLengthRules(?string $roleKey): array
    {
        return $roleKey === self::STUDENT ? ['size:5'] : ['min:5', 'max:8'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'current_role_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }
}
