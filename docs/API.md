# API REST de l'application mobile

Cette API sert l'application Android du technicien. Elle réutilise les mêmes règles que le
site web : mêmes Policies, même machine à états, mêmes notifications.

- **Adresse de base** : `https://votre-serveur/api/v1`
- **Format** : JSON en entrée et en sortie. Envoyez l'en-tête `Accept: application/json`.
- **Dates** : ISO 8601, par exemple `2026-11-23T12:19:50+01:00`.
- **Réservée aux techniciens** : un compte utilisateur ou administrateur reçoit une erreur 403.

## Authentification

L'API utilise des jetons Laravel Sanctum. L'application échange une fois l'adresse e-mail et le
mot de passe contre un jeton, puis l'envoie avec chaque requête :

```
Authorization: Bearer 12|k3Jd...
```

Le mot de passe n'est jamais stocké sur le téléphone : seul le jeton l'est. Un jeton expire au
bout de 30 jours (`SANCTUM_TOKEN_EXPIRATION`), est révoqué à la déconnexion, et tous les jetons
d'un compte sont révoqués quand l'administrateur le désactive.

## Résumé des routes

| Méthode | Route | Rôle |
|---|---|---|
| POST | `/login` | Obtenir un jeton |
| POST | `/logout` | Révoquer le jeton utilisé |
| GET | `/me` | Profil du technicien connecté |
| GET | `/tickets` | Tickets assignés au technicien |
| GET | `/tickets/{id}` | Détail d'un ticket et fil de discussion |
| PATCH | `/tickets/{id}/status` | Changer le statut |
| POST | `/tickets/{id}/messages` | Répondre ou écrire une note interne |

Toutes les routes sauf `/login` exigent un jeton.

## POST /login

Limitée à 5 tentatives par minute et par adresse IP.

| Champ | Obligatoire | Description |
|---|---|---|
| `email` | oui | Adresse e-mail du compte |
| `password` | oui | Mot de passe |
| `device_name` | oui | Nom de l'appareil, affiché dans la liste des jetons |
| `code` | selon le compte | Code à 6 chiffres, si la double authentification est activée |

```bash
curl -X POST https://votre-serveur/api/v1/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"tech1@triage.test","password":"password","device_name":"Pixel 8"}'
```

Réponse `200` :

```json
{
    "token": "12|k3JdQ0...",
    "expires_at": "2026-12-23T12:19:48+01:00",
    "user": { "id": 2, "name": "Thomas Bernard", "email": "tech1@triage.test", "role": "technician" }
}
```

| Code | Cas |
|---|---|
| 422 | Identifiants refusés. Le message est le même pour une adresse inconnue, un mauvais mot de passe et un compte désactivé, pour ne pas révéler quels comptes existent. |
| 422 | Code de double authentification manquant ou invalide (champ `code`). |
| 403 | Le compte n'est pas un technicien. |
| 429 | Trop de tentatives. |

## POST /logout

Révoque le jeton utilisé pour cette requête. Les autres appareils du technicien restent connectés.

Réponse `204`, sans contenu.

## GET /me

Réponse `200` :

```json
{ "user": { "id": 2, "name": "Thomas Bernard", "email": "tech1@triage.test", "role": "technician" } }
```

## GET /tickets

Les tickets assignés au technicien connecté, du plus urgent au moins urgent, par pages de 20.
Les tickets clos sont exclus, sauf si on les demande.

| Paramètre | Description |
|---|---|
| `status` | Ne garder qu'un statut : `assigned`, `in_progress`, `waiting_user`, `resolved`, `closed`. Une valeur inconnue est ignorée. |
| `page` | Numéro de page (1 par défaut) |

Réponse `200` :

```json
{
    "data": [
        {
            "id": 7,
            "title": "Impossible de se connecter au VPN depuis mon domicile",
            "status": { "value": "in_progress", "label": "En cours" },
            "priority": { "value": "high", "label": "Haute" },
            "category": { "id": 2, "name": "Réseau" },
            "requester": { "id": 5, "name": "Karim Benali" },
            "assignee": { "id": 2, "name": "Thomas Bernard" },
            "is_late": true,
            "resolve_deadline": "2026-11-21T14:08:00+01:00",
            "created_at": "2026-11-21T06:08:00+01:00",
            "updated_at": "2026-11-21T08:29:00+01:00"
        }
    ],
    "links": { "first": "...", "last": "...", "prev": null, "next": null },
    "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 4 }
}
```

La liste ne contient ni description ni messages : ils ne sont chargés que dans le détail.

## GET /tickets/{id}

Le détail ajoute la description, le fil de discussion (notes internes comprises), et **ce que le
technicien a le droit de faire** : l'application affiche un bouton par statut de
`allowed_statuses`, sans connaître elle-même la machine à états.

Réponse `200` :

```json
{
    "data": {
        "id": 10,
        "title": "Mot de passe Windows oublié après mes congés",
        "status": { "value": "assigned", "label": "Assigné" },
        "priority": { "value": "high", "label": "Haute" },
        "category": { "id": 3, "name": "Accès et comptes" },
        "requester": { "id": 7, "name": "Marc Dubois" },
        "assignee": { "id": 2, "name": "Thomas Bernard" },
        "is_late": true,
        "resolve_deadline": "2026-11-22T14:08:00+01:00",
        "created_at": "2026-11-22T06:08:00+01:00",
        "updated_at": "2026-11-22T07:42:00+01:00",
        "description": "De retour de congés, je ne me souviens plus de mon mot de passe...",
        "messages": [
            {
                "id": 15,
                "body": "Je déverrouille votre compte.",
                "is_internal": false,
                "author": { "id": 2, "name": "Thomas Bernard" },
                "created_at": "2026-11-23T12:19:50+01:00"
            }
        ],
        "allowed_statuses": [ { "value": "in_progress", "label": "En cours" } ],
        "can_reply": true
    }
}
```

Un technicien peut lire le ticket d'un collègue, mais `allowed_statuses` est alors vide.

| Code | Cas |
|---|---|
| 404 | Le ticket n'existe pas. |

## PATCH /tickets/{id}/status

| Champ | Obligatoire | Description |
|---|---|---|
| `status` | oui | Le statut visé, pris dans `allowed_statuses` |

```bash
curl -X PATCH https://votre-serveur/api/v1/tickets/10/status \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Authorization: Bearer 12|k3JdQ0..." \
  -d '{"status":"in_progress"}'
```

Réponse `200` : le ticket mis à jour, au format de la liste.

Le changement est écrit dans l'historique du ticket et le demandeur est prévenu, exactement
comme depuis le site web.

| Code | Cas |
|---|---|
| 403 | Le ticket n'est pas assigné à ce technicien. |
| 422 | Statut inconnu (erreur de validation sur `status`). |
| 422 | Transition interdite par la machine à états. Exemple : `{"message": "Transition interdite : « Assigné » vers « Résolu »."}` |

## POST /tickets/{id}/messages

| Champ | Obligatoire | Description |
|---|---|---|
| `body` | oui | Le texte, de 2 à 5000 caractères |
| `is_internal` | non | `true` pour une note interne, invisible du demandeur (`false` par défaut) |

Réponse `201` :

```json
{
    "data": {
        "id": 15,
        "body": "Je déverrouille votre compte.",
        "is_internal": false,
        "author": { "id": 2, "name": "Thomas Bernard" },
        "created_at": "2026-11-23T12:19:50+01:00"
    }
}
```

Une réponse publique prévient le demandeur ; une note interne ne prévient personne.

| Code | Cas |
|---|---|
| 403 | Le ticket est clos. |
| 422 | Message vide ou trop long. |

## Erreurs

Toutes les erreurs sont en JSON, avec au minimum un champ `message`.

| Code | Signification |
|---|---|
| 401 | Jeton absent, révoqué ou expiré : l'application renvoie à l'écran de connexion. |
| 403 | Action interdite pour ce compte ou ce ticket. |
| 404 | Ressource introuvable. |
| 422 | Données invalides (champ `errors` détaillé par champ) ou règle métier refusée. |
| 429 | Trop de requêtes. |

Exemple d'erreur de validation :

```json
{
    "message": "Le champ message est obligatoire.",
    "errors": { "body": ["Le champ message est obligatoire."] }
}
```

## Données exposées

L'API ne renvoie que ce que l'application affiche. Pour les autres personnes (demandeur, auteur
d'un message), seuls l'identifiant et le nom sont transmis : jamais l'adresse e-mail. L'adresse
du technicien connecté n'apparaît que dans son propre profil.
