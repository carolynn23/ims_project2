<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') { 
    header('Location: login.php'); 
    exit(); 
}

$internship_id = (int)($_GET['internship_id'] ?? 0);
if ($internship_id <= 0) { 
    echo "Invalid internship."; 
    exit(); 
}

// Fetch student_id with better error handling
$user_id = (int)$_SESSION['user_id'];
$st = $conn->prepare("SELECT student_id, full_name FROM students WHERE user_id=?");
$st->bind_param("i", $user_id);
$st->execute();
$student = $st->get_result()->fetch_assoc();

if (!$student) { 
    echo "Student profile missing. Please complete your profile first."; 
    exit(); 
}

$student_id = (int)$student['student_id'];
$student_name = $student['full_name'] ?? 'Student';

// Fetch internship details
$ti = $conn->prepare("SELECT title, deadline FROM internships WHERE internship_id=? AND deadline >= CURDATE()");
$ti->bind_param("i", $internship_id);
$ti->execute();
$intern = $ti->get_result()->fetch_assoc();

if (!$intern) { 
    echo "Internship not found or application deadline has passed."; 
    exit(); 
}

$title = $intern['title'];
$deadline = $intern['deadline'];

$msg = "";
$msg_type = "";
$already_applied = false;
$applied_date = "";

// Check if student has already applied
$ck = $conn->prepare("SELECT application_id, applied_at, status FROM applications WHERE internship_id=? AND student_id=?");
$ck->bind_param("ii", $internship_id, $student_id);
$ck->execute();
$existing_application = $ck->get_result()->fetch_assoc();

if ($existing_application) {
    $already_applied = true;
    $applied_date = date('M j, Y \a\t g:i A', strtotime($existing_application['applied_at']));
    $application_status = $existing_application['status'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Double-check for existing application (prevent race conditions)
    $double_check = $conn->prepare("SELECT application_id FROM applications WHERE internship_id=? AND student_id=?");
    $double_check->bind_param("ii", $internship_id, $student_id);
    $double_check->execute();
    
    if ($double_check->get_result()->fetch_assoc()) {
        // Application exists, redirect with error
        header("Location: student-dashboard.php?error=already_applied&internship=" . urlencode($title));
        exit();
    }

    $cover = trim($_POST['cover_letter'] ?? '');
    
    // Cover letter is optional, so it can be empty
    {
        // Process file uploads
        $resume_files = [];
        $upload_errors = [];
        
        // Handle multiple file uploads
        if (!empty($_FILES['resume']['name'][0])) {
            $allowed = ['pdf', 'doc', 'docx'];
            $max_files = 3;
            $max_size = 5 * 1024 * 1024; // 5MB per file
            
            $file_count = count($_FILES['resume']['name']);
            if ($file_count > $max_files) {
                $upload_errors[] = "Maximum $max_files files allowed.";
            } else {
                // Create upload directory if it doesn't exist
                $upload_dir = __DIR__ . "/uploads/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                for ($i = 0; $i < $file_count; $i++) {
                    if ($_FILES['resume']['error'][$i] === UPLOAD_ERR_OK) {
                        $original_name = $_FILES['resume']['name'][$i];
                        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                        
                        if (!in_array($ext, $allowed)) {
                            $upload_errors[] = "File " . ($i + 1) . " must be PDF/DOC/DOCX.";
                        } elseif ($_FILES['resume']['size'][$i] > $max_size) {
                            $upload_errors[] = "File " . ($i + 1) . " must be ≤ 5MB.";
                        } else {
                            // Generate safe filename
                            $safe_filename = time() . '_' . $student_id . '_' . $i . '.' . $ext;
                            $target_path = $upload_dir . $safe_filename;
                            
                            if (move_uploaded_file($_FILES['resume']['tmp_name'][$i], $target_path)) {
                                $resume_files[] = $safe_filename;
                            } else {
                                $upload_errors[] = "Failed to upload file " . ($i + 1) . ".";
                            }
                        }
                    }
                }
            }
        }

        // Process application if no upload errors
        if (!empty($upload_errors)) {
            $msg = implode(" ", $upload_errors);
            $msg_type = "danger";
        } elseif (empty($resume_files)) {
            $msg = "Please upload at least one resume/CV file.";
            $msg_type = "warning";
        } else {
            // Use database transaction for atomicity
            $conn->begin_transaction();
            
            try {
                $resume_json = json_encode($resume_files);
                
                // Insert application with duplicate key handling
                $ins = $conn->prepare("
                    INSERT INTO applications (internship_id, student_id, resume, cover_letter, applied_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $ins->bind_param("iiss", $internship_id, $student_id, $resume_json, $cover);
                
                if (!$ins->execute()) {
                    // Check if it's a duplicate key error
                    if ($conn->errno == 1062) { // MySQL duplicate entry error
                        throw new Exception("duplicate_application");
                    } else {
                        throw new Exception("database_error: " . $conn->error);
                    }
                }
                
                $application_id = $conn->insert_id;
                
                // Notify employer
                $eu = $conn->prepare("
                    SELECT e.user_id, u.email as employer_email
                    FROM internships i 
                    JOIN employers e ON i.employer_id = e.employer_id 
                    JOIN users u ON e.user_id = u.user_id
                    WHERE i.internship_id = ?
                ");
                $eu->bind_param("i", $internship_id);
                $eu->execute();
                $employer_data = $eu->get_result()->fetch_assoc();
                
                if ($employer_data) {
                    $emp_user_id = (int)$employer_data['user_id'];
                    $notification_msg = "$student_name applied for '$title'.";
                    
                    // Insert notification
                    $nn = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                    $nn->bind_param("is", $emp_user_id, $notification_msg);
                    $nn->execute();
                    
                    // Optional: Send email notification
                    if (!empty($employer_data['employer_email'])) {
                        // Email notification code can go here
                        // require_once 'EmailNotificationManager.php';
                        // $emailManager = new EmailNotificationManager($conn);
                        // $emailManager->sendApplicationNotification($internship_id, $student_id);
                    }
                }
                
                $conn->commit();
                
                // Success - redirect to prevent form resubmission
                header("Location: student-dashboard.php?success=application_submitted&internship=" . urlencode($title));
                exit();
                
            } catch (Exception $e) {
                $conn->rollback();
                
                // Clean up uploaded files on failure
                foreach ($resume_files as $file) {
                    $file_path = $upload_dir . $file;
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
                
                if (strpos($e->getMessage(), 'duplicate_application') !== false) {
                    // Redirect with duplicate error
                    header("Location: student-dashboard.php?error=already_applied&internship=" . urlencode($title));
                    exit();
                } else {
                    $msg = "Error submitting application. Please try again.";
                    $msg_type = "danger";
                    error_log("Application submission error: " . $e->getMessage());
                }
            }
        }
    }
}

// If we reach here and already applied, show the error
if ($already_applied) {
    $status_text = ucfirst($application_status ?? 'unknown');
    $msg = "You have already applied for this internship on $applied_date. Current status: $status_text";
    $msg_type = "info";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for <?= htmlspecialchars($title) ?> - Student Portal</title>
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

        /* Main Content - Account for navbar height */
        .main-content {
            margin-left: 260px;
            margin-top: 70px; /* Account for fixed navbar */
            padding: 1rem;
            min-height: calc(100vh - 70px);
            transition: var(--transition);
            max-width: 800px;
            margin-left: calc(260px + (100vw - 260px - 800px) / 2);
        }

        /* Compact Header */
        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            border-radius: var(--border-radius);
            color: white;
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex: 1;
        }

        .header-icon {
            font-size: 1.5rem;
            opacity: 0.9;
        }

        .header-text h1 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            line-height: 1.3;
        }

        .header-text p {
            font-size: 0.875rem;
            opacity: 0.85;
            margin: 0;
            font-weight: 400;
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateX(-2px);
        }

        /* Internship Info Section */
        .internship-info {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .internship-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.75rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: var(--hover-bg);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }

        .info-icon {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            color: white;
            flex-shrink: 0;
        }

        .info-icon.primary { background: var(--primary-color); }
        .info-icon.warning { background: var(--warning-color); }

        .info-details h6 {
            margin: 0;
            font-size: 0.6875rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.125rem;
        }

        .info-details p {
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.8125rem;
        }

        .deadline-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.6875rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .deadline-badge.danger {
            background: rgba(255, 62, 29, 0.1);
            color: var(--danger-color);
        }

        .deadline-badge.warning {
            background: rgba(255, 180, 0, 0.1);
            color: var(--warning-color);
        }

        .deadline-badge.success {
            background: rgba(113, 221, 55, 0.1);
            color: var(--success-color);
        }

        /* Form Sections */
        .form-section {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-control, .form-select {
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 0.75rem;
            font-size: 0.875rem;
            transition: var(--transition);
            background-color: var(--light-color);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.1rem rgba(105, 108, 255, 0.15);
            background-color: white;
        }

        .form-text {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        /* File Upload */
        .file-upload-area {
            border: 2px dashed var(--border-color);
            border-radius: var(--border-radius);
            padding: 2rem;
            text-align: center;
            background: var(--hover-bg);
            transition: var(--transition);
            position: relative;
        }

        .file-upload-area:hover {
            border-color: var(--primary-color);
            background: rgba(105, 108, 255, 0.05);
        }

        .file-upload-area.dragover {
            border-color: var(--primary-color);
            background: rgba(105, 108, 255, 0.1);
        }

        .upload-icon {
            font-size: 2rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }

        .upload-text {
            color: var(--text-primary);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .upload-subtext {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .file-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        /* File List */
        .file-list {
            margin-top: 1rem;
        }

        .file-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: rgba(105, 108, 255, 0.1);
            border: 1px solid rgba(105, 108, 255, 0.3);
            border-radius: var(--border-radius);
            margin-bottom: 0.5rem;
            font-size: 0.8125rem;
        }

        .file-item:last-child {
            margin-bottom: 0;
        }

        /* Requirement Badges */
        .requirement-badges {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .req-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.6875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .req-badge.required {
            background: rgba(255, 62, 29, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(255, 62, 29, 0.3);
        }

        .req-badge.optional {
            background: rgba(3, 195, 236, 0.1);
            color: var(--info-color);
            border: 1px solid rgba(3, 195, 236, 0.3);
        }

        /* Buttons */
        .btn {
            border-radius: var(--border-radius);
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            font-size: 0.875rem;
            transition: var(--transition);
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 4px 12px rgba(105, 108, 255, 0.3);
        }

        .btn-primary:hover {
            background: var(--primary-light);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(105, 108, 255, 0.4);
        }

        .btn-outline-secondary {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        .btn-outline-secondary:hover {
            background: var(--hover-bg);
            border-color: var(--text-secondary);
            color: var(--text-primary);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #5cb85c;
            color: white;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
        }

        /* Alerts */
        .alert {
            border: none;
            border-radius: var(--border-radius);
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            font-weight: 500;
            font-size: 0.875rem;
            border-left: 3px solid;
        }

        .alert-success {
            background-color: rgba(113, 221, 55, 0.1);
            color: var(--success-color);
            border-left-color: var(--success-color);
        }

        .alert-danger {
            background-color: rgba(255, 62, 29, 0.1);
            color: var(--danger-color);
            border-left-color: var(--danger-color);
        }

        .alert-warning {
            background-color: rgba(255, 180, 0, 0.1);
            color: var(--warning-color);
            border-left-color: var(--warning-color);
        }

        .alert-info {
            background-color: rgba(3, 195, 236, 0.1);
            color: var(--info-color);
            border-left-color: var(--info-color);
        }

        /* Already Applied State */
        .application-complete {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            text-align: center;
        }

        .complete-icon {
            font-size: 4rem;
            color: var(--success-color);
            margin-bottom: 1rem;
        }

        .complete-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .complete-text {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .complete-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        /* Responsive Design */
        @media (max-width: 1000px) {
            .main-content {
                margin-left: 260px;
                max-width: none;
            }
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                margin-top: 70px;
                padding: 0.75rem;
            }

            .page-header {
                padding: 0.75rem 1rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
                width: 100%;
            }

            .info-grid {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .form-actions {
                flex-direction: column;
                gap: 1rem;
            }

            .complete-actions {
                flex-direction: column;
                align-items: center;
            }
        }

        @media (max-width: 768px) {
            .requirement-badges {
                justify-content: center;
            }

            .file-upload-area {
                padding: 1.5rem 1rem;
            }

            .upload-icon {
                font-size: 1.5rem;
            }
        }

        /* Loading States */
        .btn-loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            margin: auto;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>

<body>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="main-content">
    <!-- Compact Header -->
    <div class="page-header">
        <div class="header-content">
            <i class="bi bi-send header-icon"></i>
            <div class="header-text">
                <h1>Apply for Position</h1>
                <p>Submit your application for this internship</p>
            </div>
        </div>
        <a href="student-dashboard.php" class="back-btn">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <!-- Alert Messages -->
    <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?= $msg_type === 'success' ? 'check-circle-fill' : 
                ($msg_type === 'warning' ? 'exclamation-triangle-fill' : 
                ($msg_type === 'info' ? 'info-circle-fill' : 'x-circle-fill')) ?> me-2"></i>
            <?= $msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Internship Information -->
    <div class="internship-info">
        <div class="internship-title">
            <i class="bi bi-briefcase text-primary"></i>
            <?= htmlspecialchars($title) ?>
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <div class="info-icon primary">
                    <i class="bi bi-calendar-event"></i>
                </div>
                <div class="info-details">
                    <h6>Deadline</h6>
                    <p><?= date('M j, Y', strtotime($deadline)) ?></p>
                </div>
            </div>

            <div class="info-item">
                <div class="info-icon warning">
                    <i class="bi bi-clock"></i>
                </div>
                <div class="info-details">
                    <h6>Days Left</h6>
                    <?php 
                    $days_left = ceil((strtotime($deadline) - time()) / (60 * 60 * 24));
                    $badge_class = $days_left <= 3 ? 'danger' : ($days_left <= 7 ? 'warning' : 'success');
                    ?>
                    <p>
                        <?= $days_left ?> days
                        <span class="deadline-badge <?= $badge_class ?>">
                            <?= $days_left <= 3 ? 'Urgent' : ($days_left <= 7 ? 'Soon' : 'Good') ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$already_applied): ?>
    <!-- Application Form -->
    <form method="post" enctype="multipart/form-data" id="applicationForm">
        <!-- Requirements Section -->
        <div class="form-section">
            <div class="section-title">
                <i class="bi bi-list-check text-primary"></i>
                Application Requirements
            </div>
            
            <div class="requirement-badges">
                <span class="req-badge required">Resume/CV Required</span>
                <span class="req-badge optional">Cover Letter Optional</span>
            </div>
        </div>

        <!-- Cover Letter Section -->
        <div class="form-section">
            <div class="section-title">
                <i class="bi bi-file-text text-primary"></i>
                Cover Letter
            </div>
            
            <div class="mb-3">
                <label for="cover_letter" class="form-label">
                    Tell us why you're interested in this position
                    <small class="text-muted">(Optional but recommended)</small>
                </label>
                <textarea name="cover_letter" id="cover_letter" class="form-control" rows="6" 
                          placeholder="Explain why you're interested in this position and what you can bring to the role..."
                          maxlength="2000"></textarea>
                <div class="form-text">Maximum 2000 characters</div>
            </div>
        </div>

        <!-- Resume Upload Section -->
        <div class="form-section">
            <div class="section-title">
                <i class="bi bi-file-earmark-pdf text-primary"></i>
                Resume/CV Files
            </div>
            
            <div class="file-upload-area">
                <div class="upload-icon">
                    <i class="bi bi-cloud-upload"></i>
                </div>
                <div class="upload-text">Drag & drop your files here or click to browse</div>
                <div class="upload-subtext">PDF, DOC, DOCX • Maximum 3 files • 5MB each</div>
                <input type="file" name="resume[]" multiple accept=".pdf,.doc,.docx" 
                       class="file-input" id="resumeFiles" required>
            </div>
            
            <div id="fileList" class="file-list"></div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <a href="view-internship.php?internship_id=<?= $internship_id ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i>
                Back to Details
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-send"></i>
                Submit Application
            </button>
        </div>
    </form>

    <?php else: ?>
    <!-- Already Applied State -->
    <div class="application-complete">
        <div class="complete-icon">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        <h3 class="complete-title">Application Already Submitted</h3>
        <p class="complete-text">
            You submitted your application on <?= $applied_date ?><br>
            Current status: <strong><?= ucfirst($application_status ?? 'Pending') ?></strong>
        </p>
        <div class="complete-actions">
            <a href="student-applications.php" class="btn btn-primary">
                <i class="bi bi-list"></i>
                View My Applications
            </a>
            <a href="student-dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-house"></i>
                Back to Dashboard
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('resumeFiles');
    const fileList = document.getElementById('fileList');
    const uploadArea = document.querySelector('.file-upload-area');

    // File upload preview
    fileInput.addEventListener('change', function(e) {
        updateFileList(e.target.files);
    });

    // Drag and drop functionality
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });

    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
    });

    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        const files = e.dataTransfer.files;
        fileInput.files = files;
        updateFileList(files);
    });

    function updateFileList(files) {
        fileList.innerHTML = '';
        
        if (files.length > 0) {
            Array.from(files).forEach((file, index) => {
                const fileDiv = document.createElement('div');
                fileDiv.className = 'file-item';
                fileDiv.innerHTML = `
                    <i class="bi bi-file-earmark"></i>
                    <strong>${file.name}</strong>
                    <small class="text-muted">(${(file.size / 1024 / 1024).toFixed(2)} MB)</small>
                `;
                fileList.appendChild(fileDiv);
            });
        }
    }

    // Form validation and submission
    document.getElementById('applicationForm').addEventListener('submit', function(e) {
        const coverLetter = document.getElementById('cover_letter').value.trim();
        const resumeFiles = document.getElementById('resumeFiles').files;
        
        // Cover letter validation (optional)
        if (coverLetter.length > 2000) {
            e.preventDefault();
            alert('Cover letter must not exceed 2000 characters.');
            return;
        }
        
        // Resume files validation (required)
        if (resumeFiles.length === 0) {
            e.preventDefault();
            alert('Please upload at least one resume file.');
            return;
        }
        
        if (resumeFiles.length > 3) {
            e.preventDefault();
            alert('Maximum 3 files allowed.');
            return;
        }
        
        // Check file sizes
        for (let i = 0; i < resumeFiles.length; i++) {
            if (resumeFiles[i].size > 5 * 1024 * 1024) { // 5MB
                e.preventDefault();
                alert(`File "${resumeFiles[i].name}" is too large. Maximum 5MB per file.`);
                return;
            }
        }
        
        // Show loading state
        const submitBtn = e.target.querySelector('button[type="submit"]');
        submitBtn.classList.add('btn-loading');
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Submitting...';
        submitBtn.disabled = true;
    });
});
</script>
</body>
</html>