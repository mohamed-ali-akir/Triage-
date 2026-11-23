<?php

use App\Enums\TicketPriority;
use App\Services\Evaluation\EvaluationCase;
use App\Services\Evaluation\MethodResult;
use App\Services\Evaluation\Prediction;

/**
 * Build a prediction in one line: expected values, then predicted values.
 */
function prediction(string $expectedCategory, string $expectedPriority, ?string $category, ?string $priority, ?float $confidence = null): Prediction
{
    static $id = 0;

    return new Prediction(
        new EvaluationCase(++$id, 'Titre', 'Description', $expectedCategory, TicketPriority::from($expectedPriority)),
        $category,
        $priority === null ? null : TicketPriority::from($priority),
        $confidence,
    );
}

beforeEach(function () {
    // Four tickets: all correct, wrong priority by one level, wrong category, no answer.
    $this->result = new MethodResult('Test', [
        prediction('reseau', 'high', 'reseau', 'high', 0.95),
        prediction('reseau', 'critical', 'reseau', 'high', 0.80),
        prediction('materiel', 'low', 'logiciel', 'low', 0.40),
        prediction('materiel', 'normal', null, null),
    ], totalDurationMs: 6000, usesAi: true);
});

test('accuracies are computed on all tickets, failures included', function () {
    expect($this->result->count())->toBe(4)
        ->and($this->result->categoryAccuracy())->toBe(0.5)
        ->and($this->result->priorityAccuracy())->toBe(0.5)
        ->and($this->result->fullAccuracy())->toBe(0.25)
        ->and($this->result->failures())->toBe(1);
});

test('a priority wrong by one level counts as close', function () {
    // Exact, one level away, exact; the missing answer is not close.
    expect($this->result->closePriorityAccuracy())->toBe(0.75);
});

test('a priority wrong by two levels is not close', function () {
    $result = new MethodResult('Test', [prediction('reseau', 'critical', 'reseau', 'normal')]);

    expect($result->closePriorityAccuracy())->toBe(0.0);
});

test('the average duration is per ticket', function () {
    expect($this->result->averageDurationMs())->toBe(1500.0);
});

test('accuracy is broken down by expected category', function () {
    expect($this->result->accuracyByCategory())->toBe([
        'materiel' => ['correct' => 0, 'total' => 2],
        'reseau' => ['correct' => 2, 'total' => 2],
    ]);
});

test('the confusion matrix counts each expected and predicted pair', function () {
    expect($this->result->confusionMatrix())->toBe([
        'materiel' => ['logiciel' => 1, 'aucune' => 1],
        'reseau' => ['reseau' => 2],
    ]);
});

test('calibration splits the answers around the confidence threshold', function () {
    $calibration = $this->result->calibration(0.7);

    // Above: 0.95 (fully correct) and 0.80 (wrong priority). Below: 0.40 (wrong).
    // The ticket without an answer has no confidence and is left out.
    expect($calibration)->toBe([
        'above' => ['correct' => 1, 'total' => 2],
        'below' => ['correct' => 0, 'total' => 1],
    ]);
});

test('mistakes list every ticket that is not fully correct', function () {
    expect($this->result->mistakes())->toHaveCount(3);
});

test('the margin of error follows the usual formula', function () {
    // 80 % on 60 tickets: 1.96 × sqrt(0.8 × 0.2 / 60) ≈ 0.101, about 10 points.
    expect(round(MethodResult::marginOfError(0.8, 60), 3))->toBe(0.101)
        // The same accuracy on four times more tickets halves the margin.
        ->and(round(MethodResult::marginOfError(0.8, 240), 3))->toBe(0.051)
        ->and(MethodResult::marginOfError(1.0, 60))->toBe(0.0)
        ->and(MethodResult::marginOfError(0.5, 0))->toBe(0.0);
});

test('an empty result does not divide by zero', function () {
    $empty = new MethodResult('Vide', []);

    expect($empty->categoryAccuracy())->toBe(0.0)
        ->and($empty->averageDurationMs())->toBe(0.0);
});
