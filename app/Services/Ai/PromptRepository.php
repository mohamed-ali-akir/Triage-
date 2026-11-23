<?php

namespace App\Services\Ai;

use InvalidArgumentException;

/**
 * Loads the prompts from resources/prompts, so that they can be read, reviewed
 * and versioned like any other file instead of being buried in PHP strings.
 */
class PromptRepository
{
    /**
     * Read a prompt file and replace its {{ placeholders }}.
     *
     * @param  array<string, string>  $variables
     */
    public function render(string $name, array $variables = []): string
    {
        $path = resource_path("prompts/{$name}.md");

        if (! is_file($path)) {
            throw new InvalidArgumentException("Prompt [{$name}] does not exist.");
        }

        $prompt = (string) file_get_contents($path);

        foreach ($variables as $key => $value) {
            $prompt = str_replace('{{ '.$key.' }}', $value, $prompt);
        }

        return trim($prompt);
    }
}
