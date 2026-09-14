# SchoolTry AWS architecture

## Implemented assessment deployment

```text
GitHub repository
        |
        v
GitHub Actions: CI, then manually approved deploy
        |
        v
Existing Ubuntu EC2
  +-- Nginx
  +-- PHP-FPM / Laravel 12
  +-- built Vue 3 assets
  +-- atomic releases and shared runtime storage
        |
        +----------> private RDS MySQL
        |
        +----------> private S3 assignment bucket
                      through the EC2 IAM role
```

CI is isolated and uses SQLite/local storage. It has no path to production data or AWS credentials. Deployment packages the exact commit associated with a successful `main` CI run and reaches the existing instance over pinned-host-key SSH only after a manual workflow trigger and the recommended GitHub `production` environment approval.

The single-instance assessment topology provides application-level zero-downtime releases through an atomic symlink. It does not make the EC2 instance, Availability Zone, or deployment-time database migration highly available.

## Recommended production topology

```text
Internet
   |
Route 53 / DNS
   |
Application Load Balancer + ACM HTTPS certificate
   |
private HTTP allowed only by security-group reference
   |
EC2 application instances in private subnets across AZs
   |                              |
   |                              +--> CloudWatch logs, metrics, alarms
   |
   +--> private Multi-AZ RDS MySQL
   |
   +--> private S3 bucket through instance IAM roles
   |
   +--> Parameter Store SecureString / KMS
```

The ALB is the public entry point. ACM terminates HTTPS and redirects HTTP to HTTPS at the load balancer. EC2 instances run Nginx and PHP-FPM without public application ports. The included Nginx template listens on HTTP because transport from the ALB is private; no certificate is fabricated on an instance.

## Network and security boundaries

Use a VPC spanning at least two Availability Zones:

- public subnets contain the internet-facing ALB and its routing
- private application subnets contain EC2 instances; outbound package access should use controlled NAT or VPC endpoints as appropriate
- isolated database subnets contain RDS with `PubliclyAccessible=false`

Security groups should express service-to-service identity:

- ALB: inbound 443 from the internet, optional 80 only for redirect; outbound HTTP to the application security group
- EC2 application: inbound HTTP only from the ALB security group; administration through SSM Session Manager or a tightly restricted SSH path
- RDS: inbound MySQL/3306 only from the EC2 application security group

Network ACLs are a secondary boundary, not a replacement for security groups. Enable VPC Flow Logs where the operational budget permits.

## Identity and secrets

The EC2 instance profile supplies temporary AWS credentials. Its policy should permit only the required `s3:PutObject`, `s3:GetObject`, and any explicitly needed object actions on the SchoolTry assignment prefix, plus narrowly scoped bucket metadata access. Keep S3 Block Public Access on, default object ownership enforced, encryption enabled, and avoid public object URLs.

Use Parameter Store SecureString with a customer-managed or appropriately controlled KMS key for the assessment's `APP_KEY`, RDS password, and other secrets. Scope the instance role to named `/schooltry/production/*` parameters and restrict decryption to the required key/context. Secrets Manager is the stronger choice when automated rotation is needed. GitHub stores only deployment transport secrets; it does not store application or database secrets.

For a future hardened deployment path, configure GitHub's OIDC provider in IAM, exchange the GitHub identity for a short-lived role, and invoke SSM Run Command. That removes the long-lived SSH private key but requires deliberate IAM trust policies and SSM-managed-instance setup, so it is not automatically introduced into the existing assessment architecture.

## Data services

RDS stays private, encrypted at rest, backed up with point-in-time recovery, and protected by deletion protection in production. Prefer Multi-AZ and monitor storage, CPU, connections, replica lag where applicable, and failover events. The Laravel database user should not have administrative privileges.

The S3 bucket `schooltry-assignment-uploads-2026-bjf` is in `eu-west-1` and remains private. Enable versioning/lifecycle rules if retention requirements call for them. Laravel stores opaque object keys server-side and must continue serving access through authorized application paths or short-lived signed access—not public ACLs.

## Availability, deployments, and rollback

Each deployment is an immutable release under `/var/www/schooltry/releases`. Shared `.env` and runtime storage survive releases. Preparation happens off the active path; `current` changes atomically after dependencies, assets, caches, and forward migrations succeed. An OS file lock and GitHub concurrency group prevent concurrent production changes.

The ALB checks `GET /up`, which is safe and contains no tenant or secret data. In a multi-instance production design, deploy instances gradually or use blue/green target groups, require each new target to pass health checks, then drain old targets.

Rollback repoints the symlink and reloads PHP-FPM. It never automatically reverses a database migration. All schema changes must therefore follow expand-and-contract compatibility across at least the retained application rollback window. RDS point-in-time restore is a separate disaster-recovery operation, not a routine code rollback.

## Logging, detection, and recovery

Ship Nginx access/error, PHP-FPM, Laravel, deployment, ALB, and relevant system logs to CloudWatch with defined retention. Use structured request correlation identifiers while excluding bearer tokens, credentials, sensitive submission contents, and internal S3 keys. Alarm on sustained 5xx rates, latency, failed health checks, deployment failures, disk/memory pressure, RDS capacity/connections, and suspicious authentication throttling.

Enable CloudTrail for AWS API activity, RDS automated backups, and tested recovery procedures. Application audit logs remain append-only through the existing model/infrastructure controls; restrict database operators separately because application-layer append-only rules do not prevent privileged database modification.

## Manual AWS steps

No resources are created by this repository. An operator must:

1. confirm the existing VPC routes and security-group references described above
2. confirm RDS is non-public, reachable from EC2, backed up, and uses a least-privilege application account
3. confirm the bucket is private and the EC2 instance role is restricted to the required bucket/prefix
4. create encrypted Parameter Store values and authorize only the EC2 role to read/decrypt them
5. configure DNS, the ALB listener, ACM certificate validation, target group, and `/up` health check if these do not already exist
6. install and harden Nginx/PHP-FPM on EC2 and materialize the shared `.env`
7. create the protected GitHub `production` environment and deployment secrets
8. review the deployment configuration before manually running it
