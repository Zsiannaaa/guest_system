# University Guest Monitoring System

A PHP + MySQL web application for managing campus guests and visits at St. Paul University Dumaguete.

## What this project does

This system supports the full visitor flow:

- Public pre-registration before arriving on campus.
- Guard/staff login and role-based dashboards.
- Guest check-in and check-out workflows.
- Office destination handling (arrived, in service, completed).
- Guest directory and restricted guest handling.
- Reports and audit logs.

## Project structure

```text
guest_system/
├── config/           # App constants and DB connection
├── includes/         # Shared auth/session/helpers/layout includes
├── modules/          # Data-access/model functions by domain
├── public/           # Route-like PHP pages (controllers + views)
├── assets/           # CSS, JS, images
├── database.sql      # Schema + seed data
└── index.php         # Public landing page
```

### Key directories

- `config/`
  - `constants.php` defines app URL, roles, statuses, and session timeout.
  - `db.php` provides the shared PDO connection via `getDB()`.
- `includes/`
  - `auth.php` handles login/session, RBAC (`requireRole`), and activity logging.
  - `helpers.php` provides CSRF helpers, sanitization, flash messages, formatting helpers.
  - `header.php` and `footer.php` provide the shared app layout.
- `modules/`
  - Domain model functions (`visits_module.php`, `reports_module.php`, etc.).
- `public/`
  - Feature pages grouped by area (`auth`, `dashboard`, `visits`, `office`, `admin`, `reports`, `guests`).

## Roles and access

The app has three internal roles:

- `admin`
- `guard`
- `office_staff`

Role checks are enforced in page controllers using `requireRole(...)` and the shared auth helpers.

## Database overview

Main entities:

- `users` – internal users only (admin/guard/office staff)
- `guests` – reusable guest identity records
- `guest_visits` – one row per visit session
- `visit_destinations` – one or more office stops per visit
- `vehicle_entries` – optional vehicle details per visit
- `activity_logs` – audit trail of system actions

Load schema and seed data from `database.sql`.

## Local setup (XAMPP-style)

1. Clone/copy this repo into your web root (example: `htdocs/guest_system`).
2. Create a MySQL database named `guest_system`.
3. Import `database.sql`.
4. Update DB credentials in `config/db.php` if needed.
5. Verify `APP_URL` in `config/constants.php` matches your local URL.
6. Open the app in browser:
   - `http://localhost/guest_system`

## Default seeded credentials

From `database.sql` seed data:

- Admin: `admin`
- Guard: `guard1`, `guard2`
- Office staff: `staff_reg`, `staff_fin`, `staff_hr`, `staff_admn`
- Seed password for all users: `Password@123`

> Change seeded credentials before production use.

## Security notes

- CSRF protection is implemented via `csrfField()` + `verifyCsrf()`.
- Sessions use HTTP-only cookies and strict mode.
- Access control uses role guards and office ownership checks.
- Activity logging records major actions in `activity_logs`.

## New contributor learning path

1. Start with `database.sql` to understand entities and relationships.
2. Read `includes/auth.php` and `includes/helpers.php`.
3. Trace one end-to-end flow (recommended: `public/visits/checkin.php`).
4. Compare page logic with model functions in `modules/visits_module.php`.
5. Explore role-specific dashboards under `public/dashboard/`.

## Notes about architecture

This codebase follows a pragmatic, feature-first PHP structure:

- Many pages are controller + view in the same file.
- `modules/` provides reusable query logic, but some pages still contain inline SQL for feature-specific workflows.

