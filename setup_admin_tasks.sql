-- Create admin tasks table
CREATE TABLE IF NOT EXISTS `admin_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `due_date` date DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_admin_tasks_created_by` (`created_by`),
  KEY `idx_admin_tasks_status` (`status`),
  KEY `idx_admin_tasks_due_date` (`due_date`),
  CONSTRAINT `fk_admin_tasks_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create task assignments table
CREATE TABLE IF NOT EXISTS `task_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `assigned_to` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_task_user` (`task_id`, `assigned_to`),
  KEY `idx_task_assignments_task_id` (`task_id`),
  KEY `idx_task_assignments_assigned_to` (`assigned_to`),
  KEY `idx_task_assignments_assigned_by` (`assigned_by`),
  KEY `idx_task_assignments_status` (`status`),
  CONSTRAINT `fk_task_assignments_task_id` FOREIGN KEY (`task_id`) REFERENCES `admin_tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_task_assignments_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_task_assignments_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;