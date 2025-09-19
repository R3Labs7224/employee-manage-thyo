-- Admin Roles System Setup for Employee Management System
-- This script creates the necessary tables and data for the superadmin/admin management system

-- First, let's create the admin_roles table to define different admin roles and their permissions
CREATE TABLE IF NOT EXISTS admin_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    permissions JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin roles
INSERT INTO admin_roles (role_name, display_name, description, permissions) VALUES
('superadmin', 'Super Administrator', 'Full system access with ability to manage other admins',
 '["all_permissions"]'),
('admin_full', 'Full Administrator', 'Full access to all employee management features',
 '["employees.view", "employees.create", "employees.edit", "employees.delete", "attendance.view", "attendance.manage", "petty_cash.view", "petty_cash.approve", "salary.view", "salary.manage", "tasks.view", "tasks.manage", "reports.view", "leave.view", "leave.approve", "settings.view"]'),
('admin_hr', 'HR Administrator', 'Human Resources focused administration',
 '["employees.view", "employees.create", "employees.edit", "attendance.view", "leave.view", "leave.approve", "reports.view"]'),
('admin_finance', 'Finance Administrator', 'Finance and payroll focused administration',
 '["salary.view", "salary.manage", "petty_cash.view", "petty_cash.approve", "reports.view"]'),
('admin_operations', 'Operations Administrator', 'Operations and task management focused administration',
 '["tasks.view", "tasks.manage", "attendance.view", "attendance.manage", "reports.view"]');

-- Update the users table to support admin management (if columns don't exist)
-- Add columns for admin-specific data
ALTER TABLE users
ADD COLUMN IF NOT EXISTS admin_role_id INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS created_by INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS is_admin BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS account_locked BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS failed_login_attempts INT DEFAULT 0;

-- Add foreign key constraints
ALTER TABLE users
ADD CONSTRAINT fk_users_admin_role
FOREIGN KEY (admin_role_id) REFERENCES admin_roles(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_users_created_by
FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;

-- Create indexes for better performance
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_admin_role_id ON users(admin_role_id);
CREATE INDEX idx_users_is_admin ON users(is_admin);
CREATE INDEX idx_users_status ON users(status);

-- Update existing superadmin user to use the new system
UPDATE users
SET admin_role_id = (SELECT id FROM admin_roles WHERE role_name = 'superadmin'),
    is_admin = TRUE
WHERE role = 'superadmin';

-- Create audit log table for admin actions
CREATE TABLE IF NOT EXISTS admin_audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50),
    target_id INT,
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_audit_admin_user ON admin_audit_log(admin_user_id);
CREATE INDEX idx_audit_action ON admin_audit_log(action);
CREATE INDEX idx_audit_created_at ON admin_audit_log(created_at);