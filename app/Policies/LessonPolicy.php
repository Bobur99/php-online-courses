<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;

class LessonPolicy
{
    public function viewMaterial(User $user, Lesson $lesson): bool
    {
        return $this->update($user, $lesson)
            || ($user->role === 'student' && $lesson->course->purchases()
                ->where('student_id', $user->id)->where('status', 'paid')->exists());
    }

    public function update(User $user, Lesson $lesson): bool
    {
        return $user->role === 'admin'
            || (
                $user->role === 'teacher'
                && $lesson->course->author_id === $user->id
            );
    }

    public function delete(User $user, Lesson $lesson): bool
    {
        return $this->update($user, $lesson);
    }
}
