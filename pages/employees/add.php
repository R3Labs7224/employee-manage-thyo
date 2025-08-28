<?php
require_once '../../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin();

// Check if user has permission
if (!hasPermission('superadmin')) {
    header('Location: ../../index.php');
    exit;
}

$pageTitle = 'Add Employee';
$message = '';
$errors = [];

// Get departments, shifts, sites, and potential reporting managers for dropdowns
try {
    $departments = $pdo->query("SELECT * FROM departments WHERE status = 'active' ORDER BY name")->fetchAll();
    $shifts = $pdo->query("SELECT * FROM shifts WHERE status = 'active' ORDER BY name")->fetchAll();
    $sites = $pdo->query("SELECT * FROM sites WHERE status = 'active' ORDER BY name")->fetchAll();
    $managers = $pdo->query("SELECT id, name, employee_code FROM employees WHERE status = 'active' ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $departments = $shifts = $sites = $managers = [];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize and validate basic fields
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $contact_number = sanitize($_POST['contact_number']); // Renamed from phone
    $employee_code = sanitize($_POST['employee_code']);
    $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $shift_id = !empty($_POST['shift_id']) ? (int)$_POST['shift_id'] : null;
    $site_id = !empty($_POST['site_id']) ? (int)$_POST['site_id'] : null;
    $basic_salary = (float)$_POST['basic_salary'];
    $daily_wage = (float)$_POST['daily_wage'];
    $epf_number = sanitize($_POST['epf_number']);
    $joining_date = $_POST['joining_date'];
    $password = $_POST['password'];
    
    // Sanitize new fields
    $uan_number = sanitize($_POST['uan_number']);
    $esic_number = sanitize($_POST['esic_number']);
    $aadhar_number = sanitize($_POST['aadhar_number']);
    $pan_number = strtoupper(sanitize($_POST['pan_number']));
    $designation = sanitize($_POST['designation']);
    $reporting_manager_id = !empty($_POST['reporting_manager_id']) ? (int)$_POST['reporting_manager_id'] : null;
    $emergency_contact_number = sanitize($_POST['emergency_contact_number']);
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $qualification = sanitize($_POST['qualification']);
    $total_experience = !empty($_POST['total_experience']) ? (float)$_POST['total_experience'] : null;
    $nationality = sanitize($_POST['nationality']) ?: 'Indian';
    $religion = sanitize($_POST['religion']);
    $marital_status = $_POST['marital_status'];
    $gender = $_POST['gender'];
    $blood_group = $_POST['blood_group'];
    $current_address = sanitize($_POST['current_address']);
    $permanent_address = sanitize($_POST['permanent_address']);
    $last_working_day = !empty($_POST['last_working_day']) ? $_POST['last_working_day'] : null;
    $reason_of_separation = sanitize($_POST['reason_of_separation']);
    
    // Basic validation
    if (empty($name)) $errors[] = 'Name is required';
    if (empty($contact_number)) $errors[] = 'Contact number is required';
    if (empty($employee_code)) $errors[] = 'Employee code is required';
    if (empty($joining_date)) $errors[] = 'Joining date is required';
    if (empty($password)) $errors[] = 'Password is required';
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    // Validate new fields
    if (!empty($uan_number) && !preg_match('/^\d{12}$/', $uan_number)) {
        $errors[] = 'UAN Number must be 12 digits';
    }
    if (!empty($esic_number) && !preg_match('/^\d{17}$/', $esic_number)) {
        $errors[] = 'ESIC Number must be 17 digits';
    }
    if (!empty($aadhar_number) && !preg_match('/^\d{12}$/', $aadhar_number)) {
        $errors[] = 'Aadhar Number must be 12 digits';
    }
    if (!empty($pan_number) && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan_number)) {
        $errors[] = 'Invalid PAN Number format (e.g., ABCDE1234F)';
    }
    if (!empty($contact_number) && !preg_match('/^\d{10}$/', $contact_number)) {
        $errors[] = 'Contact Number must be 10 digits';
    }
    if (!empty($emergency_contact_number) && !preg_match('/^\d{10}$/', $emergency_contact_number)) {
        $errors[] = 'Emergency Contact Number must be 10 digits';
    }
    
    // Handle file upload for photograph
    $photograph_path = null;
    if (isset($_FILES['photograph']) && $_FILES['photograph']['error'] == 0) {
        $upload_dir = '../../uploads/employees/photos/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['photograph']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            $errors[] = 'Only JPG, JPEG, PNG, and GIF files are allowed for photograph';
        } elseif ($_FILES['photograph']['size'] > 5 * 1024 * 1024) { // 5MB limit
            $errors[] = 'Photograph file size must be less than 5MB';
        } else {
            $photograph_filename = $employee_code . '_' . time() . '.' . $file_extension;
            $photograph_path = 'uploads/employees/photos/' . $photograph_filename;
            
            if (!move_uploaded_file($_FILES['photograph']['tmp_name'], $upload_dir . $photograph_filename)) {
                $errors[] = 'Failed to upload photograph';
                $photograph_path = null;
            }
        }
    }
    
    // Check for duplicate values
    if (empty($errors)) {
        try {
            // Check employee code
            $stmt = $pdo->prepare("SELECT id FROM employees WHERE employee_code = ?");
            $stmt->execute([$employee_code]);
            if ($stmt->fetch()) {
                $errors[] = 'Employee code already exists';
            }
            
            // Check email if provided
            if (!empty($email)) {
                $stmt = $pdo->prepare("SELECT id FROM employees WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errors[] = 'Email already exists';
                }
            }
            
            // Check UAN number if provided
            if (!empty($uan_number)) {
                $stmt = $pdo->prepare("SELECT id FROM employees WHERE uan_number = ?");
                $stmt->execute([$uan_number]);
                if ($stmt->fetch()) {
                    $errors[] = 'UAN Number already exists';
                }
            }
            
            // Check ESIC number if provided
            if (!empty($esic_number)) {
                $stmt = $pdo->prepare("SELECT id FROM employees WHERE esic_number = ?");
                $stmt->execute([$esic_number]);
                if ($stmt->fetch()) {
                    $errors[] = 'ESIC Number already exists';
                }
            }
            
            // Check Aadhar number if provided
            if (!empty($aadhar_number)) {
                $stmt = $pdo->prepare("SELECT id FROM employees WHERE aadhar_number = ?");
                $stmt->execute([$aadhar_number]);
                if ($stmt->fetch()) {
                    $errors[] = 'Aadhar Number already exists';
                }
            }
            
            // Check PAN number if provided
            if (!empty($pan_number)) {
                $stmt = $pdo->prepare("SELECT id FROM employees WHERE pan_number = ?");
                $stmt->execute([$pan_number]);
                if ($stmt->fetch()) {
                    $errors[] = 'PAN Number already exists';
                }
            }
            
        } catch (PDOException $e) {
            $errors[] = 'Database error occurred while checking duplicates';
        }
    }
    
    if (empty($errors)) {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO employees (
                    employee_code, name, email, contact_number, department_id, shift_id, site_id,
                    basic_salary, daily_wage, epf_number, joining_date, password, status,
                    uan_number, esic_number, aadhar_number, pan_number, designation,
                    reporting_manager_id, emergency_contact_number,
                    date_of_birth, qualification, total_experience, nationality,
                    religion, marital_status, gender, blood_group, photograph,
                    current_address, permanent_address, last_working_day, reason_of_separation
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active',
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");
            
            $stmt->execute([
                $employee_code, $name, $email, $contact_number, $department_id, $shift_id, $site_id,
                $basic_salary, $daily_wage, $epf_number, $joining_date, $hashedPassword,
                $uan_number, $esic_number, $aadhar_number, $pan_number, $designation,
                $reporting_manager_id, $emergency_contact_number,
                $date_of_birth, $qualification, $total_experience, $nationality,
                $religion, $marital_status, $gender, $blood_group, $photograph_path,
                $current_address, $permanent_address, $last_working_day, $reason_of_separation
            ]);
            
            header('Location: index.php?success=Employee added successfully');
            exit;
            
        } catch (PDOException $e) {
            $errors[] = 'Error adding employee: ' . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        $message = '<div class="alert alert-error">' . implode('<br>', $errors) . '</div>';
    }
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
        .form-section {
            background: #f8f9fa;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 8px;
            border-left: 4px solid #4f46e5;
        }
        .form-section h4 {
            color: #4f46e5;
            margin-bottom: 1rem;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .form-row.full-width {
            grid-template-columns: 1fr;
        }
        .same-as-current {
            margin-top: 0.5rem;
        }
        .same-as-current input[type="checkbox"] {
            margin-right: 0.5rem;
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
                        <h3><i class="fas fa-user-plus"></i> Add New Employee</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            
                            <!-- Basic Information -->
                            <div class="form-section">
                                <h4><i class="fas fa-user"></i> Basic Information</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="employee_code">Employee Code *</label>
                                        <input type="text" id="employee_code" name="employee_code" class="form-control" required value="<?php echo htmlspecialchars($_POST['employee_code'] ?? ''); ?>">
                                    <div class="form-row">
                                    <div class="form-group">
                                        <label for="photograph">Profile Photograph</label>
                                        <input type="file" id="photograph" name="photograph" class="form-control" accept="image/*">
                                        <small class="form-text text-muted">Max size: 5MB. Formats: JPG, PNG, GIF</small>
                                    </div>
                                </div>
                                    
                                    <div class="form-group">
                                        <label for="name">Full Name *</label>
                                        <input type="text" id="name" name="name" class="form-control" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="contact_number">Contact Number *</label>
                                        <input type="tel" id="contact_number" name="contact_number" class="form-control" required value="<?php echo htmlspecialchars($_POST['contact_number'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="emergency_contact_number">Emergency Contact Number</label>
                                        <input type="tel" id="emergency_contact_number" name="emergency_contact_number" class="form-control" value="<?php echo htmlspecialchars($_POST['emergency_contact_number'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="password">Password *</label>
                                        <input type="password" id="password" name="password" class="form-control" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Personal Information -->
                            <div class="form-section">
                                <h4><i class="fas fa-id-card"></i> Personal Information</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="date_of_birth">Date of Birth</label>
                                        <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="gender">Gender</label>
                                        <select id="gender" name="gender" class="form-control">
                                            <option value="">Select Gender</option>
                                            <option value="male" <?php echo (($_POST['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo (($_POST['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Female</option>
                                            <option value="other" <?php echo (($_POST['gender'] ?? '') === 'other') ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="blood_group">Blood Group</label>
                                        <select id="blood_group" name="blood_group" class="form-control">
                                            <option value="">Select Blood Group</option>
                                            <?php 
                                            $blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                            foreach ($blood_groups as $bg): 
                                            ?>
                                                <option value="<?php echo $bg; ?>" <?php echo (($_POST['blood_group'] ?? '') === $bg) ? 'selected' : ''; ?>><?php echo $bg; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="marital_status">Marital Status</label>
                                        <select id="marital_status" name="marital_status" class="form-control">
                                            <option value="">Select Status</option>
                                            <option value="single" <?php echo (($_POST['marital_status'] ?? '') === 'single') ? 'selected' : ''; ?>>Single</option>
                                            <option value="married" <?php echo (($_POST['marital_status'] ?? '') === 'married') ? 'selected' : ''; ?>>Married</option>
                                            <option value="divorced" <?php echo (($_POST['marital_status'] ?? '') === 'divorced') ? 'selected' : ''; ?>>Divorced</option>
                                            <option value="widowed" <?php echo (($_POST['marital_status'] ?? '') === 'widowed') ? 'selected' : ''; ?>>Widowed</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="nationality">Nationality</label>
                                        <input type="text" id="nationality" name="nationality" class="form-control" value="<?php echo htmlspecialchars($_POST['nationality'] ?? 'Indian'); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="religion">Religion</label>
                                        <input type="text" id="religion" name="religion" class="form-control" value="<?php echo htmlspecialchars($_POST['religion'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Government Documents -->
                            <div class="form-section">
                                <h4><i class="fas fa-file-alt"></i> Government Documents</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="aadhar_number">Aadhar Number</label>
                                        <input type="text" id="aadhar_number" name="aadhar_number" class="form-control" maxlength="12" pattern="\d{12}" value="<?php echo htmlspecialchars($_POST['aadhar_number'] ?? ''); ?>">
                                        <small class="form-text text-muted">12 digit number</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="pan_number">PAN Number</label>
                                        <input type="text" id="pan_number" name="pan_number" class="form-control" maxlength="10" pattern="[A-Z]{5}[0-9]{4}[A-Z]{1}" style="text-transform: uppercase" value="<?php echo htmlspecialchars($_POST['pan_number'] ?? ''); ?>">
                                        <small class="form-text text-muted">Format: ABCDE1234F</small>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="uan_number">UAN Number</label>
                                        <input type="text" id="uan_number" name="uan_number" class="form-control" maxlength="12" pattern="\d{12}" value="<?php echo htmlspecialchars($_POST['uan_number'] ?? ''); ?>">
                                        <small class="form-text text-muted">12 digit UAN number</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="esic_number">ESIC Number</label>
                                        <input type="text" id="esic_number" name="esic_number" class="form-control" maxlength="17" pattern="\d{17}" value="<?php echo htmlspecialchars($_POST['esic_number'] ?? ''); ?>">
                                        <small class="form-text text-muted">17 digit ESIC number</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Professional Information -->
                            <div class="form-section">
                                <h4><i class="fas fa-briefcase"></i> Professional Information</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="designation">Designation</label>
                                        <input type="text" id="designation" name="designation" class="form-control" value="<?php echo htmlspecialchars($_POST['designation'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="department_id">Department</label>
                                        <select id="department_id" name="department_id" class="form-control">
                                            <option value="">Select Department</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?php echo $dept['id']; ?>" <?php echo (($_POST['department_id'] ?? '') == $dept['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($dept['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="reporting_manager_id">Reporting Manager</label>
                                        <select id="reporting_manager_id" name="reporting_manager_id" class="form-control">
                                            <option value="">Select Reporting Manager</option>
                                            <?php foreach ($managers as $manager): ?>
                                                <option value="<?php echo $manager['id']; ?>" <?php echo (($_POST['reporting_manager_id'] ?? '') == $manager['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($manager['name']) . ' (' . htmlspecialchars($manager['employee_code']) . ')'; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="joining_date">Joining Date *</label>
                                        <input type="date" id="joining_date" name="joining_date" class="form-control" required value="<?php echo htmlspecialchars($_POST['joining_date'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="qualification">Qualification</label>
                                        <input type="text" id="qualification" name="qualification" class="form-control" value="<?php echo htmlspecialchars($_POST['qualification'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="total_experience">Total Experience (Years)</label>
                                        <input type="number" id="total_experience" name="total_experience" class="form-control" step="0.1" min="0" max="50" value="<?php echo htmlspecialchars($_POST['total_experience'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Work Assignment -->
                            <div class="form-section">
                                <h4><i class="fas fa-building"></i> Work Assignment</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="shift_id">Shift</label>
                                        <select id="shift_id" name="shift_id" class="form-control">
                                            <option value="">Select Shift</option>
                                            <?php foreach ($shifts as $shift): ?>
                                                <option value="<?php echo $shift['id']; ?>" <?php echo (($_POST['shift_id'] ?? '') == $shift['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($shift['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="site_id">Site</label>
                                        <select id="site_id" name="site_id" class="form-control">
                                            <option value="">Select Site</option>
                                            <?php foreach ($sites as $site): ?>
                                                <option value="<?php echo $site['id']; ?>" <?php echo (($_POST['site_id'] ?? '') == $site['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($site['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Salary Information -->
                            <div class="form-section">
                                <h4><i class="fas fa-money-bill-wave"></i> Salary Information</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="basic_salary">Basic Salary</label>
                                        <input type="number" id="basic_salary" name="basic_salary" class="form-control" step="0.01" min="0" value="<?php echo htmlspecialchars($_POST['basic_salary'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="daily_wage">Daily Wage</label>
                                        <input type="number" id="daily_wage" name="daily_wage" class="form-control" step="0.01" min="0" value="<?php echo htmlspecialchars($_POST['daily_wage'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="epf_number">EPF Number</label>
                                        <input type="text" id="epf_number" name="epf_number" class="form-control" value="<?php echo htmlspecialchars($_POST['epf_number'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Address Information -->
                            <div class="form-section">
                                <h4><i class="fas fa-map-marker-alt"></i> Address Information</h4>
                                <div class="form-row full-width">
                                    <div class="form-group">
                                        <label for="current_address">Current Address</label>
                                        <textarea id="current_address" name="current_address" class="form-control" rows="3"><?php echo htmlspecialchars($_POST['current_address'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="form-row full-width">
                                    <div class="form-group">
                                        <label for="permanent_address">Permanent Address</label>
                                        <div class="same-as-current">
                                            <input type="checkbox" id="same_as_current" onchange="copyCurrentAddress()">
                                            <label for="same_as_current">Same as current address</label>
                                        </div>
                                        <textarea id="permanent_address" name="permanent_address" class="form-control" rows="3"><?php echo htmlspecialchars($_POST['permanent_address'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Separation Details (Optional) -->
                            <div class="form-section">
                                <h4><i class="fas fa-sign-out-alt"></i> Separation Details (Optional)</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="last_working_day">Last Working Day</label>
                                        <input type="date" id="last_working_day" name="last_working_day" class="form-control" value="<?php echo htmlspecialchars($_POST['last_working_day'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-row full-width">
                                    <div class="form-group">
                                        <label for="reason_of_separation">Reason of Separation</label>
                                        <textarea id="reason_of_separation" name="reason_of_separation" class="form-control" rows="3"><?php echo htmlspecialchars($_POST['reason_of_separation'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Add Employee
                                </button>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Copy current address to permanent address
        function copyCurrentAddress() {
            const sameAsCurrentCheckbox = document.getElementById('same_as_current');
            const currentAddress = document.getElementById('current_address');
            const permanentAddress = document.getElementById('permanent_address');
            
            if (sameAsCurrentCheckbox.checked) {
                permanentAddress.value = currentAddress.value;
                permanentAddress.disabled = true;
            } else {
                permanentAddress.disabled = false;
            }
        }
        
        // Update permanent address when current address changes and checkbox is checked
        document.getElementById('current_address').addEventListener('input', function() {
            const sameAsCurrentCheckbox = document.getElementById('same_as_current');
            const permanentAddress = document.getElementById('permanent_address');
            
            if (sameAsCurrentCheckbox.checked) {
                permanentAddress.value = this.value;
            }
        });
        
        // Format phone numbers
        function formatPhoneNumber(input) {
            input.value = input.value.replace(/\D/g, '').substring(0, 10);
        }
        
        document.getElementById('contact_number').addEventListener('input', function() {
            formatPhoneNumber(this);
        });
        
        document.getElementById('emergency_contact_number').addEventListener('input', function() {
            formatPhoneNumber(this);
        });
        
        // Format government numbers
        document.getElementById('aadhar_number').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').substring(0, 12);
        });
        
        document.getElementById('uan_number').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').substring(0, 12);
        });
        
        document.getElementById('esic_number').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').substring(0, 17);
        });
        
        // Format PAN number
        document.getElementById('pan_number').addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 10);
        });
    </script>
</body>
</html>