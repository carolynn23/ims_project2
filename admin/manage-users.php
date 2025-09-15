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

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_internship'])) {
    try {
        // Validate required fields
        $required_fields = ['employer_id', 'title', 'description', 'requirements', 'duration', 'location', 'deadline'];
        $errors = [];
        
        foreach ($required_fields as $field) {
            if (empty(trim($_POST[$field]))) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required";
            }
        }
        
        // Validate deadline is in the future
        if (!empty($_POST['deadline']) && strtotime($_POST['deadline']) <= time()) {
            $errors[] = "Application deadline must be in the future";
        }
        
        if (empty($errors)) {
            $employer_id = (int)$_POST['employer_id'];
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $requirements = trim($_POST['requirements']);
            $duration = trim($_POST['duration']);
            $location = trim($_POST['location']);
            $deadline = $_POST['deadline'];
            $salary = isset($_POST['salary']) ? trim($_POST['salary']) : '';
            $type = isset($_POST['type']) ? trim($_POST['type']) : 'internship';
            $status = isset($_POST['status']) ? trim($_POST['status']) : 'active';
            
            // Build dynamic query based on available columns
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
                $message = "Internship posted successfully! Internship ID: " . $internship_id;
                $message_type = "success";
                
                // Clear form data after successful submission
                $_POST = [];
            } else {
                $message = "Error posting internship: " . $stmt->error;
                $message_type = "error";
            }
            
        } else {
            $message = "Please fix the following errors: " . implode(", ", $errors);
            $message_type = "error";
        }
        
    } catch (Exception $e) {
        $message = "Database error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Get employers for dropdown
$employers = [];
if ($employers_exist) {
    try {
        $employers_query = "SELECT employer_id, company_name, contact_person FROM employers ORDER BY company_name";
        $employers_result = $conn->query($employers_query);
        if ($employers_result) {
            while ($row = $employers_result->fetch_assoc()) {
                $employers[] = $row;
            }
        }
    } catch (Exception $e) {
        // Continue without employers list
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

        .input-group-text {
            background: var(--hover-bg);
            border: 2px solid var(--border-color);
            border-right: none;
            color: var(--text-primary);
        }

        .input-group .form-control {
            border-left: none;
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
        }

        .validation-feedback {
            display: block;
            color: var(--success-color);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .validation-feedback.invalid {
            color: var(--danger-color);
        }

        .form-floating {
            position: relative;
        }

        .form-floating label {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            padding: 1rem 0.75rem;
            pointer-events: none;
            border: 1px solid transparent;
            transform-origin: 0 0;
            transition: opacity .1s ease-in-out,transform .1s ease-in-out;
            color: var(--text-secondary);
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
                <p class="mb-0">Create and publish internship opportunities on behalf of companies</p>
            </div>
        </div>

        <!-- Feature Status Alert -->
        <div class="feature-status">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Database Features Available:</strong>
            Salary Field: <?= $internships_has_salary ? '✅ Enabled' : '❌ Not Available' ?> • 
            Type Field: <?= $internships_has_type ? '✅ Enabled' : '❌ Not Available' ?> • 
            Status Field: <?= $internships_has_status ? '✅ Enabled' : '❌ Not Available' ?>
        </div>

        <!-- Message Alert -->
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                <i class="bi bi-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($employers)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>No employers found!</strong> You need to have registered employers before posting internships. 
                Please ensure companies are registered in the system first.
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Main Form -->
            <div class="col-lg-8">
                <div class="form-card">
                    <div class="form-header">
                        <h5>
                            <i class="bi bi-briefcase me-2"></i>
                            Internship Details
                        </h5>
                    </div>

                    <form method="POST" action="" id="internshipForm" novalidate>
                        <!-- Company Selection Section -->
                        <div class="form-section">
                            <div class="section-title">
                                <i class="bi bi-building"></i>
                                Company Information
                            </div>
                            
                            <div class="form-group">
                                <label for="employer_id" class="form-label required">Select Company</label>
                                <select name="employer_id" id="employer_id" class="form-select" required>
                                    <option value="">Choose a company...</option>
                                    <?php foreach ($employers as $employer): ?>
                                        <option value="<?= $employer['employer_id'] ?>" 
                                                <?= (isset($_POST['employer_id']) && $_POST['employer_id'] == $employer['employer_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($employer['company_name']) ?>
                                            <?php if (!empty($employer['contact_person'])): ?>
                                                - <?= htmlspecialchars($employer['contact_person']) ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select the company that will host this internship</div>
                            </div>
                        </div>

                        <!-- Basic Information Section -->
                        <div class="form-section">
                            <div class="section-title">
                                <i class="bi bi-info-circle"></i>
                                Basic Information
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
                        </div>

                        <!-- Detailed Information Section -->
                        <div class="form-section">
                            <div class="section-title">
                                <i class="bi bi-file-text"></i>
                                Detailed Information
                            </div>

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
                                <button type="submit" name="create_internship" class="btn btn-primary" <?= empty($employers) ? 'disabled' : '' ?>>
                                    <i class="bi bi-check-circle me-2"></i>Post Internship
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Preview Section -->
                    <div class="preview-section" id="previewSection">
                        <h5 class="mb-3">
                            <i class="bi bi-eye me-2"></i>Internship Preview
                        </h5>
                        <div class="preview-content" id="previewContent">
                            <!-- Preview content will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Tips Card -->
                <div class="recent-internships">
                    <h6 class="mb-3">
                        <i class="bi bi-lightbulb me-2"></i>Tips for Great Internship Posts
                    </h6>
                    <div class="internship-item">
                        <div class="internship-title">Be Specific</div>
                        <div class="internship-meta">Use clear, descriptive titles and detailed job descriptions</div>
                    </div>
                    <div class="internship-item">
                        <div class="internship-title">Set Clear Expectations</div>
                        <div class="internship-meta">Define responsibilities, learning objectives, and requirements</div>
                    </div>
                    <div class="internship-item">
                        <div class="internship-title">Include Benefits</div>
                        <div class="internship-meta">Mention mentorship, training, networking, or career opportunities</div>
                    </div>
                    <div class="internship-item">
                        <div class="internship-title">Reasonable Deadlines</div>
                        <div class="internship-meta">Allow sufficient time for students to prepare applications</div>
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

        // Form validation
        document.getElementById('internshipForm').addEventListener('submit', function(e) {
            const requiredFields = ['employer_id', 'title', 'description', 'requirements', 'duration', 'location', 'deadline'];
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
            const previewContent = document.getElementById('previewContent');
            
            if (previewSection.style.display === 'none' || previewSection.style.display === '') {
                generatePreview();
                previewSection.style.display = 'block';
                document.querySelector('.btn-preview').innerHTML = '<i class="bi bi-eye-slash me-2"></i>Hide Preview';
            } else {
                previewSection.style.display = 'none';
                document.querySelector('.btn-preview').innerHTML = '<i class="bi bi-eye me-2"></i>Preview Posting';
            }
        }

        function generatePreview() {
            const form = document.getElementById('internshipForm');
            const formData = new FormData(form);
            
            const employerSelect = document.getElementById('employer_id');
            const companyName = employerSelect.options[employerSelect.selectedIndex].text;
            
            const preview = `
                <div class="internship-preview">
                    <h4 class="text-primary mb-3">${formData.get('title') || 'Internship Title'}</h4>
                    <div class="mb-3">
                        <span class="badge bg-primary me-2">${companyName || 'Company Name'}</span>
                        <span class="badge bg-secondary me-2">${formData.get('location') || 'Location'}</span>
                        <span class="badge bg-info">${formData.get('duration') || 'Duration'}</span>
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
                    
                    ${formData.get('type') ? `<div class="mb-2"><strong>Type:</strong> ${formData.get('type')}</div>` : ''}
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

        // Auto-save functionality (optional)
        let autoSaveTimeout;
        document.getElementById('internshipForm').addEventListener('input', function() {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(function() {
                // Could implement auto-save to localStorage here
                console.log('Form data auto-saved');
            }, 3000);
        });

        console.log('Internship posting page initialized');
        console.log('Available features:', {
            salary_field: <?= $internships_has_salary ? 'true' : 'false' ?>,
            type_field: <?= $internships_has_type ? 'true' : 'false' ?>,
            status_field: <?= $internships_has_status ? 'true' : 'false' ?>,
            employers_count: <?= count($employers) ?>
        });
    </script>
</body>
</html>