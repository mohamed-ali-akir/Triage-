# Évaluation du tri des tickets

- Date : 23/11/2026 à 12:05
- Jeu de test : 60 tickets étiquetés à la main (`database/data/evaluation-tickets.json`)
- Commande : `php artisan triage:evaluate`

## Résumé

| Méthode | Catégorie | Priorité | Priorité à un niveau près | Catégorie et priorité | Temps moyen | Coût estimé | Sans réponse |
|---|---|---|---|---|---|---|---|
| Référence par mots-clés | 78 % ± 10 | 68 % ± 12 | 90 % | 52 % ± 13 | < 1 ms | — | 0 |

Le chiffre après « ± » est la marge d'erreur à 95 % : avec si peu de tickets, un écart
plus petit que cette marge entre deux méthodes ne prouve rien.

## Référence par mots-clés

### Précision par catégorie

| Catégorie attendue | Bonnes réponses | Précision |
|---|---|---|
| `acces-comptes` | 5 / 10 | 50 % |
| `autre` | 5 / 10 | 50 % |
| `logiciel` | 10 / 10 | 100 % |
| `materiel` | 10 / 10 | 100 % |
| `messagerie` | 9 / 10 | 90 % |
| `reseau` | 8 / 10 | 80 % |

### Matrice de confusion

En ligne la catégorie attendue, en colonne la catégorie proposée.

| attendue \ proposée | `acces-comptes` | `autre` | `logiciel` | `materiel` | `messagerie` | `reseau` |
|---|---|---|---|---|---|---|
| `acces-comptes` | **5** | · | · | · | 5 | · |
| `autre` | · | **5** | 1 | 3 | · | 1 |
| `logiciel` | · | · | **10** | · | · | · |
| `materiel` | · | · | · | **10** | · | · |
| `messagerie` | · | 1 | · | · | **9** | · |
| `reseau` | 2 | · | · | · | · | **8** |

### Erreurs

| N° | Ticket | Attendu | Proposé |
|---|---|---|---|
| 5 | Demande d'un second écran | `materiel` / Basse | `materiel` / Critique |
| 6 | Souris sans fil qui ne répond plus | `materiel` / Basse | `materiel` / Normale |
| 10 | Douchette code-barres de l'expédition HS | `materiel` / Haute | `materiel` / Critique |
| 14 | Lecteur P: inaccessible pour toute la comptabilité | `reseau` / Critique | `acces-comptes` / Critique |
| 17 | L'agence de Lyon n'accède plus au siège | `reseau` / Critique | `reseau` / Normale |
| 18 | Ouverture d'un accès pour un prestataire | `reseau` / Basse | `acces-comptes` / Normale |
| 19 | Plus aucun appel entrant au standard | `reseau` / Critique | `reseau` / Normale |
| 22 | Compte pour un stagiaire qui arrive lundi | `acces-comptes` / Normale | `messagerie` / Normale |
| 23 | Accès refusé au dossier RH | `acces-comptes` / Haute | `acces-comptes` / Normale |
| 24 | Désactiver le compte d'un salarié parti | `acces-comptes` / Normale | `messagerie` / Normale |
| 25 | Je pense que mon compte a été piraté | `acces-comptes` / Critique | `messagerie` / Critique |
| 26 | Demande de droits administrateur | `acces-comptes` / Basse | `acces-comptes` / Normale |
| 27 | Nouveau téléphone : plus de codes de double authentification | `acces-comptes` / Haute | `messagerie` / Haute |
| 30 | Changement de nom sur mon compte | `acces-comptes` / Basse | `messagerie` / Basse |
| 32 | URGENT !!! Installation de Photoshop | `logiciel` / Basse | `logiciel` / Haute |
| 35 | Le correcteur orthographique de Word a disparu | `logiciel` / Basse | `logiciel` / Normale |
| 36 | ERP très lent pour tout l'atelier | `logiciel` / Haute | `logiciel` / Critique |
| 38 | Teams ne démarre plus | `logiciel` / Normale | `logiciel` / Haute |
| 39 | Besoin d'un outil pour signer des PDF | `logiciel` / Basse | `logiciel` / Haute |
| 42 | Boîte aux lettres pleine | `messagerie` / Normale | `messagerie` / Haute |
| 43 | E-mail suspect qui demande mes identifiants | `messagerie` / Haute | `messagerie` / Normale |
| 45 | Création d'une adresse partagée contact@ | `messagerie` / Basse | `autre` / Haute |
| 47 | Mettre à jour ma signature | `messagerie` / Basse | `messagerie` / Normale |
| 51 | Question sur la charte informatique | `autre` / Basse | `materiel` / Basse |
| 52 | Demande de formation Excel | `autre` / Basse | `logiciel` / Basse |
| 53 | Commande d'un téléphone portable professionnel | `autre` / Basse | `materiel` / Normale |
| 55 | Déménagement de quatre postes vendredi | `autre` / Normale | `materiel` / Normale |
| 56 | Où trouver le modèle de note de frais ? | `autre` / Basse | `autre` / Normale |
| 59 | Devis pour trois ordinateurs portables | `autre` / Basse | `reseau` / Basse |

