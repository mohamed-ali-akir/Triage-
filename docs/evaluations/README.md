# Évaluations du tri par IA

Ce dossier contient les rapports produits par la commande `php artisan triage:evaluate`.

## Ce que mesure la commande

Les 60 tickets de `database/data/evaluation-tickets.json` sont classés par deux méthodes :

- **l'IA**, avec exactement le même code que pour un vrai ticket (masquage, prompt, validation) ;
- **une référence par mots-clés**, sans aucune intelligence, qui sert de point de comparaison.

Pour chaque méthode, le rapport donne la précision globale avec sa marge d'erreur, la précision
par catégorie, une matrice de confusion, le temps moyen, le coût estimé, et la fiabilité du
score de confiance.

## Lancer une évaluation

```bash
# La référence seule, sans appel à une IA (gratuit)
php artisan triage:evaluate --baseline-only

# L'IA configurée dans .env, comparée à la référence
php artisan triage:evaluate

# Comparer deux modèles
php artisan triage:evaluate --model=claude-opus-5-5 --model=claude-haiku-5-5

# Un modèle local avec Ollama
php artisan triage:evaluate --provider=ollama --model=llama3.2

# Un essai rapide sur les 10 premiers tickets
php artisan triage:evaluate --limit=10 --no-save
```

Une évaluation complète avec l'IA fait 60 appels (jusqu'à 120 si des réponses sont invalides).

## Limites à connaître pour l'oral

- **60 tickets, c'est peu.** La marge d'erreur est d'environ ± 10 points : un écart de 5 points
  entre deux méthodes ne prouve rien. Le rapport le dit dans sa section « Lecture ».
- **Les étiquettes sont un jugement humain.** Certains tickets sont discutables (un compte à
  désactiver est-il « normal » ou « haut » ?). Relire les erreurs du rapport sert aussi à
  corriger les étiquettes du jeu de test.
- **La référence par mots-clés est avantagée** : ses listes de mots ont été écrites par la même
  personne que le jeu de test. Sur de vrais tickets, elle ferait moins bien.
- **Le prompt ne contient aucun exemple tiré du jeu de test**, pour ne pas fausser la mesure.

## Étiquettes à relire en priorité

Quand les trois méthodes (mots-clés et deux modèles) contredisent la même étiquette, soit le
ticket est difficile, soit l'étiquette est discutable. Dans le rapport du 23 novembre, c'est le
cas de ces tickets : ce sont les premiers à relire dans `database/data/evaluation-tickets.json`.

| N° | Ticket | Étiquette actuelle | Ce que proposent les trois méthodes |
|---|---|---|---|
| 14 | Lecteur P: inaccessible pour toute la comptabilité | `reseau` | `acces-comptes` (2 fois), `materiel` |
| 18 | Ouverture d'un accès pour un prestataire | `reseau` / Basse | `acces-comptes` / Normale (3 fois) |
| 27 | Nouveau téléphone : plus de codes de double authentification | `acces-comptes` | `materiel` (2 fois), `messagerie` |
| 45 | Création d'une adresse partagée contact@ | `messagerie` | `autre` (2 fois), `materiel` |
| 53 | Commande d'un téléphone portable professionnel | `autre` / Basse | `materiel` / Normale (3 fois) |
| 55 | Déménagement de quatre postes vendredi | `autre` | `materiel` (2 fois), `reseau` |
| 26 | Demande de droits administrateur | Basse | Normale (3 fois) |
| 35 | Le correcteur orthographique de Word a disparu | Basse | Normale (3 fois) |

Une étiquette contredite n'est pas forcément fausse : le ticket 32 (« URGENT !!! Installation de
Photoshop ») est volontairement en priorité basse, et les trois méthodes se laissent prendre par
le mot « urgent ». C'est à l'étudiant de trancher. Après une correction, relancer l'évaluation :
les chiffres d'un rapport ne valent que pour les étiquettes du jour où il a été produit.

## Rapports

| Fichier | Contenu |
|---|---|
| [`2026-11-23-reference-mots-cles.md`](2026-11-23-reference-mots-cles.md) | Référence par mots-clés seule : 78 % ± 10 sur la catégorie |
| [`2026-11-23-modeles-locaux-ollama.md`](2026-11-23-modeles-locaux-ollama.md) | Deux modèles locaux (Ollama) comparés à la référence |

## Première évaluation réelle : deux petits modèles locaux

Conditions : Ollama 0.32.15, sans carte graphique (4 cœurs), les 60 tickets, le même prompt et
le même code que pour un vrai ticket.

| Méthode | Catégorie | Priorité | Catégorie et priorité | Temps moyen |
|---|---|---|---|---|
| Référence par mots-clés | 78 % ± 10 | 68 % ± 12 | 52 % ± 13 | < 1 ms |
| `llama3.2:3b` | 60 % ± 12 | 55 % ± 13 | 28 % ± 11 | 5,1 s |
| `qwen2.5:1.5b` | 62 % ± 12 | 42 % ± 12 | 27 % ± 11 | 2,3 s |

Ce que ces chiffres montrent :

- **Un petit modèle local fait moins bien que des mots-clés.** L'écart sur la catégorie (17 à
  18 points) dépasse la marge d'erreur de la différence (± 16 points). Sans cette mesure, on
  aurait pu croire que « mettre de l'IA » améliorait forcément le tri.
- **Le score de confiance de ces modèles ne vaut rien.** `llama3.2:3b` se dit sûr de lui pour
  59 tickets sur 60, alors qu'il n'a entièrement raison que pour 29 % d'entre eux. Le badge
  « Confiance faible » n'aurait aucun sens avec ce modèle.
- **Le ton trompe les petits modèles.** Le ticket « URGENT !!! Installation de Photoshop » est
  classé en priorité critique par `qwen2.5:1.5b`, comme le fait la référence par mots-clés.
- **Les catégories floues sont les plus difficiles** : `autre` et `reseau` tombent à 20 % avec
  `llama3.2:3b`, qui range les pannes de réseau dans « accès et comptes ».
- **Rien n'a coûté un centime ni quitté la machine**, au prix de 2 à 5 secondes par ticket.

## Limite assumée : pas d'évaluation avec Claude

Ces chiffres ne disent pas ce que vaut un grand modèle comme Claude sur ce jeu de test. Cette
évaluation **n'a pas été faite** : le projet ne dispose pas de clé d'API Anthropic (l'API est
payante). Trois conséquences, à dire telles quelles à l'oral :

- La seule évaluation réelle du projet est celle des deux modèles locaux ci-dessus.
- `AnthropicProvider` n'a jamais reçu de réponse acceptée par l'API. Il est couvert par des
  tests avec `Http::fake()`, le format de ses requêtes a été contrôlé contre la documentation
  officielle, et une vraie requête avec une clé factice a bien été refusée et traduite en
  « clé refusée ». Il reste donc un risque qu'un détail du format soit à corriger au premier
  vrai appel.
- La question « l'IA du cloud justifie-t-elle son coût et l'envoi de données à un tiers ? »
  reste ouverte. La conclusion mesurée est plus modeste : un petit modèle local ne suffit pas.

Le jour où une clé est disponible, deux commandes suffisent, sans toucher au code :

```bash
php artisan triage:ai-check
php artisan triage:evaluate --model=claude-opus-5-5 --model=claude-haiku-5-5
```
