-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: guest_system
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `visit_id` int(11) DEFAULT NULL,
  `action_type` enum('pre_registration','walk_in_registration','check_in','check_out','destination_added','destination_confirmed','destination_completed','destination_cancelled','unplanned_destination_added','guest_transferred','visit_cancelled','guest_restricted','user_login','user_logout','user_created','user_updated','office_created','office_updated','other') NOT NULL,
  `performed_by_user_id` int(11) DEFAULT NULL,
  `office_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `logged_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `performed_by_user_id` (`performed_by_user_id`),
  KEY `idx_log_visit` (`visit_id`),
  KEY `idx_log_action` (`action_type`),
  KEY `idx_log_time` (`logged_at`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`visit_id`) REFERENCES `guest_visits` (`visit_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `activity_logs_ibfk_2` FOREIGN KEY (`performed_by_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,1,'pre_registration',1,NULL,'Guest Roberto Alcantara pre-registered for visit GST-20260418-0001','127.0.0.1','2026-04-23 15:26:06'),(2,1,'check_in',2,NULL,'Guard checked in Roberto Alcantara at gate','127.0.0.1','2026-04-23 15:26:06'),(3,1,'destination_confirmed',4,4,'HR staff confirmed arrival of Roberto Alcantara','127.0.0.1','2026-04-23 15:26:06'),(4,2,'walk_in_registration',2,NULL,'Walk-in guest Liza Villarreal registered','127.0.0.1','2026-04-23 15:26:06'),(5,2,'check_in',2,NULL,'Guard checked in Liza Villarreal at gate','127.0.0.1','2026-04-23 15:26:06'),(6,3,'pre_registration',1,NULL,'Guest James Ong pre-registered for visit GST-20260418-0003','127.0.0.1','2026-04-23 15:26:06'),(7,3,'check_in',2,NULL,'Guard checked in James Ong at gate','127.0.0.1','2026-04-23 15:26:06'),(8,3,'check_out',2,NULL,'Guard checked out James Ong at exit gate','127.0.0.1','2026-04-23 15:26:06'),(9,5,'walk_in_registration',3,NULL,'Walk-in guest Felix Ramos registered','127.0.0.1','2026-04-23 15:26:06'),(10,5,'unplanned_destination_added',4,7,'Office staff added Library as unplanned destination for Felix Ramos','127.0.0.1','2026-04-23 15:26:06'),(11,NULL,'user_login',1,NULL,'User logged in','::1','2026-04-23 15:27:44'),(12,NULL,'user_login',1,NULL,'User logged in','::1','2026-04-24 11:53:11'),(13,NULL,'user_logout',1,NULL,'User logged out','::1','2026-04-24 12:16:53'),(14,NULL,'user_login',2,NULL,'User logged in','::1','2026-04-24 12:17:05'),(15,5,'check_out',2,NULL,'Checked out \'Felix Ramos\'','::1','2026-04-24 12:17:21'),(16,NULL,'user_logout',2,NULL,'User logged out','::1','2026-04-24 12:18:30'),(17,NULL,'user_login',4,NULL,'User logged in','::1','2026-04-24 12:18:38'),(18,NULL,'user_logout',4,NULL,'User logged out','::1','2026-04-24 12:19:00'),(19,NULL,'user_login',4,NULL,'User logged in','::1','2026-04-25 10:53:59'),(20,NULL,'user_logout',4,NULL,'User logged out','::1','2026-04-25 10:54:05'),(21,NULL,'user_login',1,NULL,'User logged in','::1','2026-04-25 10:54:13'),(22,NULL,'user_login',1,NULL,'User logged in','::1','2026-04-25 11:52:32'),(23,NULL,'user_logout',1,NULL,'User logged out','::1','2026-04-25 12:01:22'),(24,NULL,'user_login',1,NULL,'User logged in','::1','2026-04-25 12:12:48'),(25,NULL,'user_login',1,NULL,'User logged in','::1','2026-04-25 14:20:37'),(26,NULL,'user_logout',1,NULL,'User logged out','::1','2026-04-25 14:20:44'),(27,NULL,'user_login',1,NULL,'User logged in','::1','2026-04-25 14:57:28'),(28,NULL,'guest_restricted',1,NULL,'\'Carmen Torres\' restricted: HAVING A WEAPON','::1','2026-04-25 15:37:01'),(29,NULL,'user_logout',1,NULL,'User logged out','::1','2026-04-25 15:45:47'),(30,NULL,'user_login',1,NULL,'User logged in','::1','2026-04-25 15:46:11'),(31,NULL,'user_login',1,NULL,'User logged in','::1','2026-04-25 16:19:32'),(32,1,'check_out',1,NULL,'Checked out \'Roberto Alcantara\'','::1','2026-04-25 16:20:55'),(33,2,'check_out',1,NULL,'Checked out \'Liza Villarreal\'','::1','2026-04-25 16:21:06'),(34,NULL,'user_logout',1,NULL,'User logged out','::1','2026-04-25 16:31:02'),(35,7,'pre_registration',NULL,NULL,'Guest \'Zsian Morales1\' self-registered online with reference GST-20260425-0001','::1','2026-04-25 16:57:35'),(36,NULL,'user_login',1,NULL,'User logged in','::1','2026-04-25 16:58:13'),(37,7,'check_in',1,NULL,'Checked in \'Zsian Morales1\'','::1','2026-04-25 17:07:32'),(38,NULL,'user_logout',1,NULL,'User logged out','::1','2026-04-25 17:10:26'),(39,NULL,'user_login',2,NULL,'User logged in','::1','2026-04-25 17:10:34'),(40,NULL,'user_logout',2,NULL,'User logged out','::1','2026-04-25 17:11:48'),(41,NULL,'user_login',1,NULL,'User logged in','::1','2026-04-25 17:14:18'),(42,NULL,'user_updated',1,NULL,'User \'staff_fin\' updated','::1','2026-04-25 17:14:59'),(43,NULL,'user_logout',1,NULL,'User logged out','::1','2026-04-25 17:15:06'),(44,NULL,'user_login',5,NULL,'User logged in','::1','2026-04-25 17:15:15'),(45,7,'destination_confirmed',5,2,'Finance Office confirmed Zsian Morales1','::1','2026-04-25 17:38:26'),(46,7,'destination_completed',5,2,'Finance Office completed Zsian Morales1','::1','2026-04-25 17:38:41'),(47,NULL,'user_logout',5,NULL,'User logged out','::1','2026-04-25 17:38:47'),(48,NULL,'user_login',1,NULL,'User logged in','::1','2026-04-25 17:39:16'),(49,NULL,'user_logout',1,NULL,'User logged out','::1','2026-04-25 17:39:20'),(50,NULL,'user_login',2,NULL,'User logged in','::1','2026-04-25 17:39:41'),(51,7,'check_out',2,NULL,'Checked out \'Zsian Morales1\'','::1','2026-04-25 17:44:11'),(52,NULL,'user_logout',2,NULL,'User logged out','::1','2026-04-25 17:44:19'),(53,NULL,'user_login',1,NULL,'User logged in','::1','2026-04-25 21:41:57'),(54,NULL,'user_logout',1,NULL,'User logged out','::1','2026-04-25 21:42:33'),(55,NULL,'user_login',1,NULL,'User logged in','::1','2026-04-26 14:49:47'),(56,NULL,'user_logout',1,NULL,'User logged out','::1','2026-04-26 15:26:32'),(57,NULL,'user_login',1,NULL,'User logged in','::1','2026-04-28 15:00:59'),(58,NULL,'user_logout',1,NULL,'User logged out','::1','2026-04-28 15:16:36'),(59,NULL,'user_login',1,NULL,'User logged in','::1','2026-04-30 13:54:15'),(60,NULL,'user_logout',1,NULL,'User logged out','::1','2026-04-30 13:54:22'),(61,NULL,'user_login',1,NULL,'User logged in','::1','2026-04-30 14:14:19');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `guest_visits`
--

DROP TABLE IF EXISTS `guest_visits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guest_visits` (
  `visit_id` int(11) NOT NULL AUTO_INCREMENT,
  `guest_id` int(11) NOT NULL,
  `visit_reference` varchar(30) NOT NULL,
  `qr_token` varchar(100) DEFAULT NULL,
  `registration_type` enum('pre_registered','walk_in') NOT NULL DEFAULT 'walk_in',
  `purpose_of_visit` text NOT NULL,
  `visit_date` date NOT NULL,
  `expected_time_in` time DEFAULT NULL,
  `expected_time_out` time DEFAULT NULL,
  `actual_check_in` datetime DEFAULT NULL,
  `actual_check_out` datetime DEFAULT NULL,
  `overall_status` enum('pending','checked_in','checked_out','cancelled','overstayed') NOT NULL DEFAULT 'pending',
  `has_vehicle` tinyint(1) NOT NULL DEFAULT 0,
  `pass_number` varchar(50) DEFAULT NULL,
  `processed_by_guard_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`visit_id`),
  UNIQUE KEY `visit_reference` (`visit_reference`),
  UNIQUE KEY `qr_token` (`qr_token`),
  KEY `guest_id` (`guest_id`),
  KEY `processed_by_guard_id` (`processed_by_guard_id`),
  KEY `idx_visit_ref` (`visit_reference`),
  KEY `idx_visit_qr` (`qr_token`),
  KEY `idx_visit_date` (`visit_date`),
  KEY `idx_visit_status` (`overall_status`),
  CONSTRAINT `guest_visits_ibfk_1` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`guest_id`) ON UPDATE CASCADE,
  CONSTRAINT `guest_visits_ibfk_2` FOREIGN KEY (`processed_by_guard_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `guest_visits`
--

LOCK TABLES `guest_visits` WRITE;
/*!40000 ALTER TABLE `guest_visits` DISABLE KEYS */;
INSERT INTO `guest_visits` VALUES (1,1,'GST-20260418-0001','QR-ABCDEF001','pre_registered','Business meeting with HR regarding partnership','2026-04-18','09:00:00','11:00:00','2026-04-18 09:15:00','2026-04-25 16:20:55','checked_out',0,NULL,2,NULL,'2026-04-23 15:26:06','2026-04-25 16:20:55'),(2,2,'GST-20260418-0002','QR-ABCDEF002','walk_in','Submitting scholarship documents to Registrar and Finance','2026-04-18',NULL,NULL,'2026-04-18 10:00:00','2026-04-25 16:21:06','checked_out',1,NULL,2,'Rush visit','2026-04-23 15:26:06','2026-04-25 16:21:06'),(3,3,'GST-20260418-0003','QR-ABCDEF003','pre_registered','IT consultation and system demonstration','2026-04-18','08:00:00','10:00:00','2026-04-18 08:10:00','2026-04-18 10:30:00','checked_out',0,NULL,2,NULL,'2026-04-23 15:26:06','2026-04-23 15:26:06'),(4,4,'GST-20260418-0004',NULL,'pre_registered','Meeting with Office of the President','2026-04-18','14:00:00','15:00:00',NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-04-23 15:26:06','2026-04-23 15:26:06'),(5,5,'GST-20260418-0005','QR-ABCDEF005','walk_in','Research collaboration with Guidance','2026-04-18',NULL,NULL,'2026-04-18 11:30:00','2026-04-24 12:17:21','checked_out',1,NULL,3,NULL,'2026-04-23 15:26:06','2026-04-24 12:17:21'),(7,7,'GST-20260425-0001','QR-364FCC18AEE2793D','pre_registered','enrollment','2026-04-25','16:57:00','16:59:00','2026-04-25 17:07:32','2026-04-25 17:44:10','checked_out',0,NULL,1,NULL,'2026-04-25 16:57:35','2026-04-25 17:44:10');
/*!40000 ALTER TABLE `guest_visits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `guests`
--

DROP TABLE IF EXISTS `guests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guests` (
  `guest_id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `organization` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `id_type` varchar(80) DEFAULT NULL,
  `id_number_masked` varchar(50) DEFAULT NULL,
  `photo_filename` varchar(255) DEFAULT NULL,
  `is_restricted` tinyint(1) NOT NULL DEFAULT 0,
  `restriction_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`guest_id`),
  KEY `idx_guest_name` (`full_name`),
  KEY `idx_guest_contact` (`contact_number`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `guests`
--

LOCK TABLES `guests` WRITE;
/*!40000 ALTER TABLE `guests` DISABLE KEYS */;
INSERT INTO `guests` VALUES (1,'Roberto Alcantara','09171234567','roberto@gmail.com','BPI Bank',NULL,'Government ID',NULL,NULL,0,NULL,'2026-04-23 15:26:06','2026-04-25 16:52:48'),(2,'Liza Villarreal','09281234567','liza.v@yahoo.com','DepEd Manila',NULL,'Driver\'s License',NULL,NULL,0,NULL,'2026-04-23 15:26:06','2026-04-25 16:52:48'),(3,'James Ong','09391234567','james.ong@corp.com','TechCorp PH',NULL,'Passport',NULL,NULL,0,NULL,'2026-04-23 15:26:06','2026-04-25 16:52:48'),(4,'Carmen Torres','09451234567',NULL,'Walk-in',NULL,'SSS ID',NULL,NULL,1,'HAVING A WEAPON','2026-04-23 15:26:06','2026-04-25 16:52:48'),(5,'Felix Ramos','09561234567','felix.r@email.com','University of PH',NULL,'School ID',NULL,NULL,0,NULL,'2026-04-23 15:26:06','2026-04-25 16:52:48'),(7,'Zsian Morales1','123123123','mzsianmorales@gmail.com','none',NULL,'School ID',NULL,NULL,0,NULL,'2026-04-25 16:57:35','2026-04-25 16:57:35');
/*!40000 ALTER TABLE `guests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `visit_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `type` enum('info','alert','warning','success') NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`),
  KEY `visit_id` (`visit_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`visit_id`) REFERENCES `guest_visits` (`visit_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `offices`
--

DROP TABLE IF EXISTS `offices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `offices` (
  `office_id` int(11) NOT NULL AUTO_INCREMENT,
  `office_name` varchar(150) NOT NULL,
  `office_code` varchar(30) NOT NULL,
  `office_location` varchar(200) DEFAULT NULL,
  `requires_arrival_confirmation` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`office_id`),
  UNIQUE KEY `office_code` (`office_code`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `offices`
--

LOCK TABLES `offices` WRITE;
/*!40000 ALTER TABLE `offices` DISABLE KEYS */;
INSERT INTO `offices` VALUES (1,'Registrar Office','REG','Admin Building, Ground Floor',1,'active','2026-04-23 15:26:06','2026-04-23 15:26:06'),(2,'Finance Office','FIN','Admin Building, 2nd Floor',1,'active','2026-04-23 15:26:06','2026-04-23 15:26:06'),(3,'Guidance and Counseling','GUID','Student Services Building',0,'active','2026-04-23 15:26:06','2026-04-23 15:26:06'),(4,'Human Resources','HR','Admin Building, 3rd Floor',1,'active','2026-04-23 15:26:06','2026-04-23 15:26:06'),(5,'Office of the President','PRES','Main Building, 4th Floor',1,'active','2026-04-23 15:26:06','2026-04-23 15:26:06'),(6,'IT Department','IT','Tech Building, Ground Floor',0,'active','2026-04-23 15:26:06','2026-04-23 15:26:06'),(7,'Library','LIB','Main Building, 1st Floor',0,'active','2026-04-23 15:26:06','2026-04-23 15:26:06'),(8,'Security and Safety Office','SSO','Gate House',0,'active','2026-04-23 15:26:06','2026-04-23 15:26:06'),(9,'Admissions Office','ADMN','Admin Building, Ground Floor',1,'active','2026-04-23 15:26:06','2026-04-23 15:26:06'),(10,'Accounting Office','ACCTG','Admin Building, 2nd Floor',1,'active','2026-04-23 15:26:06','2026-04-23 15:26:06');
/*!40000 ALTER TABLE `offices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `restricted_guests`
--

DROP TABLE IF EXISTS `restricted_guests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `restricted_guests` (
  `restriction_id` int(11) NOT NULL AUTO_INCREMENT,
  `guest_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `restricted_by_user_id` int(11) DEFAULT NULL,
  `restricted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `lifted_at` datetime DEFAULT NULL,
  `lifted_by_user_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`restriction_id`),
  KEY `guest_id` (`guest_id`),
  KEY `restricted_by_user_id` (`restricted_by_user_id`),
  KEY `lifted_by_user_id` (`lifted_by_user_id`),
  CONSTRAINT `restricted_guests_ibfk_1` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`guest_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `restricted_guests_ibfk_2` FOREIGN KEY (`restricted_by_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `restricted_guests_ibfk_3` FOREIGN KEY (`lifted_by_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `restricted_guests`
--

LOCK TABLES `restricted_guests` WRITE;
/*!40000 ALTER TABLE `restricted_guests` DISABLE KEYS */;
INSERT INTO `restricted_guests` VALUES (1,4,'HAVING A WEAPON',1,'2026-04-25 15:37:01',NULL,NULL,1);
/*!40000 ALTER TABLE `restricted_guests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `username` varchar(80) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','guard','office_staff') NOT NULL,
  `office_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`),
  KEY `office_id` (`office_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`office_id`) REFERENCES `offices` (`office_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'System Administrator','admin@university.edu','admin','$2y$10$js.Wo3wgCRsF7osOsvcen.twq4P1knchqbFrXG9o/zy4xeyvQRyIu','admin',NULL,'active','2026-04-30 14:14:19','2026-04-23 15:26:06','2026-04-30 14:14:19'),(2,'Juan dela Cruz','guard1@university.edu','guard1','$2y$10$js.Wo3wgCRsF7osOsvcen.twq4P1knchqbFrXG9o/zy4xeyvQRyIu','guard',NULL,'active','2026-04-25 17:39:41','2026-04-23 15:26:06','2026-04-25 17:39:41'),(3,'Maria Santos','guard2@university.edu','guard2','$2y$10$js.Wo3wgCRsF7osOsvcen.twq4P1knchqbFrXG9o/zy4xeyvQRyIu','guard',NULL,'active','2026-04-25 12:11:28','2026-04-23 15:26:06','2026-04-25 12:11:28'),(4,'Ana Reyes','registrar@university.edu','staff_reg','$2y$10$js.Wo3wgCRsF7osOsvcen.twq4P1knchqbFrXG9o/zy4xeyvQRyIu','office_staff',1,'active','2026-04-25 12:12:11','2026-04-23 15:26:06','2026-04-25 12:12:11'),(5,'Pedro Garcia','finance@university.edu','staff_fin','$2y$10$nZUZxq2dX/p.pMra448MY.d2boxj82rii9EpfvTtfcLmeHY0kMOna','office_staff',2,'active','2026-04-25 17:15:15','2026-04-23 15:26:06','2026-04-25 17:15:15'),(6,'Rosa Mendoza','hr@university.edu','staff_hr','$2y$10$js.Wo3wgCRsF7osOsvcen.twq4P1knchqbFrXG9o/zy4xeyvQRyIu','office_staff',4,'active','2026-04-25 12:11:28','2026-04-23 15:26:06','2026-04-25 12:11:28'),(7,'Carlo Bautista','admissions@university.edu','staff_admn','$2y$10$js.Wo3wgCRsF7osOsvcen.twq4P1knchqbFrXG9o/zy4xeyvQRyIu','office_staff',9,'active','2026-04-25 12:11:28','2026-04-23 15:26:06','2026-04-25 12:11:28');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicle_entries`
--

DROP TABLE IF EXISTS `vehicle_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicle_entries` (
  `vehicle_id` int(11) NOT NULL AUTO_INCREMENT,
  `visit_id` int(11) NOT NULL,
  `vehicle_type` enum('car','motorcycle','van','truck','other') NOT NULL DEFAULT 'car',
  `plate_number` varchar(20) NOT NULL,
  `sticker_number` varchar(50) DEFAULT NULL,
  `vehicle_color` varchar(50) DEFAULT NULL,
  `vehicle_model` varchar(100) DEFAULT NULL,
  `driver_name` varchar(150) DEFAULT NULL,
  `is_driver_the_guest` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`vehicle_id`),
  UNIQUE KEY `visit_id` (`visit_id`),
  KEY `idx_vehicle_plate` (`plate_number`),
  CONSTRAINT `vehicle_entries_ibfk_1` FOREIGN KEY (`visit_id`) REFERENCES `guest_visits` (`visit_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicle_entries`
--

LOCK TABLES `vehicle_entries` WRITE;
/*!40000 ALTER TABLE `vehicle_entries` DISABLE KEYS */;
INSERT INTO `vehicle_entries` VALUES (1,2,'car','ABC 1234',NULL,'White','Toyota Vios','Liza Villarreal',1,'2026-04-23 15:26:06'),(2,5,'motorcycle','DEF 5678',NULL,'Black','Honda Wave','Felix Ramos',1,'2026-04-23 15:26:06');
/*!40000 ALTER TABLE `vehicle_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `visit_destinations`
--

DROP TABLE IF EXISTS `visit_destinations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `visit_destinations` (
  `destination_id` int(11) NOT NULL AUTO_INCREMENT,
  `visit_id` int(11) NOT NULL,
  `office_id` int(11) NOT NULL,
  `sequence_no` int(3) NOT NULL DEFAULT 1,
  `destination_status` enum('pending','arrived','in_service','completed','cancelled') NOT NULL DEFAULT 'pending',
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `is_unplanned` tinyint(1) NOT NULL DEFAULT 0,
  `received_by_user_id` int(11) DEFAULT NULL,
  `arrival_time` datetime DEFAULT NULL,
  `completed_time` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`destination_id`),
  KEY `received_by_user_id` (`received_by_user_id`),
  KEY `idx_dest_visit` (`visit_id`),
  KEY `idx_dest_office` (`office_id`),
  CONSTRAINT `visit_destinations_ibfk_1` FOREIGN KEY (`visit_id`) REFERENCES `guest_visits` (`visit_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `visit_destinations_ibfk_2` FOREIGN KEY (`office_id`) REFERENCES `offices` (`office_id`) ON UPDATE CASCADE,
  CONSTRAINT `visit_destinations_ibfk_3` FOREIGN KEY (`received_by_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `visit_destinations`
--

LOCK TABLES `visit_destinations` WRITE;
/*!40000 ALTER TABLE `visit_destinations` DISABLE KEYS */;
INSERT INTO `visit_destinations` VALUES (1,1,4,1,'completed',1,0,NULL,'2026-04-18 09:20:00','2026-04-25 16:20:55',NULL,'2026-04-23 15:26:06','2026-04-25 16:20:55'),(2,2,1,1,'completed',1,0,NULL,'2026-04-18 10:10:00',NULL,NULL,'2026-04-23 15:26:06','2026-04-23 15:26:06'),(3,2,2,2,'completed',0,0,NULL,'2026-04-18 10:45:00','2026-04-25 16:21:06',NULL,'2026-04-23 15:26:06','2026-04-25 16:21:06'),(4,3,6,1,'completed',1,0,NULL,'2026-04-18 08:15:00',NULL,NULL,'2026-04-23 15:26:06','2026-04-23 15:26:06'),(5,4,5,1,'pending',1,0,NULL,NULL,NULL,NULL,'2026-04-23 15:26:06','2026-04-23 15:26:06'),(6,5,3,1,'completed',1,0,NULL,'2026-04-18 11:40:00','2026-04-24 12:17:21',NULL,'2026-04-23 15:26:06','2026-04-24 12:17:21'),(7,5,7,2,'pending',0,1,NULL,NULL,NULL,NULL,'2026-04-23 15:26:06','2026-04-23 15:26:06'),(9,7,2,1,'completed',1,0,5,'2026-04-25 17:38:26','2026-04-25 17:38:41',NULL,'2026-04-25 16:57:35','2026-04-25 17:38:41');
/*!40000 ALTER TABLE `visit_destinations` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-30 14:24:21
