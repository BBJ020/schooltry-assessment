# Superadmin and School-Admin Management

SchoolTry separates platform administration from tenant administration.

- A `superadmin` has `users.school_id = NULL` and a `role_user` row whose `school_id` is also `NULL`. Platform routes use `auth:sanctum`, `role:superadmin`, and `platform`; they never use tenant middleware.
- An `admin` belongs to exactly one school. Admin routes use `auth:sanctum`, `tenant`, and `role:admin`, so model binding and collection queries execute inside the authenticated school context.
- Lecturer and student academic authorization remains controlled by the existing middleware, visible scopes, and policies.

The `platform` middleware requires an active, school-less superadmin and explicitly clears `TenantContext` before and after the request. A superadmin is rejected from ordinary tenant management routes because `EnsureTenant` requires a school.


## Security behavior

- Browser-supplied `school_id`, passwords, status fields, and ownership fields are prohibited where the server owns them.
- A school selected in a superadmin route supplies the school ID for new admins.
- A school admin's authenticated tenant supplies all tenant ownership.
- An existing user assigned by a superadmin must be school-less or already belong to the selected school and must not be a superadmin.
- Tenant role changes accept only `admin`, `lecturer`, or `student`; role rows are replaced transactionally and carry the user's school ID.
- Temporary passwords are generated cryptographically, hashed by the User model, returned once, and never written to audit logs.
- Enrollment queries require same-school users with the student role. Writes include only `school_id`, `course_id`, `student_id`, and the schema's existing `enrolled_at`; the pivot model has timestamps disabled.
- Sensitive mutations create append-only audit entries with actor, target, action, and applicable school context.
