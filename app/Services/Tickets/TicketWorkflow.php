<?php

namespace App\Services\Tickets;

use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TriageMode;
use App\Exceptions\InvalidTicketTransition;
use App\Exceptions\TicketNotAssignable;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only place in the application that changes the state of a ticket.
 * Every change is validated, then written to the history in the same transaction.
 */
class TicketWorkflow
{
    public function __construct(private readonly TicketNotifier $notifier) {}

    /**
     * Move a ticket to another status.
     *
     * @param  User|null  $actor  null when the system makes the change
     *
     * @throws InvalidTicketTransition
     */
    public function transition(Ticket $ticket, TicketStatus $to, ?User $actor = null): Ticket
    {
        $from = $ticket->status;

        if (! $from->canTransitionTo($to)) {
            throw InvalidTicketTransition::between($from, $to);
        }

        return DB::transaction(function () use ($ticket, $from, $to, $actor): Ticket {
            $ticket->status = $to;
            $this->updateDates($ticket, $from, $to);
            $ticket->save();

            $this->record($ticket, TicketEventType::StatusChanged, $from->value, $to->value, $actor);
            $this->notifier->statusChanged($ticket, $to, $actor);

            return $ticket;
        });
    }

    /**
     * The triage is prepared (by the AI, or not at all): the ticket now waits
     * for a technician. This is the only way out of the "new" status.
     */
    public function queueForValidation(Ticket $ticket, TriageMode $mode): Ticket
    {
        $ticket->triage_mode = $mode;

        return $this->transition($ticket, TicketStatus::PendingValidation);
    }

    /**
     * Set the category and the priority of a ticket (manual triage).
     * Only the values that really change are written to the history.
     */
    public function triage(Ticket $ticket, Category $category, TicketPriority $priority, ?User $actor = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $category, $priority, $actor): Ticket {
            $oldCategoryId = $ticket->category_id;
            $oldPriority = $ticket->priority;

            $ticket->category_id = $category->id;
            $ticket->priority = $priority;
            $ticket->save();

            if ($oldCategoryId !== $category->id) {
                $this->record(
                    $ticket,
                    TicketEventType::CategoryChanged,
                    $oldCategoryId === null ? null : (string) $oldCategoryId,
                    (string) $category->id,
                    $actor,
                );
            }

            if ($oldPriority !== $priority) {
                $this->record($ticket, TicketEventType::PriorityChanged, $oldPriority?->value, $priority->value, $actor);
            }

            return $ticket;
        });
    }

    /**
     * Give the ticket to a technician.
     * A ticket waiting for validation becomes "assigned"; a ticket already
     * in progress keeps its status and only changes hands.
     *
     * @throws TicketNotAssignable
     */
    public function assign(Ticket $ticket, User $technician): Ticket
    {
        if (! $ticket->status->acceptsAssignment()) {
            throw TicketNotAssignable::wrongStatus($ticket->status);
        }

        if (! $ticket->isTriaged()) {
            throw TicketNotAssignable::notTriaged();
        }

        return DB::transaction(function () use ($ticket, $technician): Ticket {
            $previousAssigneeId = $ticket->assignee_id;

            $ticket->assignee_id = $technician->id;
            $ticket->save();

            $this->record(
                $ticket,
                TicketEventType::Assigned,
                $previousAssigneeId === null ? null : (string) $previousAssigneeId,
                (string) $technician->id,
                $technician,
            );

            if ($ticket->status === TicketStatus::PendingValidation) {
                $this->transition($ticket, TicketStatus::Assigned, $technician);
            }

            return $ticket;
        });
    }

    /**
     * Write the first line of the history when a ticket is created.
     */
    public function recordCreation(Ticket $ticket, User $requester): void
    {
        $this->record($ticket, TicketEventType::Created, null, null, $requester);
    }

    /**
     * Keep the three tracked dates in sync with the status.
     */
    private function updateDates(Ticket $ticket, TicketStatus $from, TicketStatus $to): void
    {
        if ($to === TicketStatus::Assigned) {
            $ticket->taken_at = now();
        }

        if ($to === TicketStatus::Resolved) {
            $ticket->resolved_at = now();
        }

        // A reopened ticket is not resolved any more.
        if ($from === TicketStatus::Resolved && $to === TicketStatus::InProgress) {
            $ticket->resolved_at = null;
        }

        if ($to === TicketStatus::Closed) {
            $ticket->closed_at = now();
        }
    }

    private function record(Ticket $ticket, TicketEventType $type, ?string $old, ?string $new, ?User $actor): void
    {
        $ticket->events()->create([
            'user_id' => $actor?->id,
            'type' => $type,
            'old_value' => $old,
            'new_value' => $new,
        ]);
    }
}
