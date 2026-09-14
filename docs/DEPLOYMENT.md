# SchoolTry deployment guide

This guide describes deploying the existing Laravel and Vue application to a prepared Ubuntu EC2 instance. It does not prove or provision AWS resources and does not place production credentials in GitHub or the repository.

## Release model

The server uses immutable application releases with shared runtime state:

```text
/var/www/schooltry/
├── releases/
│   ├── <release-id>/
│   └── <previous-release>/
├── shared/
│   ├── .env
│   └── storage/
└── current -> releases/<active-release>
```

The deploy script prepares a hidden staging directory, installs dependencies, builds Vue assets, creates Laravel caches, runs forward migrations, and only then atomically changes `current`. A filesystem lock prevents overlapping deploys. Five releases are retained by default.

## Prerequisites

The EC2 instance needs:

- Ubuntu with Nginx, PHP 8.3 FPM and the PHP extensions required by Laravel and MySQL
- Composer 2, Node.js 22, npm, `curl`, `tar`, `flock`, and the MySQL client for diagnosis
- a non-login `schooltry` operating-system account, in the `www-data` group, used to install/build application code without root privileges
- an EC2 instance profile with least-privilege access to the private assignment bucket
- private network access to the RDS MySQL endpoint on port 3306
- a deployment user that can upload to `/tmp` and run only the required deployment commands through `sudo`
- Nginx and PHP-FPM enabled through `systemd`

Do not grant unrestricted inbound SSH. Restrict it to an approved administration path or GitHub Actions egress strategy. For a mature production setup, GitHub OIDC plus AWS Systems Manager Run Command avoids a long-lived SSH key; adopting it requires an AWS IAM/SSM change and is therefore documented as a future hardening option, not applied here.

Install the reviewed deployment and rollback scripts as root-owned commands. Repeat this manual installation whenever those scripts are intentionally changed:

```bash
sudo useradd --system --create-home --home-dir /var/lib/schooltry --shell /usr/sbin/nologin schooltry
sudo usermod -a -G www-data schooltry
sudo install -o root -g root -m 0750 deployment/deploy.sh /usr/local/sbin/schooltry-deploy
sudo install -o root -g root -m 0750 deployment/rollback.sh /usr/local/sbin/schooltry-rollback
```

Skip `useradd` if the account already exists. Confirm group membership with `id schooltry`.

The GitHub workflow uploads only the source archive; it never uploads a script for `sudo` to execute. The root-owned command validates the archive path and runs archive extraction, Composer, npm, Artisan, and migrations as the unprivileged `schooltry` account. Root is retained only for directory ownership, the atomic symlink, release cleanup, and PHP-FPM reload. Grant the SSH deployment account passwordless `sudo` only for `/usr/local/sbin/schooltry-deploy` after reviewing an exact `sudoers` rule with `visudo`. Neither account may modify the root-owned command.

## Production environment

Copy `deployment/.env.production.example` to `/var/www/schooltry/shared/.env`, replace every placeholder, then secure it:

```bash
sudo install -d -m 0755 /var/www/schooltry/shared
sudo install -o root -g www-data -m 0640 /dev/null /var/www/schooltry/shared/.env
sudoedit /var/www/schooltry/shared/.env
```

Generate `APP_KEY` once with `php artisan key:generate --show` in a trusted environment. Store it as an encrypted parameter; never regenerate it during a routine deployment because doing so invalidates encrypted application data.

The template deliberately omits `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY`. The AWS SDK uses the EC2 instance role. The configured bucket is `schooltry-assignment-uploads-2026-bjf` in `eu-west-1`, and Sanctum tokens expire after 480 minutes.

For this assessment, AWS Systems Manager Parameter Store SecureString is the cost-conscious default for `APP_KEY`, `DB_PASSWORD`, and any mail/API secrets. Give the EC2 role narrowly scoped `ssm:GetParameter` and KMS decrypt access, retrieve the values during controlled server bootstrap, and materialize the root-owned shared `.env`. Parameter Store is not automatically queried on every request. Secrets Manager is preferable when managed rotation is required, but may add cost.

Example parameter names (names only, never values):

```text
/schooltry/production/app-key
/schooltry/production/db-password
```

## RDS and S3 checks

- RDS must remain non-public. Its security group should accept MySQL/3306 only from the EC2 application security group.
- Use a dedicated, least-privilege MySQL application user and require encrypted RDS connections where the selected RDS configuration supports them.
- The S3 bucket must remain private, with Block Public Access enabled. Grant the EC2 role only the object actions and bucket prefix the submission feature needs.
- Do not put the RDS password or AWS credentials in GitHub Actions.

Before the first release, verify DNS resolution and connectivity from EC2, and verify the instance role can access only the expected S3 location.

## Nginx and PHP-FPM

Review the domain and PHP socket in `deployment/nginx-schooltry.conf`, then install it:

```bash
sudo cp deployment/nginx-schooltry.conf /etc/nginx/sites-available/schooltry
sudo ln -s /etc/nginx/sites-available/schooltry /etc/nginx/sites-enabled/schooltry
sudo nginx -t
sudo systemctl reload nginx
```

The web root is `/var/www/schooltry/current/public`. Laravel's front controller handles API requests and the Vue history-mode fallback. Hidden files and `.env` are denied, directory listing is disabled, hashed Vite assets receive immutable caching, uploads are capped at 12 MiB, and PHP responses do not expose `X-Powered-By`.

Set `expose_php = Off`, `display_errors = Off`, and production-safe logging in PHP. The template expects `/run/php/php8.3-fpm.sock` and service `php8.3-fpm`; change both the Nginx template and `PHP_FPM_SERVICE` if the installed version differs.

**Recommended production edge:** terminate TLS at an Application Load Balancer with an ACM certificate. Nginx can then listen on private HTTP from the ALB; no fake or self-signed certificate is included. Restrict the EC2 HTTP security group to the ALB security group. If no ALB is deployed, an independently reviewed HTTPS termination design is required before public exposure.

## GitHub repository settings

Create a GitHub environment named `production` and add required reviewers before enabling deployments. Restrict deployment branches to `main`.

Add exactly these environment secrets:

| Secret | Purpose |
| --- | --- |
| `EC2_HOST` | Existing EC2 hostname or address reachable by the runner |
| `EC2_USER` | Restricted Ubuntu deployment account |
| `EC2_SSH_PRIVATE_KEY` | Private half of the deployment-only SSH key |
| `EC2_KNOWN_HOSTS` | Pinned EC2 SSH host-key line, collected through a trusted channel |

An optional environment variable, `EC2_SSH_PORT`, may override port 22. It is not a secret.

The pinned `known_hosts` value prevents accepting an attacker-controlled SSH host key. Use a separate deployment key and rotate it. Do not give the key an interactive administrator shell if it can be avoided.

## First deployment

1. Review both workflow files, scripts, Nginx template, server paths, PHP version, and service names.
2. Install the reviewed root-owned deploy/rollback commands and the narrowly scoped `sudoers` rule.
3. Create `/var/www/schooltry/shared/.env` from the production example and retrieve secrets from Parameter Store through the instance role.
4. Create the `production` GitHub environment, its required-reviewer protection, and the four environment secrets.
5. Install and test Nginx. The first deployment creates the remaining release/shared storage directories.
6. Merge to `main` and wait for the `CI` workflow triggered by that push to succeed.
7. Open the successful CI run and note its numeric run ID.
8. Manually run `Deploy production`, provide that CI run ID, and approve the protected environment deployment.

The deploy workflow verifies via the GitHub API that the supplied run belongs to `.github/workflows/ci.yml`, was triggered by a push to `main`, completed, and succeeded. It checks out that exact commit, uploads a credential-free source archive, and invokes `deployment/deploy.sh` on EC2.

The workflow is intentionally manual. Creating these files cannot deploy the application before the configuration and environment protection are reviewed.

## Subsequent deployments

Use the same successful-main-CI run ID process. The server performs:

1. exclusive deployment lock acquisition
2. archive extraction to a new staging release
3. shared `.env` and `storage` linking
4. production Composer install and npm build
5. Laravel configuration, route, and view caching
6. `php artisan migrate --force`
7. storage/cache ownership repair
8. atomic `current` symlink switch and safe PHP-FPM reload
9. `GET /up` health check and old-release retention

If the post-switch health check fails, the deploy script restores the former symlink when one exists. It never reverses a migration.

## Database-compatible changes

Migrations run before the symlink switch, so schema changes must be backward-compatible with both the current and incoming release. Use an expand-and-contract strategy: add nullable/new structures first, deploy compatible code, backfill separately, and remove old structures only in a later release. A failed application rollback does not undo schema changes.

Avoid long blocking migrations during the request-serving deployment. Schedule large index/table rewrites separately using an RDS-appropriate online migration plan.

## Manual rollback

List releases and inspect the intended target first:

```bash
readlink -f /var/www/schooltry/current
ls -lt /var/www/schooltry/releases
sudo /usr/local/sbin/schooltry-rollback
```

The interactive script selects the newest release other than `current`, prints both paths, warns about database compatibility, and requires typing `ROLLBACK`. To select a specific retained release:

```bash
sudo /usr/local/sbin/schooltry-rollback <release-id>
```

For an already-approved non-interactive operation, pass `--yes`. The script atomically repoints `current`, reloads PHP-FPM, checks `/up`, and restores the original symlink if that check fails. It intentionally does not run `migrate:rollback`.

## Health and monitoring

Laravel's built-in `GET /up` endpoint is configured in `bootstrap/app.php`. It returns a minimal status and is used by the release script; it is also the recommended ALB health-check path if an ALB is deployed. It confirms the application can boot and intentionally does not expose configuration, credentials, exception details, or tenant data. Use separate private monitoring for RDS/S3 dependency health so an intermittent downstream failure does not remove every instance from service at once.

**Recommended:** configure ALB health checks for HTTP `/up`, success code 200, with thresholds appropriate to the application startup time. Send Nginx, PHP-FPM, Laravel, ALB, RDS, and deployment logs to CloudWatch with retention and alarms for 5xx rate, latency, disk, CPU, memory, database connections, and failed deployments. Never log bearer tokens, passwords, or submitted private object keys.

## Troubleshooting

- **CI PHP tests fail:** confirm the failure uses SQLite only and does not reference production services.
- **Route caching fails:** run `php artisan route:list` and `php artisan route:cache` locally; route actions must be cacheable.
- **RDS connection fails:** check private DNS, route tables, the RDS security-group source, credentials, and MySQL TLS settings from EC2.
- **S3 upload fails:** inspect the EC2 instance profile, bucket policy, object prefix, region, and Block Public Access settings. Do not add static AWS keys.
- **502 from Nginx:** verify the PHP-FPM service/socket version and permissions.
- **403/500 after deploy:** inspect Laravel and Nginx logs, shared storage ownership, and cached environment values. Run cache commands only inside the active or staged release.
- **Health check fails on EC2:** ensure SchoolTry is the Nginx default virtual host for the private listener, or edit the reviewed root-owned scripts to use an appropriate local URL before reinstalling them.
- **Deploy remains locked:** confirm no deploy or rollback process is active before removing `/var/lock/schooltry-deploy.lock`; the lock file itself is harmless when no process holds it.

## Security notes

- CI receives no production `.env`, AWS credential, RDS password, or S3 access.
- The deployment artifact excludes `.env`, `.git`, dependencies, and built assets; dependencies and assets are reproducibly prepared on EC2 from lock files.
- Server-side tenant middleware, policies, IDOR controls, and append-only audit behavior remain authoritative and unchanged by deployment.
- `APP_DEBUG=false` and PHP `display_errors=Off` prevent browser stack-trace exposure; logs remain private.
- Review dependency-audit failures rather than bypassing them. Update affected locks or document a narrowly assessed upstream exception in a separate security review.
