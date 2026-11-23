<?php

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Evaluation\EvaluationCase;
use App\Services\Evaluation\EvaluationDataset;
use Database\Seeders\EvaluationTicketsSeeder;
use Database\Seeders\ReferenceDataSeeder;

test('the evaluation set holds sixty labelled tickets', function () {
    $cases = (new EvaluationDataset)->load();

    expect($cases)->toHaveCount(60)
        ->and($cases[0])->toBeInstanceOf(EvaluationCase::class);

    foreach ($cases as $case) {
        expect(mb_strlen($case->title))->toBeGreaterThan(5)
            ->and(mb_strlen($case->description))->toBeGreaterThan(20);
    }
});

test('every expected category exists in the reference data', function () {
    $this->seed(ReferenceDataSeeder::class);

    $expected = collect((new EvaluationDataset)->load())->pluck('expectedCategory')->unique()->sort()->values()->all();

    expect($expected)->toBe(Category::query()->orderBy('slug')->pluck('slug')->all());
});

test('the evaluation set has enough tickets in each category and covers every priority', function () {
    $cases = collect((new EvaluationDataset)->load());

    expect($cases->countBy('expectedCategory')->min())->toBeGreaterThanOrEqual(5)
        ->and($cases->map(fn (EvaluationCase $case) => $case->expectedPriority->value)->unique()->sort()->values()->all())
        ->toBe(['critical', 'high', 'low', 'normal']);
});

test('ticket identifiers are unique', function () {
    $ids = collect((new EvaluationDataset)->load())->pluck('id');

    expect($ids->unique())->toHaveCount(60);
});

test('a missing or malformed file is reported clearly', function (string $content) {
    $path = tempnam(sys_get_temp_dir(), 'eval');
    file_put_contents($path, $content);

    expect(fn () => (new EvaluationDataset)->load($path))->toThrow(InvalidArgumentException::class);

    unlink($path);
})->with(['not json', '{"tickets": "nope"}', '[]']);

test('an unknown priority in the file is refused', function () {
    $path = tempnam(sys_get_temp_dir(), 'eval');
    file_put_contents($path, json_encode(['tickets' => [[
        'id' => 1, 'title' => 'Titre', 'description' => 'Description', 'expected_category' => 'reseau', 'expected_priority' => 'urgent',
    ]]]));

    expect(fn () => (new EvaluationDataset)->load($path))->toThrow(ValueError::class);

    unlink($path);
});

test('the optional seeder loads the evaluation set as real tickets', function () {
    $users = User::factory()->count(3)->create();

    $this->seed(EvaluationTicketsSeeder::class);

    expect(Ticket::count())->toBe(60)
        // Tickets are spread over the existing users.
        ->and(Ticket::query()->distinct()->pluck('requester_id')->sort()->values()->all())->toBe($users->modelKeys())
        // The expected labels are never copied to the tickets: that would be cheating.
        ->and(Ticket::query()->whereNotNull('category_id')->count())->toBe(0);
});
