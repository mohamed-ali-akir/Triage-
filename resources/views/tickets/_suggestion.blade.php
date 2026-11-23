{{-- Shown to the support staff only: the requester never sees what the AI proposed. --}}
@if (auth()->user()->isStaff())
    @if ($ticket->pendingSuggestion)
        @php($suggestion = $ticket->pendingSuggestion)

        <x-card title="Suggestion de l'IA" class="border-violet-200 bg-violet-50/50">
            <dl class="space-y-2 text-sm">
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-slate-500">Catégorie</dt>
                    <dd class="font-medium text-slate-900">{{ $suggestion->category?->name ?? 'Catégorie supprimée' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-slate-500">Priorité</dt>
                    <dd><x-ticket.priority-badge :priority="$suggestion->priority" /></dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-slate-500">Confiance</dt>
                    <dd class="flex items-center gap-2 text-slate-900">
                        {{ round($suggestion->confidence * 100) }} %
                        @if ($suggestion->hasLowConfidence())
                            <x-badge color="amber">Confiance faible</x-badge>
                        @endif
                    </dd>
                </div>
            </dl>

            @can('triage', $ticket)
                <div class="mt-4 flex gap-2">
                    <form method="POST" action="{{ route('tickets.suggestion.accept', $ticket) }}" class="flex-1">
                        @csrf
                        <x-button class="w-full">Accepter</x-button>
                    </form>

                    <form method="POST" action="{{ route('tickets.suggestion.reject', $ticket) }}" class="flex-1">
                        @csrf
                        <x-button variant="secondary" class="w-full">Rejeter</x-button>
                    </form>
                </div>

                <p class="mt-3 text-xs text-slate-500">
                    Pour corriger la suggestion, modifiez le tri ci-dessous : elle sera notée « modifiée ».
                </p>
            @endcan
        </x-card>
    @elseif (! $ticket->isTriaged() && $ticket->triage_mode === \App\Enums\TriageMode::Manual)
        <x-card class="border-amber-200 bg-amber-50/60">
            <p class="text-sm text-amber-900">
                <x-badge color="amber">Tri manuel</x-badge>
                <span class="ml-1">Aucune suggestion de l'IA pour ce ticket.</span>
            </p>
        </x-card>
    @endif
@endif
