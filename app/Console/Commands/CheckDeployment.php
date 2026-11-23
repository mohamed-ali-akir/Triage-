<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CheckDeployment extends Command
{
    protected $signature = 'triage:check-deployment';

    protected $description = 'Check that the configuration is safe before the application is opened to real users';

    public function handle(): int
    {
        $checks = [
            ['APP_ENV vaut « production »', app()->isProduction(), 'Renseignez APP_ENV=production dans .env.'],
            ['Le mode débogage est coupé', ! config('app.debug'), 'Renseignez APP_DEBUG=false : une page d\'erreur détaillée révèle la configuration.'],
            ['L\'adresse du site est en HTTPS', Str::startsWith((string) config('app.url'), 'https://'), 'Renseignez APP_URL avec une adresse en https://.'],
            ['Le cookie de session est réservé à HTTPS', (bool) config('session.secure'), 'Renseignez SESSION_SECURE_COOKIE=true.'],
            ['La base de données n\'utilise pas le compte root', config('database.connections.'.config('database.default').'.username') !== 'root', 'Créez un compte de base de données réservé à Triage.'],
            ['Aucun compte de démonstration n\'est resté', $this->demoAccounts() === [], 'Supprimez ou changez le mot de passe de : '.implode(', ', $this->demoAccounts()).'.'],
            ['La double authentification est imposée au support', (bool) config('security.require_two_factor_for_staff'), 'Renseignez REQUIRE_2FA_FOR_STAFF=true.'],
        ];

        $failures = 0;

        foreach ($checks as [$label, $passed, $advice]) {
            if ($passed) {
                $this->line("  <info>OK</info>      {$label}");

                continue;
            }

            $failures++;
            $this->line("  <error>À FAIRE</error> {$label}");
            $this->line("          {$advice}");
        }

        if ($failures > 0) {
            $this->newLine();
            $this->error("{$failures} point(s) à corriger avant la mise en production.");

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('La configuration est prête pour la production.');

        return self::SUCCESS;
    }

    /**
     * Demonstration accounts (@triage.test) that still have the well-known password.
     *
     * @return list<string>
     */
    private function demoAccounts(): array
    {
        return User::query()
            ->where('email', 'like', '%@triage.test')
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user): bool => Hash::check('password', $user->password))
            ->pluck('email')
            ->values()
            ->all();
    }
}
