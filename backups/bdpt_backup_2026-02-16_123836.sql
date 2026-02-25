-- BDPT Database Backup
-- Generated: 2026-02-16 12:38:36
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `categories` (`id`, `name`, `is_default`, `created_at`) VALUES ('1', 'Infrastructure', '1', '2026-02-16 16:39:30');
INSERT INTO `categories` (`id`, `name`, `is_default`, `created_at`) VALUES ('2', 'Health & Sanitation', '1', '2026-02-16 16:39:30');
INSERT INTO `categories` (`id`, `name`, `is_default`, `created_at`) VALUES ('3', 'Education', '1', '2026-02-16 16:39:30');
INSERT INTO `categories` (`id`, `name`, `is_default`, `created_at`) VALUES ('4', 'Livelihood & Agriculture', '1', '2026-02-16 16:39:30');
INSERT INTO `categories` (`id`, `name`, `is_default`, `created_at`) VALUES ('5', 'Peace & Order', '1', '2026-02-16 16:39:30');
INSERT INTO `categories` (`id`, `name`, `is_default`, `created_at`) VALUES ('6', 'Disaster Preparedness', '1', '2026-02-16 16:39:30');
INSERT INTO `categories` (`id`, `name`, `is_default`, `created_at`) VALUES ('7', 'Environmental Protection', '1', '2026-02-16 16:39:30');
INSERT INTO `categories` (`id`, `name`, `is_default`, `created_at`) VALUES ('8', 'Social Welfare', '1', '2026-02-16 16:39:30');
INSERT INTO `categories` (`id`, `name`, `is_default`, `created_at`) VALUES ('9', 'Sports & Youth Development', '1', '2026-02-16 16:39:30');
INSERT INTO `categories` (`id`, `name`, `is_default`, `created_at`) VALUES ('10', 'Other', '1', '2026-02-16 16:39:30');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `project_type` enum('Root Project','SEF Project','Special Project') NOT NULL,
  `visibility` enum('Private','Public') NOT NULL DEFAULT 'Public',
  `department` varchar(50) DEFAULT NULL,
  `project_subtype` varchar(100) DEFAULT NULL,
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
  KEY `idx_projects_visibility` (`visibility`),
  KEY `idx_projects_department` (`department`),
  KEY `idx_projects_status` (`status`),
  KEY `idx_projects_fiscal_year` (`fiscal_year`),
  KEY `idx_projects_is_deleted` (`is_deleted`),
  KEY `idx_projects_is_archived` (`is_archived`),
  CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `projects` (`id`, `title`, `project_type`, `visibility`, `department`, `project_subtype`, `category_id`, `description`, `priority`, `status`, `funding_source`, `resolution_no`, `budget_allocated`, `actual_cost`, `start_date`, `target_end_date`, `actual_end_date`, `fiscal_year`, `location`, `contact_person`, `remarks`, `is_archived`, `is_deleted`, `created_at`, `updated_at`) VALUES ('1', 'Inspection for requested materials (Purok Quezon, Brgy.Rotonda)', 'Special Project', 'Public', 'Mayors', 'Special Projects', '6', '', 'Medium', 'Completed', '', '', '0.00', '0.00', NULL, NULL, NULL, '2026', '', '', '', '0', '0', '2026-02-16 16:43:32', '2026-02-16 17:32:25');
INSERT INTO `projects` (`id`, `title`, `project_type`, `visibility`, `department`, `project_subtype`, `category_id`, `description`, `priority`, `status`, `funding_source`, `resolution_no`, `budget_allocated`, `actual_cost`, `start_date`, `target_end_date`, `actual_end_date`, `fiscal_year`, `location`, `contact_person`, `remarks`, `is_archived`, `is_deleted`, `created_at`, `updated_at`) VALUES ('2', 'Inspection of Constituents(Brgy. Avanceɲa) that requested concrete pipes 24\"', 'Special Project', 'Public', 'Mayors', 'Special Projects', '6', '', 'Medium', 'Completed', '', '', '0.00', '0.00', NULL, NULL, NULL, '2026', '', '', '', '0', '0', '2026-02-16 17:16:25', '2026-02-16 17:29:22');
INSERT INTO `projects` (`id`, `title`, `project_type`, `visibility`, `department`, `project_subtype`, `category_id`, `description`, `priority`, `status`, `funding_source`, `resolution_no`, `budget_allocated`, `actual_cost`, `start_date`, `target_end_date`, `actual_end_date`, `fiscal_year`, `location`, `contact_person`, `remarks`, `is_archived`, `is_deleted`, `created_at`, `updated_at`) VALUES ('3', 'Inspection of Damaged Concrete Pipe Culvert (Ø 24”) in Brgy Avancena', 'Special Project', 'Public', 'Mayors', 'Special Projects', NULL, '', 'Medium', 'Completed', '', '', '0.00', '0.00', NULL, NULL, NULL, '2026', '', '', '', '0', '0', '2026-02-16 17:22:43', '2026-02-16 17:25:22');
INSERT INTO `projects` (`id`, `title`, `project_type`, `visibility`, `department`, `project_subtype`, `category_id`, `description`, `priority`, `status`, `funding_source`, `resolution_no`, `budget_allocated`, `actual_cost`, `start_date`, `target_end_date`, `actual_end_date`, `fiscal_year`, `location`, `contact_person`, `remarks`, `is_archived`, `is_deleted`, `created_at`, `updated_at`) VALUES ('4', 'Inspection of Repair of toilet at the old perimeter bldg along alunan ave.', 'Special Project', 'Public', 'Mayors', 'Special Projects', '2', '', 'Medium', 'Completed', '', '', '0.00', '0.00', NULL, NULL, NULL, '2026', '', '', '', '0', '0', '2026-02-16 17:35:03', '2026-02-16 17:59:04');
INSERT INTO `projects` (`id`, `title`, `project_type`, `visibility`, `department`, `project_subtype`, `category_id`, `description`, `priority`, `status`, `funding_source`, `resolution_no`, `budget_allocated`, `actual_cost`, `start_date`, `target_end_date`, `actual_end_date`, `fiscal_year`, `location`, `contact_person`, `remarks`, `is_archived`, `is_deleted`, `created_at`, `updated_at`) VALUES ('5', 'Enclosure of Window at City Health Office', 'Special Project', 'Public', 'Mayors', 'Special Projects', '2', '', 'Medium', 'Completed', '', '', '0.00', '0.00', NULL, NULL, NULL, '2026', '', '', '', '0', '0', '2026-02-16 18:05:08', '2026-02-16 18:05:39');
INSERT INTO `projects` (`id`, `title`, `project_type`, `visibility`, `department`, `project_subtype`, `category_id`, `description`, `priority`, `status`, `funding_source`, `resolution_no`, `budget_allocated`, `actual_cost`, `start_date`, `target_end_date`, `actual_end_date`, `fiscal_year`, `location`, `contact_person`, `remarks`, `is_archived`, `is_deleted`, `created_at`, `updated_at`) VALUES ('6', 'Inspection of the Request of PVC pipes and Manual Pump for source of water @ Brgy. Namnama', 'Special Project', 'Public', 'Mayors', 'Special Projects', '10', '', 'Medium', 'Completed', '', '', '0.00', '0.00', NULL, NULL, NULL, '2026', '', '', '', '0', '0', '2026-02-16 18:26:19', '2026-02-16 18:40:49');
INSERT INTO `projects` (`id`, `title`, `project_type`, `visibility`, `department`, `project_subtype`, `category_id`, `description`, `priority`, `status`, `funding_source`, `resolution_no`, `budget_allocated`, `actual_cost`, `start_date`, `target_end_date`, `actual_end_date`, `fiscal_year`, `location`, `contact_person`, `remarks`, `is_archived`, `is_deleted`, `created_at`, `updated_at`) VALUES ('7', 'Inspection of TESDA Training CEnter @Brgy. Carpenter Hills', 'Special Project', 'Public', 'Mayors', 'Special Projects', '10', 'Follow up Notice on unaddressed Structural Defects of TESDA Training Center', 'Medium', 'Completed', '', '', '0.00', '0.00', NULL, NULL, NULL, '2026', '', '', '', '0', '0', '2026-02-16 18:57:06', '2026-02-16 19:02:03');
INSERT INTO `projects` (`id`, `title`, `project_type`, `visibility`, `department`, `project_subtype`, `category_id`, `description`, `priority`, `status`, `funding_source`, `resolution_no`, `budget_allocated`, `actual_cost`, `start_date`, `target_end_date`, `actual_end_date`, `fiscal_year`, `location`, `contact_person`, `remarks`, `is_archived`, `is_deleted`, `created_at`, `updated_at`) VALUES ('8', 'INSPECTION of Morales National High School @ Brgy. Paraiso', 'Special Project', 'Public', 'Mayors', 'Special Projects', NULL, 'Request for Installation and Illumination of Gymnasium', 'Medium', 'Completed', '', '', '0.00', '0.00', NULL, NULL, NULL, '2026', '', '', '', '0', '0', '2026-02-16 19:04:38', '2026-02-16 19:07:24');
INSERT INTO `projects` (`id`, `title`, `project_type`, `visibility`, `department`, `project_subtype`, `category_id`, `description`, `priority`, `status`, `funding_source`, `resolution_no`, `budget_allocated`, `actual_cost`, `start_date`, `target_end_date`, `actual_end_date`, `fiscal_year`, `location`, `contact_person`, `remarks`, `is_archived`, `is_deleted`, `created_at`, `updated_at`) VALUES ('9', 'Residential Assistance @ Prk. Lower New Leyte Zulueta, Brgy. Topland', 'Special Project', 'Public', 'Mayors', 'Special Projects', '4', '', 'Medium', 'Completed', '', '', '0.00', '0.00', NULL, NULL, NULL, '2026', '', '', '', '0', '0', '2026-02-16 19:25:41', '2026-02-16 19:28:50');
INSERT INTO `projects` (`id`, `title`, `project_type`, `visibility`, `department`, `project_subtype`, `category_id`, `description`, `priority`, `status`, `funding_source`, `resolution_no`, `budget_allocated`, `actual_cost`, `start_date`, `target_end_date`, `actual_end_date`, `fiscal_year`, `location`, `contact_person`, `remarks`, `is_archived`, `is_deleted`, `created_at`, `updated_at`) VALUES ('10', 'Inspection of Requested Pipe Culvert @ Sitio Angeles, Barangay Saravia', 'Special Project', 'Public', 'Mayors', 'Special Projects', '10', '', 'Medium', 'Completed', '', '', '0.00', '0.00', NULL, NULL, NULL, '2026', '', '', '', '0', '0', '2026-02-16 19:30:18', '2026-02-16 19:36:11');

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('1', 'barangay_name', '', '2026-02-16 16:39:30');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('2', 'municipality', '', '2026-02-16 16:39:30');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('3', 'province', '', '2026-02-16 16:39:30');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('4', 'prepared_by', '', '2026-02-16 16:39:30');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('5', 'designation', '', '2026-02-16 16:39:30');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('6', 'fiscal_year', '2026', '2026-02-16 16:39:30');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('7', 'currency_symbol', 'PHP', '2026-02-16 16:39:30');

DROP TABLE IF EXISTS `workflow_attachments`;
CREATE TABLE `workflow_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stage_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_original_name` varchar(255) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_workflow_att_stage` (`stage_id`),
  KEY `idx_workflow_att_project` (`project_id`),
  CONSTRAINT `workflow_attachments_ibfk_1` FOREIGN KEY (`stage_id`) REFERENCES `workflow_stages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `workflow_attachments_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('1', '1', '1', '1771231423_2021f0af-aa87-4347-9053-5624c3ce8278.jpg', '2021f0af-aa87-4347-9053-5624c3ce8278.jpg', 'image/jpeg', '90687', '', '2026-02-16 16:43:43');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('2', '2', '1', '1771231898_615350334_914415897819587_5694391200275823948_n.jpg', '615350334_914415897819587_5694391200275823948_n.jpg', 'image/jpeg', '362169', '', '2026-02-16 16:51:38');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('3', '2', '1', '1771231929_616723838_938488725506554_7301962454576652406_n.jpg', '616723838_938488725506554_7301962454576652406_n.jpg', 'image/jpeg', '219655', '', '2026-02-16 16:52:09');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('4', '2', '1', '1771231937_618181303_4171374699741917_7107723874487707428_n.jpg', '618181303_4171374699741917_7107723874487707428_n.jpg', 'image/jpeg', '257195', '', '2026-02-16 16:52:17');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('5', '3', '1', '1771232938_Purok_Quezon__Brgy._Rotonda.pdf', 'Purok Quezon, Brgy. Rotonda.pdf', 'application/pdf', '553919', '', '2026-02-16 17:08:58');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('6', '13', '3', '1771233809_INSPECTION_REPORT__1_.pdf', 'INSPECTION REPORT (1).pdf', 'application/pdf', '3840911', '', '2026-02-16 17:23:29');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('7', '7', '2', '1771234064_614629026_686645091083698_487400192172455061_n.jpg', '614629026_686645091083698_487400192172455061_n.jpg', 'image/jpeg', '462532', '', '2026-02-16 17:27:44');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('8', '7', '2', '1771234071_615318775_1430482895344427_8712343224684325265_n.jpg', '615318775_1430482895344427_8712343224684325265_n.jpg', 'image/jpeg', '319297', '', '2026-02-16 17:27:51');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('9', '7', '2', '1771234079_614131277_1997271604467741_6186646465338411679_n.jpg', '614131277_1997271604467741_6186646465338411679_n.jpg', 'image/jpeg', '307387', '', '2026-02-16 17:27:59');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('10', '7', '2', '1771234086_612724902_1704828097146207_6392630572265404733_n.jpg', '612724902_1704828097146207_6392630572265404733_n.jpg', 'image/jpeg', '256142', '', '2026-02-16 17:28:06');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('11', '7', '2', '1771234092_614241817_4215759225357421_1364791032579612444_n.jpg', '614241817_4215759225357421_1364791032579612444_n.jpg', 'image/jpeg', '282556', '', '2026-02-16 17:28:12');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('12', '8', '2', '1771234262_NAME_OF_REQUESTER__1_.pdf', 'NAME OF REQUESTER (1).pdf', 'application/pdf', '1554707', '', '2026-02-16 17:31:02');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('14', '16', '4', '1771234622_d25e34b6-910e-4078-addb-8b68059c275f.jpg', 'd25e34b6-910e-4078-addb-8b68059c275f.jpg', 'image/jpeg', '222057', '', '2026-02-16 17:37:02');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('15', '16', '4', '1771234702_2657ea1f-3b58-4917-933c-1912e5336cc6.jpg', '2657ea1f-3b58-4917-933c-1912e5336cc6.jpg', 'image/jpeg', '44077', '', '2026-02-16 17:38:22');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('16', '17', '4', '1771234842_615793269_1631555811171958_4704894875251720105_n.jpg', '615793269_1631555811171958_4704894875251720105_n.jpg', 'image/jpeg', '261950', '', '2026-02-16 17:40:42');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('17', '17', '4', '1771234849_619721063_1432266958396218_92997337288952022_n.jpg', '619721063_1432266958396218_92997337288952022_n.jpg', 'image/jpeg', '345649', '', '2026-02-16 17:40:49');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('18', '17', '4', '1771234856_619560166_1633687320959519_6918226625251140188_n.jpg', '619560166_1633687320959519_6918226625251140188_n.jpg', 'image/jpeg', '221332', '', '2026-02-16 17:40:56');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('19', '17', '4', '1771234862_616139859_1373292604083622_765698278302003131_n.jpg', '616139859_1373292604083622_765698278302003131_n.jpg', 'image/jpeg', '269138', '', '2026-02-16 17:41:02');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('20', '17', '4', '1771234868_615793269_1631555811171958_4704894875251720105_n__1_.jpg', '615793269_1631555811171958_4704894875251720105_n (1).jpg', 'image/jpeg', '261950', '', '2026-02-16 17:41:08');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('21', '20', '4', '1771235594_Public_Market__United_Radio_SV.pdf', 'Public Market, United Radio SV.pdf', 'application/pdf', '3435164', '', '2026-02-16 17:53:14');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('22', '20', '4', '1771235913_POW_SEPTIC_TANK.xlsx', 'POW SEPTIC TANK.xlsx', 'application/vnd.openxmlformats-officedocument.spre', '421935', '', '2026-02-16 17:58:33');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('25', '21', '5', '1771236764_c794a503-0707-484a-9f8c-18891731dc1f.jpg', 'c794a503-0707-484a-9f8c-18891731dc1f.jpg', 'image/jpeg', '261086', '', '2026-02-16 18:12:44');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('28', '23', '5', '1771236970_CHO_INSPECTION_REPORT.pdf', 'CHO INSPECTION REPORT.pdf', 'application/pdf', '507419', '', '2026-02-16 18:16:10');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('29', '23', '5', '1771236992_CHO.pdf', 'CHO.pdf', 'application/pdf', '454011', '', '2026-02-16 18:16:32');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('30', '28', '6', '1771237672_INSPECTION_REPORT_2__2_.pdf', 'INSPECTION REPORT 2 (2).pdf', 'application/pdf', '1903703', '', '2026-02-16 18:27:52');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('31', '18', '4', '1771238782_INSPECTION_REPORT_public_market.pdf', 'INSPECTION REPORT public market.pdf', 'application/pdf', '778548', '', '2026-02-16 18:46:22');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('32', '18', '4', '1771238790_INSPECTION_REPORT_public_market.docx', 'INSPECTION REPORT public market.docx', 'application/vnd.openxmlformats-officedocument.word', '887043', '', '2026-02-16 18:46:30');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('33', '31', '7', '1771239480_54945f3b-45cc-48b3-9e26-2b4fc9545679.jpg', '54945f3b-45cc-48b3-9e26-2b4fc9545679.jpg', 'image/jpeg', '201502', '', '2026-02-16 18:58:00');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('34', '32', '7', '1771239574_620042485_1887939355232669_8005766566507641139_n.jpg', '620042485_1887939355232669_8005766566507641139_n.jpg', 'image/jpeg', '177961', '', '2026-02-16 18:59:34');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('35', '32', '7', '1771239579_620136291_866368686190687_1937461739809373046_n__1_.jpg', '620136291_866368686190687_1937461739809373046_n (1).jpg', 'image/jpeg', '185808', '', '2026-02-16 18:59:39');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('36', '32', '7', '1771239587_620499492_4314700848856817_3583464085379697810_n__1_.jpg', '620499492_4314700848856817_3583464085379697810_n (1).jpg', 'image/jpeg', '206777', '', '2026-02-16 18:59:47');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('37', '32', '7', '1771239594_621291902_2650900225277931_8803329018772401974_n__1_.jpg', '621291902_2650900225277931_8803329018772401974_n (1).jpg', 'image/jpeg', '227254', '', '2026-02-16 18:59:54');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('38', '32', '7', '1771239600_619834654_1468426154851323_6397650638758052431_n__1_.jpg', '619834654_1468426154851323_6397650638758052431_n (1).jpg', 'image/jpeg', '226784', '', '2026-02-16 19:00:00');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('39', '32', '7', '1771239607_622491716_886105200495645_3590559301616065167_n__1_.jpg', '622491716_886105200495645_3590559301616065167_n (1).jpg', 'image/jpeg', '217888', '', '2026-02-16 19:00:07');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('40', '33', '7', '1771239672_TESDA_INSPECTION_REPORT_xxxx.docx', 'TESDA INSPECTION REPORT xxxx.docx', 'application/vnd.openxmlformats-officedocument.word', '604466', '', '2026-02-16 19:01:12');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('41', '33', '7', '1771239690_Tesda_Inspection_report_1.pdf', 'Tesda Inspection report 1.pdf', 'application/pdf', '1428139', '', '2026-02-16 19:01:30');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('42', '36', '8', '1771239992_20ccff29-0eef-4aed-bc8d-468325480e9d__1_.jpg', '20ccff29-0eef-4aed-bc8d-468325480e9d (1).jpg', 'image/jpeg', '215916', '', '2026-02-16 19:06:32');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('43', '37', '8', '1771240040_619934695_25913455548344209_5466597003106822678_n.jpg', '619934695_25913455548344209_5466597003106822678_n.jpg', 'image/jpeg', '205651', '', '2026-02-16 19:07:20');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('44', '37', '8', '1771240053_624283539_873660698971035_3023625752415233212_n__1_.jpg', '624283539_873660698971035_3023625752415233212_n (1).jpg', 'image/jpeg', '243633', '', '2026-02-16 19:07:33');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('45', '37', '8', '1771240060_622864221_1199872069006832_6197215164860677266_n__1_.jpg', '622864221_1199872069006832_6197215164860677266_n (1).jpg', 'image/jpeg', '153475', '', '2026-02-16 19:07:40');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('46', '37', '8', '1771240068_619597915_703199586076760_7722679151564679607_n.jpg', '619597915_703199586076760_7722679151564679607_n.jpg', 'image/jpeg', '165566', '', '2026-02-16 19:07:48');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('47', '37', '8', '1771240074_622055094_1938409970045217_2059166161834382830_n.jpg', '622055094_1938409970045217_2059166161834382830_n.jpg', 'image/jpeg', '309940', '', '2026-02-16 19:07:54');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('48', '37', '8', '1771240080_620116128_1376577897028777_8882663721154918435_n__1_.jpg', '620116128_1376577897028777_8882663721154918435_n (1).jpg', 'image/jpeg', '221634', '', '2026-02-16 19:08:00');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('49', '37', '8', '1771240091_620116128_1376577897028777_8882663721154918435_n__1_.jpg', '620116128_1376577897028777_8882663721154918435_n (1).jpg', 'image/jpeg', '221634', '', '2026-02-16 19:08:11');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('50', '37', '8', '1771240098_620635834_25762122460113465_316836299127061825_n.jpg', '620635834_25762122460113465_316836299127061825_n.jpg', 'image/jpeg', '141613', '', '2026-02-16 19:08:18');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('51', '38', '8', '1771240131_Morales_National_High_School_INSPECTION_REPORT1.docx', 'Morales National High School INSPECTION REPORT1.docx', 'application/vnd.openxmlformats-officedocument.word', '1385511', '', '2026-02-16 19:08:51');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('52', '38', '8', '1771240139_Morales_NHS_GYM.pdf', 'Morales NHS GYM.pdf', 'application/pdf', '1385511', '', '2026-02-16 19:08:59');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('53', '41', '9', '1771241152_85de3897-68a3-4c65-aeb5-e03e93a268e6.jpg', '85de3897-68a3-4c65-aeb5-e03e93a268e6.jpg', 'image/jpeg', '161211', '', '2026-02-16 19:25:52');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('54', '42', '9', '1771241223_625615758_2431260333960958_8837724689078066521_n__1_.jpg', '625615758_2431260333960958_8837724689078066521_n (1).jpg', 'image/jpeg', '462717', '', '2026-02-16 19:27:03');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('55', '42', '9', '1771241232_623059638_908437118236003_7316257492422347747_n.jpg', '623059638_908437118236003_7316257492422347747_n.jpg', 'image/jpeg', '419199', '', '2026-02-16 19:27:12');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('56', '42', '9', '1771241238_623947100_898581929545897_3430373695111406287_n__1_.jpg', '623947100_898581929545897_3430373695111406287_n (1).jpg', 'image/jpeg', '391863', '', '2026-02-16 19:27:18');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('57', '42', '9', '1771241244_623322351_1252605820055024_3235228775946749686_n__1_.jpg', '623322351_1252605820055024_3235228775946749686_n (1).jpg', 'image/jpeg', '384473', '', '2026-02-16 19:27:24');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('58', '43', '9', '1771241259_Residential_Assistance__1_.pdf', 'Residential Assistance (1).pdf', 'application/pdf', '632002', '', '2026-02-16 19:27:39');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('59', '46', '10', '1771241432_03c39f91-99e7-4453-bee0-3e0b2c4868df.jpg', '03c39f91-99e7-4453-bee0-3e0b2c4868df.jpg', 'image/jpeg', '163325', '', '2026-02-16 19:30:32');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('60', '47', '10', '1771241642_624081077_1856615365021582_7264302849927433856_n__1_.jpg', '624081077_1856615365021582_7264302849927433856_n (1).jpg', 'image/jpeg', '300589', '', '2026-02-16 19:34:02');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('61', '47', '10', '1771241649_623300973_825546663877738_4159915831039572515_n__1_.jpg', '623300973_825546663877738_4159915831039572515_n (1).jpg', 'image/jpeg', '421812', '', '2026-02-16 19:34:09');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('62', '47', '10', '1771241658_622616583_1968027287465704_6451956583405510545_n__1_.jpg', '622616583_1968027287465704_6451956583405510545_n (1).jpg', 'image/jpeg', '334415', '', '2026-02-16 19:34:18');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('63', '47', '10', '1771241664_624517470_1244522364410388_3901863202070436992_n__1_.jpg', '624517470_1244522364410388_3901863202070436992_n (1).jpg', 'image/jpeg', '349296', '', '2026-02-16 19:34:24');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('64', '48', '10', '1771241717_saravia.pdf', 'saravia.pdf', 'application/pdf', '781343', '', '2026-02-16 19:35:17');
INSERT INTO `workflow_attachments` (`id`, `stage_id`, `project_id`, `file_name`, `file_original_name`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES ('65', '48', '10', '1771241727_saravia.docx', 'saravia.docx', 'application/vnd.openxmlformats-officedocument.word', '996590', '', '2026-02-16 19:35:27');

DROP TABLE IF EXISTS `workflow_stages`;
CREATE TABLE `workflow_stages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `stage_type` enum('Incoming Communication','Action Taken','Outgoing Communication','Status','Preparation of Plans/Estimates') NOT NULL,
  `stage_order` tinyint(4) NOT NULL,
  `status` enum('Pending','In Progress','Completed') DEFAULT 'Pending',
  `notes` text DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_stage` (`project_id`,`stage_type`),
  KEY `idx_workflow_project` (`project_id`),
  CONSTRAINT `workflow_stages_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('1', '1', 'Incoming Communication', '1', 'Completed', '', '2026-02-16 10:14:03', '2026-02-16 17:14:03', '2026-02-16 16:43:32');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('2', '1', 'Action Taken', '2', 'Completed', 'Upon inspection, the originally requested pipe culvert was found to be oversized. An 18-inch \r\npipe culvert is sufficient and is recommended, as agreed by the area resident representative.', '2026-02-16 10:18:02', '2026-02-16 17:18:02', '2026-02-16 16:43:32');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('3', '1', 'Outgoing Communication', '3', 'Completed', '', '2026-02-16 10:18:16', '2026-02-16 17:18:16', '2026-02-16 16:43:32');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('4', '1', 'Status', '4', 'Completed', '', '2026-02-16 10:18:25', '2026-02-16 17:18:25', '2026-02-16 16:43:32');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('5', '1', 'Preparation of Plans/Estimates', '5', 'Pending', NULL, NULL, '2026-02-16 16:43:32', '2026-02-16 16:43:32');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('6', '2', 'Incoming Communication', '1', 'Completed', '', '2026-02-16 10:26:27', '2026-02-16 17:26:27', '2026-02-16 17:16:25');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('7', '2', 'Action Taken', '2', 'Completed', 'Site Inspection', '2026-02-16 10:28:15', '2026-02-16 17:28:15', '2026-02-16 17:16:25');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('8', '2', 'Outgoing Communication', '3', 'Completed', '', '2026-02-16 10:26:32', '2026-02-16 17:26:32', '2026-02-16 17:16:25');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('9', '2', 'Status', '4', 'Pending', NULL, NULL, '2026-02-16 17:16:25', '2026-02-16 17:16:25');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('10', '2', 'Preparation of Plans/Estimates', '5', 'Pending', NULL, NULL, '2026-02-16 17:16:25', '2026-02-16 17:16:25');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('11', '3', 'Incoming Communication', '1', 'Completed', '', '2026-02-16 10:24:04', '2026-02-16 17:24:04', '2026-02-16 17:22:43');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('12', '3', 'Action Taken', '2', 'Completed', '', '2026-02-16 10:24:00', '2026-02-16 17:24:00', '2026-02-16 17:22:43');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('13', '3', 'Outgoing Communication', '3', 'Completed', '', '2026-02-16 10:24:14', '2026-02-16 17:24:14', '2026-02-16 17:22:43');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('14', '3', 'Status', '4', 'Pending', NULL, NULL, '2026-02-16 17:22:43', '2026-02-16 17:22:43');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('15', '3', 'Preparation of Plans/Estimates', '5', 'Pending', NULL, NULL, '2026-02-16 17:22:43', '2026-02-16 17:22:43');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('16', '4', 'Incoming Communication', '1', 'Completed', '', '2026-02-16 10:35:17', '2026-02-16 17:35:17', '2026-02-16 17:35:03');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('17', '4', 'Action Taken', '2', 'Completed', 'Repair of toilet at the old perimeter bldg along alunan ave.\r\n@Public Market of City of Koronadal\r\n\r\nFindings: Cr not operational due to leak in septic tanks and pipelines that cause bad odor.\r\n\r\nExisting septic tank: (Approx. 2.7m x 5.2 meters, depth unknown) according to report always full even after desludging.\r\n\r\nPipelines: \r\nVertical pipe drop use sharp elbows without clean-outs and inadequate venting system contributes to odor accumulation.\r\n\r\nRecommendation:\r\n1. Replace all damaged and leaking sanitary pipes from comfort rooms to septic tank.\r\n2. Install proper pipe fittings, clean outs and air vent.\r\n3. Construct new reinforced septic tank.', '2026-02-16 10:39:41', '2026-02-16 17:39:41', '2026-02-16 17:35:03');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('18', '4', 'Outgoing Communication', '3', 'Completed', '', '2026-02-16 10:35:26', '2026-02-16 17:35:26', '2026-02-16 17:35:03');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('19', '4', 'Status', '4', 'Completed', '', '2026-02-16 10:35:30', '2026-02-16 17:35:30', '2026-02-16 17:35:03');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('20', '4', 'Preparation of Plans/Estimates', '5', 'Completed', '', '2026-02-16 10:58:58', '2026-02-16 17:58:58', '2026-02-16 17:35:03');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('21', '5', 'Incoming Communication', '1', 'Completed', '', '2026-02-16 11:05:14', '2026-02-16 18:05:14', '2026-02-16 18:05:08');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('22', '5', 'Action Taken', '2', 'Completed', 'Site Inspection', '2026-02-16 11:13:12', '2026-02-16 18:13:12', '2026-02-16 18:05:08');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('23', '5', 'Outgoing Communication', '3', 'Completed', '', '2026-02-16 11:05:21', '2026-02-16 18:05:21', '2026-02-16 18:05:08');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('24', '5', 'Status', '4', 'Completed', '', '2026-02-16 11:16:53', '2026-02-16 18:16:53', '2026-02-16 18:05:08');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('25', '5', 'Preparation of Plans/Estimates', '5', 'Pending', NULL, NULL, '2026-02-16 18:05:08', '2026-02-16 18:05:08');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('26', '6', 'Incoming Communication', '1', 'Completed', '', '2026-02-16 11:26:57', '2026-02-16 18:26:57', '2026-02-16 18:26:19');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('27', '6', 'Action Taken', '2', 'Completed', 'Inspection', '2026-02-16 11:26:54', '2026-02-16 18:26:54', '2026-02-16 18:26:19');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('28', '6', 'Outgoing Communication', '3', 'Completed', '', '2026-02-16 11:27:01', '2026-02-16 18:27:01', '2026-02-16 18:26:19');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('29', '6', 'Status', '4', 'Completed', '', '2026-02-16 11:27:06', '2026-02-16 18:27:06', '2026-02-16 18:26:19');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('30', '6', 'Preparation of Plans/Estimates', '5', 'Completed', '', '2026-02-16 11:27:09', '2026-02-16 18:27:09', '2026-02-16 18:26:19');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('31', '7', 'Incoming Communication', '1', 'Completed', '', '2026-02-16 11:57:17', '2026-02-16 18:57:17', '2026-02-16 18:57:06');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('32', '7', 'Action Taken', '2', 'Completed', '', '2026-02-16 11:57:25', '2026-02-16 18:57:25', '2026-02-16 18:57:06');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('33', '7', 'Outgoing Communication', '3', 'Completed', '', '2026-02-16 11:57:29', '2026-02-16 18:57:29', '2026-02-16 18:57:06');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('34', '7', 'Status', '4', 'Pending', '', NULL, '2026-02-16 18:57:45', '2026-02-16 18:57:06');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('35', '7', 'Preparation of Plans/Estimates', '5', 'Pending', NULL, NULL, '2026-02-16 18:57:06', '2026-02-16 18:57:06');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('36', '8', 'Incoming Communication', '1', 'Completed', '', '2026-02-16 12:04:51', '2026-02-16 19:04:51', '2026-02-16 19:04:38');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('37', '8', 'Action Taken', '2', 'Completed', 'Request for Installation and Illumination for Governor Sergio B. Morales National High School\r\n@Brgy. Paraiso\r\n\r\nFindings:\r\n\r\n1. Existing lighting fixtures in the building are non-functional.\r\n\r\n2. Installed electrical wiring is undersized and not adequate for the required lighting load.\r\n\r\nRecommendations:\r\n\r\n1. Install four (4) units of floodlights to provide sufficient illumination for the gymnasium and stage area.\r\n\r\n2. Upgrade electrical wiring to ensure safety and compliance with proper electrical standards.\r\n\r\nAdditional Observation and request during inspection:\r\n\r\nThe stage paint is already deteriorated and requires repainting to improve appearance and usability.', '2026-02-16 12:09:28', '2026-02-16 19:09:28', '2026-02-16 19:04:38');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('38', '8', 'Outgoing Communication', '3', 'Completed', '', '2026-02-16 12:09:25', '2026-02-16 19:09:25', '2026-02-16 19:04:38');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('39', '8', 'Status', '4', 'Completed', '', '2026-02-16 12:05:03', '2026-02-16 19:05:03', '2026-02-16 19:04:38');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('40', '8', 'Preparation of Plans/Estimates', '5', 'Pending', NULL, NULL, '2026-02-16 19:04:38', '2026-02-16 19:04:38');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('41', '9', 'Incoming Communication', '1', 'Completed', '', '2026-02-16 12:25:56', '2026-02-16 19:25:56', '2026-02-16 19:25:41');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('42', '9', 'Action Taken', '2', 'Completed', 'Site Inspection', '2026-02-16 12:26:07', '2026-02-16 19:26:07', '2026-02-16 19:25:41');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('43', '9', 'Outgoing Communication', '3', 'Completed', '', '2026-02-16 12:27:31', '2026-02-16 19:27:31', '2026-02-16 19:25:41');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('44', '9', 'Status', '4', 'Pending', NULL, NULL, '2026-02-16 19:25:41', '2026-02-16 19:25:41');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('45', '9', 'Preparation of Plans/Estimates', '5', 'Pending', NULL, NULL, '2026-02-16 19:25:41', '2026-02-16 19:25:41');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('46', '10', 'Incoming Communication', '1', 'Completed', '', '2026-02-16 12:30:26', '2026-02-16 19:30:26', '2026-02-16 19:30:18');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('47', '10', 'Action Taken', '2', 'Completed', '7 pcs no.18 (SIte inspection)', '2026-02-16 12:34:11', '2026-02-16 19:34:11', '2026-02-16 19:30:18');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('48', '10', 'Outgoing Communication', '3', 'Completed', '', '2026-02-16 12:34:59', '2026-02-16 19:34:59', '2026-02-16 19:30:18');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('49', '10', 'Status', '4', 'Pending', NULL, NULL, '2026-02-16 19:30:18', '2026-02-16 19:30:18');
INSERT INTO `workflow_stages` (`id`, `project_id`, `stage_type`, `stage_order`, `status`, `notes`, `completed_at`, `updated_at`, `created_at`) VALUES ('50', '10', 'Preparation of Plans/Estimates', '5', 'Pending', NULL, NULL, '2026-02-16 19:30:18', '2026-02-16 19:30:18');

SET FOREIGN_KEY_CHECKS = 1;
