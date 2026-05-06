# Finals Possible Questions and Answers

## Quick System Summary

**Q: What is our system?**  
A: It is a PHP and MySQL web application for managing university visitors, campus check-in/check-out, office destinations, restricted guests, vehicle entries, reports, audit logs, and Guest House accommodations.

**Q: What problem does it solve?**  
A: It replaces manual visitor logbooks with a centralized system where guests can pre-register, guards can check guests in and out, offices can receive visitors, admins can manage users and reports, and Guest House staff can manage accommodations.

**Q: What technologies did we use?**  
A: PHP for backend pages/controllers, MySQL/MariaDB for the database, PDO for database access, HTML/CSS/JavaScript for the interface, XAMPP for local development, and InfinityFree for hosting.

**Q: What is the main project structure?**  
A:
- `public/` contains browser-accessible pages.
- `modules/` contains reusable database/model functions.
- `includes/` contains shared auth, helpers, session, and layout files.
- `config/` contains app constants and database connection.
- `assets/` contains CSS, JavaScript, and images.
- `database.sql` contains the schema and seed data.

## Architecture and File Flow

**Q: Why do we have both `public/` and `modules/` folders?**  
A: `public/` contains pages that users open in the browser, such as dashboards, forms, and lists. `modules/` contains reusable backend functions that handle database operations. This separates page/controller logic from database/model logic.

**Q: What usually happens when a page is opened?**  
A:
1. The page loads config and helper files.
2. It checks authentication or role access.
3. It reads GET/POST input.
4. It calls SQL directly or calls a module function.
5. It prepares data for display.
6. It includes the shared header.
7. It renders HTML.
8. It includes the shared footer.

**Q: What is the purpose of `includes/header.php`?**  
A: It creates the shared authenticated layout, including sidebar navigation, topbar, flash messages, user menu, and common scripts. It also calls `requireLogin()` so protected pages cannot show private content without a session.

**Q: What is the purpose of `includes/helpers.php`?**  
A: It contains reusable helper functions for CSRF tokens, escaping output with `e()`, redirects, flash messages, input handling, date formatting, and status labels.

**Q: What is the purpose of `includes/auth.php`?**  
A: It handles session security, login, logout, role checks, current user helpers, session timeout, password verification, and activity logging.

**Q: What is the purpose of `config/db.php`?**  
A: It creates the shared PDO database connection through `getDB()`. It also supports a private `config/db.local.php` override so the app can use XAMPP locally and InfinityFree in deployment.

## Database and Queries

**Q: How does the system connect to the database?**  
A: Most pages call `getDB()` from `config/db.php`. That function creates one PDO connection using database constants such as host, database name, username, password, and charset.

**Q: Why do we use PDO prepared statements?**  
A: Prepared statements separate user input from SQL code, reducing SQL injection risk. Example: instead of inserting raw input into a query string, we bind values using placeholders like `:id`, `:username`, or `:date`.

**Q: Where are most SQL queries located?**  
A: Some queries are inside page files in `public/`, especially page-specific workflows. Reusable feature queries are placed in `modules/`, such as `modules/visits/visits_module.php` and `modules/guest_house/gh_bookings_module.php`.

**Q: What are the main database tables?**  
A:
- `users` stores system users.
- `offices` stores university offices.
- `guests` stores guest profiles.
- `guest_visits` stores visit records.
- `visit_destinations` stores office stops for each visit.
- `vehicle_entries` stores vehicle details.
- `restricted_guests` stores restriction history.
- `activity_logs` stores audit logs.
- `guest_house_rooms` stores Guest House rooms.
- `guest_house_bookings` stores Guest House bookings.
- `gh_room_types` stores room type records.

**Q: Why does `guest_visits` connect to `visit_destinations`?**  
A: A single visit can have one or more destination offices. `guest_visits` stores the main visit, while `visit_destinations` stores each office destination linked by `visit_id`.

**Q: Why is `guests` separate from `guest_visits`?**  
A: A guest can visit many times. Keeping guest identity in `guests` avoids duplicating personal information for every visit and allows us to track visit history.

## Example Module Flow

**Q: Give an example module and explain how it connects to public PHP files.**  
A: Example: `modules/visits/visits_module.php`.

This module contains reusable functions for visit workflows, such as creating walk-in visits, creating pre-registered visits, searching visits, checking in, checking out, and fetching visit details.

Example flow for a walk-in visit:
1. The user opens `public/visits/walkin.php`.
2. The page loads `config/db.php`, `includes/auth.php`, `includes/helpers.php`, and `modules/visits/visits_module.php`.
3. The page calls `requireRole([ROLE_GUARD, ROLE_ADMIN])`.
4. When the form is submitted, the page validates CSRF using `verifyCsrf()`.
5. The page reads form input such as guest name, purpose, office destination, and vehicle data.
6. The page inserts or reuses a guest record.
7. It creates a row in `guest_visits`.
8. It creates related rows in `visit_destinations`.
9. If vehicle data exists, it creates a row in `vehicle_entries`.
10. It logs the action in `activity_logs`.
11. It redirects to `public/visits/view.php?id=...`.

**Q: What is another example module?**  
A: `modules/guest_house/gh_bookings_module.php`.

It connects to:
- `public/guest_house/bookings.php`
- `public/guest_house/booking_create.php`
- `public/guest_house/booking_edit.php`
- `public/guest_house/booking_view.php`
- `public/guest_house/checkin.php`
- `public/guest_house/checkout.php`
- `public/dashboard/guest_house.php`

It handles Guest House booking creation, update, cancellation, check-in, check-out, room status syncing, and linked campus visit generation.

**Q: How does Guest House check-in work?**  
A:
1. Guest House staff opens a booking page.
2. The page calls a function like `ghCheckIn()`.
3. The function verifies the booking exists and is reserved.
4. It checks that a room is assigned.
5. It starts a database transaction.
6. It updates the booking status to `checked_in`.
7. It updates the room status to `occupied`.
8. It commits the transaction.
9. It writes an activity log.

## Authentication and Roles

**Q: What roles does the system have?**  
A:
- `admin`
- `guard`
- `office_staff`
- `guest_house_staff`

**Q: How does role-based access work?**  
A: Pages call `requireRole(...)`. If the current user's role is not allowed, the system logs the attempt and redirects to `unauthorized.php`.

**Q: What is the difference between `requireLogin()` and `requireRole()`?**  
A: `requireLogin()` only checks if the user is logged in. `requireRole()` checks login plus whether the user has the correct role.

**Q: How does login work?**  
A:
1. User enters username/email and password.
2. `attemptLogin()` searches active users.
3. `password_verify()` compares the password with the stored hash.
4. If correct, `createUserSession()` stores user data in the session.
5. The user is redirected to the dashboard for their role.

**Q: Are passwords stored as plain text?**  
A: No. Passwords are stored as hashes using PHP password hashing. Login uses `password_verify()` to validate them.

## Security Questions

**Q: How do we prevent SQL injection?**  
A: We use PDO prepared statements with placeholders for user input. Inputs are bound separately from the SQL string.

**Q: How do we prevent XSS?**  
A: We escape user-controlled output using `e()`, which uses `htmlspecialchars()`. We also sanitize flash messages so only safe formatting is allowed.

**Q: How do we prevent CSRF?**  
A: Forms include `csrfField()`, and POST requests call `verifyCsrf()` before making changes.

**Q: How do we prevent unauthorized URL access?**  
A: Protected pages call `requireLogin()` or `requireRole()`. Office staff also have ownership checks so they can only handle destinations for their assigned office.

**Q: Why did we add `.htaccess`?**  
A: To block direct browser access to internal folders and files such as `config/`, `includes/`, `modules/`, `database.sql`, backups, docs, and tools. This is important when uploading to hosting.

**Q: Why do we have `config/db.local.php` and `.gitignore`?**  
A: `config/db.local.php` contains private deployment database credentials. `.gitignore` prevents it from being committed to Git, so passwords do not go into the repository.

**Q: Can the app still run locally after adding InfinityFree credentials?**  
A: Yes. The private override detects localhost and returns empty settings, so local XAMPP still uses `localhost`, database `guest_system`, user `root`, and empty password.

## Visitor Workflow Questions

**Q: What is the difference between pre-registration and walk-in registration?**  
A: Pre-registration is usually submitted before arrival and starts as `pending`. Walk-in registration is created by guard/admin at the gate and can immediately become `checked_in`.

**Q: What happens during check-in?**  
A: The visit status changes to `checked_in`, actual check-in time is recorded, destinations become active for office handling, and activity logs are created.

**Q: What happens during check-out?**  
A: The visit status changes to `checked_out`, actual check-out time is recorded, active office destinations are completed if needed, and an activity log is created.

**Q: What is a QR token used for?**  
A: The QR token is a random lookup token for safer public status checking and faster staff lookup. It is less predictable than sequential reference numbers.

**Q: Why does public status checking require QR token instead of reference number?**  
A: Reference numbers can be sequential and easier to guess. QR tokens are random, which better protects visitor privacy.

## Office Workflow Questions

**Q: What does office staff do in the system?**  
A: Office staff can see visitors routed to their office, confirm arrival, mark office service as completed, and record unexpected visitors who were not originally routed to their office.

**Q: How does the system know which office a staff user belongs to?**  
A: The `users` table has an `office_id`. The session stores the user's office ID after login.

**Q: How are office destinations connected to visits?**  
A: The `visit_destinations` table has `visit_id` and `office_id`, connecting a visit to one or more offices.

**Q: Can office staff see all guest details?**  
A: Office staff can see visit information related to their office, but full guest directory access is limited to admin and guard roles.

## Admin Workflow Questions

**Q: What can admin manage?**  
A: Admin can manage users, offices, guests, restricted guests, reports, audit logs, visitor workflows, and Guest House functions.

**Q: What is the restricted guest feature?**  
A: It allows admin to flag a guest as restricted. Restricted guests are blocked from check-in unless the restriction is lifted.

**Q: Why do we keep restriction history?**  
A: It provides accountability. The system records who restricted the guest, the reason, and when the restriction was lifted.

**Q: What are activity logs used for?**  
A: They track important actions such as login, logout, check-in, check-out, restriction, office handling, and Guest House actions.

## Guest House Questions

**Q: Why is Guest House included in the same system?**  
A: Some visitors may also stay in the university Guest House. The module lets staff manage room inventory, expected guests, bookings, occupancy, and reports.

**Q: How do bookings connect to normal guest profiles?**  
A: Guest House bookings use the same `guests` table, so visitor identity is shared across campus visits and accommodations.

**Q: How does room availability work?**  
A: The system checks overlapping bookings for the same room. If a room already has a reserved or checked-in booking during the selected date range, the booking is blocked.

**Q: Why do we use a transaction during Guest House check-in/check-out?**  
A: Because booking status and room status must update together. If one update fails, the transaction rolls back so the database does not become inconsistent.

**Q: What is a linked campus visit from a Guest House booking?**  
A: If a Guest House guest also needs campus access, the system can generate a `guest_visits` record linked to the booking.

## Reports and Export Questions

**Q: What reports are available?**  
A: The system includes visit reports, date range summaries, office breakdowns, status breakdowns, guest logs, CSV exports, audit logs, and Guest House reports.

**Q: Why export to CSV?**  
A: CSV is simple, lightweight, and opens in Excel or Google Sheets. It is useful for reporting, printing, and administrative review.

**Q: Who can export reports?**  
A: Admin can export system reports. Guest personal data export is limited to admin and guard roles.

## Hosting and Deployment Questions

**Q: What changes were made for InfinityFree hosting?**  
A: The database config supports a private deployment override through `config/db.local.php`, and `.htaccess` protects internal folders and sensitive files.

**Q: What files should not be uploaded or exposed publicly?**  
A: `db_backups/`, `tools/`, development docs, `.vscode/`, and SQL backup files should not be publicly accessible. `.htaccess` blocks them, but it is cleaner not to upload unnecessary development files.

**Q: Why should database credentials not be committed to Git?**  
A: If credentials are committed, anyone with repo access can connect to the database. We keep credentials in `config/db.local.php` and ignore that file using `.gitignore`.

## Possible Defense Questions With Short Answers

**Q: Is this MVC?**  
A: It is not strict MVC, but it follows a practical separation: public pages act as controllers/views, while modules act like models for reusable database logic.

**Q: Why not put all code in one PHP file?**  
A: Splitting files improves maintainability, reuse, security, and readability. Shared logic like auth and database helpers can be used across many pages.

**Q: How do you know the system is secure?**  
A: It uses role-based access, password hashing, prepared statements, CSRF protection, output escaping, session hardening, audit logs, and `.htaccess` protection for internal files.

**Q: What is one limitation of the current system?**  
A: Some SQL is still inside page files instead of all being moved to modules. A future improvement would be to move more page-specific queries into module functions.

**Q: What would you improve next?**  
A: Add automated tests, improve offline support for CDN assets, add password reset functionality, add pagination for large records, and create environment-based config for production settings.

**Q: What happens if MySQL is down?**  
A: `getDB()` catches the PDO exception, logs the error, and shows a generic database connection failure message.

**Q: How does the system avoid duplicate form submission?**  
A: Most POST actions redirect after success or failure. This is the POST/Redirect/GET pattern.

**Q: Why do we store audit logs?**  
A: For accountability, troubleshooting, and administrative review. It helps answer who did what and when.

**Q: What makes this system ready for finals?**  
A: The main workflows work end-to-end, PHP files pass syntax checks, database connection works, required tables exist, access control and CSRF are present, and sensitive files are protected for hosting.

## Suggested Explanation Script

When explaining a feature, use this pattern:

1. "The user opens a page from `public/`."
2. "The page loads config, auth, helpers, and sometimes a module."
3. "The page checks the user's role."
4. "If it is a form, it verifies CSRF."
5. "It reads validated input."
6. "It calls a module function or prepared SQL query."
7. "The database changes are saved."
8. "An activity log is created if the action is important."
9. "The system redirects or displays the updated page."

Example:

"For check-in, the guard opens `public/visits/checkin.php`. The page requires guard or admin role, validates the CSRF token, finds the pending visit using prepared SQL, updates `guest_visits` to `checked_in`, records the actual check-in time, logs the action in `activity_logs`, and redirects to the visit detail page."
