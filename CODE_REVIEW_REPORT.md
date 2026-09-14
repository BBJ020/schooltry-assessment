# Code Review Report: Flawed Bulk Grading Endpoint

## Reviewed code

The supplied method accepts an assignment ID, loops over browser-provided student IDs and scores, writes grades with `updateOrCreate`, optionally releases results, and returns a success message. It bypasses the security and action layers used by SchoolTry.

## Findings

### 1. Missing authentication and authorization

- **Severity:** Critical
- **Problem:** The method contains no policy authorization and does not demonstrate `auth:sanctum`, tenant, or role middleware.
- **Attack example:** A student or unrelated lecturer calls the endpoint directly and grades an assignment.
- **Impact:** Unauthorized grade creation, modification, and release.
- **Recommended fix:** Put the route behind `auth:sanctum`, `tenant`, and `role:lecturer`; resolve a visible resource; authorize through the relevant policy; delegate grading to `GradeSubmission`.

### 2. Assignment IDOR and null handling

- **Severity:** Critical
- **Problem:** `Assignment::find($assignmentId)` performs an unscoped lookup and may return `null`. The method does not verify school, course ownership, or even existence before continuing.
- **Attack example:** A lecturer changes `/assignments/12/grade` to `/assignments/13/grade` for another school.
- **Impact:** Cross-tenant tampering or a server error that leaks implementation details when the assignment is missing.
- **Recommended fix:** Resolve through `Assignment::query()->visibleTo($user)->findOrFail($id)` and authorize the intended action; return the standard 404 for inaccessible resources.

### 3. No tenant enforcement on grade lookup/write

- **Severity:** Critical
- **Problem:** The `updateOrCreate` identity omits `school_id`, and no tenant context is established.
- **Attack example:** IDs from two tenants collide logically in a malformed request and a grade is attached outside the caller's school.
- **Impact:** Cross-tenant disclosure or corruption.
- **Recommended fix:** Derive `school_id` exclusively from the authenticated tenant in an action, retain `BelongsToSchool` parent checks, and use tenant-scoped models/queries.

### 4. Arbitrary student IDs and missing enrollment/target validation

- **Severity:** Critical
- **Problem:** Every key in `$request->grades` is trusted as a student ID. The student may not belong to the school, course, or assignment audience.
- **Attack example:** A lecturer submits a platform admin's ID or another school's student ID.
- **Impact:** Invalid academic records and tenant relationship corruption.
- **Recommended fix:** Grade a concrete `AssignmentSubmission` resolved through `AssignmentSubmission::visibleTo($lecturer)`; derive student and assignment IDs from that submission, never from browser ownership fields.

### 5. Grade can exist without a valid submission

- **Severity:** High
- **Problem:** The method writes directly by assignment/student and never requires an `assignment_submissions` row.
- **Attack example:** A lecturer grades a student who never submitted or was never assigned the work.
- **Impact:** Fabricated records and broken referential/business integrity.
- **Recommended fix:** Use the existing one-submission-at-a-time `GradeSubmission` action, which receives a real submission and fills `submission_id`, `assignment_id`, `student_id`, `school_id`, and `graded_by` server-side.

### 6. Missing request validation and score bounds

- **Severity:** High
- **Problem:** The request shape, key types, scores, and release flag are unvalidated. Negative, nonnumeric, or above-maximum scores may reach persistence.
- **Attack example:** Send `-50`, an extremely large value, or nested arrays as scores.
- **Impact:** Invalid grades, exceptions, or resource abuse.
- **Recommended fix:** Use `StoreGradeRequest` for explicit scalar fields and let `GradeSubmission` enforce `0 <= score <= assignment.maximum_score`. A safe bulk design would validate every entry and cap batch size.

### 7. Unsafe request use and mass-assignment semantics

- **Severity:** High
- **Problem:** The code trusts `$request->grades`, `$studentId`, `$score`, and `$request->release_results` directly and sets ownership-sensitive fields without a validated DTO/array boundary.
- **Attack example:** Add unexpected nested fields or exploit loose boolean coercion to trigger release.
- **Impact:** Business-rule bypass and fragile behavior.
- **Recommended fix:** Use only `$request->validated()` values, prohibit ownership/audit fields in Form Requests, and force-fill server-derived identifiers inside actions.

### 8. Lecturer ownership is not verified

- **Severity:** Critical
- **Problem:** `auth()->id()` records who graded but does not prove that user owns the course.
- **Attack example:** Lecturer A grades Lecturer B's submissions while still being recorded as the grader.
- **Impact:** Unauthorized academic decisions with misleading attribution.
- **Recommended fix:** Resolve submissions with `visibleTo($lecturer)` and authorize `GradePolicy::grade`, which checks same-school course ownership.

### 9. Premature and unsafe result release

- **Severity:** Critical
- **Problem:** A loosely supplied browser flag releases the assignment in the same unprotected method. Release actor/time fields are not recorded.
- **Attack example:** Add `release_results=1` to a grading request or exploit UI/request manipulation.
- **Impact:** Immediate disclosure of all students' grades and feedback.
- **Recommended fix:** Use a distinct protected release endpoint and the existing `ReleaseAssignment` action, which authorizes ownership and writes `is_released`, `released_at`, `released_by`, and an audit event transactionally.

### 10. No transaction or all-or-nothing behavior

- **Severity:** High
- **Problem:** A failure midway leaves a partially graded batch; release may occur after only some rows are written.
- **Attack example:** One invalid score causes an exception after earlier grades were committed.
- **Impact:** Inconsistent grade sets and accidental partial disclosure.
- **Recommended fix:** Prefer the implemented single-submission action per request. If true bulk grading is required, validate the whole batch first and wrap all grade writes plus any separately authorized release decision in a carefully designed transaction.

### 11. Race conditions and lost updates

- **Severity:** High
- **Problem:** Concurrent requests can overwrite the same grade, interleave partial batches, or release during grading without an explicit concurrency strategy.
- **Attack example:** Two lecturer browser tabs save conflicting batches while one triggers release.
- **Impact:** Last-write-wins corruption and unpredictable release state.
- **Recommended fix:** Use transactions, unique constraints (`grades.submission_id` and `(assignment_id, student_id)`), optional row locks/versioning for bulk workflows, and a distinct release operation after grading completes.

### 12. Missing audit trail

- **Severity:** High
- **Problem:** No old/new grade values, assignment release event, actor context, IP, or user agent are recorded.
- **Attack example:** An unauthorized score change cannot be reconstructed after a complaint.
- **Impact:** Weak accountability and incident response.
- **Recommended fix:** Use `GradeSubmission` and `ReleaseAssignment`; both call `RecordAuditLog` inside their transactions. Keep audit records append-only and restrict database operators.

### 13. Poor exception handling and information leakage

- **Severity:** Medium
- **Problem:** Null assignments, malformed arrays, constraint failures, and database exceptions can become inconsistent 500 responses or debug traces if production is misconfigured.
- **Attack example:** Probe IDs and malformed payloads to distinguish database failures.
- **Impact:** Resource enumeration, internal detail exposure, and unreliable clients.
- **Recommended fix:** Use `findOrFail`, Form Request validation, standardized JSON 401/403/404/422 responses, `APP_DEBUG=false`, and generic client messages while retaining private application logs.

### 14. Abuse and rate protection

- **Severity:** Medium
- **Problem:** An unbounded grades array permits expensive requests and repeated overwrites. No endpoint-specific throttle is shown.
- **Attack example:** Submit thousands of grade entries repeatedly.
- **Impact:** CPU/database pressure and audit noise.
- **Recommended fix:** Prefer one visible submission per request, cap batch sizes if bulk grading is added, and apply an appropriate authenticated-user rate limiter at the application or edge layer. SchoolTry currently rate-limits login and student submission endpoints, not grading.

## Corrected SchoolTry architectural approach

The existing implementation deliberately separates grading and release:

```text
POST /api/lecturer/submissions/{submission}/grade
  -> auth:sanctum
  -> tenant
  -> role:lecturer
  -> StoreGradeRequest
  -> AssignmentSubmission::visibleTo(authenticated lecturer)->findOrFail()
  -> GradeSubmission::execute()
       -> GradePolicy::grade
       -> score <= assignment.maximum_score
       -> DB transaction
       -> server-derived school/assignment/submission/student/grader IDs
       -> append-only audit record

POST /api/lecturer/assignments/{assignment}/release
  -> same middleware
  -> tenant-visible assignment lookup
  -> ReleaseAssignment::execute()
       -> AssignmentPolicy::release
       -> DB transaction
       -> released actor/time set server-side
       -> append-only audit record
```

Illustrative thin-controller shape:

```php
public function store(StoreGradeRequest $request, int $submission, GradeSubmission $action): GradeResource
{
    $ownedSubmission = AssignmentSubmission::query()
        ->visibleTo($request->user())
        ->findOrFail($submission);

    return GradeResource::make($action->execute(
        $request->user(),
        $ownedSubmission,
        (float) $request->validated('score'),
        $request->validated('feedback'),
    ));
}
```

This architecture makes the authenticated user and resolved submission authoritative. The browser supplies grade content only; it cannot choose the school, course, student, submission linkage, grader, or release metadata.
