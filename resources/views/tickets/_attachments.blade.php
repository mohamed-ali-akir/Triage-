@php($extensions = implode(', ', (array) config('tickets.attachments.extensions')))

@if ($ticket->attachments->isNotEmpty() || auth()->user()->can('attach', $ticket))
    <x-card title="Pièces jointes">
        @if ($ticket->attachments->isEmpty())
            <p class="text-sm text-slate-500">Aucune pièce jointe.</p>
        @else
            <ul class="space-y-2 text-sm">
                @foreach ($ticket->attachments as $attachment)
                    <li class="flex flex-wrap items-baseline justify-between gap-x-4">
                        <a href="{{ route('tickets.attachments.show', [$ticket, $attachment]) }}" class="break-all font-medium text-indigo-700 hover:underline">
                            {{ $attachment->original_name }}
                        </a>
                        <span class="text-xs text-slate-500">
                            {{ $attachment->humanSize() }} — {{ $attachment->uploader?->name ?? 'Compte supprimé' }}, {{ $attachment->created_at->format('d/m/Y H:i') }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif

        @can('attach', $ticket)
            <form method="POST" action="{{ route('tickets.attachments.store', $ticket) }}" enctype="multipart/form-data" class="mt-4 space-y-2 border-t border-slate-200 pt-4">
                @csrf

                <label for="attachments" class="block text-sm font-medium text-slate-700">Ajouter des fichiers</label>
                <input id="attachments" type="file" name="attachments[]" multiple required
                       accept="{{ collect(config('tickets.attachments.extensions'))->map(fn ($extension) => '.'.$extension)->implode(',') }}"
                       class="block w-full text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700">

                <p class="text-xs text-slate-500">
                    {{ config('tickets.attachments.max_files_per_upload') }} fichiers au plus, {{ (int) (config('tickets.attachments.max_size_kb') / 1024) }} Mo chacun. Types acceptés : {{ $extensions }}.
                </p>

                @error('attachments')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
                @error('attachments.*')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror

                <x-button variant="secondary">Joindre</x-button>
            </form>
        @endcan
    </x-card>
@endif
