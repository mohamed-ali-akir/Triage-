<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

/**
 * A file joined to a ticket: a screenshot of the error, a PDF, a log.
 */
#[Fillable(['user_id', 'original_name', 'path', 'mime_type', 'size'])]
class TicketAttachment extends Model
{
    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function humanSize(): string
    {
        return (string) Number::fileSize($this->size, precision: 1);
    }
}
