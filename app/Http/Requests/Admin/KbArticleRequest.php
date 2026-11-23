<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class KbArticleRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:150'],
            'content' => ['required', 'string', 'min:20', 'max:20000'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'is_published' => ['required', 'boolean'],
            'is_public' => ['required', 'boolean'],
        ];
    }
}
