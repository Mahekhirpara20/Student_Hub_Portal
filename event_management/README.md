# Practical 12: Event Management

> Workspace status: this directory currently contains this README and the
> `uploads/` folder only. The PHP, SQL, and CSS module files described below
> are not present, so the routes and setup steps are not currently runnable.

This module is isolated from Practical 11 and uses the existing StudentHub
`studenthub_db`, `db.php`, `users` table, hashed Admin passwords, and Practical
10 session/CSRF helpers. Its events are stored in `practical12_events`; the
existing Practical 8 `events` and `registrations` tables are not modified.

## Files

- `database.sql`: creates only the Practical 12 event table.
- `config.php`: loads the existing project database connection.
- `auth.php`: Admin authorization, validation, flash messages, and poster file
  helpers.
- `setup_admin.php`: localhost-only first-Admin creation using
  `password_hash()`. It refuses to add an account when an Admin already exists.
- `login.php`, `logout.php`: Admin sign-in and CSRF-protected sign-out.
- `dashboard.php`: status counts and links to event management.
- `events.php`: search, status filter, event table, and poster thumbnails.
- `add_event.php`, `edit_event.php`, `delete_event.php`: event CRUD and poster
  handling.
- `style.css`: responsive module styling.
- `uploads/`: generated poster files; Apache script execution is denied by
  `uploads/.htaccess`.

## Setup in XAMPP

1. Start Apache and MySQL in the XAMPP Control Panel.
2. Open `http://localhost/phpmyadmin/` and select the existing `studenthub_db`.
3. Choose **Import**, select `C:\xampp\htdocs\Student Hub\event_management\database.sql`,
   then click **Import**. The script uses `CREATE TABLE IF NOT EXISTS` for
   the new `practical12_events` table. It does not drop, rewrite, or seed other
   tables. Do not rerun if the table already exists unless you are intentionally
   checking the idempotent setup.
4. Your project already has an Admin account. Use its existing email and
   password at `http://localhost/Student%20Hub/event_management/login.php`.
   Do not create another Admin or change the account password for this module.
5. If the existing `users` table has no Admin in a different test database,
   use `http://localhost/Student%20Hub/event_management/setup_admin.php` from
   localhost only. Choose a strong password of at least 12 characters. The
   page stores a PHP password hash and refuses to create a second Admin. No
   default credentials are included in SQL.
6. If poster uploads fail due to folder permissions, ensure Apache can write to
   `event_management/uploads/`. PHP `file_uploads` and `fileinfo` must be
   enabled. XAMPP currently has both enabled. The application limit is 2 MB;
   the PHP `upload_max_filesize` and `post_max_size` must each be at least 2 MB.

## URLs

- Admin login: `http://localhost/Student%20Hub/event_management/login.php`
- Admin dashboard: `http://localhost/Student%20Hub/event_management/dashboard.php`
- Event list: `http://localhost/Student%20Hub/event_management/events.php`
- Add event: `http://localhost/Student%20Hub/event_management/add_event.php`
- Existing database configuration remains in `C:\xampp\htdocs\Student Hub\db.php`.

The dashboard, event list, add, edit, and delete-confirmation pages require an
Admin session. Student accounts receive HTTP 403; signed-out users are
redirected to the module login page. Login and mutations use the existing
session cookie settings and CSRF tokens. Add, edit, delete, and logout use
POST; successful mutations redirect with HTTP 303.

## Manual test sequence and screenshots

Use a disposable event with a real JPG or PNG poster. Screenshots must not show
passwords, password hashes, or session-cookie values.

1. **Admin login and dashboard**: open the login URL, sign in with the existing
   Admin account, then capture the dashboard with the Event Management title
   and status counts visible. Suggested file:
   `12-01-admin-login-dashboard.png`.
2. **Successful creation**: open Add event, enter a name, description, future
   date/time, venue, Upcoming status, and a valid poster under 2 MB. Submit and
   capture the success message. `12-02-event-created.png`.
3. **Event list and poster**: open the event list and capture the new row with
   poster thumbnail, date/time, venue, and status.
   `12-03-event-list-poster.png`.
4. **Update**: choose Edit, change the venue or description, leave the poster
   input empty, save, and capture the updated row with the same poster.
   `12-04-event-updated.png`.
5. **Deletion**: click Delete and capture the confirmation page. Confirm the
   deletion and capture the success alert and row's absence. Use
   `12-05-event-delete-confirmed.png` for the confirmation and
   `12-05b-event-deleted.png` for the result.
6. **Valid poster upload**: add or edit an event with a real JPG/PNG under
   2 MB; capture the poster thumbnail and success alert.
   `12-06-valid-poster.png`.
7. **Invalid type**: attempt an upload using a small `.txt` file or a non-image
   file renamed `.jpg`. Capture the clear validation error and confirm no event
   was added. `12-07-invalid-poster-type.png`.
8. **File size limit**: attempt to upload a real JPG/PNG larger than 2 MB but
   smaller than the PHP upload limit. Capture the 2 MB error and confirm no
   event was added. `12-08-poster-too-large.png`.
9. **Success and error messages**: capture a successful CRUD alert and one
   invalid-upload or invalid-field error. `12-09-event-messages.png`.
10. **Unauthorized restriction**: sign out and open the dashboard URL; capture
    the redirect to module login. Sign in as a Student account and open
    `events.php`; capture HTTP 403. `12-10-unauthorized-access.png`.

Record actual results only after performing these steps. Do not submit real
student information or non-test event data for the practical demonstration.

## Lab explanation

“This module reuses StudentHub's existing Admin accounts, password hashes, and
secure session checks. Practical 12 events are stored in their own table so
Practical 8 and 11 data stay unchanged. Admins can create, view, search, filter,
update, and delete events. Poster uploads are restricted by extension, MIME
type, image contents, and a 2 MB size limit, then stored under a random safe
filename. Forms use CSRF tokens and prepared statements, and event text is
escaped before it is displayed.”
