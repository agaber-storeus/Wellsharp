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

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'current_role_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }
}
