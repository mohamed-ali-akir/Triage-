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

    // "Give this ticket to a colleague" is offered to the support staff once the ticket is triaged.
    $canAssignTo = $ticket->status->acceptsAssignment() && $ticket->isTriaged() && $technicians->isNotEmpty();
@endphp

@if ($transitions->isNotEmpty() || $canAssign || $canAssignTo)
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

            @if ($canAssignTo)
                <form method="POST" action="{{ route('tickets.assign.update', $ticket) }}" class="flex gap-2">
                    @csrf
                    @method('PATCH')

                    <label for="assignee_id" class="sr-only">Assigner à un technicien</label>
                    <select id="assignee_id" name="assignee_id" required
                            class="block w-full min-w-0 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        <option value="">Assigner à…</option>
                        @foreach ($technicians as $technician)
                            <option value="{{ $technician->id }}">{{ $technician->name }}</option>
                        @endforeach
                    </select>

                    <x-button variant="secondary">Assigner</x-button>
                </form>
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
