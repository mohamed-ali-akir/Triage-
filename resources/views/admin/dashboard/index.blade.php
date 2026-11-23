@php
    use App\Enums\AiSuggestionStatus;
    use App\Support\Duration;

    $onTimeRate = $sla['resolved_total'] > 0 ? round($sla['resolved_on_time'] / $sla['resolved_total'] * 100) : null;

    $suggestionTotal = collect($suggestions)->sum('count');
    $suggestionColors = [
        AiSuggestionStatus::Accepted->value => 'var(--viz-series-1)',
        AiSuggestionStatus::Modified->value => 'var(--viz-series-2)',
        AiSuggestionStatus::Rejected->value => 'var(--viz-series-3)',
        AiSuggestionStatus::Pending->value => 'var(--viz-series-4)',
    ];
    // Fixed order: the decisions first, the suggestions still waiting last.
    $suggestionRows = collect([AiSuggestionStatus::Accepted, AiSuggestionStatus::Modified, AiSuggestionStatus::Rejected, AiSuggestionStatus::Pending])
        ->map(fn (AiSuggestionStatus $status) => collect($suggestions)->firstWhere('status', $status));

    $maxTokens = max(1, collect($aiUsageByDay)->max(fn (array $day) => $day['input_tokens'] + $day['output_tokens']));
@endphp

<x-layouts.app title="Tableau de bord">
    <x-page-header title="Tableau de bord" subtitle="L'activité du support et l'usage de l'IA." />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-stat-tile label="Tickets ouverts" :value="$sla['open']" />
        <x-stat-tile label="En retard" :value="$sla['late']" hint="Parmi les tickets ouverts" />
        <x-stat-tile label="Prise en charge" :value="Duration::humanize($averageMinutesToTake)" hint="Délai moyen" />
        <x-stat-tile label="Résolution" :value="Duration::humanize($averageMinutesToResolve)" hint="Délai moyen" />
        <x-stat-tile label="Résolus dans les délais"
                     :value="$onTimeRate === null ? '—' : $onTimeRate.' %'"
                     :hint="$sla['resolved_on_time'].' sur '.$sla['resolved_total']" />
        <x-stat-tile label="Satisfaction"
                     :value="$satisfaction['average'] === null ? '—' : number_format($satisfaction['average'], 1, ',', ' ').' / 5'"
                     :hint="$satisfaction['count'].' avis'" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-card title="Tickets par statut">
            <x-bar-list :rows="$ticketsByStatus" />
        </x-card>

        <x-card title="Tickets par catégorie">
            @if ($ticketsByCategory === [])
                <p class="text-sm text-slate-500">Aucun ticket pour le moment.</p>
            @else
                <x-bar-list :rows="$ticketsByCategory" />
            @endif
        </x-card>
    </div>

    <x-card title="Satisfaction des demandeurs" class="mt-6">
        @if ($satisfaction['count'] === 0)
            <p class="text-sm text-slate-500">Aucun avis pour le moment. Le demandeur donne le sien quand son ticket est résolu.</p>
        @else
            <x-bar-list :rows="$satisfaction['by_score']" />
        @endif
    </x-card>

    <x-card title="Suggestions de l'IA : décisions des techniciens" class="mt-6">
        @if ($suggestionTotal === 0)
            <p class="text-sm text-slate-500">L'IA n'a encore fait aucune suggestion.</p>
        @else
            {{-- One whole split into parts: a thin segmented bar; the list below carries names and figures. --}}
            <div class="viz">
                <div class="flex h-3 gap-0.5" role="img" aria-label="Répartition des suggestions de l'IA">
                    @foreach ($suggestionRows as $row)
                        @if ($row['count'] > 0)
                            <span class="first:rounded-l last:rounded-r"
                                  style="flex-grow: {{ $row['count'] }}; background-color: {{ $suggestionColors[$row['status']->value] }}"
                                  title="{{ $row['status']->label() }} : {{ $row['count'] }}"></span>
                        @endif
                    @endforeach
                </div>

                <ul class="mt-4 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($suggestionRows as $row)
                        <li class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 text-slate-600">
                                <span class="viz-swatch" style="background-color: {{ $suggestionColors[$row['status']->value] }}"></span>
                                {{ $row['status']->label() }}
                            </span>
                            <span class="tabular-nums text-slate-900">
                                {{ $row['count'] }}
                                <span class="text-slate-500">({{ round($row['count'] / $suggestionTotal * 100) }} %)</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </x-card>

    <x-card title="Consommation de l'IA : 14 derniers jours" class="mt-6">
        <div class="viz overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="py-2 pr-4">Jour</th>
                        <th class="py-2 pr-4 text-right">Appels</th>
                        <th class="py-2 pr-4 text-right">Échecs</th>
                        <th class="py-2 pr-4 text-right">Jetons en entrée</th>
                        <th class="py-2 pr-4 text-right">Jetons en sortie</th>
                        <th class="py-2 pr-4 text-right">Coût estimé</th>
                        <th class="w-1/4 py-2">Jetons</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($aiUsageByDay as $day)
                        @php($tokens = $day['input_tokens'] + $day['output_tokens'])
                        <tr>
                            <td class="py-2 pr-4 whitespace-nowrap text-slate-600">{{ $day['day']->format('d/m') }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums text-slate-900">{{ $day['calls'] }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums text-slate-900">{{ $day['failures'] }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums text-slate-900">{{ number_format($day['input_tokens'], 0, ',', ' ') }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums text-slate-900">{{ number_format($day['output_tokens'], 0, ',', ' ') }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums text-slate-900">{{ number_format($day['cost'], 4, ',', ' ') }} $</td>
                            <td class="py-2" title="{{ $day['day']->format('d/m') }} : {{ number_format($tokens, 0, ',', ' ') }} jetons">
                                @if ($tokens > 0)
                                    <span class="viz-bar block" style="width: {{ round($tokens / $maxTokens * 100, 1) }}%"></span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($aiUsageByPurpose !== [])
            <h3 class="mb-2 mt-6 text-sm font-semibold text-slate-900">Par usage, depuis le début</h3>
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="py-2 pr-4">Usage</th>
                        <th class="py-2 pr-4 text-right">Appels</th>
                        <th class="py-2 pr-4 text-right">Jetons</th>
                        <th class="py-2 text-right">Coût estimé</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($aiUsageByPurpose as $row)
                        <tr>
                            <td class="py-2 pr-4 text-slate-600">{{ __('ai.purposes.'.$row['purpose']) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums text-slate-900">{{ $row['calls'] }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums text-slate-900">{{ number_format($row['tokens'], 0, ',', ' ') }}</td>
                            <td class="py-2 text-right tabular-nums text-slate-900">{{ number_format($row['cost'], 4, ',', ' ') }} $</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <p class="mt-4 text-sm">
            <a href="{{ route('admin.ai-logs.index') }}" class="text-indigo-600 hover:underline">Voir le journal détaillé des appels →</a>
        </p>
    </x-card>
</x-layouts.app>
