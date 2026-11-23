<?php

namespace App\Services\Evaluation;

use Illuminate\Support\Carbon;

/**
 * Turns the results of an evaluation into a Markdown report.
 */
class EvaluationReport
{
    /**
     * @param  list<MethodResult>  $results
     */
    public function render(array $results, Carbon $date, float $confidenceThreshold): string
    {
        $count = $results === [] ? 0 : $results[0]->count();

        $lines = [
            '# Évaluation du tri des tickets',
            '',
            "- Date : {$date->format('d/m/Y à H:i')}",
            "- Jeu de test : {$count} tickets étiquetés à la main (`database/data/evaluation-tickets.json`)",
            '- Commande : `php artisan triage:evaluate`',
            '',
            '## Résumé',
            '',
            '| Méthode | Catégorie | Priorité | Priorité à un niveau près | Catégorie et priorité | Temps moyen | Coût estimé | Sans réponse |',
            '|---|---|---|---|---|---|---|---|',
        ];

        foreach ($results as $result) {
            $lines[] = sprintf(
                '| %s | %s | %s | %s | %s | %s | %s | %d |',
                $result->label,
                $this->percentWithMargin($result->categoryAccuracy(), $result->count()),
                $this->percentWithMargin($result->priorityAccuracy(), $result->count()),
                $this->percent($result->closePriorityAccuracy()),
                $this->percentWithMargin($result->fullAccuracy(), $result->count()),
                $result->usesAi ? number_format($result->averageDurationMs() / 1000, 2, ',', ' ').' s' : '< 1 ms',
                $result->costUsd === null ? '—' : number_format($result->costUsd, 4, ',', ' ').' $',
                $result->failures(),
            );
        }

        $lines[] = '';
        $lines[] = 'Le chiffre après « ± » est la marge d\'erreur à 95 % : avec si peu de tickets, un écart';
        $lines[] = 'plus petit que cette marge entre deux méthodes ne prouve rien.';
        $lines[] = '';

        array_push($lines, ...$this->comparison($results));

        foreach ($results as $result) {
            array_push($lines, ...$this->methodSection($result, $confidenceThreshold));
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Say in one sentence whether the gap between the baseline and each AI run means something.
     *
     * @param  list<MethodResult>  $results
     * @return list<string>
     */
    private function comparison(array $results): array
    {
        $baseline = collect($results)->first(fn (MethodResult $result): bool => ! $result->usesAi);
        $aiResults = array_filter($results, fn (MethodResult $result): bool => $result->usesAi);

        if ($baseline === null || $aiResults === []) {
            return [];
        }

        $lines = ['## Lecture', ''];

        foreach ($aiResults as $result) {
            $gap = $result->categoryAccuracy() - $baseline->categoryAccuracy();
            $margin = MethodResult::marginOfDifference(
                $result->categoryAccuracy(),
                $result->count(),
                $baseline->categoryAccuracy(),
                $baseline->count(),
            );

            $verdict = abs($gap) > $margin
                ? 'cet écart dépasse la marge d\'erreur de la différence : il est significatif.'
                : 'cet écart est inférieur à la marge d\'erreur de la différence : il ne permet pas de conclure.';

            $lines[] = sprintf(
                '- **%s** : %+d points sur la catégorie par rapport à la référence par mots-clés (marge d\'erreur de la différence : ± %d points) ; %s',
                $result->label,
                (int) round($gap * 100),
                (int) round($margin * 100),
                $verdict,
            );
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function methodSection(MethodResult $result, float $confidenceThreshold): array
    {
        $lines = ["## {$result->label}", '', '### Précision par catégorie', '', '| Catégorie attendue | Bonnes réponses | Précision |', '|---|---|---|'];

        foreach ($result->accuracyByCategory() as $category => $row) {
            $lines[] = sprintf('| `%s` | %d / %d | %s |', $category, $row['correct'], $row['total'], $this->percent($row['correct'] / $row['total']));
        }

        array_push($lines, '', '### Matrice de confusion', '', 'En ligne la catégorie attendue, en colonne la catégorie proposée.', '');
        array_push($lines, ...$this->confusionTable($result));

        if ($result->usesAi) {
            array_push($lines, ...$this->calibrationSection($result, $confidenceThreshold));

            $lines[] = sprintf(
                'Consommation : %s jetons en entrée, %s jetons en sortie.',
                number_format($result->inputTokens, 0, ',', ' '),
                number_format($result->outputTokens, 0, ',', ' '),
            );
            $lines[] = '';
        }

        array_push($lines, ...$this->mistakesSection($result));

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function confusionTable(MethodResult $result): array
    {
        $matrix = $result->confusionMatrix();

        $columns = [];

        foreach ($matrix as $row) {
            $columns = array_merge($columns, array_keys($row));
        }

        $columns = array_values(array_unique(array_merge(array_keys($matrix), $columns)));
        sort($columns);

        $lines = [
            '| attendue \\ proposée | '.implode(' | ', array_map(fn (string $column): string => "`{$column}`", $columns)).' |',
            '|---|'.str_repeat('---|', count($columns)),
        ];

        foreach ($matrix as $expected => $row) {
            $cells = array_map(function (string $column) use ($row, $expected): string {
                $value = $row[$column] ?? 0;

                // The diagonal holds the correct answers.
                return $column === $expected ? "**{$value}**" : ($value === 0 ? '·' : (string) $value);
            }, $columns);

            $lines[] = "| `{$expected}` | ".implode(' | ', $cells).' |';
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function calibrationSection(MethodResult $result, float $threshold): array
    {
        $calibration = $result->calibration($threshold);

        $describe = function (array $group): string {
            return $group['total'] === 0
                ? 'aucun ticket'
                : sprintf('%d / %d entièrement justes (%s)', $group['correct'], $group['total'], $this->percent($group['correct'] / $group['total']));
        };

        return [
            '### Fiabilité du score de confiance',
            '',
            sprintf('Seuil du badge « Confiance faible » : %s.', number_format($threshold, 2, ',', ' ')),
            '',
            '| Score de confiance | Catégorie et priorité justes |',
            '|---|---|',
            '| Au-dessus du seuil | '.$describe($calibration['above']).' |',
            '| En dessous du seuil | '.$describe($calibration['below']).' |',
            '',
            'Un score utile donne nettement plus de réponses justes au-dessus du seuil qu\'en dessous.',
            '',
        ];
    }

    /**
     * @return list<string>
     */
    private function mistakesSection(MethodResult $result): array
    {
        $mistakes = $result->mistakes();

        if ($mistakes === []) {
            return ['### Erreurs', '', 'Aucune erreur.', ''];
        }

        $lines = ['### Erreurs', '', '| N° | Ticket | Attendu | Proposé |', '|---|---|---|---|'];

        foreach ($mistakes as $prediction) {
            $lines[] = sprintf(
                '| %d | %s | `%s` / %s | %s |',
                $prediction->case->id,
                str_replace('|', '\\|', $prediction->case->title),
                $prediction->case->expectedCategory,
                $prediction->case->expectedPriority->label(),
                $prediction->failed()
                    ? 'aucune réponse'
                    : sprintf('`%s` / %s', $prediction->category, $prediction->priority?->label() ?? '—'),
            );
        }

        $lines[] = '';

        return $lines;
    }

    private function percent(float $proportion): string
    {
        return round($proportion * 100).' %';
    }

    private function percentWithMargin(float $proportion, int $n): string
    {
        return sprintf('%d %% ± %d', round($proportion * 100), round(MethodResult::marginOfError($proportion, $n) * 100));
    }
}
