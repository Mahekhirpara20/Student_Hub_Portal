# Practical 10 test report

## Setup

The XAMPP copy uses `studenthub_db`. The additive role migration in
`migrations/002_add_user_role.sql` has already been applied to the local XAMPP
database. It adds `users.role` as `ENUM('Admin', 'Student') NOT NULL DEFAULT
'Student'`; the one existing account therefore remains a Student. No Practical
8 tables or records were changed.

To apply the migration in another database, select `studenthub_db` in
phpMyAdmin, open its **SQL** tab, paste the contents of
`migrations/002_add_user_role.sql`, and run it once. Do not run it again after
the `role` column exists.

Create test accounts through [register.php](./register.php). New public
registrations receive the Student role by default. To grant Admin only to an
account you designated for this test, replace the placeholder below with that
account's exact test email and run this single-row update in phpMyAdmin:

```sql
UPDATE users
SET role = 'Admin'
WHERE email = 'REPLACE_WITH_YOUR_ADMIN_TEST_EMAIL'
LIMIT 1;
```

Verify that only the designated test account was updated:

```sql
SELECT email, role
FROM users
WHERE email = 'REPLACE_WITH_YOUR_ADMIN_TEST_EMAIL';
```

Do not use an account unless you are authorized to grant it Admin access. Keep
at least one separate registered account as Student. Public registration does
not accept a role.

## Test cases

Complete the **Actual result** field while performing each test. No successful
Student or Admin login is claimed here because the test account passwords and
designated Admin account have not been provided.

### 1. Successful Student login

- **Steps:** Open `http://localhost/Student%20Hub/login.php`; enter the email
  and password for a registered Student account; submit.
- **Expected result:** The request redirects to
  `student_dashboard.php`, which displays the account name/email and Student
  role.
- **Actual result:** ______________________________________________
- **Screenshot:** Capture the Student dashboard after redirect.
- **Suggested filename:** `01-student-login.png`

### 2. Successful Admin login

- **Steps:** First grant Admin to the designated test account using the
  single-row SQL update in Setup. Sign in with that account.
- **Expected result:** The request redirects to `admin_dashboard.php`, which
  displays the account name/email and Admin role.
- **Actual result:** ______________________________________________
- **Screenshot:** Capture the Admin dashboard after redirect.
- **Suggested filename:** `02-admin-login.png`

### 3. Invalid email/password

- **Steps:** On the login page, submit a nonexistent email or an incorrect
  password.
- **Expected result:** The page displays “Invalid email or password.” without
  revealing whether an email is registered or showing database details.
- **Actual result:** ______________________________________________
- **Screenshot:** Capture the login page with the generic error visible.
- **Suggested filename:** `03-invalid-credentials.png`

### 4. Student attempting to access the Admin dashboard

- **Steps:** Sign in as Student, then open
  `http://localhost/Student%20Hub/admin_dashboard.php` directly.
- **Expected result:** The response is HTTP 403 and displays “Unauthorized
  Access”; Admin-only content is not shown.
- **Actual result:** ______________________________________________
- **Screenshot:** Capture the unauthorized message and address bar.
- **Suggested filename:** `04-student-denied-admin.png`

### 5. Admin attempting to access the Student dashboard

- **Steps:** Sign in as Admin, then open
  `http://localhost/Student%20Hub/student_dashboard.php` directly.
- **Expected result:** The response is HTTP 403 and displays “Unauthorized
  Access”; Student-only content is not shown.
- **Actual result:** ______________________________________________
- **Screenshot:** Capture the unauthorized message and address bar.
- **Suggested filename:** `05-admin-denied-student.png`

### 6. Successful logout

- **Steps:** Sign in and use the **Log out** button on the dashboard.
- **Expected result:** The session is destroyed and the browser returns to
  `login.php` with “You have been logged out successfully.”
- **Actual result:** ______________________________________________
- **Screenshot:** Capture the login page with the logout-success message.
- **Suggested filename:** `06-logout-success.png`

### 7. Dashboard access denied after logout

- **Steps:** After logout, directly enter
  `http://localhost/Student%20Hub/dashboard.php` or a role dashboard URL.
- **Expected result:** The request redirects to `login.php`; protected content
  is not displayed.
- **Actual result:** ______________________________________________
- **Screenshot:** Capture the login page after the protected URL redirects.
- **Suggested filename:** `07-dashboard-after-logout.png`

### 8. Session timeout

- **Steps:** For a quick test, temporarily change
  `SESSION_TIMEOUT_SECONDS` in `timeout.php` from `300` to `30`. Sign in, wait
  at least 30 seconds without making requests, then refresh a protected
  dashboard. Restore the value to `300` after testing.
- **Expected result:** The session is destroyed and the browser is redirected
  to `login.php?timeout=1` with the inactivity-expired message.
- **Actual result:** ______________________________________________
- **Screenshot:** Capture the login page with the session-expired message.
- **Suggested filename:** `08-session-timeout.png`

### 9. Session ID regeneration after login

- **Steps:** In browser developer tools, inspect the `PHPSESSID` cookie before
  login. Sign in successfully and inspect it again.
- **Expected result:** The cookie value changes after login because PHP
  regenerates the session ID; authenticated session data is then set.
- **Actual result:** ______________________________________________
- **Screenshot:** Capture before and after states as
  `09a-session-before-login.png` and `09b-session-after-login.png`.
- **Security note:** Treat `PHPSESSID` as a secret. Do not include its value in
  submitted screenshots; redact the value after checking that it changed.

## Additional compatibility checks

- PDO/Practical 8 connectivity: `http://localhost/Student%20Hub/test_connection.php`
- Registration: `http://localhost/Student%20Hub/register.php`
- Login: `http://localhost/Student%20Hub/login.php`
- Use XAMPP Apache and MySQL; do not use Live Server for PHP pages.

`user_registration.sql` contains the role-enabled table structure only; it
does not expose registered users' names, emails, or password hashes from the
public web root. For a real-data export, use phpMyAdmin to export only the
designated Student and Admin demonstration rows, keep password hashes intact,
and store the export outside the served web directory.

## Submission checklist

- [ ] Apply the role migration once in the target database.
- [ ] Register a Student test account and designate an authorized Admin test
  account.
- [ ] Complete the Actual result fields for all nine tests.
- [ ] Capture the screenshots with the suggested filenames.
- [ ] Redact session-cookie values and any unrelated personal information.
- [ ] Restore the normal 300-second timeout after the timeout test.
- [ ] Include only the minimum required SQL export; keep it outside the public
  web root if it contains account data.
