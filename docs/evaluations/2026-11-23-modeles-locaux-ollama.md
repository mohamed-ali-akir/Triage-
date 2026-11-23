# Évaluation du tri des tickets

- Date : 23/11/2026 à 13:08
- Jeu de test : 60 tickets étiquetés à la main (`database/data/evaluation-tickets.json`)
- Commande : `php artisan triage:evaluate`

## Résumé

| Méthode | Catégorie | Priorité | Priorité à un niveau près | Catégorie et priorité | Temps moyen | Coût estimé | Sans réponse |
|---|---|---|---|---|---|---|---|
| Référence par mots-clés | 78 % ± 10 | 68 % ± 12 | 90 % | 52 % ± 13 | < 1 ms | — | 0 |
| IA — ollama / llama3.2:3b | 60 % ± 12 | 55 % ± 13 | 98 % | 28 % ± 11 | 5,05 s | 0,0000 $ | 0 |
| IA — ollama / qwen2.5:1.5b | 62 % ± 12 | 42 % ± 12 | 80 % | 27 % ± 11 | 2,29 s | 0,0000 $ | 0 |

Le chiffre après « ± » est la marge d'erreur à 95 % : avec si peu de tickets, un écart
plus petit que cette marge entre deux méthodes ne prouve rien.

## Lecture

- **IA — ollama / llama3.2:3b** : -18 points sur la catégorie par rapport à la référence par mots-clés (marge d'erreur de la différence : ± 16 points) ; cet écart dépasse la marge d'erreur de la différence : il est significatif.
- **IA — ollama / qwen2.5:1.5b** : -17 points sur la catégorie par rapport à la référence par mots-clés (marge d'erreur de la différence : ± 16 points) ; cet écart dépasse la marge d'erreur de la différence : il est significatif.

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

## IA — ollama / llama3.2:3b

### Précision par catégorie

| Catégorie attendue | Bonnes réponses | Précision |
|---|---|---|
| `acces-comptes` | 8 / 10 | 80 % |
| `autre` | 2 / 10 | 20 % |
| `logiciel` | 10 / 10 | 100 % |
| `materiel` | 7 / 10 | 70 % |
| `messagerie` | 7 / 10 | 70 % |
| `reseau` | 2 / 10 | 20 % |

### Matrice de confusion

En ligne la catégorie attendue, en colonne la catégorie proposée.

| attendue \ proposée | `acces-comptes` | `autre` | `logiciel` | `materiel` | `messagerie` | `reseau` |
|---|---|---|---|---|---|---|
| `acces-comptes` | **8** | · | 1 | 1 | · | · |
| `autre` | 1 | **2** | 2 | 5 | · | · |
| `logiciel` | · | · | **10** | · | · | · |
| `materiel` | · | 1 | 2 | **7** | · | · |
| `messagerie` | 2 | 1 | · | · | **7** | · |
| `reseau` | 5 | 2 | · | 1 | · | **2** |

### Fiabilité du score de confiance

Seuil du badge « Confiance faible » : 0,70.

| Score de confiance | Catégorie et priorité justes |
|---|---|
| Au-dessus du seuil | 17 / 59 entièrement justes (29 %) |
| En dessous du seuil | 0 / 1 entièrement justes (0 %) |

Un score utile donne nettement plus de réponses justes au-dessus du seuil qu'en dessous.

Consommation : 42 568 jetons en entrée, 1 689 jetons en sortie.

### Erreurs

| N° | Ticket | Attendu | Proposé |
|---|---|---|---|
| 1 | Écran qui clignote | `materiel` / Normale | `logiciel` / Normale |
| 2 | PC portable ne s'allume plus | `materiel` / Haute | `materiel` / Critique |
| 3 | Touche Entrée du clavier capricieuse | `materiel` / Basse | `logiciel` / Normale |
| 5 | Demande d'un second écran | `materiel` / Basse | `autre` / Normale |
| 6 | Souris sans fil qui ne répond plus | `materiel` / Basse | `materiel` / Normale |
| 8 | Vidéoprojecteur de la salle B en panne | `materiel` / Haute | `materiel` / Critique |
| 9 | Batterie du portable qui tient 20 minutes | `materiel` / Normale | `materiel` / Basse |
| 10 | Douchette code-barres de l'expédition HS | `materiel` / Haute | `materiel` / Normale |
| 12 | Le wifi Invités ne fonctionne pas | `reseau` / Normale | `autre` / Normale |
| 13 | VPN qui se déconnecte régulièrement | `reseau` / Normale | `acces-comptes` / Normale |
| 14 | Lecteur P: inaccessible pour toute la comptabilité | `reseau` / Critique | `materiel` / Critique |
| 16 | Connexion très lente tous les matins | `reseau` / Normale | `acces-comptes` / Normale |
| 17 | L'agence de Lyon n'accède plus au siège | `reseau` / Critique | `acces-comptes` / Critique |
| 18 | Ouverture d'un accès pour un prestataire | `reseau` / Basse | `acces-comptes` / Normale |
| 19 | Plus aucun appel entrant au standard | `reseau` / Critique | `acces-comptes` / Critique |
| 20 | Visioconférences saccadées en salle A | `reseau` / Normale | `autre` / Normale |
| 21 | Mot de passe oublié, compte verrouillé | `acces-comptes` / Haute | `acces-comptes` / Critique |
| 26 | Demande de droits administrateur | `acces-comptes` / Basse | `acces-comptes` / Normale |
| 27 | Nouveau téléphone : plus de codes de double authentification | `acces-comptes` / Haute | `materiel` / Haute |
| 28 | Accès au logiciel de paie pour ma remplaçante | `acces-comptes` / Normale | `logiciel` / Normale |
| 29 | Mot de passe expiré pendant mes congés | `acces-comptes` / Haute | `acces-comptes` / Normale |
| 30 | Changement de nom sur mon compte | `acces-comptes` / Basse | `acces-comptes` / Normale |
| 32 | URGENT !!! Installation de Photoshop | `logiciel` / Basse | `logiciel` / Critique |
| 34 | Mise à jour Windows bloquée à 30 % | `logiciel` / Haute | `logiciel` / Normale |
| 35 | Le correcteur orthographique de Word a disparu | `logiciel` / Basse | `logiciel` / Normale |
| 39 | Besoin d'un outil pour signer des PDF | `logiciel` / Basse | `logiciel` / Normale |
| 40 | Le logiciel de CAO se ferme au lancement | `logiciel` / Haute | `logiciel` / Critique |
| 43 | E-mail suspect qui demande mes identifiants | `messagerie` / Haute | `messagerie` / Critique |
| 44 | J'ai saisi mon mot de passe sur un faux site | `messagerie` / Critique | `acces-comptes` / Critique |
| 45 | Création d'une adresse partagée contact@ | `messagerie` / Basse | `autre` / Normale |
| 46 | Nos e-mails vers un client sont rejetés | `messagerie` / Haute | `messagerie` / Critique |
| 47 | Mettre à jour ma signature | `messagerie` / Basse | `messagerie` / Normale |
| 49 | Calendrier partagé invisible | `messagerie` / Normale | `acces-comptes` / Normale |
| 51 | Question sur la charte informatique | `autre` / Basse | `autre` / Normale |
| 52 | Demande de formation Excel | `autre` / Basse | `logiciel` / Normale |
| 53 | Commande d'un téléphone portable professionnel | `autre` / Basse | `materiel` / Normale |
| 54 | Mon badge n'ouvre plus la porte d'entrée | `autre` / Normale | `acces-comptes` / Normale |
| 55 | Déménagement de quatre postes vendredi | `autre` / Normale | `materiel` / Normale |
| 56 | Où trouver le modèle de note de frais ? | `autre` / Basse | `autre` / Normale |
| 57 | Fuite d'eau dans la salle serveur | `autre` / Critique | `materiel` / Critique |
| 58 | Climatisation de la salle serveur en panne | `autre` / Critique | `materiel` / Critique |
| 59 | Devis pour trois ordinateurs portables | `autre` / Basse | `logiciel` / Normale |
| 60 | Récupérer un dossier supprimé par erreur | `autre` / Haute | `materiel` / Normale |

## IA — ollama / qwen2.5:1.5b

### Précision par catégorie

| Catégorie attendue | Bonnes réponses | Précision |
|---|---|---|
| `acces-comptes` | 8 / 10 | 80 % |
| `autre` | 4 / 10 | 40 % |
| `logiciel` | 9 / 10 | 90 % |
| `materiel` | 6 / 10 | 60 % |
| `messagerie` | 3 / 10 | 30 % |
| `reseau` | 7 / 10 | 70 % |

### Matrice de confusion

En ligne la catégorie attendue, en colonne la catégorie proposée.

| attendue \ proposée | `acces-comptes` | `autre` | `logiciel` | `materiel` | `messagerie` | `reseau` |
|---|---|---|---|---|---|---|
| `acces-comptes` | **8** | · | · | 2 | · | · |
| `autre` | 1 | **4** | · | 2 | · | 3 |
| `logiciel` | · | · | **9** | · | · | 1 |
| `materiel` | 1 | · | · | **6** | · | 3 |
| `messagerie` | 1 | 2 | 3 | 1 | **3** | · |
| `reseau` | 3 | · | · | · | · | **7** |

### Fiabilité du score de confiance

Seuil du badge « Confiance faible » : 0,70.

| Score de confiance | Catégorie et priorité justes |
|---|---|
| Au-dessus du seuil | 6 / 17 entièrement justes (35 %) |
| En dessous du seuil | 10 / 43 entièrement justes (23 %) |

Un score utile donne nettement plus de réponses justes au-dessus du seuil qu'en dessous.

Consommation : 41 308 jetons en entrée, 1 672 jetons en sortie.

### Erreurs

| N° | Ticket | Attendu | Proposé |
|---|---|---|---|
| 1 | Écran qui clignote | `materiel` / Normale | `materiel` / Basse |
| 2 | PC portable ne s'allume plus | `materiel` / Haute | `materiel` / Basse |
| 3 | Touche Entrée du clavier capricieuse | `materiel` / Basse | `acces-comptes` / Basse |
| 4 | Imprimante de l'accueil : traces noires | `materiel` / Normale | `materiel` / Basse |
| 5 | Demande d'un second écran | `materiel` / Basse | `materiel` / Normale |
| 7 | Onduleur de la salle serveur en alarme | `materiel` / Critique | `reseau` / Critique |
| 8 | Vidéoprojecteur de la salle B en panne | `materiel` / Haute | `reseau` / Critique |
| 9 | Batterie du portable qui tient 20 minutes | `materiel` / Normale | `materiel` / Basse |
| 10 | Douchette code-barres de l'expédition HS | `materiel` / Haute | `reseau` / Basse |
| 12 | Le wifi Invités ne fonctionne pas | `reseau` / Normale | `reseau` / Basse |
| 14 | Lecteur P: inaccessible pour toute la comptabilité | `reseau` / Critique | `acces-comptes` / Critique |
| 15 | Prise réseau murale morte au bureau 214 | `reseau` / Normale | `reseau` / Basse |
| 17 | L'agence de Lyon n'accède plus au siège | `reseau` / Critique | `acces-comptes` / Critique |
| 18 | Ouverture d'un accès pour un prestataire | `reseau` / Basse | `acces-comptes` / Normale |
| 19 | Plus aucun appel entrant au standard | `reseau` / Critique | `reseau` / Basse |
| 20 | Visioconférences saccadées en salle A | `reseau` / Normale | `reseau` / Basse |
| 21 | Mot de passe oublié, compte verrouillé | `acces-comptes` / Haute | `acces-comptes` / Critique |
| 23 | Accès refusé au dossier RH | `acces-comptes` / Haute | `acces-comptes` / Normale |
| 26 | Demande de droits administrateur | `acces-comptes` / Basse | `acces-comptes` / Normale |
| 27 | Nouveau téléphone : plus de codes de double authentification | `acces-comptes` / Haute | `materiel` / Basse |
| 29 | Mot de passe expiré pendant mes congés | `acces-comptes` / Haute | `materiel` / Basse |
| 30 | Changement de nom sur mon compte | `acces-comptes` / Basse | `acces-comptes` / Normale |
| 32 | URGENT !!! Installation de Photoshop | `logiciel` / Basse | `logiciel` / Critique |
| 34 | Mise à jour Windows bloquée à 30 % | `logiciel` / Haute | `logiciel` / Normale |
| 35 | Le correcteur orthographique de Word a disparu | `logiciel` / Basse | `logiciel` / Normale |
| 36 | ERP très lent pour tout l'atelier | `logiciel` / Haute | `reseau` / Basse |
| 38 | Teams ne démarre plus | `logiciel` / Normale | `logiciel` / Basse |
| 39 | Besoin d'un outil pour signer des PDF | `logiciel` / Basse | `logiciel` / Normale |
| 40 | Le logiciel de CAO se ferme au lancement | `logiciel` / Haute | `logiciel` / Critique |
| 41 | Outlook ne reçoit plus les nouveaux messages | `messagerie` / Normale | `logiciel` / Normale |
| 42 | Boîte aux lettres pleine | `messagerie` / Normale | `logiciel` / Basse |
| 43 | E-mail suspect qui demande mes identifiants | `messagerie` / Haute | `messagerie` / Basse |
| 44 | J'ai saisi mon mot de passe sur un faux site | `messagerie` / Critique | `autre` / Basse |
| 45 | Création d'une adresse partagée contact@ | `messagerie` / Basse | `materiel` / Normale |
| 46 | Nos e-mails vers un client sont rejetés | `messagerie` / Haute | `messagerie` / Critique |
| 47 | Mettre à jour ma signature | `messagerie` / Basse | `autre` / Basse |
| 49 | Calendrier partagé invisible | `messagerie` / Normale | `logiciel` / Normale |
| 50 | Message d'absence pour une collègue en arrêt | `messagerie` / Normale | `acces-comptes` / Normale |
| 53 | Commande d'un téléphone portable professionnel | `autre` / Basse | `materiel` / Normale |
| 54 | Mon badge n'ouvre plus la porte d'entrée | `autre` / Normale | `acces-comptes` / Critique |
| 55 | Déménagement de quatre postes vendredi | `autre` / Normale | `reseau` / Basse |
| 57 | Fuite d'eau dans la salle serveur | `autre` / Critique | `reseau` / Basse |
| 58 | Climatisation de la salle serveur en panne | `autre` / Critique | `reseau` / Critique |
| 60 | Récupérer un dossier supprimé par erreur | `autre` / Haute | `materiel` / Basse |

