<?php

namespace App\Services\Evaluation;

/**
 * Every prediction of one method (the AI with one model, or the keyword baseline)
 * and the figures computed from them.
 */
final class MethodResult
{
    /**
     * @param  list<Prediction>  $predictions
     */
    public function __construct(
        public readonly string $label,
        public readonly array $predictions,
        public readonly int $totalDurationMs = 0,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly ?float $costUsd = null,
        public readonly bool $usesAi = false,
    ) {}

    public function count(): int
    {
        return count($this->predictions);
    }

    public function categoryAccuracy(): float
    {
        return $this->share(fn (Prediction $prediction): bool => $prediction->categoryIsCorrect());
    }

    public function priorityAccuracy(): float
    {
        return $this->share(fn (Prediction $prediction): bool => $prediction->priorityIsCorrect());
    }

    public function closePriorityAccuracy(): float
    {
        return $this->share(fn (Prediction $prediction): bool => $prediction->priorityIsClose());
    }

    public function fullAccuracy(): float
    {
        return $this->share(fn (Prediction $prediction): bool => $prediction->isFullyCorrect());
    }

    public function failures(): int
    {
        return count(array_filter($this->predictions, fn (Prediction $prediction): bool => $prediction->failed()));
    }

    public function averageDurationMs(): float
    {
        return $this->count() === 0 ? 0.0 : $this->totalDurationMs / $this->count();
    }

    /**
     * Half-width of the 95 % confidence interval of a proportion measured on n tickets
     * (normal approximation). With 60 tickets and 80 % accuracy it is about 10 points:
     * a smaller gap between two methods proves nothing.
     */
    public static function marginOfError(float $proportion, int $n): float
    {
        if ($n === 0) {
            return 0.0;
        }

        return 1.96 * sqrt($proportion * (1 - $proportion) / $n);
    }

    /**
     * Category accuracy for each expected category.
     *
     * @return array<string, array{correct: int, total: int}>
     */
    public function accuracyByCategory(): array
    {
        $rows = [];

        foreach ($this->predictions as $prediction) {
            $category = $prediction->case->expectedCategory;

            $rows[$category] ??= ['correct' => 0, 'total' => 0];
            $rows[$category]['total']++;
            $rows[$category]['correct'] += $prediction->categoryIsCorrect() ? 1 : 0;
        }

        ksort($rows);

        return $rows;
    }

    /**
     * Confusion matrix: [expected category][predicted category] => number of tickets.
     * A missing answer is counted under the key "aucune".
     *
     * @return array<string, array<string, int>>
     */
    public function confusionMatrix(): array
    {
        $matrix = [];

        foreach ($this->predictions as $prediction) {
            $expected = $prediction->case->expectedCategory;
            $predicted = $prediction->category ?? 'aucune';

            $matrix[$expected][$predicted] = ($matrix[$expected][$predicted] ?? 0) + 1;
        }

        ksort($matrix);

        return $matrix;
    }

    /**
     * Is the confidence score trustworthy? Compare the share of fully correct answers
     * above and below the threshold: a useful score gives a clearly higher share above.
     *
     * @return array{above: array{correct: int, total: int}, below: array{correct: int, total: int}}
     */
    public function calibration(float $threshold): array
    {
        $groups = [
            'above' => ['correct' => 0, 'total' => 0],
            'below' => ['correct' => 0, 'total' => 0],
        ];

        foreach ($this->predictions as $prediction) {
            if ($prediction->confidence === null) {
                continue;
            }

            $group = $prediction->confidence >= $threshold ? 'above' : 'below';

            $groups[$group]['total']++;
            $groups[$group]['correct'] += $prediction->isFullyCorrect() ? 1 : 0;
        }

        return $groups;
    }

    /**
     * @return list<Prediction>
     */
    public function mistakes(): array
    {
        return array_values(array_filter(
            $this->predictions,
            fn (Prediction $prediction): bool => ! $prediction->isFullyCorrect(),
        ));
    }

    /**
     * @param  callable(Prediction): bool  $test
     */
    private function share(callable $test): float
    {
        if ($this->count() === 0) {
            return 0.0;
        }

        return count(array_filter($this->predictions, $test)) / $this->count();
    }
}
