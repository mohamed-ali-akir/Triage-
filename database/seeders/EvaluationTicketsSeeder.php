<?php

namespace Database\Seeders;

use App\Actions\Tickets\CreateTicket;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Evaluation\EvaluationDataset;
use Illuminate\Database\Seeder;

/**
 * Optional: load the sixty tickets of the evaluation set as real tickets,
 * to fill the queue for a demonstration or a load test.
 *
 *     php artisan db:seed --class=EvaluationTicketsSeeder
 *
 * It is not part of the default seeding: the evaluation command reads the
 * JSON file directly and does not need these tickets.
 */
class EvaluationTicketsSeeder extends Seeder
{
    public function __construct(
        private readonly EvaluationDataset $dataset,
        private readonly CreateTicket $createTicket,
    ) {}

    public function run(): void
    {
        $requesters = User::query()->where('role', UserRole::User)->where('is_active', true)->get();

        if ($requesters->isEmpty()) {
            $this->command->warn('Aucun utilisateur : lancez d\'abord php artisan db:seed.');

            return;
        }

        foreach ($this->dataset->load() as $index => $case) {
            // Tickets go through the normal creation: the AI classifies them if it is enabled.
            $this->createTicket->handle($requesters[$index % $requesters->count()], [
                'title' => $case->title,
                'description' => $case->description,
            ]);
        }
    }
}
