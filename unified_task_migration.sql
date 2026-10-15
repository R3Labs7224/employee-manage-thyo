-- Unified Task Management System Migration
-- This script creates a fresh unified task system

-- Drop existing task tables to start fresh
DROP TABLE IF EXISTS `task_assignments`;
DROP TABLE IF EXISTS `admin_tasks`;
DROP TABLE IF EXISTS `tasks`;

-- Create unified tasks table with all features
CREATE TABLE `tasks` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,

    -- Core Task Fields
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT,
    `task_type` ENUM('self_created', 'admin_assigned') NOT NULL DEFAULT 'self_created',

    -- Assignment Fields (consistent employee_id usage)
    `created_by_employee_id` INT(11), -- Employee who created (for self-created tasks)
    `assigned_to_employee_id` INT(11) NOT NULL, -- Employee assigned to do the task
    `assigned_by_user_id` INT(11), -- Admin user who assigned (for admin tasks)

    -- Location & Media (for ALL task types)
    `start_latitude` DECIMAL(10,8),
    `start_longitude` DECIMAL(11,8),
    `completion_latitude` DECIMAL(10,8),
    `completion_longitude` DECIMAL(11,8),
    `task_image` VARCHAR(255),
    `completion_image` VARCHAR(255),

    -- Task Management
    `priority` ENUM('low','medium','high') DEFAULT 'medium',
    `status` ENUM('pending','active','in_progress','completed','cancelled') DEFAULT 'pending',

    -- Dates & Times
    `start_date` DATE,
    `due_date` DATE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `started_at` TIMESTAMP NULL,
    `completed_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Notes & Comments
    `notes` TEXT, -- Admin assignment notes or employee task notes
    `completion_notes` TEXT,

    -- Financial
    `expense_required` TINYINT(1) DEFAULT 0,
    `budget_amount` DECIMAL(10,2),

    -- References
    `site_id` INT(11) NOT NULL,
    `attendance_id` INT(11), -- For self-created tasks linked to attendance

    PRIMARY KEY (`id`),

    -- Foreign Key Constraints
    CONSTRAINT `fk_tasks_assigned_to_employee` FOREIGN KEY (`assigned_to_employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tasks_created_by_employee` FOREIGN KEY (`created_by_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tasks_assigned_by_user` FOREIGN KEY (`assigned_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tasks_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tasks_attendance` FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`id`) ON DELETE SET NULL,

    -- Indexes for Performance
    INDEX `idx_tasks_assigned_to` (`assigned_to_employee_id`),
    INDEX `idx_tasks_created_by` (`created_by_employee_id`),
    INDEX `idx_tasks_assigned_by` (`assigned_by_user_id`),
    INDEX `idx_tasks_type` (`task_type`),
    INDEX `idx_tasks_status` (`status`),
    INDEX `idx_tasks_priority` (`priority`),
    INDEX `idx_tasks_due_date` (`due_date`),
    INDEX `idx_tasks_created_at` (`created_at`),
    INDEX `idx_tasks_site` (`site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Comments for documentation
ALTER TABLE `tasks`
COMMENT = 'Unified task management table supporting both self-created and admin-assigned tasks';