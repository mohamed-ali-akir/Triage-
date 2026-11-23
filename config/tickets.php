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

];
