-- BDPT Database Backup
-- Generated: 2026-02-05 07:02:38
-- Barangay Dev Project Tracker v1.0

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `action_logs`;
CREATE TABLE `action_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `action_taken` varchar(500) NOT NULL,
  `notes` text DEFAULT NULL,
  `logged_by` varchar(150) DEFAULT NULL,
  `log_date` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_action_logs_project` (`project_id`),
  KEY `idx_action_logs_date` (`log_date`),
  CONSTRAINT `action_logs_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `action_logs` (`id`, `project_id`, `action_taken`, `notes`, `logged_by`, `log_date`, `created_at`) VALUES ('1', '1', 'Started excavation work', 'Mobilized 10 workers', 'Juan Dela Cruz', '2026-01-20 09:00:00', '2026-02-05 13:49:52');

DROP TABLE IF EXISTS `attachments`;
CREATE TABLE `attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_original_name` varchar(255) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_attachments_project` (`project_id`),
  CONSTRAINT `attachments_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `budget_items`;
CREATE TABLE `budget_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `budget_category` enum('MOOE','Capital Outlay','Personnel Services') DEFAULT 'MOOE',
  `quantity` decimal(10,2) DEFAULT 1.00,
  `unit` varchar(50) DEFAULT 'pcs',
  `unit_cost` decimal(15,2) DEFAULT 0.00,
  `total_cost` decimal(15,2) GENERATED ALWAYS AS (`quantity` * `unit_cost`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_budget_items_project` (`project_id`),
  CONSTRAINT `budget_items_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `budget_items` (`id`, `project_id`, `item_name`, `budget_category`, `quantity`, `unit`, `unit_cost`, `created_at`) VALUES ('1', '1', 'Cement', 'MOOE', '50.00', 'bags', '300.00', '2026-02-05 13:49:51');
INSERT INTO `budget_items` (`id`, `project_id`, `item_name`, `budget_category`, `quantity`, `unit`, `unit_cost`, `created_at`) VALUES ('2', '1', 'Gravel', 'MOOE', '10.00', 'cu.m', '2500.00', '2026-02-05 13:49:51');

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`id`, `name`, `is_default`, `created_at`) VALUES ('1', 'Infrastructure', '1', '2026-02-05 13:29:34');
INSERT INTO `categories` (`id`, `name`, `is_default`, `created_at`) VALUES ('2', 'Health & Sanitation', '1', '2026-02-05 13:29:34');
INSERT INTO `categories` (`id`, `name`, `is_default`, `created_at`) VALUES ('3', 'Education', '1', '2026-02-05 13:29:34');
INSERT INTO `categories` (`id`, `name`, `is_default`, `created_at`) VALUES ('4', 'Livelihood & Agriculture', '1', '2026-02-05 13:29:34');
INSERT INTO `categories` (`id`, `name`, `is_default`, `created_at`) VALUES ('5', 'Peace & Order', '1', '2026-02-05 13:29:34');
INSERT INTO `categories` (`id`, `name`, `is_default`, `created_at`) VALUES ('6', 'Disaster Preparedness', '1', '2026-02-05 13:29:34');
INSERT INTO `categories` (`id`, `name`, `is_default`, `created_at`) VALUES ('7', 'Environmental Protection', '1', '2026-02-05 13:29:34');
INSERT INTO `categories` (`id`, `name`, `is_default`, `created_at`) VALUES ('8', 'Social Welfare', '1', '2026-02-05 13:29:34');
INSERT INTO `categories` (`id`, `name`, `is_default`, `created_at`) VALUES ('9', 'Sports & Youth Development', '1', '2026-02-05 13:29:34');
INSERT INTO `categories` (`id`, `name`, `is_default`, `created_at`) VALUES ('10', 'Other', '1', '2026-02-05 13:29:34');

DROP TABLE IF EXISTS `milestones`;
CREATE TABLE `milestones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `target_date` date DEFAULT NULL,
  `completed_date` date DEFAULT NULL,
  `status` enum('Pending','Done') DEFAULT 'Pending',
  `notes` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_milestones_project` (`project_id`),
  CONSTRAINT `milestones_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `milestones` (`id`, `project_id`, `title`, `target_date`, `completed_date`, `status`, `notes`, `sort_order`, `created_at`) VALUES ('1', '1', 'Site Clearing', '2026-01-15', '2026-02-05', 'Done', 'Clear the project area', '1', '2026-02-05 13:49:51');
INSERT INTO `milestones` (`id`, `project_id`, `title`, `target_date`, `completed_date`, `status`, `notes`, `sort_order`, `created_at`) VALUES ('2', '1', 'Foundation Work', '2026-01-30', NULL, 'Pending', '', '2', '2026-02-05 13:49:52');

DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `project_type` enum('Root Project','SEF Project','Special Project') NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `priority` enum('High','Medium','Low') DEFAULT 'Medium',
  `status` enum('Not Started','In Progress','On Hold','Completed','Cancelled') DEFAULT 'Not Started',
  `funding_source` varchar(150) DEFAULT NULL,
  `resolution_no` varchar(50) DEFAULT NULL,
  `budget_allocated` decimal(15,2) DEFAULT 0.00,
  `actual_cost` decimal(15,2) DEFAULT 0.00,
  `start_date` date DEFAULT NULL,
  `target_end_date` date DEFAULT NULL,
  `actual_end_date` date DEFAULT NULL,
  `fiscal_year` year(4) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `idx_projects_type` (`project_type`),
  KEY `idx_projects_status` (`status`),
  KEY `idx_projects_fiscal_year` (`fiscal_year`),
  KEY `idx_projects_is_deleted` (`is_deleted`),
  KEY `idx_projects_is_archived` (`is_archived`),
  CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `projects` (`id`, `title`, `project_type`, `category_id`, `description`, `priority`, `status`, `funding_source`, `resolution_no`, `budget_allocated`, `actual_cost`, `start_date`, `target_end_date`, `actual_end_date`, `fiscal_year`, `location`, `contact_person`, `remarks`, `is_archived`, `is_deleted`, `created_at`, `updated_at`) VALUES ('1', 'Road Repair Phase 2', 'Root Project', '1', 'Repair of main barangay road', 'High', 'In Progress', 'IRA', 'RES-2026-015', '500000.00', '0.00', '2026-01-10', '2026-03-30', NULL, '2026', 'Purok 3', 'Juan Dela Cruz', '', '0', '0', '2026-02-05 13:38:59', '2026-02-05 13:38:59');

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('1', 'barangay_name', '', '2026-02-05 13:29:34');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('2', 'municipality', '', '2026-02-05 13:29:34');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('3', 'province', '', '2026-02-05 13:29:34');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('4', 'prepared_by', '', '2026-02-05 13:29:34');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('5', 'designation', '', '2026-02-05 13:29:34');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('6', 'fiscal_year', '2026', '2026-02-05 13:29:34');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('7', 'currency_symbol', 'PHP', '2026-02-05 13:29:34');

SET FOREIGN_KEY_CHECKS = 1;
