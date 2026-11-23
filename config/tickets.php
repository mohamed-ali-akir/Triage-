<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Target delays (SLA)
    |--------------------------------------------------------------------------
    |
    | Hours allowed, from the creation of the ticket, to take it ("take") and to
    | resolve it ("resolve"), for each priority. These are calendar hours:
    | nights and week-ends are not excluded, which keeps the rule easy to check.
    |
    */

    'sla' => [
        'critical' => ['take' => 1, 'resolve' => 4],
        'high' => ['take' => 4, 'resolve' => 8],
        'normal' => ['take' => 8, 'resolve' => 24],
        'low' => ['take' => 24, 'resolve' => 72],
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    |
    | Files joined to a ticket (a screenshot, a PDF, a log). Only harmless types
    | are accepted, and the files live on a private disk: they are only reachable
    | through the application, which checks who may read the ticket.
    |
    */

    'attachments' => [
        'disk' => 'local',
        'extensions' => ['png', 'jpg', 'jpeg', 'pdf', 'txt'],
        'max_size_kb' => 5 * 1024,
        'max_files_per_upload' => 3,
        'max_per_ticket' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Automatic closing
    |--------------------------------------------------------------------------
    |
    | Days a ticket stays "resolved" before it is closed automatically. During
    | that time the requester can reopen it; once closed, they create a new one.
    |
    */

    'auto_close_days' => (int) env('TICKET_AUTO_CLOSE_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Retention (GDPR)
    |--------------------------------------------------------------------------
    |
    | Months a closed ticket keeps its content. After that, a scheduled command
    | erases the texts and keeps only what statistics need (category, priority, dates).
    |
    */

    'retention_months' => (int) env('TICKET_RETENTION_MONTHS', 24),

];
