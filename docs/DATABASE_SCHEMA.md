# SchoolTry Database Schema

## Conventions and tenant model

Primary application data is partitioned by `school_id`. Tenant-owned Eloquent models use the `BelongsToSchool` global scope, which reads the request-scoped `TenantContext`, fills a missing school ID on writes, rejects a conflicting school ID, and verifies tenant-owned parent IDs through scoped model queries.

The exception is the platform superadmin: `users.school_id = NULL` and its `role_user.school_id = NULL`. Platform requests use `EnsurePlatformContext` and do not establish tenant context. Ordinary admins, lecturers, and students require a non-null school and use `EnsureTenant`.

Database foreign keys enforce referenced-row existence and delete behavior, but most are not composite tenant foreign keys. Cross-school consistency is therefore also enforced by application scopes, policies, action validation, and Eloquent write hooks. Direct privileged SQL remains outside those controls.

## Application tables

### `schools`

| Column | Type | Nullable | Key/index | Purpose |
| --- | --- | --- | --- | --- |
| `id` | unsigned bigint | No | Primary key | School identifier |
| `name` | varchar | No | — | Display name |
| `slug` | varchar | No | Unique | Stable school slug |
| `email` | varchar | Yes | — | School contact email |
| `phone` | varchar(30) | Yes | — | School contact phone |
| `is_active` | boolean | No | Index; default true | Tenant login/access status |
| `created_at`, `updated_at` | timestamp | Yes | — | Laravel timestamps |

### `users`

| Column | Type | Nullable | Key/index | Purpose |
| --- | --- | --- | --- | --- |
| `id` | unsigned bigint | No | Primary key | User identifier |
| `school_id` | unsigned bigint | Yes | FK `schools`; index with `email`; restrict delete | Tenant membership; null only for platform users |
| `name` | varchar | No | — | User display name |
| `email` | varchar | No | Unique; also indexed with `school_id` | Login identity; globally unique |
| `email_verified_at` | timestamp | Yes | — | Laravel verification timestamp |
| `password` | varchar | No | — | Hashed password |
| `is_active` | boolean | No | Default true | Account access status |
| `remember_token` | varchar(100) | Yes | — | Laravel remember token field |
| `created_at`, `updated_at` | timestamp | Yes | — | Laravel timestamps |

The global unique email constraint means two schools cannot use the same login email.

### `roles`

| Column | Type | Nullable | Key/index | Purpose |
| --- | --- | --- | --- | --- |
| `id` | unsigned bigint | No | Primary key | Role identifier |
| `name` | varchar | No | Unique | `superadmin`, `admin`, `lecturer`, or `student` |
| `description` | varchar | Yes | — | Human-readable role description |
| `created_at`, `updated_at` | timestamp | Yes | — | Laravel timestamps |

### `permissions`

| Column | Type | Nullable | Key/index | Purpose |
| --- | --- | --- | --- | --- |
| `id` | unsigned bigint | No | Primary key | Permission identifier |
| `name` | varchar | No | Unique | Permission name |
| `description` | varchar | Yes | — | Permission description |
| `created_at`, `updated_at` | timestamp | Yes | — | Laravel timestamps |

### `role_user`

| Column | Type | Nullable | Key/index | Purpose |
| --- | --- | --- | --- | --- |
| `school_id` | unsigned bigint | Yes | FK `schools`, cascade delete; index with `user_id` | Tenant role context; null for platform superadmin |
| `role_id` | unsigned bigint | No | FK `roles`, cascade delete; unique with `user_id` | Assigned role |
| `user_id` | unsigned bigint | No | FK `users`, cascade delete; unique with `role_id` | Assigned user |

The platform extension replaced the original three-column primary key with unique `(role_id, user_id)`, allowing a null school while preventing duplicate user-role assignments. Tenant role mutations replace the user's role row transactionally and write the authenticated user's school ID.

### `permission_role`

| Column | Type | Nullable | Key/index | Purpose |
| --- | --- | --- | --- | --- |
| `permission_id` | unsigned bigint | No | FK `permissions`, cascade delete; composite primary key | Permission |
| `role_id` | unsigned bigint | No | FK `roles`, cascade delete; composite primary key | Role receiving permission |

### `courses`

| Column | Type | Nullable | Key/index | Purpose |
| --- | --- | --- | --- | --- |
| `id` | unsigned bigint | No | Primary key | Course identifier |
| `school_id` | unsigned bigint | No | FK `schools`, restrict delete; several composite indexes | Tenant owner |
| `lecturer_id` | unsigned bigint | No | FK `users`, restrict delete; index with `school_id` | Assigned lecturer |
| `code` | varchar | No | Unique with `school_id` | School-local course code |
| `title` | varchar | No | — | Course title |
| `description` | text | Yes | — | Course description |
| `is_active` | boolean | No | Index with `school_id`; default true | Course status |
| `created_at`, `updated_at` | timestamp | Yes | — | Laravel timestamps |

School admins create courses and assign lecturers. Lecturer collection queries return only courses where `lecturer_id` equals the authenticated lecturer.

### `course_student`

| Column | Type | Nullable | Key/index | Purpose |
| --- | --- | --- | --- | --- |
| `school_id` | unsigned bigint | No | FK `schools`, restrict delete; index with `student_id` | Tenant context |
| `course_id` | unsigned bigint | No | FK `courses`, cascade delete; composite primary key | Enrolled course |
| `student_id` | unsigned bigint | No | FK `users`, cascade delete; composite primary key | Enrolled student |
| `enrolled_at` | timestamp | No | Default current time | Enrollment time |

The `(course_id, student_id)` primary key prevents duplicate enrollment. There are deliberately no `created_at` or `updated_at` columns.

### `assignments`

| Column | Type | Nullable | Key/index | Purpose |
| --- | --- | --- | --- | --- |
| `id` | unsigned bigint | No | Primary key | Assignment identifier |
| `school_id` | unsigned bigint | No | FK `schools`, restrict delete; indexes with course/release | Tenant owner |
| `course_id` | unsigned bigint | No | FK `courses`, cascade delete; indexes with school/due date | Parent course |
| `created_by` | unsigned bigint | No | FK `users`, restrict delete | Creator |
| `title` | varchar | No | — | Assignment title |
| `description` | text | Yes | — | Instructions |
| `file_path` | varchar | Yes | — | Private internal object path; never serialized normally |
| `file_name` | varchar | Yes | — | Sanitized original display name |
| `file_mime_type` | varchar | Yes | — | Server-detected MIME type |
| `file_size` | unsigned bigint | Yes | — | Server-detected size in bytes |
| `maximum_score` | decimal(8,2) | No | Default 100 | Maximum valid score |
| `due_at` | timestamp | Yes | Index with `course_id` | Optional deadline |
| `target_type` | enum(`all`,`selected`) | No | Default `all` | Audience strategy |
| `is_released` | boolean | No | Index with `school_id`; default false | Grade visibility gate |
| `released_at` | timestamp | Yes | — | Release time |
| `released_by` | unsigned bigint | Yes | FK `users`, null on delete | Releasing user |
| `created_at`, `updated_at` | timestamp | Yes | — | Laravel timestamps |

For `all`, visibility is computed dynamically from current `course_student` enrollment. For `selected`, visibility requires a matching `assignment_targets` row. Lecturers may edit/delete only before the first submission. Attachments use private generated paths and authenticated streaming.

### `assignment_targets`

| Column | Type | Nullable | Key/index | Purpose |
| --- | --- | --- | --- | --- |
| `id` | unsigned bigint | No | Primary key | Target identifier |
| `school_id` | unsigned bigint | No | FK `schools`, restrict delete; index with `student_id` | Tenant context |
| `assignment_id` | unsigned bigint | No | FK `assignments`, cascade delete; unique with student | Selected assignment |
| `student_id` | unsigned bigint | No | FK `users`, cascade delete; unique with assignment | Selected enrolled student |
| `created_at`, `updated_at` | timestamp | Yes | — | Laravel timestamps |

Unique `(assignment_id, student_id)` prevents duplicate targeting. Creation/update actions verify every selected student is enrolled in the assignment's course.

### `assignment_submissions`

| Column | Type | Nullable | Key/index | Purpose |
| --- | --- | --- | --- | --- |
| `id` | unsigned bigint | No | Primary key | Submission identifier |
| `school_id` | unsigned bigint | No | FK `schools`, restrict delete; indexes with student/assignment | Tenant owner |
| `assignment_id` | unsigned bigint | No | FK `assignments`, cascade delete; unique with student | Submitted assignment |
| `student_id` | unsigned bigint | No | FK `users`, restrict delete; unique with assignment | Submitting student |
| `submission_text` | text | Yes | — | Escaped student response text |
| `file_path` | varchar | Yes | — | Private internal object path |
| `original_filename` | varchar | Yes | — | Sanitized display filename |
| `mime_type` | varchar | Yes | — | Server-detected MIME type |
| `file_size` | unsigned bigint | Yes | — | Server-detected size in bytes |
| `submitted_at` | timestamp | No | — | Submission/update time |
| `created_at`, `updated_at` | timestamp | Yes | — | Laravel timestamps |

Unique `(assignment_id, student_id)` gives one current submission per student and assignment. Student queries add `student_id = authenticated user`; lecturer queries require ownership of the assignment's course. Resources omit `file_path`.

### `grades`

| Column | Type | Nullable | Key/index | Purpose |
| --- | --- | --- | --- | --- |
| `id` | unsigned bigint | No | Primary key | Grade identifier |
| `school_id` | unsigned bigint | No | FK `schools`, restrict delete; index with student | Tenant owner |
| `assignment_id` | unsigned bigint | No | FK `assignments`, cascade delete; unique with student | Parent assignment |
| `submission_id` | unsigned bigint | No | FK `assignment_submissions`, cascade delete; unique | Graded submission |
| `student_id` | unsigned bigint | No | FK `users`, restrict delete; unique with assignment | Student receiving grade |
| `graded_by` | unsigned bigint | No | FK `users`, restrict delete | Lecturer/admin actor |
| `score` | decimal(8,2) | No | — | Score validated against assignment maximum |
| `feedback` | text | Yes | — | Optional feedback |
| `graded_at` | timestamp | No | — | Grading time |
| `created_at`, `updated_at` | timestamp | Yes | — | Laravel timestamps |

Unique `submission_id` and unique `(assignment_id, student_id)` prevent duplicate grade records. Student-visible grade queries require `assignments.is_released = true` and authenticated student ownership.

### `audit_logs`

| Column | Type | Nullable | Key/index | Purpose |
| --- | --- | --- | --- | --- |
| `id` | unsigned bigint | No | Primary key | Audit event identifier |
| `school_id` | unsigned bigint | Yes | FK `schools`, restrict delete; index with creation time | Tenant context; may be null for platform events |
| `actor_id` | unsigned bigint | Yes | FK `users`, null on delete; index with creation time | Acting user |
| `action` | varchar(100) | No | — | Event name |
| `auditable_type` | varchar | Yes | Polymorphic index with ID | Target model type |
| `auditable_id` | unsigned bigint | Yes | Polymorphic index with type | Target identifier |
| `old_values` | JSON | Yes | — | Prior state snapshot |
| `new_values` | JSON | Yes | — | New state snapshot |
| `ip_address` | varchar(45) | Yes | — | Request IP where available |
| `user_agent` | text | Yes | — | Request user agent where available |
| `created_at` | timestamp | No | Default current time; tenant/actor indexes | Event time |

There is no `updated_at`. `AuditLog` model hooks and `AppendOnlyBuilder` reject update, delete, and force-delete operations through Eloquent. This does not make the table immutable to a privileged database account.

### `personal_access_tokens`

| Column | Type | Nullable | Key/index | Purpose |
| --- | --- | --- | --- | --- |
| `id` | unsigned bigint | No | Primary key | Token row identifier |
| `tokenable_type` | varchar | No | Morph index with `tokenable_id` | Authenticatable model type |
| `tokenable_id` | unsigned bigint | No | Morph index with type | User ID for SchoolTry tokens |
| `name` | text | No | — | Device/token label |
| `token` | varchar(64) | No | Unique | Hashed Sanctum token value |
| `abilities` | text | Yes | — | Sanctum abilities |
| `last_used_at` | timestamp | Yes | — | Last use time |
| `expires_at` | timestamp | Yes | Index | Per-token expiry where set |
| `created_at`, `updated_at` | timestamp | Yes | — | Laravel timestamps |

Global Sanctum expiration is configured separately and defaults to 480 minutes.

## Laravel infrastructure tables

These framework tables are also created by migrations.

### `password_reset_tokens`

| Column | Type | Nullable | Key/index | Purpose |
| --- | --- | --- | --- | --- |
| `email` | varchar | No | Primary key | Password-reset identity |
| `token` | varchar | No | — | Reset token |
| `created_at` | timestamp | Yes | — | Creation time |

### `sessions`

| Column | Type | Nullable | Key/index | Purpose |
| --- | --- | --- | --- | --- |
| `id` | varchar | No | Primary key | Session identifier |
| `user_id` | unsigned bigint | Yes | Index; no FK | Optional user reference |
| `ip_address` | varchar(45) | Yes | — | Client IP |
| `user_agent` | text | Yes | — | Client user agent |
| `payload` | longtext | No | — | Serialized session payload |
| `last_activity` | integer | No | Index | Last activity epoch |

### `cache` and `cache_locks`

| Table | Columns and keys | Purpose |
| --- | --- | --- |
| `cache` | `key` varchar PK; `value` mediumtext; `expiration` integer | Database cache store |
| `cache_locks` | `key` varchar PK; `owner` varchar; `expiration` integer | Atomic cache locks |

### `jobs`, `job_batches`, and `failed_jobs`

| Table | Columns and keys | Purpose |
| --- | --- | --- |
| `jobs` | bigint `id` PK; indexed `queue`; longtext `payload`; tinyint `attempts`; nullable int `reserved_at`; int `available_at`; int `created_at` | Queued jobs |
| `job_batches` | varchar `id` PK; `name`; job counters; longtext `failed_job_ids`; nullable mediumtext `options`; nullable cancellation/finish integers; creation integer | Batch state |
| `failed_jobs` | bigint `id` PK; unique `uuid`; text connection/queue; longtext payload/exception; timestamp `failed_at` | Failed-job diagnostics |

## Indexing and access implications

- Tenant-first indexes support common school/course, school/student, school/lecturer, and release-state filters.
- Unique constraints enforce school-local course codes, one enrollment, one selected target, one submission, and one grade per assignment/student.
- `all` assignment visibility follows current enrollment; enrolling a student later can make an existing `all` assignment visible, while unenrollment removes visibility.
- `selected` assignments are independent of later broad enrollment visibility but targets can only be chosen from currently enrolled students when the assignment is created or edited.
- Cascades remove course-dependent assignments, targets, submissions, and grades. Restrict-delete relationships preserve referenced schools/users when academic or audit records still depend on them.
- Application code must remain tenant-aware because ordinary foreign keys alone do not prove that two referenced rows share a school.
