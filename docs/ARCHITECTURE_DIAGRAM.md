# SchoolTry Architecture Diagrams

## Application and deployment architecture

```mermaid
flowchart TB
    Browser[Browser: Vue 3 SPA] -->|HTTP API + Sanctum bearer token| Nginx[Nginx on EC2]
    Nginx --> App[Laravel 12 API + Vue assets<br/>PHP-FPM]

    subgraph Laravel[Laravel security and domain pipeline]
        Sanctum[Sanctum authentication]
        Context[Tenant / platform context]
        Role[Role middleware]
        Policies[Policies + visibleTo scopes]
        Requests[Form Requests]
        Actions[Transactional actions]
        Audit[Append-only audit log]
        Sanctum --> Context --> Role --> Policies --> Requests --> Actions --> Audit
    end

    App --> Sanctum
    Actions --> RDS[(Private RDS MySQL)]
    Actions --> S3[(Private S3)]
    App -->|EC2 instance IAM role<br/>no static AWS keys in template| S3

    Dev[Developer localhost] -->|push / pull request| GitHub[GitHub repository]
    GitHub --> CI[GitHub Actions CI<br/>audits, tests, analysis, build]
    CI -->|successful main run ID| Deploy[Manual deploy workflow]
    Deploy -->|verified commit archive<br/>pinned SSH host key| Releases[EC2 release directories]
    Releases --> Shared[shared .env + storage]
    Releases --> Current[current symlink]
    Current --> Nginx
```

The repository contains the application and deployment configuration. The assessment deployment uses EC2, RDS MySQL, private S3 storage, and an EC2 IAM role.
