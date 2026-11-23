<?php

namespace Database\Seeders;

use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\PostTicketMessage;
use App\Enums\ReplyStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TriageMode;
use App\Enums\UserRole;
use App\Models\AiLog;
use App\Models\Category;
use App\Models\Department;
use App\Models\KbArticle;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Ai\SuggestionReviewer;
use App\Services\Tickets\TicketWorkflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demonstration data: 1 administrator, 2 technicians, 5 users, tickets in every status
 * and AI suggestions in every state (pending, accepted, modified, rejected).
 *
 * Tickets are not inserted directly: they go through the same actions and workflow
 * as the real application, so their history and notifications are consistent.
 */
class DemoSeeder extends Seeder
{
    private Carbon $startedAt;

    public function __construct(
        private readonly CreateTicket $createTicket,
        private readonly PostTicketMessage $postMessage,
        private readonly TicketWorkflow $workflow,
        private readonly SuggestionReviewer $reviewer,
    ) {}

    public function run(): void
    {
        // Deliver notifications immediately instead of queueing them, so that the
        // demo shows them even before a queue worker is started.
        config(['queue.default' => 'sync']);

        // Seeding must never call a real AI: suggestions are written by hand below.
        config(['ai.enabled' => false]);

        // The real current time, read before the clock is moved to simulate the past.
        $this->startedAt = Carbon::now();

        $users = $this->createUsers();
        $this->createKnowledgeBase();

        foreach ($this->scenarios() as $scenario) {
            $this->play($scenario, $users);
        }

        // Give the clock back: the seeder moved it to simulate the past.
        Carbon::setTestNow();
    }

    /**
     * @return array<string, User>
     */
    private function createUsers(): array
    {
        $accounts = [
            'admin' => ['Alice Martin', 'admin@triage.test', UserRole::Admin, 'Informatique'],
            'tech1' => ['Thomas Bernard', 'tech1@triage.test', UserRole::Technician, 'Informatique'],
            'tech2' => ['Sarah Petit', 'tech2@triage.test', UserRole::Technician, 'Informatique'],
            'user1' => ['Julie Moreau', 'julie.moreau@triage.test', UserRole::User, 'Comptabilité'],
            'user2' => ['Karim Benali', 'karim.benali@triage.test', UserRole::User, 'Commercial'],
            'user3' => ['Sophie Lambert', 'sophie.lambert@triage.test', UserRole::User, 'Ressources humaines'],
            'user4' => ['Marc Dubois', 'marc.dubois@triage.test', UserRole::User, 'Production'],
            'user5' => ['Élodie Garnier', 'elodie.garnier@triage.test', UserRole::User, 'Direction'],
        ];

        $users = [];

        foreach ($accounts as $key => [$name, $email, $role, $department]) {
            // The role is not mass assignable: it is set explicitly, like in the administration.
            $users[$key] = User::firstOrNew(['email' => $email])->forceFill([
                'name' => $name,
                // Demonstration password only. Never use it outside a demo.
                'password' => 'password',
                'role' => $role,
                'is_active' => true,
                'department_id' => Department::query()->where('name', $department)->value('id'),
            ]);

            $users[$key]->save();
        }

        return $users;
    }

    /**
     * Play one scenario: create the ticket, then move it step by step up to its target status.
     *
     * @param  array{title: string, description: string, requester: string, days_ago: int, status: TicketStatus, category?: string, priority?: TicketPriority, technician?: string, messages?: list<array{0: string, 1: string, 2?: bool}>, reopened?: bool, ai?: array{0: string, 1: TicketPriority, 2: float}, ai_rejected?: bool, ai_reply?: array{0: string, 1: string}, ai_no_article?: bool}  $scenario
     * @param  array<string, User>  $users
     */
    private function play(array $scenario, array $users): void
    {
        // Each step happens a little later than the previous one. Starting six hours
        // before "now" keeps even today's tickets in the past.
        $clock = $this->startedAt->copy()->subDays($scenario['days_ago'])->subHours(6);
        $tick = function () use (&$clock): void {
            $clock = $clock->copy()->addMinutes(47);
            Carbon::setTestNow($clock);
        };

        $tick();
        $requester = $users[$scenario['requester']];
        $ticket = $this->createTicket->handle($requester, [
            'title' => $scenario['title'],
            'description' => $scenario['description'],
        ]);

        $technician = isset($scenario['technician']) ? $users[$scenario['technician']] : null;
        $reviewer = $technician ?? $users['tech1'];

        if (isset($scenario['ai'])) {
            $this->storeSuggestion($ticket, $scenario['ai'], $scenario['ai_reply'] ?? null, $scenario['ai_no_article'] ?? false);
        } elseif ($technician !== null) {
            // Tickets triaged by hand: the AI did not answer in time that day.
            $this->logCall($ticket, 'classification', 0, 0, 'timeout');
        }

        if ($scenario['ai_rejected'] ?? false) {
            $tick();
            $this->reviewer->reject($ticket->pendingSuggestion()->firstOrFail(), $reviewer);
        }

        if (isset($scenario['category'], $scenario['priority'])) {
            $tick();
            $category = Category::query()->where('slug', $scenario['category'])->firstOrFail();

            $this->workflow->triage($ticket, $category, $scenario['priority'], $reviewer);
            // Marks the suggestion "accepted" or "modified", as the triage form does.
            $this->reviewer->recordManualTriage($ticket, $category, $scenario['priority'], $reviewer);
        }

        if ($technician !== null) {
            $tick();
            $this->workflow->assign($ticket, $technician);
            $this->advance($ticket, $scenario, $requester, $technician, $users, $tick);
        }
    }

    /**
     * Store what the AI jobs would have stored if the AI had been called.
     *
     * @param  array{0: string, 1: TicketPriority, 2: float}  $suggestion  category slug, priority, confidence
     * @param  array{0: string, 1: string}|null  $reply  draft reply and title of the article it cites
     */
    private function storeSuggestion(Ticket $ticket, array $suggestion, ?array $reply, bool $noArticle): void
    {
        [$slug, $priority, $confidence] = $suggestion;

        $ticket->triage_mode = TriageMode::Ai;
        $ticket->save();

        $stored = $ticket->suggestions()->create([
            'category_id' => Category::query()->where('slug', $slug)->value('id'),
            'priority' => $priority,
            'confidence' => $confidence,
            'reply' => $reply[0] ?? null,
            'reply_status' => match (true) {
                $reply !== null => ReplyStatus::Proposed,
                $noArticle => ReplyStatus::NoArticle,
                default => null,
            },
        ]);

        if ($reply !== null) {
            $stored->articles()->attach(KbArticle::query()->where('title', $reply[1])->value('id'));
        }

        $this->logCall($ticket, 'classification', 640 + $ticket->id * 13, 38);

        if ($reply !== null || $noArticle) {
            $this->logCall($ticket, 'reply', 1850 + $ticket->id * 21, $reply !== null ? 210 : 24);
        }
    }

    /**
     * Write the AI log line a real call would have left, so that the dashboard has figures to show.
     */
    private function logCall(Ticket $ticket, string $purpose, int $inputTokens, int $outputTokens, ?string $errorCode = null): void
    {
        $model = 'claude-opus-5-5';
        [$inputPrice, $outputPrice] = config("ai.pricing.{$model}");

        (new AiLog)->forceFill([
            'ticket_id' => $ticket->id,
            'purpose' => $purpose,
            'provider' => 'anthropic',
            'model' => $model,
            'input_tokens' => $errorCode === null ? $inputTokens : 0,
            'output_tokens' => $errorCode === null ? $outputTokens : 0,
            'duration_ms' => $errorCode === null ? 1400 + ($ticket->id * 137) % 900 : 30000,
            'estimated_cost_usd' => $errorCode === null
                ? round(($inputTokens * $inputPrice + $outputTokens * $outputPrice) / 1_000_000, 6)
                : null,
            'success' => $errorCode === null,
            'error_code' => $errorCode,
            // The simulated time of the scenario, not the time the seeder runs.
            'created_at' => now(),
        ])->save();
    }

    /**
     * A small knowledge base: the procedures the support team already knows.
     */
    private function createKnowledgeBase(): void
    {
        $articles = [
            ['materiel', true, 'Bourrage papier sur une imprimante multifonction', <<<'MD'
                # Symptôme

                L'imprimante affiche « Bourrage papier » et refuse d'imprimer.

                # Solution

                1. Éteindre l'imprimante.
                2. Ouvrir le capot arrière et retirer doucement les feuilles coincées.
                3. Ouvrir le bac papier et vérifier qu'aucune feuille n'est pliée.
                4. Rallumer l'imprimante : le message doit disparaître.

                Si le message revient sans feuille visible, un morceau de papier est resté sur les rouleaux : prévoir un passage du support.
                MD],
            ['acces-comptes', true, 'Réinitialiser un mot de passe Windows et déverrouiller un compte', <<<'MD'
                # Quand l'utiliser

                Un salarié a oublié son mot de passe de session, ou son compte est verrouillé après plusieurs essais.

                # Procédure

                1. Vérifier l'identité du demandeur (rappel sur son poste interne).
                2. Dans la console Active Directory, déverrouiller le compte.
                3. Définir un mot de passe provisoire et cocher « L'utilisateur doit changer le mot de passe ».
                4. Communiquer le mot de passe provisoire **par téléphone**, jamais dans le ticket ni par e-mail.
                MD],
            ['reseau', true, 'Renouveler un certificat VPN expiré', <<<'MD'
                # Symptôme

                En télétravail, le client VPN affiche « Échec de l'authentification » alors que le mot de passe est correct.

                # Cause

                Le certificat VPN du poste est valable un an. Passé ce délai, la connexion est refusée.

                # Solution

                1. Générer un nouveau certificat dans la console VPN.
                2. L'envoyer au salarié sur sa messagerie professionnelle.
                3. Le salarié double-clique sur le fichier puis relance le client VPN.
                MD],
            ['messagerie', true, 'Outlook affiche « Déconnecté »', <<<'MD'
                # Vérifications

                1. Redémarrer complètement le poste (et non une simple mise en veille).
                2. Vérifier que le mode « Travailler hors connexion » n'est pas activé dans l'onglet Envoi/Réception.
                3. Tester le webmail : s'il fonctionne, le problème vient du profil Outlook.

                # Si le problème persiste

                Recréer le profil Outlook depuis le Panneau de configuration > Courrier.
                MD],
            ['messagerie', true, 'Reconnaître et signaler un e-mail d\'hameçonnage', <<<'MD'
                # Les signes

                - Un expéditeur inconnu ou une adresse qui imite celle d'un collègue.
                - Une demande urgente de confirmer des identifiants ou de payer une facture.
                - Un lien dont l'adresse ne correspond pas au texte affiché.

                # Que faire

                1. Ne cliquer sur aucun lien, n'ouvrir aucune pièce jointe.
                2. Signaler le message au support, qui bloque l'expéditeur.
                3. Supprimer le message.

                Si un lien a été ouvert ou un mot de passe saisi, le signaler immédiatement : le mot de passe doit être changé.
                MD],
            ['reseau', true, 'Wifi lent ou instable dans une salle de réunion', <<<'MD'
                # Vérifications

                1. Vérifier que le poste est connecté au réseau « Entreprise » et non au réseau « Invités », qui est bridé.
                2. Se rapprocher de la borne : les cloisons vitrées affaiblissent le signal.
                3. Pour une visioconférence, préférer le câble réseau disponible sur la table de réunion.

                # Si plusieurs personnes sont touchées

                Relever l'heure et la salle, puis prévoir un contrôle de la borne par le support.
                MD],
            ['logiciel', true, 'Demander l\'installation d\'un logiciel', <<<'MD'
                # Logiciels du catalogue

                LibreOffice, 7-Zip, VLC, Firefox et le lecteur PDF sont installés à distance par le support, sans validation.

                # Autres logiciels

                Une validation du responsable de service est nécessaire. Le support vérifie ensuite la licence et la sécurité du logiciel avant l'installation.
                MD],
            ['autre', true, 'Où enregistrer ses documents pour qu\'ils soient sauvegardés', <<<'MD'
                # Ce qui est sauvegardé

                Seul le lecteur réseau **P:** est sauvegardé, chaque nuit.

                # Ce qui ne l'est pas

                Le Bureau, le dossier Documents du poste et les clés USB ne sont pas sauvegardés. En cas de panne du poste, leur contenu est perdu.

                # Restaurer un fichier

                Clic droit sur le dossier dans P: > « Restaurer les versions précédentes ».
                MD],
            ['acces-comptes', true, 'Arrivée d\'un nouveau salarié : comptes et accès', <<<'MD'
                # À préparer avant l'arrivée

                1. Compte Windows et boîte mail, au format prenom.nom.
                2. Ajout aux groupes de son service pour l'accès aux dossiers partagés.
                3. Accès aux logiciels métier, sur demande écrite du responsable.

                # Le jour de l'arrivée

                Remettre le mot de passe provisoire en main propre et faire signer la charte informatique.
                MD],
            ['reseau', false, 'Migration vers le nouveau client VPN (brouillon)', <<<'MD'
                Procédure en cours de rédaction. Ne pas appliquer avant validation.
                MD],
        ];

        foreach ($articles as [$slug, $published, $title, $content]) {
            KbArticle::updateOrCreate(['title' => $title], [
                'content' => $content,
                'category_id' => Category::query()->where('slug', $slug)->value('id'),
                'is_published' => $published,
            ]);
        }
    }

    /**
     * @param  array{status: TicketStatus, messages?: list<array{0: string, 1: string, 2?: bool}>, reopened?: bool}  $scenario
     * @param  array<string, User>  $users
     * @param  callable(): void  $tick
     */
    private function advance(Ticket $ticket, array $scenario, User $requester, User $technician, array $users, callable $tick): void
    {
        $target = $scenario['status'];

        if ($target === TicketStatus::Assigned) {
            return;
        }

        $tick();
        $this->workflow->transition($ticket, TicketStatus::InProgress, $technician);

        foreach ($scenario['messages'] ?? [] as $message) {
            $tick();
            $this->postMessage->handle($ticket, $users[$message[0]], $message[1], $message[2] ?? false);
        }

        if ($target === TicketStatus::WaitingUser) {
            $tick();
            $this->workflow->transition($ticket, TicketStatus::WaitingUser, $technician);

            return;
        }

        if ($target === TicketStatus::InProgress) {
            return;
        }

        $tick();
        $this->workflow->transition($ticket, TicketStatus::Resolved, $technician);

        if ($scenario['reopened'] ?? false) {
            $tick();
            $this->workflow->transition($ticket, TicketStatus::InProgress, $requester);
            $tick();
            $this->workflow->transition($ticket, TicketStatus::Resolved, $technician);
        }

        if ($target === TicketStatus::Closed) {
            $tick();
            $this->workflow->transition($ticket, TicketStatus::Closed, $requester);
        }
    }

    /**
     * @return list<array{title: string, description: string, requester: string, days_ago: int, status: TicketStatus, category?: string, priority?: TicketPriority, technician?: string, messages?: list<array{0: string, 1: string, 2?: bool}>, reopened?: bool, ai?: array{0: string, 1: TicketPriority, 2: float}, ai_rejected?: bool, ai_reply?: array{0: string, 1: string}, ai_no_article?: bool}>
     */
    private function scenarios(): array
    {
        return [
            [
                'title' => 'Souris sans fil qui se déconnecte toutes les cinq minutes',
                'description' => "Ma souris sans fil perd la connexion plusieurs fois par heure. J'ai déjà changé les piles.",
                'requester' => 'user3', 'days_ago' => 12, 'status' => TicketStatus::Closed,
                'category' => 'materiel', 'priority' => TicketPriority::Low, 'technician' => 'tech2',
                'ai' => ['materiel', TicketPriority::Low, 0.95],
                'messages' => [
                    ['tech2', 'Bonjour, le récepteur USB est sans doute défectueux. Je vous dépose une souris neuve cet après-midi.'],
                    ['user3', 'Parfait, merci beaucoup.'],
                ],
            ],
            [
                'title' => 'Question sur la sauvegarde de mes documents',
                'description' => 'Je voudrais savoir si les fichiers enregistrés sur mon bureau sont sauvegardés, ou seulement ceux du lecteur réseau.',
                'requester' => 'user5', 'days_ago' => 10, 'status' => TicketStatus::Closed,
                'category' => 'autre', 'priority' => TicketPriority::Low, 'technician' => 'tech1', 'reopened' => true,
                'ai' => ['autre', TicketPriority::Low, 0.81],
                'messages' => [
                    ['tech1', "Bonjour, seul le lecteur réseau P: est sauvegardé chaque nuit. Le bureau ne l'est pas."],
                    ['user5', 'Merci. Pouvez-vous déplacer mon dossier « Bilans » vers le lecteur réseau ?'],
                ],
            ],
            [
                'title' => 'Écran externe non détecté sur la station d\'accueil',
                'description' => "Depuis lundi, mon deuxième écran reste noir quand je branche mon portable sur la station d'accueil.",
                'requester' => 'user2', 'days_ago' => 8, 'status' => TicketStatus::Closed,
                'category' => 'materiel', 'priority' => TicketPriority::Normal, 'technician' => 'tech1',
                // The AI hesitated: the technician corrected the category.
                'ai' => ['logiciel', TicketPriority::Normal, 0.62],
                'messages' => [
                    ['tech1', 'Le pilote de la station d\'accueil était obsolète. Je l\'ai mis à jour à distance, pouvez-vous réessayer ?'],
                    ['user2', 'Ça fonctionne de nouveau, merci !'],
                ],
            ],
            [
                'title' => 'E-mail suspect : tentative d\'hameçonnage ?',
                'description' => "J'ai reçu un e-mail qui me demande de confirmer mes identifiants de messagerie via un lien. L'expéditeur m'est inconnu. Je n'ai pas cliqué.",
                'requester' => 'user1', 'days_ago' => 6, 'status' => TicketStatus::Resolved,
                'category' => 'messagerie', 'priority' => TicketPriority::High, 'technician' => 'tech2',
                // The technician raised the priority proposed by the AI.
                'ai' => ['messagerie', TicketPriority::Normal, 0.77],
                'messages' => [
                    ['tech2', 'Expéditeur usurpé, même campagne que la semaine dernière. Domaine à bloquer sur le filtre.', true],
                    ['tech2', "Vous avez eu le bon réflexe. C'est bien une tentative d'hameçonnage : l'expéditeur est bloqué, vous pouvez supprimer le message."],
                ],
            ],
            [
                'title' => 'Installation de LibreOffice sur mon poste',
                'description' => "J'ai besoin de LibreOffice pour ouvrir les fichiers .odt envoyés par un fournisseur.",
                'requester' => 'user4', 'days_ago' => 5, 'status' => TicketStatus::Resolved,
                'category' => 'logiciel', 'priority' => TicketPriority::Low, 'technician' => 'tech1',
                'ai' => ['logiciel', TicketPriority::Low, 0.97],
                'messages' => [
                    ['tech1', 'LibreOffice est installé sur votre poste. Il apparaîtra dans le menu Démarrer après un redémarrage.'],
                ],
            ],
            [
                'title' => 'Le serveur de fichiers est inaccessible pour tout le service',
                'description' => "Depuis 8 h 30, plus personne à la comptabilité n'accède au lecteur réseau P:. Nous ne pouvons plus travailler sur la clôture mensuelle.",
                'requester' => 'user1', 'days_ago' => 1, 'status' => TicketStatus::InProgress,
                'category' => 'reseau', 'priority' => TicketPriority::Critical, 'technician' => 'tech2',
                'ai' => ['reseau', TicketPriority::Critical, 0.90],
                'messages' => [
                    ['tech2', 'Le commutateur de l\'étage ne répond plus au ping. Je vais vérifier sur place.', true],
                    ['tech2', 'Nous avons identifié une panne sur un équipement réseau de votre étage. Intervention en cours.'],
                    ['user1', 'Merci, tenez-nous au courant : la clôture doit partir demain.'],
                ],
            ],
            [
                'title' => 'Impossible de se connecter au VPN depuis mon domicile',
                'description' => "En télétravail, le client VPN affiche « Échec de l'authentification » alors que mon mot de passe fonctionne au bureau.",
                'requester' => 'user2', 'days_ago' => 2, 'status' => TicketStatus::InProgress,
                'category' => 'reseau', 'priority' => TicketPriority::High, 'technician' => 'tech1',
                // The technician rejected the suggestion and triaged by hand.
                'ai' => ['acces-comptes', TicketPriority::High, 0.55], 'ai_rejected' => true,
                'messages' => [
                    ['tech1', 'Bonjour, votre certificat VPN a expiré hier. Je vous en génère un nouveau.'],
                ],
            ],
            [
                'title' => 'Nouvelle salariée : création du compte et de la boîte mail',
                'description' => "Une nouvelle assistante arrive lundi prochain aux ressources humaines. Il lui faut un compte Windows, une boîte mail et l'accès au logiciel de paie.",
                'requester' => 'user3', 'days_ago' => 3, 'status' => TicketStatus::InProgress,
                'category' => 'acces-comptes', 'priority' => TicketPriority::Normal, 'technician' => 'tech1',
            ],
            [
                'title' => 'Outlook ne synchronise plus mes e-mails',
                'description' => 'Outlook affiche « Déconnecté » en bas de la fenêtre depuis ce matin. Je reçois pourtant mes e-mails sur mon téléphone.',
                'requester' => 'user5', 'days_ago' => 2, 'status' => TicketStatus::WaitingUser,
                'category' => 'messagerie', 'priority' => TicketPriority::Normal, 'technician' => 'tech2',
                'ai' => ['messagerie', TicketPriority::Normal, 0.91],
                'messages' => [
                    ['tech2', 'Bonjour, pouvez-vous me dire si le problème persiste après un redémarrage complet du poste ?'],
                ],
            ],
            [
                'title' => 'Mot de passe Windows oublié après mes congés',
                'description' => 'De retour de congés, je ne me souviens plus de mon mot de passe de session et mon compte est maintenant verrouillé.',
                'requester' => 'user4', 'days_ago' => 1, 'status' => TicketStatus::Assigned,
                'category' => 'acces-comptes', 'priority' => TicketPriority::High, 'technician' => 'tech1',
                'ai' => ['acces-comptes', TicketPriority::High, 0.96],
            ],
            [
                'title' => 'Excel se ferme tout seul à l\'ouverture d\'un gros fichier',
                'description' => "Le classeur du budget prévisionnel (environ 40 Mo) fait planter Excel dès l'ouverture. Les autres fichiers s'ouvrent normalement.",
                'requester' => 'user1', 'days_ago' => 1, 'status' => TicketStatus::Assigned,
                'category' => 'logiciel', 'priority' => TicketPriority::Normal, 'technician' => 'tech2',
            ],
            [
                'title' => 'Accès au dossier partagé « Contrats clients »',
                'description' => "Je viens de rejoindre l'équipe commerciale et je n'ai pas accès au dossier partagé des contrats clients.",
                'requester' => 'user2', 'days_ago' => 0, 'status' => TicketStatus::PendingValidation,
                'category' => 'acces-comptes', 'priority' => TicketPriority::Normal,
                'ai' => ['acces-comptes', TicketPriority::Normal, 0.89],
            ],
            [
                'title' => 'Imprimante du 2e étage : bourrage papier permanent',
                'description' => "L'imprimante du 2e étage affiche « Bourrage papier » même après avoir retiré toutes les feuilles.",
                'requester' => 'user3', 'days_ago' => 0, 'status' => TicketStatus::PendingValidation,
                'ai' => ['materiel', TicketPriority::Normal, 0.93],
                'ai_reply' => [
                    "Bonjour,\n\nCe message apparaît quand une feuille est restée coincée à l'intérieur. Pouvez-vous essayer ceci :\n\n1. Éteindre l'imprimante.\n2. Ouvrir le capot arrière et retirer doucement les feuilles coincées.\n3. Vérifier qu'aucune feuille n'est pliée dans le bac papier.\n4. Rallumer l'imprimante.\n\nSi le message revient, dites-le-nous : un technicien passera contrôler les rouleaux.",
                    'Bourrage papier sur une imprimante multifonction',
                ],
            ],
            [
                'title' => 'L\'imprimante du 2e étage ne répond plus',
                'description' => "Impossible d'imprimer sur l'imprimante du deuxième étage : les documents restent bloqués dans la file d'attente.",
                'requester' => 'user5', 'days_ago' => 0, 'status' => TicketStatus::PendingValidation,
                'ai' => ['materiel', TicketPriority::Normal, 0.88],
                // The only printer article is about paper jams: the AI says that nothing fits.
                'ai_no_article' => true,
            ],
            [
                'title' => 'Wifi très lent dans la salle de réunion',
                'description' => "Pendant les visioconférences en salle de réunion, l'image se fige et le son se coupe. Le problème n'existe pas dans les bureaux.",
                'requester' => 'user4', 'days_ago' => 0, 'status' => TicketStatus::PendingValidation,
                // Low confidence: network or hardware? The technician is warned.
                'ai' => ['reseau', TicketPriority::Normal, 0.58],
                'ai_reply' => [
                    "Bonjour,\n\nPour vos visioconférences en salle de réunion, deux vérifications :\n\n1. Votre poste doit être connecté au réseau « Entreprise » et non au réseau « Invités », qui est bridé.\n2. Le câble réseau disponible sur la table de réunion donne une connexion bien plus stable que le wifi.\n\nSi le problème continue, indiquez-nous l'heure et la salle : nous contrôlerons la borne.",
                    'Wifi lent ou instable dans une salle de réunion',
                ],
            ],
        ];
    }
}
