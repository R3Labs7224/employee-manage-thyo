# Expenses System API Documentation

## Overview
This document covers the new expenses system APIs that replace the old petty cash system. The new system includes expense categories, task linking, and enhanced filtering capabilities.

## Base URL
```
https://yourdomain.com/employee_management_system/api
```

## Authentication
All APIs require Bearer token authentication:
```
Authorization: Bearer <employee_token>
```

---

## Employee APIs

### 1. Get Expense Categories
**Endpoint:** `GET /employee/expense_categories.php`

**Description:** Retrieve all active expense categories for dropdown/selection purposes.

**Headers:**
```
Authorization: Bearer <employee_token>
Content-Type: application/json
```

**Response:**
```json
{
    "success": true,
    "message": "Expense categories retrieved successfully",
    "data": [
        {
            "id": 1,
            "name": "Food",
            "description": "Food and meal expenses"
        },
        {
            "id": 2,
            "name": "Fuel",
            "description": "Vehicle fuel and transportation costs"
        },
        {
            "id": 3,
            "name": "Travelling",
            "description": "Travel and accommodation expenses"
        },
        {
            "id": 4,
            "name": "Office Supplies",
            "description": "Stationery and office materials"
        },
        {
            "id": 5,
            "name": "Equipment",
            "description": "Tools and equipment purchases"
        },
        {
            "id": 6,
            "name": "Maintenance",
            "description": "Repairs and maintenance costs"
        },
        {
            "id": 7,
            "name": "Communication",
            "description": "Phone, internet, and communication expenses"
        },
        {
            "id": 8,
            "name": "Other",
            "description": "Miscellaneous expenses not covered by other categories"
        }
    ],
    "timestamp": "2025-09-20 12:00:00",
    "server_time": 1726826400
}
```

---

### 2. Get User Tasks
**Endpoint:** `GET /employee/user_tasks.php`

**Description:** Retrieve tasks available for linking to expenses (both self-created and assigned tasks).

**Headers:**
```
Authorization: Bearer <employee_token>
Content-Type: application/json
```

**Response:**
```json
{
    "success": true,
    "message": "User tasks retrieved successfully",
    "data": [
        {
            "id": 123,
            "title": "Site inspection at Mall Road",
            "status": "active",
            "created_at": "2025-09-20 09:00:00",
            "site_name": "Mall Road Construction",
            "task_type": "self_created"
        },
        {
            "id": 124,
            "title": "Equipment maintenance check",
            "status": "completed",
            "created_at": "2025-09-19 14:30:00",
            "site_name": "Central Office",
            "task_type": "assigned"
        }
    ],
    "timestamp": "2025-09-20 12:00:00",
    "server_time": 1726826400
}
```

---

### 3. Submit Expense Request
**Endpoint:** `POST /employee/expenses.php`

**Description:** Submit a new expense request with category and optional task linking.

**Headers:**
```
Authorization: Bearer <employee_token>
Content-Type: application/json
```

**Request Body:**
```json
{
    "amount": 150.50,
    "reason": "Fuel expense for site visit to Mall Road project",
    "category_id": 2,
    "task_id": 123,
    "receipt_number": "REC-2025-001",
    "receipt_image": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQ...",
    "request_date": "2025-09-20"
}
```

**Required Fields:**
- `amount` (float): Expense amount
- `reason` (string): Expense reason (minimum 10 characters)
- `category_id` (integer): Valid expense category ID

**Optional Fields:**
- `task_id` (integer): Task ID to link expense to
- `receipt_number` (string): Receipt reference number
- `receipt_image` (string): Base64 encoded receipt image
- `request_date` (string): Date in YYYY-MM-DD format (defaults to today)

**Response:**
```json
{
    "success": true,
    "message": "Expense request submitted successfully",
    "data": {
        "request_id": 456
    },
    "timestamp": "2025-09-20 12:00:00",
    "server_time": 1726826400
}
```

**Error Response:**
```json
{
    "success": false,
    "message": "Invalid category selected",
    "data": null,
    "timestamp": "2025-09-20 12:00:00",
    "server_time": 1726826400
}
```

---

### 4. Get Employee Expenses
**Endpoint:** `GET /employee/expenses.php`

**Description:** Retrieve employee's expense requests with enhanced category and task information.

**Headers:**
```
Authorization: Bearer <employee_token>
Content-Type: application/json
```

**Query Parameters:**
- `month` (optional): Filter by month in YYYY-MM format (defaults to current month)

**Example:** `GET /employee/expenses.php?month=2025-09`

**Response:**
```json
{
    "success": true,
    "message": "Expense requests retrieved successfully",
    "data": {
        "requests": [
            {
                "id": 456,
                "employee_id": 789,
                "amount": "150.50",
                "category_id": 2,
                "category_name": "Fuel",
                "category_description": "Vehicle fuel and transportation costs",
                "task_id": 123,
                "task_title": "Site inspection at Mall Road",
                "reason": "Fuel expense for site visit to Mall Road project",
                "receipt_image": "task_1726826400_12345.jpg",
                "receipt_number": "REC-2025-001",
                "request_date": "2025-09-20",
                "status": "pending",
                "approved_by": null,
                "approved_by_name": null,
                "approval_date": null,
                "notes": null,
                "created_at": "2025-09-20 12:00:00",
                "updated_at": "2025-09-20 12:00:00"
            }
        ],
        "summary": {
            "total_requests": 1,
            "total_amount": 150.50,
            "approved_amount": 0,
            "pending_amount": 150.50,
            "rejected_amount": 0
        }
    },
    "timestamp": "2025-09-20 12:00:00",
    "server_time": 1726826400
}
```

---

## Admin APIs

### 5. Get Expenses with Advanced Filtering
**Endpoint:** `GET /admin/expenses.php`

**Description:** Retrieve expenses with robust filtering options for admin management.

**Headers:**
```
Authorization: Bearer <admin_token>
Content-Type: application/json
```

**Query Parameters:**
- `employee_id` (optional): Filter by specific employee
- `category_id` (optional): Filter by expense category
- `task_id` (optional): Filter by specific task
- `status` (optional): Filter by status (pending/approved/rejected)
- `start_date` (optional): Filter from date (YYYY-MM-DD)
- `end_date` (optional): Filter to date (YYYY-MM-DD)
- `month` (optional): Filter by specific month (YYYY-MM)
- `page` (optional): Page number for pagination (default: 1)
- `limit` (optional): Results per page (default: 20, max: 100)

**Example:**
```
GET /admin/expenses.php?category_id=2&status=pending&month=2025-09&page=1&limit=10
```

**Response:**
```json
{
    "success": true,
    "message": "Expenses retrieved successfully",
    "data": {
        "expenses": [
            {
                "id": 456,
                "employee_id": 789,
                "amount": "150.50",
                "category_id": 2,
                "category_name": "Fuel",
                "category_description": "Vehicle fuel and transportation costs",
                "task_id": 123,
                "task_title": "Site inspection at Mall Road",
                "employee_name": "John Doe",
                "employee_code": "EMP001",
                "reason": "Fuel expense for site visit",
                "receipt_image": "task_1726826400_12345.jpg",
                "receipt_number": "REC-2025-001",
                "request_date": "2025-09-20",
                "status": "pending",
                "approved_by": null,
                "approved_by_name": null,
                "approval_date": null,
                "notes": null,
                "created_at": "2025-09-20 12:00:00",
                "updated_at": "2025-09-20 12:00:00"
            }
        ],
        "pagination": {
            "current_page": 1,
            "total_pages": 1,
            "per_page": 10,
            "total_records": 1
        },
        "summary": {
            "total_requests": 1,
            "total_amount": "150.50",
            "pending_amount": "150.50",
            "approved_amount": "0.00",
            "rejected_amount": "0.00",
            "pending_count": 1,
            "approved_count": 0,
            "rejected_count": 0
        },
        "category_breakdown": [
            {
                "category_name": "Fuel",
                "count": "1",
                "total_amount": "150.50"
            }
        ],
        "filters_applied": {
            "employee_id": null,
            "task_id": null,
            "category_id": "2",
            "status": "pending",
            "start_date": null,
            "end_date": null,
            "month": "2025-09"
        }
    },
    "timestamp": "2025-09-20 12:00:00",
    "server_time": 1726826400
}
```

---

### 6. Update Expense Status (Approve/Reject)
**Endpoint:** `PUT /admin/expenses.php`

**Description:** Approve or reject expense requests.

**Headers:**
```
Authorization: Bearer <admin_token>
Content-Type: application/json
```

**Request Body:**
```json
{
    "expense_id": 456,
    "status": "approved",
    "notes": "Approved for valid business expense with proper receipt"
}
```

**Required Fields:**
- `expense_id` (integer): Expense request ID
- `status` (string): New status ("approved" or "rejected")

**Optional Fields:**
- `notes` (string): Approval/rejection notes

**Response:**
```json
{
    "success": true,
    "message": "Expense status updated successfully",
    "data": {
        "id": 456,
        "employee_name": "John Doe",
        "amount": "150.50",
        "category_name": "Fuel",
        "task_title": "Site inspection at Mall Road",
        "status": "approved",
        "approved_by_name": "Admin User",
        "approval_date": "2025-09-20 12:30:00",
        "notes": "Approved for valid business expense with proper receipt"
    },
    "timestamp": "2025-09-20 12:30:00",
    "server_time": 1726827800
}
```

---

## Error Codes and Messages

### Common Error Responses

**401 Unauthorized:**
```json
{
    "success": false,
    "message": "Authorization token required",
    "data": null,
    "timestamp": "2025-09-20 12:00:00",
    "server_time": 1726826400
}
```

**400 Bad Request:**
```json
{
    "success": false,
    "message": "Missing required fields: amount, reason, category_id",
    "data": null,
    "timestamp": "2025-09-20 12:00:00",
    "server_time": 1726826400
}
```

**404 Not Found:**
```json
{
    "success": false,
    "message": "Expense not found",
    "data": null,
    "timestamp": "2025-09-20 12:00:00",
    "server_time": 1726826400
}
```

**500 Server Error:**
```json
{
    "success": false,
    "message": "Database error occurred",
    "data": null,
    "timestamp": "2025-09-20 12:00:00",
    "server_time": 1726826400
}
```

### Validation Error Messages

- **Amount:** "Amount must be greater than zero"
- **Reason:** "Reason must be at least 10 characters long"
- **Category:** "Invalid category selected"
- **Task:** "Invalid task selected or task not assigned to you"
- **Status:** "Invalid status. Must be one of: pending, approved, rejected"

---

## Image Upload Guidelines

### Receipt Image Upload
- **Format:** Base64 encoded string with data URI prefix
- **Supported Types:** JPEG, PNG
- **Maximum Size:** 10MB
- **Storage Location:** `/assets/images/uploads/expenses/`
- **Filename Format:** `expense_<timestamp>_<unique_id>.jpg`

**Example Base64 Format:**
```
data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=
```

---

## Migration Notes

### Changes from Petty Cash System

1. **Table Renamed:** `petty_cash_requests` → `expenses`
2. **New Fields Added:**
   - `category_id` (required)
   - `task_id` (optional)
   - `receipt_number` (optional)
3. **API Endpoints Updated:**
   - `/employee/petty_cash.php` → `/employee/expenses.php`
4. **New Endpoints Added:**
   - `/employee/expense_categories.php`
   - `/employee/user_tasks.php`
   - `/admin/expenses.php`

### Mobile App Integration Checklist

- [ ] Update expense submission form to include category selection (required)
- [ ] Add optional task linking with dropdown
- [ ] Update expense list to display category and task information
- [ ] Implement new filtering options in admin screens
- [ ] Update API endpoints in mobile app configuration
- [ ] Test image upload with new directory structure
- [ ] Implement new validation rules
- [ ] Update local database schema if using offline storage

---

## Testing Endpoints

You can test these APIs using tools like Postman or curl:

```bash
# Get expense categories
curl -X GET "https://yourdomain.com/api/employee/expense_categories.php" \
  -H "Authorization: Bearer your_token_here"

# Submit expense
curl -X POST "https://yourdomain.com/api/employee/expenses.php" \
  -H "Authorization: Bearer your_token_here" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 100.00,
    "reason": "Transportation expense for client meeting",
    "category_id": 3,
    "task_id": 123
  }'
```

---

## Support

For technical support or questions about API implementation, please refer to:
- Database schema: `setup_expenses_system.sql`
- Implementation guide: `EXPENSES_SYSTEM_GUIDE.md`
- Source code: `/api/employee/` and `/api/admin/` directories