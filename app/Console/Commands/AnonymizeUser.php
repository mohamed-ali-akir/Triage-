<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Privacy\TicketAnonymizer;
use Illuminate\Console\Command;

class AnonymizeUser extends Command
{
    protected $signature = 'triage:anonymize-user {email : E-mail address of the account} {--force : Do not ask for confirmation}';

    protected $description = 'Erase the personal data of one account and of its tickets (GDPR right to erasure)';

    public function handle(TicketAnonymizer $anonymizer): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('Aucun compte ne correspond à cette adresse.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Anonymiser définitivement le compte de {$user->name} et tous ses tickets ?")) {
            $this->line('Annulé.');

            return self::SUCCESS;
        }

        $count = $anonymizer->anonymizeUser($user);

        $this->info("Compte anonymisé. {$count} ticket(s) vidé(s) de leur contenu.");

        return self::SUCCESS;
    }
}
