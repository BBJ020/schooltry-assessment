# SchoolTry Security Analysis

## Top Five Security Risks and Implemented Controls

### 1. Cross-Tenant Data Leakage
**Risk:** A user from one school could try to access data belonging to another school.

**Implemented controls:**
- `auth:sanctum`
- `EnsureTenant`
- request-scoped `TenantContext`
- `BelongsToSchool` global scope
- tenant-aware policies
- `visibleTo()` scopes
- server-derived `school_id`
- tenant-safe lookups returning 404 for inaccessible resources

### 2. IDOR / Broken Object-Level Authorization
**Risk:** An authenticated user could manipulate resource IDs to access another user's submission, grade, or assignment.

**Implemented controls:**
- tenant-scoped resource lookup
- policies for assignments, submissions, courses, and grades
- `visibleTo()` scopes
- ownership checks
- server-side IDs instead of trusting browser-supplied ownership fields
- inaccessible objects return 404

### 3. Premature Grade Disclosure
**Risk:** A student could attempt to view grades before the lecturer releases results.

**Implemented controls:**
- `Grade::visibleTo()`
- `GradePolicy::view()`
- authenticated student ownership check
- `assignments.is_released`
- unreleased grades return 404
- student API resources do not expose hidden scores or feedback

### 4. Malicious or Unrestricted File Upload
**Risk:** A user could upload an oversized, unsafe, or unauthorized file.

**Implemented controls:**
- Form Request validation
- file type and MIME validation
- assignment and submission size limits
- server-generated storage paths
- private S3 storage
- EC2 IAM role rather than static AWS keys
- authenticated download endpoints
- no public S3 URLs exposed

### 5. Privilege Escalation / Unauthorized Grading
**Risk:** A student, lecturer, or tenant administrator could attempt actions outside their assigned role.

**Implemented controls:**
- role middleware
- separate platform and tenant contexts
- policies
- lecturer-course ownership enforcement
- restricted tenant roles
- server-controlled `school_id`
- transactional grading
- append-only audit logging
- temporary passwords are generated and hashed server-side

## Breach Scenario

A student attempts to access another student's unreleased grade.

The request must first pass Sanctum authentication and tenant middleware. The assignment is resolved through `Assignment::visibleTo()`, which checks course enrollment or selected targeting. The grade query is restricted to the authenticated student's ID and requires the assignment to be released. `GradePolicy::view()` repeats ownership and release checks.

If any condition fails, the request returns a generic 404 and the score or feedback is never returned.

## Logging and Monitoring

### Implemented
- append-only `audit_logs`
- Laravel application logs
- Nginx access/error logs
- login throttling
- submission throttling
- CI security and dependency checks
- `APP_DEBUG=false` in production

### Recommended
- CloudWatch centralized logging
- alerts for repeated 401/403/404/429 responses
- monitoring of unusual grade access
- RDS monitoring
- S3 access monitoring