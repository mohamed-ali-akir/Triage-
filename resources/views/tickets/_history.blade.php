@php
    use App\Enums\TicketEventType;
@endphp

<x-card title="Historique">
    <ol class="space-y-3 text-sm">
        @foreach ($ticket->events as $event)
            <li class="flex gap-3">
                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-slate-300" aria-hidden="true"></span>

                <div>
                    <p class="text-slate-800">
                        @if ($event->type === TicketEventType::Created)
                            Ticket créé
                        @elseif ($event->oldLabel() === null)
                            {{ $event->type->label() }} : {{ $event->newLabel() }}
                        @else
                            {{ $event->type->label() }} : {{ $event->oldLabel() }} → {{ $event->newLabel() }}
                        @endif
                    </p>
                    <p class="text-xs text-slate-500">
                        {{ $event->created_at->format('d/m/Y H:i') }} — {{ $event->user?->name ?? 'Système' }}
                    </p>
                </div>
            </li>
        @endforeach
    </ol>
</x-card>
