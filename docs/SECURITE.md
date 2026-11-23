# Sécurité

Ce document reprend l'audit de sécurité du 23 novembre 2026 : les 16 faiblesses trouvées, ce qui
a été corrigé, le fichier qui applique chaque correction et le test qui la vérifie. Il se
termine par ce qui reste une limite.

## Avant une mise en production

```bash
php artisan triage:check-deployment
```

La commande vérifie le mode débogage, HTTPS, le cookie de session, le compte de la base de
données, les comptes de démonstration restés avec leur mot de passe connu, et la double
authentification du support. Elle échoue tant qu'un point reste à corriger.

Variables à renseigner dans `.env` :

| Variable | Valeur en production |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | une adresse en `https://` |
| `REQUIRE_2FA_FOR_STAFF` | `true` |
| `TRUSTED_PROXIES` | l'adresse du proxy placé devant l'application, s'il y en a un |
| `DB_USERNAME`, `DB_PASSWORD` | un compte réservé à Triage, jamais `root` |

## Les 16 points de l'audit

### Gravité élevée

| N° | Faiblesse | Correction | Code | Test |
|---|---|---|---|---|
| 1 | Le masquage laissait passer des mots de passe (« mon mdp Azerty123 ne marche plus »), des IBAN, des numéros de carte, des identifiants, des adresses postales | Motifs élargis ; un mot de passe est en plus retiré du ticket avant d'être enregistré | `PiiMasker`, `CreateTicket`, `PostTicketMessage` | `PiiMaskerTest`, `HardeningTest` |
| 2 | Configuration par défaut dangereuse en production : débogage actif, comptes de démonstration créés hors production stricte | Commande de vérification ; les comptes de démonstration ne sont créés qu'en local et dans les tests | `CheckDeployment`, `DatabaseSeeder` | `DeploymentCheckTest` |

### Gravité moyenne

| N° | Faiblesse | Correction | Code | Test |
|---|---|---|---|---|
| 3 | Changer de mot de passe ne déconnectait personne | Les autres sessions sont fermées et les jetons mobiles révoqués, aussi quand le rôle change | `bootstrap/app.php`, `User::booted()` | `AccountProtectionTest` |
| 4 | « Mot de passe oublié » révélait quelles adresses ont un compte | Même réponse dans tous les cas, 5 demandes par minute ; la connexion vérifie toujours un hachage | `PasswordResetLinkRequested`, `CredentialChecker` | `AccountProtectionTest` |
| 5 | Aucune limite de débit une fois connecté | 20 tickets par heure, 10 messages par minute, 120 appels d'API par minute, par compte | `AppServiceProvider`, `TicketThread`, `config/security.php` | `HardeningTest` |
| 6 | Aucun en-tête de sécurité HTTP | `X-Frame-Options`, `Content-Security-Policy`, `nosniff`, HSTS en HTTPS ; cookie de session réservé à HTTPS en production | `AddSecurityHeaders`, `config/session.php` | `HardeningTest` |
| 7 | Injection de prompt partiellement bloquée | Plus aucun chevron dans un ticket ; variables du prompt remplacées en une passe ; le prompt interdit de recopier un secret d'un article | `TicketPromptFormatter`, `PromptRepository`, `reply-system.md` | `PromptInjectionTest`, `PromptRepositoryTest` |
| 8 | Double authentification facultative ; tentatives limitées par adresse IP seulement | Double authentification imposable au support ; 20 tentatives par quart d'heure et par compte, quelle que soit l'adresse IP ; proxy de confiance configurable | `EnsureAccountIsSecured`, `FortifyServiceProvider` | `AccountProtectionTest` |

### Gravité faible

| N° | Faiblesse | Correction | Code | Test |
|---|---|---|---|---|
| 9 | Un jeton mobile ouvrait tous les tickets | L'API n'ouvre que les tickets assignés au technicien | `TicketPolicy::accessFromMobile`, `routes/api.php` | `TicketApiTest` |
| 10 | `role` et `is_active` assignables en masse | Retirés des champs assignables ; l'administration les affecte explicitement | `User`, `Admin/UserController` | `AccountProtectionTest` |
| 11 | Jeton mobile en clair, valable 30 jours, jamais purgé | Chiffré par le Keystore Android, 7 jours, purge quotidienne des jetons expirés, épinglage de certificat en option | `KeystoreTokenStore`, `ApiFactory`, `routes/console.php` | `CertificatePinningTest`, test sur émulateur |
| 12 | La purge RGPD oubliait les tickets résolus jamais clos, et le nom d'une personne effacée restait dans les tickets des autres | Les tickets résolus sont purgés ; le nom et l'adresse sont retirés partout | `PurgeOldTickets`, `TicketAnonymizer` | `RetentionTest` |
| 13 | Le fil de discussion ne revérifiait pas le droit de lecture | Vérifié à chaque rafraîchissement | `TicketThread::render()` | `HardeningTest` |
| 14 | Pas de journal d'audit ; mot de passe choisi par l'administrateur sans changement forcé | Journal de sécurité ; un mot de passe provisoire doit être remplacé à la première connexion | `Audit`, `EnsureAccountIsSecured` | `HardeningTest`, `AccountProtectionTest` |
| 15 | Actions GitHub référencées par étiquette | Chaque action est épinglée sur un commit ; Dependabot propose les mises à jour | `.github/workflows/ci.yml` | La CI elle-même |
| 16 | Ollama en HTTP par défaut | HTTP refusé vers une autre machine, sauf `OLLAMA_ALLOW_INSECURE=true` | `OllamaProvider` | `DeploymentCheckTest` |

## Le journal de sécurité

`storage/logs/audit-AAAA-MM-JJ.log`, conservé 180 jours. Il reçoit :

- les connexions réussies et les déconnexions (identifiant du compte) ;
- les tentatives échouées (adresse e-mail essayée, jamais le mot de passe) ;
- les connexions de l'application mobile et les codes de double authentification refusés ;
- les réinitialisations de mot de passe ;
- les comptes créés ou modifiés par un administrateur : qui, quel compte, quels champs.

Chaque ligne porte l'adresse IP. Le journal n'a pas d'écran dans l'application : il se lit sur
le serveur.

## Ce qui reste une limite

- **Le masquage n'est pas une garantie.** Il repose sur des motifs. Le nom d'une personne
  inconnue de l'application et écrit sans civilité (« Lucas Fontaine arrive lundi ») n'est pas
  reconnu, pas plus qu'un mot de passe uniquement en lettres écrit sans mot-clé. Un modèle
  local (Ollama) reste la seule façon de ne rien envoyer du tout.
- **Une injection de prompt peut encore influencer une suggestion.** Un texte bien tourné peut
  pousser le modèle vers une priorité ou un score de confiance. C'est pour cela que rien n'est
  appliqué sans un technicien, et que la réponse du modèle est validée comme une saisie.
- **Pas de restriction des scripts dans la `Content-Security-Policy`.** Livewire a besoin de
  scripts en ligne. La protection contre le XSS repose sur l'échappement systématique de Blade
  et sur le Markdown sans HTML.
- **Un technicien lit tous les tickets sur le site web** et peut reprendre celui d'un collègue :
  la file commune en a besoin (absences, entraide). La reprise est écrite dans l'historique du
  ticket. Seule l'application mobile est restreinte aux tickets assignés.
- **Le jeton mobile reste utilisable par qui déverrouille le téléphone.** Le chiffrement protège
  d'une copie du stockage, pas d'un téléphone déverrouillé. D'où les 7 jours et la révocation
  au changement de mot de passe.
- **L'épinglage de certificat est une option de compilation**, pas un réglage par défaut : il
  faut connaître l'empreinte du certificat du serveur.
- **La limite par compte permet de gêner quelqu'un** : 20 mauvaises tentatives bloquent la
  connexion à ce compte pendant un quart d'heure. C'est le prix d'une protection contre une
  attaque répartie sur plusieurs machines.
