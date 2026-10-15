-- Test data for unified task system
-- Insert sample admin-assigned tasks

INSERT INTO tasks (
    title, description, task_type, assigned_to_employee_id, assigned_by_user_id,
    site_id, priority, start_date, due_date, expense_required, budget_amount,
    notes, start_latitude, start_longitude, status
) VALUES
('Complete Monthly Inventory', 'Conduct full inventory count for all equipment and materials', 'admin_assigned', 1, 1, 1, 'high', '2024-01-15', '2024-01-20', 1, 5000.00, 'Use the new inventory checklist form', 22.5726, 88.3639, 'pending'),
('Install Security Cameras', 'Install and configure 4 new security cameras in parking area', 'admin_assigned', 2, 1, 1, 'medium', '2024-01-16', '2024-01-18', 1, 15000.00, 'Cameras are in storage room B', 22.5726, 88.3639, 'pending'),
('Maintenance Check - Generator', 'Perform routine maintenance on backup generator', 'admin_assigned', 3, 1, 1, 'high', '2024-01-17', '2024-01-17', 0, NULL, 'Check oil, filters, and run test', 22.5726, 88.3639, 'pending');

-- Insert sample self-created task (if there's attendance data)
-- This would typically be created via the API with proper attendance checks
INSERT INTO tasks (
    title, description, task_type, created_by_employee_id, assigned_to_employee_id,
    site_id, start_latitude, start_longitude, status, attendance_id
) VALUES
('Clean Equipment Storage', 'Organize and clean the equipment storage area', 'self_created', 1, 1, 1, 22.5726, 88.3639, 'active', 1);