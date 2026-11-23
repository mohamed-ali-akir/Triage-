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
@endphp

@if ($transitions->isNotEmpty())
    <x-card title="Actions">
        <div class="space-y-2">
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
