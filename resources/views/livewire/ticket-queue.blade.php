<div>
    @php
        $inputClasses = 'block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500';
    @endphp

    <div class="mb-4 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-6">
        <div class="lg:col-span-2">
            <label for="search" class="mb-1 block text-xs font-medium text-slate-600">Recherche</label>
            <input id="search" type="search" wire:model.live.debounce.300ms="search" placeholder="Titre du ticket" class="{{ $inputClasses }}">
        </div>

        <div>
            <label for="scope" class="mb-1 block text-xs font-medium text-slate-600">Affectation</label>
            <select id="scope" wire:model.live="scope" class="{{ $inputClasses }}">
                <option value="all">Tous</option>
                <option value="mine">Mes tickets</option>
                <option value="unassigned">Non assignés</option>
            </select>
        </div>

        <div>
            <label for="status" class="mb-1 block text-xs font-medium text-slate-600">Statut</label>
            <select id="status" wire:model.live="status" class="{{ $inputClasses }}">
                <option value="">Tous sauf clos</option>
                @foreach ($statuses as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="category" class="mb-1 block text-xs font-medium text-slate-600">Catégorie</label>
            <select id="category" wire:model.live="category" class="{{ $inputClasses }}">
                <option value="">Toutes</option>
                @foreach ($categories as $option)
                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="priority" class="mb-1 block text-xs font-medium text-slate-600">Priorité</label>
            <select id="priority" wire:model.live="priority" class="{{ $inputClasses }}">
                <option value="">Toutes</option>
                @foreach ($priorities as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($tickets->isEmpty())
        <x-card class="text-center">
            <p class="text-sm text-slate-600">Aucun ticket ne correspond à ces filtres.</p>
            <button type="button" wire:click="resetFilters" class="mt-2 text-sm font-medium text-indigo-600 hover:underline">
                Réinitialiser les filtres
            </button>
        </x-card>
    @else
        <x-table>
            <x-slot:head>
                <th class="px-4 py-3">N°</th>
                <th class="px-4 py-3">Ticket</th>
                <th class="px-4 py-3">Statut</th>
                <th class="px-4 py-3">Priorité</th>
                <th class="px-4 py-3">Catégorie</th>
                <th class="px-4 py-3">Technicien</th>
                <th class="px-4 py-3">Créé</th>
            </x-slot:head>

            @foreach ($tickets as $ticket)
                <tr wire:key="ticket-{{ $ticket->id }}">
                    <td class="px-4 py-3 text-slate-500">#{{ $ticket->id }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('tickets.show', $ticket) }}" class="font-medium text-indigo-700 hover:underline">
                            {{ $ticket->title }}
                        </a>
                        <span class="block text-xs text-slate-500">{{ $ticket->requester->name }}</span>
                    </td>
                    <td class="px-4 py-3"><x-ticket.status-badge :status="$ticket->status" /></td>
                    <td class="px-4 py-3">
                        @if ($ticket->isTriaged())
                            <x-ticket.priority-badge :priority="$ticket->priority" />
                        @else
                            <x-badge color="amber">À trier</x-badge>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-600">{{ $ticket->category?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $ticket->assignee?->name ?? '—' }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-slate-600" title="{{ $ticket->created_at->format('d/m/Y H:i') }}">
                        {{ $ticket->created_at->diffForHumans() }}
                    </td>
                </tr>
            @endforeach
        </x-table>

        <div class="mt-4">{{ $tickets->links() }}</div>
    @endif
</div>
