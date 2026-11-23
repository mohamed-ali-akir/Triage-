@php
    use App\Enums\TicketStatus;

    // One button per transition that the state machine allows AND the current user may perform.
    $transitions = collect($ticket->status->allowedTransitions())
        ->filter(fn (TicketStatus $target) => auth()->user()->can('changeStatus', [$ticket, $target]));

    $labelFor = fn (TicketStatus $target): string => match ($target) {
        TicketStatus::InProgress => match ($ticket->status) {
            TicketStatus::Resolved => 'Rouvrir le ticket',
            TicketStatus::WaitingUser => 'Reprendre le traitement',
            default => 'Commencer le traitement',
        },
        TicketStatus::WaitingUser => 'Attendre une réponse du demandeur',
        TicketStatus::Resolved => 'Marquer comme résolu',
        TicketStatus::Closed => 'Clore le ticket',
        default => $target->label(),
    };

    // "Take this ticket" is offered to a technician when the ticket can change hands.
    $canAssign = $ticket->status->acceptsAssignment() && auth()->user()->can('assign', $ticket);
@endphp

@if ($transitions->isNotEmpty() || $canAssign)
    <x-card title="Actions">
        <div class="space-y-2">
            @if ($canAssign)
                <form method="POST" action="{{ route('tickets.assign.update', $ticket) }}">
                    @csrf
                    @method('PATCH')

                    <x-button class="w-full" :disabled="! $ticket->isTriaged()">
                        {{ $ticket->assignee_id === null ? 'Prendre en charge' : 'Reprendre ce ticket' }}
                    </x-button>
                </form>

                @unless ($ticket->isTriaged())
                    <p class="text-xs text-slate-500">Triez d'abord le ticket : catégorie et priorité.</p>
                @endunless
            @endif

            @foreach ($transitions as $target)
                <form method="POST" action="{{ route('tickets.status.update', $ticket) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ $target->value }}">

                    <x-button class="w-full" :variant="$loop->first ? 'primary' : 'secondary'">
                        {{ $labelFor($target) }}
                    </x-button>
                </form>
            @endforeach
        </div>
    </x-card>
@endif
