# StudentHub Security Review

## Scope

This is a focused review of the authentication, registration, session, role,
student-management, audit-log, and Practical 13 API code in the VS Code
workspace. It is not a penetration test or a review of every static page.

## Controls present

- Registration uses `password_hash()`, validates input, checks for duplicate
  email/username, and relies on database unique keys.
- Login uses a prepared query, `password_verify()`, session ID regeneration,
  role validation, and generic failure messages.
- Protected PHP routes validate the session and role. Mutation forms use CSRF
  tokens; API mutations require the CSRF request header.
- Student and audit-page output is HTML-escaped. Student management and the
  inventory API use prepared statements.
- Sessions use HTTP-only, SameSite cookies, strict-mode settings, no URL
  session IDs, and a five-minute inactivity timeout.

## Issues corrected

- The audit page selected `log_id`, but the configured `studenthub_db` table
  uses `id`. The query, clean-install schema, and migration now match the live
  table. The migration preserves existing IDs when converting the older
  `log_id` name and adds `ip_address` only when missing.
- The legacy `login.html` form displayed a successful-login alert without
  authenticating. It now sends the user to the real PHP login route.
- The legacy password-reset control claimed to send a reset link even though
  no reset workflow exists. It now states that reset is unavailable.
- The legacy `signout.html` button returned to the static login page instead
  of ending the session. It now leads to a CSRF-protected sign-out confirmation
  using the existing `logout.php` handler.

## Remaining limitations

- Root-level HTML pages are static prototypes and do not enforce login or
  display private database records. Keep real student/account data out of
  those pages.
- Event registration is not implemented, and Student accounts are not mapped
  to `students.student_id`. Do not add a browser-supplied ID as the
  registration identity.
- The Practical 12 event-management implementation files are absent from this
  workspace even though its README describes them.
- No real-account authentication or authorization test was performed during
  this review. The route checks covered unauthenticated redirects only.

## Verification

All PHP files passed syntax checks. The audit query executed against
`studenthub_db`, and re-running the audit migration left the audit row count
unchanged. See `PRACTICAL15-TEST-REPORT.md` for the tested routes and remaining
unverified workflows.
