<?php

use App\Models\AiLog;
use App\Models\Category;
use App\Models\Ticket;
use App\Services\Ai\AiProvider;
use App\Services\Ai\AiProviderException;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);

    $this->ai = app(AiProvider::class);
    $this->output = storage_path('framework/testing/evaluations-'.uniqid());
});

afterEach(function () {
    File::deleteDirectory($this->output);
});

function latestReport(string $directory): string
{
    $files = File::files($directory);

    expect($files)->toHaveCount(1);

    return File::get($files[0]->getPathname());
}

test('the command refuses to run without the reference categories', function () {
    Category::query()->delete();

    $this->artisan('triage:evaluate', ['--baseline-only' => true, '--no-save' => true])
        ->expectsOutputToContain('Catégories absentes')
        ->assertFailed();
});

test('the baseline alone runs without any AI call', function () {
    $this->artisan('triage:evaluate', ['--baseline-only' => true, '--output' => $this->output])
        ->expectsOutputToContain('Référence par mots-clés')
        ->assertSuccessful();

    $report = latestReport($this->output);

    expect($report)->toContain('# Évaluation du tri des tickets')
        ->toContain('60 tickets étiquetés à la main')
        ->toContain('## Référence par mots-clés')
        ->toContain('### Matrice de confusion')
        ->toContain('### Précision par catégorie')
        ->not->toContain('## Lecture')
        ->and($this->ai->calls)->toBe([])
        ->and(AiLog::count())->toBe(0);
});

test('the AI is evaluated on the same tickets and compared with the baseline', function () {
    // Tickets 1 to 3 of the set are hardware: normal, high, low.
    $this->ai->willReturnJson(['category' => 'materiel', 'priority' => 'normal', 'confidence' => 0.9])
        ->willReturnJson(['category' => 'materiel', 'priority' => 'critical', 'confidence' => 0.8])
        ->willReturnJson(['category' => 'logiciel', 'priority' => 'low', 'confidence' => 0.4]);

    $this->artisan('triage:evaluate', ['--limit' => 3, '--output' => $this->output])
        ->expectsOutputToContain('IA — fake / fake')
        ->assertSuccessful();

    $report = latestReport($this->output);

    expect($report)->toContain('3 tickets étiquetés à la main')
        ->toContain('## IA — fake / fake')
        // 2 categories right out of 3, 2 priorities right out of 3, 1 ticket fully right.
        ->toContain('| IA — fake / fake | 67 % ± 53 | 67 % ± 53 | 100 % | 33 % ± 53 |')
        ->toContain('## Lecture')
        // On three tickets no gap can be significant, and the report says so.
        ->toContain('il ne permet pas de conclure')
        ->toContain('### Fiabilité du score de confiance')
        ->toContain('| Au-dessus du seuil | 1 / 2 entièrement justes (50 %) |')
        ->toContain('| En dessous du seuil | 0 / 1 entièrement justes (0 %) |')
        ->toContain('### Erreurs');
});

test('evaluation calls are logged under their own purpose and create no ticket', function () {
    $this->artisan('triage:evaluate', ['--limit' => 2, '--no-save' => true])->assertSuccessful();

    expect(AiLog::query()->pluck('purpose')->unique()->all())->toBe(['evaluation'])
        ->and(AiLog::count())->toBe(2)
        ->and(AiLog::query()->whereNotNull('ticket_id')->count())->toBe(0)
        ->and(Ticket::count())->toBe(0);
});

test('a ticket the AI cannot classify counts as a wrong answer', function () {
    $this->ai->willFail(AiProviderException::timeout())
        ->willReturnJson(['category' => 'materiel', 'priority' => 'high', 'confidence' => 0.9]);

    $this->artisan('triage:evaluate', ['--limit' => 2, '--output' => $this->output])->assertSuccessful();

    $report = latestReport($this->output);

    expect($report)->toContain('aucune réponse')
        ->toContain('| IA — fake / fake | 50 % ± 69 |');
});

test('the evaluation set is masked like any ticket', function () {
    $this->artisan('triage:evaluate', ['--limit' => 2, '--no-save' => true])->assertSuccessful();

    // Ticket 2 of the set contains a phone number.
    expect($this->ai->calls[1]['user'])->toContain('[TELEPHONE_1]')
        ->not->toContain('06 45 12 78 90');
});

test('several models can be compared in one run', function () {
    $this->artisan('triage:evaluate', ['--limit' => 1, '--model' => ['modele-a', 'modele-b'], '--output' => $this->output])
        ->assertSuccessful();

    // The fake provider ignores the model name, but two AI runs are reported.
    expect(substr_count(latestReport($this->output), '## IA — fake / fake'))->toBe(2)
        ->and($this->ai === app(AiProvider::class))->toBeFalse();
});

test('--no-save writes no report', function () {
    $this->artisan('triage:evaluate', ['--baseline-only' => true, '--no-save' => true, '--output' => $this->output])
        ->assertSuccessful();

    expect(File::exists($this->output))->toBeFalse();
});
