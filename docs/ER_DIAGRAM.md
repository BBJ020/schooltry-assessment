# SchoolTry ER Diagram

```mermaid
erDiagram
    SCHOOLS {
        bigint id PK
        varchar slug UK
        boolean is_active
    }

    USERS {
        bigint id PK
        bigint school_id FK "nullable for superadmin"
        varchar email UK
        boolean is_active
    }

    ROLES {
        bigint id PK
        varchar name UK
    }

    PERMISSIONS {
        bigint id PK
        varchar name UK
    }

    ROLE_USER {
        bigint school_id FK "nullable for platform role"
        bigint role_id FK
        bigint user_id FK
    }

    PERMISSION_ROLE {
        bigint permission_id PK,FK
        bigint role_id PK,FK
    }

    COURSES {
        bigint id PK
        bigint school_id FK
        bigint lecturer_id FK
        varchar code
    }

    COURSE_STUDENT {
        bigint school_id FK
        bigint course_id PK,FK
        bigint student_id PK,FK
        timestamp enrolled_at
    }

    ASSIGNMENTS {
        bigint id PK
        bigint school_id FK
        bigint course_id FK
        bigint created_by FK
        enum target_type
        boolean is_released
        bigint released_by FK "nullable"
    }

    ASSIGNMENT_TARGETS {
        bigint id PK
        bigint school_id FK
        bigint assignment_id FK
        bigint student_id FK
    }

    ASSIGNMENT_SUBMISSIONS {
        bigint id PK
        bigint school_id FK
        bigint assignment_id FK
        bigint student_id FK
    }

    GRADES {
        bigint id PK
        bigint school_id FK
        bigint assignment_id FK
        bigint submission_id FK,UK
        bigint student_id FK
        bigint graded_by FK
    }

    AUDIT_LOGS {
        bigint id PK
        bigint school_id FK "nullable"
        bigint actor_id FK "nullable"
        varchar auditable_type
        bigint auditable_id
    }

    PERSONAL_ACCESS_TOKENS {
        bigint id PK
        varchar tokenable_type
        bigint tokenable_id
        varchar token UK
    }

    SCHOOLS ||--o{ USERS : contains
    SCHOOLS ||--o{ COURSES : owns
    SCHOOLS o|--o{ ROLE_USER : contextualizes
    SCHOOLS ||--o{ COURSE_STUDENT : scopes
    SCHOOLS ||--o{ ASSIGNMENTS : owns
    SCHOOLS ||--o{ ASSIGNMENT_TARGETS : scopes
    SCHOOLS ||--o{ ASSIGNMENT_SUBMISSIONS : owns
    SCHOOLS ||--o{ GRADES : owns
    SCHOOLS o|--o{ AUDIT_LOGS : scopes

    USERS ||--o{ ROLE_USER : receives
    ROLES ||--o{ ROLE_USER : assigned_as
    ROLES ||--o{ PERMISSION_ROLE : receives
    PERMISSIONS ||--o{ PERMISSION_ROLE : grants

    USERS ||--o{ COURSES : teaches
    COURSES ||--o{ COURSE_STUDENT : has_enrollment
    USERS ||--o{ COURSE_STUDENT : enrolls_as_student
    COURSES ||--o{ ASSIGNMENTS : contains
    USERS ||--o{ ASSIGNMENTS : creates
    USERS o|--o{ ASSIGNMENTS : releases

    ASSIGNMENTS ||--o{ ASSIGNMENT_TARGETS : targets
    USERS ||--o{ ASSIGNMENT_TARGETS : selected_for
    ASSIGNMENTS ||--o{ ASSIGNMENT_SUBMISSIONS : receives
    USERS ||--o{ ASSIGNMENT_SUBMISSIONS : submits
    ASSIGNMENT_SUBMISSIONS ||--o| GRADES : receives
    ASSIGNMENTS ||--o{ GRADES : groups
    USERS ||--o{ GRADES : receives
    USERS ||--o{ GRADES : grades
    USERS o|--o{ AUDIT_LOGS : acts
    USERS ||--o{ PERSONAL_ACCESS_TOKENS : authenticates_with
```

## Notes

- `ROLE_USER` is the real many-to-many bridge between users and roles. Its unique key is `(role_id, user_id)` after the platform extension; `school_id` remains a foreign key and tenant index component.
- A platform superadmin is the deliberate exception to ordinary tenant membership: both `users.school_id` and the related `role_user.school_id` are null. Platform middleware requires that combination and the `superadmin` role.
- `AUDIT_LOGS.auditable_type` plus `auditable_id` is polymorphic application metadata, not a database foreign key to every auditable table; no invented target relationship is drawn.
- `PERSONAL_ACCESS_TOKENS.tokenable_type/tokenable_id` is also polymorphic. SchoolTry currently issues tokens to `User` records.
- The database has direct user foreign keys for creator, releaser, lecturer, student, grader, and audit actor roles. The relationship labels distinguish those meanings.
