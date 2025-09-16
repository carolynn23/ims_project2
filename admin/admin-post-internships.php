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
    <title>Post Internship - InternHub Admin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
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
            --light-color: #fcfdfd;
            --dark-color: #233446;
            --text-primary: #566a7f;
            --text-secondary: #a8aaae;
            --text-muted: #c7c8cc;
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            color: var(--text-primary);
            line-height: 1.6;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            margin-top: 70px;
            padding: 1.5rem;
            min-height: calc(100vh - 70px);
            transition: var(--transition);
        }

        /* Welcome Header - Simple and Clean */
        .welcome-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            border-radius: var(--border-radius-lg);
            color: white;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .welcome-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0 0 0.25rem 0;
        }

        .welcome-header p {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0;
        }

        /* Section Cards - Matching Dashboard Style */
        .section-card {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
        }

        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }

        /* Form Styling - Clean and Simple */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            color: var(--text-primary);
            font-weight: 500;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            display: block;
        }

        .form-label.required::after {
            content: ' *';
            color: var(--danger-color);
        }

        .form-control, .form-select {
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 0.75rem;
            font-size: 0.875rem;
            transition: var(--transition);
            background-color: var(--card-bg);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(105, 108, 255, 0.1);
            outline: none;
        }

        .form-text {
            color: var(--text-secondary);
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }

        /* Buttons - Simple Design */
        .btn-primary {
            background: var(--primary-color);
            border: none;
            border-radius: var(--border-radius);
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--secondary-color);
            border: none;
            border-radius: var(--border-radius);
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .btn-outline-secondary {
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            background: transparent;
            border-radius: var(--border-radius);
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .btn-outline-secondary:hover {
            background: var(--hover-bg);
            color: var(--text-primary);
            border-color: var(--text-primary);
        }

        /* Alert Messages */
        .alert {
            border-radius: var(--border-radius);
            border: none;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .alert-success {
            background: rgba(113, 221, 55, 0.1);
            color: var(--success-color);
            border-left: 3px solid var(--success-color);
        }

        .alert-danger {
            background: rgba(255, 62, 29, 0.1);
            color: var(--danger-color);
            border-left: 3px solid var(--danger-color);
        }

        .alert-info {
            background: rgba(3, 195, 236, 0.1);
            color: var(--info-color);
            border-left: 3px solid var(--info-color);
        }

        /* Sidebar Info Cards */
        .info-card {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
            padding: 1.25rem;
        }

        .info-card h6 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .info-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .info-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .info-title {
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .info-text {
            font-size: 0.75rem;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        /* Character Counter */
        .character-count {
            text-align: right;
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        /* Input Group for Salary */
        .input-group-text {
            background: var(--hover-bg);
            border: 1px solid var(--border-color);
            border-right: none;
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        .input-group .form-control {
            border-left: none;
        }

        .input-group:focus-within .input-group-text {
            border-color: var(--primary-color);
        }

        /* Status Badge */
        .status-info {
            background: var(--info-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }

        @media (max-width: 768px) {
            .welcome-header {
                padding: 1.25rem;
            }

            .section-card {
                padding: 1.25rem;
            }
        }

        /* Validation States */
        .form-control.is-invalid {
            border-color: var(--danger-color);
        }

        .form-control.is-valid {
            border-color: var(--success-color);
        }

        /* Recent Items Simple List */
        .recent-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .recent-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.875rem;
        }

        .recent-list li:last-child {
            border-bottom: none;
        }

        .recent-title {
            font-weight: 500;
            color: var(--text-primary);
        }

        .recent-meta {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
    </style>
</head>

<body>
    <!-- Include Sidebar and Navbar (placeholder) -->
    <!-- <?php include '../includes/sidebar.php'; ?> -->
    <!-- <?php include '../includes/navbar.php'; ?> -->

    <div class="main-content">
        <!-- Page Header -->
        <div class="welcome-header">
            <h1><i class="bi bi-plus-circle me-2"></i>Post New Internship</h1>
            <p>Create company profiles and publish internship opportunities</p>
        </div>

        <!-- Feature Status -->
        <div class="status-info">
            <i class="bi bi-info-circle"></i>
            System Features: Salary ✓ • Type ✓ • Status ✓ • Auto Company Creation ✓
        </div>

        <!-- Success/Error Messages -->
        <div class="alert alert-success" style="display: none;" id="successAlert">
            <i class="bi bi-check-circle me-2"></i>
            <span>Internship posted successfully! Company profile created and login credentials generated.</span>
        </div>

        <div class="row">
            <!-- Main Form -->
            <div class="col-lg-8">
                <div class="section-card">
                    <div class="section-title">
                        <i class="bi bi-file-earmark-plus"></i>
                        Internship Posting Form
                    </div>

                    <form id="internshipForm">
                        <!-- Company Information -->
                        <h6 class="text-info mb-3"><i class="bi bi-building me-2"></i>Company Information</h6>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="company_name" class="form-label required">Company Name</label>
                                    <input type="text" class="form-control" id="company_name" 
                                           placeholder="Tech Innovations Inc." required>
                                    <div class="form-text">Full legal name of the company</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="industry" class="form-label required">Industry</label>
                                    <input type="text" class="form-control" id="industry" 
                                           placeholder="Technology, Healthcare, Finance" required>
                                    <div class="form-text">Primary business sector</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="contact_person" class="form-label required">Contact Person</label>
                                    <input type="text" class="form-control" id="contact_person" 
                                           placeholder="Sarah Johnson" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phone" class="form-label required">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" 
                                           placeholder="+1 (555) 123-4567" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email" class="form-label required">Email Address</label>
                                    <input type="email" class="form-control" id="email" 
                                           placeholder="contact@company.com" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="website" class="form-label">Website</label>
                                    <input type="url" class="form-control" id="website" 
                                           placeholder="https://www.company.com">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="address" class="form-label required">Company Address</label>
                            <input type="text" class="form-control" id="address" 
                                   placeholder="123 Business St, New York, NY 10001" required>
                        </div>

                        <div class="form-group">
                            <label for="company_profile" class="form-label">Company Description</label>
                            <textarea class="form-control" id="company_profile" rows="3" 
                                      placeholder="Brief description of the company..." maxlength="1000"></textarea>
                            <div class="character-count">
                                <span id="companyCount">0</span>/1000
                            </div>
                        </div>

                        <!-- Internship Information -->
                        <hr class="my-4">
                        <h6 class="text-primary mb-3"><i class="bi bi-briefcase me-2"></i>Internship Details</h6>

                        <div class="form-group">
                            <label for="title" class="form-label required">Internship Title</label>
                            <input type="text" class="form-control" id="title" 
                                   placeholder="Software Engineering Intern" maxlength="100" required>
                            <div class="character-count">
                                <span id="titleCount">0</span>/100
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="duration" class="form-label required">Duration</label>
                                    <input type="text" class="form-control" id="duration" 
                                           placeholder="3 months, Summer 2024" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="location" class="form-label required">Location</label>
                                    <input type="text" class="form-control" id="location" 
                                           placeholder="New York, NY; Remote" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="deadline" class="form-label required">Application Deadline</label>
                                    <input type="date" class="form-control" id="deadline" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="salary" class="form-label">Salary/Stipend</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="text" class="form-control" id="salary" 
                                               placeholder="2000/month, 15/hour">
                                    </div>
                                    <div class="form-text">Optional compensation details</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="type" class="form-label">Type</label>
                                    <select class="form-select" id="type">
                                        <option value="internship">Regular Internship</option>
                                        <option value="co-op">Co-operative Education</option>
                                        <option value="part-time">Part-time Position</option>
                                        <option value="summer">Summer Program</option>
                                        <option value="research">Research Internship</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status">
                                        <option value="active">Active</option>
                                        <option value="draft">Draft</option>
                                        <option value="closed">Closed</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description" class="form-label required">Job Description</label>
                            <textarea class="form-control" id="description" rows="6" 
                                      placeholder="Describe the internship role, responsibilities, and learning opportunities..." 
                                      maxlength="2000" required></textarea>
                            <div class="character-count">
                                <span id="descCount">0</span>/2000
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="requirements" class="form-label required">Requirements</label>
                            <textarea class="form-control" id="requirements" rows="4" 
                                      placeholder="List required skills, qualifications, and experience..." 
                                      maxlength="1500" required></textarea>
                            <div class="character-count">
                                <span id="reqCount">0</span>/1500
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <button type="button" class="btn btn-outline-secondary">
                                <i class="bi bi-eye me-2"></i>Preview
                            </button>
                            <div>
                                <a href="admin-dashboard.php" class="btn btn-secondary me-2">
                                    <i class="bi bi-arrow-left me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-2"></i>Create Internship
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Process Info -->
                <div class="info-card">
                    <h6><i class="bi bi-info-circle me-2"></i>What happens next?</h6>
                    <div class="info-item">
                        <div class="info-title">Company Profile Created</div>
                        <div class="info-text">System registers the company automatically</div>
                    </div>
                    <div class="info-item">
                        <div class="info-title">Login Credentials Generated</div>
                        <div class="info-text">Employer account created for future access</div>
                    </div>
                    <div class="info-item">
                        <div class="info-title">Internship Published</div>
                        <div class="info-text">Position becomes visible to students</div>
                    </div>
                </div>

                <!-- Guidelines -->
                <div class="info-card">
                    <h6><i class="bi bi-lightbulb me-2"></i>Best Practices</h6>
                    <div class="info-item">
                        <div class="info-title">Verify Company Details</div>
                        <div class="info-text">Double-check all information for accuracy</div>
                    </div>
                    <div class="info-item">
                        <div class="info-title">Clear Job Descriptions</div>
                        <div class="info-text">Detailed posts attract better candidates</div>
                    </div>
                    <div class="info-item">
                        <div class="info-title">Professional Email</div>
                        <div class="info-text">Use company domain for credibility</div>
                    </div>
                </div>

                <!-- Recent Posts -->
                <div class="info-card">
                    <h6><i class="bi bi-clock-history me-2"></i>Recently Posted</h6>
                    <ul class="recent-list">
                        <li>
                            <div class="recent-title">Software Development Intern</div>
                            <div class="recent-meta">Tech Corp • Mar 15, 2024</div>
                        </li>
                        <li>
                            <div class="recent-title">Marketing Assistant</div>
                            <div class="recent-meta">Brand Solutions • Mar 14, 2024</div>
                        </li>
                        <li>
                            <div class="recent-title">Data Analysis Intern</div>
                            <div class="recent-meta">Analytics Pro • Mar 13, 2024</div>
                        </li>
                    </ul>
                    <div class="mt-3">
                        <a href="#" class="btn btn-outline-secondary btn-sm w-100">View All</a>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="info-card">
                    <h6><i class="bi bi-lightning me-2"></i>Quick Actions</h6>
                    <div class="d-grid gap-2">
                        <a href="#" class="btn btn-outline-secondary btn-sm">Manage Users</a>
                        <a href="#" class="btn btn-outline-secondary btn-sm">View Applications</a>
                        <a href="#" class="btn btn-outline-secondary btn-sm">Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Character counting
        function setupCharacterCount(inputId, countId, maxLength) {
            const input = document.getElementById(inputId);
            const counter = document.getElementById(countId);
            
            if (input && counter) {
                input.addEventListener('input', function() {
                    const length = this.value.length;
                    counter.textContent = length;
                    
                    if (length > maxLength * 0.9) {
                        counter.style.color = 'var(--warning-color)';
                    } else if (length >= maxLength) {
                        counter.style.color = 'var(--danger-color)';
                    } else {
                        counter.style.color = 'var(--text-secondary)';
                    }
                });
            }
        }

        // Initialize character counters
        setupCharacterCount('title', 'titleCount', 100);
        setupCharacterCount('description', 'descCount', 2000);
        setupCharacterCount('requirements', 'reqCount', 1500);
        setupCharacterCount('company_profile', 'companyCount', 1000);

        // Form validation and submission
        document.getElementById('internshipForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Simple validation
            const requiredFields = ['company_name', 'industry', 'contact_person', 'phone', 'email', 'address', 'title', 'description', 'requirements', 'duration', 'location', 'deadline'];
            let isValid = true;
            
            requiredFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                    field.classList.add('is-valid');
                }
            });
            
            if (isValid) {
                document.getElementById('successAlert').style.display = 'block';
                window.scrollTo({top: 0, behavior: 'smooth'});
            }
        });

        // Set default deadline to next week
        document.addEventListener('DOMContentLoaded', function() {
            const deadline = document.getElementById('deadline');
            const nextWeek = new Date();
            nextWeek.setDate(nextWeek.getDate() + 7);
            deadline.value = nextWeek.toISOString().split('T')[0];
        });

        // Real-time validation feedback
        document.querySelectorAll('.form-control, .form-select').forEach(input => {
            input.addEventListener('blur', function() {
                if (this.hasAttribute('required') && !this.value.trim()) {
                    this.classList.add('is-invalid');
                } else if (this.value.trim()) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });
        });

        console.log('Post Internship form initialized');
    </script>
</body>
</html>