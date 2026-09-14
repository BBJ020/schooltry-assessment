# SchoolTry Assignment & Grading System

## Engineering Assessment

**Role:** DevOps & Security Engineer

## Overview

SchoolTry is a multi-tenant assignment and grading application for schools. A platform superadmin provisions schools and school administrators; each school then manages its users, courses, lecturer assignments, and student enrolments. Lecturers create and manage work only inside courses assigned to them, while students see only work for which they are eligible and see grades only after an explicit result release.

The application is implemented as a Laravel 12 JSON API and a Vue 3 single-page application. Tenant isolation and authorization are enforced by the backend; frontend role-aware navigation is a user-experience aid, not a security boundary.

## Core Features

### Lecturer workflow

- Select from courses assigned by the school administrator. Lecturers cannot create courses.
- Create assignments for all enrolled students or selected enrolled students.
- Optionally attach a privately stored academic file.
- Edit, replace/remove the attachment, or delete an assignment while it has no submissions.
- Review assignment submissions, record grades and feedback, and release results.

### Student workflow

- View only assigned and currently visible work.
- Download authorized assignment attachments without receiving a raw storage key or public S3 URL.
- Submit text and/or a validated file.
- View only their own submissions.
- Retrieve score and feedback only after the assignment has been released.

### Assessment extensions

- Platform superadmin school provisioning and school-admin management.
- School-admin user, course, lecturer-assignment, and enrolment management.
- Active/inactive controls for schools and users.
- Audited administrative and academic mutations.

## Technology Stack

- Laravel 12, Laravel Sanctum 4, PHP 8.2+
- Vue 3 Composition API, Pinia, Vue Router, Axios
- Tailwind CSS 4 and Vite
- MySQL (AWS RDS in the deployment design)
- AWS EC2, private S3 storage, and an EC2 IAM role
- Nginx and PHP-FPM
- GitHub Actions CI/CD

## Security Architecture

- Sanctum bearer tokens protect the API; configured token expiry is eight hours.
- Tenant requests pass through authentication, active-account, tenant-context, and role middleware.
- The platform superadmin path is separated from school tenant context.
- Policies, tenant-aware route binding, ownership checks, and database scopes prevent IDOR and broad cross-tenant queries.
- School administrators create courses; lecturers can operate only on courses assigned to them.
- Grade fields are not merely hidden in the UI: unreleased results are unavailable from the student grade API.
- Form Requests, explicit action inputs, transactions, and guarded model attributes reduce validation and mass-assignment risk.
- Login and submission endpoints are rate limited.
- Assignment and submission files use the configured private filesystem; production is configured for S3 without public object URLs.
- Application mutations create append-only audit records. Immutability is enforced at the application/Eloquent layer.
- Production templates use `APP_DEBUG=false`, a non-public RDS design, and an EC2 IAM role rather than static AWS credentials.
- Frontend route guards improve navigation only; Laravel remains authoritative for authorization.

See [Security Analysis](SECURITY_ANALYSIS.md) and [Code Review Report](CODE_REVIEW_REPORT.md) for the threat analysis and remediation approach.

## Project Architecture

- [Architecture](docs/ARCHITECTURE.md)
- [Architecture Diagram](docs/ARCHITECTURE_DIAGRAM.md)

## Database Design

- [Database Schema](docs/DATABASE_SCHEMA.md)
- [ER Diagram](docs/ER_DIAGRAM.md)

## Local Development

The following assumes Windows with XAMPP MySQL available and PHP/Composer/Node.js on `PATH`.

1. Install dependencies and create a local environment file:

   ```powershell
   composer install
   npm install
   Copy-Item .env.example .env
   php artisan key:generate
   ```

2. Create an empty MySQL database named `schooltry_local`, then configure `.env` with local values. Do not commit credentials.

   ```dotenv
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=schooltry_local
   DB_USERNAME=<local-mysql-user>
   DB_PASSWORD=<local-mysql-password>
   ```

3. Build the schema:

   ```powershell
   php artisan migrate
   ```

4. In separate terminals, run the backend and frontend development servers:

   ```powershell
   php artisan serve
   ```

   ```powershell
   npm run dev
   ```

The default local filesystem disk can be used for private attachments. Set `FILESYSTEM_DISK=s3` only when an S3 environment has been deliberately configured.

## Development Seeder

`LocalDevelopmentSeeder` creates a demonstration school and one account for each supported role. It refuses to run unless a development password of at least 12 characters is supplied.

Set this only in your local `.env`:

```dotenv
SCHOOLTRY_DEV_PASSWORD=<choose-a-local-password>
```

Then run:

```powershell
php artisan db:seed --class=LocalDevelopmentSeeder
```

Development email addresses:

- `superadmin@schooltry.test`
- `admin@schooltry.test`
- `lecturer@schooltry.test`
- `student@schooltry.test`

No development or production password is published in this repository.

## Running Tests and Checks

```powershell
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
npm run test:frontend
npm audit
npm run build
```

`php artisan route:list` can additionally be used to inspect the registered API and web routes.

## CI/CD

`.github/workflows/ci.yml` runs dependency audits, formatting checks, static analysis, backend tests, frontend tests, and the production asset build for pull requests and pushes to `main`.

`.github/workflows/deploy.yml` is a manually dispatched deployment gate. It verifies a successful CI run for the exact `main` commit, pins the SSH host identity, transfers a source archive, and invokes the server-side deployment script. The script builds numbered release directories, links shared `.env` and storage, migrates before an atomic `current` symlink switch, performs an application health check, retains recent releases, and restores the prior symlink if the health check fails. Database migrations are intentionally not rolled back automatically.

## AWS Deployment

The repository contains an EC2 deployment design using Nginx/PHP-FPM, a non-public RDS MySQL database, private S3 objects, and an EC2 IAM role. Runtime secrets belong in the server’s shared environment file or an external secret manager, never source control.

Application Load Balancer, ACM-managed HTTPS, Route 53, multi-AZ capacity, and CloudWatch-based monitoring are documented as **Recommended** production enhancements; their live deployment is not asserted by this repository.

- [Deployment Runbook](docs/DEPLOYMENT.md)
- [AWS Architecture](docs/AWS_ARCHITECTURE.md)

## Live Demo

**Live URL:** `<production URL>`

Production credentials must be provided through a secure, separate channel and must not be committed to this README.

## Security Documentation

- [Security Analysis](SECURITY_ANALYSIS.md)
- [Code Review Report](CODE_REVIEW_REPORT.md)

## Assessment Coverage

| Requirement | Implementation | Evidence |
|---|---|---|
| Database schema | Tenant-aware relational schema, constraints, indexes, migrations, and platform extension | [Schema](docs/DATABASE_SCHEMA.md), [ER diagram](docs/ER_DIAGRAM.md), `database/migrations/` |
| Architecture | API/SPA request lifecycle, authorization boundaries, storage, and deployment flow | [Architecture](docs/ARCHITECTURE.md), [diagram](docs/ARCHITECTURE_DIAGRAM.md) |
| Laravel backend | Sanctum API, middleware, policies, scopes, Form Requests, actions, resources, and audit records | `app/`, `routes/api.php`, `tests/Feature/` |
| Vue + Tailwind | Role-oriented dashboards, Pinia stores, Axios integration, safe rendering, and guarded navigation | `resources/js/`, `resources/css/`, `tests/frontend/` |
| CI/CD | CI quality/security gates and gated atomic EC2 deployment workflow | `.github/workflows/`, `deployment/`, [runbook](docs/DEPLOYMENT.md) |
| Security analysis | Five principal risks, breach walkthrough, controls, residual risk, and monitoring | [Security Analysis](SECURITY_ANALYSIS.md) |
| Code review | Findings against the supplied vulnerable grading example and corrected architecture | [Code Review Report](CODE_REVIEW_REPORT.md) |

The detailed evidence mapping is in the [Submission Checklist](docs/SUBMISSION_CHECKLIST.md).

## Repository Structure

```text
app/                    Laravel models, HTTP layer, policies, actions and support code
bootstrap/              Application bootstrapping and middleware aliases
config/                 Laravel, Sanctum, filesystem and service configuration
database/               Migrations, factories and seeders
deployment/             Nginx, environment templates and release automation
docs/                   Architecture, database, deployment and submission documents
resources/js/           Vue application, pages, stores, router and API services
routes/                  API, web and console routes
tests/                   Backend feature/unit tests and frontend security tests
.github/workflows/       Continuous integration and gated deployment workflows
```

## Known Limitations and Future Improvements

- **Outstanding:** replace the live-demo placeholder with the approved deployment URL.
- **Recommended:** terminate public HTTPS through an ALB with ACM and Route 53, then restrict EC2 ingress to the ALB.
- **Recommended:** add multi-AZ/high-availability capacity appropriate to production demand.
- **Recommended:** centralize Nginx, PHP-FPM, Laravel, ALB, RDS, S3, and deployment telemetry in CloudWatch with alerting.
- **Recommended:** force first-login password changes for temporary administrator-created credentials.
- **Recommended:** add broader browser-level end-to-end and infrastructure integration tests.
- **Recommended:** align the Nginx request-body limit (currently 12 MiB in the supplied configuration) with the Laravel assignment attachment limit (20 MiB), or deliberately lower the application limit.
- **Recommended:** add malware/content scanning and quarantine for uploaded documents before production-scale use.

Live AWS resources and operational alert delivery cannot be proven from repository inspection alone and must be verified during deployment acceptance.
