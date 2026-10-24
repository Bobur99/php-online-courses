<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CourseRequest extends FormRequest
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

        return [
            'title' => [$required, 'string', 'max:255'],
            'description' => [$required, 'string'],
            'price' => [$required, 'integer', 'min:0'],
            'category_id' => [
                $required,
                'integer',
                Rule::exists('categories', 'id'),
            ],
        ];
    }
}