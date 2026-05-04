<?php
/**
 * STUDY NOTES FOR REVIEW
 * Purpose: Defines application-wide constants such as URLs, roles, statuses, and timeout values.
 * Flow: Loaded first by other PHP files; it does not render a page by itself.
 * Security: Keep access checks in the calling page and escape user-controlled output before displaying it.
 */
/**
 * config/constants.php
 * Application-wide constants
 */

define('APP_NAME',    'University Guest Monitoring System');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'http://localhost/guest_system');

// Session timeout in seconds (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Roles
define('ROLE_ADMIN',              'admin');
define('ROLE_GUARD',              'guard');
define('ROLE_OFFICE_STAFF',       'office_staff');
define('ROLE_GUEST_HOUSE_STAFF',  'guest_house_staff');

// Visit statuses
define('STATUS_PENDING',     'pending');
define('STATUS_CHECKED_IN',  'checked_in');
define('STATUS_CHECKED_OUT', 'checked_out');
define('STATUS_CANCELLED',   'cancelled');
define('STATUS_OVERSTAYED',  'overstayed');

// Destination statuses
define('DEST_PENDING',    'pending');
define('DEST_ARRIVED',    'arrived');
define('DEST_IN_SERVICE', 'in_service');
define('DEST_COMPLETED',  'completed');
define('DEST_CANCELLED',  'cancelled');

// Registration types
define('REG_PRE',    'pre_registered');
define('REG_WALKIN', 'walk_in');

// Visit reference prefix
define('VISIT_REF_PREFIX', 'GST');

// ─── Guest House ────────────────────────────────────────────
define('GH_STATUS_RESERVED',    'reserved');
define('GH_STATUS_CHECKED_IN',  'checked_in');
define('GH_STATUS_OCCUPIED',    'occupied');
define('GH_STATUS_CHECKED_OUT', 'checked_out');
define('GH_STATUS_CANCELLED',   'cancelled');
define('GH_STATUS_NO_SHOW',     'no_show');

define('GH_ROOM_AVAILABLE',   'available');
define('GH_ROOM_OCCUPIED',    'occupied');
define('GH_ROOM_MAINTENANCE', 'maintenance');
define('GH_ROOM_INACTIVE',    'inactive');

define('GH_BOOKING_REF_PREFIX', 'GH');
