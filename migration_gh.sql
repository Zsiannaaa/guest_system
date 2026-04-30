-- ============================================================
-- Migration: Add Guest House module to EXISTING guest_system DB
-- Run this once on databases that were already imported from the
-- original database.sql before the Guest House module existed.
-- Safe to run multiple times (uses IF NOT EXISTS where possible).
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+08:00";

-- 1) Extend users.role ENUM to include guest_house_staff
ALTER TABLE `users`
  MODIFY `role` ENUM('admin','guard','office_staff','guest_house_staff') NOT NULL;

-- 2) Extend activity_logs.action_type ENUM with gh_* values
ALTER TABLE `activity_logs`
  MODIFY `action_type` ENUM(
    'pre_registration',
    'walk_in_registration',
    'check_in',
    'check_out',
    'destination_added',
    'destination_confirmed',
    'destination_completed',
    'destination_cancelled',
    'unplanned_destination_added',
    'guest_transferred',
    'visit_cancelled',
    'guest_restricted',
    'user_login',
    'user_logout',
    'user_created',
    'user_updated',
    'office_created',
    'office_updated',
    'gh_room_created',
    'gh_room_updated',
    'gh_booking_created',
    'gh_booking_updated',
    'gh_booking_cancelled',
    'gh_checked_in',
    'gh_checked_out',
    'gh_visit_generated',
    'other'
  ) NOT NULL;

-- 3) Create Guest House tables
CREATE TABLE IF NOT EXISTS `gh_room_types` (
  `type_id` INT(11) NOT NULL AUTO_INCREMENT,
  `type_name` VARCHAR(80) NOT NULL UNIQUE,
  `default_capacity` TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,
  `description` TEXT DEFAULT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `guest_house_rooms` (
  `room_id` INT(11) NOT NULL AUTO_INCREMENT,
  `room_number` VARCHAR(20) NOT NULL UNIQUE,
  `type_id` INT(11) NOT NULL,
  `capacity` TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,
  `floor` VARCHAR(20) DEFAULT NULL,
  `location_note` VARCHAR(200) DEFAULT NULL,
  `status` ENUM('available','occupied','maintenance','inactive') NOT NULL DEFAULT 'available',
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`room_id`),
  FOREIGN KEY (`type_id`) REFERENCES `gh_room_types`(`type_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX `idx_gh_room_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `guest_house_bookings` (
  `booking_id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking_reference` VARCHAR(30) NOT NULL UNIQUE,
  `guest_id` INT(11) NOT NULL,
  `room_id` INT(11) DEFAULT NULL,
  `check_in_date` DATE NOT NULL,
  `check_out_date` DATE NOT NULL,
  `actual_check_in` DATETIME DEFAULT NULL,
  `actual_check_out` DATETIME DEFAULT NULL,
  `purpose_of_stay` TEXT NOT NULL,
  `sponsoring_office_id` INT(11) DEFAULT NULL,
  `external_sponsor` VARCHAR(200) DEFAULT NULL,
  `number_of_guests` TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,
  `status` ENUM('reserved','checked_in','occupied','checked_out','cancelled','no_show') NOT NULL DEFAULT 'reserved',
  `linked_visit_id` INT(11) DEFAULT NULL,
  `created_by_user_id` INT(11) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`booking_id`),
  FOREIGN KEY (`guest_id`)             REFERENCES `guests`(`guest_id`)         ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (`room_id`)              REFERENCES `guest_house_rooms`(`room_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (`sponsoring_office_id`) REFERENCES `offices`(`office_id`)       ON DELETE SET NULL ON UPDATE CASCADE,
  FOREIGN KEY (`linked_visit_id`)      REFERENCES `guest_visits`(`visit_id`)   ON DELETE SET NULL ON UPDATE CASCADE,
  FOREIGN KEY (`created_by_user_id`)   REFERENCES `users`(`user_id`)           ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX `idx_gh_bk_room_dates` (`room_id`, `check_in_date`, `check_out_date`),
  INDEX `idx_gh_bk_status`     (`status`),
  INDEX `idx_gh_bk_guest`      (`guest_id`),
  INDEX `idx_gh_bk_ref`        (`booking_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gh_companions` (
  `companion_id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking_id` INT(11) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `relationship` VARCHAR(100) DEFAULT NULL,
  `contact_number` VARCHAR(20) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`companion_id`),
  FOREIGN KEY (`booking_id`) REFERENCES `guest_house_bookings`(`booking_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_gh_comp_booking` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Seed default room types (only if table is empty)
INSERT INTO `gh_room_types` (`type_name`, `default_capacity`, `description`, `status`)
SELECT * FROM (
  SELECT 'Single'    AS type_name, 1 AS default_capacity, 'Single-occupant room with one bed.'          AS description, 'active' AS status UNION ALL
  SELECT 'Double',    2, 'Two-person room with double or twin beds.',            'active' UNION ALL
  SELECT 'Suite',     2, 'Premium suite for VIP / visiting officials.',          'active' UNION ALL
  SELECT 'Dormitory', 6, 'Shared room for group accommodation.',                 'active'
) AS defaults
WHERE NOT EXISTS (SELECT 1 FROM `gh_room_types` LIMIT 1);

-- 5) Seed starter rooms (only if the room table is empty)
INSERT INTO `guest_house_rooms` (`room_number`, `type_id`, `capacity`, `floor`, `location_note`, `status`)
SELECT * FROM (
  SELECT 'GH-101' AS room_number, (SELECT `type_id` FROM `gh_room_types` WHERE `type_name` = 'Single' LIMIT 1) AS type_id, 1 AS capacity, '1' AS floor, 'Ground Floor, East Wing' AS location_note, 'available' AS status UNION ALL
  SELECT 'GH-102', (SELECT `type_id` FROM `gh_room_types` WHERE `type_name` = 'Double' LIMIT 1), 2, '1', 'Ground Floor, East Wing', 'available' UNION ALL
  SELECT 'GH-201', (SELECT `type_id` FROM `gh_room_types` WHERE `type_name` = 'Suite' LIMIT 1), 2, '2', '2nd Floor, VIP Suite', 'available' UNION ALL
  SELECT 'GH-202', (SELECT `type_id` FROM `gh_room_types` WHERE `type_name` = 'Double' LIMIT 1), 2, '2', '2nd Floor', 'available' UNION ALL
  SELECT 'GH-301', (SELECT `type_id` FROM `gh_room_types` WHERE `type_name` = 'Dormitory' LIMIT 1), 6, '3', '3rd Floor, Group Dorm', 'available'
) AS defaults
WHERE `type_id` IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `guest_house_rooms` LIMIT 1);

-- 6) Seed a guest_house_staff user if none exists (password: Password@123)
INSERT INTO `users` (`full_name`, `email`, `username`, `password_hash`, `role`, `office_id`, `status`)
SELECT 'Guest House Staff', 'gh_staff@university.edu', 'gh_staff',
       '$2y$10$js.Wo3wgCRsF7osOsvcen.twq4P1knchqbFrXG9o/zy4xeyvQRyIu',
       'guest_house_staff', NULL, 'active'
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `username` = 'gh_staff');

COMMIT;
