<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTicketRequest extends FormRequest
{
    /**
     * Without "assignee_id" the technician takes the ticket for themselves.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assignee_id' => [
                'nullable',
                'integer',
                // A ticket is only given to an active technician.
                Rule::exists('users', 'id')->where('role', UserRole::Technician->value)->where('is_active', true),
            ],
        ];
    }
}
