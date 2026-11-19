-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: traffic_demerit
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
-- Table structure for table `appeals`
--

DROP TABLE IF EXISTS `appeals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appeals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `driver_id` bigint(20) unsigned NOT NULL,
  `violation_id` bigint(20) unsigned DEFAULT NULL,
  `sanction_action_id` bigint(20) unsigned DEFAULT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `outcome` varchar(255) DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `reviewed_by` varchar(255) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `submitted_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `appeals_sanction_action_id_unique` (`sanction_action_id`),
  KEY `appeals_violation_id_foreign` (`violation_id`),
  KEY `idx_appeals_driver_status` (`driver_id`,`status`),
  KEY `idx_appeals_resolved_at` (`resolved_at`),
  CONSTRAINT `appeals_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `driver_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appeals_sanction_action_id_foreign` FOREIGN KEY (`sanction_action_id`) REFERENCES `sanction_actions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `appeals_violation_id_foreign` FOREIGN KEY (`violation_id`) REFERENCES `violations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appeals`
--

LOCK TABLES `appeals` WRITE;
/*!40000 ALTER TABLE `appeals` DISABLE KEYS */;
INSERT INTO `appeals` VALUES (1,2,6,NULL,'Road signage not visible','approved',NULL,NULL,NULL,NULL,NULL,'2026-09-25 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(2,2,8,NULL,'Vehicle was not driven by owner','approved',NULL,NULL,NULL,NULL,NULL,'2026-10-03 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(3,3,12,NULL,'Incorrect vehicle identification','pending',NULL,NULL,NULL,NULL,NULL,'2026-10-14 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(4,3,16,NULL,'Road signage not visible','rejected',NULL,NULL,NULL,NULL,NULL,'2026-09-21 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(5,4,17,NULL,'Following traffic officer instructions','pending',NULL,NULL,NULL,NULL,NULL,'2026-09-21 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(6,4,18,NULL,'Vehicle was not driven by owner','pending',NULL,NULL,NULL,NULL,NULL,'2026-10-01 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(7,5,21,NULL,'Road signage not visible','pending',NULL,NULL,NULL,NULL,NULL,'2026-09-16 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(8,7,23,NULL,'Following traffic officer instructions','approved',NULL,NULL,NULL,NULL,NULL,'2026-09-16 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(9,7,25,NULL,'Following traffic officer instructions','pending',NULL,NULL,NULL,NULL,NULL,'2026-10-09 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(10,7,26,NULL,'Speed camera malfunction','rejected',NULL,NULL,NULL,NULL,NULL,'2026-10-08 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37');
/*!40000 ALTER TABLE `appeals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `demerit_ledger_entries`
--

DROP TABLE IF EXISTS `demerit_ledger_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `demerit_ledger_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `driver_id` bigint(20) unsigned NOT NULL,
  `violation_id` bigint(20) unsigned DEFAULT NULL,
  `points_applied` int(11) NOT NULL,
  `days_since_last_offence` int(11) DEFAULT NULL,
  `running_balance` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `reason_code` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `demerit_ledger_entries_violation_id_foreign` (`violation_id`),
  KEY `idx_ledger_driver_created_at` (`driver_id`,`created_at`),
  CONSTRAINT `demerit_ledger_entries_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `driver_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `demerit_ledger_entries_violation_id_foreign` FOREIGN KEY (`violation_id`) REFERENCES `violations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=187 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `demerit_ledger_entries`
--

LOCK TABLES `demerit_ledger_entries` WRITE;
/*!40000 ALTER TABLE `demerit_ledger_entries` DISABLE KEYS */;
INSERT INTO `demerit_ledger_entries` VALUES (1,1,1,8,NULL,8,'violation',NULL,'2026-10-16 02:43:35','2026-10-16 02:43:35'),(2,1,2,5,NULL,13,'violation',NULL,'2026-10-16 02:43:35','2026-10-16 02:43:35'),(3,1,3,3,NULL,16,'violation',NULL,'2026-10-16 02:43:35','2026-10-16 02:43:35'),(4,1,4,8,NULL,24,'violation',NULL,'2026-10-16 02:43:35','2026-10-16 02:43:35'),(5,1,5,6,NULL,30,'violation',NULL,'2026-10-16 02:43:35','2026-10-16 02:43:35'),(6,2,6,6,NULL,6,'violation',NULL,'2026-10-16 02:43:35','2026-10-16 02:43:35'),(7,2,7,2,NULL,8,'violation',NULL,'2026-10-16 02:43:35','2026-10-16 02:43:35'),(8,2,8,2,NULL,10,'violation',NULL,'2026-10-16 02:43:35','2026-10-16 02:43:35'),(9,2,9,6,NULL,16,'violation',NULL,'2026-10-16 02:43:35','2026-10-16 02:43:35'),(10,2,10,4,NULL,20,'violation',NULL,'2026-10-16 02:43:35','2026-10-16 02:43:35'),(11,3,11,4,NULL,4,'violation',NULL,'2026-10-16 02:43:35','2026-10-16 02:43:35'),(12,3,12,5,NULL,9,'violation',NULL,'2026-10-16 02:43:35','2026-10-16 02:43:35'),(13,3,13,6,NULL,15,'violation',NULL,'2026-10-16 02:43:35','2026-10-16 02:43:35'),(14,3,14,4,NULL,19,'violation',NULL,'2026-10-16 02:43:35','2026-10-16 02:43:35'),(15,3,15,6,NULL,25,'violation',NULL,'2026-10-16 02:43:35','2026-10-16 02:43:35'),(16,3,16,4,NULL,29,'violation',NULL,'2026-10-16 02:43:35','2026-10-16 02:43:35'),(17,4,17,4,NULL,4,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(18,4,18,8,NULL,12,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(19,5,19,4,NULL,4,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(20,5,20,5,NULL,9,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(21,5,21,8,NULL,17,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(22,6,22,2,NULL,2,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(23,7,23,6,NULL,6,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(24,7,24,1,NULL,7,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(25,7,25,8,NULL,15,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(26,7,26,4,NULL,19,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(27,7,27,3,NULL,22,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(28,8,28,6,NULL,6,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(29,8,29,6,NULL,12,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(30,9,30,5,NULL,5,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(31,9,31,8,NULL,13,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(32,9,32,5,NULL,18,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(33,9,33,8,NULL,26,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(34,9,34,6,NULL,32,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(35,10,35,8,NULL,8,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(36,10,36,4,NULL,12,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(37,10,37,1,NULL,13,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(38,11,38,4,NULL,4,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(39,11,39,3,NULL,7,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(40,11,40,8,NULL,15,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(41,11,41,3,NULL,18,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(42,11,42,1,NULL,19,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(43,11,43,2,NULL,21,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(44,12,44,5,NULL,5,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(45,12,45,1,NULL,6,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(46,12,46,5,NULL,11,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(47,12,47,8,NULL,19,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(48,12,48,5,NULL,24,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(49,12,49,6,NULL,30,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(50,13,50,6,NULL,6,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(51,14,51,6,NULL,6,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(52,14,52,3,NULL,9,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(53,14,53,4,NULL,13,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(54,14,54,8,NULL,21,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(55,14,55,4,NULL,25,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(56,15,56,8,NULL,8,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(57,15,57,6,NULL,14,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(58,15,58,3,NULL,17,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(59,15,59,5,NULL,22,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(60,16,60,4,NULL,4,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(61,16,61,4,NULL,8,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(62,16,62,6,NULL,14,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(63,16,63,8,NULL,22,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(64,16,64,4,NULL,26,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(65,17,65,6,NULL,6,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(66,18,66,5,NULL,5,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(67,18,67,4,NULL,9,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(68,18,68,4,NULL,13,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(69,18,69,2,NULL,15,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(70,18,70,8,NULL,23,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(71,18,71,4,NULL,27,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(72,19,72,3,NULL,3,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(73,20,73,4,NULL,4,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(74,20,74,6,NULL,10,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(75,21,75,6,NULL,6,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(76,21,76,4,NULL,10,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(77,21,77,4,NULL,14,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(78,21,78,6,NULL,20,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(79,21,79,4,NULL,24,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(80,22,80,4,NULL,4,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(81,22,81,8,NULL,12,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(82,22,82,6,NULL,18,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(83,22,83,2,NULL,20,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(84,22,84,4,NULL,24,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(85,23,85,8,NULL,8,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(86,23,86,8,NULL,16,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(87,23,87,8,NULL,24,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(88,23,88,8,NULL,32,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(89,23,89,4,NULL,36,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(90,23,90,3,NULL,39,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(91,24,91,6,NULL,6,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(92,24,92,8,NULL,14,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(93,24,93,4,NULL,18,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(94,24,94,3,NULL,21,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(95,24,95,1,NULL,22,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(96,24,96,4,NULL,26,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(97,25,97,2,NULL,2,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(98,25,98,6,NULL,8,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(99,26,99,3,NULL,3,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(100,27,100,5,NULL,5,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(101,27,101,3,NULL,8,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(102,28,102,6,NULL,6,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(103,28,103,3,NULL,9,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(104,28,104,1,NULL,10,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(105,28,105,4,NULL,14,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(106,29,106,2,NULL,2,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(107,29,107,5,NULL,7,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(108,29,108,5,NULL,12,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(109,30,109,1,NULL,1,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(110,31,110,8,NULL,8,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(111,31,111,4,NULL,12,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(112,31,112,2,NULL,14,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(113,31,113,3,NULL,17,'violation',NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(114,31,114,3,NULL,20,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(115,32,115,6,NULL,6,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(116,32,116,8,NULL,14,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(117,32,117,4,NULL,18,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(118,32,118,1,NULL,19,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(119,33,119,2,NULL,2,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(120,33,120,6,NULL,8,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(121,33,121,8,NULL,16,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(122,33,122,4,NULL,20,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(123,33,123,8,NULL,28,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(124,34,124,6,NULL,6,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(125,34,125,6,NULL,12,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(126,35,126,6,NULL,6,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(127,35,127,4,NULL,10,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(128,35,128,2,NULL,12,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(129,36,129,2,NULL,2,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(130,36,130,6,NULL,8,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(131,37,131,6,NULL,6,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(132,37,132,8,NULL,14,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(133,38,133,1,NULL,1,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(134,38,134,5,NULL,6,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(135,39,135,6,NULL,6,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(136,39,136,6,NULL,12,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(137,39,137,5,NULL,17,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(138,39,138,5,NULL,22,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(139,39,139,4,NULL,26,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(140,40,140,8,NULL,8,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(141,40,141,6,NULL,14,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(142,40,142,1,NULL,15,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(143,40,143,2,NULL,17,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(144,40,144,2,NULL,19,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(145,41,145,1,NULL,1,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(146,41,146,3,NULL,4,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(147,41,147,4,NULL,8,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(148,41,148,3,NULL,11,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(149,41,149,8,NULL,19,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(150,42,150,4,NULL,4,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(151,42,151,8,NULL,12,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(152,42,152,8,NULL,20,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(153,43,153,6,NULL,6,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(154,43,154,4,NULL,10,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(155,43,155,4,NULL,14,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(156,43,156,5,NULL,19,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(157,43,157,4,NULL,23,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(158,44,158,8,NULL,8,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(159,44,159,6,NULL,14,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(160,44,160,4,NULL,18,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(161,44,161,2,NULL,20,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(162,44,162,8,NULL,28,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(163,45,163,1,NULL,1,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(164,45,164,5,NULL,6,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(165,45,165,4,NULL,10,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(166,45,166,5,NULL,15,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(167,46,167,4,NULL,4,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(168,46,168,8,NULL,12,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(169,46,169,1,NULL,13,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(170,47,170,5,NULL,5,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(171,47,171,2,NULL,7,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(172,47,172,5,NULL,12,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(173,47,173,6,NULL,18,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(174,47,174,6,NULL,24,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(175,47,175,6,NULL,30,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(176,48,176,6,NULL,6,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(177,48,177,6,NULL,12,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(178,48,178,5,NULL,17,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(179,48,179,5,NULL,22,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(180,49,180,8,NULL,8,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(181,49,181,1,NULL,9,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(182,49,182,3,NULL,12,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(183,49,183,4,NULL,16,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(184,49,184,6,NULL,22,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(185,50,185,1,NULL,1,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(186,50,186,1,NULL,2,'violation',NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37');
/*!40000 ALTER TABLE `demerit_ledger_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `driver_profiles`
--

DROP TABLE IF EXISTS `driver_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `driver_profiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `license_no` varchar(255) NOT NULL,
  `license_class` varchar(255) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `profile_photo_path` varchar(255) DEFAULT NULL,
  `license_issue_date` date NOT NULL,
  `license_expiry_date` date DEFAULT NULL,
  `status` enum('active','restricted','suspended') NOT NULL DEFAULT 'active',
  `current_demerit_points` int(11) NOT NULL DEFAULT 0,
  `account_status` enum('active','restricted','suspended') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `driver_profiles_license_no_unique` (`license_no`),
  KEY `driver_profiles_user_id_foreign` (`user_id`),
  CONSTRAINT `driver_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `driver_profiles`
--

LOCK TABLES `driver_profiles` WRITE;
/*!40000 ALTER TABLE `driver_profiles` DISABLE KEYS */;
INSERT INTO `driver_profiles` VALUES (1,NULL,'DL-KE-2000',NULL,'Thuo Njoki',NULL,'2014-09-29',NULL,'suspended',30,'suspended','2026-10-16 02:43:12','2026-10-16 02:46:51'),(2,NULL,'DL-KE-2001',NULL,'Otieno Akinyi',NULL,'2015-12-30',NULL,'suspended',20,'suspended','2026-10-16 02:43:12','2026-10-16 02:46:51'),(3,NULL,'DL-KE-2002',NULL,'Odongo Akeyo',NULL,'2012-01-11',NULL,'suspended',29,'suspended','2026-10-16 02:43:13','2026-10-16 02:46:51'),(4,NULL,'DL-KE-2003',NULL,'Ouma Apiyo',NULL,'2015-09-08',NULL,'restricted',12,'restricted','2026-10-16 02:43:13','2026-10-16 02:46:51'),(5,NULL,'DL-KE-2004',NULL,'Karanja Mumbi',NULL,'2011-11-06',NULL,'suspended',17,'suspended','2026-10-16 02:43:14','2026-10-16 02:46:51'),(6,NULL,'DL-KE-2005',NULL,'Thuo Njoki',NULL,'2025-01-05',NULL,'restricted',2,'restricted','2026-10-16 02:43:14','2026-10-16 02:46:51'),(7,NULL,'DL-KE-2006',NULL,'Kinyanjui Wacera',NULL,'2016-11-06',NULL,'suspended',22,'suspended','2026-10-16 02:43:15','2026-10-16 02:46:51'),(8,NULL,'DL-KE-2007',NULL,'Kimani Wangari',NULL,'2013-11-16',NULL,'restricted',12,'restricted','2026-10-16 02:43:15','2026-10-16 02:46:51'),(9,NULL,'DL-KE-2008',NULL,'Omondi Adhiambo',NULL,'2020-12-30',NULL,'suspended',32,'suspended','2026-10-16 02:43:16','2026-10-16 02:46:51'),(10,NULL,'DL-KE-2009',NULL,'Karanja Mumbi',NULL,'2011-02-06',NULL,'suspended',13,'suspended','2026-10-16 02:43:16','2026-10-16 02:46:51'),(11,NULL,'DL-KE-2010',NULL,'Were Achieng',NULL,'2012-04-19',NULL,'suspended',21,'suspended','2026-10-16 02:43:16','2026-10-16 02:46:51'),(12,NULL,'DL-KE-2011',NULL,'Gitau Wairimu',NULL,'2011-07-24',NULL,'suspended',30,'suspended','2026-10-16 02:43:17','2026-10-16 02:46:51'),(13,NULL,'DL-KE-2012',NULL,'Ochieng Awino',NULL,'2018-05-11',NULL,'restricted',6,'restricted','2026-10-16 02:43:17','2026-10-16 02:46:51'),(14,NULL,'DL-KE-2013',NULL,'Okello Adoyo',NULL,'2016-04-16',NULL,'suspended',25,'suspended','2026-10-16 02:43:18','2026-10-16 02:46:51'),(15,NULL,'DL-KE-2014',NULL,'Kamande Wangeci',NULL,'2023-09-29',NULL,'suspended',22,'suspended','2026-10-16 02:43:18','2026-10-16 02:46:51'),(16,NULL,'DL-KE-2015',NULL,'Odera Atieno',NULL,'2020-04-21',NULL,'suspended',26,'suspended','2026-10-16 02:43:19','2026-10-16 02:46:51'),(17,NULL,'DL-KE-2016',NULL,'Kimani Wangari',NULL,'2011-07-01',NULL,'active',6,'active','2026-10-16 02:43:19','2026-10-16 02:46:51'),(18,NULL,'DL-KE-2017',NULL,'Njenga Nyambura',NULL,'2025-08-31',NULL,'suspended',27,'suspended','2026-10-16 02:43:20','2026-10-16 02:46:51'),(19,NULL,'DL-KE-2018',NULL,'Ochieng Awino',NULL,'2014-11-18',NULL,'restricted',3,'restricted','2026-10-16 02:43:20','2026-10-16 02:46:51'),(20,NULL,'DL-KE-2019',NULL,'Ouma Apiyo',NULL,'2013-01-09',NULL,'restricted',10,'restricted','2026-10-16 02:43:21','2026-10-16 02:46:51'),(21,NULL,'DL-KE-2020',NULL,'Otieno Akinyi',NULL,'2025-09-21',NULL,'suspended',24,'suspended','2026-10-16 02:43:21','2026-10-16 02:46:51'),(22,NULL,'DL-KE-2021',NULL,'Kamande Wangeci',NULL,'2017-03-08',NULL,'suspended',24,'suspended','2026-10-16 02:43:22','2026-10-16 02:46:51'),(23,NULL,'DL-KE-2022',NULL,'Ngugi Wangechi',NULL,'2016-01-07',NULL,'suspended',39,'suspended','2026-10-16 02:43:22','2026-10-16 02:46:51'),(24,NULL,'DL-KE-2023',NULL,'Oluoch Akinyi',NULL,'2015-03-01',NULL,'suspended',26,'suspended','2026-10-16 02:43:23','2026-10-16 02:46:51'),(25,NULL,'DL-KE-2024',NULL,'Macharia Nduta',NULL,'2012-06-02',NULL,'restricted',8,'restricted','2026-10-16 02:43:23','2026-10-16 02:46:51'),(26,NULL,'DL-KE-2025',NULL,'Onyango Auma',NULL,'2024-10-27',NULL,'restricted',3,'restricted','2026-10-16 02:43:23','2026-10-16 02:46:51'),(27,NULL,'DL-KE-2026',NULL,'Were Achieng',NULL,'2011-05-20',NULL,'active',8,'active','2026-10-16 02:43:24','2026-10-16 02:46:51'),(28,NULL,'DL-KE-2027',NULL,'Owino Akello',NULL,'2012-06-18',NULL,'suspended',14,'suspended','2026-10-16 02:43:24','2026-10-16 02:46:51'),(29,NULL,'DL-KE-2028',NULL,'Macharia Nduta',NULL,'2025-05-13',NULL,'restricted',12,'restricted','2026-10-16 02:43:25','2026-10-16 02:46:51'),(30,NULL,'DL-KE-2029',NULL,'Kariuki Wambui',NULL,'2011-07-14',NULL,'active',1,'active','2026-10-16 02:43:25','2026-10-16 02:46:51'),(31,NULL,'DL-KE-2030',NULL,'Otieno Akinyi',NULL,'2018-04-20',NULL,'suspended',20,'suspended','2026-10-16 02:43:26','2026-10-16 02:46:51'),(32,NULL,'DL-KE-2031',NULL,'Mwangi Njeri',NULL,'2025-04-06',NULL,'suspended',19,'suspended','2026-10-16 02:43:26','2026-10-16 02:46:51'),(33,NULL,'DL-KE-2032',NULL,'Onyango Auma',NULL,'2017-08-26',NULL,'suspended',28,'suspended','2026-10-16 02:43:27','2026-10-16 02:46:51'),(34,NULL,'DL-KE-2033',NULL,'Okello Adoyo',NULL,'2025-03-30',NULL,'restricted',12,'restricted','2026-10-16 02:43:27','2026-10-16 02:46:51'),(35,NULL,'DL-KE-2034',NULL,'Owino Akello',NULL,'2015-04-08',NULL,'restricted',12,'restricted','2026-10-16 02:43:28','2026-10-16 02:46:51'),(36,NULL,'DL-KE-2035',NULL,'Ouma Apiyo',NULL,'2020-05-19',NULL,'active',8,'active','2026-10-16 02:43:28','2026-10-16 02:46:51'),(37,NULL,'DL-KE-2036',NULL,'Njoroge Wamboi',NULL,'2018-10-13',NULL,'restricted',14,'restricted','2026-10-16 02:43:29','2026-10-16 02:46:51'),(38,NULL,'DL-KE-2037',NULL,'Odera Atieno',NULL,'2017-03-10',NULL,'restricted',6,'restricted','2026-10-16 02:43:29','2026-10-16 02:46:51'),(39,NULL,'DL-KE-2038',NULL,'Omondi Adhiambo',NULL,'2012-09-16',NULL,'suspended',26,'suspended','2026-10-16 02:43:30','2026-10-16 02:46:51'),(40,NULL,'DL-KE-2039',NULL,'Owino Akello',NULL,'2012-01-02',NULL,'suspended',19,'suspended','2026-10-16 02:43:30','2026-10-16 02:46:51'),(41,NULL,'DL-KE-2040',NULL,'Okello Adoyo',NULL,'2020-11-21',NULL,'suspended',19,'suspended','2026-10-16 02:43:30','2026-10-16 02:46:51'),(42,NULL,'DL-KE-2041',NULL,'Gathoni Wangui',NULL,'2012-11-15',NULL,'suspended',20,'suspended','2026-10-16 02:43:31','2026-10-16 02:46:51'),(43,NULL,'DL-KE-2042',NULL,'Ochieng Awino',NULL,'2016-01-23',NULL,'suspended',23,'suspended','2026-10-16 02:43:31','2026-10-16 02:46:51'),(44,NULL,'DL-KE-2043',NULL,'Gitau Wairimu',NULL,'2016-04-03',NULL,'suspended',28,'suspended','2026-10-16 02:43:32','2026-10-16 02:46:51'),(45,NULL,'DL-KE-2044',NULL,'Njoroge Wamboi',NULL,'2020-04-08',NULL,'suspended',15,'suspended','2026-10-16 02:43:32','2026-10-16 02:46:51'),(46,NULL,'DL-KE-2045',NULL,'Njoroge Wamboi',NULL,'2022-07-07',NULL,'restricted',13,'restricted','2026-10-16 02:43:33','2026-10-16 02:46:51'),(47,NULL,'DL-KE-2046',NULL,'Gathoni Wangui',NULL,'2012-10-14',NULL,'suspended',30,'suspended','2026-10-16 02:43:33','2026-10-16 02:46:51'),(48,NULL,'DL-KE-2047',NULL,'Owino Akello',NULL,'2013-09-12',NULL,'suspended',22,'suspended','2026-10-16 02:43:34','2026-10-16 02:46:51'),(49,NULL,'DL-KE-2048',NULL,'Odera Atieno',NULL,'2017-04-16',NULL,'suspended',22,'suspended','2026-10-16 02:43:34','2026-10-16 02:46:51'),(50,NULL,'DL-KE-2049',NULL,'Njoroge Wamboi',NULL,'2025-03-29',NULL,'active',2,'active','2026-10-16 02:43:35','2026-10-16 02:46:51');
/*!40000 ALTER TABLE `driver_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_audits`
--

DROP TABLE IF EXISTS `login_audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_audits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `successful` tinyint(1) NOT NULL DEFAULT 0,
  `failure_reason` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `login_audits_user_id_foreign` (`user_id`),
  KEY `login_audits_email_index` (`email`),
  KEY `login_audits_successful_index` (`successful`),
  KEY `login_audits_attempted_at_index` (`attempted_at`),
  CONSTRAINT `login_audits_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_audits`
--

LOCK TABLES `login_audits` WRITE;
/*!40000 ALTER TABLE `login_audits` DISABLE KEYS */;
INSERT INTO `login_audits` VALUES (1,NULL,'admin@ntsa.go.ke',0,'invalid_credentials','127.0.0.1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9549','2026-10-16 02:44:13','2026-10-16 02:44:13','2026-10-16 02:44:13'),(2,59,'admin@ntsa.go.ke',1,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9549','2026-10-16 02:46:13','2026-10-16 02:46:13','2026-10-16 02:46:13'),(3,59,'admin@ntsa.go.ke',1,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9549','2026-10-16 02:46:50','2026-10-16 02:46:50','2026-10-16 02:46:50'),(4,59,'admin@ntsa.go.ke',1,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9549','2026-10-16 02:47:15','2026-10-16 02:47:15','2026-10-16 02:47:15'),(5,NULL,'test@test.com',0,'invalid_credentials','127.0.0.1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9549','2026-10-16 02:48:04','2026-10-16 02:48:04','2026-10-16 02:48:04'),(6,59,'admin@ntsa.go.ke',1,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9549','2026-10-16 02:49:22','2026-10-16 02:49:22','2026-10-16 02:49:22'),(7,59,'admin@ntsa.go.ke',1,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36','2026-10-16 02:50:07','2026-10-16 02:50:07','2026-10-16 02:50:07');
/*!40000 ALTER TABLE `login_audits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_01_01_000001_create_driver_profiles_table',1),(5,'2026_01_01_000002_create_violations_table',1),(6,'2026_01_01_000003_create_demerit_ledger_entries_table',1),(7,'2026_01_01_000004_create_risk_assessments_table',1),(8,'2026_01_01_000005_create_sanction_actions_table',1),(9,'2026_01_01_000006_create_payments_table',1),(10,'2026_08_10_100507_create_personal_access_tokens_table',1),(11,'2026_08_31_000006_update_risk_class_enum_to_moderate',1),(12,'2026_08_31_000007_create_appeals_table',1),(13,'2026_08_31_000008_add_portal_status_to_sanction_actions_table',1),(14,'2026_08_31_000009_add_portal_status_to_violations_table',1),(15,'2026_08_31_000010_create_sessions_table_for_database_driver',1),(16,'2026_08_31_000011_add_profile_photo_path_to_driver_profiles_table',1),(17,'2026_08_31_000012_expand_relational_schema_clusters',1),(18,'2026_08_31_000013_seed_roles_and_backfill_users',1),(19,'2026_08_31_000014_create_login_audits_table',1),(20,'2026_08_31_000015_enforce_closed_loop_flow_fields',1),(21,'2026_08_31_000016_add_days_since_last_offence_to_ledger_entries',1),(22,'2026_08_31_000017_create_system_notifications_table',1),(23,'2026_08_31_000018_add_performance_indexes',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `officer_assignments`
--

DROP TABLE IF EXISTS `officer_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `officer_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `officer_user_id` bigint(20) unsigned NOT NULL,
  `station_id` bigint(20) unsigned NOT NULL,
  `badge_number` varchar(255) NOT NULL,
  `shift_type` enum('day','night','swing') NOT NULL DEFAULT 'day',
  `assignment_start_date` date NOT NULL,
  `assignment_end_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `officer_assignments_officer_user_id_foreign` (`officer_user_id`),
  KEY `officer_assignments_station_id_foreign` (`station_id`),
  CONSTRAINT `officer_assignments_officer_user_id_foreign` FOREIGN KEY (`officer_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `officer_assignments_station_id_foreign` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `officer_assignments`
--

LOCK TABLES `officer_assignments` WRITE;
/*!40000 ALTER TABLE `officer_assignments` DISABLE KEYS */;
INSERT INTO `officer_assignments` VALUES (1,1,1,'BADGE-00001','night','2026-06-22',NULL,'2026-10-16 02:43:08','2026-10-16 02:43:08'),(2,2,2,'BADGE-00002','day','2026-07-16',NULL,'2026-10-16 02:43:08','2026-10-16 02:43:08'),(3,3,3,'BADGE-00003','night','2026-07-29',NULL,'2026-10-16 02:43:09','2026-10-16 02:43:09'),(4,4,4,'BADGE-00004','swing','2026-05-13',NULL,'2026-10-16 02:43:09','2026-10-16 02:43:09'),(5,5,5,'BADGE-00005','night','2026-05-27',NULL,'2026-10-16 02:43:10','2026-10-16 02:43:10'),(6,6,6,'BADGE-00006','swing','2026-07-29',NULL,'2026-10-16 02:43:10','2026-10-16 02:43:10'),(7,7,7,'BADGE-00007','night','2025-12-17',NULL,'2026-10-16 02:43:11','2026-10-16 02:43:11'),(8,8,8,'BADGE-00008','swing','2026-03-28',NULL,'2026-10-16 02:43:11','2026-10-16 02:43:11');
/*!40000 ALTER TABLE `officer_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ledger_entry_id` bigint(20) unsigned DEFAULT NULL,
  `violation_id` bigint(20) unsigned NOT NULL,
  `driver_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `amount_paid` decimal(10,2) DEFAULT NULL,
  `method` enum('mpesa','card') NOT NULL DEFAULT 'mpesa',
  `status` enum('pending','completed','failed') NOT NULL DEFAULT 'pending',
  `transaction_ref` varchar(255) DEFAULT NULL,
  `reference_number` varchar(255) DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'KES',
  `payment_method` varchar(255) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payments_violation_id_foreign` (`violation_id`),
  KEY `payments_driver_id_foreign` (`driver_id`),
  KEY `payments_ledger_entry_id_foreign` (`ledger_entry_id`),
  CONSTRAINT `payments_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `driver_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_ledger_entry_id_foreign` FOREIGN KEY (`ledger_entry_id`) REFERENCES `demerit_ledger_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_violation_id_foreign` FOREIGN KEY (`violation_id`) REFERENCES `violations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=141 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,NULL,1,1,12000.00,NULL,'mpesa','completed','TRX-8821-2447',NULL,'KES',NULL,'2026-04-19 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(2,NULL,2,1,5000.00,NULL,'card','failed','TRX-9128-1469',NULL,'KES',NULL,NULL,'2026-10-16 02:43:35','2026-10-16 02:43:35'),(3,NULL,3,1,2000.00,NULL,'mpesa','failed','TRX-3922-0536',NULL,'KES',NULL,NULL,'2026-10-16 02:43:35','2026-10-16 02:43:35'),(4,NULL,4,1,12000.00,NULL,'mpesa','completed','TRX-5459-1618',NULL,'KES',NULL,'2026-10-06 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(5,NULL,5,1,7000.00,NULL,'mpesa','completed','TRX-5780-3406',NULL,'KES',NULL,'2026-02-20 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(6,NULL,6,2,7000.00,NULL,'card','completed','TRX-9268-5643',NULL,'KES',NULL,'2025-11-16 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(7,NULL,7,2,1000.00,NULL,'mpesa','failed','TRX-6482-4215',NULL,'KES',NULL,NULL,'2026-10-16 02:43:35','2026-10-16 02:43:35'),(8,NULL,8,2,1000.00,NULL,'mpesa','completed','TRX-4693-1883',NULL,'KES',NULL,'2026-03-19 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(9,NULL,9,2,8000.00,NULL,'card','completed','TRX-7923-3183',NULL,'KES',NULL,'2026-02-05 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(10,NULL,10,2,4000.00,NULL,'card','completed','TRX-3382-2000',NULL,'KES',NULL,'2025-11-25 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(11,NULL,11,3,3000.00,NULL,'mpesa','failed','TRX-2181-5162',NULL,'KES',NULL,NULL,'2026-10-16 02:43:35','2026-10-16 02:43:35'),(12,NULL,13,3,7000.00,NULL,'card','completed','TRX-3120-8388',NULL,'KES',NULL,'2025-11-14 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(13,NULL,14,3,3000.00,NULL,'card','completed','TRX-2284-5151',NULL,'KES',NULL,'2026-09-06 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(14,NULL,15,3,8000.00,NULL,'card','pending','TRX-0467-5705',NULL,'KES',NULL,NULL,'2026-10-16 02:43:35','2026-10-16 02:43:35'),(15,NULL,16,3,4000.00,NULL,'card','completed','TRX-9115-3955',NULL,'KES',NULL,'2026-03-06 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(16,NULL,18,4,10000.00,NULL,'card','pending','TRX-9994-3608',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(17,NULL,19,5,3000.00,NULL,'mpesa','pending','TRX-4227-5375',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(18,NULL,20,5,5000.00,NULL,'mpesa','completed','TRX-7727-5526',NULL,'KES',NULL,'2026-08-27 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(19,NULL,21,5,10000.00,NULL,'card','pending','TRX-0588-7627',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(20,NULL,23,7,8000.00,NULL,'card','failed','TRX-1421-5067',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(21,NULL,24,7,500.00,NULL,'mpesa','completed','TRX-3590-0217',NULL,'KES',NULL,'2025-12-13 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(22,NULL,25,7,12000.00,NULL,'card','completed','TRX-5944-7904',NULL,'KES',NULL,'2026-08-30 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(23,NULL,26,7,4000.00,NULL,'card','failed','TRX-9756-2807',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(24,NULL,27,7,2000.00,NULL,'card','completed','TRX-9229-5532',NULL,'KES',NULL,'2026-05-11 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(25,NULL,28,8,7000.00,NULL,'card','pending','TRX-1522-4993',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(26,NULL,30,9,5000.00,NULL,'mpesa','completed','TRX-0503-9387',NULL,'KES',NULL,'2026-04-08 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(27,NULL,32,9,5000.00,NULL,'mpesa','pending','TRX-9514-0584',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(28,NULL,33,9,10000.00,NULL,'card','completed','TRX-8637-8277',NULL,'KES',NULL,'2026-07-09 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(29,NULL,34,9,7000.00,NULL,'card','completed','TRX-1798-1408',NULL,'KES',NULL,'2026-09-26 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(30,NULL,35,10,12000.00,NULL,'mpesa','failed','TRX-2329-2202',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(31,NULL,36,10,3000.00,NULL,'mpesa','failed','TRX-5583-1278',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(32,NULL,37,10,500.00,NULL,'mpesa','pending','TRX-2371-9285',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(33,NULL,38,11,3000.00,NULL,'mpesa','completed','TRX-7255-5164',NULL,'KES',NULL,'2026-01-15 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(34,NULL,39,11,2000.00,NULL,'card','completed','TRX-1969-4761',NULL,'KES',NULL,'2026-02-05 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(35,NULL,40,11,10000.00,NULL,'card','completed','TRX-6093-5496',NULL,'KES',NULL,'2025-11-08 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(36,NULL,41,11,2000.00,NULL,'mpesa','pending','TRX-3338-4150',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(37,NULL,43,11,1000.00,NULL,'card','failed','TRX-7438-8620',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(38,NULL,44,12,5000.00,NULL,'card','completed','TRX-0008-3012',NULL,'KES',NULL,'2026-03-13 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(39,NULL,46,12,5000.00,NULL,'card','pending','TRX-8391-1755',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(40,NULL,47,12,12000.00,NULL,'mpesa','completed','TRX-6400-5046',NULL,'KES',NULL,'2025-11-12 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(41,NULL,48,12,5000.00,NULL,'card','pending','TRX-0885-4127',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(42,NULL,49,12,7000.00,NULL,'card','failed','TRX-9978-7969',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(43,NULL,50,13,8000.00,NULL,'card','failed','TRX-9878-2050',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(44,NULL,52,14,2000.00,NULL,'mpesa','completed','TRX-0877-7679',NULL,'KES',NULL,'2026-01-25 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(45,NULL,53,14,4000.00,NULL,'mpesa','pending','TRX-1671-1005',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(46,NULL,54,14,10000.00,NULL,'mpesa','pending','TRX-6076-9104',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(47,NULL,55,14,4000.00,NULL,'mpesa','failed','TRX-6860-6115',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(48,NULL,56,15,12000.00,NULL,'mpesa','pending','TRX-1644-0446',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(49,NULL,58,15,2000.00,NULL,'card','completed','TRX-2830-1144',NULL,'KES',NULL,'2026-08-27 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(50,NULL,60,16,3000.00,NULL,'card','completed','TRX-3813-4768',NULL,'KES',NULL,'2025-11-27 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(51,NULL,61,16,3000.00,NULL,'mpesa','completed','TRX-3900-5604',NULL,'KES',NULL,'2026-02-04 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(52,NULL,62,16,8000.00,NULL,'mpesa','completed','TRX-1191-0937',NULL,'KES',NULL,'2026-02-11 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(53,NULL,63,16,10000.00,NULL,'mpesa','completed','TRX-8535-8910',NULL,'KES',NULL,'2026-08-07 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(54,NULL,64,16,4000.00,NULL,'mpesa','failed','TRX-2372-5675',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(55,NULL,65,17,8000.00,NULL,'mpesa','pending','TRX-2471-9674',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(56,NULL,66,18,5000.00,NULL,'mpesa','completed','TRX-5356-4917',NULL,'KES',NULL,'2026-09-20 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(57,NULL,68,18,3000.00,NULL,'card','pending','TRX-4674-5330',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(58,NULL,69,18,1000.00,NULL,'mpesa','pending','TRX-1025-0042',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(59,NULL,70,18,12000.00,NULL,'mpesa','pending','TRX-9051-3860',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(60,NULL,71,18,3000.00,NULL,'card','completed','TRX-7104-7214',NULL,'KES',NULL,'2026-08-15 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(61,NULL,72,19,2000.00,NULL,'mpesa','pending','TRX-5549-5967',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(62,NULL,73,20,3000.00,NULL,'mpesa','completed','TRX-8707-9940',NULL,'KES',NULL,'2026-08-03 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(63,NULL,74,20,8000.00,NULL,'card','completed','TRX-7355-8686',NULL,'KES',NULL,'2026-02-27 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(64,NULL,75,21,7000.00,NULL,'mpesa','pending','TRX-5518-9542',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(65,NULL,76,21,3000.00,NULL,'card','completed','TRX-4999-1490',NULL,'KES',NULL,'2026-02-28 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(66,NULL,77,21,3000.00,NULL,'mpesa','completed','TRX-6526-2382',NULL,'KES',NULL,'2026-03-28 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(67,NULL,78,21,8000.00,NULL,'card','completed','TRX-6491-7336',NULL,'KES',NULL,'2026-01-13 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(68,NULL,79,21,3000.00,NULL,'mpesa','completed','TRX-7698-0971',NULL,'KES',NULL,'2026-04-26 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(69,NULL,80,22,4000.00,NULL,'mpesa','failed','TRX-9321-3536',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(70,NULL,83,22,1000.00,NULL,'mpesa','completed','TRX-7760-1507',NULL,'KES',NULL,'2026-01-20 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(71,NULL,84,22,3000.00,NULL,'mpesa','completed','TRX-4736-6936',NULL,'KES',NULL,'2026-06-18 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(72,NULL,85,23,10000.00,NULL,'mpesa','completed','TRX-1275-6996',NULL,'KES',NULL,'2026-05-25 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(73,NULL,86,23,12000.00,NULL,'mpesa','completed','TRX-1008-6186',NULL,'KES',NULL,'2026-07-30 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(74,NULL,88,23,12000.00,NULL,'card','failed','TRX-7411-8655',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(75,NULL,89,23,3000.00,NULL,'mpesa','completed','TRX-3838-6033',NULL,'KES',NULL,'2026-05-25 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(76,NULL,91,24,8000.00,NULL,'card','completed','TRX-6696-6477',NULL,'KES',NULL,'2026-08-22 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(77,NULL,92,24,12000.00,NULL,'card','completed','TRX-3362-1255',NULL,'KES',NULL,'2026-01-31 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(78,NULL,94,24,2000.00,NULL,'mpesa','completed','TRX-4221-0240',NULL,'KES',NULL,'2026-09-25 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(79,NULL,95,24,500.00,NULL,'mpesa','pending','TRX-0218-3174',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(80,NULL,96,24,3000.00,NULL,'card','completed','TRX-9117-2006',NULL,'KES',NULL,'2026-02-28 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(81,NULL,97,25,1000.00,NULL,'card','pending','TRX-8871-9562',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(82,NULL,98,25,8000.00,NULL,'card','completed','TRX-5911-5419',NULL,'KES',NULL,'2026-08-13 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(83,NULL,99,26,2000.00,NULL,'card','completed','TRX-6237-9246',NULL,'KES',NULL,'2026-02-20 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(84,NULL,102,28,7000.00,NULL,'mpesa','failed','TRX-8605-8351',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(85,NULL,103,28,2000.00,NULL,'card','pending','TRX-3408-1349',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(86,NULL,104,28,500.00,NULL,'card','failed','TRX-4735-8130',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(87,NULL,107,29,5000.00,NULL,'mpesa','completed','TRX-6554-4536',NULL,'KES',NULL,'2026-08-20 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(88,NULL,108,29,5000.00,NULL,'mpesa','pending','TRX-0725-1945',NULL,'KES',NULL,NULL,'2026-10-16 02:43:36','2026-10-16 02:43:36'),(89,NULL,110,31,12000.00,NULL,'mpesa','completed','TRX-8207-0321',NULL,'KES',NULL,'2026-06-29 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(90,NULL,111,31,3000.00,NULL,'card','completed','TRX-9974-5698',NULL,'KES',NULL,'2026-05-26 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(91,NULL,113,31,2000.00,NULL,'mpesa','completed','TRX-0863-5343',NULL,'KES',NULL,'2026-03-07 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(92,NULL,114,31,2000.00,NULL,'mpesa','completed','TRX-1045-3274',NULL,'KES',NULL,'2026-04-17 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(93,NULL,115,32,7000.00,NULL,'card','completed','TRX-8370-9702',NULL,'KES',NULL,'2026-04-04 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(94,NULL,116,32,12000.00,NULL,'mpesa','completed','TRX-2267-5214',NULL,'KES',NULL,'2026-07-09 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(95,NULL,117,32,4000.00,NULL,'mpesa','pending','TRX-9691-3545',NULL,'KES',NULL,NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(96,NULL,118,32,500.00,NULL,'card','completed','TRX-4331-6408',NULL,'KES',NULL,'2026-08-16 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(97,NULL,119,33,1000.00,NULL,'card','pending','TRX-4881-3606',NULL,'KES',NULL,NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(98,NULL,120,33,7000.00,NULL,'mpesa','pending','TRX-7216-3804',NULL,'KES',NULL,NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(99,NULL,121,33,10000.00,NULL,'mpesa','completed','TRX-2787-5261',NULL,'KES',NULL,'2026-05-28 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(100,NULL,122,33,4000.00,NULL,'mpesa','completed','TRX-4696-7141',NULL,'KES',NULL,'2026-04-05 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(101,NULL,123,33,10000.00,NULL,'mpesa','completed','TRX-2977-8682',NULL,'KES',NULL,'2026-05-18 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(102,NULL,124,34,8000.00,NULL,'mpesa','pending','TRX-8408-2629',NULL,'KES',NULL,NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(103,NULL,125,34,7000.00,NULL,'card','completed','TRX-8094-9624',NULL,'KES',NULL,'2025-11-29 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(104,NULL,126,35,8000.00,NULL,'card','completed','TRX-0940-0356',NULL,'KES',NULL,'2026-07-15 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(105,NULL,128,35,1000.00,NULL,'card','completed','TRX-9842-5302',NULL,'KES',NULL,'2026-10-06 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(106,NULL,131,37,8000.00,NULL,'card','completed','TRX-1829-3816',NULL,'KES',NULL,'2026-01-18 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(107,NULL,132,37,10000.00,NULL,'mpesa','completed','TRX-5645-2202',NULL,'KES',NULL,'2026-09-14 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(108,NULL,135,39,8000.00,NULL,'mpesa','completed','TRX-2813-7244',NULL,'KES',NULL,'2026-01-01 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(109,NULL,136,39,7000.00,NULL,'card','failed','TRX-5120-6073',NULL,'KES',NULL,NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(110,NULL,137,39,5000.00,NULL,'card','completed','TRX-3256-5066',NULL,'KES',NULL,'2026-07-29 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(111,NULL,139,39,3000.00,NULL,'card','pending','TRX-6160-0421',NULL,'KES',NULL,NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(112,NULL,140,40,12000.00,NULL,'mpesa','pending','TRX-2658-8046',NULL,'KES',NULL,NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(113,NULL,143,40,1000.00,NULL,'mpesa','pending','TRX-2442-2723',NULL,'KES',NULL,NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(114,NULL,144,40,1000.00,NULL,'card','pending','TRX-1331-6146',NULL,'KES',NULL,NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(115,NULL,146,41,2000.00,NULL,'mpesa','pending','TRX-9552-4780',NULL,'KES',NULL,NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(116,NULL,147,41,4000.00,NULL,'mpesa','completed','TRX-2105-2120',NULL,'KES',NULL,'2026-01-10 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(117,NULL,148,41,2000.00,NULL,'card','failed','TRX-0315-6070',NULL,'KES',NULL,NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(118,NULL,149,41,12000.00,NULL,'mpesa','completed','TRX-3478-6326',NULL,'KES',NULL,'2026-07-01 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(119,NULL,150,42,4000.00,NULL,'mpesa','pending','TRX-8416-3962',NULL,'KES',NULL,NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(120,NULL,152,42,10000.00,NULL,'card','completed','TRX-1457-2975',NULL,'KES',NULL,'2026-04-11 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(121,NULL,155,43,3000.00,NULL,'mpesa','failed','TRX-1733-9394',NULL,'KES',NULL,NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(122,NULL,156,43,5000.00,NULL,'mpesa','completed','TRX-2619-7675',NULL,'KES',NULL,'2026-02-16 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(123,NULL,157,43,3000.00,NULL,'card','completed','TRX-5472-4533',NULL,'KES',NULL,'2026-01-27 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(124,NULL,159,44,8000.00,NULL,'card','completed','TRX-4417-6323',NULL,'KES',NULL,'2026-05-02 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(125,NULL,161,44,1000.00,NULL,'card','completed','TRX-4902-8514',NULL,'KES',NULL,'2025-11-17 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(126,NULL,162,44,10000.00,NULL,'mpesa','completed','TRX-9303-0544',NULL,'KES',NULL,'2026-07-01 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(127,NULL,164,45,5000.00,NULL,'mpesa','failed','TRX-2523-8557',NULL,'KES',NULL,NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(128,NULL,165,45,3000.00,NULL,'mpesa','failed','TRX-1135-5287',NULL,'KES',NULL,NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(129,NULL,167,46,3000.00,NULL,'mpesa','failed','TRX-3731-5774',NULL,'KES',NULL,NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(130,NULL,168,46,10000.00,NULL,'card','completed','TRX-7578-8807',NULL,'KES',NULL,'2025-11-18 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(131,NULL,169,46,500.00,NULL,'card','completed','TRX-5980-0220',NULL,'KES',NULL,'2026-08-21 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(132,NULL,170,47,5000.00,NULL,'mpesa','completed','TRX-5606-2868',NULL,'KES',NULL,'2026-02-20 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(133,NULL,171,47,1000.00,NULL,'mpesa','completed','TRX-5875-2889',NULL,'KES',NULL,'2026-02-25 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(134,NULL,172,47,5000.00,NULL,'card','completed','TRX-6650-8841',NULL,'KES',NULL,'2026-08-20 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(135,NULL,173,47,8000.00,NULL,'card','pending','TRX-1897-9477',NULL,'KES',NULL,NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(136,NULL,174,47,7000.00,NULL,'mpesa','pending','TRX-1150-5244',NULL,'KES',NULL,NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(137,NULL,176,48,8000.00,NULL,'mpesa','completed','TRX-3793-7736',NULL,'KES',NULL,'2026-07-30 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(138,NULL,180,49,12000.00,NULL,'card','pending','TRX-4414-6648',NULL,'KES',NULL,NULL,'2026-10-16 02:43:37','2026-10-16 02:43:37'),(139,NULL,181,49,500.00,NULL,'mpesa','completed','TRX-6497-2481',NULL,'KES',NULL,'2026-09-27 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(140,NULL,186,50,500.00,NULL,'card','completed','TRX-4774-9026',NULL,'KES',NULL,'2026-06-29 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `review_logs`
--

DROP TABLE IF EXISTS `review_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `review_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sanction_action_id` bigint(20) unsigned NOT NULL,
  `officer_assignment_id` bigint(20) unsigned DEFAULT NULL,
  `reviewed_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `decision` enum('upheld','amended','reversed') DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `review_logs_officer_assignment_id_foreign` (`officer_assignment_id`),
  KEY `review_logs_reviewed_by_user_id_foreign` (`reviewed_by_user_id`),
  KEY `idx_review_logs_sanction_decision` (`sanction_action_id`,`decision`),
  KEY `idx_review_logs_reviewed_at` (`reviewed_at`),
  CONSTRAINT `review_logs_officer_assignment_id_foreign` FOREIGN KEY (`officer_assignment_id`) REFERENCES `officer_assignments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `review_logs_reviewed_by_user_id_foreign` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `review_logs_sanction_action_id_foreign` FOREIGN KEY (`sanction_action_id`) REFERENCES `sanction_actions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `review_logs`
--

LOCK TABLES `review_logs` WRITE;
/*!40000 ALTER TABLE `review_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `review_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `risk_assessments`
--

DROP TABLE IF EXISTS `risk_assessments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `risk_assessments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `driver_id` bigint(20) unsigned NOT NULL,
  `risk_prediction_id` bigint(20) unsigned DEFAULT NULL,
  `risk_score` decimal(5,4) NOT NULL,
  `risk_class` enum('low','moderate','high') NOT NULL,
  `model_version` varchar(255) NOT NULL,
  `feature_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`feature_snapshot`)),
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `risk_assessments_risk_prediction_id_foreign` (`risk_prediction_id`),
  KEY `idx_risk_assessments_driver_generated_at` (`driver_id`,`generated_at`),
  CONSTRAINT `risk_assessments_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `driver_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `risk_assessments_risk_prediction_id_foreign` FOREIGN KEY (`risk_prediction_id`) REFERENCES `risk_predictions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `risk_assessments`
--

LOCK TABLES `risk_assessments` WRITE;
/*!40000 ALTER TABLE `risk_assessments` DISABLE KEYS */;
INSERT INTO `risk_assessments` VALUES (1,1,1,0.9800,'high','rf_v1','{\"active_points\":30,\"total_violations\":5,\"days_since_last\":2}','2026-10-16 05:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(2,2,2,0.9300,'high','rf_v1','{\"active_points\":20,\"total_violations\":5,\"days_since_last\":79}','2026-10-16 05:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(3,3,3,0.9800,'high','rf_v1','{\"active_points\":29,\"total_violations\":6,\"days_since_last\":335}','2026-10-16 05:43:36','2026-10-16 02:43:35','2026-10-16 02:43:35'),(4,4,4,0.5600,'moderate','rf_v1','{\"active_points\":12,\"total_violations\":2,\"days_since_last\":304}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(5,5,5,0.7950,'high','rf_v1','{\"active_points\":17,\"total_violations\":3,\"days_since_last\":10}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(6,6,6,0.3200,'low','rf_v1','{\"active_points\":2,\"total_violations\":1,\"days_since_last\":73}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(7,7,7,0.9500,'high','rf_v1','{\"active_points\":22,\"total_violations\":5,\"days_since_last\":221}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(8,8,8,0.6600,'moderate','rf_v1','{\"active_points\":12,\"total_violations\":2,\"days_since_last\":38}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(9,9,9,0.9800,'high','rf_v1','{\"active_points\":32,\"total_violations\":5,\"days_since_last\":148}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(10,10,10,0.7250,'high','rf_v1','{\"active_points\":13,\"total_violations\":3,\"days_since_last\":334}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(11,11,11,0.9800,'high','rf_v1','{\"active_points\":21,\"total_violations\":6,\"days_since_last\":252}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(12,12,12,0.9800,'high','rf_v1','{\"active_points\":30,\"total_violations\":6,\"days_since_last\":269}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(13,13,13,0.4800,'moderate','rf_v1','{\"active_points\":6,\"total_violations\":1,\"days_since_last\":33}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(14,14,14,0.9800,'high','rf_v1','{\"active_points\":25,\"total_violations\":5,\"days_since_last\":70}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(15,15,15,0.8600,'high','rf_v1','{\"active_points\":22,\"total_violations\":4,\"days_since_last\":332}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(16,16,16,0.9800,'high','rf_v1','{\"active_points\":26,\"total_violations\":5,\"days_since_last\":59}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(17,17,17,0.4400,'moderate','rf_v1','{\"active_points\":6,\"total_violations\":1,\"days_since_last\":250}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(18,18,18,0.9800,'high','rf_v1','{\"active_points\":27,\"total_violations\":6,\"days_since_last\":330}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(19,19,19,0.2450,'low','rf_v1','{\"active_points\":3,\"total_violations\":1,\"days_since_last\":1}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(20,20,20,0.5000,'moderate','rf_v1','{\"active_points\":10,\"total_violations\":2,\"days_since_last\":204}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(21,21,21,0.9400,'high','rf_v1','{\"active_points\":24,\"total_violations\":5,\"days_since_last\":76}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(22,22,22,0.9800,'high','rf_v1','{\"active_points\":24,\"total_violations\":5,\"days_since_last\":194}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(23,23,23,0.9800,'high','rf_v1','{\"active_points\":39,\"total_violations\":6,\"days_since_last\":168}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(24,24,24,0.9800,'high','rf_v1','{\"active_points\":26,\"total_violations\":6,\"days_since_last\":69}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(25,25,25,0.5700,'moderate','rf_v1','{\"active_points\":8,\"total_violations\":2,\"days_since_last\":227}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(26,26,26,0.2550,'low','rf_v1','{\"active_points\":3,\"total_violations\":1,\"days_since_last\":265}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(27,27,27,0.4500,'moderate','rf_v1','{\"active_points\":8,\"total_violations\":2,\"days_since_last\":3}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(28,28,28,0.8000,'high','rf_v1','{\"active_points\":14,\"total_violations\":4,\"days_since_last\":85}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(29,29,29,0.5700,'moderate','rf_v1','{\"active_points\":12,\"total_violations\":3,\"days_since_last\":46}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(30,30,30,0.1750,'low','rf_v1','{\"active_points\":1,\"total_violations\":1,\"days_since_last\":328}','2026-10-16 05:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(31,31,31,0.8500,'high','rf_v1','{\"active_points\":20,\"total_violations\":5,\"days_since_last\":92}','2026-10-16 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(32,32,32,0.8250,'high','rf_v1','{\"active_points\":19,\"total_violations\":4,\"days_since_last\":321}','2026-10-16 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(33,33,33,0.9800,'high','rf_v1','{\"active_points\":28,\"total_violations\":5,\"days_since_last\":104}','2026-10-16 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(34,34,34,0.5600,'moderate','rf_v1','{\"active_points\":12,\"total_violations\":2,\"days_since_last\":345}','2026-10-16 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(35,35,35,0.6900,'moderate','rf_v1','{\"active_points\":12,\"total_violations\":3,\"days_since_last\":331}','2026-10-16 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(36,36,36,0.4200,'moderate','rf_v1','{\"active_points\":8,\"total_violations\":2,\"days_since_last\":275}','2026-10-16 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(37,37,37,0.5700,'moderate','rf_v1','{\"active_points\":14,\"total_violations\":2,\"days_since_last\":102}','2026-10-16 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(38,38,38,0.4300,'moderate','rf_v1','{\"active_points\":6,\"total_violations\":2,\"days_since_last\":219}','2026-10-16 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(39,39,39,0.9800,'high','rf_v1','{\"active_points\":26,\"total_violations\":5,\"days_since_last\":281}','2026-10-16 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(40,40,40,0.8350,'high','rf_v1','{\"active_points\":19,\"total_violations\":5,\"days_since_last\":261}','2026-10-16 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(41,41,41,0.8650,'high','rf_v1','{\"active_points\":19,\"total_violations\":5,\"days_since_last\":101}','2026-10-16 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(42,42,42,0.9000,'high','rf_v1','{\"active_points\":20,\"total_violations\":3,\"days_since_last\":249}','2026-10-16 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(43,43,43,0.9750,'high','rf_v1','{\"active_points\":23,\"total_violations\":5,\"days_since_last\":185}','2026-10-16 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(44,44,44,0.9800,'high','rf_v1','{\"active_points\":28,\"total_violations\":5,\"days_since_last\":364}','2026-10-16 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(45,45,45,0.7050,'high','rf_v1','{\"active_points\":15,\"total_violations\":4,\"days_since_last\":129}','2026-10-16 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(46,46,46,0.6750,'moderate','rf_v1','{\"active_points\":13,\"total_violations\":3,\"days_since_last\":359}','2026-10-16 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(47,47,47,0.9800,'high','rf_v1','{\"active_points\":30,\"total_violations\":6,\"days_since_last\":330}','2026-10-16 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(48,48,48,0.8200,'high','rf_v1','{\"active_points\":22,\"total_violations\":4,\"days_since_last\":194}','2026-10-16 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(49,49,49,0.9300,'high','rf_v1','{\"active_points\":22,\"total_violations\":5,\"days_since_last\":164}','2026-10-16 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(50,50,50,0.3200,'low','rf_v1','{\"active_points\":2,\"total_violations\":2,\"days_since_last\":172}','2026-10-16 05:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37');
/*!40000 ALTER TABLE `risk_assessments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `risk_predictions`
--

DROP TABLE IF EXISTS `risk_predictions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `risk_predictions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `driver_id` bigint(20) unsigned NOT NULL,
  `risk_score` decimal(5,4) NOT NULL,
  `risk_class` enum('low','moderate','high') NOT NULL,
  `model_version` varchar(255) NOT NULL,
  `confidence_level` decimal(5,4) DEFAULT NULL,
  `predicted_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_risk_predictions_driver_predicted_at` (`driver_id`,`predicted_at`),
  KEY `idx_risk_predictions_class_predicted_at` (`risk_class`,`predicted_at`),
  CONSTRAINT `risk_predictions_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `driver_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `risk_predictions`
--

LOCK TABLES `risk_predictions` WRITE;
/*!40000 ALTER TABLE `risk_predictions` DISABLE KEYS */;
INSERT INTO `risk_predictions` VALUES (1,1,0.9800,'high','rf_v1',0.9800,'2026-10-16 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(2,2,0.9300,'high','rf_v1',0.9300,'2026-10-16 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(3,3,0.9800,'high','rf_v1',0.9800,'2026-10-16 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(4,4,0.5600,'moderate','rf_v1',0.5600,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(5,5,0.7950,'high','rf_v1',0.7950,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(6,6,0.3200,'low','rf_v1',0.3200,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(7,7,0.9500,'high','rf_v1',0.9500,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(8,8,0.6600,'moderate','rf_v1',0.6600,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(9,9,0.9800,'high','rf_v1',0.9800,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(10,10,0.7250,'high','rf_v1',0.7250,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(11,11,0.9800,'high','rf_v1',0.9800,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(12,12,0.9800,'high','rf_v1',0.9800,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(13,13,0.4800,'moderate','rf_v1',0.4800,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(14,14,0.9800,'high','rf_v1',0.9800,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(15,15,0.8600,'high','rf_v1',0.8600,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(16,16,0.9800,'high','rf_v1',0.9800,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(17,17,0.4400,'moderate','rf_v1',0.4400,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(18,18,0.9800,'high','rf_v1',0.9800,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(19,19,0.2450,'low','rf_v1',0.2450,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(20,20,0.5000,'moderate','rf_v1',0.5000,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(21,21,0.9400,'high','rf_v1',0.9400,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(22,22,0.9800,'high','rf_v1',0.9800,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(23,23,0.9800,'high','rf_v1',0.9800,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(24,24,0.9800,'high','rf_v1',0.9800,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(25,25,0.5700,'moderate','rf_v1',0.5700,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(26,26,0.2550,'low','rf_v1',0.2550,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(27,27,0.4500,'moderate','rf_v1',0.4500,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(28,28,0.8000,'high','rf_v1',0.8000,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(29,29,0.5700,'moderate','rf_v1',0.5700,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(30,30,0.1750,'low','rf_v1',0.1750,'2026-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(31,31,0.8500,'high','rf_v1',0.8500,'2026-10-16 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(32,32,0.8250,'high','rf_v1',0.8250,'2026-10-16 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(33,33,0.9800,'high','rf_v1',0.9800,'2026-10-16 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(34,34,0.5600,'moderate','rf_v1',0.5600,'2026-10-16 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(35,35,0.6900,'moderate','rf_v1',0.6900,'2026-10-16 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(36,36,0.4200,'moderate','rf_v1',0.4200,'2026-10-16 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(37,37,0.5700,'moderate','rf_v1',0.5700,'2026-10-16 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(38,38,0.4300,'moderate','rf_v1',0.4300,'2026-10-16 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(39,39,0.9800,'high','rf_v1',0.9800,'2026-10-16 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(40,40,0.8350,'high','rf_v1',0.8350,'2026-10-16 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(41,41,0.8650,'high','rf_v1',0.8650,'2026-10-16 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(42,42,0.9000,'high','rf_v1',0.9000,'2026-10-16 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(43,43,0.9750,'high','rf_v1',0.9750,'2026-10-16 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(44,44,0.9800,'high','rf_v1',0.9800,'2026-10-16 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(45,45,0.7050,'high','rf_v1',0.7050,'2026-10-16 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(46,46,0.6750,'moderate','rf_v1',0.6750,'2026-10-16 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(47,47,0.9800,'high','rf_v1',0.9800,'2026-10-16 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(48,48,0.8200,'high','rf_v1',0.8200,'2026-10-16 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(49,49,0.9300,'high','rf_v1',0.9300,'2026-10-16 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(50,50,0.3200,'low','rf_v1',0.3200,'2026-10-16 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37');
/*!40000 ALTER TABLE `risk_predictions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_name` varchar(255) NOT NULL,
  `permission_level` int(10) unsigned NOT NULL DEFAULT 1,
  `role_description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_role_name_unique` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'admin',100,'System administrator','2026-10-16 02:42:52','2026-10-16 02:42:52'),(2,'officer',70,'Field traffic enforcement officer','2026-10-16 02:42:52','2026-10-16 02:42:52'),(3,'motorist',30,'Registered motorist user','2026-10-16 02:42:52','2026-10-16 02:42:52');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sanction_actions`
--

DROP TABLE IF EXISTS `sanction_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sanction_actions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `driver_id` bigint(20) unsigned NOT NULL,
  `risk_assessment_id` bigint(20) unsigned DEFAULT NULL,
  `risk_prediction_id` bigint(20) unsigned DEFAULT NULL,
  `trigger_reason` varchar(255) NOT NULL,
  `action_type` enum('warning','profile_restriction','profile_suspension') NOT NULL,
  `sanction_type` varchar(255) DEFAULT NULL,
  `fine_amount` decimal(10,2) DEFAULT NULL,
  `effective_from` timestamp NULL DEFAULT NULL,
  `effective_to` timestamp NULL DEFAULT NULL,
  `issued_at` timestamp NULL DEFAULT NULL,
  `status` enum('active','lifted') NOT NULL DEFAULT 'active',
  `lifted_by` varchar(255) DEFAULT NULL,
  `lifted_at` timestamp NULL DEFAULT NULL,
  `webhook_status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `webhook_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`webhook_response`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sanction_actions_risk_assessment_id_foreign` (`risk_assessment_id`),
  KEY `sanction_actions_risk_prediction_id_foreign` (`risk_prediction_id`),
  KEY `idx_sanctions_driver_status` (`driver_id`,`status`),
  KEY `idx_sanctions_webhook_created_at` (`webhook_status`,`created_at`),
  CONSTRAINT `sanction_actions_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `driver_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sanction_actions_risk_assessment_id_foreign` FOREIGN KEY (`risk_assessment_id`) REFERENCES `risk_assessments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sanction_actions_risk_prediction_id_foreign` FOREIGN KEY (`risk_prediction_id`) REFERENCES `risk_predictions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sanction_actions`
--

LOCK TABLES `sanction_actions` WRITE;
/*!40000 ALTER TABLE `sanction_actions` DISABLE KEYS */;
INSERT INTO `sanction_actions` VALUES (1,1,1,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'pending','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:35+00:00\"}','2026-10-16 02:43:35','2026-10-16 02:43:35'),(2,2,2,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'sent','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:35+00:00\"}','2026-10-16 02:43:35','2026-10-16 02:43:35'),(3,3,3,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'sent','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:36+00:00\"}','2026-10-16 02:43:36','2026-10-16 02:43:36'),(4,4,4,NULL,'risk_score >= 0.40 AND active_points >= 10','profile_restriction',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'sent','{\"status\":\"queued\"}','2026-10-16 02:43:36','2026-10-16 02:43:36'),(5,5,5,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'pending','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:36+00:00\"}','2026-10-16 02:43:36','2026-10-16 02:43:36'),(6,7,7,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'pending','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:36+00:00\"}','2026-10-16 02:43:36','2026-10-16 02:43:36'),(7,8,8,NULL,'risk_score >= 0.40 AND active_points >= 10','profile_restriction',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'sent','{\"status\":\"queued\"}','2026-10-16 02:43:36','2026-10-16 02:43:36'),(8,9,9,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'pending','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:36+00:00\"}','2026-10-16 02:43:36','2026-10-16 02:43:36'),(9,10,10,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'failed','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:36+00:00\"}','2026-10-16 02:43:36','2026-10-16 02:43:36'),(10,11,11,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'pending','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:36+00:00\"}','2026-10-16 02:43:36','2026-10-16 02:43:36'),(11,12,12,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'sent','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:36+00:00\"}','2026-10-16 02:43:36','2026-10-16 02:43:36'),(12,14,14,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'pending','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:36+00:00\"}','2026-10-16 02:43:36','2026-10-16 02:43:36'),(13,15,15,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'pending','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:36+00:00\"}','2026-10-16 02:43:36','2026-10-16 02:43:36'),(14,16,16,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'sent','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:36+00:00\"}','2026-10-16 02:43:36','2026-10-16 02:43:36'),(15,18,18,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'pending','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:36+00:00\"}','2026-10-16 02:43:36','2026-10-16 02:43:36'),(16,20,20,NULL,'risk_score >= 0.40 AND active_points >= 10','profile_restriction',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'sent','{\"status\":\"queued\"}','2026-10-16 02:43:36','2026-10-16 02:43:36'),(17,21,21,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'sent','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:36+00:00\"}','2026-10-16 02:43:36','2026-10-16 02:43:36'),(18,22,22,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'sent','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:36+00:00\"}','2026-10-16 02:43:36','2026-10-16 02:43:36'),(19,23,23,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'pending','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:36+00:00\"}','2026-10-16 02:43:36','2026-10-16 02:43:36'),(20,24,24,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'sent','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:36+00:00\"}','2026-10-16 02:43:36','2026-10-16 02:43:36'),(21,28,28,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'pending','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:36+00:00\"}','2026-10-16 02:43:36','2026-10-16 02:43:36'),(22,29,29,NULL,'risk_score >= 0.40 AND active_points >= 10','profile_restriction',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'sent','{\"status\":\"queued\"}','2026-10-16 02:43:36','2026-10-16 02:43:36'),(23,31,31,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'pending','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:37+00:00\"}','2026-10-16 02:43:37','2026-10-16 02:43:37'),(24,32,32,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'pending','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:37+00:00\"}','2026-10-16 02:43:37','2026-10-16 02:43:37'),(25,33,33,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'pending','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:37+00:00\"}','2026-10-16 02:43:37','2026-10-16 02:43:37'),(26,34,34,NULL,'risk_score >= 0.40 AND active_points >= 10','profile_restriction',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'sent','{\"status\":\"queued\"}','2026-10-16 02:43:37','2026-10-16 02:43:37'),(27,35,35,NULL,'risk_score >= 0.40 AND active_points >= 10','profile_restriction',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'sent','{\"status\":\"queued\"}','2026-10-16 02:43:37','2026-10-16 02:43:37'),(28,37,37,NULL,'risk_score >= 0.40 AND active_points >= 10','profile_restriction',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'sent','{\"status\":\"queued\"}','2026-10-16 02:43:37','2026-10-16 02:43:37'),(29,39,39,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'sent','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:37+00:00\"}','2026-10-16 02:43:37','2026-10-16 02:43:37'),(30,40,40,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'pending','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:37+00:00\"}','2026-10-16 02:43:37','2026-10-16 02:43:37'),(31,41,41,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'pending','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:37+00:00\"}','2026-10-16 02:43:37','2026-10-16 02:43:37'),(32,42,42,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'failed','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:37+00:00\"}','2026-10-16 02:43:37','2026-10-16 02:43:37'),(33,43,43,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'pending','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:37+00:00\"}','2026-10-16 02:43:37','2026-10-16 02:43:37'),(34,44,44,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'sent','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:37+00:00\"}','2026-10-16 02:43:37','2026-10-16 02:43:37'),(35,45,45,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'pending','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:37+00:00\"}','2026-10-16 02:43:37','2026-10-16 02:43:37'),(36,46,46,NULL,'risk_score >= 0.40 AND active_points >= 10','profile_restriction',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'sent','{\"status\":\"queued\"}','2026-10-16 02:43:37','2026-10-16 02:43:37'),(37,47,47,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'sent','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:37+00:00\"}','2026-10-16 02:43:37','2026-10-16 02:43:37'),(38,48,48,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'sent','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:37+00:00\"}','2026-10-16 02:43:37','2026-10-16 02:43:37'),(39,49,49,NULL,'risk_score >= 0.70','profile_suspension',NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'pending','{\"status\":\"queued\",\"timestamp\":\"2026-10-16T05:43:37+00:00\"}','2026-10-16 02:43:37','2026-10-16 02:43:37');
/*!40000 ALTER TABLE `sanction_actions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stations`
--

DROP TABLE IF EXISTS `stations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `station_name` varchar(255) NOT NULL,
  `region` varchar(255) NOT NULL,
  `physical_address` varchar(255) NOT NULL,
  `contact_phone` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stations`
--

LOCK TABLES `stations` WRITE;
/*!40000 ALTER TABLE `stations` DISABLE KEYS */;
INSERT INTO `stations` VALUES (1,'Central Police Station','Nairobi Central','2263 Smitham Extension\nElveraland, HI 95516','+254774063435','2026-10-16 02:43:07','2026-10-16 02:43:07'),(2,'Kilimani Police Station','Nairobi West','927 Torp Cliffs Suite 006\nGerlachmouth, WV 27156','+254780703209','2026-10-16 02:43:07','2026-10-16 02:43:07'),(3,'Kasarani Police Station','Nairobi North','450 Alycia Squares Suite 162\nNedramouth, MO 61347-6527','+254753931697','2026-10-16 02:43:07','2026-10-16 02:43:07'),(4,'Langata Police Station','Nairobi South','8692 Heathcote Dam Suite 879\nHagenesshire, NV 00761','+254777006133','2026-10-16 02:43:07','2026-10-16 02:43:07'),(5,'Mombasa Road Station','Nairobi Industrial','657 Serenity Radial Apt. 853\nAkeemville, DC 52808-1698','+254726498406','2026-10-16 02:43:07','2026-10-16 02:43:07'),(6,'Thika Road Station','Kiambu County','65182 Estell Station Suite 127\nBrandonmouth, RI 54780','+254743338372','2026-10-16 02:43:07','2026-10-16 02:43:07'),(7,'Nakuru Central','Nakuru County','8165 Runte Ranch\nNorth Ahmedchester, AK 08765-8661','+254714700090','2026-10-16 02:43:07','2026-10-16 02:43:07'),(8,'Kisumu Central','Kisumu County','306 Schiller Creek\nRempelville, MT 10258-1765','+254735348221','2026-10-16 02:43:07','2026-10-16 02:43:07');
/*!40000 ALTER TABLE `stations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_notifications`
--

DROP TABLE IF EXISTS `system_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `recipient_user_id` bigint(20) unsigned DEFAULT NULL,
  `recipient_role` varchar(255) DEFAULT NULL,
  `channel` varchar(255) NOT NULL DEFAULT 'in_app',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context`)),
  `related_type` varchar(255) DEFAULT NULL,
  `related_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('unread','read') NOT NULL DEFAULT 'unread',
  `sent_at` timestamp NULL DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `system_notifications_recipient_user_id_status_index` (`recipient_user_id`,`status`),
  KEY `system_notifications_recipient_role_status_index` (`recipient_role`,`status`),
  KEY `system_notifications_related_type_related_id_index` (`related_type`,`related_id`),
  KEY `idx_notifications_status_sent_at` (`status`,`sent_at`),
  CONSTRAINT `system_notifications_recipient_user_id_foreign` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_notifications`
--

LOCK TABLES `system_notifications` WRITE;
/*!40000 ALTER TABLE `system_notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('motorist','officer','admin') NOT NULL DEFAULT 'motorist',
  `role_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `registered_on` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_id_foreign` (`role_id`),
  CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'John Kamau','john.kamau@ntsa.go.ke',NULL,'$2y$12$PLGCXTCzVuqgrle/GcDQt.8cI3Z/XYo0q0PjrrfPCm9mPkW.saY86','officer',2,1,'2026-07-31 02:43:08',NULL,'2026-10-16 02:43:08','2026-10-16 02:43:08'),(2,'Mary Wanjiku','mary.wanjiku@ntsa.go.ke',NULL,'$2y$12$o0K2dbJrwPvg.47TTzB0yu2hPXXim85/wbmumdjdV9Chw1X6YC/9W','officer',2,1,'2026-05-06 02:43:08',NULL,'2026-10-16 02:43:08','2026-10-16 02:43:08'),(3,'Peter Omondi','peter.omondi@ntsa.go.ke',NULL,'$2y$12$joP0gPMhXQ81NngeoFbyI.Aq0Pe3oro2s9iNOwpk2qVJvdhtbPKm2','officer',2,1,'2026-07-27 02:43:09',NULL,'2026-10-16 02:43:09','2026-10-16 02:43:09'),(4,'Jane Akinyi','jane.akinyi@ntsa.go.ke',NULL,'$2y$12$0YEpz0haaAxBQkm7lEgb9OuO8.b5h5V2WDPfus1WXdcWHRleSlTem','officer',2,1,'2026-01-30 02:43:09',NULL,'2026-10-16 02:43:09','2026-10-16 02:43:09'),(5,'David Mwangi','david.mwangi@ntsa.go.ke',NULL,'$2y$12$YoTpczHXd0JaPgPRL1yieO776M0fuz.KbAxVU6t/s7T0WfX3Epd.q','officer',2,1,'2025-11-02 02:43:10',NULL,'2026-10-16 02:43:10','2026-10-16 02:43:10'),(6,'Grace Njeri','grace.njeri@ntsa.go.ke',NULL,'$2y$12$RTgzWgX/roSBLMR7mPSSv.Qb3g5oTupTTi0P6Toc//sKoPgSfmYZm','officer',2,1,'2025-12-23 02:43:10',NULL,'2026-10-16 02:43:10','2026-10-16 02:43:10'),(7,'Samuel Otieno','samuel.otieno@ntsa.go.ke',NULL,'$2y$12$RL2Rjqr98Xww3tQ5pjaFK.ti7RUrRz7gIyXyEjpdjSbgPu4GVH4lO','officer',2,1,'2026-01-19 02:43:11',NULL,'2026-10-16 02:43:11','2026-10-16 02:43:11'),(8,'Lucy Adhiambo','lucy.adhiambo@ntsa.go.ke',NULL,'$2y$12$SOcrb7oWggTH9XdRIkmdAu9ovWmomhdjPHjV/PkJQnDnIhOk1.Bum','officer',2,1,'2026-02-04 02:43:11',NULL,'2026-10-16 02:43:11','2026-10-16 02:43:11'),(9,'Thuo Njoki','thuo.njoki722@gmail.com',NULL,'$2y$12$cvGbOaS.3uBH2wyocFzoLOOeJ4ImfxCq1v0WBQb3Hc12/hHG1sxg6','motorist',3,1,'2024-10-22 02:43:12',NULL,'2026-10-16 02:43:12','2026-10-16 02:43:12'),(10,'Otieno Akinyi','otieno.akinyi539@gmail.com',NULL,'$2y$12$Dh1vS.4Z.5Agy0.8PdH6XOciQk0bdHTFaHW5O4Et4D/8ST.LT.y.W','motorist',3,1,'2026-07-29 02:43:12',NULL,'2026-10-16 02:43:12','2026-10-16 02:43:12'),(11,'Odongo Akeyo','odongo.akeyo727@gmail.com',NULL,'$2y$12$MXqOfucBTDHzcwAcIP3I3urHJCaCyUXdxhLGiTJJFzCI8TD3o.6Xy','motorist',3,1,'2025-10-04 02:43:13',NULL,'2026-10-16 02:43:13','2026-10-16 02:43:13'),(12,'Ouma Apiyo','ouma.apiyo326@gmail.com',NULL,'$2y$12$xe4XaoKr7KHR3hoEcyDL2Oh17pBC2aXivjNi7ydRCpG/dc6DRu7fm','motorist',3,1,'2024-11-26 02:43:13',NULL,'2026-10-16 02:43:13','2026-10-16 02:43:13'),(13,'Karanja Mumbi','karanja.mumbi630@gmail.com',NULL,'$2y$12$sF9bhs6qvGCAIg3PJOHzYeEhYoR2mtqbprzDfr.4AK99R9/hPu6I6','motorist',3,1,'2026-10-13 02:43:14',NULL,'2026-10-16 02:43:14','2026-10-16 02:43:14'),(14,'Thuo Njoki','thuo.njoki518@gmail.com',NULL,'$2y$12$nMStS9qJlD.BrTucz27VG.sV0mQG8D00Mvo6novhc.pu41dJhvNZW','motorist',3,1,'2025-12-08 02:43:14',NULL,'2026-10-16 02:43:14','2026-10-16 02:43:14'),(15,'Kinyanjui Wacera','kinyanjui.wacera665@gmail.com',NULL,'$2y$12$f3/rFd6FNyoXLjhuAddRCOXeeG5iJJ1vtNIMaXXEbk3BSExpZ38lW','motorist',3,1,'2026-05-05 02:43:15',NULL,'2026-10-16 02:43:15','2026-10-16 02:43:15'),(16,'Kimani Wangari','kimani.wangari180@gmail.com',NULL,'$2y$12$0EaFtT/goXJKWV1ZQAYcouS/tcdEiG5F4A//Za0DJD.VV40gd5qWa','motorist',3,1,'2025-09-30 02:43:15',NULL,'2026-10-16 02:43:15','2026-10-16 02:43:15'),(17,'Omondi Adhiambo','omondi.adhiambo554@gmail.com',NULL,'$2y$12$PC82Qpd6bZsexakgVxo/VO4Iz7uVAAd5KWKG56d8EKAkXb8XgvuH.','motorist',3,1,'2025-04-15 02:43:15',NULL,'2026-10-16 02:43:15','2026-10-16 02:43:15'),(18,'Karanja Mumbi','karanja.mumbi440@gmail.com',NULL,'$2y$12$F3JH0l/SZSqy9tIbTlsYQutkQepSCoD7pnzzSuXDiTYmAgJYFuLKG','motorist',3,1,'2026-03-19 02:43:16',NULL,'2026-10-16 02:43:16','2026-10-16 02:43:16'),(19,'Were Achieng','were.achieng367@gmail.com',NULL,'$2y$12$MS8JtDL72vrnJl1oA4gN.uah0.qVkuYG8kAitdFBlon92WpA4lC/e','motorist',3,1,'2025-04-05 02:43:16',NULL,'2026-10-16 02:43:16','2026-10-16 02:43:16'),(20,'Gitau Wairimu','gitau.wairimu323@gmail.com',NULL,'$2y$12$nTe3X5mNcGSgGai5U5TwK.OwkMoL4xLip2DC0n1pI8ZCS5o8EkiNy','motorist',3,1,'2025-01-23 02:43:17',NULL,'2026-10-16 02:43:17','2026-10-16 02:43:17'),(21,'Ochieng Awino','ochieng.awino464@gmail.com',NULL,'$2y$12$XqsjsL05h3PGB3U.w6ADOelidwRX1x6aurMk2P3KWedjItjRh.y3.','motorist',3,1,'2025-02-26 02:43:17',NULL,'2026-10-16 02:43:17','2026-10-16 02:43:17'),(22,'Okello Adoyo','okello.adoyo403@gmail.com',NULL,'$2y$12$5HPLr0tPG.NvbekIEBQRP.gFvUm3V7dPeefbGG6o2RTjZHUUN69Tu','motorist',3,1,'2026-04-27 02:43:18',NULL,'2026-10-16 02:43:18','2026-10-16 02:43:18'),(23,'Kamande Wangeci','kamande.wangeci379@gmail.com',NULL,'$2y$12$bg2UQZQJ67orBeAQaH4hu.sh5MQqLt0uKgUrEVG7Q3gzg2JM1IOCW','motorist',3,1,'2025-03-20 02:43:18',NULL,'2026-10-16 02:43:18','2026-10-16 02:43:18'),(24,'Odera Atieno','odera.atieno774@gmail.com',NULL,'$2y$12$.y0M52XueOLLgwgxTKEbEOh31EeXUkmgTTfl83QiWtvVS5xupWNkO','motorist',3,1,'2026-03-26 02:43:19',NULL,'2026-10-16 02:43:19','2026-10-16 02:43:19'),(25,'Kimani Wangari','kimani.wangari252@gmail.com',NULL,'$2y$12$GkbeBLerFLkeHEIqA50f1.11Anp9eWQyNXVf5G9EJw2n5mrWJKxcG','motorist',3,1,'2026-04-11 02:43:19',NULL,'2026-10-16 02:43:19','2026-10-16 02:43:19'),(26,'Njenga Nyambura','njenga.nyambura601@gmail.com',NULL,'$2y$12$MGFmqQUFvtb4jAoaD2PVoO4L254swLdoQEb5rfw78u3q2oi1cKdMe','motorist',3,1,'2026-05-01 02:43:20',NULL,'2026-10-16 02:43:20','2026-10-16 02:43:20'),(27,'Ochieng Awino','ochieng.awino214@gmail.com',NULL,'$2y$12$oEcl25InyBILyl4JiMcPi.UWhx0j4er0U/ZsKnKQATXlEEx0XCb1G','motorist',3,1,'2025-11-22 02:43:20',NULL,'2026-10-16 02:43:20','2026-10-16 02:43:20'),(28,'Ouma Apiyo','ouma.apiyo809@gmail.com',NULL,'$2y$12$9MILg.o3Q1LL8gOIr47F7eUr5g35x870prFRW63uHzBUmPky0FO2C','motorist',3,1,'2026-01-17 02:43:21',NULL,'2026-10-16 02:43:21','2026-10-16 02:43:21'),(29,'Otieno Akinyi','otieno.akinyi805@gmail.com',NULL,'$2y$12$6Ivcy9F6l8Gjwqm9cOPe4OQahkYHbOz/wDpFEwjkjh6.Adm6LskqG','motorist',3,1,'2026-09-05 02:43:21',NULL,'2026-10-16 02:43:21','2026-10-16 02:43:21'),(30,'Kamande Wangeci','kamande.wangeci302@gmail.com',NULL,'$2y$12$.2xlUjy3ife3I/BsUjEaLeKLiBzSzcx53ba6FCM1/53a9pwewadRC','motorist',3,1,'2025-07-02 02:43:22',NULL,'2026-10-16 02:43:22','2026-10-16 02:43:22'),(31,'Ngugi Wangechi','ngugi.wangechi840@gmail.com',NULL,'$2y$12$b0rAduHQtZNX7/Y9B/V9zOGKVDH4VGJK7aoVtNhNRCoi.8YREtUG.','motorist',3,1,'2025-04-11 02:43:22',NULL,'2026-10-16 02:43:22','2026-10-16 02:43:22'),(32,'Oluoch Akinyi','oluoch.akinyi627@gmail.com',NULL,'$2y$12$Rnat7ZA63F1/mGKi2fglre3/leXMYfWGIw27qrESfIpoQ5NS/5Gnq','motorist',3,1,'2025-03-01 02:43:22',NULL,'2026-10-16 02:43:22','2026-10-16 02:43:22'),(33,'Macharia Nduta','macharia.nduta758@gmail.com',NULL,'$2y$12$dcU7Y5Iyv5oDEpZtw1y7veB34As6pdNHbsQ5UR1lwDEAS0pJNPP/6','motorist',3,1,'2026-08-29 02:43:23',NULL,'2026-10-16 02:43:23','2026-10-16 02:43:23'),(34,'Onyango Auma','onyango.auma626@gmail.com',NULL,'$2y$12$jD3fSInUTN/y1/pC0V5VdO7VzpT5M0XU1gQd4/pfyLpmqfL7s9TXG','motorist',3,1,'2025-06-06 02:43:23',NULL,'2026-10-16 02:43:23','2026-10-16 02:43:23'),(35,'Were Achieng','were.achieng548@gmail.com',NULL,'$2y$12$bNmMDeRryXr9bKCakYJomOwei23rEnqwKavoKUp/hISnVqmx2gUpK','motorist',3,1,'2026-06-10 02:43:24',NULL,'2026-10-16 02:43:24','2026-10-16 02:43:24'),(36,'Owino Akello','owino.akello381@gmail.com',NULL,'$2y$12$hfHc0dTF/sA02HZBByRulOzGgFlsy0nYQ2h3/8xYyspm34dl93RIG','motorist',3,1,'2026-01-07 02:43:24',NULL,'2026-10-16 02:43:24','2026-10-16 02:43:24'),(37,'Macharia Nduta','macharia.nduta565@gmail.com',NULL,'$2y$12$KjD11HbzIX0Tt8x2zPumputOcRPfJOSS.NPDqyo7jGMa16VJ.ADxW','motorist',3,1,'2026-05-11 02:43:25',NULL,'2026-10-16 02:43:25','2026-10-16 02:43:25'),(38,'Kariuki Wambui','kariuki.wambui649@gmail.com',NULL,'$2y$12$KUUqU.tO3eQSWkmRoFpRcuqhzYfkmNpAIwTkmxrI0Xqe1Sr6CDNkS','motorist',3,1,'2026-04-20 02:43:25',NULL,'2026-10-16 02:43:25','2026-10-16 02:43:25'),(39,'Otieno Akinyi','otieno.akinyi754@gmail.com',NULL,'$2y$12$CkA0ibxo.Pswp9QjjGgwau1lXYU70dl/B3IekxIo0sKCrAwmpRbTC','motorist',3,1,'2026-05-13 02:43:26',NULL,'2026-10-16 02:43:26','2026-10-16 02:43:26'),(40,'Mwangi Njeri','mwangi.njeri362@gmail.com',NULL,'$2y$12$ESFY8Q3C3MNwMJZGE/bHXOQ/5BDY/kNOTAV1ThD0nquFbP1MKIzBy','motorist',3,1,'2024-11-20 02:43:26',NULL,'2026-10-16 02:43:26','2026-10-16 02:43:26'),(41,'Onyango Auma','onyango.auma308@gmail.com',NULL,'$2y$12$Wqt2uNzbnjGQGii6FidymuIobjJAmJ1I.fTpAoZNzY4qNOaPSJXB2','motorist',3,1,'2024-11-15 02:43:27',NULL,'2026-10-16 02:43:27','2026-10-16 02:43:27'),(42,'Okello Adoyo','okello.adoyo290@gmail.com',NULL,'$2y$12$PG8uq6MuWhflZQmtNDuO2e8OBcExSYkgHZtpk0821KyA8gf.q5m8i','motorist',3,1,'2025-04-03 02:43:27',NULL,'2026-10-16 02:43:27','2026-10-16 02:43:27'),(43,'Owino Akello','owino.akello406@gmail.com',NULL,'$2y$12$fWJYttSjZnJDpKkSuBteNetQPSruiGC8jBj3vR7BPjMMsak1qJA2K','motorist',3,1,'2025-05-06 02:43:28',NULL,'2026-10-16 02:43:28','2026-10-16 02:43:28'),(44,'Ouma Apiyo','ouma.apiyo184@gmail.com',NULL,'$2y$12$WuqZktsC0E/BfiF58ka70.v4wW3qJVCs7jwDoGpHCOX/oxRDbNHKm','motorist',3,1,'2026-09-17 02:43:28',NULL,'2026-10-16 02:43:28','2026-10-16 02:43:28'),(45,'Njoroge Wamboi','njoroge.wamboi346@gmail.com',NULL,'$2y$12$xWc5I/lE1NpYm0t273SfSO2ld14MyNip4sHjUIQT.Y.ODT6B3I4Ya','motorist',3,1,'2026-01-30 02:43:29',NULL,'2026-10-16 02:43:29','2026-10-16 02:43:29'),(46,'Odera Atieno','odera.atieno842@gmail.com',NULL,'$2y$12$YQFoFOTd9TX6Ic8viz1WheDLPZ2/3WqZfYMdDNXgwhT42K/u3puES','motorist',3,1,'2025-04-26 02:43:29',NULL,'2026-10-16 02:43:29','2026-10-16 02:43:29'),(47,'Omondi Adhiambo','omondi.adhiambo737@gmail.com',NULL,'$2y$12$WlgfI2nQ.iq9ECpITnutteokTyT4XidQmqJZWqwKg.GcgF/Qn2ERy','motorist',3,1,'2025-04-30 02:43:30',NULL,'2026-10-16 02:43:30','2026-10-16 02:43:30'),(48,'Owino Akello','owino.akello788@gmail.com',NULL,'$2y$12$ASWXJCe2DVaAoWPXJzlwteIyu9OnN4CYkKF4mV5MTQyx7kjrFUFKO','motorist',3,1,'2026-08-26 02:43:30',NULL,'2026-10-16 02:43:30','2026-10-16 02:43:30'),(49,'Okello Adoyo','okello.adoyo737@gmail.com',NULL,'$2y$12$Vcqdr5/aKDs92D.5IFglV.i8vQuf3Mx0xP/7EAM0wdWKj/krMFTGW','motorist',3,1,'2026-01-15 02:43:30',NULL,'2026-10-16 02:43:30','2026-10-16 02:43:30'),(50,'Gathoni Wangui','gathoni.wangui798@gmail.com',NULL,'$2y$12$hbEnJFDejoxnpLTyM.0oE.m3q27DFu3Wyd/6fHBr4ltYXJLz5qvBm','motorist',3,1,'2025-04-02 02:43:31',NULL,'2026-10-16 02:43:31','2026-10-16 02:43:31'),(51,'Ochieng Awino','ochieng.awino951@gmail.com',NULL,'$2y$12$aAEQelT1nK2/412V6HQLBuVM9Utmt/XZCShqSdx6/wjAx1CeV3Hjq','motorist',3,1,'2025-12-24 02:43:31',NULL,'2026-10-16 02:43:31','2026-10-16 02:43:31'),(52,'Gitau Wairimu','gitau.wairimu801@gmail.com',NULL,'$2y$12$Mqkz.F4f745JFL5qw/BxbeBKn42saangxM8RUc/Ja87Yw1ECe3NoO','motorist',3,1,'2026-03-11 02:43:32',NULL,'2026-10-16 02:43:32','2026-10-16 02:43:32'),(53,'Njoroge Wamboi','njoroge.wamboi708@gmail.com',NULL,'$2y$12$8cURZHhUeCr.xBjhFBGJzudWHvP8tZPSsYuvzRNaXmYqv1MmBeZ9q','motorist',3,1,'2025-03-31 02:43:32',NULL,'2026-10-16 02:43:32','2026-10-16 02:43:32'),(54,'Njoroge Wamboi','njoroge.wamboi476@gmail.com',NULL,'$2y$12$0sx145CkDRmWKkLGGmn.vucSRei7LmP8A5QjD2z4aPu78b2azerD6','motorist',3,1,'2025-01-24 02:43:33',NULL,'2026-10-16 02:43:33','2026-10-16 02:43:33'),(55,'Gathoni Wangui','gathoni.wangui845@gmail.com',NULL,'$2y$12$jC6GTr/e4Rn0ZKuPW8cHYOr2gGN40xhboduwLzWkEjFHwoWeDppQy','motorist',3,1,'2026-07-27 02:43:33',NULL,'2026-10-16 02:43:33','2026-10-16 02:43:33'),(56,'Owino Akello','owino.akello935@gmail.com',NULL,'$2y$12$vszfz5ZzpeJeYAgH5iU1AeP.yDj/ioKCA2iAe387b.FuEkdzFJ.Zy','motorist',3,1,'2026-04-19 02:43:34',NULL,'2026-10-16 02:43:34','2026-10-16 02:43:34'),(57,'Odera Atieno','odera.atieno155@gmail.com',NULL,'$2y$12$2m5ljMfv89w8prB/0M5Vlup8fzQge7di5CAnj2jmvbTxC1auzMjgm','motorist',3,1,'2025-10-04 02:43:34',NULL,'2026-10-16 02:43:34','2026-10-16 02:43:34'),(58,'Njoroge Wamboi','njoroge.wamboi219@gmail.com',NULL,'$2y$12$yoBPzkeZKvS6Vh2R4M.h..6QGDdmWe3uGOOCIX3cbo/CY7JSi3922','motorist',3,1,'2026-01-16 02:43:35',NULL,'2026-10-16 02:43:35','2026-10-16 02:43:35'),(59,'NTSA Administrator','admin@ntsa.go.ke',NULL,'$2y$12$mf1BS4L4wTPh9LAm6oOlfOyLZX4DZptvEx8UuJlx2TtAWHhwyGoR2','admin',1,1,'2026-10-16 02:45:58',NULL,'2026-10-16 02:45:58','2026-10-16 02:45:58'),(60,'Test Traffic Officer','officer@ntsa.go.ke',NULL,'$2y$12$O6r4juWyu1a7EclYLT/2XOYMRrax223ZMF3.LKATsg1WKUpOIFgoe','officer',2,1,'2026-10-16 02:45:59',NULL,'2026-10-16 02:45:59','2026-10-16 02:45:59'),(61,'Test Driver Account','driver@ntsa.go.ke',NULL,'$2y$12$Y8qaPZtdPdUfVw6ocARws.2QfCJUr0CbmCMThAC0hY9zzRX5Jwm.u','motorist',3,1,'2026-10-16 02:45:59',NULL,'2026-10-16 02:45:59','2026-10-16 02:45:59');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicles`
--

DROP TABLE IF EXISTS `vehicles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `owner_user_id` bigint(20) unsigned NOT NULL,
  `plate_number` varchar(255) NOT NULL,
  `make_model` varchar(255) NOT NULL,
  `vehicle_class` varchar(255) NOT NULL,
  `colour` varchar(255) DEFAULT NULL,
  `year_of_manufacture` smallint(5) unsigned DEFAULT NULL,
  `registration_expiry` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vehicles_plate_number_unique` (`plate_number`),
  KEY `vehicles_owner_user_id_foreign` (`owner_user_id`),
  CONSTRAINT `vehicles_owner_user_id_foreign` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicles`
--

LOCK TABLES `vehicles` WRITE;
/*!40000 ALTER TABLE `vehicles` DISABLE KEYS */;
INSERT INTO `vehicles` VALUES (1,41,'KDH 672R','Toyota RAV4','pickup','white',2022,'2028-04-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(2,14,'KDE 102O','Mitsubishi Lancer','pickup','white',2024,'2028-04-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(3,37,'KDG 964B','Toyota Premio','hatchback','red',2018,'2028-06-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(4,25,'KAC 395K','Volkswagen Passat','pickup','grey',2022,'2027-06-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(5,20,'KDD 398T','Volkswagen Passat','suv','black',2012,'2028-06-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(6,58,'KCC 855L','Nissan Note','saloon','green',2015,'2027-03-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(7,25,'KDA 404L','Subaru Impreza','pickup','red',2016,'2027-02-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(8,31,'KDK 956Y','Nissan Navara','saloon','black',2022,'2028-06-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(9,21,'KDC 921Y','Toyota Prado','saloon','silver',2010,'2028-09-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(10,21,'KDA 906E','Nissan X-Trail','van','red',2011,'2028-10-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(11,29,'KDE 188J','Nissan Navara','hatchback','blue',2018,'2028-08-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(12,29,'KDJ 278K','Nissan X-Trail','hatchback','silver',2010,'2028-04-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(13,40,'KDJ 604N','Subaru Forester','hatchback','red',2022,'2028-08-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(14,21,'KCA 632E','Mitsubishi Lancer','van','grey',2012,'2028-02-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(15,47,'KAC 699V','Toyota Land Cruiser','hatchback','red',2019,'2028-09-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(16,48,'KDJ 534R','Toyota RAV4','pickup','grey',2017,'2027-03-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(17,23,'KAA 538Z','Toyota Hilux','saloon','silver',2016,'2027-12-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(18,56,'KDJ 173D','Toyota Corolla','suv','silver',2017,'2027-06-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(19,10,'KBA 935H','Toyota RAV4','hatchback','red',2011,'2028-04-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(20,19,'KDF 718N','Ford Ranger','suv','grey',2019,'2028-09-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(21,13,'KCC 786E','Nissan Note','van','white',2024,'2027-07-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(22,22,'KDC 108M','Toyota Axio','pickup','grey',2012,'2028-09-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(23,42,'KAB 798Y','Honda Civic','pickup','blue',2011,'2027-10-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(24,49,'KDF 629T','Honda Civic','hatchback','silver',2019,'2028-07-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(25,52,'KAB 953P','Mazda Demio','hatchback','black',2018,'2028-06-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(26,26,'KBC 912R','Subaru Impreza','suv','white',2014,'2028-04-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(27,22,'KAC 443U','Nissan X-Trail','saloon','grey',2018,'2027-02-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(28,25,'KCB 887K','Toyota Corolla','saloon','red',2020,'2027-10-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(29,29,'KDD 951V','Toyota RAV4','suv','red',2020,'2027-03-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(30,36,'KDC 156T','Volkswagen Passat','pickup','black',2012,'2027-03-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(31,19,'KDB 525X','Mitsubishi Lancer','suv','green',2018,'2027-05-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(32,14,'KDC 807F','Honda CR-V','van','blue',2018,'2027-12-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(33,50,'KCB 868P','Toyota Prado','pickup','black',2022,'2028-03-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(34,17,'KAA 288B','Toyota Land Cruiser','van','white',2024,'2028-05-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(35,49,'KCA 834S','Toyota Premio','hatchback','silver',2021,'2027-09-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(36,36,'KDF 224U','Toyota Axio','suv','black',2017,'2027-05-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(37,32,'KCC 483Q','Nissan Tiida','suv','blue',2015,'2027-12-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(38,34,'KDH 839N','Toyota Axio','saloon','white',2019,'2028-08-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(39,44,'KDE 736H','Toyota Land Cruiser','hatchback','silver',2011,'2028-05-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(40,14,'KCA 595M','Toyota Hilux','pickup','white',2021,'2026-12-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(41,32,'KDG 204E','Nissan Patrol','pickup','red',2023,'2028-05-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(42,53,'KCC 801W','Nissan Note','van','grey',2015,'2027-09-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(43,19,'KAB 636W','Nissan Note','van','white',2013,'2028-02-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(44,45,'KCA 592L','Nissan X-Trail','suv','red',2023,'2027-08-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(45,9,'KAB 265T','Ford Ranger','van','white',2014,'2026-12-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(46,28,'KBB 858V','Nissan Navara','van','white',2022,'2028-02-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(47,35,'KAB 761B','Honda Civic','suv','blue',2014,'2028-02-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(48,56,'KDA 710V','Ford Ranger','pickup','silver',2011,'2027-12-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(49,50,'KDB 714R','Honda Civic','hatchback','silver',2024,'2027-12-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(50,57,'KCA 502V','Honda CR-V','pickup','white',2019,'2027-01-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(51,26,'KDA 808K','Subaru Forester','pickup','green',2023,'2027-09-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(52,15,'KDB 986V','Mazda Demio','van','blue',2015,'2027-02-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(53,49,'KBB 506S','Nissan Navara','saloon','white',2015,'2028-05-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(54,47,'KDF 104H','Toyota Axio','pickup','green',2012,'2028-02-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(55,32,'KDK 750Q','Nissan Note','saloon','silver',2020,'2028-03-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(56,22,'KDG 679E','Isuzu D-Max','suv','red',2022,'2028-06-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(57,25,'KAA 205A','Toyota Corolla','hatchback','black',2018,'2027-05-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(58,22,'KBA 463A','Toyota Vitz','hatchback','white',2015,'2028-05-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(59,12,'KCB 194A','Toyota Prado','hatchback','grey',2010,'2027-03-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(60,13,'KBB 870Q','Toyota Axio','pickup','blue',2014,'2027-12-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(61,44,'KDG 314E','Honda CR-V','pickup','green',2017,'2026-11-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(62,29,'KDC 209Z','Toyota Hilux','hatchback','blue',2020,'2027-08-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(63,40,'KDD 789F','Toyota Hilux','suv','grey',2019,'2028-05-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(64,58,'KCC 979M','Toyota Land Cruiser','pickup','green',2018,'2028-09-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(65,13,'KDB 622J','Isuzu D-Max','suv','green',2020,'2027-11-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(66,49,'KBB 632B','Nissan Navara','pickup','grey',2013,'2027-07-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(67,51,'KCA 151G','Honda Fit','hatchback','silver',2012,'2027-01-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(68,29,'KDK 236M','Honda CR-V','saloon','silver',2023,'2028-02-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(69,38,'KDA 707A','Nissan Patrol','van','white',2021,'2028-04-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(70,24,'KBA 187R','Subaru Forester','van','green',2019,'2027-01-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(71,12,'KDK 763J','Ford Ranger','pickup','white',2010,'2027-10-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(72,28,'KDF 678N','Toyota Prado','saloon','grey',2013,'2027-08-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(73,12,'KDK 837R','Toyota Vitz','saloon','blue',2018,'2028-07-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(74,36,'KDD 723C','Ford Ranger','van','blue',2013,'2026-12-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(75,19,'KBA 134F','Honda Fit','pickup','grey',2012,'2026-11-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(76,55,'KDD 324B','Subaru Forester','pickup','blue',2012,'2028-04-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(77,15,'KCC 338X','Subaru Forester','pickup','white',2021,'2027-09-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(78,58,'KDG 118Z','Subaru Impreza','hatchback','blue',2017,'2027-11-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(79,26,'KDB 685H','Volkswagen Passat','pickup','green',2019,'2027-04-16','2026-10-16 02:43:35','2026-10-16 02:43:35'),(80,46,'KAC 926W','Ford Ranger','van','grey',2014,'2028-01-16','2026-10-16 02:43:35','2026-10-16 02:43:35');
/*!40000 ALTER TABLE `vehicles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `violations`
--

DROP TABLE IF EXISTS `violations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `violations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `driver_id` bigint(20) unsigned NOT NULL,
  `vehicle_id` bigint(20) unsigned DEFAULT NULL,
  `officer_id` bigint(20) unsigned DEFAULT NULL,
  `officer_assignment_id` bigint(20) unsigned DEFAULT NULL,
  `offense_type` varchar(255) NOT NULL,
  `speed_recorded` decimal(6,2) DEFAULT NULL,
  `posted_speed_limit` decimal(6,2) DEFAULT NULL,
  `zone_type` enum('urban','highway','school_zone','residential') NOT NULL,
  `weather_conditions` enum('clear','rain','fog','night') NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `evidence_reference` varchar(255) DEFAULT NULL,
  `points_assigned` tinyint(3) unsigned NOT NULL,
  `status` enum('confirmed','disputed') NOT NULL DEFAULT 'confirmed',
  `occurred_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `violations_officer_id_foreign` (`officer_id`),
  KEY `violations_vehicle_id_foreign` (`vehicle_id`),
  KEY `violations_officer_assignment_id_foreign` (`officer_assignment_id`),
  KEY `idx_violations_driver_occurred_at` (`driver_id`,`occurred_at`),
  KEY `idx_violations_zone_weather` (`zone_type`,`weather_conditions`),
  CONSTRAINT `violations_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `driver_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `violations_officer_assignment_id_foreign` FOREIGN KEY (`officer_assignment_id`) REFERENCES `officer_assignments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `violations_officer_id_foreign` FOREIGN KEY (`officer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `violations_vehicle_id_foreign` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=187 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `violations`
--

LOCK TABLES `violations` WRITE;
/*!40000 ALTER TABLE `violations` DISABLE KEYS */;
INSERT INTO `violations` VALUES (1,1,NULL,1,NULL,'no_license',NULL,NULL,'highway','night','University Way',NULL,8,'confirmed','2026-03-20 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(2,1,NULL,3,NULL,'speeding',85.00,NULL,'urban','fog','Valley Road',NULL,5,'confirmed','2026-01-13 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(3,1,NULL,5,NULL,'phone_use',NULL,NULL,'residential','rain','Langata Road',NULL,3,'confirmed','2025-10-18 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(4,1,NULL,7,NULL,'no_license',NULL,NULL,'residential','clear','Haile Selassie Avenue',NULL,8,'confirmed','2026-09-28 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(5,1,NULL,5,NULL,'reckless_driving',NULL,NULL,'school_zone','fog','Argwings Kodhek Road',NULL,6,'confirmed','2026-02-06 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(6,2,NULL,4,NULL,'reckless_driving',NULL,NULL,'highway','night','Argwings Kodhek Road',NULL,6,'confirmed','2025-11-04 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(7,2,NULL,4,NULL,'no_seatbelt',NULL,NULL,'highway','clear','Mombasa Road',NULL,2,'confirmed','2025-11-12 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(8,2,NULL,1,NULL,'no_seatbelt',NULL,NULL,'residential','clear','Mombasa Road',NULL,2,'confirmed','2026-03-17 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(9,2,NULL,2,NULL,'no_insurance',NULL,NULL,'residential','rain','Langata Road',NULL,6,'confirmed','2026-01-26 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(10,2,NULL,1,NULL,'overloading',NULL,NULL,'residential','clear','Uhuru Highway',NULL,4,'confirmed','2025-10-29 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(11,3,NULL,1,NULL,'red_light',NULL,NULL,'urban','night','Southern Bypass',NULL,4,'confirmed','2026-09-28 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(12,3,NULL,1,NULL,'speeding',146.00,NULL,'residential','night','Nairobi-Nakuru Highway',NULL,5,'confirmed','2026-09-30 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(13,3,NULL,4,NULL,'reckless_driving',NULL,NULL,'highway','clear','Jogoo Road',NULL,6,'confirmed','2025-11-08 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(14,3,NULL,8,NULL,'red_light',NULL,NULL,'residential','clear','Southern Bypass',NULL,4,'confirmed','2026-09-04 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(15,3,NULL,8,NULL,'no_insurance',NULL,NULL,'urban','fog','Argwings Kodhek Road',NULL,6,'confirmed','2026-06-11 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(16,3,NULL,3,NULL,'overloading',NULL,NULL,'school_zone','clear','Langata Road',NULL,4,'confirmed','2026-03-02 02:43:35','2026-10-16 02:43:35','2026-10-16 02:43:35'),(17,4,NULL,1,NULL,'red_light',NULL,NULL,'highway','fog','Eastern Bypass',NULL,4,'confirmed','2026-03-11 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(18,4,NULL,6,NULL,'dui',NULL,NULL,'school_zone','fog','Moi Avenue',NULL,8,'confirmed','2026-03-12 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(19,5,NULL,1,NULL,'red_light',NULL,NULL,'highway','night','Southern Bypass',NULL,4,'confirmed','2026-04-11 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(20,5,NULL,6,NULL,'speeding',150.00,NULL,'school_zone','night','Moi Avenue',NULL,5,'confirmed','2026-08-14 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(21,5,NULL,7,NULL,'dui',NULL,NULL,'highway','night','University Way',NULL,8,'confirmed','2026-08-26 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(22,6,NULL,3,NULL,'no_seatbelt',NULL,NULL,'school_zone','fog','Langata Road',NULL,2,'confirmed','2025-11-29 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(23,7,NULL,4,NULL,'no_insurance',NULL,NULL,'residential','clear','Waiyaki Way',NULL,6,'confirmed','2026-01-11 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(24,7,NULL,5,NULL,'illegal_parking',NULL,NULL,'residential','night','Kenyatta Avenue',NULL,1,'confirmed','2025-11-19 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(25,7,NULL,4,NULL,'no_license',NULL,NULL,'urban','clear','Uhuru Highway',NULL,8,'confirmed','2026-08-17 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(26,7,NULL,2,NULL,'overloading',NULL,NULL,'highway','fog','Uhuru Highway',NULL,4,'confirmed','2026-02-23 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(27,7,NULL,6,NULL,'phone_use',NULL,NULL,'highway','fog','Nairobi-Nakuru Highway',NULL,3,'confirmed','2026-04-15 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(28,8,NULL,8,NULL,'reckless_driving',NULL,NULL,'highway','clear','Haile Selassie Avenue',NULL,6,'confirmed','2026-02-06 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(29,8,NULL,6,NULL,'no_insurance',NULL,NULL,'school_zone','clear','Kenyatta Avenue',NULL,6,'confirmed','2025-12-03 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(30,9,NULL,2,NULL,'speeding',146.00,NULL,'highway','fog','Outering Road',NULL,5,'confirmed','2026-03-25 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(31,9,NULL,4,NULL,'dui',NULL,NULL,'highway','clear','Langata Road',NULL,8,'confirmed','2026-01-12 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(32,9,NULL,2,NULL,'speeding',90.00,NULL,'residential','night','Southern Bypass',NULL,5,'confirmed','2025-12-18 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(33,9,NULL,5,NULL,'dui',NULL,NULL,'residential','clear','University Way',NULL,8,'confirmed','2026-06-17 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(34,9,NULL,4,NULL,'reckless_driving',NULL,NULL,'urban','rain','Tom Mboya Street',NULL,6,'confirmed','2026-08-28 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(35,10,NULL,3,NULL,'no_license',NULL,NULL,'highway','fog','Kenyatta Avenue',NULL,8,'confirmed','2026-07-03 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(36,10,NULL,6,NULL,'red_light',NULL,NULL,'urban','rain','Langata Road',NULL,4,'confirmed','2026-04-24 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(37,10,NULL,4,NULL,'illegal_parking',NULL,NULL,'urban','fog','Argwings Kodhek Road',NULL,1,'confirmed','2026-09-30 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(38,11,NULL,3,NULL,'red_light',NULL,NULL,'urban','fog','Ronald Ngala Street',NULL,4,'confirmed','2026-01-09 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(39,11,NULL,1,NULL,'phone_use',NULL,NULL,'urban','night','Haile Selassie Avenue',NULL,3,'confirmed','2026-01-15 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(40,11,NULL,8,NULL,'dui',NULL,NULL,'residential','rain','Mombasa Road',NULL,8,'confirmed','2025-11-06 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(41,11,NULL,1,NULL,'phone_use',NULL,NULL,'residential','clear','Eastern Bypass',NULL,3,'confirmed','2025-11-30 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(42,11,NULL,4,NULL,'illegal_parking',NULL,NULL,'residential','night','Haile Selassie Avenue',NULL,1,'confirmed','2025-12-22 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(43,11,NULL,7,NULL,'no_seatbelt',NULL,NULL,'school_zone','night','Kenyatta Avenue',NULL,2,'confirmed','2025-12-01 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(44,12,NULL,8,NULL,'speeding',127.00,NULL,'urban','night','Southern Bypass',NULL,5,'confirmed','2026-02-17 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(45,12,NULL,7,NULL,'illegal_parking',NULL,NULL,'school_zone','night','Ronald Ngala Street',NULL,1,'confirmed','2026-06-07 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(46,12,NULL,6,NULL,'speeding',90.00,NULL,'school_zone','rain','Ngong Road',NULL,5,'confirmed','2026-01-23 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(47,12,NULL,4,NULL,'no_license',NULL,NULL,'residential','night','Thika Superhighway',NULL,8,'confirmed','2025-10-24 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(48,12,NULL,2,NULL,'speeding',148.00,NULL,'residential','clear','Mombasa Road',NULL,5,'confirmed','2026-02-26 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(49,12,NULL,1,NULL,'reckless_driving',NULL,NULL,'school_zone','night','Tom Mboya Street',NULL,6,'confirmed','2026-07-11 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(50,13,NULL,5,NULL,'no_insurance',NULL,NULL,'urban','fog','Waiyaki Way',NULL,6,'confirmed','2026-05-05 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(51,14,NULL,7,NULL,'reckless_driving',NULL,NULL,'urban','night','Moi Avenue',NULL,6,'confirmed','2026-04-12 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(52,14,NULL,1,NULL,'phone_use',NULL,NULL,'urban','fog','Kipande Road',NULL,3,'confirmed','2026-01-24 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(53,14,NULL,7,NULL,'overloading',NULL,NULL,'residential','night','Nairobi-Nakuru Highway',NULL,4,'confirmed','2026-06-23 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(54,14,NULL,7,NULL,'dui',NULL,NULL,'school_zone','night','University Way',NULL,8,'confirmed','2026-01-17 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(55,14,NULL,4,NULL,'overloading',NULL,NULL,'highway','clear','Eastern Bypass',NULL,4,'confirmed','2026-05-29 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(56,15,NULL,8,NULL,'no_license',NULL,NULL,'highway','night','Nairobi-Nakuru Highway',NULL,8,'confirmed','2026-01-23 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(57,15,NULL,4,NULL,'no_insurance',NULL,NULL,'school_zone','fog','Valley Road',NULL,6,'confirmed','2025-12-06 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(58,15,NULL,8,NULL,'phone_use',NULL,NULL,'residential','fog','Uhuru Highway',NULL,3,'confirmed','2026-08-22 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(59,15,NULL,2,NULL,'speeding',147.00,NULL,'residential','night','Mombasa Road',NULL,5,'confirmed','2026-05-28 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(60,16,NULL,7,NULL,'red_light',NULL,NULL,'highway','rain','Nairobi-Nakuru Highway',NULL,4,'confirmed','2025-11-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(61,16,NULL,3,NULL,'red_light',NULL,NULL,'highway','clear','Uhuru Highway',NULL,4,'confirmed','2026-01-31 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(62,16,NULL,4,NULL,'no_insurance',NULL,NULL,'school_zone','night','University Way',NULL,6,'confirmed','2026-01-21 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(63,16,NULL,1,NULL,'dui',NULL,NULL,'school_zone','clear','Thika Superhighway',NULL,8,'confirmed','2026-07-22 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(64,16,NULL,5,NULL,'overloading',NULL,NULL,'residential','night','Nairobi-Nakuru Highway',NULL,4,'confirmed','2025-11-03 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(65,17,NULL,7,NULL,'no_insurance',NULL,NULL,'highway','clear','Waiyaki Way',NULL,6,'confirmed','2026-08-10 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(66,18,NULL,8,NULL,'speeding',142.00,NULL,'urban','clear','Haile Selassie Avenue',NULL,5,'confirmed','2026-09-04 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(67,18,NULL,2,NULL,'red_light',NULL,NULL,'highway','clear','Kenyatta Avenue',NULL,4,'confirmed','2025-11-28 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(68,18,NULL,1,NULL,'red_light',NULL,NULL,'highway','night','Moi Avenue',NULL,4,'confirmed','2026-06-18 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(69,18,NULL,3,NULL,'no_seatbelt',NULL,NULL,'highway','night','Moi Avenue',NULL,2,'confirmed','2025-10-25 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(70,18,NULL,8,NULL,'no_license',NULL,NULL,'school_zone','fog','Thika Superhighway',NULL,8,'confirmed','2026-04-29 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(71,18,NULL,5,NULL,'red_light',NULL,NULL,'school_zone','night','Outering Road',NULL,4,'confirmed','2026-08-02 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(72,19,NULL,6,NULL,'phone_use',NULL,NULL,'highway','fog','Langata Road',NULL,3,'confirmed','2026-03-24 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(73,20,NULL,7,NULL,'red_light',NULL,NULL,'residential','fog','Mombasa Road',NULL,4,'confirmed','2026-07-10 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(74,20,NULL,2,NULL,'no_insurance',NULL,NULL,'highway','clear','Eastern Bypass',NULL,6,'confirmed','2026-02-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(75,21,NULL,2,NULL,'reckless_driving',NULL,NULL,'school_zone','fog','Haile Selassie Avenue',NULL,6,'confirmed','2025-12-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(76,21,NULL,5,NULL,'red_light',NULL,NULL,'urban','rain','Uhuru Highway',NULL,4,'confirmed','2026-02-25 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(77,21,NULL,8,NULL,'red_light',NULL,NULL,'highway','night','Tom Mboya Street',NULL,4,'confirmed','2026-03-07 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(78,21,NULL,3,NULL,'no_insurance',NULL,NULL,'school_zone','clear','Kenyatta Avenue',NULL,6,'confirmed','2026-01-04 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(79,21,NULL,3,NULL,'red_light',NULL,NULL,'school_zone','rain','Eastern Bypass',NULL,4,'confirmed','2026-04-14 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(80,22,NULL,4,NULL,'overloading',NULL,NULL,'highway','clear','Tom Mboya Street',NULL,4,'confirmed','2025-10-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(81,22,NULL,8,NULL,'dui',NULL,NULL,'urban','clear','Valley Road',NULL,8,'confirmed','2026-06-13 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(82,22,NULL,5,NULL,'no_insurance',NULL,NULL,'school_zone','night','Outering Road',NULL,6,'confirmed','2025-12-17 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(83,22,NULL,4,NULL,'no_seatbelt',NULL,NULL,'school_zone','rain','Waiyaki Way',NULL,2,'confirmed','2026-01-17 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(84,22,NULL,8,NULL,'red_light',NULL,NULL,'school_zone','fog','Uhuru Highway',NULL,4,'confirmed','2026-05-29 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(85,23,NULL,1,NULL,'dui',NULL,NULL,'school_zone','clear','Mombasa Road',NULL,8,'confirmed','2026-05-06 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(86,23,NULL,6,NULL,'no_license',NULL,NULL,'school_zone','rain','Kipande Road',NULL,8,'confirmed','2026-07-19 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(87,23,NULL,1,NULL,'no_license',NULL,NULL,'highway','rain','Waiyaki Way',NULL,8,'confirmed','2026-01-26 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(88,23,NULL,4,NULL,'no_license',NULL,NULL,'highway','fog','Kipande Road',NULL,8,'confirmed','2026-06-10 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(89,23,NULL,5,NULL,'red_light',NULL,NULL,'school_zone','rain','Ronald Ngala Street',NULL,4,'confirmed','2026-05-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(90,23,NULL,4,NULL,'phone_use',NULL,NULL,'urban','clear','Kipande Road',NULL,3,'confirmed','2026-03-28 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(91,24,NULL,8,NULL,'no_insurance',NULL,NULL,'highway','rain','Outering Road',NULL,6,'confirmed','2026-07-26 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(92,24,NULL,4,NULL,'no_license',NULL,NULL,'highway','night','Kenyatta Avenue',NULL,8,'confirmed','2026-01-12 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(93,24,NULL,4,NULL,'overloading',NULL,NULL,'urban','clear','Eastern Bypass',NULL,4,'confirmed','2026-06-20 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(94,24,NULL,1,NULL,'phone_use',NULL,NULL,'school_zone','fog','Langata Road',NULL,3,'confirmed','2026-09-11 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(95,24,NULL,6,NULL,'illegal_parking',NULL,NULL,'residential','clear','Southern Bypass',NULL,1,'confirmed','2026-06-21 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(96,24,NULL,5,NULL,'red_light',NULL,NULL,'highway','rain','Nairobi-Nakuru Highway',NULL,4,'confirmed','2026-02-09 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(97,25,NULL,5,NULL,'no_seatbelt',NULL,NULL,'residential','clear','Tom Mboya Street',NULL,2,'confirmed','2026-09-07 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(98,25,NULL,3,NULL,'no_insurance',NULL,NULL,'school_zone','night','Uhuru Highway',NULL,6,'confirmed','2026-07-27 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(99,26,NULL,8,NULL,'phone_use',NULL,NULL,'urban','clear','Ronald Ngala Street',NULL,3,'confirmed','2026-02-01 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(100,27,NULL,1,NULL,'speeding',153.00,NULL,'residential','fog','Ngong Road',NULL,5,'confirmed','2026-06-12 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(101,27,NULL,4,NULL,'phone_use',NULL,NULL,'urban','fog','Tom Mboya Street',NULL,3,'confirmed','2026-07-26 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(102,28,NULL,4,NULL,'reckless_driving',NULL,NULL,'residential','fog','Langata Road',NULL,6,'confirmed','2026-06-08 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(103,28,NULL,1,NULL,'phone_use',NULL,NULL,'highway','night','Valley Road',NULL,3,'confirmed','2026-07-11 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(104,28,NULL,5,NULL,'illegal_parking',NULL,NULL,'residential','night','Ronald Ngala Street',NULL,1,'confirmed','2026-03-16 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(105,28,NULL,1,NULL,'red_light',NULL,NULL,'urban','fog','Langata Road',NULL,4,'confirmed','2026-04-01 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(106,29,NULL,5,NULL,'no_seatbelt',NULL,NULL,'school_zone','rain','Southern Bypass',NULL,2,'confirmed','2026-01-01 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(107,29,NULL,2,NULL,'speeding',95.00,NULL,'urban','rain','Uhuru Highway',NULL,5,'confirmed','2026-07-30 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(108,29,NULL,4,NULL,'speeding',115.00,NULL,'highway','fog','Haile Selassie Avenue',NULL,5,'confirmed','2026-01-06 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(109,30,NULL,5,NULL,'illegal_parking',NULL,NULL,'school_zone','rain','Eastern Bypass',NULL,1,'confirmed','2025-12-12 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(110,31,NULL,7,NULL,'no_license',NULL,NULL,'residential','clear','Kenyatta Avenue',NULL,8,'confirmed','2026-06-28 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(111,31,NULL,2,NULL,'red_light',NULL,NULL,'highway','night','Ronald Ngala Street',NULL,4,'confirmed','2026-05-14 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(112,31,NULL,6,NULL,'no_seatbelt',NULL,NULL,'residential','rain','Kenyatta Avenue',NULL,2,'confirmed','2026-03-10 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(113,31,NULL,3,NULL,'phone_use',NULL,NULL,'residential','clear','Langata Road',NULL,3,'confirmed','2026-02-12 02:43:36','2026-10-16 02:43:36','2026-10-16 02:43:36'),(114,31,NULL,2,NULL,'phone_use',NULL,NULL,'school_zone','clear','Argwings Kodhek Road',NULL,3,'confirmed','2026-04-10 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(115,32,NULL,2,NULL,'reckless_driving',NULL,NULL,'school_zone','clear','Ngong Road',NULL,6,'confirmed','2026-03-21 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(116,32,NULL,3,NULL,'no_license',NULL,NULL,'highway','rain','Nairobi-Nakuru Highway',NULL,8,'confirmed','2026-06-22 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(117,32,NULL,3,NULL,'overloading',NULL,NULL,'school_zone','clear','Haile Selassie Avenue',NULL,4,'confirmed','2025-12-14 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(118,32,NULL,1,NULL,'illegal_parking',NULL,NULL,'residential','clear','Thika Superhighway',NULL,1,'confirmed','2026-07-31 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(119,33,NULL,1,NULL,'no_seatbelt',NULL,NULL,'school_zone','clear','Haile Selassie Avenue',NULL,2,'confirmed','2025-12-29 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(120,33,NULL,2,NULL,'reckless_driving',NULL,NULL,'school_zone','fog','University Way',NULL,6,'confirmed','2026-01-15 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(121,33,NULL,8,NULL,'dui',NULL,NULL,'urban','rain','Ngong Road',NULL,8,'confirmed','2026-05-09 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(122,33,NULL,7,NULL,'overloading',NULL,NULL,'urban','night','Moi Avenue',NULL,4,'confirmed','2026-03-18 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(123,33,NULL,1,NULL,'dui',NULL,NULL,'highway','fog','Southern Bypass',NULL,8,'confirmed','2026-05-13 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(124,34,NULL,2,NULL,'no_insurance',NULL,NULL,'highway','clear','University Way',NULL,6,'confirmed','2026-06-01 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(125,34,NULL,8,NULL,'reckless_driving',NULL,NULL,'school_zone','night','Waiyaki Way',NULL,6,'confirmed','2025-11-20 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(126,35,NULL,8,NULL,'no_insurance',NULL,NULL,'school_zone','clear','Outering Road',NULL,6,'confirmed','2026-07-07 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(127,35,NULL,6,NULL,'red_light',NULL,NULL,'school_zone','fog','Eastern Bypass',NULL,4,'confirmed','2026-05-31 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(128,35,NULL,1,NULL,'no_seatbelt',NULL,NULL,'urban','clear','Jogoo Road',NULL,2,'confirmed','2026-09-29 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(129,36,NULL,1,NULL,'no_seatbelt',NULL,NULL,'highway','fog','Valley Road',NULL,2,'confirmed','2026-06-06 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(130,36,NULL,8,NULL,'no_insurance',NULL,NULL,'school_zone','clear','Mombasa Road',NULL,6,'confirmed','2026-08-09 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(131,37,NULL,8,NULL,'no_insurance',NULL,NULL,'urban','rain','Valley Road',NULL,6,'confirmed','2025-12-23 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(132,37,NULL,3,NULL,'dui',NULL,NULL,'highway','night','Waiyaki Way',NULL,8,'confirmed','2026-09-05 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(133,38,NULL,2,NULL,'illegal_parking',NULL,NULL,'school_zone','clear','Argwings Kodhek Road',NULL,1,'confirmed','2026-09-27 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(134,38,NULL,2,NULL,'speeding',110.00,NULL,'residential','fog','Jogoo Road',NULL,5,'confirmed','2026-07-08 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(135,39,NULL,6,NULL,'no_insurance',NULL,NULL,'school_zone','night','Moi Avenue',NULL,6,'confirmed','2025-12-17 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(136,39,NULL,3,NULL,'reckless_driving',NULL,NULL,'urban','rain','University Way',NULL,6,'confirmed','2026-03-19 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(137,39,NULL,3,NULL,'speeding',136.00,NULL,'urban','fog','Eastern Bypass',NULL,5,'confirmed','2026-07-10 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(138,39,NULL,4,NULL,'speeding',129.00,NULL,'residential','fog','Ronald Ngala Street',NULL,5,'confirmed','2025-11-24 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(139,39,NULL,3,NULL,'red_light',NULL,NULL,'school_zone','night','Uhuru Highway',NULL,4,'confirmed','2026-02-02 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(140,40,NULL,3,NULL,'no_license',NULL,NULL,'highway','fog','Thika Superhighway',NULL,8,'confirmed','2026-03-20 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(141,40,NULL,6,NULL,'reckless_driving',NULL,NULL,'urban','fog','Haile Selassie Avenue',NULL,6,'confirmed','2026-07-23 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(142,40,NULL,8,NULL,'illegal_parking',NULL,NULL,'highway','rain','Jogoo Road',NULL,1,'confirmed','2026-04-16 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(143,40,NULL,1,NULL,'no_seatbelt',NULL,NULL,'residential','fog','Uhuru Highway',NULL,2,'confirmed','2026-10-11 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(144,40,NULL,6,NULL,'no_seatbelt',NULL,NULL,'urban','clear','Moi Avenue',NULL,2,'confirmed','2026-08-29 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(145,41,NULL,7,NULL,'illegal_parking',NULL,NULL,'highway','rain','Waiyaki Way',NULL,1,'confirmed','2025-11-07 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(146,41,NULL,7,NULL,'phone_use',NULL,NULL,'urban','fog','University Way',NULL,3,'confirmed','2025-12-22 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(147,41,NULL,3,NULL,'overloading',NULL,NULL,'urban','night','Mombasa Road',NULL,4,'confirmed','2026-01-03 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(148,41,NULL,5,NULL,'phone_use',NULL,NULL,'highway','clear','Argwings Kodhek Road',NULL,3,'confirmed','2026-01-31 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(149,41,NULL,1,NULL,'no_license',NULL,NULL,'school_zone','fog','Langata Road',NULL,8,'confirmed','2026-06-28 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(150,42,NULL,7,NULL,'overloading',NULL,NULL,'school_zone','fog','Ronald Ngala Street',NULL,4,'confirmed','2025-10-30 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(151,42,NULL,3,NULL,'dui',NULL,NULL,'residential','clear','Outering Road',NULL,8,'confirmed','2026-10-03 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(152,42,NULL,7,NULL,'dui',NULL,NULL,'highway','clear','Waiyaki Way',NULL,8,'confirmed','2026-03-15 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(153,43,NULL,6,NULL,'no_insurance',NULL,NULL,'residential','fog','Uhuru Highway',NULL,6,'confirmed','2025-12-20 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(154,43,NULL,5,NULL,'overloading',NULL,NULL,'highway','clear','Tom Mboya Street',NULL,4,'confirmed','2026-07-13 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(155,43,NULL,6,NULL,'red_light',NULL,NULL,'school_zone','night','Nairobi-Nakuru Highway',NULL,4,'confirmed','2026-09-17 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(156,43,NULL,7,NULL,'speeding',88.00,NULL,'residential','clear','University Way',NULL,5,'confirmed','2026-01-31 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(157,43,NULL,3,NULL,'red_light',NULL,NULL,'urban','rain','Ngong Road',NULL,4,'confirmed','2026-01-24 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(158,44,NULL,7,NULL,'dui',NULL,NULL,'highway','fog','Outering Road',NULL,8,'confirmed','2025-12-21 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(159,44,NULL,7,NULL,'no_insurance',NULL,NULL,'urban','night','Outering Road',NULL,6,'confirmed','2026-04-23 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(160,44,NULL,1,NULL,'overloading',NULL,NULL,'residential','clear','Mombasa Road',NULL,4,'confirmed','2026-07-11 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(161,44,NULL,6,NULL,'no_seatbelt',NULL,NULL,'school_zone','rain','Waiyaki Way',NULL,2,'confirmed','2025-10-27 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(162,44,NULL,7,NULL,'dui',NULL,NULL,'highway','clear','Nairobi-Nakuru Highway',NULL,8,'confirmed','2026-06-19 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(163,45,NULL,2,NULL,'illegal_parking',NULL,NULL,'school_zone','rain','Argwings Kodhek Road',NULL,1,'confirmed','2026-02-04 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(164,45,NULL,4,NULL,'speeding',86.00,NULL,'residential','night','Waiyaki Way',NULL,5,'confirmed','2026-06-25 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(165,45,NULL,1,NULL,'red_light',NULL,NULL,'highway','rain','Jogoo Road',NULL,4,'confirmed','2026-05-14 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(166,45,NULL,1,NULL,'speeding',154.00,NULL,'residential','rain','Southern Bypass',NULL,5,'confirmed','2026-09-25 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(167,46,NULL,1,NULL,'red_light',NULL,NULL,'highway','clear','Eastern Bypass',NULL,4,'confirmed','2026-05-08 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(168,46,NULL,2,NULL,'dui',NULL,NULL,'highway','rain','Mombasa Road',NULL,8,'confirmed','2025-11-14 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(169,46,NULL,7,NULL,'illegal_parking',NULL,NULL,'school_zone','clear','Ronald Ngala Street',NULL,1,'confirmed','2026-07-23 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(170,47,NULL,8,NULL,'speeding',123.00,NULL,'urban','rain','Haile Selassie Avenue',NULL,5,'confirmed','2026-01-28 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(171,47,NULL,3,NULL,'no_seatbelt',NULL,NULL,'residential','night','Thika Superhighway',NULL,2,'confirmed','2026-02-21 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(172,47,NULL,6,NULL,'speeding',137.00,NULL,'residential','rain','Haile Selassie Avenue',NULL,5,'confirmed','2026-08-09 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(173,47,NULL,5,NULL,'no_insurance',NULL,NULL,'school_zone','clear','Langata Road',NULL,6,'confirmed','2026-06-04 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(174,47,NULL,6,NULL,'reckless_driving',NULL,NULL,'urban','night','Ngong Road',NULL,6,'confirmed','2026-06-11 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(175,47,NULL,4,NULL,'reckless_driving',NULL,NULL,'school_zone','clear','Argwings Kodhek Road',NULL,6,'confirmed','2025-12-07 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(176,48,NULL,6,NULL,'no_insurance',NULL,NULL,'urban','night','Eastern Bypass',NULL,6,'confirmed','2026-07-19 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(177,48,NULL,8,NULL,'no_insurance',NULL,NULL,'school_zone','rain','Uhuru Highway',NULL,6,'confirmed','2026-02-08 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(178,48,NULL,8,NULL,'speeding',148.00,NULL,'residential','clear','Ronald Ngala Street',NULL,5,'confirmed','2025-12-25 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(179,48,NULL,7,NULL,'speeding',88.00,NULL,'residential','clear','Southern Bypass',NULL,5,'confirmed','2026-08-20 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(180,49,NULL,4,NULL,'no_license',NULL,NULL,'highway','rain','University Way',NULL,8,'confirmed','2026-01-30 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(181,49,NULL,3,NULL,'illegal_parking',NULL,NULL,'urban','clear','Tom Mboya Street',NULL,1,'confirmed','2026-09-01 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(182,49,NULL,1,NULL,'phone_use',NULL,NULL,'residential','clear','Kipande Road',NULL,3,'confirmed','2025-11-03 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(183,49,NULL,3,NULL,'red_light',NULL,NULL,'school_zone','fog','Outering Road',NULL,4,'confirmed','2026-01-13 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(184,49,NULL,8,NULL,'no_insurance',NULL,NULL,'school_zone','night','Nairobi-Nakuru Highway',NULL,6,'confirmed','2026-08-23 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(185,50,NULL,5,NULL,'illegal_parking',NULL,NULL,'residential','clear','Ngong Road',NULL,1,'confirmed','2026-03-03 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37'),(186,50,NULL,5,NULL,'illegal_parking',NULL,NULL,'school_zone','rain','Nairobi-Nakuru Highway',NULL,1,'confirmed','2026-06-22 02:43:37','2026-10-16 02:43:37','2026-10-16 02:43:37');
/*!40000 ALTER TABLE `violations` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-17 12:39:41
