<?php

namespace App\Actions\Tickets;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class StoreTicketAttachments
{
    /**
     * Store the uploaded files on the private disk and attach them to the ticket.
     * The files were validated before (type, size, number): see AttachmentRules.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function handle(Ticket $ticket, User $uploader, array $files): void
    {
        foreach ($files as $file) {
            // store() names the file with a random hash and an extension taken from its
            // real content: the name sent by the browser never reaches the disk.
            $path = $file->store("tickets/{$ticket->id}", config('tickets.attachments.disk'));

            $ticket->attachments()->create([
                'user_id' => $uploader->id,
                'original_name' => $this->displayName($file),
                'path' => $path,
                'mime_type' => (string) $file->getMimeType(),
                'size' => (int) $file->getSize(),
            ]);
        }
    }

    /**
     * The name shown in the application: no directory, no control character, bounded length.
     */
    private function displayName(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name));

        return $name === '' ? 'fichier' : mb_substr($name, -150);
    }
}
