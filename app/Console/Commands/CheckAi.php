<?php

namespace App\Console\Commands;

use App\Models\AiLog;
use App\Models\Category;
use App\Models\Ticket;
use App\Services\Ai\AiProvider;
use App\Services\Ai\ClassificationFailed;
use App\Services\Ai\TicketClassifier;
use Illuminate\Console\Command;

class CheckAi extends Command
{
    protected $signature = 'triage:ai-check';

    protected $description = 'Send one sample ticket to the configured AI provider and explain the result';

    /**
     * What to look at for each error code written to the AI log.
     */
    private const HINTS = [
        'not_configured' => 'La clé est absente : renseignez ANTHROPIC_API_KEY dans .env.',
        'insecure_transport' => 'Ollama est sur une autre machine en HTTP : les tickets circuleraient en clair. Utilisez une adresse en https://, ou OLLAMA_ALLOW_INSECURE=true si le réseau est déjà chiffré.',
        'authentication' => 'La clé est refusée : vérifiez ANTHROPIC_API_KEY (clé révoquée, mal copiée, ou d\'un autre compte).',
        'http_400' => 'La requête est refusée : vérifiez ANTHROPIC_MODEL. Avec un ancien modèle, essayez ANTHROPIC_REFUSAL_FALLBACK=false et videz ANTHROPIC_EFFORT.',
        'http_404' => 'Le modèle est introuvable : vérifiez ANTHROPIC_MODEL (ou OLLAMA_MODEL, puis « ollama pull »).',
        'rate_limited' => 'Le quota de l\'API est atteint : réessayez plus tard ou vérifiez les limites de votre compte.',
        'overloaded' => 'Le service est surchargé : réessayez dans quelques minutes.',
        'timeout' => 'Pas de réponse dans le délai imparti : augmentez AI_TIMEOUT ou vérifiez la connexion.',
        'unreachable' => 'Le serveur est injoignable : vérifiez la connexion (ou lancez « ollama serve » pour un modèle local).',
        'refusal' => 'Le modèle a refusé de répondre à ce ticket d\'essai.',
        'max_tokens' => 'La réponse a été tronquée : augmentez ANTHROPIC_MAX_TOKENS.',
        'invalid_output' => 'Le modèle a répondu, mais pas avec un classement valide : essayez un modèle plus capable.',
    ];

    public function handle(TicketClassifier $classifier, AiProvider $provider): int
    {
        if (Category::query()->active()->doesntExist()) {
            $this->error('Aucune catégorie en base. Lancez d\'abord : php artisan db:seed --class=ReferenceDataSeeder');

            return self::FAILURE;
        }

        $this->line("Fournisseur : {$provider->name()} — modèle : {$provider->model()}");

        // A ticket that is never saved, with personal data to exercise the masking.
        $ticket = new Ticket([
            'title' => 'Impossible de me connecter au wifi du bureau',
            'description' => 'Depuis ce matin mon portable ne trouve plus le wifi « Entreprise ». Joignable au 06 12 34 56 78.',
        ]);

        $lastLogId = (int) AiLog::query()->max('id');

        try {
            $classification = $classifier->classify($ticket, 'check');
        } catch (ClassificationFailed) {
            $errorCode = (string) AiLog::query()->where('id', '>', $lastLogId)->latest('id')->value('error_code');

            $this->error("Échec de l'appel à l'IA (code : {$errorCode}).");
            $this->line(self::HINTS[$errorCode] ?? 'Consultez le journal de l\'IA dans l\'administration.');

            return self::FAILURE;
        }

        $log = AiLog::query()->where('id', '>', $lastLogId)->latest('id')->firstOrFail();

        $this->info('L\'IA répond correctement.');
        $this->table(['Catégorie', 'Priorité', 'Confiance', 'Durée', 'Jetons', 'Coût estimé'], [[
            $classification->category->name,
            $classification->priority->label(),
            round($classification->confidence * 100).' %',
            number_format($log->duration_ms / 1000, 2, ',', ' ').' s',
            "{$log->input_tokens} + {$log->output_tokens}",
            $log->estimated_cost_usd === null ? '—' : number_format($log->estimated_cost_usd, 4, ',', ' ').' $',
        ]]);

        return self::SUCCESS;
    }
}
