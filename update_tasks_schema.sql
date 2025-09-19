-- Update existing tasks table to support admin-created tasks
ALTER TABLE `tasks`
ADD COLUMN `admin_created` TINYINT(1) DEFAULT 0 COMMENT 'Flag to indicate if task was created by admin',
ADD COLUMN `assigned_by` INT(11) DEFAULT NULL COMMENT 'Admin user who assigned this task',
ADD COLUMN `priority` ENUM('low','medium','high') DEFAULT 'medium' COMMENT 'Task priority',
ADD COLUMN `due_date` DATE DEFAULT NULL COMMENT 'Task due date',
MODIFY COLUMN `employee_id` INT(11) DEFAULT NULL COMMENT 'Employee ID - can be null for admin-assigned tasks',
MODIFY COLUMN `attendance_id` INT(11) DEFAULT NULL COMMENT 'Attendance ID - can be null for admin-assigned tasks',
MODIFY COLUMN `status` ENUM('active','completed','cancelled','pending') DEFAULT 'active' COMMENT 'Task status - added pending for admin tasks';

-- Add foreign key constraint for assigned_by
ALTER TABLE `tasks`
ADD CONSTRAINT `fk_tasks_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- Create task_assignments table if it doesn't exist
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
  CONSTRAINT `fk_task_assignments_task_id` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_task_assignments_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_task_assignments_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add indexes for better performance
ALTER TABLE `tasks`
ADD INDEX `idx_tasks_admin_created` (`admin_created`),
ADD INDEX `idx_tasks_assigned_by` (`assigned_by`),
ADD INDEX `idx_tasks_priority` (`priority`),
ADD INDEX `idx_tasks_due_date` (`due_date`);