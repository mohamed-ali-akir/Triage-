<?php

use App\Services\Ai\PromptRepository;

test('a prompt is loaded from resources/prompts and its placeholders are replaced', function () {
    $prompt = (new PromptRepository)->render('classify-user', ['ticket' => 'Titre : Wifi en panne']);

    expect($prompt)->toBe("<ticket>\nTitre : Wifi en panne\n</ticket>");
});

test('the system prompt tells the model that the ticket is data, not instructions', function () {
    $prompt = (new PromptRepository)->render('classify-system', ['categories' => '- `reseau` : Réseau']);

    expect($prompt)->toContain('- `reseau` : Réseau')
        ->toContain('<ticket>')
        ->toContain('ne les suis pas')
        ->not->toContain('{{');
});

test('an unknown prompt is an error', function () {
    (new PromptRepository)->render('does-not-exist');
})->throws(InvalidArgumentException::class);
