<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function before(User $actor): ?bool
    {
        return $actor->isAdmin() ? true : null;
    }

    public function viewAny(User $actor): bool
    {
        return false;
    }

    public function view(User $actor, Course $course): bool
    {
        return false;
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, Course $course): bool
    {
        return false;
    }

    public function delete(User $actor, Course $course): bool
    {
        return false;
    }
}
