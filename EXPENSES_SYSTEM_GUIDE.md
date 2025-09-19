# Expenses System Implementation Guide

## Overview
The petty cash system has been transformed into a robust expenses management system with categories, task linking, and comprehensive admin filtering.

## Database Changes

### 1. New Tables
- **expense_categories**: Manages expense categories (Food, Fuel, Travelling, etc.)
- **expenses**: Renamed from `petty_cash_requests` with additional fields

### 2. Schema Updates
```sql
-- Categories: Food, Fuel, Travelling, Office Supplies, Equipment, Maintenance, Communication, Other
-- New fields in expenses table:
- category_id (required) - Links to expense_categories
- task_id (optional) - Links to tasks table
- receipt_number - Optional receipt reference number
```

## API Endpoints

### Employee APIs

#### 1. Submit Expense Request
**POST** `/api/employee/expenses.php`

```json
{
    "amount": 150.00,
    "reason": "Fuel expense for site visit",
    "category_id": 2,
    "task_id": 123,
    "receipt_number": "REC-001",
    "receipt_image": "base64_image_data",
    "request_date": "2025-09-20"
}
```

#### 2. Get Employee Expenses
**GET** `/api/employee/expenses.php?month=2025-09`

Returns expenses with category and task information.

#### 3. Get Expense Categories
**GET** `/api/employee/expense_categories.php`

Returns all active expense categories.

#### 4. Get User Tasks
**GET** `/api/employee/user_tasks.php`

Returns tasks available for linking to expenses.

### Admin APIs

#### 1. Get Expenses with Filters
**GET** `/api/admin/expenses.php`

**Query Parameters:**
- `employee_id` - Filter by specific employee
- `task_id` - Filter by specific task
- `category_id` - Filter by expense category
- `status` - Filter by approval status (pending/approved/rejected)
- `start_date` & `end_date` - Date range filter
- `month` - Specific month filter (YYYY-MM)
- `page` & `limit` - Pagination

**Response includes:**
- Filtered expenses list
- Pagination information
- Summary statistics (total amounts by status)
- Category breakdown
- Applied filters

#### 2. Update Expense Status
**PUT** `/api/admin/expenses.php`

```json
{
    "expense_id": 123,
    "status": "approved",
    "notes": "Approved for valid business expense"
}
```

## Features Implemented

### 1. Expense Categories
- Predefined categories: Food, Fuel, Travelling, Office Supplies, Equipment, Maintenance, Communication, Other
- Category validation during expense creation
- Category breakdown in admin reports

### 2. Task Linking
- Employees can link expenses to their tasks (both self-created and assigned)
- Validation ensures task belongs to or is assigned to the employee
- Optional field - expenses can be submitted without task linking

### 3. Admin Filtering System
- **By User**: Filter expenses by specific employee
- **By Task**: Filter expenses linked to specific tasks
- **By Category**: Filter by expense category
- **By Date**: Date range or specific month filtering
- **By Status**: Filter by approval status
- **Combined Filters**: Multiple filters can be applied simultaneously

### 4. Enhanced Reporting
- Total expense calculations on applied filters
- Category-wise expense breakdown
- Status-wise summaries (pending, approved, rejected amounts)
- Pagination for large datasets

### 5. Validation & Security
- Category existence validation
- Task ownership validation
- Receipt image upload with proper directory structure
- Admin authentication for management functions

## File Structure Changes

```
api/
├── employee/
│   ├── expenses.php (renamed from petty_cash.php)
│   ├── expense_categories.php (new)
│   └── user_tasks.php (new)
└── admin/
    └── expenses.php (new)

assets/images/uploads/
└── expenses/ (new directory)

Database files:
└── setup_expenses_system.sql (migration script)
```

## Migration Notes

1. **Backup Created**: Original `petty_cash_requests` data backed up to `petty_cash_backup` table
2. **Table Renamed**: `petty_cash_requests` → `expenses`
3. **Default Category**: Existing records assigned to "Other" category
4. **Indexes Added**: Performance indexes for filtering operations
5. **Foreign Keys**: Proper relationships established

## Usage Instructions

### For Employees:
1. Get available categories from `/api/employee/expense_categories.php`
2. Get available tasks from `/api/employee/user_tasks.php`
3. Submit expense with required category and optional task
4. View expenses with enhanced category/task information

### For Admins:
1. Access comprehensive filtering via `/api/admin/expenses.php`
2. Apply multiple filters for specific reporting needs
3. View real-time statistics and breakdowns
4. Approve/reject expenses with notes

## Next Steps

Consider implementing:
1. Expense approval workflows
2. Budget limits per category
3. Recurring expense templates
4. Mobile app integration
5. Export functionality (PDF/Excel)