# Journal des décisions techniques

Chaque choix important est noté ici, avec sa raison. Ce fichier sert de support pour l'oral.

## Phase 1 — Socle

### D01 — Laravel 13 et PHP 8.3 minimum

Laravel 13 est la dernière version stable ; elle exige PHP 8.3. Je garde `php: ^8.3` plutôt que
8.4 pour que le projet s'installe sur un maximum de machines (poste du lycée, poste de l'oral).
La CI teste les deux versions, 8.3 et 8.4, pour garantir la compatibilité.

### D02 — Pest 4 plutôt que Pest 5

Pest 5 exige PHP 8.4, ce qui contredit D01. Pest 4 fonctionne avec PHP 8.3 et offre la même
syntaxe. Le passage à Pest 5 se fera en changeant une ligne de `composer.json` le jour où le
projet abandonnera PHP 8.3.

### D03 — Fortify plutôt que Breeze

Breeze n'est plus le kit de démarrage officiel depuis Laravel 12. Fortify fournit toute la
logique d'authentification (connexion, mot de passe oublié, double authentification) sans
imposer de vues : j'écris mes propres pages Blade. Résultat : très peu de code généré, donc
tout le code du dépôt est du code que je peux expliquer.

### D04 — Pas d'inscription publique

C'est une application interne : seuls les salariés doivent y accéder. L'administrateur crée les
comptes. Cela supprime une surface d'attaque (création de comptes en masse) et correspond au
fonctionnement réel d'une PME.

### D05 — Rôles par enum PHP plutôt que `spatie/laravel-permission`

Il y a trois rôles fixes et un utilisateur n'en a qu'un. Un enum `UserRole` stocké dans une
colonne de `users`, associé aux Policies, suffit. Le paquet Spatie ajouterait cinq tables et
une gestion dynamique des permissions dont le projet n'a pas besoin.

### D06 — Machine à états écrite à la main

Les transitions permises sont déclarées dans l'enum `TicketStatus`, et le service
`TicketWorkflow` est le seul endroit du code qui change un statut. C'est une soixantaine de
lignes, entièrement testées, que je peux expliquer ligne par ligne. Un paquet comme
`spatie/laravel-model-states` ferait la même chose en cachant le mécanisme.

### D07 — Séparer « qui a le droit » et « quelle transition est valide »

La `TicketPolicy` répond à « cet utilisateur a-t-il le droit ? ». L'enum `TicketStatus` répond
à « cette transition existe-t-elle ? ». Les deux questions sont indépendantes : les séparer
évite de dupliquer les règles et rend chaque partie testable seule.

### D08 — Historique dans une table `ticket_events`

Chaque changement de statut, d'assignation, de priorité ou de catégorie crée une ligne avec
l'ancienne valeur, la nouvelle, l'auteur et la date. L'historique est écrit par
`TicketWorkflow`, dans la même transaction que le changement : il ne peut pas y avoir de
changement sans trace. Cette table servira aussi aux statistiques de la phase 4.

### D09 — Contrôleurs classiques pour les CRUD, Livewire pour les écrans interactifs

Les CRUD (tickets, comptes, catégories) suivent le schéma MVC habituel : route, contrôleur,
Form Request, vue Blade. Livewire est réservé aux deux écrans qui gagnent à se mettre à jour
sans recharger la page : la file du technicien (filtres) et le fil de discussion.

### D10 — Classes d'action pour la logique partagée

Créer un ticket ou publier un message sont des opérations utilisées par le web aujourd'hui et
par l'API mobile en phase 5. Elles vivent dans `app/Actions` : le contrôleur, le composant
Livewire et le futur contrôleur d'API appellent la même classe, sans dupliquer la logique.

### D11 — Table `departments` plutôt qu'un champ texte

Un champ texte libre donnerait « Compta », « compta », « Comptabilité » pour le même service.
Une table avec une clé étrangère respecte la troisième forme normale et permettra des
statistiques par service.

### D12 — Désactiver un compte au lieu de le supprimer

Supprimer un utilisateur casserait l'historique de ses tickets et de ses messages. Le champ
`is_active` bloque la connexion tout en gardant les données cohérentes. L'effacement des
données personnelles sera traité par la purge RGPD de la phase 4.

### D13 — La priorité et la catégorie sont vides à la création

L'utilisateur décrit son problème ; il ne le classe pas. Laisser ces champs à `NULL` distingue
clairement un ticket « pas encore trié » d'un ticket trié en priorité normale. Un ticket ne
peut pas être assigné tant qu'il n'est pas trié.

### D14 — Clôture par l'utilisateur uniquement depuis « résolu »

La machine à états reste linéaire et simple à tester. Un utilisateur qui veut abandonner sa
demande l'écrit dans le fil, et le technicien résout le ticket. Cela évite d'ajouter une
transition « annuler » depuis chaque statut.

### D15 — Notifications en file d'attente, deux canaux

Les notifications utilisent le système natif de Laravel, sur les canaux `database` (cloche dans
l'application) et `mail`. Elles implémentent `ShouldQueue` : l'envoi d'un e-mail ne ralentit
jamais la requête de l'utilisateur. La file utilise le driver `database`, sans Redis.

### D16 — Double authentification proposée, pas imposée

Fortify fournit la double authentification par application (TOTP) et les codes de secours.
Elle est proposée à tous les comptes et recommandée aux techniciens et à l'administrateur.
L'imposer compliquerait la démonstration et la création des comptes.

### D17 — Traductions françaises écrites dans le projet

Les fichiers `lang/fr` contiennent les messages de validation et d'authentification réellement
utilisés. Cela évite une dépendance supplémentaire pour quelques dizaines de phrases. Les
messages absents retombent sur l'anglais (`APP_FALLBACK_LOCALE`).

### D18 — CI : SQLite et MySQL, deux versions de PHP, audit de sécurité

Les tests tournent sur SQLite (rapide) et sur MySQL (base de production), car les deux moteurs
n'ont pas le même comportement. `composer audit` fait échouer la CI si une dépendance a une
faille connue, et Dependabot propose les mises à jour chaque semaine.
