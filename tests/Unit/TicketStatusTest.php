<?php

use App\Enums\TicketStatus;

/*
 * The state machine, written out as a table.
 * Every pair that is not listed here must be refused.
 */
const ALLOWED_TRANSITIONS = [
    ['new', 'pending_validation'],
    ['pending_validation', 'assigned'],
    ['assigned', 'in_progress'],
    ['in_progress', 'waiting_user'],
    ['in_progress', 'resolved'],
    ['waiting_user', 'in_progress'],
    ['resolved', 'closed'],
    ['resolved', 'in_progress'],
];

test('an allowed transition is accepted', function (string $from, string $to) {
    expect(TicketStatus::from($from)->canTransitionTo(TicketStatus::from($to)))->toBeTrue();
})->with(ALLOWED_TRANSITIONS);

test('every other transition is refused', function () {
    $refused = 0;

    foreach (TicketStatus::cases() as $from) {
        foreach (TicketStatus::cases() as $to) {
            if (in_array([$from->value, $to->value], ALLOWED_TRANSITIONS, true)) {
                continue;
            }

            expect($from->canTransitionTo($to))
                ->toBeFalse("{$from->value} → {$to->value} should be refused");

            $refused++;
        }
    }

    // 7 statuses give 49 pairs; 8 are allowed.
    expect($refused)->toBe(41);
});

test('a closed ticket cannot move anywhere', function () {
    expect(TicketStatus::Closed->allowedTransitions())->toBe([])
        ->and(TicketStatus::Closed->isOpen())->toBeFalse();
});

test('every status has a French label', function (TicketStatus $status) {
    expect($status->label())->not->toBeEmpty();
})->with(TicketStatus::cases());
