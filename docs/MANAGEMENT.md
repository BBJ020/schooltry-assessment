# Superadmin and school-admin management

SchoolTry separates platform administration from tenant administration.

- A `superadmin` has `users.school_id = NULL` and a `role_user` row whose `school_id` is also `NULL`. Platform routes use `auth:sanctum`, `role:superadmin`, and `platform`; they never use tenant middleware.
- An `admin` belongs to exactly one school. Admin routes use `auth:sanctum`, `tenant`, and `role:admin`, so model binding and collection queries execute inside the authenticated school context.
- Lecturer and student academic authorization remains controlled by the existing middleware, visible scopes, and policies.

The `platform` middleware requires an active, school-less superadmin and explicitly clears `TenantContext` before and after the request. A superadmin is rejected from ordinary tenant management routes because `EnsureTenant` requires a school.

## Local development accounts

The optional local seeder refuses to run unless `APP_ENV=local` and requires a caller-supplied password of at least 12 characters. It is not called by deployment or production migrations.

In local `.env`, set:

```text
SCHOOLTRY_DEV_PASSWORD=<your-local-password-of-at-least-12-characters>
```

Then run:

```bash
php artisan migrate
php artisan db:seed --class=LocalDevelopmentSeeder
```

Local account emails are:

- `superadmin@schooltry.test`
- `admin@schooltry.test`
- `lecturer@schooltry.test`
- `student@schooltry.test`

All use the locally supplied `SCHOOLTRY_DEV_PASSWORD`. Never copy that variable or these development accounts into production. For production, run `RoleSeeder` deliberately if the four role records do not yet exist, then create the first superadmin through a controlled operator procedure.

## Security behavior

- Browser-supplied `school_id`, passwords, status fields, and ownership fields are prohibited where the server owns them.
- A school selected in a superadmin route supplies the school ID for new admins.
- A school admin's authenticated tenant supplies all tenant ownership.
- An existing user assigned by a superadmin must be school-less or already belong to the selected school and must not be a superadmin.
- Tenant role changes accept only `admin`, `lecturer`, or `student`; role rows are replaced transactionally and carry the user's school ID.
- Temporary passwords are generated cryptographically, hashed by the User model, returned once, and never written to audit logs.
- Enrollment queries require same-school users with the student role. Writes include only `school_id`, `course_id`, `student_id`, and the schema's existing `enrolled_at`; the pivot model has timestamps disabled.
- Sensitive mutations create append-only audit entries with actor, target, action, and applicable school context.

The migration making `role_user.school_id` nullable is required for platform roles. Its new unique `(role_id, user_id)` index prevents duplicate assignments even when `school_id` is `NULL`. Before deliberately rolling that migration back, all platform-role rows must be removed because the old schema cannot represent them.
