-- Add task_identifier column to unified tasks table
ALTER TABLE `tasks`
ADD COLUMN `task_identifier` VARCHAR(20) NULL UNIQUE COMMENT 'Auto-generated task identifier based on title and location'
AFTER `title`;

-- Add index for faster searching
ALTER TABLE `tasks`
ADD INDEX `idx_tasks_identifier` (`task_identifier`);

-- Add sites table location name if not exists (for location-based identifier generation)
-- This ensures we have a clean location name for identifier generation