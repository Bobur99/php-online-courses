<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['teacher', 'admin'], true);
    }

    public function update(User $user, Course $course): bool
    {
        return $user->role === 'admin'
            || ($user->role === 'teacher' && $course->author_id === $user->id);
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->role === 'admin'
            || ($user->role === 'teacher' && $course->author_id === $user->id);
    }

    public function publish(User $user, Course $course): bool
    {
        return $user->role === 'admin'
            || ($user->role === 'teacher' && $course->author_id === $user->id);
    }
}
