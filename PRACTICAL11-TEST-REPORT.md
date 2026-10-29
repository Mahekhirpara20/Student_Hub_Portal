# Practical 11: Student Management System

## Existing schema and migration

The live `studenthub_db` contains `students`, `users`, `events`, and
`registrations`. `students` already had `student_id`, `full_name`, `email`,
`branch`, and `semester`; it has no enrollment number, contact number, or
separate course table. The management page uses `branch` as the existing
course-like value and loads filter choices from distinct values in that
column. Adding a student record does not create a login account in `users`.

The soft-delete requirement needed a status column. The additive migration
`migrations/003_add_student_status.sql` added `status` as `Active` or
`Inactive`, defaulting to `Active`. It was applied to the existing database;
verification found the five existing student rows still present and Active.
No user, registration, password, role, or other table was changed by the
migration.

## Test setup

1. Start Apache and MySQL in XAMPP.
2. Sign in at `http://localhost/Student%20Hub/login.php` with an existing
   Admin account. The Admin dashboard contains the **Manage student records**
   link.
3. Open `http://localhost/Student%20Hub/student_management.php`.
4. For add/edit/deactivate tests, use a clearly identifiable test record and a
   unique test email. Record that email privately so you can search for it;
   do not use a real student's email for test data.
5. Fill in each test's Actual result after reproducing it. No browser-based
  screenshot has been captured yet. The localhost HTTP smoke checks below
  used a synthetic Admin session and do not substitute for signing in with
  your real Admin account or capturing the requested screenshots.

## HTTP smoke-check results

The implementation was exercised over localhost HTTP with a temporary
synthetic Admin-role session. The management table rendered; add, edit,
combined search/course filtering, delete confirmation, soft delete, and
filtered CSV export returned the expected results. Duplicate and malformed
email submissions were rejected. The CSV response had `text/csv; charset=UTF-8`,
the expected headers, and a UTF-8 BOM. A synthetic Student-role session
received HTTP 403, and an unauthenticated request redirected to login.

These checks verify application responses, not screenshots or sign-in through
an existing account. The latest live count query returned 6 Active and 1
Inactive student, with 3 user accounts and 6 event registrations. Do not
assume the inactive row is available test data; create a new unique test row
for your screenshot workflow.

## Required Screenshot Order

Use the same newly added test student for screenshots 2 through 6. Keep the
record Inactive after screenshot 5. Capture screenshots after signing in with
your real Admin account; the prior HTTP checks used a synthetic local session.

### 1. Student records table

- **Steps:** Open the management URL while signed in as Admin. Leave search
  empty and select Active status.
- **Expected:** A semantic table shows student ID, name, email, course/branch,
  semester, status, and available actions. If no rows match, a helpful empty
  message appears.
- **Screenshot:** `11-01-student-records.png`
- **Caption:** `Active student records displayed in the Admin management table.`
- **Actual result:** The live browser table rendered; screenshot not captured.

### 2. Add a student

- **Steps:** Enter a test name, unique valid test email, an existing branch
  value, and a semester from 1 to 12. Submit **Add student**.
- **Expected:** The page redirects after POST, displays a success alert, and
  shows the new record with Active status.
- **Screenshot:** `11-02-student-added.png`
- **Caption:** `A new student record was added with Active status.`
- **Actual result:** HTTP add/redirect/success check passed; real-account screenshot not captured.

### 3. Update student details

- **Steps:** Click **Edit** for the test record. Change its name or semester
  and save the form.
- **Expected:** The page redirects after POST, shows an update success alert,
  and displays the changed values. The status remains unchanged.
- **Screenshot:** `11-03-student-updated.png`
- **Caption:** `Student details were updated while the existing status was preserved.`
- **Actual result:** HTTP edit/redirect/status-preservation check passed; real-account screenshot not captured.

### 4. Delete confirmation

- **Steps:** Find the Active test record and click **Delete**. Do not confirm
  yet.
- **Expected:** A confirmation identifies the student and explains the record
  will be retained as Inactive. No change occurs until confirmed by POST.
- **Screenshot:** `11-04-delete-confirmation.png`
- **Caption:** `The selected student is identified before soft deletion.`
- **Actual result:** HTTP confirmation-page check passed; real-account screenshot not captured.

### 5. Successful soft delete

- **Steps:** From the confirmation section, click **Confirm: mark Inactive**.
  Then select Inactive status if needed.
- **Expected:** A success alert appears; the test row remains in the database
  with Inactive status and is excluded from the default Active list.
- **Screenshot:** `11-05-student-inactive.png`
- **Caption:** `The student is marked Inactive without removing its database row.`
- **Actual result:** HTTP soft-delete and retained-row check passed; real-account screenshot not captured.

### 6. CSV export in Excel

- **Steps:** Filter for the test student's name/email, its existing course, and
  Inactive status. Click **Export CSV**, then open the downloaded CSV in Excel.
- **Expected:** Excel shows the UTF-8 headers and only the filtered row(s).
- **Screenshot:** `11-06-csv-in-excel.png`
- **Caption:** `The filtered student export opens in Excel with readable columns.`
- **Actual result:** CSV response, header, and UTF-8 BOM verified; Excel opening and screenshot not verified.

### 7. Search functionality

- **Steps:** Enter a distinctive part of the test student's name or email and
  submit **Search / filter**.
- **Expected:** Matching rows appear, the term remains in the input, and Clear
  resets the filters.
- **Screenshot:** `11-07-student-search.png`
- **Caption:** `A partial name or email search returns the matching student.`
- **Actual result:** HTTP search check passed; real-account screenshot not captured.

### 8. Course-wise filtering

- **Steps:** Select an existing Course / branch value such as
  `Information Technology`; submit the filter. Optionally keep the test search
  term to show combined filtering.
- **Expected:** Only rows in that existing branch value appear. All courses
  clears just the course restriction.
- **Screenshot:** `11-08-course-filter.png`
- **Caption:** `The table is filtered using a course value from the database.`
- **Actual result:** HTTP course-filter check passed; real-account screenshot not captured.

### 9. Success and error messages

- **Steps:** Capture a success alert after add, update, or soft delete. Then
  try adding another record with the test student's email and otherwise valid
  fields.
- **Expected:** Success appears after valid changes; duplicate email displays
  a clear error and creates no additional row.
- **Screenshot:** `11-09-success-error-messages.png`
- **Caption:** `Student actions report success and duplicate email is rejected.`
- **Actual result:** HTTP success and duplicate-email error checks passed; real-account screenshot not captured.

### 10. Active and inactive totals

- **Steps:** Open the management page with no filters and capture the two
  summary cards above the form/records.
- **Expected:** Active and Inactive totals match the database and refresh after
  add or soft delete. Current baseline: Active 6, Inactive 1. After adding and
  deactivating one new test student, expected totals are Active 6, Inactive 2,
  assuming no other Admin changes during testing.
- **Screenshot:** `11-10-student-status-totals.png`
- **Caption:** `Student Management displays current Active and Inactive totals.`
- **Actual result:** Prepared count query and browser display verified the
  baseline Active 6 / Inactive 1. A screenshot preview was captured in the
  browser session; save a report copy manually.

For server-side validation evidence, include a duplicate-email submission in
screenshot 9. Client-side required/email/semester validation may prevent an
invalid form from reaching PHP; database errors are logged server-side and
shown to the page as a safe generic message.

## Authorization check

- **Admin:** Sign in as Admin and open the management URL. The records and
  management actions should be available.
- **Student:** Sign in as a Student and open the same URL. The server should
  return HTTP 403 without showing records or forms.
- **Signed out:** Open the URL after logout. Existing session validation should
  redirect to login.

Complete these checks manually with the appropriate existing accounts. Do not
include passwords, password hashes, or session-cookie values in screenshots.