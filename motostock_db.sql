-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jul 29, 2026 at 06:07 AM
-- Server version: 8.4.7
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `motostock26`
--

-- --------------------------------------------------------

--
-- Table structure for table `customer`
--

DROP TABLE IF EXISTS `customer`;
CREATE TABLE IF NOT EXISTS `customer` (
  `customerID` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vehicleNo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastServiceDate` date DEFAULT NULL,
  `nextServiceDue` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`customerID`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer`
--

INSERT INTO `customer` (`customerID`, `name`, `phone`, `vehicleNo`, `lastServiceDate`, `nextServiceDue`, `created_at`) VALUES
(1, 'John Silva', '0771234567', 'WP ABC 1234', '2026-03-10', '2026-06-10', '2026-03-23 13:09:56'),
(2, 'Sara Perera', '0719876543', 'WP XYZ 5678', '2026-02-15', '2026-05-15', '2026-03-23 13:09:56'),
(3, 'Jayaweera', '458794563', 'PP-9834-908', '2026-03-24', '2026-03-26', '2026-03-23 23:41:53');

-- --------------------------------------------------------

--
-- Table structure for table `notification`
--

DROP TABLE IF EXISTS `notification`;
CREATE TABLE IF NOT EXISTS `notification` (
  `notificationID` int NOT NULL AUTO_INCREMENT,
  `type` varchar(40) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `toEmail` varchar(255) DEFAULT NULL,
  `emailSent` tinyint(1) NOT NULL DEFAULT '0',
  `sentAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notificationID`),
  KEY `idx_sentAt` (`sentAt`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `notification`
--

INSERT INTO `notification` (`notificationID`, `type`, `title`, `message`, `toEmail`, `emailSent`, `sentAt`) VALUES
(1, 'service_due', 'Service Reminder — Jayaweera', 'Dear Jayaweera, your vehicle (No: PP-9834-908) was due for service on 26 Mar 2026. Please visit us at your earliest convenience.', NULL, 0, '2026-04-28 00:07:48'),
(2, 'low_stock', 'Low Stock Summary — 1 part(s) need reordering', 'The following parts are below minimum stock: Engine Oil 10W-40', 'anaathithan@gmail.com', 0, '2026-04-28 00:08:07'),
(3, 'custom', 'Test0019328', 'testing if the email works', 'anaathithan@gmail.com', 0, '2026-04-28 00:08:57'),
(4, 'service_due', 'Service Reminder — Jayaweera', 'Dear Jayaweera, your vehicle (No: PP-9834-908) was due for service on 26 Mar 2026. Please visit us at your earliest convenience.', NULL, 0, '2026-04-28 00:16:21'),
(5, 'low_stock', 'Low Stock Summary — 1 part(s) need reordering', 'The following parts are below minimum stock: Engine Oil 10W-40', 'anaathithan@gmail.com', 0, '2026-04-28 00:16:30'),
(6, 'service_due', 'Service Reminder — Jayaweera', 'Dear Jayaweera, your vehicle (No: PP-9834-908) was due for service on 26 Mar 2026. Please visit us at your earliest convenience.', NULL, 0, '2026-04-28 00:16:34'),
(7, 'service_due', 'Service Reminder — Jayaweera', 'Dear Jayaweera, your vehicle (No: PP-9834-908) was due for service on 26 Mar 2026. Please visit us at your earliest convenience.', NULL, 0, '2026-04-28 00:17:56'),
(8, 'low_stock', 'Low Stock Summary — 1 part(s) need reordering', 'The following parts are below minimum stock: Engine Oil 10W-40', 'anaathithan@gmail.com', 0, '2026-04-28 00:18:01'),
(9, 'service_due', 'Service Reminder — Jayaweera', 'Dear Jayaweera, your vehicle (No: PP-9834-908) was due for service on 26 Mar 2026. Please visit us at your earliest convenience.', NULL, 0, '2026-04-28 00:21:34'),
(10, 'low_stock', 'Low Stock Summary — 1 part(s) need reordering', 'The following parts are below minimum stock: Engine Oil 10W-40', 'anaathithan@gmail.com', 0, '2026-04-28 00:43:00');

-- --------------------------------------------------------

--
-- Table structure for table `purchaseitem`
--

DROP TABLE IF EXISTS `purchaseitem`;
CREATE TABLE IF NOT EXISTS `purchaseitem` (
  `purchaseItemID` int NOT NULL AUTO_INCREMENT,
  `poID` int DEFAULT NULL,
  `partName` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `boughtPrice` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`purchaseItemID`),
  KEY `poID` (`poID`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchaseitem`
--

INSERT INTO `purchaseitem` (`purchaseItemID`, `poID`, `partName`, `quantity`, `boughtPrice`) VALUES
(1, 1, 'Fuel Tank', 1, 5000.00);

-- --------------------------------------------------------

--
-- Table structure for table `purchaseorder`
--

DROP TABLE IF EXISTS `purchaseorder`;
CREATE TABLE IF NOT EXISTS `purchaseorder` (
  `poID` int NOT NULL AUTO_INCREMENT,
  `supplierID` int DEFAULT NULL,
  `orderDate` datetime DEFAULT CURRENT_TIMESTAMP,
  `totalCost` decimal(10,2) DEFAULT NULL,
  `status` enum('Pending','Received','Partial') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  PRIMARY KEY (`poID`),
  KEY `supplierID` (`supplierID`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchaseorder`
--

INSERT INTO `purchaseorder` (`poID`, `supplierID`, `orderDate`, `totalCost`, `status`) VALUES
(1, 1, '2026-03-23 13:17:17', 5000.00, 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `sale`
--

DROP TABLE IF EXISTS `sale`;
CREATE TABLE IF NOT EXISTS `sale` (
  `saleID` int NOT NULL AUTO_INCREMENT,
  `customerID` int DEFAULT NULL,
  `customerName` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `saleDate` datetime DEFAULT CURRENT_TIMESTAMP,
  `subTotal` decimal(10,2) DEFAULT NULL,
  `discountPercent` decimal(5,2) DEFAULT '0.00',
  `grandTotal` decimal(10,2) DEFAULT NULL,
  `paymentMethod` enum('Cash','Online Transfer') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Cash',
  PRIMARY KEY (`saleID`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sale`
--

INSERT INTO `sale` (`saleID`, `customerID`, `customerName`, `saleDate`, `subTotal`, `discountPercent`, `grandTotal`, `paymentMethod`) VALUES
(6, NULL, 'nikesh', '2026-04-28 00:53:52', 3200.00, 0.00, 3200.00, 'Online Transfer'),
(5, NULL, 'Sameera', '2026-04-28 00:51:38', 3200.00, 0.00, 3200.00, 'Cash'),
(4, NULL, 'Perera', '2026-04-28 00:51:16', 450.00, 0.00, 450.00, 'Cash');

-- --------------------------------------------------------

--
-- Table structure for table `saleitem`
--

DROP TABLE IF EXISTS `saleitem`;
CREATE TABLE IF NOT EXISTS `saleitem` (
  `saleItemID` int NOT NULL AUTO_INCREMENT,
  `saleID` int DEFAULT NULL,
  `partName` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `sellingPrice` decimal(10,2) DEFAULT NULL,
  `itemTotal` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`saleItemID`),
  KEY `saleID` (`saleID`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `saleitem`
--

INSERT INTO `saleitem` (`saleItemID`, `saleID`, `partName`, `quantity`, `sellingPrice`, `itemTotal`) VALUES
(1, 1, 'Front Tire', 1, 3200.00, 3200.00),
(2, 2, 'Front Tire', 1, 3200.00, 3200.00),
(3, 3, 'Front Tire', 1, 3200.00, 3200.00),
(4, 4, 'Brake Cable', 1, 450.00, 450.00),
(5, 5, 'Front Tire', 1, 3200.00, 3200.00),
(6, 6, 'Front Tire', 1, 3200.00, 3200.00);

-- --------------------------------------------------------

--
-- Table structure for table `servicejob`
--

DROP TABLE IF EXISTS `servicejob`;
CREATE TABLE IF NOT EXISTS `servicejob` (
  `jobID` int NOT NULL AUTO_INCREMENT,
  `bikeNo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customerID` int DEFAULT NULL,
  `problemDescription` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('Investigating','InformingCustomer','Repairing','Finished') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Investigating',
  `isWarranty` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`jobID`),
  KEY `fk_servicejob_customer` (`customerID`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `servicejob`
--

INSERT INTO `servicejob` (`jobID`, `bikeNo`, `customerID`, `problemDescription`, `status`, `isWarranty`, `created_at`) VALUES
(1, 'WP ABC 1234', 1, 'Needs full Service', 'Investigating', 0, '2026-04-28 01:05:33');

-- --------------------------------------------------------

--
-- Table structure for table `sparepart`
--

DROP TABLE IF EXISTS `sparepart`;
CREATE TABLE IF NOT EXISTS `sparepart` (
  `partID` int NOT NULL AUTO_INCREMENT,
  `partName` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `brandName` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sellingPrice` decimal(10,2) DEFAULT NULL,
  `boughtPrice` decimal(10,2) DEFAULT NULL,
  `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currentQuantity` int DEFAULT '10',
  `minQuantity` int DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`partID`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sparepart`
--

INSERT INTO `sparepart` (`partID`, `partName`, `brandName`, `size`, `sellingPrice`, `boughtPrice`, `category`, `currentQuantity`, `minQuantity`, `created_at`) VALUES
(1, 'Engine Oil 10W-40', 'Castrol', '1L', 1250.00, 850.00, 'Oil & Lubricants', 3, 1, '2026-03-23 13:10:39'),
(2, 'Brake Cable', 'Yamaha', 'Standard', 450.00, 280.00, 'Dat Today Items', 3, 1, '2026-03-23 13:10:39'),
(3, 'Front Tire', 'MRF', '17 inch', 3200.00, 2100.00, 'Body Parts', 3, 1, '2026-03-23 13:10:39');

-- --------------------------------------------------------

--
-- Table structure for table `supplier`
--

DROP TABLE IF EXISTS `supplier`;
CREATE TABLE IF NOT EXISTS `supplier` (
  `supplierID` int NOT NULL AUTO_INCREMENT,
  `supplierName` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`supplierID`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier`
--

INSERT INTO `supplier` (`supplierID`, `supplierName`, `contact`, `address`) VALUES
(1, 'Yamaha Parts Lanka', '077-1234567', 'Colombo'),
(2, 'Castrol Distributor', '071-9876543', 'Negombo');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
CREATE TABLE IF NOT EXISTS `user` (
  `userID` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('Owner','Cashier','Employee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`userID`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`userID`, `username`, `password`, `role`) VALUES
(0, 'owner1', '$2y$10$YOf9qOdW3gBwkIHuxFOsNOZ401zWcobs.qcwBUlMfcUH4b2aGHYzu', 'Owner');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
