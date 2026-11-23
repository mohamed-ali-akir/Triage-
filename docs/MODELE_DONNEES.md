# Modèle de données et diagrammes

GitHub affiche directement les schémas Mermaid ci-dessous.

## 1. MCD (phase 1)

```mermaid
erDiagram
    DEPARTMENT ||--o{ USER : "regroupe"
    USER ||--o{ TICKET : "demande"
    USER |o--o{ TICKET : "traite"
    CATEGORY |o--o{ TICKET : "classe"
    TICKET ||--o{ TICKET_MESSAGE : "contient"
    USER ||--o{ TICKET_MESSAGE : "écrit"
    TICKET ||--o{ TICKET_EVENT : "historise"
    USER |o--o{ TICKET_EVENT : "déclenche"

    DEPARTMENT {
        int id PK
        string name
    }
    USER {
        int id PK
        string name
        string email
        string password
        string role
        boolean is_active
    }
    CATEGORY {
        int id PK
        string name
        string slug
        boolean is_active
    }
    TICKET {
        int id PK
        string title
        text description
        string status
        string priority
        datetime taken_at
        datetime resolved_at
        datetime closed_at
    }
    TICKET_MESSAGE {
        int id PK
        text body
        boolean is_internal
    }
    TICKET_EVENT {
        int id PK
        string type
        string old_value
        string new_value
        datetime created_at
    }
```

Lecture des cardinalités :

- Un service regroupe 0 à n utilisateurs ; un utilisateur appartient à 0 ou 1 service.
- Un utilisateur demande 0 à n tickets ; un ticket a exactement 1 demandeur.
- Un technicien traite 0 à n tickets ; un ticket a 0 ou 1 technicien assigné.
- Une catégorie classe 0 à n tickets ; un ticket a 0 ou 1 catégorie (0 tant qu'il n'est pas trié).
- Un ticket contient 0 à n messages et 0 à n événements.
- Un événement a 0 ou 1 auteur : 0 quand c'est le système qui agit.

## 2. MLD (phase 1)

Les clés primaires sont soulignées par la mention PK, les clés étrangères sont précédées de `#`.

- **departments** (id PK, name, created_at, updated_at)
- **users** (id PK, name, email, password, role, is_active, #department_id, colonnes de double
  authentification, remember_token, created_at, updated_at)
- **categories** (id PK, name, slug, is_active, created_at, updated_at)
- **tickets** (id PK, title, description, status, priority, #category_id, #requester_id,
  #assignee_id, taken_at, resolved_at, closed_at, created_at, updated_at)
- **ticket_messages** (id PK, #ticket_id, #user_id, body, is_internal, created_at, updated_at)
- **ticket_events** (id PK, #ticket_id, #user_id, type, old_value, new_value, created_at)

Contraintes :

| Colonne | Contrainte |
|---|---|
| `users.email` | unique |
| `users.department_id` | nullable, `ON DELETE SET NULL` |
| `categories.slug` | unique |
| `tickets.requester_id` | obligatoire, `ON DELETE RESTRICT` |
| `tickets.assignee_id` | nullable, `ON DELETE SET NULL` |
| `tickets.category_id` | nullable, `ON DELETE RESTRICT` |
| `tickets.priority` | nullable (vide tant que le ticket n'est pas trié) |
| `ticket_messages.ticket_id`, `ticket_events.ticket_id` | `ON DELETE CASCADE` |
| `ticket_events.user_id` | nullable (action du système) |

Index : `tickets (status)`, plus les index créés par chaque clé étrangère.

Tables techniques fournies par Laravel : `sessions`, `password_reset_tokens`, `cache`, `jobs`
(file d'attente) et `notifications`.

## 3. Machine à états du ticket

```mermaid
stateDiagram-v2
    [*] --> nouveau
    nouveau --> en_attente_validation : tri prêt (système)
    en_attente_validation --> assigne : un technicien s'assigne le ticket
    assigne --> en_cours : le technicien commence
    en_cours --> en_attente_utilisateur : question au demandeur
    en_attente_utilisateur --> en_cours : réponse du demandeur
    en_cours --> resolu : problème réglé
    resolu --> en_cours : réouverture par le demandeur
    resolu --> clos : clôture
    clos --> [*]
```

## 4. Cas d'utilisation

```mermaid
flowchart LR
    U([Utilisateur])
    T([Technicien])
    A([Administrateur])

    U --> U1(Créer un ticket)
    U --> U2(Suivre ses tickets)
    U --> U3(Répondre dans le fil)
    U --> U4(Clore ou rouvrir un ticket résolu)

    T --> T1(Consulter la file)
    T --> T2(Trier : catégorie et priorité)
    T --> T3(S'assigner un ticket)
    T --> T4(Répondre et écrire des notes internes)
    T --> T5(Changer le statut)

    A --> A1(Gérer les comptes)
    A --> A2(Gérer les catégories)
    A --> A3(Consulter tous les tickets)
```

## 5. Tables de la phase 2 (tri par IA)

```mermaid
erDiagram
    TICKET ||--o{ AI_SUGGESTION : "reçoit"
    CATEGORY |o--o{ AI_SUGGESTION : "est proposée par"
    USER |o--o{ AI_SUGGESTION : "valide"
    TICKET |o--o{ AI_LOG : "concerne"

    AI_SUGGESTION {
        int id PK
        string priority
        decimal confidence
        string status
        datetime reviewed_at
    }
    AI_LOG {
        int id PK
        string purpose
        string provider
        string model
        int input_tokens
        int output_tokens
        int duration_ms
        decimal estimated_cost_usd
        boolean success
        string error_code
        datetime created_at
    }
```

- **ai_suggestions** (id PK, #ticket_id, #category_id, priority, confidence, status,
  #reviewed_by, reviewed_at, created_at, updated_at)
- **ai_logs** (id PK, #ticket_id, purpose, provider, model, input_tokens, output_tokens,
  duration_ms, estimated_cost_usd, success, error_code, created_at)
- **tickets** reçoit la colonne `triage_mode` (`ai` ou `manual`).

Une suggestion garde ce que l'IA a proposé, même si le technicien choisit autre chose : c'est ce
qui permettra de mesurer le taux de suggestions acceptées, modifiées et rejetées.

`ai_logs` n'a aucune colonne de type texte : il est impossible d'y écrire un prompt ou une réponse.

## 6. Flux du tri par IA

```mermaid
sequenceDiagram
    actor U as Utilisateur
    participant App as Application (requête HTTP)
    participant Q as File d'attente
    participant J as Job ClassifyTicket
    participant M as PiiMasker
    participant IA as Fournisseur d'IA
    actor T as Technicien

    U->>App: Crée un ticket
    App->>Q: Met le Job en file
    App-->>U: « Ticket enregistré » (sans attendre l'IA)

    Q->>J: Un worker exécute le Job
    J->>M: Masque les données personnelles
    M-->>J: Texte avec jetons [NOM_1], [EMAIL_1]...
    J->>IA: Prompt système + ticket entre balises + schéma JSON

    alt Réponse valide
        IA-->>J: catégorie, priorité, confiance
        J->>J: Valide contre les catégories existantes
        J->>App: Crée une suggestion « en attente »
    else Réponse inutilisable
        IA-->>J: JSON invalide
        J->>IA: Une nouvelle tentative
        Note over J: Deuxième échec : tri manuel
    else Panne, délai dépassé, quota, refus
        IA--xJ: Erreur
        Note over J: Mode dégradé : tri manuel
    end

    J->>App: Ticket « en attente de validation » + ligne dans ai_logs

    T->>App: Ouvre le ticket
    App-->>T: Suggestion et score de confiance
    T->>App: Accepte, modifie ou rejette
    Note over App: Le ticket n'est modifié qu'ici, par le technicien
```

## 7. Tables prévues pour les phases suivantes

| Table | Phase | Rôle |
|---|---|---|
| `kb_articles` | 3 | Articles de la base de connaissances |
| `ai_suggestion_kb_article` | 3 | Articles cités par une suggestion (table de liaison) |

Leur MCD sera ajouté ici au début de chaque phase.
