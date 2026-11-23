<x-layouts.app title="Mes tickets">
    <x-page-header title="Mes tickets" subtitle="Les demandes que vous avez envoyées au support informatique.">
        <x-button-link :href="route('tickets.create')">Nouveau ticket</x-button-link>
    </x-page-header>

    @if ($tickets->isEmpty())
        <x-card class="text-center">
            <p class="text-sm text-slate-600">Vous n'avez encore envoyé aucun ticket.</p>
        </x-card>
    @else
        <x-table>
            <x-slot:head>
                <th class="px-4 py-3">N°</th>
                <th class="px-4 py-3">Titre</th>
                <th class="px-4 py-3">Statut</th>
                <th class="px-4 py-3">Catégorie</th>
                <th class="px-4 py-3">Technicien</th>
                <th class="px-4 py-3">Créé le</th>
            </x-slot:head>

            @foreach ($tickets as $ticket)
                <tr>
                    <td class="px-4 py-3 text-slate-500">#{{ $ticket->id }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('tickets.show', $ticket) }}" class="font-medium text-indigo-700 hover:underline">
                            {{ $ticket->title }}
                        </a>
                    </td>
                    <td class="px-4 py-3"><x-ticket.status-badge :status="$ticket->status" /></td>
                    <td class="px-4 py-3 text-slate-600">{{ $ticket->category?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $ticket->assignee?->name ?? '—' }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-slate-600">{{ $ticket->created_at->format('d/m/Y H:i') }}</td>
                </tr>
            @endforeach
        </x-table>

        <div class="mt-4">{{ $tickets->links() }}</div>
    @endif
</x-layouts.app>
