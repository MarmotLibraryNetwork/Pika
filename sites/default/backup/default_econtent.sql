-- MySQL dump 10.13  Distrib 5.7.12, for Win64 (x86_64)
--
-- Host: localhost    Database: econtent
-- ------------------------------------------------------
-- Server version	5.5.47-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `db_update`
--

DROP TABLE IF EXISTS `db_update`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `db_update` (
  `update_key` varchar(100) NOT NULL,
  `date_run` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`update_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `db_update`
--

LOCK TABLES `db_update` WRITE;
/*!40000 ALTER TABLE `db_update` DISABLE KEYS */;
INSERT INTO `db_update` VALUES ('overdrive_api_data','2016-06-30 17:11:12'),('overdrive_api_data_availability_type','2016-06-30 17:11:12'),('overdrive_api_data_update_1','2016-06-30 17:11:12'),('overdrive_api_data_update_2','2016-06-30 17:11:12'),('utf8_update','2016-06-30 17:11:12');
/*!40000 ALTER TABLE `db_update` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `overdrive_api_product_availability`
--

DROP TABLE IF EXISTS `overdrive_api_product_availability`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `overdrive_api_product_availability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `productId` int(11) DEFAULT NULL,
  `libraryId` int(11) DEFAULT NULL,
  `available` tinyint(1) DEFAULT NULL,
  `copiesOwned` int(11) DEFAULT NULL,
  `copiesAvailable` int(11) DEFAULT NULL,
  `numberOfHolds` int(11) DEFAULT NULL,
  `availabilityType` varchar(35) DEFAULT 'Normal',
  PRIMARY KEY (`id`),
  UNIQUE KEY `productId_2` (`productId`,`libraryId`),
  KEY `productId` (`productId`),
  KEY `libraryId` (`libraryId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `overdrive_api_product_availability`
--

LOCK TABLES `overdrive_api_product_availability` WRITE;
/*!40000 ALTER TABLE `overdrive_api_product_availability` DISABLE KEYS */;
/*!40000 ALTER TABLE `overdrive_api_product_availability` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `overdrive_api_product_creators`
--

DROP TABLE IF EXISTS `overdrive_api_product_creators`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `overdrive_api_product_creators` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `productId` int(11) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `name` varchar(215) DEFAULT NULL,
  `fileAs` varchar(215) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `productId` (`productId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `overdrive_api_product_creators`
--

LOCK TABLES `overdrive_api_product_creators` WRITE;
/*!40000 ALTER TABLE `overdrive_api_product_creators` DISABLE KEYS */;
/*!40000 ALTER TABLE `overdrive_api_product_creators` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `overdrive_api_product_formats`
--

DROP TABLE IF EXISTS `overdrive_api_product_formats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `overdrive_api_product_formats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `productId` int(11) DEFAULT NULL,
  `textId` varchar(25) DEFAULT NULL,
  `name` varchar(512) DEFAULT NULL,
  `fileName` varchar(215) DEFAULT NULL,
  `fileSize` int(11) DEFAULT NULL,
  `partCount` tinyint(4) DEFAULT NULL,
  `sampleSource_1` varchar(215) DEFAULT NULL,
  `sampleUrl_1` varchar(215) DEFAULT NULL,
  `sampleSource_2` varchar(215) DEFAULT NULL,
  `sampleUrl_2` varchar(215) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `productId_2` (`productId`,`textId`),
  KEY `productId` (`productId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `overdrive_api_product_formats`
--

LOCK TABLES `overdrive_api_product_formats` WRITE;
/*!40000 ALTER TABLE `overdrive_api_product_formats` DISABLE KEYS */;
/*!40000 ALTER TABLE `overdrive_api_product_formats` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `overdrive_api_product_identifiers`
--

DROP TABLE IF EXISTS `overdrive_api_product_identifiers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `overdrive_api_product_identifiers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `productId` int(11) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `value` varchar(75) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `productId` (`productId`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `overdrive_api_product_identifiers`
--

LOCK TABLES `overdrive_api_product_identifiers` WRITE;
/*!40000 ALTER TABLE `overdrive_api_product_identifiers` DISABLE KEYS */;
/*!40000 ALTER TABLE `overdrive_api_product_identifiers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `overdrive_api_product_languages`
--

DROP TABLE IF EXISTS `overdrive_api_product_languages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `overdrive_api_product_languages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(10) DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `overdrive_api_product_languages`
--

LOCK TABLES `overdrive_api_product_languages` WRITE;
/*!40000 ALTER TABLE `overdrive_api_product_languages` DISABLE KEYS */;
/*!40000 ALTER TABLE `overdrive_api_product_languages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `overdrive_api_product_languages_ref`
--

DROP TABLE IF EXISTS `overdrive_api_product_languages_ref`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `overdrive_api_product_languages_ref` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `productId` int(11) DEFAULT NULL,
  `languageId` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `productId` (`productId`,`languageId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `overdrive_api_product_languages_ref`
--

LOCK TABLES `overdrive_api_product_languages_ref` WRITE;
/*!40000 ALTER TABLE `overdrive_api_product_languages_ref` DISABLE KEYS */;
/*!40000 ALTER TABLE `overdrive_api_product_languages_ref` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `overdrive_api_product_metadata`
--

DROP TABLE IF EXISTS `overdrive_api_product_metadata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `overdrive_api_product_metadata` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `productId` int(11) DEFAULT NULL,
  `checksum` bigint(20) DEFAULT NULL,
  `sortTitle` varchar(512) DEFAULT NULL,
  `publisher` varchar(215) DEFAULT NULL,
  `publishDate` int(11) DEFAULT NULL,
  `isPublicDomain` tinyint(1) DEFAULT NULL,
  `isPublicPerformanceAllowed` tinyint(1) DEFAULT NULL,
  `shortDescription` text,
  `fullDescription` text,
  `starRating` float DEFAULT NULL,
  `popularity` int(11) DEFAULT NULL,
  `rawData` mediumtext,
  `thumbnail` varchar(255) DEFAULT NULL,
  `cover` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `productId` (`productId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `overdrive_api_product_metadata`
--

LOCK TABLES `overdrive_api_product_metadata` WRITE;
/*!40000 ALTER TABLE `overdrive_api_product_metadata` DISABLE KEYS */;
/*!40000 ALTER TABLE `overdrive_api_product_metadata` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `overdrive_api_product_subjects`
--

DROP TABLE IF EXISTS `overdrive_api_product_subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `overdrive_api_product_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(512) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `name` (`name`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `overdrive_api_product_subjects`
--

LOCK TABLES `overdrive_api_product_subjects` WRITE;
/*!40000 ALTER TABLE `overdrive_api_product_subjects` DISABLE KEYS */;
/*!40000 ALTER TABLE `overdrive_api_product_subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `overdrive_api_product_subjects_ref`
--

DROP TABLE IF EXISTS `overdrive_api_product_subjects_ref`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `overdrive_api_product_subjects_ref` (
  `productId` int(11) DEFAULT NULL,
  `subjectId` int(11) DEFAULT NULL,
  UNIQUE KEY `productId` (`productId`,`subjectId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `overdrive_api_product_subjects_ref`
--

LOCK TABLES `overdrive_api_product_subjects_ref` WRITE;
/*!40000 ALTER TABLE `overdrive_api_product_subjects_ref` DISABLE KEYS */;
/*!40000 ALTER TABLE `overdrive_api_product_subjects_ref` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `overdrive_api_products`
--

DROP TABLE IF EXISTS `overdrive_api_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `overdrive_api_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `overdriveId` varchar(36) NOT NULL,
  `mediaType` varchar(50) NOT NULL,
  `title` varchar(512) NOT NULL,
  `series` varchar(215) DEFAULT NULL,
  `primaryCreatorRole` varchar(50) DEFAULT NULL,
  `primaryCreatorName` varchar(215) DEFAULT NULL,
  `cover` varchar(215) DEFAULT NULL,
  `dateAdded` int(11) DEFAULT NULL,
  `dateUpdated` int(11) DEFAULT NULL,
  `lastMetadataCheck` int(11) DEFAULT NULL,
  `lastMetadataChange` int(11) DEFAULT NULL,
  `lastAvailabilityCheck` int(11) DEFAULT NULL,
  `lastAvailabilityChange` int(11) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `dateDeleted` int(11) DEFAULT NULL,
  `rawData` mediumtext DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `needsUpdate` tinyint(1) DEFAULT 0,
  `crossRefId` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `overdriveId` (`overdriveId`),
  KEY `dateUpdated` (`dateUpdated`),
  KEY `lastMetadataCheck` (`lastMetadataCheck`),
  KEY `lastAvailabilityCheck` (`lastAvailabilityCheck`),
  KEY `deleted` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `overdrive_api_products`
--

LOCK TABLES `overdrive_api_products` WRITE;
/*!40000 ALTER TABLE `overdrive_api_products` DISABLE KEYS */;
/*!40000 ALTER TABLE `overdrive_api_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `overdrive_extract_log`
--

DROP TABLE IF EXISTS `overdrive_extract_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `overdrive_extract_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `startTime` int(11) DEFAULT NULL,
  `endTime` int(11) DEFAULT NULL,
  `lastUpdate` int(11) DEFAULT NULL,
  `numProducts` int(11) DEFAULT '0',
  `numErrors` int(11) DEFAULT '0',
  `numAdded` int(11) DEFAULT '0',
  `numDeleted` int(11) DEFAULT '0',
  `numUpdated` int(11) DEFAULT '0',
  `numSkipped` int(11) DEFAULT '0',
  `numAvailabilityChanges` int(11) DEFAULT '0',
  `numMetadataChanges` int(11) DEFAULT '0',
  `numTitlesProcessed` INT UNSIGNED NULL DEFAULT NULL,
  `notes` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `overdrive_extract_log`
--

LOCK TABLES `overdrive_extract_log` WRITE;
/*!40000 ALTER TABLE `overdrive_extract_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `overdrive_extract_log` ENABLE KEYS */;
UNLOCK TABLES;

-- Dump completed on 2016-06-30 11:23:51
