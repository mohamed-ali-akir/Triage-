<section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="mb-4 text-base font-semibold text-slate-900">Discussion</h2>

    {{-- Refresh the messages regularly so that both sides see new answers. --}}
    <div wire:poll.15s class="space-y-4">
        @forelse ($messages as $message)
            <article wire:key="message-{{ $message->id }}"
                     @class([
                         'rounded-md border px-4 py-3 text-sm',
                         'border-amber-200 bg-amber-50' => $message->is_internal,
                         'border-slate-200 bg-slate-50' => ! $message->is_internal,
                     ])>
                <header class="mb-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    <span class="font-semibold text-slate-800">{{ $message->author->name }}</span>
                    <span>{{ $message->created_at->format('d/m/Y H:i') }}</span>

                    @if ($message->is_internal)
                        <x-badge color="amber">Note interne</x-badge>
                    @endif
                </header>

                <p class="whitespace-pre-line leading-relaxed text-slate-700">{{ $message->body }}</p>
            </article>
        @empty
            <p class="text-sm text-slate-500">Aucun message pour le moment.</p>
        @endforelse
    </div>

    @can('reply', $ticket)
        <form wire:submit="post" class="mt-6 space-y-3 border-t border-slate-200 pt-4">
            <div class="space-y-1">
                <label for="body" class="block text-sm font-medium text-slate-700">Votre message</label>
                <textarea id="body" wire:model="body" rows="4" required maxlength="5000"
                          class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"></textarea>

                @error('body')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                @can('writeInternalNote', $ticket)
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" wire:model="isInternal" class="rounded border-slate-300 text-indigo-600">
                        Note interne (invisible du demandeur)
                    </label>
                @else
                    <span></span>
                @endcan

                <x-button wire:loading.attr="disabled">Envoyer</x-button>
            </div>
        </form>
    @else
        <p class="mt-6 border-t border-slate-200 pt-4 text-sm text-slate-500">
            @if ($ticket->status->isOpen())
                Vous consultez ce ticket en lecture seule.
            @else
                Ce ticket est clos : la discussion est fermée.
            @endif
        </p>
    @endcan
</section>
