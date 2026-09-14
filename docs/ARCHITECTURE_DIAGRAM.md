# SchoolTry Architecture Diagrams

## Implemented application and repository-supported deployment design

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
    Actions --> RDS[(Private RDS MySQL<br/>deployment design)]
    Actions --> S3[(Private S3<br/>configured disk)]
    App -->|EC2 instance IAM role<br/>no static AWS keys in template| S3

    Dev[Developer localhost] -->|push / pull request| GitHub[GitHub repository]
    GitHub --> CI[GitHub Actions CI<br/>audits, tests, analysis, build]
    CI -->|successful main run ID| Deploy[Manual protected deploy workflow]
    Deploy -->|verified commit archive<br/>pinned SSH host key| Releases[EC2 release directories]
    Releases --> Shared[shared .env + storage]
    Releases --> Current[current symlink]
    Current --> Nginx
```

The repository configures the application and deployment process but does not provision or inspect AWS resources. Private RDS/S3 and the EC2 IAM role are requirements of the checked-in deployment design.

## Recommended production edge and availability enhancement

```mermaid
flowchart LR
    Internet((Internet)) -.-> DNS[DNS / Route 53<br/>Recommended]
    DNS -.-> ALB[Application Load Balancer<br/>Recommended]
    ACM[ACM HTTPS certificate<br/>Recommended] -.-> ALB
    ALB -.-> EC2A[EC2 application instance]
    ALB -.-> EC2B[Additional EC2 instance / AZ<br/>Recommended]
    EC2A --> RDS[(Private Multi-AZ RDS<br/>Recommended hardening)]
    EC2B --> RDS
    EC2A --> S3[(Private S3)]
    EC2B --> S3
    EC2A -.-> CW[CloudWatch logs / metrics / alarms<br/>Recommended]
    EC2B -.-> CW
```

Dashed paths and nodes labeled **Recommended** are not created by this repository and must not be interpreted as verified live infrastructure.
