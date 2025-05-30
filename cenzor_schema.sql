-- MySQL dump 10.13  Distrib 8.0.41, for Win64 (x86_64)
--
-- Host: localhost    Database: cenzor
-- ------------------------------------------------------
-- Server version	5.5.5-10.11.6-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `account_funds`
--

DROP TABLE IF EXISTS `account_funds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `account_funds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `fund_type` enum('special_fund','ball_fund','general') NOT NULL,
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `account_id` (`account_id`),
  CONSTRAINT `account_funds_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `accounts`
--

DROP TABLE IF EXISTS `accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `apartment_fees`
--

DROP TABLE IF EXISTS `apartment_fees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `apartment_fees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `apartment_id` int(11) NOT NULL,
  `month_key` char(7) NOT NULL,
  `issued_date` date DEFAULT NULL,
  `payment_deadline` date DEFAULT NULL,
  `fond_rulment` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) DEFAULT NULL,
  `restante_cote` decimal(10,2) DEFAULT NULL,
  `restante_fond_rulment` decimal(10,2) DEFAULT NULL,
  `restante_fond_penalizari` decimal(10,2) DEFAULT NULL,
  `total_restante` decimal(10,2) GENERATED ALWAYS AS (`restante_cote` + `restante_fond_rulment` + `restante_fond_penalizari`) STORED,
  `utilities` decimal(10,2) DEFAULT 0.00,
  `previous_unpaid` decimal(10,2) DEFAULT 0.00,
  `penalties` decimal(10,2) DEFAULT 0.00,
  `fond_special` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) GENERATED ALWAYS AS (`restante_cote` + `restante_fond_rulment` + `restante_fond_penalizari` + `total`) STORED,
  `createtime` datetime NOT NULL DEFAULT current_timestamp(),
  `updatetime` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `apartment_id` (`apartment_id`),
  CONSTRAINT `apartment_fees_ibfk_1` FOREIGN KEY (`apartment_id`) REFERENCES `apartments` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=256 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `apartment_occupancy`
--

DROP TABLE IF EXISTS `apartment_occupancy`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `apartment_occupancy` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `apartment_id` int(11) NOT NULL,
  `month_key` char(7) NOT NULL,
  `num_people` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `apartment_id` (`apartment_id`),
  CONSTRAINT `` FOREIGN KEY (`apartment_id`) REFERENCES `apartments` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=260 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `apartments`
--

DROP TABLE IF EXISTS `apartments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `apartments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scara` varchar(3) DEFAULT NULL,
  `number` varchar(10) NOT NULL,
  `owner_name` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3004 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bill_attachments`
--

DROP TABLE IF EXISTS `bill_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bill_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `upload_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `bill_id` (`bill_id`),
  CONSTRAINT `bill_attachments_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `building_bills` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `building_bills`
--

DROP TABLE IF EXISTS `building_bills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `building_bills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_type` varchar(255) DEFAULT NULL,
  `bill_no` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `bill_date` date NOT NULL,
  `bill_deadline` date DEFAULT NULL,
  `account_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `repartizare_luna` date DEFAULT NULL,
  `furnizor_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `account_id` (`account_id`),
  KEY `fk_building_bills_furnizor` (`furnizor_id`),
  CONSTRAINT `building_bills_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `fk_building_bills_furnizor` FOREIGN KEY (`furnizor_id`) REFERENCES `furnizori` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `credit_usage`
--

DROP TABLE IF EXISTS `credit_usage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `credit_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `credit_id` int(11) NOT NULL,
  `used_payment_id` int(11) NOT NULL,
  `used_amount` decimal(10,2) NOT NULL,
  `used_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `credit_id` (`credit_id`),
  KEY `used_payment_id` (`used_payment_id`),
  CONSTRAINT `credit_usage_ibfk_1` FOREIGN KEY (`credit_id`) REFERENCES `payment_credits` (`id`),
  CONSTRAINT `credit_usage_ibfk_2` FOREIGN KEY (`used_payment_id`) REFERENCES `payments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `expense_attachments`
--

DROP TABLE IF EXISTS `expense_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expense_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `upload_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `expense_id` (`expense_id`),
  CONSTRAINT `expense_attachments_ibfk_1` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `expense_payments`
--

DROP TABLE IF EXISTS `expense_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expense_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_id` int(11) NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `account_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `expense_id` (`expense_id`),
  KEY `account_id` (`account_id`),
  CONSTRAINT `expense_payments_ibfk_1` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`),
  CONSTRAINT `expense_payments_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `account_id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `expense_deadline` date DEFAULT NULL,
  `repartizare_luna` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `account_id` (`account_id`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `facturi`
--

DROP TABLE IF EXISTS `facturi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facturi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `FurnizorNume` varchar(255) DEFAULT NULL,
  `FurnizorCIF` bigint(20) DEFAULT NULL,
  `FurnizorNrRegCom` varchar(255) DEFAULT NULL,
  `FurnizorCapital` varchar(255) DEFAULT NULL,
  `FurnizorTara` varchar(100) DEFAULT NULL,
  `FurnizorJudet` varchar(100) DEFAULT NULL,
  `FurnizorAdresa` text DEFAULT NULL,
  `FurnizorBanca` varchar(255) DEFAULT NULL,
  `FurnizorIBAN` varchar(50) DEFAULT NULL,
  `FurnizorInformatiiSuplimentare` text DEFAULT NULL,
  `ClientNume` varchar(255) DEFAULT NULL,
  `ClientInformatiiSuplimentare` text DEFAULT NULL,
  `ClientCIF` varchar(100) DEFAULT NULL,
  `ClientNrRegCom` varchar(100) DEFAULT NULL,
  `ClientTara` varchar(100) DEFAULT NULL,
  `ClientJudet` varchar(100) DEFAULT NULL,
  `ClientAdresa` text DEFAULT NULL,
  `ClientBanca` varchar(255) DEFAULT NULL,
  `ClientIBAN` varchar(50) DEFAULT NULL,
  `ClientTelefon` varchar(50) DEFAULT NULL,
  `ClientMail` varchar(255) DEFAULT NULL,
  `FacturaNumar` int(11) DEFAULT NULL,
  `FacturaData` date DEFAULT NULL,
  `FacturaScadenta` date DEFAULT NULL,
  `FacturaTaxareInversa` varchar(10) DEFAULT NULL,
  `FacturaTVAIncasare` varchar(10) DEFAULT NULL,
  `FacturaTip` varchar(100) DEFAULT NULL,
  `FacturaInformatiiSuplimentare` text DEFAULT NULL,
  `FacturaMoneda` varchar(10) DEFAULT NULL,
  `FacturaCotaTVA` decimal(5,2) DEFAULT NULL,
  `FacturaID` varchar(100) DEFAULT NULL,
  `FacturaGreutate` int(11) DEFAULT NULL,
  `Total` decimal(15,2) DEFAULT NULL,
  `TotalTVA` decimal(15,2) DEFAULT NULL,
  `TotalValoare` decimal(15,2) DEFAULT NULL,
  `SoldClient` text DEFAULT NULL,
  `txtObservatii` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=135 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `facturi_linii`
--

DROP TABLE IF EXISTS `facturi_linii`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facturi_linii` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `factura_id` int(11) DEFAULT NULL,
  `LinieNrCrt` int(11) DEFAULT NULL,
  `Gestiune` varchar(255) DEFAULT NULL,
  `Activitate` varchar(255) DEFAULT NULL,
  `Descriere` text DEFAULT NULL,
  `CodArticolFurnizor` varchar(255) DEFAULT NULL,
  `CodArticolClient` varchar(255) DEFAULT NULL,
  `CodBare` varchar(255) DEFAULT NULL,
  `InformatiiSuplimentare` text DEFAULT NULL,
  `UM` varchar(50) DEFAULT NULL,
  `Cantitate` int(11) DEFAULT NULL,
  `Pret` decimal(15,2) DEFAULT NULL,
  `Valoare` decimal(15,2) DEFAULT NULL,
  `ProcTVA` decimal(15,2) DEFAULT NULL,
  `TVA` decimal(5,2) DEFAULT NULL,
  `Cont` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `factura_id` (`factura_id`),
  CONSTRAINT `facturi_linii_ibfk_1` FOREIGN KEY (`factura_id`) REFERENCES `facturi` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2651 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `furnizori`
--

DROP TABLE IF EXISTS `furnizori`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `furnizori` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nume` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payment_credits`
--

DROP TABLE IF EXISTS `payment_credits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_credits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `apartment_id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `credit_amount` decimal(10,2) NOT NULL,
  `remaining_amount` decimal(10,2) NOT NULL,
  `fund_type` enum('ball_fund','utilities','special_fund','penalties','previous_unpaid','other') NOT NULL,
  `created_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','fully_used','expired') DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `payment_id` (`payment_id`),
  KEY `idx_apartment_status` (`apartment_id`,`status`),
  KEY `idx_fund_type` (`fund_type`),
  CONSTRAINT `payment_credits_ibfk_1` FOREIGN KEY (`apartment_id`) REFERENCES `apartments` (`id`),
  CONSTRAINT `payment_credits_ibfk_2` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payment_links`
--

DROP TABLE IF EXISTS `payment_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) DEFAULT NULL,
  `expense_id` int(11) DEFAULT NULL,
  `apartment_fee_id` int(11) DEFAULT NULL,
  `rent_id` int(11) DEFAULT NULL,
  `tenant_invoice_id` int(11) DEFAULT NULL,
  `building_bills_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `fund_type` enum('intretinere','fond special','fond rulment','other','utilities','facturi utilitati','comision','transfer','utilitati chiriasi','chirie') DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `createtime` datetime NOT NULL DEFAULT current_timestamp(),
  `updatetime` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `payment_id` (`payment_id`),
  KEY `expense_id` (`expense_id`),
  KEY `apartment_fee_id` (`apartment_fee_id`),
  KEY `rent_id` (`rent_id`),
  KEY `building_bills_id` (`building_bills_id`),
  KEY `payment_links_ibfk_6` (`tenant_invoice_id`),
  CONSTRAINT `payment_links_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`),
  CONSTRAINT `payment_links_ibfk_2` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`),
  CONSTRAINT `payment_links_ibfk_3` FOREIGN KEY (`apartment_fee_id`) REFERENCES `apartment_fees` (`id`),
  CONSTRAINT `payment_links_ibfk_4` FOREIGN KEY (`rent_id`) REFERENCES `rents` (`id`),
  CONSTRAINT `payment_links_ibfk_5` FOREIGN KEY (`building_bills_id`) REFERENCES `building_bills` (`id`),
  CONSTRAINT `payment_links_ibfk_6` FOREIGN KEY (`tenant_invoice_id`) REFERENCES `tenant_invoices` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=224 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `apartment_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `fund_type` enum('intretinere','fond special','fond rulment','other','utilities','facturi utilitati','comision','transfer','utilitati chiriasi','chirie') DEFAULT NULL,
  `account_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `rent_id` int(11) DEFAULT NULL,
  `createtime` datetime NOT NULL DEFAULT current_timestamp(),
  `updatetime` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `apartment_id` (`apartment_id`),
  KEY `account_id` (`account_id`),
  KEY `rent_id` (`rent_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`apartment_id`) REFERENCES `apartments` (`id`),
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`rent_id`) REFERENCES `rents` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=232 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rents`
--

DROP TABLE IF EXISTS `rents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `space_id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `rent_fee` decimal(10,2) NOT NULL,
  `utilities_fee` decimal(10,2) DEFAULT 0.00,
  `issued_date` date NOT NULL,
  `payment_deadline` date NOT NULL,
  `account_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `total` decimal(10,2) GENERATED ALWAYS AS (`rent_fee` + `utilities_fee`) STORED,
  PRIMARY KEY (`id`),
  KEY `account_id` (`account_id`),
  KEY `space_id` (`space_id`),
  CONSTRAINT `rents_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `rents_ibfk_2` FOREIGN KEY (`space_id`) REFERENCES `spaces` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `spaces`
--

DROP TABLE IF EXISTS `spaces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `spaces` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `location` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tenant_contract_attachments`
--

DROP TABLE IF EXISTS `tenant_contract_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_contract_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contract_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `upload_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `contract_id` (`contract_id`),
  CONSTRAINT `tenant_contract_attachments_ibfk_1` FOREIGN KEY (`contract_id`) REFERENCES `tenant_contracts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tenant_contracts`
--

DROP TABLE IF EXISTS `tenant_contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_contracts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `space_id` int(11) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `utilities_fee` decimal(10,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'RON',
  `payment_deadline` int(11) DEFAULT 15,
  `payment_frequency` enum('monthly','yearly') DEFAULT 'monthly',
  `account_id` int(11) NOT NULL,
  `status` enum('active','expired','terminated') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `space_id` (`space_id`),
  KEY `account_id` (`account_id`),
  CONSTRAINT `tenant_contracts_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tenant_contracts_ibfk_2` FOREIGN KEY (`space_id`) REFERENCES `spaces` (`id`),
  CONSTRAINT `tenant_contracts_ibfk_3` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tenant_invoice_attachments`
--

DROP TABLE IF EXISTS `tenant_invoice_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_invoice_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `upload_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`),
  CONSTRAINT `tenant_invoice_attachments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `tenant_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tenant_invoices`
--

DROP TABLE IF EXISTS `tenant_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `invoice_number` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `rent_amount` decimal(10,2) DEFAULT 0.00,
  `utilities_amount` decimal(10,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'RON',
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `status` enum('paid','unpaid','partial') DEFAULT 'unpaid',
  `payment_date` date DEFAULT NULL,
  `payment_amount` decimal(10,2) DEFAULT NULL,
  `account_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `contract_id` (`contract_id`),
  KEY `account_id` (`account_id`),
  CONSTRAINT `tenant_invoices_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tenant_invoices_ibfk_2` FOREIGN KEY (`contract_id`) REFERENCES `tenant_contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tenant_invoices_ibfk_3` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tenants`
--

DROP TABLE IF EXISTS `tenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transfers`
--

DROP TABLE IF EXISTS `transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transfers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_account_id` int(11) NOT NULL,
  `to_account_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `fund_type` enum('intretinere','fond special','fond rulment','other','utilities','facturi utilitati','comision','transfer','utilitati chiriasi') DEFAULT NULL,
  `transfer_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `from_account_id` (`from_account_id`),
  KEY `to_account_id` (`to_account_id`),
  CONSTRAINT `transfers_ibfk_1` FOREIGN KEY (`from_account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `transfers_ibfk_2` FOREIGN KEY (`to_account_id`) REFERENCES `accounts` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(64) DEFAULT NULL,
  `last_name` varchar(64) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-05-30 10:41:17
