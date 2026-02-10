SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";




CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `activity` varchar(200) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



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
  `image_path` varchar(500) DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL
) ;

DELIMITER $$
CREATE TRIGGER `asset_history_insert` AFTER INSERT ON `assets` FOR EACH ROW BEGIN
    INSERT INTO asset_history (asset_history_id, asset_id, changed_by_user_id, change_type, change_summary)
    VALUES (UUID(), NEW.asset_id, NEW.updated_by, 'Create', 'Asset created');
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `asset_history_update` AFTER UPDATE ON `assets` FOR EACH ROW BEGIN
    DECLARE changes TEXT DEFAULT '';
    
    -- Check which fields changed
    IF OLD.asset_name != NEW.asset_name THEN
        SET changes = CONCAT(changes, 'Asset Name: ', OLD.asset_name, ' → ', NEW.asset_name, '; ');
    END IF;
    
    IF OLD.asset_status != NEW.asset_status THEN
        SET changes = CONCAT(changes, 'Status: ', OLD.asset_status, ' → ', NEW.asset_status, '; ');
    END IF;
    
    IF OLD.location_id != NEW.location_id THEN
        SET changes = CONCAT(changes, 'Location changed; ');
    END IF;
    
    IF OLD.assigned_to_user_id != NEW.assigned_to_user_id THEN
        SET changes = CONCAT(changes, 'Assignment changed; ');
    END IF;
    
    IF changes = '' THEN
        SET changes = 'Other updates';
    END IF;
    
    INSERT INTO asset_history (asset_history_id, asset_id, changed_by_user_id, change_type, change_summary)
    VALUES (UUID(), NEW.asset_id, NEW.updated_by, 'Update', changes);
END
$$
DELIMITER ;



CREATE TABLE `asset_classes` (
  `class_id` varchar(50) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



INSERT INTO `asset_classes` (`class_id`, `class_name`, `description`) VALUES
('CLASS001', 'Computers', NULL),
('CLASS002', 'Computer softwares', NULL),
('CLASS003', 'Furniture and fittings', NULL),
('CLASS004', 'Equipment', NULL),
('CLASS005', 'Office Equipment', NULL),
('CLASS006', 'Motor Vehicles', NULL);



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



CREATE TABLE `departments` (
  `department_id` varchar(50) NOT NULL,
  `name` varchar(50) NOT NULL,
  `department_head_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



INSERT INTO `departments` (`department_id`, `name`, `department_head_id`, `created_at`) VALUES
('DEPT001', 'Admin', NULL, '2026-01-22 03:20:39'),
('DEPT002', 'Finance', NULL, '2026-01-22 03:20:39'),
('DEPT003', 'Operations', NULL, '2026-01-22 03:20:39'),
('DEPT004', 'Warehouse', NULL, '2026-01-22 03:20:39'),
('DEPT005', 'IT', NULL, '2026-01-22 03:20:39');



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



CREATE TABLE `locations` (
  `location_id` varchar(50) NOT NULL,
  `name` varchar(200) NOT NULL,
  `barcode_qr` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



INSERT INTO `locations` (`location_id`, `name`, `barcode_qr`, `notes`, `created_at`) VALUES
('LOC001', 'Duta Jasa Warehouse', NULL, NULL, '2026-01-22 03:20:39'),
('LOC002', 'AT609', NULL, NULL, '2026-01-22 03:20:39'),
('LOC003', 'AT309', NULL, NULL, '2026-01-22 03:20:39'),
('LOC004', 'Nugen Office', NULL, NULL, '2026-01-22 03:20:39');



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



CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `notification_type` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `procurement` (
  `procurement_id` varchar(50) NOT NULL,
  `vendor` varchar(200) DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Ordered',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `procurement_assets` (
  `procurement_id` varchar(50) NOT NULL,
  `asset_id` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



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



CREATE TABLE `user_sessions` (
  `session_id` varchar(128) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `is_valid` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_activity_user` (`user_id`);


ALTER TABLE `assets`
  ADD PRIMARY KEY (`asset_id`),
  ADD UNIQUE KEY `asset_tag` (`asset_tag`),
  ADD UNIQUE KEY `serial_number` (`serial_number`),
  ADD KEY `fk_asset_updated_by` (`updated_by`),
  ADD KEY `idx_assets_status` (`asset_status`),
  ADD KEY `idx_assets_location` (`location_id`),
  ADD KEY `idx_assets_department` (`owner_department_id`),
  ADD KEY `idx_assets_user` (`assigned_to_user_id`),
  ADD KEY `idx_assets_warranty` (`warranty_expiry`);


ALTER TABLE `asset_classes`
  ADD PRIMARY KEY (`class_id`);


ALTER TABLE `asset_history`
  ADD PRIMARY KEY (`asset_history_id`),
  ADD KEY `fk_history_user` (`changed_by_user_id`),
  ADD KEY `idx_history_asset` (`asset_id`),
  ADD KEY `idx_history_date` (`changed_at`);


ALTER TABLE `asset_movements`
  ADD PRIMARY KEY (`movement_id`),
  ADD KEY `fk_movement_user` (`performed_by_user_id`),
  ADD KEY `fk_movement_from_location` (`from_location_id`),
  ADD KEY `fk_movement_to_location` (`to_location_id`),
  ADD KEY `idx_movements_asset` (`asset_id`),
  ADD KEY `idx_movements_date` (`movement_date`);


ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`),
  ADD KEY `fk_department_head` (`department_head_id`);


ALTER TABLE `disposals`
  ADD PRIMARY KEY (`write_off_id`),
  ADD KEY `fk_disposal_asset` (`asset_id`),
  ADD KEY `fk_disposal_user` (`written_off_by_user_id`);


ALTER TABLE `locations`
  ADD PRIMARY KEY (`location_id`);


ALTER TABLE `maintenance`
  ADD PRIMARY KEY (`maintenance_id`),
  ADD KEY `idx_maintenance_asset` (`asset_id`),
  ADD KEY `idx_maintenance_status` (`status`);


ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_notifications_user` (`user_id`),
  ADD KEY `idx_notifications_read` (`is_read`);


ALTER TABLE `procurement`
  ADD PRIMARY KEY (`procurement_id`);


ALTER TABLE `procurement_assets`
  ADD PRIMARY KEY (`procurement_id`,`asset_id`),
  ADD KEY `fk_procurement_assets_asset` (`asset_id`);


ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `reset_token_hash` (`reset_token_hash`),
  ADD KEY `idx_users_department` (`department_id`),
  ADD KEY `idx_users_role` (`role`);


ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `idx_sessions_user` (`user_id`),
  ADD KEY `idx_sessions_valid` (`is_valid`);


ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;


ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;


ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;


ALTER TABLE `assets`
  ADD CONSTRAINT `fk_asset_department` FOREIGN KEY (`owner_department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_asset_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`location_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_asset_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_asset_user` FOREIGN KEY (`assigned_to_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;


ALTER TABLE `asset_history`
  ADD CONSTRAINT `fk_history_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_history_user` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;


ALTER TABLE `asset_movements`
  ADD CONSTRAINT `fk_movement_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_movement_from_location` FOREIGN KEY (`from_location_id`) REFERENCES `locations` (`location_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_movement_to_location` FOREIGN KEY (`to_location_id`) REFERENCES `locations` (`location_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_movement_user` FOREIGN KEY (`performed_by_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;


ALTER TABLE `disposals`
  ADD CONSTRAINT `fk_disposal_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_disposal_user` FOREIGN KEY (`written_off_by_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;


ALTER TABLE `maintenance`
  ADD CONSTRAINT `fk_maintenance_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE;


ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;


ALTER TABLE `procurement_assets`
  ADD CONSTRAINT `fk_procurement_assets_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_procurement_assets_procurement` FOREIGN KEY (`procurement_id`) REFERENCES `procurement` (`procurement_id`) ON DELETE CASCADE;


ALTER TABLE `user_sessions`
  ADD CONSTRAINT `fk_session_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

