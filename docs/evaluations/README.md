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

Aucune évaluation avec une IA réelle n'a encore été enregistrée : elle demande une clé d'API.
