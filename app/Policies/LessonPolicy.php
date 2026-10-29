<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class LessonPolicy
{
    public function viewMaterial(User $user, Lesson $lesson): bool
    {
        return Gate::forUser($user)->allows('viewContent', $lesson->course);
    }

    public function create(User $user, Course $course): bool
    {
        return $user->can(PermissionName::CreateAnyLessons->value)
            || ($user->can(PermissionName::CreateOwnLessons->value) && $course->author_id === $user->id);
    }

    public function update(User $user, Lesson $lesson): bool
    {
        return $user->can(PermissionName::UpdateAnyLessons->value)
            || ($user->can(PermissionName::UpdateOwnLessons->value) && $lesson->course->author_id === $user->id);
    }

    public function delete(User $user, Lesson $lesson): bool
    {
        return $user->can(PermissionName::DeleteAnyLessons->value)
            || ($user->can(PermissionName::DeleteOwnLessons->value) && $lesson->course->author_id === $user->id);
    }
}
