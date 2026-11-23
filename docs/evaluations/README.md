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

Ce que ces chiffres ne disent pas : ce que vaut un grand modèle comme Claude sur ce jeu de test.
Cette évaluation demande une clé d'API et reste à faire ; c'est elle qui dira si l'IA du cloud
justifie son coût et l'envoi de données à un tiers.

```bash
php artisan triage:evaluate --model=claude-opus-5-5 --model=claude-haiku-5-5
```
