<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') { header('Location: login.php'); exit(); }

$user_id = (int)$_SESSION['user_id'];

$q = $conn->prepare("SELECT * FROM students WHERE user_id=? LIMIT 1");
$q->bind_param("i",$user_id);
$q->execute();
$student = $q->get_result()->fetch_assoc();
if (!$student) { echo "Student profile not found."; exit(); }

$msg = "";
$msg_type = "";
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $full_name        = trim($_POST['full_name'] ?? $student['full_name']);
  $department       = trim($_POST['department'] ?? $student['department']);
  $program          = trim($_POST['program'] ?? $student['program']);
  $level            = trim($_POST['level'] ?? $student['level']);
  $field_of_interest= trim($_POST['field_of_interest'] ?? $student['field_of_interest']);
  $skills           = trim($_POST['skills'] ?? $student['skills']);
  $preferences      = trim($_POST['preferences'] ?? $student['preferences']);
  $gpa_input        = $_POST['gpa'] ?? '';
  $gpa              = ($gpa_input !== '') ? (float)$gpa_input : null;

  if ($gpa !== null && ($gpa < 0 || $gpa > 4)) {
    $msg = "GPA must be between 0.00 and 4.00.";
    $msg_type = "danger";
  } else {
    // Resume replace (optional)
    $resume_filename = $student['resume'];
    if (!empty($_FILES['resume']['name'])) {
      $allowed = ['pdf','doc','docx'];
      $ext = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext,$allowed)) {
        $msg = "Resume must be PDF/DOC/DOCX.";
        $msg_type = "danger";
      } elseif ($_FILES['resume']['size'] > 5*1024*1024) {
        $msg = "Resume must be ≤ 5MB.";
        $msg_type = "danger";
      } else {
        $upload_dir = __DIR__ . "/uploads/resumes/";
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
        $safe = preg_replace('/[^A-Za-z0-9._-]/','_', basename($_FILES['resume']['name']));
        $new_name = time().'_'.$student['student_number'].'_'.$safe;
        $target = $upload_dir . $new_name;
        if (move_uploaded_file($_FILES['resume']['tmp_name'], $target)) {
          // delete old resume
          if (!empty($resume_filename)) {
            $old = $upload_dir . $resume_filename;
            if (is_file($old)) @unlink($old);
          }
          $resume_filename = $new_name;
        } else {
          $msg = "Failed to upload new resume.";
          $msg_type = "danger";
        }
      }
    }

    if ($msg === "") {
      $u = $conn->prepare("
        UPDATE students SET
          full_name=?, department=?, program=?, level=?, field_of_interest=?, skills=?, gpa=?, resume=?, preferences=?
        WHERE user_id=?
      ");
      $u->bind_param(
        "sssssssdsi",
        $full_name, $department, $program, $level, $field_of_interest, $skills, $gpa, $resume_filename, $preferences, $user_id
      );
      if ($u->execute()) {
        $msg = "Profile updated successfully!";
        $msg_type = "success";
        $student = array_merge($student, compact('full_name','department','program','level','field_of_interest','skills','preferences'));
        $student['gpa'] = $gpa;
        $student['resume'] = $resume_filename;
        $_SESSION['display_name'] = $full_name; // keep navbar in sync
      } else {
        $msg = "Update failed. Please try again.";
        $msg_type = "danger";
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Student Portal</title>
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
            max-width: 1000px;
            margin-left: calc(260px + (100vw - 260px - 1000px) / 2);
        }

        /* Compact Welcome Header */
        .welcome-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            border-radius: var(--border-radius);
            color: white;
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow);
        }

        .welcome-content {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .welcome-icon {
            font-size: 1.5rem;
            opacity: 0.9;
        }

        .welcome-text h1 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            line-height: 1.3;
        }

        .welcome-text p {
            font-size: 0.875rem;
            opacity: 0.85;
            margin: 0;
            font-weight: 400;
        }

        /* Profile Overview Cards */
        .profile-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .overview-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .overview-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .overview-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            flex-shrink: 0;
        }

        .overview-icon.primary { background: var(--primary-color); }
        .overview-icon.success { background: var(--success-color); }
        .overview-icon.info { background: var(--info-color); }
        .overview-icon.warning { background: var(--warning-color); }

        .overview-content {
            flex: 1;
        }

        .overview-value {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.125rem;
            line-height: 1;
        }

        .overview-label {
            color: var(--text-secondary);
            font-size: 0.8125rem;
            font-weight: 500;
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
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .form-grid-full {
            grid-column: 1 / -1;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
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

        .form-control[readonly] {
            background-color: var(--hover-bg);
            color: var(--text-secondary);
        }

        .form-text {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .form-text a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .form-text a:hover {
            text-decoration: underline;
        }

        /* Resume Upload Section */
        .resume-section {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .current-resume {
            padding: 0.75rem;
            background: var(--hover-bg);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .resume-icon {
            width: 32px;
            height: 32px;
            background: var(--primary-color);
            color: white;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
        }

        .resume-info {
            flex: 1;
        }

        .resume-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.875rem;
            margin-bottom: 0.125rem;
        }

        .resume-status {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .resume-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-resume {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 6px;
            text-decoration: none;
            transition: var(--transition);
        }

        .btn-download {
            background: rgba(3, 195, 236, 0.1);
            color: var(--info-color);
            border: 1px solid rgba(3, 195, 236, 0.3);
        }

        .btn-download:hover {
            background: var(--info-color);
            color: white;
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

        /* Form Actions */
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
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

        /* Required Field Indicator */
        .required {
            color: var(--danger-color);
            margin-left: 0.25rem;
        }

        /* Completion Progress */
        .completion-progress {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .progress-title {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9375rem;
        }

        .progress-percentage {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 0.9375rem;
        }

        .progress {
            height: 8px;
            border-radius: 4px;
            background: var(--hover-bg);
        }

        .progress-bar {
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
            border-radius: 4px;
        }

        /* Dummy Content Section */
        .tips-section {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-top: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .tips-title {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .tip-item {
            padding: 0.75rem;
            background: var(--hover-bg);
            border-radius: var(--border-radius);
            border-left: 3px solid var(--primary-color);
        }

        .tip-item h6 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.8125rem;
        }

        .tip-item p {
            color: var(--text-secondary);
            font-size: 0.75rem;
            line-height: 1.4;
            margin: 0;
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

            .form-grid {
                grid-template-columns: 1fr;
            }

            .profile-overview {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 0.5rem;
            }

            .overview-card {
                padding: 0.75rem;
            }

            .overview-icon {
                width: 32px;
                height: 32px;
                font-size: 1rem;
            }

            .overview-value {
                font-size: 1rem;
            }

            .form-actions {
                flex-direction: column;
                gap: 1rem;
            }

            .tips-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .welcome-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .form-section {
                padding: 1rem;
            }

            .resume-actions {
                flex-direction: column;
                gap: 0.25rem;
            }
        }
    </style>
</head>

<body>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="main-content">
    <!-- Welcome Header -->
    <div class="welcome-header">
        <div class="welcome-content">
            <i class="bi bi-person-circle welcome-icon"></i>
            <div class="welcome-text">
                <h1>My Profile</h1>
                <p>Manage your personal information and preferences</p>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?= $msg_type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i>
            <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Profile Completion Progress -->
    <?php
    $completion_fields = [
        'full_name' => !empty($student['full_name']),
        'program' => !empty($student['program']),
        'department' => !empty($student['department']),
        'level' => !empty($student['level']),
        'gpa' => !empty($student['gpa']),
        'field_of_interest' => !empty($student['field_of_interest']),
        'skills' => !empty($student['skills']),
        'resume' => !empty($student['resume']),
        'preferences' => !empty($student['preferences'])
    ];
    $completed = array_sum($completion_fields);
    $total = count($completion_fields);
    $percentage = round(($completed / $total) * 100);
    ?>

    <div class="completion-progress">
        <div class="progress-header">
            <span class="progress-title">Profile Completion</span>
            <span class="progress-percentage"><?= $percentage ?>%</span>
        </div>
        <div class="progress">
            <div class="progress-bar" style="width: <?= $percentage ?>%"></div>
        </div>
    </div>

    <!-- Profile Overview -->
    <div class="profile-overview">
        <div class="overview-card">
            <div class="overview-icon primary">
                <i class="bi bi-mortarboard"></i>
            </div>
            <div class="overview-content">
                <div class="overview-value"><?= htmlspecialchars($student['level'] ?: 'Not Set') ?></div>
                <div class="overview-label">Academic Level</div>
            </div>
        </div>

        <div class="overview-card">
            <div class="overview-icon success">
                <i class="bi bi-bar-chart"></i>
            </div>
            <div class="overview-content">
                <div class="overview-value"><?= $student['gpa'] ? number_format($student['gpa'], 2) : 'Not Set' ?></div>
                <div class="overview-label">GPA</div>
            </div>
        </div>

        <div class="overview-card">
            <div class="overview-icon info">
                <i class="bi bi-building"></i>
            </div>
            <div class="overview-content">
                <div class="overview-value"><?= htmlspecialchars($student['department'] ?: 'Not Set') ?></div>
                <div class="overview-label">Department</div>
            </div>
        </div>

        <div class="overview-card">
            <div class="overview-icon warning">
                <i class="bi bi-file-earmark-text"></i>
            </div>
            <div class="overview-content">
                <div class="overview-value"><?= !empty($student['resume']) ? 'Uploaded' : 'Missing' ?></div>
                <div class="overview-label">Resume</div>
            </div>
        </div>
    </div>

    <!-- Profile Form -->
    <form method="post" enctype="multipart/form-data" id="profileForm">
        <!-- Basic Information -->
        <div class="form-section">
            <div class="section-title">
                <i class="bi bi-person text-primary"></i>
                Basic Information
            </div>
            
            <div class="form-grid">
                <div>
                    <label class="form-label">
                        <i class="bi bi-person"></i>
                        Full Name <span class="required">*</span>
                    </label>
                    <input name="full_name" class="form-control" 
                           value="<?= htmlspecialchars($student['full_name']) ?>" 
                           required placeholder="Enter your full name">
                </div>

                <div>
                    <label class="form-label">
                        <i class="bi bi-hash"></i>
                        Student Number
                    </label>
                    <input class="form-control" 
                           value="<?= htmlspecialchars($student['student_number']) ?>" 
                           readonly>
                    <div class="form-text">Institution ID (cannot be changed)</div>
                </div>

                <div>
                    <label class="form-label">
                        <i class="bi bi-envelope"></i>
                        Email Address
                    </label>
                    <input class="form-control" 
                           value="<?= htmlspecialchars($student['email']) ?>" 
                           readonly>
                    <div class="form-text">Login email (cannot be changed)</div>
                </div>
            </div>
        </div>

        <!-- Academic Information -->
        <div class="form-section">
            <div class="section-title">
                <i class="bi bi-mortarboard text-primary"></i>
                Academic Information
            </div>
            
            <div class="form-grid">
                <div>
                    <label class="form-label">
                        <i class="bi bi-book"></i>
                        Program
                    </label>
                    <input name="program" class="form-control" 
                           value="<?= htmlspecialchars($student['program']) ?>"
                           placeholder="e.g., Computer Science">
                </div>

                <div>
                    <label class="form-label">
                        <i class="bi bi-building"></i>
                        Department
                    </label>
                    <input name="department" class="form-control" 
                           value="<?= htmlspecialchars($student['department']) ?>"
                           placeholder="e.g., School of Engineering">
                </div>

                <div>
                    <label class="form-label">
                        <i class="bi bi-trophy"></i>
                        Academic Level
                    </label>
                    <input name="level" class="form-control" 
                           value="<?= htmlspecialchars($student['level']) ?>"
                           placeholder="e.g., Sophomore, Junior">
                </div>

                <div>
                    <label class="form-label">
                        <i class="bi bi-bar-chart"></i>
                        GPA
                    </label>
                    <input name="gpa" class="form-control" 
                           value="<?= htmlspecialchars($student['gpa']) ?>" 
                           type="number" step="0.01" min="0" max="4" 
                           placeholder="e.g., 3.45">
                    <div class="form-text">Scale: 0.00 - 4.00</div>
                </div>
            </div>
        </div>

        <!-- Professional Information -->
        <div class="form-section">
            <div class="section-title">
                <i class="bi bi-briefcase text-primary"></i>
                Professional Information
            </div>
            
            <div class="form-grid">
                <div>
                    <label class="form-label">
                        <i class="bi bi-lightbulb"></i>
                        Field of Interest
                    </label>
                    <input name="field_of_interest" class="form-control" 
                           value="<?= htmlspecialchars($student['field_of_interest']) ?>" 
                           placeholder="e.g., Data Science, UI/UX Design">
                </div>

                <div>
                    <label class="form-label">
                        <i class="bi bi-file-earmark-text"></i>
                        Resume/CV
                    </label>
                    <input type="file" name="resume" class="form-control" 
                           accept=".pdf,.doc,.docx">
                    <div class="form-text">PDF, DOC, DOCX • Maximum 5MB</div>
                    
                    <?php if (!empty($student['resume'])): ?>
                        <div class="current-resume mt-2">
                            <div class="resume-icon">
                                <i class="bi bi-file-earmark-pdf"></i>
                            </div>
                            <div class="resume-info">
                                <div class="resume-name">Current Resume</div>
                                <div class="resume-status">Uploaded and ready</div>
                            </div>
                            <div class="resume-actions">
                                <a href="uploads/resumes/<?= rawurlencode($student['resume']) ?>" 
                                   target="_blank" class="btn-resume btn-download">
                                    <i class="bi bi-download"></i> Download
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-grid-full">
                    <label class="form-label">
                        <i class="bi bi-tools"></i>
                        Skills & Technologies
                    </label>
                    <textarea name="skills" class="form-control" rows="3"
                              placeholder="e.g., Python, JavaScript, React, Machine Learning, Project Management"><?= htmlspecialchars($student['skills']) ?></textarea>
                    <div class="form-text">List your technical and soft skills, separated by commas</div>
                </div>

                <div class="form-grid-full">
                    <label class="form-label">
                        <i class="bi bi-gear"></i>
                        Work Preferences
                    </label>
                    <textarea name="preferences" class="form-control" rows="3" 
                              placeholder="e.g., Prefer hybrid work, 3-6 month duration, flexible hours"><?= htmlspecialchars($student['preferences']) ?></textarea>
                    <div class="form-text">Describe your ideal work environment and internship preferences</div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <a href="student-dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i>
                Back to Dashboard
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle"></i>
                Save Changes
            </button>
        </div>
    </form>

    <!-- Profile Tips Section -->
    <div class="tips-section">
        <div class="tips-title">
            <i class="bi bi-lightbulb text-warning"></i>
            Profile Optimization Tips
        </div>
        
        <div class="tips-grid">
            <div class="tip-item">
                <h6>Complete Your Profile</h6>
                <p>Profiles with 100% completion get 3x more views from recruiters and have higher application success rates.</p>
            </div>
            
            <div class="tip-item">
                <h6>Professional Resume</h6>
                <p>Keep your resume updated and tailored. Use action verbs and quantify your achievements where possible.</p>
            </div>
            
            <div class="tip-item">
                <h6>Relevant Skills</h6>
                <p>List both technical and soft skills. Include programming languages, tools, and frameworks you're comfortable with.</p>
            </div>
            
            <div class="tip-item">
                <h6>Clear Preferences</h6>
                <p>Specify your work preferences to help employers match you with suitable opportunities and work environments.</p>
            </div>
            
            <div class="tip-item">
                <h6>Accurate GPA</h6>
                <p>Be honest about your GPA. Many companies value transparency and growth mindset over perfect grades.</p>
            </div>
            
            <div class="tip-item">
                <h6>Regular Updates</h6>
                <p>Review and update your profile quarterly. Add new skills, projects, and adjust preferences as you grow.</p>
            </div>
        </div>

        <p style="margin-top: 1rem; color: var(--text-secondary); font-size: 0.875rem;">
            <strong>Pro tip:</strong> Students with complete profiles receive 40% more internship opportunities. 
            Take a few minutes to fill out all sections—your future self will thank you!
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-dismiss success alerts after 5 seconds
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert-success');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);

// Form validation
document.getElementById('profileForm').addEventListener('submit', function(e) {
    const fullName = document.querySelector('input[name="full_name"]').value.trim();
    const gpa = document.querySelector('input[name="gpa"]').value;
    
    if (!fullName) {
        e.preventDefault();
        alert('Full name is required.');
        return;
    }
    
    if (gpa && (parseFloat(gpa) < 0 || parseFloat(gpa) > 4)) {
        e.preventDefault();
        alert('GPA must be between 0.00 and 4.00.');
        return;
    }
});

// File upload validation
document.querySelector('input[name="resume"]').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!allowedTypes.includes(file.type)) {
            alert('Please upload a PDF, DOC, or DOCX file.');
            e.target.value = '';
            return;
        }
        
        if (file.size > maxSize) {
            alert('File size must be less than 5MB.');
            e.target.value = '';
            return;
        }
    }
});
</script>
</body>
</html>