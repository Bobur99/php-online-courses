<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ViewAnyCourses->value);
    }

    public function view(?User $user, Course $course): bool
    {
        return $course->status === 'published'
            || ($user !== null && (
                $user->can(PermissionName::ViewAnyCourses->value)
                || ($user->can(PermissionName::ViewOwnCourses->value) && $course->author_id === $user->id)
                || $course->purchases()->where('student_id', $user->id)->where('status', 'paid')->exists()
            ));
    }

    public function viewContent(User $user, Course $course): bool
    {
        return $user->can(PermissionName::ViewAnyLessons->value)
            || ($user->can(PermissionName::ViewOwnLessons->value) && $course->author_id === $user->id)
            || ($user->can(PermissionName::StudyCourses->value)
                && $course->purchases()->where('student_id', $user->id)->where('status', 'paid')->exists());
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::CreateCourses->value);
    }

    public function update(User $user, Course $course): bool
    {
        return $user->can(PermissionName::UpdateAnyCourses->value)
            || ($user->can(PermissionName::UpdateOwnCourses->value) && $course->author_id === $user->id);
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->can(PermissionName::DeleteAnyCourses->value)
            || ($user->can(PermissionName::DeleteOwnCourses->value) && $course->author_id === $user->id);
    }

    public function publish(User $user, Course $course): bool
    {
        return $user->can(PermissionName::PublishAnyCourses->value)
            || ($user->can(PermissionName::PublishOwnCourses->value) && $course->author_id === $user->id);
    }
}
