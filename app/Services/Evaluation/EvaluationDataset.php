<?php

namespace App\Services\Evaluation;

use App\Enums\TicketPriority;
use InvalidArgumentException;

/**
 * Loads the hand-labelled tickets of database/data/evaluation-tickets.json.
 */
class EvaluationDataset
{
    /**
     * @return list<EvaluationCase>
     */
    public function load(?string $path = null): array
    {
        $path ??= database_path('data/evaluation-tickets.json');

        if (! is_file($path)) {
            throw new InvalidArgumentException("Evaluation set not found: {$path}");
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data) || ! is_array($data['tickets'] ?? null)) {
            throw new InvalidArgumentException('The evaluation set must be a JSON object with a "tickets" list.');
        }

        return array_map(fn (array $row): EvaluationCase => new EvaluationCase(
            id: (int) $row['id'],
            title: (string) $row['title'],
            description: (string) $row['description'],
            expectedCategory: (string) $row['expected_category'],
            // from() throws on an unknown priority: a typo in the file is caught at once.
            expectedPriority: TicketPriority::from((string) $row['expected_priority']),
        ), array_values($data['tickets']));
    }
}
