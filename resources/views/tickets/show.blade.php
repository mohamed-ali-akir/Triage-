<x-layouts.app :title="'Ticket #'.$ticket->id">
    <x-page-header :title="$ticket->title" :subtitle="'Ticket #'.$ticket->id.' — créé le '.$ticket->created_at->format('d/m/Y à H:i')">
        <x-ticket.status-badge :status="$ticket->status" />
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Description">
                <p class="whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $ticket->description }}</p>
            </x-card>

            <livewire:ticket-thread :ticket="$ticket" />
        </div>

        <aside class="space-y-6">
            @include('tickets._triage')
            @include('tickets._actions')

            <x-card title="Détails">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-slate-500">Priorité</dt>
                        <dd><x-ticket.priority-badge :priority="$ticket->priority" /></dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-slate-500">Catégorie</dt>
                        <dd class="text-slate-900">{{ $ticket->category?->name ?? 'Non triée' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-slate-500">Demandeur</dt>
                        <dd class="text-right text-slate-900">
                            {{ $ticket->requester->name }}
                            @if ($ticket->requester->department)
                                <span class="block text-xs text-slate-500">{{ $ticket->requester->department->name }}</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-slate-500">Technicien</dt>
                        <dd class="text-slate-900">{{ $ticket->assignee?->name ?? 'Non assigné' }}</dd>
                    </div>
                    @if ($ticket->taken_at)
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-500">Pris en charge</dt>
                            <dd class="text-slate-900">{{ $ticket->taken_at->format('d/m/Y H:i') }}</dd>
                        </div>
                    @endif
                    @if ($ticket->resolved_at)
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-500">Résolu</dt>
                            <dd class="text-slate-900">{{ $ticket->resolved_at->format('d/m/Y H:i') }}</dd>
                        </div>
                    @endif
                    @if ($ticket->closed_at)
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-500">Clos</dt>
                            <dd class="text-slate-900">{{ $ticket->closed_at->format('d/m/Y H:i') }}</dd>
                        </div>
                    @endif
                </dl>
            </x-card>

            @include('tickets._history')
        </aside>
    </div>
</x-layouts.app>
