<img width="620" height="134" alt="readimg" src="https://github.com/user-attachments/assets/6073c7dc-bbfc-47c6-af76-1d0f8c696f6e" />


# University Guest Monitoring System

A PHP + MySQL web application for managing campus guests, visits, and Guest House accommodations at St. Paul University Dumaguete.

## What This Project Does

This system supports the main visitor flow:

- Public pre-registration before arriving on campus.
- Guard/staff login and role-based dashboards.
- Guest check-in and check-out workflows.
- Office destination handling.
- Guest directory and restricted guest handling.
- Vehicle entry recording.
- Guest House expected guest and room management.
- Reports and audit logs.
<img width="1919" height="979" alt="Screenshot 2026-05-03 201047" src="https://github.com/user-attachments/assets/c4ddeef1-5cf5-458d-a238-3991f92cd63a" />

## Project Structure

```text
guest_system/
|-- config/           # App constants and DB connection
|-- includes/         # Shared auth/session/helpers/layout includes
|-- modules/          # Data-access/model functions grouped by feature
|-- public/           # Browser-accessible pages/controllers/views
|-- assets/           # CSS, JS, images
|-- database.sql      # Full schema + seed data for fresh setup
|-- migration_gh.sql  # Upgrade script for old databases before Guest House
`-- index.php         # Public landing page
```

## Key Directories

- `config/`
  - `constants.php` defines app URL, roles, statuses, and session timeout.
  - `db.php` provides the shared PDO connection via `getDB()`.
- `includes/`
  - `auth.php` handles login/session, RBAC (`requireRole`), no-cache headers, and activity logging.
  - `helpers.php` provides CSRF helpers, sanitization, flash messages, and formatting helpers.
  - `header.php` and `footer.php` provide the shared app layout.
- `modules/`
  - `admin/` contains user and office model functions.
  - `dashboard/` contains dashboard query functions.
  - `guests/` contains guest directory and restriction logic.
  - `guest_house/` contains Guest House booking, room, and report logic.
  - `reports/` contains general report logic.
  - `visits/` contains visit and office destination logic.
- `public/`
  - Feature pages grouped by area: `auth`, `dashboard`, `visits`, `office`, `admin`, `reports`, `guests`, and `guest_house`.

## Roles And Access

The app has four internal roles:

- `admin`
- `guard`
- `office_staff`
- `guest_house_staff`

Role checks are enforced in page controllers using `requireRole(...)` and the shared auth helpers.

## Database Overview

Main entities:

- `users` - internal users only.
- `guests` - reusable guest identity records.
- `guest_visits` - one row per visit session.
- `visit_destinations` - one or more office stops per visit.
- `vehicle_entries` - optional vehicle details per visit.
- `guest_house_rooms` - Guest House room inventory.
- `guest_house_bookings` - expected Guest House stays.
- `activity_logs` - audit trail of system actions.

For a fresh setup, import `database.sql`.

For an existing old setup that does not have the Guest House tables yet, back up the database first, then run `migration_gh.sql`.

## Local Setup

1. Clone/copy this repo into your web root, for example `htdocs/guest_system`.
2. Create a MySQL database named `guest_system`.
3. Import `database.sql`.
4. Update DB credentials in `config/db.php` if needed.
5. Verify `APP_URL` in `config/constants.php` matches your local URL.
6. Open `http://localhost/guest_system`.

## Default Seeded Credentials

From `database.sql` seed data:

- Admin: `admin`
- Guard: `guard1`, `guard2`
- Office staff: `staff_reg`, `staff_fin`, `staff_hr`, `staff_admn`
- Guest House staff: `ghstaff`
- Seed password for all users: `Password@123`

Change seeded credentials before production use.

## Security Notes

- CSRF protection is implemented via `csrfField()` and `verifyCsrf()`.
- Sessions use HTTP-only cookies and strict mode.
- Authenticated pages send no-cache headers to reduce protected-page browser caching after logout.
- Access control uses role guards and office ownership checks.
- Activity logging records major actions in `activity_logs`.

## New Contributor Learning Path

1. Start with `database.sql` to understand entities and relationships.
2. Read `includes/auth.php` and `includes/helpers.php`.
3. Trace one end-to-end flow, such as `public/visits/checkin.php`.
4. Compare page logic with model functions in `modules/visits/visits_module.php`.
5. Explore role-specific dashboards under `public/dashboard/`.

## Notes About Architecture

This codebase follows a pragmatic, feature-first PHP structure:

- `public/` contains browser-accessible pages. These pages act as controllers and views.
- `modules/` contains reusable model/query logic grouped by feature.
- Some pages still contain inline SQL for page-specific workflows. Future cleanup can move those queries into the matching module folder.
