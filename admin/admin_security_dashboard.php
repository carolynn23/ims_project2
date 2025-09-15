<?php
session_start();
require_once '../config.php';

// Check admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../secure_login.php");
    exit();
}

// Function to check if column exists in table
function columnExists($conn, $table, $column) {
    try {
        $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $result && $result->num_rows > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Function to check if table exists
function tableExists($conn, $table) {
    try {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        return $result && $result->num_rows > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Check database structure
$internships_has_salary = columnExists($conn, 'internships', 'salary');
$internships_has_type = columnExists($conn, 'internships', 'type');
$internships_has_status = columnExists($conn, 'internships', 'status');
$employers_exist = tableExists($conn, 'employers');
$users_exist = tableExists($conn, 'users');

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_internship'])) {
    try {
        // Validate required fields
        $required_fields = [
            'company_name', 'industry', 'contact_person', 'phone', 'email',
            'title', 'description', 'requirements', 'duration', 'location', 'deadline'
        ];
        $errors = [];
        
        foreach ($required_fields as $field) {
            if (empty(trim($_POST[$field]))) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required";
            }
        }
        
        // Validate email format
        if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address";
        }
        
        // Validate deadline is in the future
        if (!empty($_POST['deadline']) && strtotime($_POST['deadline']) <= time()) {
            $errors[] = "Application deadline must be in the future";
        }
        
        if (empty($errors)) {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Company information
                $company_name = trim($_POST['company_name']);
                $industry = trim($_POST['industry']);
                $company_profile = trim($_POST['company_profile']);
                $contact_person = trim($_POST['contact_person']);
                $phone = trim($_POST['phone']);
                $address = trim($_POST['address']);
                $website = trim($_POST['website']);
                $email = trim($_POST['email']);
                
                // Check if company already exists
                $check_company = $conn->prepare("SELECT employer_id FROM employers WHERE company_name = ? AND contact_person = ?");
                $check_company->bind_param("ss", $company_name, $contact_person);
                $check_company->execute();
                $existing_company = $check_company->get_result()->fetch_assoc();
                
                if ($existing_company) {
                    $employer_id = $existing_company['employer_id'];
                    $company_action = "Used existing company";
                } else {
                    // Create user account for the company (optional, for future employer login)
                    $user_id = null;
                    if ($users_exist) {
                        // Generate a temporary password
                        $temp_password = bin2hex(random_bytes(8));
                        $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);
                        $username = strtolower(str_replace(' ', '_', $company_name)) . '_' . rand(1000, 9999);
                        
                        // Check if username already exists
                        $check_username = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
                        $check_username->bind_param("s", $username);
                        $check_username->execute();
                        if ($check_username->get_result()->num_rows > 0) {
                            $username .= '_' . rand(10000, 99999);
                        }
                        
                        $insert_user = $conn->prepare("INSERT INTO users (username, email, password_hash, role, status) VALUES (?, ?, ?, 'employer', 'active')");
                        $insert_user->bind_param("sss", $username, $email, $password_hash);
                        
                        if ($insert_user->execute()) {
                            $user_id = $insert_user->insert_id;
                        }
                    }
                    
                    // Insert new company
                    if ($user_id) {
                        $insert_employer = $conn->prepare("INSERT INTO employers (user_id, company_name, industry, company_profile, contact_person, phone, address, website) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $insert_employer->bind_param("isssssss", $user_id, $company_name, $industry, $company_profile, $contact_person, $phone, $address, $website);
                    } else {
                        $insert_employer = $conn->prepare("INSERT INTO employers (company_name, industry, company_profile, contact_person, phone, address, website) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $insert_employer->bind_param("sssssss", $company_name, $industry, $company_profile, $contact_person, $phone, $address, $website);
                    }
                    
                    if ($insert_employer->execute()) {
                        $employer_id = $insert_employer->insert_id;
                        $company_action = "Created new company";
                    } else {
                        throw new Exception("Failed to create company: " . $insert_employer->error);
                    }
                }
                
                // Internship information
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $requirements = trim($_POST['requirements']);
                $duration = trim($_POST['duration']);
                $location = trim($_POST['location']);
                $deadline = $_POST['deadline'];
                $salary = isset($_POST['salary']) ? trim($_POST['salary']) : '';
                $type = isset($_POST['type']) ? trim($_POST['type']) : 'internship';
                $status = isset($_POST['status']) ? trim($_POST['status']) : 'active';
                
                // Build dynamic internship query based on available columns
                $columns = ['employer_id', 'title', 'description', 'requirements', 'duration', 'location', 'deadline'];
                $values = [$employer_id, $title, $description, $requirements, $duration, $location, $deadline];
                $placeholders = ['?', '?', '?', '?', '?', '?', '?'];
                $types = 'issssss';
                
                if ($internships_has_salary && !empty($salary)) {
                    $columns[] = 'salary';
                    $values[] = $salary;
                    $placeholders[] = '?';
                    $types .= 's';
                }
                
                if ($internships_has_type) {
                    $columns[] = 'type';
                    $values[] = $type;
                    $placeholders[] = '?';
                    $types .= 's';
                }
                
                if ($internships_has_status) {
                    $columns[] = 'status';
                    $values[] = $status;
                    $placeholders[] = '?';
                    $types .= 's';
                }
                
                $sql = "INSERT INTO internships (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$values);
                
                if ($stmt->execute()) {
                    $internship_id = $stmt->insert_id;
                    
                    // Commit transaction
                    $conn->commit();
                    
                    $message = "Internship posted successfully! $company_action. Internship ID: $internship_id";
                    if (isset($temp_password)) {
                        $message .= " | Company login - Username: $username, Password: $temp_password";
                    }
                    $message_type = "success";
                    
                    // Clear form data after successful submission
                    $_POST = [];
                } else {
                    throw new Exception("Failed to create internship: " . $stmt->error);
                }
                
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            
        } else {
            $message = "Please fix the following errors: " . implode(", ", $errors);
            $message_type = "error";
        }
        
    } catch (Exception $e) {
        if ($conn->in_transaction) {
            $conn->rollback();
        }
        $message = "Database error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Get recent internships for reference
$recent_internships = [];
try {
    $recent_query = "
        SELECT 
            i.internship_id,
            i.title,
            i.created_at,
            e.company_name
        FROM internships i
        LEFT JOIN employers e ON i.employer_id = e.employer_id
        ORDER BY i.created_at DESC
        LIMIT 5
    ";
    $recent_result = $conn->query($recent_query);
    if ($recent_result) {
        while ($row = $recent_result->fetch_assoc()) {
            $recent_internships[] = $row;
        }
    }
} catch (Exception $e) {
    // Continue without recent internships
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Internship - Admin Panel</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #696cff;
            --primary-light: #7367f0;
            --secondary-color: #8592a3;
            --success-color: #71dd37;
            --warning-color: #ffb400;
            --danger-color: #ff3e1d;
            --info-color: #03c3ec;
            --text-primary: #566a7f;
            --text-secondary: #a8aaae;
            --border-color: #e4e6e8;
            --card-bg: #fff;
            --hover-bg: #f8f9fa;
            --shadow-sm: 0 2px 6px 0 rgba(67, 89, 113, 0.12);
            --shadow-md: 0 4px 8px -4px rgba(67, 89, 113, 0.1);
            --shadow-lg: 0 6px 14px 0 rgba(67, 89, 113, 0.15);
            --border-radius: 8px;
            --border-radius-lg: 12px;
            --transition: all 0.2s ease-in-out;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f5f5f9;
            margin: 0;
            padding: 0;
        }

        .main-content {
            margin-left: 260px;
            padding: 2rem;
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, var(--success-color) 0%, #66c732 100%);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-15px) rotate(180deg); }
        }

        .form-card {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .form-header {
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }

        .form-header h5 {
            color: var(--text-primary);
            font-weight: 600;
            margin: 0;
        }

        .form-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: var(--hover-bg);
            border-radius: var(--border-radius);
        }

        .form-section.company {
            border-left: 4px solid var(--info-color);
        }

        .form-section.internship {
            border-left: 4px solid var(--primary-color);
        }

        .section-title {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 0.5rem;
        }

        .section-title.company i {
            color: var(--info-color);
        }

        .section-title.internship i {
            color: var(--primary-color);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-label.required::after {
            content: ' *';
            color: var(--danger-color);
        }

        .form-control, .form-select {
            border-radius: var(--border-radius);
            border: 2px solid var(--border-color);
            padding: 0.75rem 1rem;
            transition: var(--transition);
            font-size: 0.95rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(105, 108, 255, 0.25);
            outline: none;
        }

        .form-control.is-invalid {
            border-color: var(--danger-color);
        }

        .form-control.is-valid {
            border-color: var(--success-color);
        }

        .form-text {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .character-count {
            text-align: right;
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            border: none;
            border-radius: var(--border-radius);
            padding: 0.875rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-color) 100%);
        }

        .btn-secondary {
            background: var(--secondary-color);
            border: none;
            border-radius: var(--border-radius);
            padding: 0.875rem 2rem;
            font-weight: 600;
            transition: var(--transition);
        }

        .alert {
            border-radius: var(--border-radius);
            border: none;
            margin-bottom: 2rem;
            padding: 1rem 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(113, 221, 55, 0.1);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background: rgba(255, 62, 29, 0.1);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        .alert-warning {
            background: rgba(255, 180, 0, 0.1);
            color: var(--warning-color);
            border-left: 4px solid var(--warning-color);
        }

        .recent-internships {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
        }

        .internship-item {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .internship-item:last-child {
            border-bottom: none;
        }

        .internship-item:hover {
            background: var(--hover-bg);
            border-radius: var(--border-radius);
        }

        .internship-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .internship-meta {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .preview-section {
            background: var(--hover-bg);
            border: 2px dashed var(--border-color);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-top: 2rem;
            display: none;
        }

        .preview-content {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            border: 1px solid var(--border-color);
        }

        .feature-status {
            background: var(--info-color);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            font-size: 0.9rem;
        }

        .salary-input {
            position: relative;
        }

        .salary-symbol {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-weight: 600;
            z-index: 10;
        }

        .salary-symbol + .form-control {
            padding-left: 2rem;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }

        .step {
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
            margin: 0 0.5rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: var(--transition);
        }

        .step.active {
            background: var(--primary-color);
            color: white;
        }

        .step.completed {
            background: var(--success-color);
            color: white;
        }

        .step.inactive {
            background: var(--hover-bg);
            color: var(--text-secondary);
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .form-card {
                padding: 1.5rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .step-indicator {
                flex-direction: column;
                align-items: center;
            }

            .step {
                margin: 0.25rem 0;
                width: 200px;
                justify-content: center;
            }
        }

        .btn-preview {
            background: var(--info-color);
            border: none;
            color: white;
            border-radius: var(--border-radius);
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .btn-preview:hover {
            background: #029bc5;
            color: white;
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/navbar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="position: relative; z-index: 2;">
                <h1 class="mb-2">
                    <i class="bi bi-plus-circle me-3"></i>Post New Internship
                </h1>
                <p class="mb-0">Create company profiles and publish internship opportunities in one streamlined process</p>
            </div>
        </div>

        <!-- Feature Status Alert -->
        <div class="feature-status">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Available Features:</strong>
            <?php if ($internships_has_salary): ?>Salary Field ✅<?php else: ?>Salary Field ❌<?php endif; ?> • 
            <?php if ($internships_has_type): ?>Type Field ✅<?php else: ?>Type Field ❌<?php endif; ?> • 
            <?php if ($internships_has_status): ?>Status Field ✅<?php else: ?>Status Field ❌<?php endif; ?> • 
            Auto User Creation ✅
        </div>

        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step active">
                <i class="bi bi-building me-2"></i>Company Details
            </div>
            <div class="step active">
                <i class="bi bi-briefcase me-2"></i>Internship Details
            </div>
            <div class="step inactive">
                <i class="bi bi-check-circle me-2"></i>Review & Post
            </div>
        </div>

        <!-- Message Alert -->
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                <i class="bi bi-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Main Form -->
            <div class="col-lg-8">
                <div class="form-card">
                    <div class="form-header">
                        <h5>
                            <i class="bi bi-file-earmark-plus me-2"></i>
                            Complete Internship Posting Form
                        </h5>
                    </div>

                    <form method="POST" action="" id="internshipForm" novalidate>
                        <!-- Company Information Section -->
                        <div class="form-section company">
                            <div class="section-title company">
                                <i class="bi bi-building"></i>
                                Company Information
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="company_name" class="form-label required">Company Name</label>
                                        <input type="text" 
                                               name="company_name" 
                                               id="company_name" 
                                               class="form-control" 
                                               placeholder="e.g., Tech Innovations Inc."
                                               value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>"
                                               required>
                                        <div class="form-text">Full legal name of the company</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="industry" class="form-label required">Industry</label>
                                        <input type="text" 
                                               name="industry" 
                                               id="industry" 
                                               class="form-control" 
                                               placeholder="e.g., Technology, Healthcare, Finance"
                                               value="<?= htmlspecialchars($_POST['industry'] ?? '') ?>"
                                               required>
                                        <div class="form-text">Primary business sector</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="contact_person" class="form-label required">Contact Person</label>
                                        <input type="text" 
                                               name="contact_person" 
                                               id="contact_person" 
                                               class="form-control" 
                                               placeholder="e.g., Sarah Johnson"
                                               value="<?= htmlspecialchars($_POST['contact_person'] ?? '') ?>"
                                               required>
                                        <div class="form-text">Primary contact for internship inquiries</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="phone" class="form-label required">Phone Number</label>
                                        <input type="tel" 
                                               name="phone" 
                                               id="phone" 
                                               class="form-control" 
                                               placeholder="e.g., +1 (555) 123-4567"
                                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                                               required>
                                        <div class="form-text">Business contact number</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email" class="form-label required">Email Address</label>
                                        <input type="email" 
                                               name="email" 
                                               id="email" 
                                               class="form-control" 
                                               placeholder="e.g., contact@techcompany.com"
                                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                               required>
                                        <div class="form-text">Primary business email</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="website" class="form-label">Website (Optional)</label>
                                        <input type="url" 
                                               name="website" 
                                               id="website" 
                                               class="form-control" 
                                               placeholder="e.g., https://www.company.com"
                                               value="<?= htmlspecialchars($_POST['website'] ?? '') ?>">
                                        <div class="form-text">Company website URL</div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="address" class="form-label required">Company Address</label>
                                <input type="text" 
                                       name="address" 
                                       id="address" 
                                       class="form-control" 
                                       placeholder="e.g., 123 Business St, New York, NY 10001"
                                       value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
                                       required>
                                <div class="form-text">Complete business address</div>
                            </div>

                            <div class="form-group">
                                <label for="company_profile" class="form-label">Company Profile (Optional)</label>
                                <textarea name="company_profile" 
                                          id="company_profile" 
                                          class="form-control" 
                                          rows="3" 
                                          placeholder="Brief description of the company, mission, values, and what makes it a great place to work..."
                                          maxlength="1000"><?= htmlspecialchars($_POST['company_profile'] ?? '') ?></textarea>
                                <div class="character-count">
                                    <span id="companyProfileCount">0</span>/1000 characters
                                </div>
                            </div>
                        </div>

                        <!-- Internship Information Section -->
                        <div class="form-section internship">
                            <div class="section-title internship">
                                <i class="bi bi-briefcase"></i>
                                Internship Details
                            </div>

                            <div class="form-group">
                                <label for="title" class="form-label required">Internship Title</label>
                                <input type="text" 
                                       name="title" 
                                       id="title" 
                                       class="form-control" 
                                       placeholder="e.g., Software Engineering Intern, Marketing Assistant, Data Analyst Intern"
                                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                                       maxlength="100"
                                       required>
                                <div class="character-count">
                                    <span id="titleCount">0</span>/100 characters
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="duration" class="form-label required">Duration</label>
                                        <input type="text" 
                                               name="duration" 
                                               id="duration" 
                                               class="form-control" 
                                               placeholder="e.g., 3 months, Summer 2024, 6-month program"
                                               value="<?= htmlspecialchars($_POST['duration'] ?? '') ?>"
                                               required>
                                        <div class="form-text">How long is the internship program?</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="location" class="form-label required">Location</label>
                                        <input type="text" 
                                               name="location" 
                                               id="location" 
                                               class="form-control" 
                                               placeholder="e.g., New York, NY; Remote; Hybrid - San Francisco"
                                               value="<?= htmlspecialchars($_POST['location'] ?? '') ?>"
                                               required>
                                        <div class="form-text">Where will the intern work?</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="deadline" class="form-label required">Application Deadline</label>
                                        <input type="date" 
                                               name="deadline" 
                                               id="deadline" 
                                               class="form-control" 
                                               value="<?= $_POST['deadline'] ?? '' ?>"
                                               min="<?= date('Y-m-d') ?>"
                                               required>
                                        <div class="form-text">Last date to accept applications</div>
                                    </div>
                                </div>
                                <?php if ($internships_has_salary): ?>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="salary" class="form-label">Salary/Stipend (Optional)</label>
                                        <div class="salary-input">
                                            <span class="salary-symbol">$</span>
                                            <input type="text" 
                                                   name="salary" 
                                                   id="salary" 
                                                   class="form-control" 
                                                   placeholder="2000/month, 15/hour, 5000 total"
                                                   value="<?= htmlspecialchars($_POST['salary'] ?? '') ?>">
                                        </div>
                                        <div class="form-text">Compensation details (leave blank if unpaid)</div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($internships_has_type): ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="type" class="form-label">Internship Type</label>
                                        <select name="type" id="type" class="form-select">
                                            <option value="internship" <?= (($_POST['type'] ?? '') === 'internship') ? 'selected' : '' ?>>Regular Internship</option>
                                            <option value="co-op" <?= (($_POST['type'] ?? '') === 'co-op') ? 'selected' : '' ?>>Co-operative Education</option>
                                            <option value="part-time" <?= (($_POST['type'] ?? '') === 'part-time') ? 'selected' : '' ?>>Part-time Position</option>
                                            <option value="summer" <?= (($_POST['type'] ?? '') === 'summer') ? 'selected' : '' ?>>Summer Program</option>
                                            <option value="research" <?= (($_POST['type'] ?? '') === 'research') ? 'selected' : '' ?>>Research Internship</option>
                                        </select>
                                    </div>
                                </div>
                                <?php if ($internships_has_status): ?>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="status" class="form-label">Status</label>
                                        <select name="status" id="status" class="form-select">
                                            <option value="active" <?= (($_POST['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Active - Accepting Applications</option>
                                            <option value="draft" <?= (($_POST['status'] ?? '') === 'draft') ? 'selected' : '' ?>>Draft - Not Published</option>
                                            <option value="closed" <?= (($_POST['status'] ?? '') === 'closed') ? 'selected' : '' ?>>Closed - No More Applications</option>
                                        </select>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <div class="form-group">
                                <label for="description" class="form-label required">Job Description</label>
                                <textarea name="description" 
                                          id="description" 
                                          class="form-control" 
                                          rows="6" 
                                          placeholder="Describe the internship role, responsibilities, what the intern will learn, projects they'll work on, team structure, mentorship opportunities, and company culture..."
                                          maxlength="2000"
                                          required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                <div class="character-count">
                                    <span id="descriptionCount">0</span>/2000 characters
                                </div>
                                <div class="form-text">Provide a comprehensive overview of the internship opportunity</div>
                            </div>

                            <div class="form-group">
                                <label for="requirements" class="form-label required">Requirements & Qualifications</label>
                                <textarea name="requirements" 
                                          id="requirements" 
                                          class="form-control" 
                                          rows="4" 
                                          placeholder="List required skills, qualifications, education level, technical skills, soft skills, previous experience, certifications, or any other prerequisites..."
                                          maxlength="1500"
                                          required><?= htmlspecialchars($_POST['requirements'] ?? '') ?></textarea>
                                <div class="character-count">
                                    <span id="requirementsCount">0</span>/1500 characters
                                </div>
                                <div class="form-text">Specify what qualifications candidates should have</div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <div>
                                <button type="button" class="btn btn-preview" onclick="togglePreview()">
                                    <i class="bi bi-eye me-2"></i>Preview Posting
                                </button>
                            </div>
                            <div>
                                <a href="admin-dashboard.php" class="btn btn-secondary me-3">
                                    <i class="bi bi-arrow-left me-2"></i>Cancel
                                </a>
                                <button type="submit" name="create_internship" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-2"></i>Create Company & Post Internship
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Preview Section -->
                    <div class="preview-section" id="previewSection">
                        <h5 class="mb-3">
                            <i class="bi bi-eye me-2"></i>Complete Posting Preview
                        </h5>
                        <div class="preview-content" id="previewContent">
                            <!-- Preview content will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Process Info Card -->
                <div class="recent-internships">
                    <h6 class="mb-3">
                        <i class="bi bi-info-circle me-2"></i>What This Process Does
                    </h6>
                    <div class="internship-item">
                        <div class="internship-title">Creates Company Profile</div>
                        <div class="internship-meta">Automatically registers the company in the system</div>
                    </div>
                    <div class="internship-item">
                        <div class="internship-title">Generates Login Credentials</div>
                        <div class="internship-meta">Creates employer account for future access</div>
                    </div>
                    <div class="internship-item">
                        <div class="internship-title">Posts Internship</div>
                        <div class="internship-meta">Makes the position visible to students</div>
                    </div>
                    <div class="internship-item">
                        <div class="internship-title">Prevents Duplicates</div>
                        <div class="internship-meta">Checks for existing companies before creating new ones</div>
                    </div>
                </div>

                <!-- Tips Card -->
                <div class="recent-internships mt-4">
                    <h6 class="mb-3">
                        <i class="bi bi-lightbulb me-2"></i>Best Practices
                    </h6>
                    <div class="internship-item">
                        <div class="internship-title">Verify Company Details</div>
                        <div class="internship-meta">Ensure all company information is accurate and up-to-date</div>
                    </div>
                    <div class="internship-item">
                        <div class="internship-title">Use Professional Email</div>
                        <div class="internship-meta">Company domain email builds trust with students</div>
                    </div>
                    <div class="internship-item">
                        <div class="internship-title">Clear Job Descriptions</div>
                        <div class="internship-meta">Detailed descriptions attract better candidates</div>
                    </div>
                    <div class="internship-item">
                        <div class="internship-title">Realistic Deadlines</div>
                        <div class="internship-meta">Give students adequate time to prepare applications</div>
                    </div>
                </div>

                <!-- Recent Internships -->
                <?php if (!empty($recent_internships)): ?>
                <div class="recent-internships mt-4">
                    <h6 class="mb-3">
                        <i class="bi bi-clock-history me-2"></i>Recently Posted
                    </h6>
                    <?php foreach ($recent_internships as $internship): ?>
                        <div class="internship-item">
                            <div class="internship-title"><?= htmlspecialchars($internship['title']) ?></div>
                            <div class="internship-meta">
                                <?= htmlspecialchars($internship['company_name']) ?> • 
                                <?= date('M j, Y', strtotime($internship['created_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="mt-3">
                        <a href="view-all-internships.php" class="btn btn-outline-primary btn-sm w-100">
                            <i class="bi bi-list me-2"></i>View All Internships
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="recent-internships mt-4">
                    <h6 class="mb-3">
                        <i class="bi bi-lightning me-2"></i>Quick Actions
                    </h6>
                    <div class="d-grid gap-2">
                        <a href="manage-users.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-people me-2"></i>Manage Users
                        </a>
                        <a href="view-all-applications.php" class="btn btn-outline-info btn-sm">
                            <i class="bi bi-file-earmark-text me-2"></i>View Applications
                        </a>
                        <a href="admin-dashboard.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-speedometer2 me-2"></i>Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Character counting
        function setupCharacterCount(inputId, countId, maxLength) {
            const input = document.getElementById(inputId);
            const counter = document.getElementById(countId);
            
            if (input && counter) {
                function updateCount() {
                    const length = input.value.length;
                    counter.textContent = length;
                    
                    if (length > maxLength * 0.9) {
                        counter.style.color = 'var(--warning-color)';
                    } else if (length >= maxLength) {
                        counter.style.color = 'var(--danger-color)';
                    } else {
                        counter.style.color = 'var(--text-secondary)';
                    }
                }
                
                input.addEventListener('input', updateCount);
                updateCount(); // Initial count
            }
        }

        // Setup character counting for all text fields
        setupCharacterCount('title', 'titleCount', 100);
        setupCharacterCount('description', 'descriptionCount', 2000);
        setupCharacterCount('requirements', 'requirementsCount', 1500);
        setupCharacterCount('company_profile', 'companyProfileCount', 1000);

        // Form validation
        document.getElementById('internshipForm').addEventListener('submit', function(e) {
            const requiredFields = [
                'company_name', 'industry', 'contact_person', 'phone', 'email', 'address',
                'title', 'description', 'requirements', 'duration', 'location', 'deadline'
            ];
            let isValid = true;
            
            requiredFields.forEach(fieldName => {
                const field = document.getElementById(fieldName);
                if (field && !field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else if (field) {
                    field.classList.remove('is-invalid');
                    field.classList.add('is-valid');
                }
            });
            
            // Validate email format
            const email = document.getElementById('email');
            if (email && email.value) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email.value)) {
                    email.classList.add('is-invalid');
                    isValid = false;
                } else {
                    email.classList.remove('is-invalid');
                    email.classList.add('is-valid');
                }
            }
            
            // Validate deadline is in the future
            const deadline = document.getElementById('deadline');
            if (deadline && deadline.value) {
                const deadlineDate = new Date(deadline.value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                if (deadlineDate <= today) {
                    deadline.classList.add('is-invalid');
                    isValid = false;
                    alert('Application deadline must be in the future');
                } else {
                    deadline.classList.remove('is-invalid');
                    deadline.classList.add('is-valid');
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields correctly');
            }
        });

        // Preview functionality
        function togglePreview() {
            const previewSection = document.getElementById('previewSection');
            
            if (previewSection.style.display === 'none' || previewSection.style.display === '') {
                generatePreview();
                previewSection.style.display = 'block';
                document.querySelector('.btn-preview').innerHTML = '<i class="bi bi-eye-slash me-2"></i>Hide Preview';
                // Update step indicator
                document.querySelectorAll('.step')[2].classList.remove('inactive');
                document.querySelectorAll('.step')[2].classList.add('active');
            } else {
                previewSection.style.display = 'none';
                document.querySelector('.btn-preview').innerHTML = '<i class="bi bi-eye me-2"></i>Preview Posting';
                // Update step indicator
                document.querySelectorAll('.step')[2].classList.remove('active');
                document.querySelectorAll('.step')[2].classList.add('inactive');
            }
        }

        function generatePreview() {
            const form = document.getElementById('internshipForm');
            const formData = new FormData(form);
            
            const preview = `
                <div class="company-preview mb-4">
                    <h5 class="text-info mb-3"><i class="bi bi-building me-2"></i>Company Profile</h5>
                    <h4 class="text-primary">${formData.get('company_name') || 'Company Name'}</h4>
                    <p><strong>Industry:</strong> ${formData.get('industry') || 'Not specified'}</p>
                    <p><strong>Contact:</strong> ${formData.get('contact_person') || 'Not specified'} - ${formData.get('email') || 'Not specified'}</p>
                    <p><strong>Phone:</strong> ${formData.get('phone') || 'Not specified'}</p>
                    <p><strong>Address:</strong> ${formData.get('address') || 'Not specified'}</p>
                    ${formData.get('website') ? `<p><strong>Website:</strong> <a href="${formData.get('website')}" target="_blank">${formData.get('website')}</a></p>` : ''}
                    ${formData.get('company_profile') ? `<p><strong>About:</strong> ${formData.get('company_profile')}</p>` : ''}
                </div>
                
                <div class="internship-preview">
                    <h5 class="text-primary mb-3"><i class="bi bi-briefcase me-2"></i>Internship Details</h5>
                    <h4 class="text-dark mb-3">${formData.get('title') || 'Internship Title'}</h4>
                    <div class="mb-3">
                        <span class="badge bg-secondary me-2">${formData.get('location') || 'Location'}</span>
                        <span class="badge bg-info me-2">${formData.get('duration') || 'Duration'}</span>
                        ${formData.get('type') ? `<span class="badge bg-success">${formData.get('type')}</span>` : ''}
                    </div>
                    
                    ${formData.get('salary') ? `<div class="mb-3"><strong>Compensation:</strong> $${formData.get('salary')}</div>` : ''}
                    
                    <div class="mb-3">
                        <strong>Application Deadline:</strong> ${formData.get('deadline') ? new Date(formData.get('deadline')).toLocaleDateString() : 'Not set'}
                    </div>
                    
                    <div class="mb-4">
                        <h6>Description</h6>
                        <p style="white-space: pre-line;">${formData.get('description') || 'No description provided'}</p>
                    </div>
                    
                    <div class="mb-4">
                        <h6>Requirements</h6>
                        <p style="white-space: pre-line;">${formData.get('requirements') || 'No requirements specified'}</p>
                    </div>
                </div>
            `;
            
            document.getElementById('previewContent').innerHTML = preview;
        }

        // Set minimum date for deadline
        document.addEventListener('DOMContentLoaded', function() {
            const deadlineInput = document.getElementById('deadline');
            if (deadlineInput && !deadlineInput.value) {
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                deadlineInput.value = tomorrow.toISOString().split('T')[0];
            }
        });

        console.log('Enhanced internship posting page initialized');
        console.log('Features available:', {
            salary_field: <?= $internships_has_salary ? 'true' : 'false' ?>,
            type_field: <?= $internships_has_type ? 'true' : 'false' ?>,
            status_field: <?= $internships_has_status ? 'true' : 'false' ?>,
            auto_user_creation: true,
            company_creation: true
        });
    </script>
</body>
</html>