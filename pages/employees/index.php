<?php
require_once '../../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin();

// Check if user has permission
if (!hasPermission('superadmin') && !hasPermission('supervisor')) {
    header('Location: ../../index.php');
    exit;
}

$pageTitle = 'Employees';
$message = '';

// Handle success/error messages
if (isset($_GET['success'])) {
    if ($_GET['success'] == '1') {
        $message = '<div class="alert alert-success">Employee added successfully!</div>';
    } else {
        $message = '<div class="alert alert-success">' . htmlspecialchars($_GET['success']) . '</div>';
    }
}

if (isset($_GET['error'])) {
    $message = '<div class="alert alert-error">' . htmlspecialchars($_GET['error']) . '</div>';
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$department_filter = $_GET['department'] ?? '';
$status_filter = $_GET['status'] ?? 'active';

// Build query with new fields
$query = "
    SELECT 
        e.*,
        d.name as department_name,
        s.name as shift_name,
        st.name as site_name,
        rm.name as reporting_manager_name,
        rm.employee_code as reporting_manager_code
    FROM employees e 
    LEFT JOIN departments d ON e.department_id = d.id 
    LEFT JOIN shifts s ON e.shift_id = s.id 
    LEFT JOIN sites st ON e.site_id = st.id
    LEFT JOIN employees rm ON e.reporting_manager_id = rm.id
    WHERE e.status = ?
";

$params = [$status_filter];

if (!empty($search)) {
    $query .= " AND (e.name LIKE ? OR e.employee_code LIKE ? OR e.email LIKE ? OR e.contact_number LIKE ? OR e.designation LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($department_filter)) {
    $query .= " AND e.department_id = ?";
    $params[] = $department_filter;
}

$query .= " ORDER BY e.name";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $employees = $stmt->fetchAll();
    
    // Get departments for filter dropdown
    $departments = $pdo->query("SELECT * FROM departments WHERE status = 'active' ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $employees = [];
    $departments = [];
    $message = '<div class="alert alert-error">Error fetching employees.</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Employee Management System</title>
    <link rel="icon" type="image/png" href="../../assets/images/logo.png">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .employee-photo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
        }
        .employee-info {
            display: flex;
            align-items: center;
        }
        .employee-details {
            display: flex;
            flex-direction: column;
        }
        .employee-name {
            font-weight: 600;
            margin-bottom: 2px;
        }
        .employee-code {
            font-size: 0.85em;
            color: #666;
        }
        .employee-designation {
            font-size: 0.85em;
            color: #4f46e5;
            font-style: italic;
        }
        .filters-section {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .filters-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }
        .contact-info {
            font-size: 0.9em;
        }
        .government-docs {
            font-size: 0.85em;
            color: #666;
        }
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .status-active {
            background-color: #d1fae5;
            color: #065f46;
        }
        .status-inactive {
            background-color: #fee2e2;
            color: #991b1b;
        }
        @media (max-width: 768px) {
            .filters-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../../components/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../../components/header.php'; ?>
            
            <div class="content">
                <?php echo $message; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-users"></i> All Employees (<?php echo count($employees); ?>)</h3>
                        <?php if (hasPermission('superadmin')): ?>
                            <a href="add.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Employee
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-body">
                        <!-- Filters Section -->
                        <div class="filters-section">
                            <form method="GET" action="">
                                <div class="filters-row">
                                    <div class="form-group">
                                        <label for="search">Search Employees</label>
                                        <input type="text" id="search" name="search" class="form-control" 
                                               placeholder="Search by name, code, email, contact, or designation..."
                                               value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="department">Department</label>
                                        <select id="department" name="department" class="form-control">
                                            <option value="">All Departments</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?php echo $dept['id']; ?>" 
                                                        <?php echo ($department_filter == $dept['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($dept['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="status">Status</label>
                                        <select id="status" name="status" class="form-control">
                                            <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo ($status_filter === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i> Filter
                                        </button>
                                        <a href="index.php" class="btn btn-secondary">
                                            <i class="fas fa-times"></i> Clear
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <?php if (empty($employees)): ?>
                            <div style="text-align: center; padding: 3rem; color: #666;">
                                <i class="fas fa-users fa-3x" style="margin-bottom: 1rem; opacity: 0.5;"></i>
                                <h3>No Employees Found</h3>
                                <?php if (empty($search) && empty($department_filter)): ?>
                                    <p>Start by adding your first employee.</p>
                                    <?php if (hasPermission('superadmin')): ?>
                                        <a href="add.php" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> Add Employee
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p>Try adjusting your search criteria.</p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Contact Info</th>
                                            <th>Department</th>
                                            <th>Work Details</th>
                                            <th>Documents</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($employees as $employee): ?>
                                        <tr>
                                            <td>
                                                <div class="employee-info">
                                                    <?php if (!empty($employee['photograph'])): ?>
                                                        <img src="../../<?php echo htmlspecialchars($employee['photograph']); ?>" 
                                                             alt="Photo" class="employee-photo">
                                                    <?php else: ?>
                                                        <div class="employee-photo" style="background-color: #e5e7eb; display: flex; align-items: center; justify-content: center;">
                                                            <i class="fas fa-user" style="color: #9ca3af;"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="employee-details">
                                                        <div class="employee-name">
                                                            <?php echo htmlspecialchars($employee['name']); ?>
                                                        </div>
                                                        <div class="employee-code">
                                                            ID: <?php echo htmlspecialchars($employee['employee_code']); ?>
                                                        </div>
                                                        <?php if (!empty($employee['designation'])): ?>
                                                            <div class="employee-designation">
                                                                <?php echo htmlspecialchars($employee['designation']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <td>
                                                <div class="contact-info">
                                                    <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($employee['contact_number']); ?></div>
                                                    <?php if (!empty($employee['email'])): ?>
                                                        <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($employee['email']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($employee['emergency_contact_number'])): ?>
                                                        <div style="color: #ef4444;"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($employee['emergency_contact_number']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            
                                            <td>
                                                <div>
                                                    <?php if (!empty($employee['department_name'])): ?>
                                                        <div><strong><?php echo htmlspecialchars($employee['department_name']); ?></strong></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($employee['reporting_manager_name'])): ?>
                                                        <div style="font-size: 0.85em; color: #666;">
                                                            Reports to: <?php echo htmlspecialchars($employee['reporting_manager_name']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            
                                            <td>
                                                <div style="font-size: 0.9em;">
                                                    <?php if (!empty($employee['site_name'])): ?>
                                                        <div><i class="fas fa-building"></i> <?php echo htmlspecialchars($employee['site_name']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($employee['shift_name'])): ?>
                                                        <div><i class="fas fa-clock"></i> <?php echo htmlspecialchars($employee['shift_name']); ?></div>
                                                    <?php endif; ?>
                                                    <div><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($employee['joining_date'])); ?></div>
                                                </div>
                                            </td>
                                            
                                            <td>
                                                <div class="government-docs">
                                                    <?php if (!empty($employee['aadhar_number'])): ?>
                                                        <div>Aadhar: <?php echo htmlspecialchars($employee['aadhar_number']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($employee['pan_number'])): ?>
                                                        <div>PAN: <?php echo htmlspecialchars($employee['pan_number']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($employee['uan_number'])): ?>
                                                        <div>UAN: <?php echo htmlspecialchars($employee['uan_number']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            
                                            <td>
                                                <span class="status-badge status-<?php echo $employee['status']; ?>">
                                                    <?php echo ucfirst($employee['status']); ?>
                                                </span>
                                            </td>
                                            
                                            <td class="actions-column">
                                               
                                                
                                                <?php if (hasPermission('superadmin')): ?>
                                                    <a href="edit.php?id=<?php echo $employee['id']; ?>" 
                                                       class="btn btn-sm btn-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    
                                                    <a href="javascript:void(0)" 
                                                       onclick="confirmDelete(<?php echo $employee['id']; ?>, '<?php echo addslashes($employee['name']); ?>')" 
                                                       class="btn btn-sm btn-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(id, name) {
            if (confirm(`Are you sure you want to delete employee "${name}"? This action cannot be undone.`)) {
                window.location.href = `delete.php?id=${id}`;
            }
        }
        
        // Auto-submit form on status change
        document.getElementById('status').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>