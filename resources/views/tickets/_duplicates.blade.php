{{-- Shown to the support staff only. --}}
@if (auth()->user()->isStaff())
    @php($similarTickets = $ticket->similarOpenTickets())

    @if ($similarTickets->isNotEmpty())
        <x-card title="Tickets similaires ouverts" class="border-orange-200 bg-orange-50/50">
            <p class="mb-3 text-sm text-slate-700">
                {{ trans_choice('{1} Un autre ticket ouvert semble porter sur le même problème.|[2,*] :count autres tickets ouverts semblent porter sur le même problème.', $similarTickets->count()) }}
            </p>

            <ul class="space-y-2 text-sm">
                @foreach ($similarTickets as $similar)
                    <li>
                        <a href="{{ route('tickets.show', $similar) }}" class="font-medium text-indigo-700 hover:underline">
                            #{{ $similar->id }} — {{ $similar->title }}
                        </a>
                        <span class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                            <x-ticket.status-badge :status="$similar->status" />
                            {{ $similar->created_at->diffForHumans() }}
                            @if ($similar->pivot->detected_by === 'ai')
                                <x-badge color="violet">Confirmé par l'IA</x-badge>
                            @else
                                <x-badge>Mots communs</x-badge>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
@endif
