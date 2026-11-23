<x-layouts.app title="Journal de l'IA">
    <x-page-header title="Journal de l'IA" subtitle="Une ligne par appel. Le journal ne contient que des chiffres : ni prompt, ni réponse, ni donnée personnelle." />

    @if ($logs->isEmpty())
        <x-card class="text-center">
            <p class="text-sm text-slate-600">Aucun appel à l'IA n'a encore été fait.</p>
        </x-card>
    @else
        <x-table>
            <x-slot:head>
                <th class="px-4 py-3">Date</th>
                <th class="px-4 py-3">Usage</th>
                <th class="px-4 py-3">Ticket</th>
                <th class="px-4 py-3">Modèle</th>
                <th class="px-4 py-3 text-right">Jetons</th>
                <th class="px-4 py-3 text-right">Durée</th>
                <th class="px-4 py-3 text-right">Coût</th>
                <th class="px-4 py-3">Résultat</th>
            </x-slot:head>

            @foreach ($logs as $log)
                <tr>
                    <td class="px-4 py-3 whitespace-nowrap text-slate-600">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                    <td class="px-4 py-3 text-slate-900">{{ __('ai.purposes.'.$log->purpose) }}</td>
                    <td class="px-4 py-3">
                        @if ($log->ticket_id)
                            <a href="{{ route('tickets.show', $log->ticket_id) }}" class="text-indigo-700 hover:underline">#{{ $log->ticket_id }}</a>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-600">{{ $log->provider }} / {{ $log->model }}</td>
                    <td class="px-4 py-3 text-right tabular-nums text-slate-900">{{ number_format($log->input_tokens, 0, ',', ' ') }} + {{ number_format($log->output_tokens, 0, ',', ' ') }}</td>
                    <td class="px-4 py-3 text-right tabular-nums text-slate-900">{{ number_format($log->duration_ms / 1000, 2, ',', ' ') }} s</td>
                    <td class="px-4 py-3 text-right tabular-nums text-slate-900">
                        {{ $log->estimated_cost_usd === null ? '—' : number_format($log->estimated_cost_usd, 4, ',', ' ').' $' }}
                    </td>
                    <td class="px-4 py-3">
                        @if ($log->success)
                            <x-badge color="emerald">Réussi</x-badge>
                        @else
                            <x-badge color="red">{{ Lang::has('ai.errors.'.$log->error_code) ? __('ai.errors.'.$log->error_code) : $log->error_code }}</x-badge>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>

        <div class="mt-4">{{ $logs->links() }}</div>
    @endif
</x-layouts.app>
