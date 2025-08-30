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
    <style>
        body { margin: 0; font-family: 'Segoe UI', system-ui, sans-serif; }
        .main-content {
            margin-left: 250px;
            padding: 2rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }
        .application-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .page-title {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .internship-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid #007bff;
        }
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .file-upload {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        .file-upload:hover {
            border-color: #007bff;
            background: #e7f3ff;
        }
        .btn-apply {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-apply:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 123, 255, 0.3);
            color: white;
        }
        .alert {
            border: none;
            border-radius: 10px;
            font-weight: 500;
            margin-bottom: 1.5rem;
        }
        .requirements-section {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid #e1bee7;
        }
        .badge {
            font-size: 0.75rem;
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 1rem; }
        }
    </style>
</head>

<body>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col">
                <h1 class="page-title">
                    <i class="bi bi-send me-2"></i>
                    Apply for Position
                </h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="student-dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Apply for Internship</li>
                    </ol>
                </nav>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?= $msg_type === 'success' ? 'check-circle' : 
                    ($msg_type === 'warning' ? 'exclamation-triangle' : 
                    ($msg_type === 'info' ? 'info-circle' : 'x-circle')) ?> me-2"></i>
                <?= $msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Internship Info -->
        <div class="internship-info">
            <h4 class="mb-3">
                <i class="bi bi-briefcase me-2"></i>
                <?= htmlspecialchars($title) ?>
            </h4>
            <div class="row">
                <div class="col-md-6">
                    <small class="text-muted">Application Deadline:</small>
                    <p class="mb-2"><strong><?= date('F j, Y', strtotime($deadline)) ?></strong></p>
                </div>
                <div class="col-md-6">
                    <small class="text-muted">Days Remaining:</small>
                    <?php 
                    $days_left = ceil((strtotime($deadline) - time()) / (60 * 60 * 24));
                    $badge_class = $days_left <= 3 ? 'danger' : ($days_left <= 7 ? 'warning' : 'success');
                    ?>
                    <p class="mb-2">
                        <span class="badge bg-<?= $badge_class ?>"><?= $days_left ?> days</span>
                    </p>
                </div>
            </div>
        </div>

        <?php if (!$already_applied): ?>
        <!-- Application Form -->
        <div class="application-card">
            <div class="mb-3">
                <h5 class="mb-2">
                    <i class="bi bi-file-text me-2"></i>
                    Application Requirements
                </h5>
                <p class="text-muted mb-0">
                    <span class="badge bg-danger me-2">Required</span> Resume/CV files
                    <span class="badge bg-info ms-3 me-2">Optional</span> Cover letter
                </p>
                <hr class="my-3">
            </div>
            
            <form method="post" enctype="multipart/form-data" id="applicationForm">
                <!-- Cover Letter -->
                <div class="mb-4">
                    <label for="cover_letter" class="form-label">
                        <i class="bi bi-file-text me-1"></i>
                        Cover Letter <small class="text-muted">(Optional)</small>
                    </label>
                    <textarea name="cover_letter" id="cover_letter" class="form-control" rows="6" 
                              placeholder="Explain why you're interested in this position and what you can bring to the role... (Optional)"
                              maxlength="2000"></textarea>
                    <div class="form-text">Maximum 2000 characters (This field is optional, but recommended)</div>
                </div>

                <!-- Resume Upload -->
                <div class="mb-4">
                    <label class="form-label">
                        <i class="bi bi-file-earmark-pdf me-1"></i>
                        Resume/CV Files * (Maximum 3 files, 5MB each)
                    </label>
                    <div class="file-upload">
                        <i class="bi bi-cloud-upload display-4 text-muted mb-3"></i>
                        <p class="mb-2">Drag & drop your resume files here, or click to browse</p>
                        <input type="file" name="resume[]" multiple accept=".pdf,.doc,.docx" 
                               class="form-control" id="resumeFiles" required>
                        <small class="text-muted">Accepted formats: PDF, DOC, DOCX (Required)</small>
                    </div>
                    <div id="fileList" class="mt-2"></div>
                </div>

                <!-- Form Actions -->
                <div class="d-flex justify-content-between">
                    <a href="student-dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-apply">
                        <i class="bi bi-send me-1"></i>
                        Submit Application
                    </button>
                </div>
            </form>
        </div>
        <?php else: ?>
        <!-- Already Applied Message -->
        <div class="application-card text-center">
            <i class="bi bi-check-circle-fill text-success display-1 mb-3"></i>
            <h4>Application Already Submitted</h4>
            <p class="text-muted mb-4">
                You submitted your application on <?= $applied_date ?><br>
                Current status: <strong><?= ucfirst($application_status ?? 'Pending') ?></strong>
            </p>
            <a href="student-applications.php" class="btn btn-primary me-2">
                <i class="bi bi-list me-1"></i>
                View My Applications
            </a>
            <a href="student-dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-house me-1"></i>
                Back to Dashboard
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// File upload preview
document.getElementById('resumeFiles').addEventListener('change', function(e) {
    const fileList = document.getElementById('fileList');
    fileList.innerHTML = '';
    
    if (e.target.files.length > 0) {
        const files = Array.from(e.target.files);
        files.forEach((file, index) => {
            const fileDiv = document.createElement('div');
            fileDiv.className = 'alert alert-info py-2 mb-1';
            fileDiv.innerHTML = `
                <i class="bi bi-file-earmark me-2"></i>
                <strong>${file.name}</strong> 
                <small class="text-muted">(${(file.size / 1024 / 1024).toFixed(2)} MB)</small>
            `;
            fileList.appendChild(fileDiv);
        });
    }
});

// Form validation
document.getElementById('applicationForm').addEventListener('submit', function(e) {
    const coverLetter = document.getElementById('cover_letter').value.trim();
    const resumeFiles = document.getElementById('resumeFiles').files;
    
    // Cover letter is optional, so we only check if it's too long when provided
    if (coverLetter.length > 2000) {
        e.preventDefault();
        alert('Cover letter must not exceed 2000 characters.');
        return;
    }
    
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
    
    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Submitting...';
    submitBtn.disabled = true;
});
</script>
</body>
</html>