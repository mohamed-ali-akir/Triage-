<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AttachmentRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttachmentsRequest extends FormRequest
{
    use AttachmentRules;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->attachmentRules(),
            'attachments' => ['required', 'array', 'max:'.config('tickets.attachments.max_files_per_upload')],
        ];
    }
}
