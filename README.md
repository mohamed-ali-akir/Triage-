# Triage

Helpdesk interne avec tri assisté par IA — projet de BTS SIO option SLAM.

Les salariés d'une PME déclarent leurs incidents informatiques. Une IA propose une catégorie,
une priorité et une réponse ; **un technicien valide toujours avant toute action**.

![File des tickets](docs/captures/file-des-tickets.png)

## Avancement

| Phase | Contenu | État |
|---|---|---|
| 1 | Socle : authentification, rôles, tickets, machine à états, fil de discussion, notifications, CI | Terminée |
| 2 | Tri par IA : classification, anonymisation, mode dégradé | À venir |
| 3 | Base de connaissances et réponses suggérées | À venir |
| 4 | Évaluation de l'IA et tableau de bord | À venir |
| 5 | API REST et application mobile du technicien | À venir |

## Documentation

- [Cahier des charges](docs/CAHIER_DES_CHARGES.md)
- [Modèle de données et diagrammes](docs/MODELE_DONNEES.md)
- [Journal des décisions techniques](docs/DECISIONS.md)

## Prérequis

- PHP 8.3 ou plus récent, avec les extensions `pdo_mysql` et `pdo_sqlite`
- Composer 2
- Node.js 20 ou plus récent
- MySQL 8 ou MariaDB 10.6 (ou SQLite pour un essai rapide)

## Installation pas à pas

```bash
git clone https://github.com/mohamed-ali-akir/Triage-.git triage
cd triage

# 1. Dépendances PHP et JavaScript
composer install
npm install

# 2. Configuration
cp .env.example .env
php artisan key:generate
```

Créez ensuite la base de données, puis renseignez son accès dans `.env` :

```sql
CREATE DATABASE triage CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
# 3. Tables et données de démonstration
php artisan migrate --seed

# 4. Compilation des feuilles de style
npm run build

# 5. Lancement
php artisan serve
```

L'application est disponible sur <http://localhost:8000>.

Pour repartir d'une base propre à tout moment (avant une démonstration par exemple) :

```bash
php artisan migrate:fresh --seed
```

### Essai rapide avec SQLite

Sans MySQL, remplacez dans `.env` le bloc `DB_*` par la seule ligne `DB_CONNECTION=sqlite`,
créez le fichier vide `database/database.sqlite`, puis reprenez à l'étape 3.

## Comptes de démonstration

Tous les comptes ont le mot de passe `password`. **Ils ne doivent jamais exister en production** :
le seeder de démonstration ne s'exécute pas quand `APP_ENV=production`.

| Rôle | Nom | Adresse e-mail |
|---|---|---|
| Administrateur | Alice Martin | `admin@triage.test` |
| Technicien | Thomas Bernard | `tech1@triage.test` |
| Technicien | Sarah Petit | `tech2@triage.test` |
| Utilisateur | Julie Moreau | `julie.moreau@triage.test` |
| Utilisateur | Karim Benali | `karim.benali@triage.test` |
| Utilisateur | Sophie Lambert | `sophie.lambert@triage.test` |
| Utilisateur | Marc Dubois | `marc.dubois@triage.test` |
| Utilisateur | Élodie Garnier | `elodie.garnier@triage.test` |

Le seeder crée aussi 15 tickets, dans tous les statuts, avec leurs messages et leur historique.

## Variables `.env` importantes

| Variable | Rôle | Valeur par défaut |
|---|---|---|
| `APP_URL` | Adresse de l'application (utilisée dans les e-mails) | `http://localhost:8000` |
| `APP_LOCALE` | Langue de l'interface | `fr` |
| `APP_TIMEZONE` | Fuseau horaire | `Europe/Paris` |
| `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Accès à la base de données | MySQL local, base `triage` |
| `QUEUE_CONNECTION` | File d'attente | `database` |
| `MAIL_MAILER` | Envoi des e-mails. `log` les écrit dans `storage/logs/laravel.log` | `log` |
| `MAIL_FROM_ADDRESS` | Expéditeur des e-mails | `support@triage.test` |

## File d'attente

Les notifications (et, à partir de la phase 2, les appels à l'IA) passent par une file
d'attente stockée en base de données. Il faut donc un processus qui la traite :

```bash
php artisan queue:work
```

Sans ce processus, l'application fonctionne mais les notifications restent en attente.

En développement, `composer dev` lance en une commande le serveur, la file d'attente et Vite.

## Qualité

```bash
php artisan test     # tests (Pest)
composer analyse     # analyse statique (PHPStan niveau 6)
composer lint        # style du code (Pint)
composer quality     # les trois à la suite
```

La CI GitHub Actions exécute les mêmes contrôles à chaque pull request : tests sur SQLite
(PHP 8.3 et 8.4) et sur MySQL, PHPStan, Pint, `composer audit` et compilation des assets.

## Organisation du code

| Dossier | Contenu |
|---|---|
| `app/Enums` | Rôles, statuts, priorités. La machine à états est dans `TicketStatus` |
| `app/Services/Tickets` | `TicketWorkflow` (seul point d'entrée pour modifier un ticket) et `TicketNotifier` |
| `app/Actions` | Opérations partagées entre le web et la future API (créer un ticket, publier un message) |
| `app/Policies` | Règles d'accès à un ticket |
| `app/Livewire` | File du technicien et fil de discussion |
| `app/Http` | Contrôleurs, Form Requests, middleware |
| `database/seeders` | Données de référence et données de démonstration |
| `tests` | Tests de fonctionnalité (`Feature`) et unitaires (`Unit`) |
