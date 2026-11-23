<?php

namespace App\Http\Resources;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * The list returns the summary; the detail adds the description, the thread
     * and what the technician is allowed to do next.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'priority' => $this->priority === null ? null : ['value' => $this->priority->value, 'label' => $this->priority->label()],
            'category' => $this->category === null ? null : ['id' => $this->category->id, 'name' => $this->category->name],
            'requester' => new UserResource($this->requester),
            'assignee' => $this->assignee === null ? null : new UserResource($this->assignee),
            'is_late' => $this->isLate(),
            'resolve_deadline' => $this->resolveDeadline()?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Detail only (see TicketController::show).
            'description' => $this->when($this->relationLoaded('messages'), fn () => $this->description),
            'messages' => TicketMessageResource::collection($this->whenLoaded('messages')),
            'allowed_statuses' => $this->when($this->relationLoaded('messages'), fn () => $this->allowedStatuses($request)),
            'can_reply' => $this->when($this->relationLoaded('messages'), fn () => $request->user()->can('reply', $this->resource)),
        ];
    }

    /**
     * The statuses this user may move the ticket to: allowed by the state machine AND by the policy.
     * The application shows one button per entry.
     *
     * @return list<array{value: string, label: string}>
     */
    private function allowedStatuses(Request $request): array
    {
        $allowed = array_filter(
            $this->status->allowedTransitions(),
            fn (TicketStatus $target): bool => $request->user()->can('changeStatus', [$this->resource, $target]),
        );

        return array_values(array_map(
            fn (TicketStatus $target): array => ['value' => $target->value, 'label' => $target->label()],
            $allowed,
        ));
    }
}
