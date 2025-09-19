# Superadmin System Setup Instructions

This document provides instructions for setting up the new superadmin and admin role management system.

## Database Setup

1. **Run the SQL Setup Script**
   Execute the `setup_admin_roles.sql` file in your MySQL database:
   ```sql
   mysql -u root -p employee_management_system < setup_admin_roles.sql
   ```

   This will:
   - Create the `admin_roles` table with predefined roles
   - Add new columns to the `users` table for admin management
   - Create the `admin_audit_log` table for tracking admin actions
   - Set up proper indexes and constraints

## Features Implemented

### 1. Superadmin Panel
- **Location**: `pages/admin/index.php`
- **Access**: Only users with `role = 'superadmin'` can access
- **Features**:
  - Create new admin users
  - Assign roles to admins
  - View all admin accounts
  - Activate/deactivate admin accounts
  - Delete admin accounts (except superadmin)

### 2. Role Management
- **Location**: `pages/admin/roles.php`
- **Features**:
  - Create custom admin roles
  - Define permissions for each role
  - Edit existing roles (except superadmin role)
  - Activate/deactivate roles

### 3. Role-Based Access Control
- **Sidebar Menu**: Only shows menu items based on user permissions
- **Available Permissions**:
  - `employees.view`, `employees.create`, `employees.edit`, `employees.delete`
  - `attendance.view`, `attendance.manage`
  - `petty_cash.view`, `petty_cash.approve`
  - `salary.view`, `salary.manage`
  - `tasks.view`, `tasks.manage`
  - `leave.view`, `leave.approve`
  - `reports.view`
  - `settings.view`

### 4. Predefined Admin Roles
- **Super Administrator**: Full system access (protected)
- **Full Administrator**: Access to all employee management features
- **HR Administrator**: Human Resources focused
- **Finance Administrator**: Finance and payroll focused
- **Operations Administrator**: Operations and task management

### 5. Audit Logging
- All admin actions are logged in `admin_audit_log` table
- Tracks: user, action, target, details, IP address, user agent

## Usage Instructions

### For Superadmin Users

1. **Login** with your superadmin account
2. **Navigate** to the "Admin" section in the sidebar (only visible to superadmin)
3. **Create Admin Roles** (optional):
   - Go to "Manage Roles" to create custom roles
   - Define specific permissions for each role
4. **Create Admin Users**:
   - Click "Create New Admin"
   - Choose username, password, and role
   - Set status (active/inactive)

### For Regular Admin Users

1. **Login** with your admin account
2. **Access** only the menu items you have permissions for
3. **Perform** actions based on your role permissions

## Security Features

- **Password Hashing**: All passwords are hashed using PHP's `password_hash()`
- **Session Management**: Secure session handling
- **Permission Checking**: Every action is validated against user permissions
- **Audit Trail**: All admin actions are logged
- **Account Protection**: Superadmin accounts cannot be deleted by other users

## Files Modified/Created

### New Files
- `setup_admin_roles.sql` - Database setup script
- `pages/admin/index.php` - Admin management interface
- `pages/admin/roles.php` - Role management interface
- `includes/admin_helpers.php` - Helper functions for admin operations
- `SUPERADMIN_SETUP.md` - This documentation

### Modified Files
- `includes/session.php` - Enhanced with admin role functions
- `components/sidebar.php` - Added role-based menu filtering and Admin section

## Database Schema

### admin_roles Table
```sql
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- role_name (VARCHAR(50), UNIQUE)
- display_name (VARCHAR(100))
- description (TEXT)
- permissions (JSON)
- is_active (BOOLEAN)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)
```

### users Table (Additional Columns)
```sql
- admin_role_id (INT, FOREIGN KEY to admin_roles.id)
- created_by (INT, FOREIGN KEY to users.id)
- is_admin (BOOLEAN)
- last_login (TIMESTAMP)
- account_locked (BOOLEAN)
- failed_login_attempts (INT)
```

### admin_audit_log Table
```sql
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- admin_user_id (INT, FOREIGN KEY to users.id)
- action (VARCHAR(100))
- target_type (VARCHAR(50))
- target_id (INT)
- details (JSON)
- ip_address (VARCHAR(45))
- user_agent (TEXT)
- created_at (TIMESTAMP)
```

## Helper Functions Available

### Session Functions
- `isSuperAdmin()` - Check if current user is superadmin
- `isAdmin()` - Check if current user is any type of admin
- `hasAdminPermission($permission)` - Check specific permission
- `getAdminRole()` - Get current user's admin role details
- `logAdminAction($action, $target_type, $target_id, $details)` - Log admin actions

### Helper Functions
- `requireAdminPermission($permission)` - Require permission or redirect
- `requireSuperAdmin()` - Require superadmin or redirect
- `canAccessModule($module)` - Check module access
- `canPerformAction($action, $module)` - Check action permissions

## Next Steps

1. Run the database setup script
2. Test login with existing superadmin account
3. Create additional admin roles as needed
4. Create admin users with appropriate roles
5. Test role-based access control

## Security Notes

- Always use HTTPS in production
- Regularly review admin audit logs
- Implement session timeout for security
- Consider implementing two-factor authentication for admin accounts
- Regular backup of the database including audit logs