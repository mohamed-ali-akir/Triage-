# Feuille de route : Triage face aux helpdesks du marché

Ce document compare Triage aux logiciels du même type, classe les améliorations par intérêt pour
une entreprise, et dit ce qui a été fait et ce qui reste. Il sert à répondre à la question
« pourquoi une PME choisirait-elle cet outil ? ».

## Ce qu'une entreprise attend d'un helpdesk

Les helpdesks les plus répandus (Zendesk, Freshdesk, Jira Service Management, GLPI, Zammad,
osTicket) ont un socle commun. Une entreprise qui évalue un outil le compare à ce socle :

| Attente | Pourquoi elle compte | Triage |
|---|---|---|
| Tickets, statuts, historique, notifications | Le métier de base | Présent depuis la phase 1 |
| Rôles et droits | Séparer salariés, support, administration | Présent (Policies testées) |
| Délais cibles par priorité (SLA) | S'engager sur un temps de réponse | Présent, en heures calendaires |
| Tableau de bord | Piloter le support | Présent |
| Base de connaissances interne | Capitaliser les solutions | Présente depuis la phase 3 |
| **Libre-service pour les salariés** | Éviter les tickets qui ont déjà une réponse | **Ajouté** : centre d'aide et articles suggérés |
| **Pièces jointes** | Une capture d'écran vaut dix messages | **Ajouté** |
| **Enquête de satisfaction** | Mesurer la qualité, pas seulement la vitesse | **Ajouté** |
| **Affectation à un collègue** | Répartir le travail, gérer les absences | **Ajouté** |
| **Clôture automatique** | Ne pas laisser traîner les tickets résolus | **Ajouté** |
| **Export des données** | Rapports, tableur, sortie de l'outil | **Ajouté** (CSV) |
| Double authentification, journal de sécurité | Exigence des services informatiques | Présents (voir `SECURITE.md`) |
| Conformité RGPD | Obligation légale en France | Présente (voir `RGPD.md`) |
| Application mobile | Technicien en déplacement | Présente (Android) |
| Connexion unique (SSO) | Un seul compte pour tous les outils | Non fait |
| Tickets créés par e-mail | Le canal habituel des salariés | Non fait |
| Réponses types | Gagner du temps sur les réponses répétitives | Non fait (l'IA propose un brouillon) |

## Ce qui distingue Triage

- **Le tri assisté par IA, validé par un humain.** L'IA propose une catégorie, une priorité et
  un brouillon de réponse ; un technicien décide toujours. Le journal montre ce qu'elle coûte et
  ce que les techniciens font de ses suggestions.
- **Une IA mesurée, pas vantée.** La commande `triage:evaluate` compare l'IA à une méthode sans
  IA, avec une marge d'erreur. Le premier résultat publié montre qu'un petit modèle local fait
  moins bien que des mots-clés : c'est écrit tel quel.
- **Les données peuvent rester dans l'entreprise.** Avec Ollama, rien ne sort. Avec un
  fournisseur externe, les données personnelles sont masquées et les mots de passe ne sont
  jamais stockés.
- **Un outil qu'on installe chez soi.** Laravel et MySQL, sans dépendance à un service en ligne.

## Les améliorations, classées

Le classement suit une règle simple : ce qui réduit le plus le travail du support ou lève le
plus gros frein à l'adoption passe en premier ; ce qui demande une infrastructure extérieure
impossible à vérifier ici passe après.

| Rang | Amélioration | Effet attendu | État |
|---|---|---|---|
| 1 | Centre d'aide et articles suggérés à la création d'un ticket | Moins de tickets : le salarié trouve la réponse avant d'écrire | Fait |
| 2 | Pièces jointes | Tickets mieux décrits, moins d'allers-retours | Fait |
| 3 | Enquête de satisfaction | Un indicateur de qualité pour la direction | Fait |
| 4 | Affectation à un technicien choisi | Répartition du travail, relais pendant une absence | Fait |
| 5 | Clôture automatique des tickets résolus | Une file propre, une purge RGPD qui suit | Fait |
| 6 | Export CSV | Rapports libres, pas d'enfermement dans l'outil | Fait |
| 7 | Connexion unique (Microsoft Entra, Google) | Adoption par les entreprises qui l'exigent | À faire |
| 8 | Tickets créés par e-mail | Le salarié garde son habitude | À faire |
| 9 | Alerte avant le dépassement d'un délai | Agir avant le retard, pas après | À faire |
| 10 | Délais en heures ouvrées | Des engagements réalistes hors des nuits et week-ends | À faire |
| 11 | Réponses types | Réponses répétitives en un clic | À faire |
| 12 | Notification Teams ou Slack | Le support voit les tickets critiques là où il travaille | À faire |
| 13 | Pièces jointes dans l'application mobile, analyse antivirus des fichiers | Parité mobile, défense supplémentaire | À faire |

## Pourquoi les points 7 et 8 ne sont pas faits

- **Connexion unique.** Elle demande un fournisseur d'identité réel pour être testée de bout en
  bout. Sans lui, le code ne pourrait être vérifié qu'avec des réponses simulées : pour une porte
  d'entrée de l'application, ce n'est pas suffisant. Le point d'accroche existe déjà :
  `CredentialChecker` est le seul endroit qui décide d'une connexion.
- **Tickets par e-mail.** Il faut une boîte aux lettres et un service de réception. Le point
  d'accroche existe aussi : l'action `CreateTicket` crée un ticket d'où qu'il vienne.

## La sécurité des nouvelles fonctions

Chaque ajout a été pensé avec son risque propre ; le détail est dans `SECURITE.md`.

| Fonction | Risque | Réponse |
|---|---|---|
| Centre d'aide | Une procédure interne montrée aux salariés | Article interne par défaut ; 404 identique pour un article caché |
| Pièces jointes | Dépôt d'un fichier dangereux, lecture par un tiers | Types lus dans le contenu, disque privé, nom aléatoire, téléchargement autorisé par la Policy |
| Export CSV | Formule exécutée à l'ouverture dans un tableur | Cellules neutralisées ; export journalisé et réservé à l'administrateur |
| Avis du demandeur | Texte libre conservé | Mot de passe retiré, commentaire effacé par la purge |
| Affectation | Ticket donné à un compte sans droit | Seulement à un technicien actif, par le support, tracé dans l'historique |
