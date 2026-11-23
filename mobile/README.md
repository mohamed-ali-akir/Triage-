# Triage Mobile — application Android du technicien

Application Android (Kotlin, Jetpack Compose, Retrofit) qui utilise l'API REST décrite dans
[`docs/API.md`](../docs/API.md). Elle permet à un technicien de :

- se connecter (avec le code de double authentification si son compte l'exige) ;
- voir les tickets qui lui sont assignés, du plus urgent au moins urgent ;
- ouvrir un ticket et lire le fil de discussion, notes internes comprises ;
- changer le statut du ticket ;
- répondre au demandeur ou écrire une note interne.

## Organisation

| Module | Contenu | Dépend d'Android ? |
|---|---|---|
| `core` | Le client de l'API : interface Retrofit, objets JSON, `AuthRepository`, `TicketRepository`, traduction des erreurs | Non : Kotlin pur, testé sur la JVM |
| `app` | L'application : écrans Compose, ViewModels, navigation, stockage du jeton | Oui |

Ce découpage a une raison pratique : tout ce qui parle au serveur est dans `core` et se teste en
quelques secondes, sans émulateur.

```
app/src/main/java/fr/triage/mobile/
├── MainActivity.kt, TriageApplication.kt, AppContainer.kt
├── data/SharedPreferencesTokenStore.kt
└── ui/
    ├── Navigation.kt          les trois écrans et leurs enchaînements
    ├── login/                 écran de connexion
    ├── tickets/               liste des tickets assignés
    └── ticket/                détail, statut, discussion, réponse
```

Chaque écran suit le même schéma : un `ViewModel` expose un état immuable (`StateFlow`), l'écran
Compose l'affiche et renvoie les actions de l'utilisateur au `ViewModel`.

## Ouvrir et lancer l'application

1. Installer [Android Studio](https://developer.android.com/studio).
2. Ouvrir le dossier `mobile/` (et non la racine du dépôt).
3. Laisser Android Studio synchroniser le projet Gradle.
4. Lancer le serveur Laravel : `php artisan migrate:fresh --seed && php artisan serve`.
5. Lancer l'application sur un émulateur.
6. Se connecter avec un compte technicien de démonstration : `tech1@triage.test` / `password`.

L'émulateur joint l'ordinateur à l'adresse `10.0.2.2` : c'est l'adresse par défaut de l'API
(`http://10.0.2.2:8000/api/v1/`).

### Sur un vrai téléphone

Le téléphone et l'ordinateur doivent être sur le même réseau.

```bash
php artisan serve --host=0.0.0.0            # écouter sur le réseau local
./gradlew :app:installDebug -Ptriage.apiBaseUrl=http://192.168.1.20:8000/api/v1/
```

Remplacez `192.168.1.20` par l'adresse de votre ordinateur. L'adresse doit finir par `/`.

Le HTTP non chiffré n'est accepté que dans la version de débogage. Une version publiée doit
utiliser HTTPS.

## Tests

```bash
./gradlew :core:test
```

Les tests de `core` utilisent un faux serveur (MockWebServer) : requêtes envoyées, lecture des
réponses, traduction de chaque erreur (401, 403, 404, 422, 429, panne réseau).

Un test supplémentaire fait un parcours complet contre un **vrai** serveur Triage, pour vérifier
que le Kotlin et le Laravel s'accordent. Il est ignoré tant que l'adresse n'est pas donnée :

```bash
php artisan migrate:fresh --seed && php artisan serve
TRIAGE_API_URL=http://127.0.0.1:8000/api/v1/ ./gradlew :core:test
```

### Test sur émulateur

`app/src/androidTest` contient un test qui pilote la vraie application : le technicien se
connecte, ouvre un ticket et le marque comme résolu, contre un vrai serveur Triage.

```bash
php artisan migrate:fresh --seed && php artisan serve    # dans un premier terminal
./gradlew :app:connectedDebugAndroidTest                  # émulateur démarré
```

La CI exécute ce test à chaque pull request, sur un émulateur Android 14 : elle démarre un
serveur Triage avec les données de démonstration, puis lance l'application.

## Choix techniques

- **Le serveur décide de ce qui est permis.** Le détail d'un ticket contient `allowed_statuses` ;
  l'application affiche un bouton par entrée. La machine à états n'est pas recopiée en Kotlin.
- **Seul le jeton est stocké**, dans le stockage privé de l'application (`SharedPreferences`),
  jamais le mot de passe. La sauvegarde automatique Android est désactivée pour cette application.
- **Les erreurs ne font jamais planter l'application.** Chaque appel renvoie un `ApiResult` :
  une valeur, ou une erreur avec un message à afficher. Un jeton refusé ramène à l'écran de connexion.
- **Pas de bibliothèque d'injection de dépendances.** `AppContainer` crée les trois objets
  partagés ; c'est suffisant et lisible.

## Versions

Les versions sont dans `gradle/libs.versions.toml`. Elles forment un ensemble stable (fin 2024).
Android Studio propose de les mettre à jour avec son assistant de mise à niveau.

## Limites connues

- Pas de mode hors connexion : sans réseau, l'application affiche une erreur.
- La liste n'affiche que la première page (20 tickets).
- Le jeton n'est pas chiffré par le Keystore Android. Il est protégé par le bac à sable de
  l'application ; un chiffrement supplémentaire serait une amélioration.
- Pas de notifications push.
