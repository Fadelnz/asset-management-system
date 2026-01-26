-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jan 26, 2026 at 04:20 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_40289891_asset_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `activity` varchar(200) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

CREATE TABLE `assets` (
  `asset_id` varchar(30) NOT NULL,
  `asset_tag` varchar(30) DEFAULT NULL,
  `asset_name` varchar(200) NOT NULL,
  `asset_class` varchar(50) NOT NULL,
  `model` varchar(100) DEFAULT NULL,
  `manufacturer` varchar(200) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `purchase_order_number` varchar(20) DEFAULT NULL,
  `acquisition_date` date DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `vendor` varchar(100) DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `depreciation_method` varchar(50) DEFAULT NULL,
  `depreciation_rate` decimal(5,2) DEFAULT NULL,
  `depreciation_start_date` date DEFAULT NULL,
  `life_expectancy_years` int(11) DEFAULT NULL,
  `asset_status` varchar(50) NOT NULL DEFAULT 'In Stock',
  `location_id` varchar(50) DEFAULT NULL,
  `owner_department_id` varchar(50) DEFAULT NULL,
  `assigned_to_user_id` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `remarks` text DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asset_classes`
--

CREATE TABLE `asset_classes` (
  `class_id` varchar(50) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `asset_classes`
--

INSERT INTO `asset_classes` (`class_id`, `class_name`, `description`) VALUES
('CLASS001', 'Notebook', NULL),
('CLASS002', 'Desktop', NULL),
('CLASS003', 'Server', NULL),
('CLASS004', 'Network Device', NULL),
('CLASS005', 'Printer', NULL),
('CLASS006', 'Mobile Device', NULL),
('CLASS007', 'Storage', NULL),
('CLASS008', 'Audio Visual', NULL),
('CLASS009', 'Furniture', NULL),
('CLASS010', 'Software License', NULL),
('CLASS011', 'Other', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `asset_history`
--

CREATE TABLE `asset_history` (
  `asset_history_id` varchar(50) NOT NULL,
  `asset_id` varchar(30) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `changed_by_user_id` varchar(50) DEFAULT NULL,
  `change_type` varchar(50) NOT NULL,
  `change_summary` text DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asset_movements`
--

CREATE TABLE `asset_movements` (
  `movement_id` varchar(50) NOT NULL,
  `asset_id` varchar(30) NOT NULL,
  `movement_type` varchar(50) NOT NULL,
  `performed_by_user_id` varchar(50) NOT NULL,
  `movement_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `from_location_id` varchar(50) DEFAULT NULL,
  `to_location_id` varchar(50) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `department_id` varchar(50) NOT NULL,
  `name` varchar(50) NOT NULL,
  `department_head_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`department_id`, `name`, `department_head_id`, `created_at`) VALUES
('DEPT001', 'Administration', NULL, '2026-01-22 03:20:39'),
('DEPT002', 'Finance', NULL, '2026-01-22 03:20:39'),
('DEPT003', 'Operations', NULL, '2026-01-22 03:20:39'),
('DEPT004', 'Warehouse', NULL, '2026-01-22 03:20:39'),
('DEPT005', 'IT', NULL, '2026-01-22 03:20:39');

-- --------------------------------------------------------

--
-- Table structure for table `disposals`
--

CREATE TABLE `disposals` (
  `write_off_id` varchar(50) NOT NULL,
  `asset_id` varchar(30) NOT NULL,
  `reason` varchar(200) NOT NULL,
  `disposal_date` date DEFAULT NULL,
  `method` varchar(200) DEFAULT NULL,
  `net_book_value` decimal(10,2) DEFAULT NULL,
  `written_off_by_user_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `location_id` varchar(50) NOT NULL,
  `name` varchar(200) NOT NULL,
  `barcode_qr` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`location_id`, `name`, `barcode_qr`, `notes`, `created_at`) VALUES
('LOC001', 'Main Warehouse', NULL, NULL, '2026-01-22 03:20:39'),
('LOC002', 'Office Building A', NULL, NULL, '2026-01-22 03:20:39'),
('LOC003', 'IT Server Room', NULL, NULL, '2026-01-22 03:20:39');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance`
--

CREATE TABLE `maintenance` (
  `maintenance_id` varchar(50) NOT NULL,
  `asset_id` varchar(30) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `maintenance_type` varchar(100) DEFAULT NULL,
  `provider` varchar(200) DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Scheduled',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `notification_type` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `procurement`
--

CREATE TABLE `procurement` (
  `procurement_id` varchar(50) NOT NULL,
  `vendor` varchar(200) DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Ordered',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `procurement_assets`
--

CREATE TABLE `procurement_assets` (
  `procurement_id` varchar(50) NOT NULL,
  `asset_id` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` varchar(50) NOT NULL,
  `username` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `reset_token_hash` varchar(64) DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL,
  `full_name` varchar(50) DEFAULT NULL,
  `email` varchar(30) DEFAULT NULL,
  `role` varchar(100) NOT NULL,
  `department_id` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password_hash`, `reset_token_hash`, `reset_token_expires_at`, `full_name`, `email`, `role`, `department_id`, `is_active`, `created_at`, `last_login`) VALUES
('USR202601220888', 'Arnep', '$2y$10$.t81nbLXfi8GeDTtv4yw0OleJ581.HEHbGOXClFhRMymD0KjTwmDK', NULL, NULL, 'arnep', 'Arnep@gmailcom', 'accountant', '', 1, '2026-01-22 07:04:11', '2026-01-22 08:27:30'),
('USR202601224072', 'del', '$2y$10$ePamkvTBWYcQWDx6I5LuWOBh1S7kCGMZz7Zmw5ht5tj5EY9HgpoVi', '1235eae98a6a403469c7ea7bf5c9d38c2b13aed872770afc7dd17a9356ba2902', '2026-01-26 03:50:30', 'del', 'del@gmail.com', 'warehouse_coordinator', '', 1, '2026-01-22 07:05:25', '2026-01-22 08:25:35'),
('USR202601224120', 'fed', '$2y$10$vHqTbB4CKRCWzqnvdT22Z.HC2YK7iSQv3CwQzLZLSwHLuw5QM/bM.', NULL, NULL, 'fed', 'fed2@gmail.com', 'operation_manager', '', 1, '2026-01-22 07:40:48', '2026-01-22 08:27:36'),
('USR202601229474', 'nep', '$2y$10$OlYnC.UcPZME/vu0U/Bx.uyHqwmeqKP/36gzxPY8WNLFR9jtRPTbu', 'c60b6be898fbcf8f35d7d8ca6977978cfc0b8304bbb8399f32003b374f4773f4', '2026-01-26 03:44:12', 'hanif Fadhil', 'nep@gmail.com', 'warehouse_coordinator', '', 1, '2026-01-22 07:39:40', '2026-01-26 00:48:33'),
('USR202601230422', 'del1', '$2y$10$ieF9lg8VUDmCK.OycUPPwuwhuFhea/88kFBTwmtyrRBzn3jXAtY0K', NULL, NULL, 'del1', 'del1@gmail.com', 'operation_team', '', 1, '2026-01-23 01:57:11', '2026-01-23 01:57:21'),
('USR202601233858', 'admin', '$2y$10$lehFQoEI61CLkBXKej7G3unW3oKX2G7kdRRBtjdrPdRxlaOD00ZIW', NULL, NULL, 'admin', 'admin@gmail.com', 'admin', '', 1, '2026-01-23 02:01:51', '2026-01-26 04:10:54'),
('USR202601237694', 'operation', '$2y$10$pSwZiMuv0NKRo2rO6WUrN.ObL8pcMewjSn1KadqqJctQkocUa4kzG', NULL, NULL, 'Operation', 'operation@gmail.com', 'operation_team', '', 1, '2026-01-23 02:02:17', '2026-01-26 00:49:09'),
('USR202601238270', 'warehouse', '$2y$10$9iLDMlvoq3Cm0/3zZo.kh.F/Ui6h1P7ZPQvP2KE2TuNVq4o2W5oxK', NULL, NULL, 'warehouse', 'ware@gmail.com', 'warehouse_coordinator', '', 1, '2026-01-23 02:03:46', '2026-01-26 00:49:23'),
('USR202601262993', 'abangfadhil2', '$2y$10$5mO.XUJebhie6WlhZ8RNQu3EACFaZnOmdBojB36UjHnRd23a0TVUG', 'c1ce8e9e4c0c1ab024e9b2133255b8c59e8ffe082dc9c51961af3e31fe1e644b', '2026-01-26 04:01:25', 'muhd hanif fadhil', 'haniffadhil@icloud.com', 'accountant', '', 1, '2026-01-26 02:31:10', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `session_id` varchar(128) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `is_valid` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_activity_user` (`user_id`);

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`asset_id`),
  ADD UNIQUE KEY `asset_tag` (`asset_tag`),
  ADD UNIQUE KEY `serial_number` (`serial_number`),
  ADD KEY `fk_asset_updated_by` (`updated_by`),
  ADD KEY `fk_asset_created_by` (`created_by`),
  ADD KEY `idx_assets_status` (`asset_status`),
  ADD KEY `idx_assets_location` (`location_id`),
  ADD KEY `idx_assets_department` (`owner_department_id`),
  ADD KEY `idx_assets_user` (`assigned_to_user_id`),
  ADD KEY `idx_assets_warranty` (`warranty_expiry`);

--
-- Indexes for table `asset_classes`
--
ALTER TABLE `asset_classes`
  ADD PRIMARY KEY (`class_id`);

--
-- Indexes for table `asset_history`
--
ALTER TABLE `asset_history`
  ADD PRIMARY KEY (`asset_history_id`),
  ADD KEY `fk_history_user` (`changed_by_user_id`),
  ADD KEY `idx_history_asset` (`asset_id`),
  ADD KEY `idx_history_date` (`changed_at`);

--
-- Indexes for table `asset_movements`
--
ALTER TABLE `asset_movements`
  ADD PRIMARY KEY (`movement_id`),
  ADD KEY `fk_movement_user` (`performed_by_user_id`),
  ADD KEY `fk_movement_from_location` (`from_location_id`),
  ADD KEY `fk_movement_to_location` (`to_location_id`),
  ADD KEY `idx_movements_asset` (`asset_id`),
  ADD KEY `idx_movements_date` (`movement_date`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`),
  ADD KEY `fk_department_head` (`department_head_id`);

--
-- Indexes for table `disposals`
--
ALTER TABLE `disposals`
  ADD PRIMARY KEY (`write_off_id`),
  ADD KEY `fk_disposal_asset` (`asset_id`),
  ADD KEY `fk_disposal_user` (`written_off_by_user_id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`location_id`);

--
-- Indexes for table `maintenance`
--
ALTER TABLE `maintenance`
  ADD PRIMARY KEY (`maintenance_id`),
  ADD KEY `idx_maintenance_asset` (`asset_id`),
  ADD KEY `idx_maintenance_status` (`status`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_notifications_user` (`user_id`),
  ADD KEY `idx_notifications_read` (`is_read`);

--
-- Indexes for table `procurement`
--
ALTER TABLE `procurement`
  ADD PRIMARY KEY (`procurement_id`);

--
-- Indexes for table `procurement_assets`
--
ALTER TABLE `procurement_assets`
  ADD PRIMARY KEY (`procurement_id`,`asset_id`),
  ADD KEY `fk_procurement_assets_asset` (`asset_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `reset_token_hash` (`reset_token_hash`),
  ADD KEY `idx_users_department` (`department_id`),
  ADD KEY `idx_users_role` (`role`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `idx_sessions_user` (`user_id`),
  ADD KEY `idx_sessions_valid` (`is_valid`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `assets`
--
ALTER TABLE `assets`
  ADD CONSTRAINT `fk_asset_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_asset_department` FOREIGN KEY (`owner_department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_asset_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`location_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_asset_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_asset_user` FOREIGN KEY (`assigned_to_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `asset_history`
--
ALTER TABLE `asset_history`
  ADD CONSTRAINT `fk_history_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_history_user` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `asset_movements`
--
ALTER TABLE `asset_movements`
  ADD CONSTRAINT `fk_movement_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_movement_from_location` FOREIGN KEY (`from_location_id`) REFERENCES `locations` (`location_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_movement_to_location` FOREIGN KEY (`to_location_id`) REFERENCES `locations` (`location_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_movement_user` FOREIGN KEY (`performed_by_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `disposals`
--
ALTER TABLE `disposals`
  ADD CONSTRAINT `fk_disposal_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_disposal_user` FOREIGN KEY (`written_off_by_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `maintenance`
--
ALTER TABLE `maintenance`
  ADD CONSTRAINT `fk_maintenance_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `procurement_assets`
--
ALTER TABLE `procurement_assets`
  ADD CONSTRAINT `fk_procurement_assets_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_procurement_assets_procurement` FOREIGN KEY (`procurement_id`) REFERENCES `procurement` (`procurement_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `fk_session_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;