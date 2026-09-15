# SchoolTry AWS Architecture

## Implemented assessment deployment

```text
GitHub repository
        |
        v
GitHub Actions CI
        |
        v
Ubuntu EC2
  +-- Nginx
  +-- PHP-FPM / Laravel 12
  +-- built Vue 3 assets
  +-- immutable release directories
  +-- atomic `current` symlink
        |
        +----------> private RDS MySQL
        |
        +----------> private S3 assignment bucket
                      through the EC2 IAM role
```

The assessment separates application compute, relational data, and uploaded files. EC2 serves the Laravel/Vue application, RDS stores application data, and S3 stores assignment and submission files. CI uses SQLite/local storage and does not receive production database data or AWS credentials.

## Network and access boundaries

- RDS is not publicly exposed and accepts MySQL/3306 from the EC2 application security group.
- S3 remains private; files are accessed through authorized application paths rather than public object URLs.
- EC2 uses an instance IAM role for S3 access, so static AWS access keys are not stored in the application environment.
- Nginx is the public web entry point for the current assessment deployment.

## Identity and secrets

Production secrets are kept outside GitHub. The shared production `.env` on EC2 contains Laravel and database configuration and is permission-restricted. AWS credentials are supplied by the EC2 instance role.

For the assessment design, sensitive values such as `APP_KEY` and the RDS password can be stored in AWS Systems Manager Parameter Store or Secrets Manager and materialized during controlled server setup. The repository does not contain secret values.

## RDS and S3

RDS MySQL is the production database. The Laravel application applies tenant-aware queries, policies, and validation on top of database constraints.

The production S3 bucket is `schooltry-assignment-uploads-2026-bjf` in `eu-west-1`. Assignment attachments and student submissions are stored privately with server-generated object paths and authenticated access.

## Zero-downtime deployment

Each release is created under:

```text
/var/www/schooltry/releases/<release-id>
```

Shared runtime state is kept under:

```text
/var/www/schooltry/shared/
```

The deployment prepares dependencies, builds frontend assets, creates Laravel caches, and runs forward migrations before atomically switching `/var/www/schooltry/current` to the new release.

## Rollback

Rollback repoints the `current` symlink to a retained previous release and reloads PHP-FPM. Database migrations are not automatically reversed, so schema changes must remain compatible with the retained application rollback window.

## ALB / HTTPS design

The assessment asks for an EC2 + ALB deployment design. If an Application Load Balancer is added, it becomes the public entry point, terminates HTTPS with an ACM certificate, forwards private HTTP to EC2, and uses `GET /up` for health checks. EC2 application access can then be restricted to the ALB security group.

The current submitted deployment uses a single EC2 public endpoint, so ALB/ACM are described as part of the required design rather than claimed as deployed infrastructure.
