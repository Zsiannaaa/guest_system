-- ============================================================
-- University Guest Monitoring & Visitor Management System
-- Database: guest_system
-- Compatible with: MySQL 5.7+ / phpMyAdmin / XAMPP
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+08:00";

-- ============================================================
-- TABLE: offices
-- University offices/departments used as visit destinations
-- ============================================================
CREATE TABLE IF NOT EXISTS `offices` (
  `office_id` INT(11) NOT NULL AUTO_INCREMENT,
  `office_name` VARCHAR(150) NOT NULL,
  `office_code` VARCHAR(30) NOT NULL UNIQUE,
  `office_location` VARCHAR(200) DEFAULT NULL,         -- e.g. "Building A, 2nd Floor"
  `requires_arrival_confirmation` TINYINT(1) NOT NULL DEFAULT 0, -- 1 = yes, 0 = no (optional)
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`office_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: users
-- Internal system users only (admin, guard, office staff)
-- Guests do NOT have accounts here
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT(11) NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `username` VARCHAR(80) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','guard','office_staff','guest_house_staff') NOT NULL,
  `office_id` INT(11) DEFAULT NULL,                    -- nullable for admin and guard
  `status` ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `last_login` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  FOREIGN KEY (`office_id`) REFERENCES `offices`(`office_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: guests
-- Reusable guest personal records (identity-level data)
-- Each guest can have many visits across time
-- ============================================================
CREATE TABLE IF NOT EXISTS `guests` (
  `guest_id` INT(11) NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(150) NOT NULL,
  `contact_number` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `organization` VARCHAR(150) DEFAULT NULL,            -- company/institution guest is from
  `address` TEXT DEFAULT NULL,
  `id_type` VARCHAR(80) DEFAULT NULL,                  -- e.g. "Driver's License", "Passport"
  `id_number_masked` VARCHAR(50) DEFAULT NULL,         -- deprecated; ID numbers are verified at gate, not stored
  `photo_filename` VARCHAR(255) DEFAULT NULL,          -- optional uploaded guest photo
  `is_restricted` TINYINT(1) NOT NULL DEFAULT 0,       -- 1 = flagged/restricted
  `restriction_reason` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`guest_id`),
  INDEX `idx_guest_name` (`full_name`),
  INDEX `idx_guest_contact` (`contact_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: guest_visits
-- One record per visit session
-- Core table linking guest → visit → destinations
-- ============================================================
CREATE TABLE IF NOT EXISTS `guest_visits` (
  `visit_id` INT(11) NOT NULL AUTO_INCREMENT,
  `guest_id` INT(11) NOT NULL,
  `visit_reference` VARCHAR(30) NOT NULL UNIQUE,       -- e.g. "GST-20250418-0001"
  `qr_token` VARCHAR(100) DEFAULT NULL UNIQUE,         -- optional QR code token
  `registration_type` ENUM('pre_registered','walk_in') NOT NULL DEFAULT 'walk_in',
  `purpose_of_visit` TEXT NOT NULL,
  `visit_date` DATE NOT NULL,                          -- the scheduled/actual visit date
  `expected_time_in` TIME DEFAULT NULL,                -- for pre-registered guests
  `expected_time_out` TIME DEFAULT NULL,               -- for pre-registered guests
  `actual_check_in` DATETIME DEFAULT NULL,             -- when guard checks them in at gate
  `actual_check_out` DATETIME DEFAULT NULL,            -- when guard checks them out at exit
  `overall_status` ENUM('pending','checked_in','checked_out','cancelled','overstayed') NOT NULL DEFAULT 'pending',
  `has_vehicle` TINYINT(1) NOT NULL DEFAULT 0,
  `pass_number` VARCHAR(50) DEFAULT NULL,              -- physical pass/badge number if issued
  `processed_by_guard_id` INT(11) DEFAULT NULL,        -- guard who checked in/out
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`visit_id`),
  FOREIGN KEY (`guest_id`) REFERENCES `guests`(`guest_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (`processed_by_guard_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX `idx_visit_ref` (`visit_reference`),
  INDEX `idx_visit_qr` (`qr_token`),
  INDEX `idx_visit_date` (`visit_date`),
  INDEX `idx_visit_status` (`overall_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: visit_destinations
-- One or more office destinations per visit
-- Supports multi-office visits and unplanned transfers
-- ============================================================
CREATE TABLE IF NOT EXISTS `visit_destinations` (
  `destination_id` INT(11) NOT NULL AUTO_INCREMENT,
  `visit_id` INT(11) NOT NULL,
  `office_id` INT(11) NOT NULL,
  `sequence_no` INT(3) NOT NULL DEFAULT 1,             -- order of office stops
  `destination_status` ENUM('pending','arrived','in_service','completed','cancelled') NOT NULL DEFAULT 'pending',
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,          -- 1 = original intended office
  `is_unplanned` TINYINT(1) NOT NULL DEFAULT 0,        -- 1 = added on-the-fly (not in original plan)
  `received_by_user_id` INT(11) DEFAULT NULL,          -- office staff who confirmed arrival
  `arrival_time` DATETIME DEFAULT NULL,                -- when guest arrived at this office
  `completed_time` DATETIME DEFAULT NULL,              -- when office finished with guest
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`destination_id`),
  FOREIGN KEY (`visit_id`) REFERENCES `guest_visits`(`visit_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`office_id`) REFERENCES `offices`(`office_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (`received_by_user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX `idx_dest_visit` (`visit_id`),
  INDEX `idx_dest_office` (`office_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: vehicle_entries
-- Vehicle info linked to a guest visit (optional)
-- ============================================================
CREATE TABLE IF NOT EXISTS `vehicle_entries` (
  `vehicle_id` INT(11) NOT NULL AUTO_INCREMENT,
  `visit_id` INT(11) NOT NULL UNIQUE,                  -- one vehicle per visit record
  `vehicle_type` ENUM('car','motorcycle','van','truck','other') NOT NULL DEFAULT 'car',
  `plate_number` VARCHAR(20) NOT NULL,
  `has_university_sticker` TINYINT(1) NOT NULL DEFAULT 0, -- 1 = university sticker/pass was seen
  `sticker_number` VARCHAR(50) DEFAULT NULL,           -- university-issued sticker if applicable
  `vehicle_color` VARCHAR(50) DEFAULT NULL,
  `vehicle_model` VARCHAR(100) DEFAULT NULL,
  `driver_name` VARCHAR(150) DEFAULT NULL,             -- if driver is different from guest
  `is_driver_the_guest` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`vehicle_id`),
  FOREIGN KEY (`visit_id`) REFERENCES `guest_visits`(`visit_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_vehicle_plate` (`plate_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: activity_logs
-- Audit trail for all important actions in the system
-- ============================================================
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `log_id` INT(11) NOT NULL AUTO_INCREMENT,
  `visit_id` INT(11) DEFAULT NULL,                     -- linked visit (nullable for non-visit actions)
  `action_type` ENUM(
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
  ) NOT NULL,
  `performed_by_user_id` INT(11) DEFAULT NULL,
  `office_id` INT(11) DEFAULT NULL,                    -- nullable (not all actions tied to office)
  `description` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `logged_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  FOREIGN KEY (`visit_id`) REFERENCES `guest_visits`(`visit_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  FOREIGN KEY (`performed_by_user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX `idx_log_visit` (`visit_id`),
  INDEX `idx_log_action` (`action_type`),
  INDEX `idx_log_time` (`logged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: restricted_guests
-- Optional: blacklisted or flagged guest records
-- ============================================================
CREATE TABLE IF NOT EXISTS `restricted_guests` (
  `restriction_id` INT(11) NOT NULL AUTO_INCREMENT,
  `guest_id` INT(11) NOT NULL,
  `reason` TEXT NOT NULL,
  `restricted_by_user_id` INT(11) DEFAULT NULL,
  `restricted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lifted_at` DATETIME DEFAULT NULL,
  `lifted_by_user_id` INT(11) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`restriction_id`),
  FOREIGN KEY (`guest_id`) REFERENCES `guests`(`guest_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`restricted_by_user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
  FOREIGN KEY (`lifted_by_user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: notifications (placeholder for future use)
-- ============================================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,                      -- target user (null = broadcast)
  `visit_id` INT(11) DEFAULT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info','alert','warning','success') NOT NULL DEFAULT 'info',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`visit_id`) REFERENCES `guest_visits`(`visit_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- SEED DATA: Offices
-- ============================================================
INSERT INTO `offices` (`office_name`, `office_code`, `office_location`, `requires_arrival_confirmation`, `status`) VALUES
('Registrar Office',          'REG',      'Admin Building, Ground Floor', 1, 'active'),
('Finance Office',            'FIN',      'Admin Building, 2nd Floor',    1, 'active'),
('Guidance and Counseling',   'GUID',     'Student Services Building',    0, 'active'),
('Human Resources',           'HR',       'Admin Building, 3rd Floor',    1, 'active'),
('Office of the President',   'PRES',     'Main Building, 4th Floor',     1, 'active'),
('IT Department',             'IT',       'Tech Building, Ground Floor',  0, 'active'),
('Library',                   'LIB',      'Main Building, 1st Floor',     0, 'active'),
('Security and Safety Office','SSO',      'Gate House',                   0, 'active'),
('Admissions Office',         'ADMN',     'Admin Building, Ground Floor', 1, 'active'),
('Accounting Office',         'ACCTG',    'Admin Building, 2nd Floor',    1, 'active');


-- ============================================================
-- SEED DATA: Users (passwords are hashed with password_hash())
-- All passwords are: Password@123
-- ============================================================
INSERT INTO `users` (`full_name`, `email`, `username`, `password_hash`, `role`, `office_id`, `status`) VALUES
-- Admin
('System Administrator',   'admin@university.edu',    'admin',
 '$2y$10$js.Wo3wgCRsF7osOsvcen.twq4P1knchqbFrXG9o/zy4xeyvQRyIu', 'admin', NULL, 'active'),

-- Guards
('Juan dela Cruz',         'guard1@university.edu',   'guard1',
 '$2y$10$js.Wo3wgCRsF7osOsvcen.twq4P1knchqbFrXG9o/zy4xeyvQRyIu', 'guard', NULL, 'active'),
('Maria Santos',           'guard2@university.edu',   'guard2',
 '$2y$10$js.Wo3wgCRsF7osOsvcen.twq4P1knchqbFrXG9o/zy4xeyvQRyIu', 'guard', NULL, 'active'),

-- Office Staff - Registrar
('Ana Reyes',              'registrar@university.edu','staff_reg',
 '$2y$10$js.Wo3wgCRsF7osOsvcen.twq4P1knchqbFrXG9o/zy4xeyvQRyIu', 'office_staff', 1, 'active'),

-- Office Staff - Finance
('Pedro Garcia',           'finance@university.edu',  'staff_fin',
 '$2y$10$js.Wo3wgCRsF7osOsvcen.twq4P1knchqbFrXG9o/zy4xeyvQRyIu', 'office_staff', 2, 'active'),

-- Office Staff - HR
('Rosa Mendoza',           'hr@university.edu',       'staff_hr',
 '$2y$10$js.Wo3wgCRsF7osOsvcen.twq4P1knchqbFrXG9o/zy4xeyvQRyIu', 'office_staff', 4, 'active'),

-- Office Staff - Admissions
('Carlo Bautista',         'admissions@university.edu','staff_admn',
 '$2y$10$js.Wo3wgCRsF7osOsvcen.twq4P1knchqbFrXG9o/zy4xeyvQRyIu', 'office_staff', 9, 'active');


-- ============================================================
-- SEED DATA: Guests
-- ============================================================
INSERT INTO `guests` (`full_name`, `contact_number`, `email`, `organization`, `id_type`) VALUES
('Roberto Alcantara',  '09171234567', 'roberto@gmail.com',     'BPI Bank',         'Government ID'),
('Liza Villarreal',    '09281234567', 'liza.v@yahoo.com',      'DepEd Manila',     "Driver's License"),
('James Ong',          '09391234567', 'james.ong@corp.com',    'TechCorp PH',      'Passport'),
('Carmen Torres',      '09451234567', NULL,                    'Walk-in',          'SSS ID'),
('Felix Ramos',        '09561234567', 'felix.r@email.com',     'University of PH', 'School ID');


-- ============================================================
-- SEED DATA: Guest Visits
-- ============================================================
INSERT INTO `guest_visits` (
  `guest_id`, `visit_reference`, `qr_token`, `registration_type`,
  `purpose_of_visit`, `visit_date`, `expected_time_in`, `expected_time_out`,
  `actual_check_in`, `actual_check_out`, `overall_status`, `has_vehicle`,
  `processed_by_guard_id`, `notes`
) VALUES
-- Visit 1: Pre-registered, currently inside
(1, 'GST-20260418-0001', 'QR-ABCDEF001', 'pre_registered',
 'Business meeting with HR regarding partnership',
 '2026-04-18', '09:00:00', '11:00:00',
 '2026-04-18 09:15:00', NULL, 'checked_in', 0, 2, NULL),

-- Visit 2: Walk-in, currently inside, has vehicle
(2, 'GST-20260418-0002', 'QR-ABCDEF002', 'walk_in',
 'Submitting scholarship documents to Registrar and Finance',
 '2026-04-18', NULL, NULL,
 '2026-04-18 10:00:00', NULL, 'checked_in', 1, 2, 'Rush visit'),

-- Visit 3: Checked out
(3, 'GST-20260418-0003', 'QR-ABCDEF003', 'pre_registered',
 'IT consultation and system demonstration',
 '2026-04-18', '08:00:00', '10:00:00',
 '2026-04-18 08:10:00', '2026-04-18 10:30:00', 'checked_out', 0, 2, NULL),

-- Visit 4: Pending (pre-registered, hasn't arrived yet)
(4, 'GST-20260418-0004', NULL, 'pre_registered',
 'Meeting with Office of the President',
 '2026-04-18', '14:00:00', '15:00:00',
 NULL, NULL, 'pending', 0, NULL, NULL),

-- Visit 5: Walk-in
(5, 'GST-20260418-0005', 'QR-ABCDEF005', 'walk_in',
 'Research collaboration with Guidance',
 '2026-04-18', NULL, NULL,
 '2026-04-18 11:30:00', NULL, 'checked_in', 1, 3, NULL);


-- ============================================================
-- SEED DATA: Visit Destinations
-- ============================================================
INSERT INTO `visit_destinations` (`visit_id`, `office_id`, `sequence_no`, `destination_status`, `is_primary`, `is_unplanned`, `arrival_time`) VALUES
-- Visit 1 → HR
(1, 4, 1, 'in_service', 1, 0, '2026-04-18 09:20:00'),

-- Visit 2 → Registrar then Finance
(2, 1, 1, 'completed',  1, 0, '2026-04-18 10:10:00'),
(2, 2, 2, 'arrived',    0, 0, '2026-04-18 10:45:00'),

-- Visit 3 → IT (completed)
(3, 6, 1, 'completed',  1, 0, '2026-04-18 08:15:00'),

-- Visit 4 → President's Office (pending)
(4, 5, 1, 'pending',    1, 0, NULL),

-- Visit 5 → Guidance then Library (library is unplanned)
(5, 3, 1, 'in_service', 1, 0, '2026-04-18 11:40:00'),
(5, 7, 2, 'pending',    0, 1, NULL);


-- ============================================================
-- SEED DATA: Vehicle Entries
-- ============================================================
INSERT INTO `vehicle_entries` (`visit_id`, `vehicle_type`, `plate_number`, `vehicle_color`, `vehicle_model`, `driver_name`, `is_driver_the_guest`) VALUES
(2, 'car',        'ABC 1234', 'White', 'Toyota Vios',  'Liza Villarreal', 1),
(5, 'motorcycle', 'DEF 5678', 'Black', 'Honda Wave',   'Felix Ramos',     1);


-- ============================================================
-- SEED DATA: Activity Logs (sample trail)
-- ============================================================
INSERT INTO `activity_logs` (`visit_id`, `action_type`, `performed_by_user_id`, `office_id`, `description`, `ip_address`) VALUES
(1, 'pre_registration',     1,    NULL, 'Guest Roberto Alcantara pre-registered for visit GST-20260418-0001', '127.0.0.1'),
(1, 'check_in',             2,    NULL, 'Guard checked in Roberto Alcantara at gate',                         '127.0.0.1'),
(1, 'destination_confirmed',4,    4,    'HR staff confirmed arrival of Roberto Alcantara',                    '127.0.0.1'),
(2, 'walk_in_registration', 2,    NULL, 'Walk-in guest Liza Villarreal registered',                          '127.0.0.1'),
(2, 'check_in',             2,    NULL, 'Guard checked in Liza Villarreal at gate',                          '127.0.0.1'),
(3, 'pre_registration',     1,    NULL, 'Guest James Ong pre-registered for visit GST-20260418-0003',        '127.0.0.1'),
(3, 'check_in',             2,    NULL, 'Guard checked in James Ong at gate',                                '127.0.0.1'),
(3, 'check_out',            2,    NULL, 'Guard checked out James Ong at exit gate',                          '127.0.0.1'),
(5, 'walk_in_registration', 3,    NULL, 'Walk-in guest Felix Ramos registered',                              '127.0.0.1'),
(5, 'unplanned_destination_added', 4, 7, 'Office staff added Library as unplanned destination for Felix Ramos', '127.0.0.1');


-- ============================================================
-- ============================================================
-- PHASE 2: GUEST HOUSE ACCOMMODATION MODULE
-- ============================================================
-- ============================================================

-- ------------------------------------------------------------
-- TABLE: gh_room_types
-- Configurable lookup for room types (Single, Double, Suite...)
-- ------------------------------------------------------------
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

-- ------------------------------------------------------------
-- TABLE: guest_house_rooms
-- Physical rooms in the guest house
-- ------------------------------------------------------------
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

-- ------------------------------------------------------------
-- TABLE: guest_house_bookings
-- One record per reservation/stay
-- ------------------------------------------------------------
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

-- ------------------------------------------------------------
-- TABLE: gh_companions (scaffold; no UI in Phase 2a)
-- ------------------------------------------------------------
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


-- ============================================================
-- SEED DATA: Guest House
-- ============================================================

INSERT INTO `gh_room_types` (`type_name`, `default_capacity`, `description`, `status`) VALUES
('Single',    1, 'Single-occupant room with one bed.',                  'active'),
('Double',    2, 'Two-person room with double or twin beds.',            'active'),
('Suite',     2, 'Premium suite for VIP / visiting officials.',         'active'),
('Dormitory', 6, 'Shared room for group accommodation.',                'active');

INSERT INTO `guest_house_rooms` (`room_number`, `type_id`, `capacity`, `floor`, `location_note`, `status`) VALUES
('GH-101', 1, 1, '1', 'Ground Floor, East Wing',  'available'),
('GH-102', 2, 2, '1', 'Ground Floor, East Wing',  'available'),
('GH-201', 3, 2, '2', '2nd Floor, VIP Suite',     'available'),
('GH-202', 2, 2, '2', '2nd Floor',                'available'),
('GH-301', 4, 6, '3', '3rd Floor, Group Dorm',    'available');

-- Seed: Guest House staff user (password: Password@123)
INSERT INTO `users` (`full_name`, `email`, `username`, `password_hash`, `role`, `office_id`, `status`) VALUES
('Guest House Staff',     'gh_staff@university.edu', 'gh_staff',
 '$2y$10$js.Wo3wgCRsF7osOsvcen.twq4P1knchqbFrXG9o/zy4xeyvQRyIu', 'guest_house_staff', NULL, 'active');

-- Seed: Sample bookings
INSERT INTO `guest_house_bookings`
  (`booking_reference`, `guest_id`, `room_id`, `check_in_date`, `check_out_date`,
   `actual_check_in`, `actual_check_out`,
   `purpose_of_stay`, `sponsoring_office_id`, `external_sponsor`,
   `number_of_guests`, `status`, `created_by_user_id`, `notes`)
VALUES
-- Reserved (future)
('GH-20260430-0001', 3, 1, '2026-05-05', '2026-05-07',
 NULL, NULL,
 'Guest speaker for IT seminar', 6, NULL,
 1, 'reserved', 1, NULL),

-- Currently checked in
('GH-20260430-0002', 1, 3, '2026-04-30', '2026-05-02',
 '2026-04-30 14:00:00', NULL,
 'Board of Trustees meeting (VIP)', 5, NULL,
 1, 'checked_in', 1, 'VIP — provide airport transport'),

-- Checked out (historical)
('GH-20260430-0003', 5, 2, '2026-04-25', '2026-04-27',
 '2026-04-25 13:30:00', '2026-04-27 10:00:00',
 'Partner university research visit', NULL, 'University of the Philippines',
 2, 'checked_out', 1, NULL);

-- Reflect currently-occupied room status for the checked_in seed booking
UPDATE `guest_house_rooms` SET `status` = 'occupied' WHERE `room_number` = 'GH-201';

COMMIT;
