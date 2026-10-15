# Unified Task Management System

## Overview
The unified task management system consolidates both self-created and admin-assigned tasks into a single table structure, resolving ID mapping issues and providing consistent features across all task types.

## Key Improvements

### 1. Single Table Architecture
- **Before**: Separate `tasks` and `task_assignments` tables causing ID conflicts
- **After**: Single `tasks` table with `task_type` field to distinguish task types
- **Benefit**: Consistent employee_id usage throughout, eliminating user/employee ID mapping issues

### 2. Enhanced Features for All Task Types
- **Images**: Both start and completion images for all tasks
- **Locations**: Start and completion coordinates for all tasks
- **Budgets**: Expense tracking available for all tasks
- **Priority**: Low/Medium/High priority for all tasks
- **Dates**: Start and due dates for all tasks

### 3. Resolved Data Integrity Issues
- Consistent use of `employee_id` throughout the system
- Proper foreign key relationships
- Eliminated dual-table complexity

## Database Schema

### Unified Tasks Table
```sql
tasks (
    id                      INT(11) PRIMARY KEY AUTO_INCREMENT,
    title                   VARCHAR(200) NOT NULL,
    description             TEXT,
    task_type               ENUM('self_created', 'admin_assigned'),

    -- Assignment Fields
    created_by_employee_id  INT(11),     -- For self-created tasks
    assigned_to_employee_id INT(11) NOT NULL, -- Employee doing the task
    assigned_by_user_id     INT(11),     -- Admin who assigned (admin tasks)

    -- Location & Media
    start_latitude          DECIMAL(10,8),
    start_longitude         DECIMAL(11,8),
    completion_latitude     DECIMAL(10,8),
    completion_longitude    DECIMAL(11,8),
    task_image              VARCHAR(255),
    completion_image        VARCHAR(255),

    -- Management
    priority                ENUM('low','medium','high') DEFAULT 'medium',
    status                  ENUM('pending','active','in_progress','completed','cancelled'),

    -- Dates
    start_date              DATE,
    due_date                DATE,
    created_at              TIMESTAMP,
    started_at              TIMESTAMP,
    completed_at            TIMESTAMP,

    -- Notes
    notes                   TEXT,        -- Assignment/task notes
    completion_notes        TEXT,        -- Completion notes

    -- Financial
    expense_required        TINYINT(1) DEFAULT 0,
    budget_amount           DECIMAL(10,2),

    -- References
    site_id                 INT(11) NOT NULL,
    attendance_id           INT(11)      -- For self-created tasks
)
```

## API Endpoints

### Employee API: `/api/employee/unified_tasks.php`

#### GET - Retrieve Tasks
```http
GET /api/employee/unified_tasks.php?task_type=all&status=active&date=2024-01-15
```

**Parameters:**
- `task_type`: `all`, `self_created`, `admin_assigned`
- `status`: `pending`, `active`, `in_progress`, `completed`, `cancelled`
- `date`: Date filter for self-created tasks (YYYY-MM-DD)
- `limit`: Number of results to return

**Response:**
```json
{
    "success": true,
    "data": {
        "tasks": [...],
        "summary": {
            "total_tasks": 10,
            "pending_tasks": 3,
            "active_tasks": 2,
            "completed_tasks": 5,
            "admin_assigned_tasks": 6,
            "self_created_tasks": 4,
            "overdue_tasks": 1,
            "due_today_tasks": 2
        },
        "can_create_self_task": true,
        "attendance_status": "checked_in"
    }
}
```

#### POST - Create Self Task
```http
POST /api/employee/unified_tasks.php
Content-Type: application/json

{
    "title": "Equipment Maintenance",
    "description": "Check and maintain equipment",
    "site_id": 1,
    "start_latitude": 22.5726,
    "start_longitude": 88.3639,
    "task_image": "data:image/jpeg;base64,..."
}
```

#### PUT - Update Task Status
```http
PUT /api/employee/unified_tasks.php
Content-Type: application/json

{
    "task_id": 1,
    "status": "completed",
    "completion_notes": "Task completed successfully",
    "latitude": 22.5726,
    "longitude": 88.3639,
    "completion_image": "data:image/jpeg;base64,..."
}
```

### Admin API: `/api/admin/unified_tasks.php`

#### GET - Retrieve All Tasks
```http
GET /api/admin/unified_tasks.php?employee_id=1&task_type=admin_assigned&status=pending
```

#### POST - Create Admin Task
```http
POST /api/admin/unified_tasks.php
Content-Type: application/json

{
    "title": "Security Inspection",
    "description": "Conduct monthly security inspection",
    "assigned_to_employee_ids": [1, 2, 3],
    "site_id": 1,
    "priority": "high",
    "start_date": "2024-01-15",
    "due_date": "2024-01-20",
    "expense_required": 1,
    "budget_amount": 5000.00,
    "notes": "Use new security checklist",
    "start_latitude": 22.5726,
    "start_longitude": 88.3639
}
```

#### PUT - Update Admin Task
```http
PUT /api/admin/unified_tasks.php
Content-Type: application/json

{
    "task_id": 1,
    "title": "Updated Security Inspection",
    "priority": "medium",
    "due_date": "2024-01-25"
}
```

#### DELETE - Delete Admin Task
```http
DELETE /api/admin/unified_tasks.php?task_id=1
```

## Task Types

### Self-Created Tasks
- **Who Creates**: Employees during work hours
- **Requirements**: Must be checked in, no active self-created task
- **Features**: All features available (images, locations, notes)
- **Status Flow**: `active` → `in_progress` → `completed`

### Admin-Assigned Tasks
- **Who Creates**: Admin users
- **Assignment**: Can assign to multiple employees
- **Features**: All features including budgets, priorities, due dates
- **Status Flow**: `pending` → `in_progress` → `completed`

## Migration Notes

### Backward Compatibility
- Old API endpoints (`tasks.php`, `admin_tasks.php`) redirect to unified system
- Existing frontend code continues to work without changes
- All previous functionality maintained

### Data Migration
- Fresh start approach - no existing data migrated
- Previous tables dropped and recreated with unified structure
- Test data inserted to verify functionality

## Benefits Achieved

1. **Eliminated ID Conflicts**: Consistent employee_id usage throughout
2. **Feature Parity**: All task types have access to all features
3. **Simplified Architecture**: Single table, single API endpoint
4. **Better Data Integrity**: Proper foreign key relationships
5. **Enhanced Functionality**: Images and locations for admin tasks
6. **Unified Reporting**: Single source for all task analytics

## Testing

Sample data has been inserted to test:
- Admin-assigned tasks with different priorities and budgets
- Self-created tasks with location tracking
- Status transitions and location updates
- Image upload functionality

The system is now ready for production use with both task types fully supported in a unified, consistent manner.