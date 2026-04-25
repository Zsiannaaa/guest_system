<?php
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
define('ROLE_ADMIN',        'admin');
define('ROLE_GUARD',        'guard');
define('ROLE_OFFICE_STAFF', 'office_staff');

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
