<?php

namespace App\Http\Requests\Concerns;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

trait AttachmentRules
{
    /**
     * The same rules wherever files are sent: a few files, small, of harmless types.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function attachmentRules(): array
    {
        $extensions = (array) config('tickets.attachments.extensions');

        return [
            'attachments' => ['nullable', 'array', 'max:'.config('tickets.attachments.max_files_per_upload')],
            'attachments.*' => [
                // The type is read from the content of the file, not from its name.
                File::types($extensions)->max((int) config('tickets.attachments.max_size_kb')),
                $this->extensionMatchesContent(...),
            ],
        ];
    }

    /**
     * The extension must be the one of the real content: a script or a text
     * renamed "capture.png" is refused, even though text files are accepted.
     */
    private function extensionMatchesContent(string $attribute, mixed $file, Closure $fail): void
    {
        if (! $file instanceof UploadedFile) {
            return;
        }

        $normalise = fn (?string $extension): string => str_replace('jpeg', 'jpg', strtolower((string) $extension));

        if ($normalise($file->getClientOriginalExtension()) !== $normalise($file->guessExtension())) {
            $fail('Le contenu de ce fichier ne correspond pas à son extension.');
        }
    }
}
