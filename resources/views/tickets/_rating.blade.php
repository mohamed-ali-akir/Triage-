@php
    $scoreLabels = [5 => 'Très satisfait', 4 => 'Satisfait', 3 => 'Moyen', 2 => 'Insatisfait', 1 => 'Très insatisfait'];
@endphp

@can('rate', $ticket)
    <x-card title="Êtes-vous satisfait de la résolution ?" class="border-indigo-200 bg-indigo-50/40">
        <form method="POST" action="{{ route('tickets.rating.store', $ticket) }}" class="space-y-4">
            @csrf

            <fieldset>
                <legend class="sr-only">Votre note</legend>

                <div class="flex flex-wrap gap-2">
                    @foreach ($scoreLabels as $score => $label)
                        <label class="flex cursor-pointer items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                            <input type="radio" name="score" value="{{ $score }}" required @checked((string) old('score') === (string) $score) class="text-indigo-600">
                            {{ $score }} — {{ $label }}
                        </label>
                    @endforeach
                </div>

                @error('score')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </fieldset>

            <x-form.field name="comment" label="Un commentaire ? (facultatif)">
                <x-form.textarea name="comment" rows="2" maxlength="1000" />
            </x-form.field>

            <x-button>Envoyer mon avis</x-button>
        </form>
    </x-card>
@elseif ($ticket->isRated() && (auth()->user()->isStaff() || $ticket->requester_id === auth()->id()))
    <x-card title="Avis du demandeur">
        <p class="text-sm text-slate-900">
            <span class="text-2xl font-semibold">{{ $ticket->satisfaction_score }}</span> / 5
            <span class="ml-2 text-slate-600">{{ $scoreLabels[$ticket->satisfaction_score] ?? '' }}</span>
        </p>

        @if ($ticket->satisfaction_comment)
            <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $ticket->satisfaction_comment }}</p>
        @endif

        <p class="mt-2 text-xs text-slate-500">Donné le {{ $ticket->rated_at->format('d/m/Y à H:i') }}</p>
    </x-card>
@endcan
