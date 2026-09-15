# SchoolTry Architecture

## System overview

SchoolTry is a Laravel 12 JSON API with a Vue 3 single-page interface built by Vite. Pinia owns client session and assignment state, Vue Router provides role-aware navigation UX, and Axios sends Sanctum bearer tokens. Laravel remains authoritative for authentication, tenant resolution, authorization, validation, persistence, file access, and response filtering.

Production configuration targets Nginx/PHP-FPM on EC2, MySQL on private RDS, and private S3 storage through the EC2 instance role. The repository also includes GitHub Actions CI and a manually triggered immutable-release deployment workflow.

## Backend boundaries

### Authentication

- `POST /api/login` validates email/password/device name, returns a generic 401 for invalid credentials, rejects inactive users and tenant users from inactive schools, and issues a Sanctum bearer token.
- Active school-less users are accepted only when they have the `superadmin` role.
- `GET /api/me` and `POST /api/logout` use `auth:sanctum` plus account-context middleware.
- Sanctum expiration defaults to 480 minutes through `SANCTUM_EXPIRATION`.
- API resources hide password hashes, remember tokens, and unrelated internal fields.

### Tenant resolution

`EnsureTenant` requires an authenticated, active user with a school and verifies that the school is active. It sets the request-scoped `TenantContext` and clears it in a `finally` block. `BelongsToSchool` applies the context as a global `school_id` query scope and enforces tenant ownership during Eloquent saves.

`EnsurePlatformContext` is separate: it requires an active, school-less superadmin and explicitly clears tenant context. `EnsureAccountContext` selects tenant or platform behavior for `/logout` and `/me`.

### Authorization

- Route role middleware provides a coarse role boundary.
- Policies provide resource/action authorization for courses, assignments, submissions, grades, users, and schools.
- `visibleTo($user)` scopes constrain collection and ID lookups in SQL rather than filtering broad results in PHP.
- Controllers generally accept integer IDs and resolve them through a visible query. Tenant-scoped route-model binding is used where model binding is present.
- Actions repeat authorization for mutations and derive ownership fields from authenticated/resolved objects.
- Inaccessible cross-tenant or non-owned resources normally return 404; a known owned resource that fails an action policy returns 403.

Frontend role checks never replace these controls.

## Request lifecycles

### School admin creates and assigns a course

```text
Vue admin screen
  -> Axios bearer token
  -> auth:sanctum
  -> EnsureTenant
  -> role:admin
  -> StoreAdminCourseRequest
  -> CoursePolicy::create
  -> ManageCourse / CreateCourse
  -> server-derived school_id and selected same-school lecturer
  -> database transaction + audit_logs
```

Lecturers cannot create courses. Their dashboard lists only courses assigned to them through `Course::visibleTo()`.

### Lecturer creates an assignment

```text
Lecturer selects an assigned course
  -> POST /api/lecturer/courses/{course}/assignments
  -> auth:sanctum -> tenant -> role:lecturer
  -> Course::visibleTo(lecturer)->findOrFail
  -> StoreAssignmentRequest
  -> CreateAssignment
  -> AssignmentPolicy + CoursePolicy
  -> validate selected targets against course enrollment
  -> transaction: assignment + targets + audit
  -> optional private file on configured disk
```

The browser cannot choose `school_id`, `course_id`, creator/releaser IDs, file path, MIME type, or size. Assignment attachments use server-generated paths. Lecturers may edit/delete an assignment only before any submission exists; policies and row-locked actions enforce the restriction and audit updates, deletes, replacements, and removals.

### Student retrieves an assignment

```text
GET /api/student/assignments[/{id}]
  -> auth:sanctum -> tenant -> role:student
  -> Assignment::visibleTo(student)
       -> target_type=all: current course enrollment required
       -> target_type=selected: assignment_targets membership required
  -> AssignmentPolicy::view
  -> safe AssignmentResource
```

An authorized attachment is downloaded through an authenticated streaming endpoint. The API exposes sanitized display metadata, not an S3 key or public URL.

### Student submits

```text
POST /api/student/assignments/{assignment}/submissions
  -> auth:sanctum -> tenant -> role:student
  -> throttle:submissions (5/minute per authenticated user)
  -> visible assignment lookup
  -> StoreSubmissionRequest
  -> SubmitAssignment
  -> AssignmentSubmissionPolicy::submit
  -> optional private upload on configured disk
  -> transaction: create/update submission + audit
```

Text, file, or both are accepted. The action derives school, assignment, student, timestamps, storage path, MIME, size, and sanitized filename. A unique database constraint gives one current submission per student/assignment.

### Lecturer reviews and grades

```text
GET assignment submissions
  -> visible owned assignment
  -> AssignmentPolicy::viewSubmissions
  -> AssignmentSubmission::visibleTo(lecturer)
  -> student + safe file metadata + grade status

POST /api/lecturer/submissions/{submission}/grade
  -> visible owned submission
  -> StoreGradeRequest
  -> GradeSubmission
  -> GradePolicy::grade
  -> maximum-score validation
  -> transaction: grade + audit
```

The student, assignment, school, and grader identifiers come from the resolved submission and authenticated lecturer, not request input.

### Lecturer releases and student retrieves a grade

```text
POST /api/lecturer/assignments/{assignment}/release
  -> tenant-visible owned assignment
  -> AssignmentPolicy::release
  -> ReleaseAssignment transaction
  -> is_released + released_at + released_by + audit

GET /api/student/assignments/{assignment}/grade
  -> student-visible assignment
  -> query by authenticated student
  -> Grade::visibleTo requires released assignment
  -> GradePolicy repeats school, student, and release checks
  -> GradeResource, or generic 404 while unavailable
```

Unreleased scores and feedback are omitted at the query/API boundary; they are not sent as null/hidden frontend fields.

## API and Vue integration

- `resources/js/services/api.js` sets the API base, `Accept`, XMLHttpRequest, and bearer Authorization headers.
- The assessment stores the token in `sessionStorage`, which limits persistence to the browser tab/session but remains readable by JavaScript. User content is rendered through escaped Vue interpolation rather than `v-html`.
- A generic Axios response interceptor clears the local token and invokes navigation handling on 401.
- `apiError` maps 401, 403, 404, 422, and 429 to safe user messages and exposes only Laravel validation errors for 422.
- The Pinia auth store performs login/logout/me hydration. The assignments store loads paginated API collections, sends multipart requests when files exist, and never invents tenant visibility.
- Vue Router redirects guests and separates superadmin/admin/lecturer/student screens for usability. Every API call is still protected server-side.
- User-generated text uses escaped interpolation and `whitespace-pre-wrap`; filenames pass through `safeFilename` before display.

## Failure behavior

| Status | Meaning | Application behavior |
| --- | --- | --- |
| 401 | Unauthenticated/expired token | Generic JSON; frontend clears session and redirects to login |
| 403 | Authenticated but action forbidden | Generic authorization JSON; no stack trace |
| 404 | Missing or inaccessible object | Generic resource response, used to reduce tenant/object enumeration |
| 422 | Invalid input | Safe field-level Form Request errors |
| 429 | Rate limit exceeded | Generic retry-later response |

`bootstrap/app.php` forces JSON rendering for API exceptions. Production configuration sets `APP_DEBUG=false`.

## Persistence and files

- MySQL/RDS is the production database; CI uses in-memory SQLite.
- Tenant-owned tables carry `school_id`; indexes support school-first access paths.
- Production selects S3 with `FILESYSTEM_DISK=s3`; local development defaults to the private local disk.
- S3 visibility is private and production configuration omits static key/secret values so the EC2 IAM role can provide credentials.
- Assignment attachments and student submission files are represented by server-owned metadata. Normal resources omit raw paths.
- File/database changes are coordinated by action classes with cleanup on failed transactions. Storage and SQL cannot form one atomic transaction, so post-commit cleanup failures are reported and may require operational orphan cleanup.

## Audit model

Mutation actions call `RecordAuditLog` with actor, action, target, old/new values, school context, and request metadata. `AuditLog` model hooks plus `AppendOnlyBuilder` reject Eloquent changes/deletes. This protects application paths, not privileged direct SQL.

## Deployment architecture

- CI runs on pushes to `main` and pull requests, with read-only repository permission.
- PHP and frontend jobs install locked dependencies, audit them, run format/static/test checks, and build assets.
- Production deployment is a manual workflow requiring the ID of a successful `main` push CI run.
- The exact verified commit is archived without `.env`, dependencies, or built assets and transferred over pinned-host-key SSH.
- A root-owned server script prepares an unprivileged staging release, links shared `.env`/storage, installs/builds, caches Laravel configuration/routes/views, runs forward migrations, atomically switches `current`, reloads PHP-FPM, health-checks `/up`, and retains five releases.
- Rollback atomically repoints the symlink and does not reverse migrations.

See [DEPLOYMENT.md](DEPLOYMENT.md) and [AWS_ARCHITECTURE.md](AWS_ARCHITECTURE.md).
