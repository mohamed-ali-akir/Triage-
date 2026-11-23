<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    /**
     * E-mail addresses are stored in lower case, which is how the login form looks them up.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower((string) $this->input('email'))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editedUser())],
            'role' => ['required', Rule::enum(UserRole::class)],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'is_active' => ['required', 'boolean'],
            // Left empty, the password is not changed.
            'password' => ['nullable', 'string', Password::defaults()],
        ];
    }

    /**
     * An administrator must not lock themselves out of the administration.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->editedUser()->is($this->user())) {
                    return;
                }

                if ($this->input('role') !== UserRole::Admin->value) {
                    $validator->errors()->add('role', 'Vous ne pouvez pas retirer votre propre rôle d\'administrateur.');
                }

                if (! $this->boolean('is_active')) {
                    $validator->errors()->add('is_active', 'Vous ne pouvez pas désactiver votre propre compte.');
                }
            },
        ];
    }

    private function editedUser(): User
    {
        /** @var User $user */
        $user = $this->route('user');

        return $user;
    }
}
