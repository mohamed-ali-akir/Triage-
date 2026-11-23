<?php

namespace App\Console\Commands;

use App\Models\AiLog;
use App\Models\Category;
use App\Models\Ticket;
use App\Services\Ai\AiProvider;
use App\Services\Ai\ClassificationFailed;
use App\Services\Ai\TicketClassifier;
use App\Services\Evaluation\EvaluationCase;
use App\Services\Evaluation\EvaluationDataset;
use App\Services\Evaluation\EvaluationReport;
use App\Services\Evaluation\KeywordClassifier;
use App\Services\Evaluation\MethodResult;
use App\Services\Evaluation\Prediction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class EvaluateTriage extends Command
{
    protected $signature = 'triage:evaluate
        {--model=* : Model to evaluate; repeat the option to compare several models}
        {--provider= : AI provider to use instead of the one in .env (anthropic, ollama)}
        {--baseline-only : Only run the keyword reference method, without any AI call}
        {--limit= : Only use the first N tickets of the evaluation set}
        {--delay=0 : Milliseconds to wait between two AI calls}
        {--output= : Directory of the report (default: docs/evaluations)}
        {--no-save : Print the results without writing a report}';

    protected $description = 'Measure the quality of the AI triage on the hand-labelled evaluation set';

    public function handle(EvaluationDataset $dataset, KeywordClassifier $keywords, EvaluationReport $report): int
    {
        $cases = $dataset->load();

        if ($this->option('limit') !== null) {
            $cases = array_slice($cases, 0, max(1, (int) $this->option('limit')));
        }

        if (! $this->categoriesExist($cases)) {
            return self::FAILURE;
        }

        $results = [$this->evaluateKeywords($cases, $keywords)];

        if (! $this->option('baseline-only')) {
            if ($this->option('provider') !== null) {
                config(['ai.provider' => $this->option('provider')]);
            }

            /** @var list<string> $models */
            $models = (array) $this->option('model');

            foreach ($models === [] ? [null] : $models as $model) {
                $results[] = $this->evaluateAi($cases, $model);
            }
        }

        $this->printSummary($results);

        if (! $this->option('no-save')) {
            $path = $this->save($report->render($results, now(), (float) config('ai.low_confidence_threshold')));

            $this->info("Rapport enregistré : {$path}");
        }

        return self::SUCCESS;
    }

    /**
     * The evaluation set refers to categories by slug: they must exist in the database.
     *
     * @param  list<EvaluationCase>  $cases
     */
    private function categoriesExist(array $cases): bool
    {
        $expected = array_unique(array_map(fn (EvaluationCase $case): string => $case->expectedCategory, $cases));
        $missing = array_diff($expected, Category::query()->active()->pluck('slug')->all());

        if ($missing === []) {
            return true;
        }

        $this->error('Catégories absentes de la base : '.implode(', ', $missing).'.');
        $this->line('Lancez d\'abord : php artisan db:seed --class=ReferenceDataSeeder');

        return false;
    }

    /**
     * @param  list<EvaluationCase>  $cases
     */
    private function evaluateKeywords(array $cases, KeywordClassifier $keywords): MethodResult
    {
        $predictions = array_map(function (EvaluationCase $case) use ($keywords): Prediction {
            $answer = $keywords->classify($case->title, $case->description);

            return new Prediction($case, $answer['category'], $answer['priority']);
        }, $cases);

        return new MethodResult('Référence par mots-clés', $predictions);
    }

    /**
     * @param  list<EvaluationCase>  $cases
     */
    private function evaluateAi(array $cases, ?string $model): MethodResult
    {
        if ($model !== null) {
            config(['ai.'.config('ai.provider').'.model' => $model]);
        }

        // The provider is a singleton built from the configuration: when an option
        // changed the provider or the model, drop it so that a fresh one is built.
        if ($model !== null || $this->option('provider') !== null) {
            app()->forgetInstance(AiProvider::class);
        }

        $provider = app(AiProvider::class);
        $classifier = app(TicketClassifier::class);

        $label = "IA — {$provider->name()} / {$provider->model()}";
        $this->line("Évaluation : {$label}");

        $lastLogId = (int) AiLog::query()->max('id');
        $predictions = [];

        $this->withProgressBar($cases, function (EvaluationCase $case) use ($classifier, &$predictions): void {
            // A ticket that is never saved: the evaluation set does not pollute the real queue.
            $ticket = new Ticket(['title' => $case->title, 'description' => $case->description]);

            try {
                $classification = $classifier->classify($ticket, 'evaluation');

                $predictions[] = new Prediction(
                    $case,
                    $classification->category->slug,
                    $classification->priority,
                    $classification->confidence,
                );
            } catch (ClassificationFailed) {
                // No usable answer counts as a wrong answer.
                $predictions[] = new Prediction($case, null, null);
            }

            usleep(max(0, (int) $this->option('delay')) * 1000);
        });

        $this->newLine(2);

        // Time, tokens and cost come from the AI log lines written during this run.
        $logs = AiLog::query()->where('id', '>', $lastLogId)->where('purpose', 'evaluation')->get();

        return new MethodResult(
            label: $label,
            predictions: $predictions,
            totalDurationMs: (int) $logs->sum('duration_ms'),
            inputTokens: (int) $logs->sum('input_tokens'),
            outputTokens: (int) $logs->sum('output_tokens'),
            costUsd: $logs->whereNotNull('estimated_cost_usd')->isEmpty() ? null : (float) $logs->sum('estimated_cost_usd'),
            usesAi: true,
        );
    }

    /**
     * @param  list<MethodResult>  $results
     */
    private function printSummary(array $results): void
    {
        $percent = fn (float $value, int $n): string => sprintf(
            '%d %% ± %d',
            round($value * 100),
            round(MethodResult::marginOfError($value, $n) * 100),
        );

        $this->table(
            ['Méthode', 'Catégorie', 'Priorité', 'Les deux', 'Temps moyen', 'Coût estimé', 'Sans réponse'],
            array_map(fn (MethodResult $result): array => [
                $result->label,
                $percent($result->categoryAccuracy(), $result->count()),
                $percent($result->priorityAccuracy(), $result->count()),
                $percent($result->fullAccuracy(), $result->count()),
                $result->usesAi ? number_format($result->averageDurationMs() / 1000, 2).' s' : '< 1 ms',
                $result->costUsd === null ? '—' : number_format($result->costUsd, 4).' $',
                $result->failures(),
            ], $results),
        );
    }

    private function save(string $markdown): string
    {
        $directory = $this->option('output') ?? base_path('docs/evaluations');

        File::ensureDirectoryExists($directory);

        $path = rtrim((string) $directory, '/').'/'.now()->format('Y-m-d-His').'-evaluation.md';

        File::put($path, $markdown);

        return $path;
    }
}
