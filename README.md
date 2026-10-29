STUDENT HUB PORTAL

PAGES
1.LOGIN
2.HOME
3.TIME-TABLE
4.ATTENDENCE
5.ASSIGNMENTS
6.EVENTS
7.CERTIFICATES
8.FEES
9.NOTIFICATIONS
10.PROFILE
11.RESULTS 
12.STUDY RESOURCES
13.LOGOUT

## Existing PHP workflows

Run the project through XAMPP Apache and MySQL. The secure sign-in page is
`http://localhost/Student%20Hub/login.php`; registration is at
`http://localhost/Student%20Hub/register.php`. The Admin pages are
`admin_dashboard.php`, `student_management.php`, and `audit_logs.php`.
Student sign-in goes to `student_dashboard.php`. These workflows use the
existing `studenthub_db`, prepared statements, hashed passwords, role checks,
session timeout, and CSRF tokens where applicable.

The VS Code workspace at
`C:\Users\hetvi\OneDrive\Desktop\Student Hub` is separate from
`C:\xampp\htdocs\Student Hub`; changes in the workspace do not automatically
change the XAMPP copy. To run the open workspace directly for local testing,
use `C:\xampp\php\php.exe -S 127.0.0.1:8000 -t "C:\Users\hetvi\OneDrive\Desktop\Student Hub"`
and open `http://127.0.0.1:8000/login.php`.

The audit page reads the existing `audit_logs.id` primary key. The matching
schema and migration are in `schema.sql` and
`migrations/004_create_audit_logs.sql`; applying the migration is additive and
does not delete existing audit rows.

## Prototype pages and incomplete integrations

The existing root HTML pages preserve the original StudentHub interface, but
their timetable, attendance, results, fees, notices, profile, and event
content is static sample content rather than authenticated, live database
data. The event cards do not submit registrations. `event_management/` in
this workspace currently contains its README and uploads folder only; the
PHP/SQL/CSS files described there are not present. Student accounts also are
not linked to `students.student_id`, so an AJAX registration flow must not
infer a student identity from an untrusted browser value.
The password-reset control on the legacy login prototype now reports that no
reset workflow is available rather than claiming that a reset email was sent.

Monday and Saturday timetable links currently point to missing pages. The
actual schedules and a Practical 15-specific reference checklist were not
present in this workspace, so no course, payment, results, notices, event
registration, or timetable data has been invented.

## Verification

The existing Practical 10 and 11 test procedures are documented in
`PRACTICAL10-TEST-REPORT.md` and `PRACTICAL11-TEST-REPORT.md`. Tests performed
for this correction should be reported separately from the real-account
manual tests; do not mark a screenshot or database-backed feature as verified
unless it has actually been exercised.
