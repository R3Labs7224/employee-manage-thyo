# Tasks API Documentation

## Base URL
```
http://localhost/employee_management_system/api/employee/tasks.php
```

## Authentication
All requests require a Bearer token in the Authorization header.

```
Authorization: Bearer <employee_token>
```

---

## GET - Retrieve Tasks

### Endpoint
```
GET /api/employee/tasks.php
```

### Request Headers
```
Authorization: Bearer <employee_token>
Content-Type: application/json
```

### Query Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `date` | string | No | Date in YYYY-MM-DD format. Defaults to today |
| `limit` | integer | No | Maximum number of tasks to return |

### Example Request
```bash
curl -X GET "http://localhost/employee_management_system/api/employee/tasks.php?date=2025-09-19&limit=10" \
  -H "Authorization: Bearer <employee_token>" \
  -H "Content-Type: application/json"
```

### Success Response (200 OK)
```json
{
  "success": true,
  "message": "Tasks retrieved successfully",
  "data": {
    "tasks": [
      {
        "id": 1,
        "employee_id": 2,
        "attendance_id": 5,
        "site_id": 1,
        "title": "Complete inventory check",
        "description": "Check all items in warehouse section A",
        "task_image": "task_12345_67890.jpg",
        "latitude": 21.52162950,
        "longitude": 86.89994160,
        "status": "active",
        "created_at": "2025-09-19 10:30:00",
        "completed_at": null,
        "completion_notes": null,
        "completion_latitude": null,
        "completion_longitude": null,
        "completion_image": null,
        "admin_created": 1,
        "assigned_by": 1,
        "priority": "high",
        "due_date": "2025-09-20",
        "site_name": "Main Office",
        "assigned_by_name": "admin",
        "location_display": "Lat: 21.521630, Lng: 86.899942",
        "completion_location_display": null,
        "created_time_display": "10:30",
        "completed_time_display": null,
        "assignment_id": 15,
        "assignment_status": "pending",
        "assignment_notes": null,
        "assignment_started_at": null,
        "assignment_completed_at": null,
        "task_type": "assigned"
      }
    ],
    "summary": {
      "total_tasks": 5,
      "completed_tasks": 2,
      "active_tasks": 3,
      "cancelled_tasks": 0,
      "assigned_tasks": 3,
      "self_created_tasks": 2
    },
    "can_create_task": true,
    "attendance_status": "checked_in"
  }
}
```

### Error Response (401 Unauthorized)
```json
{
  "success": false,
  "message": "Authorization token required",
  "data": null
}
```

### Error Response (401 Unauthorized - Invalid Token)
```json
{
  "success": false,
  "message": "Invalid or expired token",
  "data": null
}
```

---

## POST - Create Task

### Endpoint
```
POST /api/employee/tasks.php
```

### Request Headers
```
Authorization: Bearer <employee_token>
Content-Type: application/json
```

### Request Body
```json
{
  "title": "Task title",
  "description": "Task description (optional)",
  "site_id": 1,
  "latitude": 21.52162950,
  "longitude": 86.89994160,
  "task_image": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD..." // Optional base64 image
}
```

### Example Request
```bash
curl -X POST "http://localhost/employee_management_system/api/employee/tasks.php" \
  -H "Authorization: Bearer <employee_token>" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Equipment maintenance",
    "description": "Check and clean equipment in section B",
    "site_id": 1,
    "latitude": 21.52162950,
    "longitude": 86.89994160
  }'
```

### Success Response (200 OK)
```json
{
  "success": true,
  "message": "Task created successfully",
  "data": {
    "id": 6,
    "employee_id": 2,
    "attendance_id": 5,
    "site_id": 1,
    "title": "Equipment maintenance",
    "description": "Check and clean equipment in section B",
    "task_image": null,
    "start_time": null,
    "end_time": null,
    "latitude": 21.52162950,
    "longitude": 86.89994160,
    "status": "active",
    "created_at": "2025-09-19 14:30:15",
    "updated_at": "2025-09-19 14:30:15",
    "completed_at": null,
    "completion_notes": null,
    "completion_latitude": null,
    "completion_longitude": null,
    "completion_image": null,
    "site_name": "Main Office",
    "location_display": "Lat: 21.521630, Lng: 86.899942",
    "created_time_display": "14:30"
  }
}
```

---

## PUT - Complete Task

### Endpoint
```
PUT /api/employee/tasks.php
```

### Request Headers
```
Authorization: Bearer <employee_token>
Content-Type: application/json
```

### Request Body
```json
{
  "task_id": 6,
  "completion_notes": "Task completed successfully",
  "latitude": 21.52163000,
  "longitude": 86.89994000,
  "completion_image": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD..." // Optional base64 image
}
```

### Example Request
```bash
curl -X PUT "http://localhost/employee_management_system/api/employee/tasks.php" \
  -H "Authorization: Bearer <employee_token>" \
  -H "Content-Type: application/json" \
  -d '{
    "task_id": 6,
    "completion_notes": "Equipment cleaned and checked successfully",
    "latitude": 21.52163000,
    "longitude": 86.89994000
  }'
```

### Success Response (200 OK)
```json
{
  "success": true,
  "message": "Task completed successfully",
  "data": {
    "id": 6,
    "employee_id": 2,
    "attendance_id": 5,
    "site_id": 1,
    "title": "Equipment maintenance",
    "description": "Check and clean equipment in section B",
    "task_image": null,
    "start_time": null,
    "end_time": null,
    "latitude": 21.52162950,
    "longitude": 86.89994160,
    "status": "completed",
    "created_at": "2025-09-19 14:30:15",
    "updated_at": "2025-09-19 15:45:20",
    "completed_at": "2025-09-19 15:45:20",
    "completion_notes": "Equipment cleaned and checked successfully",
    "completion_latitude": 21.52163000,
    "completion_longitude": 86.89994000,
    "completion_image": null,
    "site_name": "Main Office",
    "location_display": "Lat: 21.521630, Lng: 86.899942",
    "completion_location_display": "Lat: 21.521630, Lng: 86.899940",
    "created_time_display": "14:30",
    "completed_time_display": "15:45"
  }
}
```

---

## PATCH - Update Assignment Status

### Endpoint
```
PATCH /api/employee/tasks.php
```

### Request Headers
```
Authorization: Bearer <employee_token>
Content-Type: application/json
```

### Request Body
```json
{
  "assignment_id": 15,
  "status": "in_progress",
  "notes": "Started working on the task",
  "latitude": 21.52163000,
  "longitude": 86.89994000
}
```

### Valid Status Values
- `pending` - Task is assigned but not started
- `in_progress` - Task is being worked on
- `completed` - Task is finished
- `cancelled` - Task is cancelled

### Example Request
```bash
curl -X PATCH "http://localhost/employee_management_system/api/employee/tasks.php" \
  -H "Authorization: Bearer <employee_token>" \
  -H "Content-Type: application/json" \
  -d '{
    "assignment_id": 15,
    "status": "completed",
    "notes": "Inventory check completed successfully",
    "latitude": 21.52163000,
    "longitude": 86.89994000
  }'
```

### Success Response (200 OK)
```json
{
  "success": true,
  "message": "Assignment status updated successfully",
  "data": {
    "id": 15,
    "task_id": 1,
    "assigned_to": 5,
    "assigned_by": 1,
    "status": "completed",
    "notes": "Inventory check completed successfully",
    "started_at": "2025-09-19 10:45:00",
    "completed_at": "2025-09-19 15:30:00",
    "created_at": "2025-09-19 10:30:00",
    "updated_at": "2025-09-19 15:30:00",
    "title": "Complete inventory check",
    "task_status": "completed",
    "site_name": "Main Office",
    "assigned_by_name": "admin"
  }
}
```

---

## Data Types

### Task Object
| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Task ID |
| `employee_id` | integer | Employee who created the task (null for admin tasks) |
| `attendance_id` | integer | Related attendance record (null for admin tasks) |
| `site_id` | integer | Site where task is performed |
| `title` | string | Task title |
| `description` | string | Task description |
| `task_image` | string | Filename of task image |
| `latitude` | decimal | Task location latitude |
| `longitude` | decimal | Task location longitude |
| `status` | enum | `active`, `completed`, `cancelled`, `pending` |
| `admin_created` | boolean | Whether task was created by admin |
| `assigned_by` | integer | Admin user who assigned the task |
| `priority` | enum | `low`, `medium`, `high` |
| `due_date` | date | Task due date (YYYY-MM-DD) |
| `task_type` | string | `assigned` or `self_created` |

### Assignment Status Values
- `pending` - Newly assigned, not started
- `in_progress` - Employee is working on it
- `completed` - Employee finished the task
- `cancelled` - Task was cancelled

### Attendance Status Values
- `not_checked_in` - Employee hasn't checked in today
- `checked_in` - Employee is checked in and can create tasks
- `checked_out` - Employee has checked out

---

## Error Codes

| HTTP Code | Description |
|-----------|-------------|
| 200 | Success |
| 400 | Bad Request - Invalid input data |
| 401 | Unauthorized - Missing or invalid token |
| 404 | Not Found - Task/assignment not found |
| 405 | Method Not Allowed - Invalid HTTP method |
| 500 | Internal Server Error - Database or server error |

---

## Example Mobile App Integration

### Get employee token first (from login API)
```javascript
const token = "your_employee_token_here";
```

### Fetch tasks for today
```javascript
const response = await fetch('http://localhost/employee_management_system/api/employee/tasks.php', {
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  }
});

const result = await response.json();
if (result.success) {
  console.log('Tasks:', result.data.tasks);
  console.log('Summary:', result.data.summary);
} else {
  console.error('Error:', result.message);
}
```

### Create a new task
```javascript
const taskData = {
  title: "Equipment check",
  description: "Check all equipment in zone A",
  site_id: 1,
  latitude: 21.52162950,
  longitude: 86.89994160
};

const response = await fetch('http://localhost/employee_management_system/api/employee/tasks.php', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify(taskData)
});

const result = await response.json();
```

### Update assignment status
```javascript
const updateData = {
  assignment_id: 15,
  status: "completed",
  notes: "Task completed successfully",
  latitude: 21.52163000,
  longitude: 86.89994000
};

const response = await fetch('http://localhost/employee_management_system/api/employee/tasks.php', {
  method: 'PATCH',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify(updateData)
});
```