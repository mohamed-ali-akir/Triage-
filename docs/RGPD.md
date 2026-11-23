# Protection des données personnelles (RGPD)

Ce document décrit les données personnelles traitées par Triage, pourquoi elles le sont, combien
de temps elles sont gardées et comment elles sont protégées. Il renvoie au code qui applique
chaque règle : rien ici n'est une simple intention.

## 1. Responsable et finalité

- **Responsable du traitement** : l'entreprise qui installe Triage pour son support interne.
- **Finalité** : enregistrer, suivre et résoudre les demandes informatiques des salariés.
- **Base légale** : l'intérêt légitime de l'employeur à assurer le fonctionnement de son
  système d'information (article 6.1.f du RGPD).

Les données ne servent à rien d'autre : ni évaluation des salariés, ni publicité, ni
entraînement d'un modèle d'IA par l'application.

## 2. Données traitées

| Donnée | Où | Pourquoi |
|---|---|---|
| Nom, adresse e-mail professionnelle, service, rôle | table `users` | Identifier le demandeur et le technicien |
| Mot de passe (haché, jamais lisible) | table `users` | Authentification |
| Secret de double authentification (chiffré) | table `users` | Authentification renforcée |
| Titre et description des tickets | table `tickets` | Traiter la demande |
| Messages du fil de discussion | table `ticket_messages` | Échanger avec le demandeur |
| Historique des tickets (qui a fait quoi, quand) | table `ticket_events` | Traçabilité |
| Adresse IP et navigateur de la session | table `sessions` | Sécurité de la connexion |

Un ticket est un texte libre : un salarié peut y écrire des données personnelles (un nom, un
numéro de téléphone) sans que l'application le demande. Le formulaire rappelle de ne jamais
écrire de mot de passe.

**Données absentes par conception :**

- La table `notifications` ne contient que le numéro du ticket et une phrase générique.
- La table `ai_logs` n'a aucune colonne de texte : elle ne peut contenir ni prompt ni réponse.
- Il n'y a pas de pièces jointes.

## 3. Envoi de données à un fournisseur d'IA

Quand l'IA est activée, le texte du ticket est envoyé au fournisseur configuré. Trois mesures
limitent ce transfert :

1. **Anonymisation avant envoi** (`app/Services/Privacy/PiiMasker.php`). Les adresses e-mail,
   numéros de téléphone, adresses IP, noms des utilisateurs connus et mots de passe écrits en
   clair sont remplacés par des jetons (`[EMAIL_1]`, `[NOM_1]`...). La table de correspondance
   reste sur le serveur de l'entreprise.
2. **Minimisation.** Seuls le titre et la description partent. Ni l'identité du demandeur, ni
   son service, ni le fil de discussion ne sont envoyés.
3. **Choix du fournisseur.** Avec `AI_PROVIDER=ollama`, le modèle tourne sur une machine de
   l'entreprise et rien ne sort. Avec `AI_ENABLED=false`, aucun envoi n'a lieu.

**Limite connue.** L'anonymisation repose sur des motifs (expressions régulières) et sur la
liste des utilisateurs. Elle ne reconnaît pas le nom d'une personne extérieure à l'entreprise,
ni une donnée écrite de façon inhabituelle. Elle réduit fortement le risque, elle ne le
supprime pas. C'est pourquoi le fournisseur doit être encadré par un contrat de sous-traitance
(article 28 du RGPD), et pourquoi un modèle local reste possible.

Les articles de la base de connaissances sont envoyés tels quels : ils décrivent des
procédures et ne doivent pas contenir de données personnelles.

## 4. Durées de conservation

| Donnée | Durée | Mécanisme |
|---|---|---|
| Contenu d'un ticket clos (titre, description, messages, brouillons de l'IA) | 24 mois après la clôture | Commande planifiée `triage:purge-old-tickets`, chaque nuit |
| Statistiques d'un ticket (catégorie, priorité, dates, historique) | Sans limite, sans contenu | Conservées après la purge : elles n'identifient plus personne |
| Compte d'un salarié parti | Désactivé au départ, anonymisé sur demande | Écran d'administration, puis `triage:anonymize-user` |
| Sessions | 2 heures d'inactivité | Configuration de Laravel |
| Journal de l'IA | Sans limite | Ne contient aucune donnée personnelle |

La durée de 24 mois se règle avec la variable `TICKET_RETENTION_MONTHS`.

```bash
# Voir combien de tickets seraient concernés, sans rien effacer
php artisan triage:purge-old-tickets --dry-run
```

## 5. Droits des personnes

| Droit | Comment il est exercé |
|---|---|
| **Accès** | Chaque salarié voit ses tickets et ses messages dans l'application. L'administrateur peut fournir une copie sur demande. |
| **Rectification** | L'administrateur corrige le nom, l'adresse e-mail ou le service depuis l'écran « Comptes ». |
| **Effacement** | `php artisan triage:anonymize-user adresse@exemple.fr` : le nom et l'adresse sont remplacés, le compte est désactivé, ses tickets sont vidés de leur contenu, ses notifications sont supprimées. |
| **Opposition au traitement par l'IA** | L'IA peut être désactivée pour toute l'application (`AI_ENABLED=false`). Il n'y a pas de désactivation par personne : c'est une limite actuelle. |

Le compte anonymisé n'est pas supprimé de la base : il reste une coquille (« Utilisateur
supprimé #12 ») pour que les tickets et leur historique restent cohérents.

## 6. Sécurité

- Mots de passe hachés (bcrypt), 12 caractères minimum, double authentification disponible.
- Limitation des tentatives de connexion ; un compte désactivé est déconnecté immédiatement.
- Chaque accès est contrôlé par une Policy : un salarié ne voit que ses propres tickets, les
  notes internes et les suggestions de l'IA ne sont visibles que du support.
- La clé de l'API d'IA est dans `.env`, jamais dans le dépôt Git.
- Les dépendances sont auditées à chaque pull request (`composer audit`) et suivies par Dependabot.

## 7. Décision automatisée

L'IA ne prend aucune décision. Elle propose une catégorie, une priorité et un brouillon de
réponse ; un technicien accepte, corrige ou rejette. L'article 22 du RGPD (décision fondée
exclusivement sur un traitement automatisé) ne s'applique donc pas.

## 8. Ce qui reste à faire par l'entreprise

Triage fournit les mécanismes techniques. L'entreprise qui l'installe doit encore :

- inscrire le traitement dans son registre ;
- informer les salariés (note d'information, charte informatique) ;
- signer un contrat de sous-traitance avec le fournisseur d'IA si elle en utilise un ;
- vérifier la localisation des données chez ce fournisseur.
