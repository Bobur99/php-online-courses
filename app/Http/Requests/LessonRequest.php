<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post')
            ? 'required'
            : 'sometimes';

        $courseId = $this->route('course')?->id
            ?? $this->route('lesson')?->course_id;

        return [
            'title' => [$required, 'string', 'max:255'],
            'content' => [$required, 'string'],
            'order_number' => [
                $required,
                'integer',
                'min:1',
                Rule::unique('lessons', 'order_number')
                    ->where(fn ($query) => $query->where('course_id', $courseId))
                    ->ignore($this->route('lesson')),
            ],
            'file' => [
                'sometimes',
                'nullable',
                'file',
                'mimes:mp4,webm,mov,pdf,doc,docx,ppt,pptx,jpg,jpeg,png,webp,gif',
                'max:10240',
            ],
        ];
    }
}
