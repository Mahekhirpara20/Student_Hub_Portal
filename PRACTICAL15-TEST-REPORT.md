# Practical 15: Project Checklist and Verification

## Reference and scope

No Practical 15 reference/checklist document was found in the workspace. The
Desktop exam document found during inspection covers a separate Task 1
registration exercise, so this report uses only the checklist in the project
request. It does not assume additional requirements or invent data.

## Checklist status

| Area | Status |
| --- | --- |
| Login and registration | PHP login and registration routes exist. Registration hashes passwords, checks duplicate email/username, and uses CSRF. Routes and syntax were checked; no real-account login was performed in this pass. Password reset is not implemented. |
| Admin dashboard and role checks | Role-specific PHP dashboards and Admin-only student/audit pages exist. Unauthenticated access redirects to login. Student-vs-Admin role denial was not exercised in this pass. |
| Student management | Admin add/view/update/deactivate, search/filter, and CSV export are implemented. The existing Practical 11 report documents earlier HTTP checks. CRUD was not repeated in this pass. |
| Audit logs | The page now queries the live `audit_logs.id` column. Its query returned 3 records. The migration ran without changing that row count. |
| Events and search | `events.html` contains static event cards only. It has no search or registration form. `event_management/` currently has a README and uploads folder but not the PHP/SQL/CSS files described by that README. |
| AJAX event registration | Not implemented. The `users` table has no mapping to `students.student_id`; registration must not trust a student ID supplied by the browser. |
| Student profile | `student_dashboard.php` is authenticated, but `profile.html` is static and is not connected to the signed-in user's database record. |
| Results, fees/payments, courses, timetable, notices | These root pages are static prototypes. The project schema has no corresponding live results/payment/course/notices workflows. |
| Dark/light mode | Theme switching exists in the legacy login prototype only; it is not a shared theme for all pages. |
| Navigation | The Monday and Saturday timetable links target missing pages. They were left unchanged because actual schedules were not available. |
| Practical documentation | Practical 10 and 11 test reports exist. This report records the supplied checklist and distinguishes prior checks from checks performed now. |

## Checks performed in this pass

- `php -l` passed for all 17 PHP files in this workspace.
- `node --check` passed for both JavaScript files; the inline JavaScript in
  `login.html` parsed successfully.
- The database connection reported `studenthub_db`; the audit query using
  `id` returned 3 rows.
- Re-running `migrations/004_create_audit_logs.sql` preserved all 3 audit
  rows and retained the `id` and `ip_address` columns.
- Using PHP's built-in server with this workspace as document root:
  `login.php` and `register.php` returned HTTP 200; unauthenticated requests
  to the Admin dashboard, student management, audit page, and sign-out route
  redirected to login; `logout.php` rejected GET with HTTP 405.
- No authenticated student update, Admin login, student-role denial, browser
  screenshot, or end-to-end registration was performed in this pass.

The XAMPP `htdocs` folder and the VS Code workspace are separate copies.
These checks exercised the VS Code workspace; updating this copy does not
automatically update `C:\xampp\htdocs\Student Hub`.
