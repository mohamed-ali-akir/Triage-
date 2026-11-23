<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            // Shown in the list of tokens, e.g. "Pixel 8 de Thomas".
            'device_name' => ['required', 'string', 'max:100'],
            // Six-digit code, required only when the account uses two-factor authentication.
            'code' => ['nullable', 'string'],
        ];
    }
}
