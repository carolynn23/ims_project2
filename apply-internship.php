<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') { header('Location: login.php'); exit(); }

$internship_id = (int)($_GET['internship_id'] ?? 0);
if ($internship_id <= 0) { echo "Invalid internship."; exit(); }

// fetch student_id
$user_id = (int)$_SESSION['user_id'];
$st = $conn->prepare("SELECT student_id, full_name FROM students WHERE user_id=?");
$st->bind_param("i", $user_id);
$st->execute();
$student = $st->get_result()->fetch_assoc();
if (!$student) { echo "Student profile missing."; exit(); }
$student_id = (int)$student['student_id'];
$student_name = $student['full_name'] ?? 'Student';

// fetch internship title
$ti = $conn->prepare("SELECT title FROM internships WHERE internship_id=?");
$ti->bind_param("i", $internship_id);
$ti->execute();
$intern = $ti->get_result()->fetch_assoc();
if (!$intern) { echo "Internship not found."; exit(); }
$title = $intern['title'];

$msg = "";
$msg_type = "";
$already_applied = false;

// Check if student has already applied
$ck = $conn->prepare("SELECT application_id, applied_at FROM applications WHERE internship_id=? AND student_id=?");
$ck->bind_param("ii", $internship_id, $student_id);
$ck->execute();
$existing_application = $ck->get_result()->fetch_assoc();
if ($existing_application) {
  $already_applied = true;
  $applied_date = date('M j, Y \a\t g:i A', strtotime($existing_application['applied_at']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already_applied) {
  $cover = $_POST['cover_letter'] ?? '';

  // Process application since we already checked for duplicates
    $resume_files = [];
    $upload_errors = [];
    
    // Handle multiple file uploads
    if (!empty($_FILES['resume']['name'][0])) {
      $allowed = ['pdf','doc','docx'];
      $max_files = 3; // Allow up to 3 files
      $max_size = 5*1024*1024; // 5MB per file
      
      $file_count = count($_FILES['resume']['name']);
      if ($file_count > $max_files) {
        $upload_errors[] = "Maximum $max_files files allowed.";
      } else {
        for ($i = 0; $i < $file_count; $i++) {
          if ($_FILES['resume']['error'][$i] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['resume']['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
              $upload_errors[] = "File " . ($_i+1) . " must be PDF/DOC/DOCX.";
            } elseif ($_FILES['resume']['size'][$i] > $max_size) {
              $upload_errors[] = "File " . ($_i+1) . " must be ≤ 5MB.";
            } else {
              $resume_name = time() . '_' . $student_id . '_' . $i . '.' . $ext;
              $target = __DIR__ . "/uploads/" . $resume_name;
              if (move_uploaded_file($_FILES['resume']['tmp_name'][$i], $target)) {
                $resume_files[] = $resume_name;
              } else {
                $upload_errors[] = "Failed to upload file " . ($_i+1) . ".";
              }
            }
          }
        }
      }
    }

    if (!empty($upload_errors)) {
      $msg = implode(" ", $upload_errors);
      $msg_type = "danger";
    } elseif (empty($resume_files)) {
      $msg = "Please upload at least one resume/CV file.";
      $msg_type = "warning";
    } else {
      $resume_json = json_encode($resume_files);
      $ins = $conn->prepare("INSERT INTO applications (internship_id, student_id, resume, cover_letter) VALUES (?,?,?,?)");
      $ins->bind_param("iiss", $internship_id, $student_id, $resume_json, $cover);
      if ($ins->execute()) {
        // notify employer
        $eu = $conn->prepare("
          SELECT e.user_id 
          FROM internships i 
          JOIN employers e ON i.employer_id = e.employer_id 
          WHERE i.internship_id=?");
        $eu->bind_param("i", $internship_id);
        $eu->execute();
        if ($er = $eu->get_result()->fetch_assoc()) {
          $emp_user_id = (int)$er['user_id'];
          $msgtxt = "$student_name applied for '$title'.";
          $nn = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
          $nn->bind_param("is", $emp_user_id, $msgtxt);
          $nn->execute();
        }
        $msg = "🎉 Application submitted successfully! Your application has been sent to the employer and you will receive notifications about any updates. Good luck with your application!";
        $msg_type = "success";
      } else {
        $msg = "Error submitting application. Please try again.";
        $msg_type = "danger";
      }
    }
  }
else if ($already_applied) {
    $msg = "You have already applied for this internship on $applied_date.";
    $msg_type = "warning";
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Apply for <?= htmlspecialchars($title) ?> — InternHub</title>
  
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

    /* Main Content Area */
    .main-content {
      margin-left: 260px;
      padding: 2rem;
      min-height: 100vh;
      transition: var(--transition);
    }

    /* Breadcrumb */
    .breadcrumb-container {
      margin-bottom: 1.5rem;
    }

    .breadcrumb {
      background: transparent;
      padding: 0;
      margin: 0;
    }

    .breadcrumb-item {
      color: var(--text-secondary);
    }

    .breadcrumb-item.active {
      color: var(--text-primary);
      font-weight: 500;
    }

    .breadcrumb-item + .breadcrumb-item::before {
      content: "›";
      color: var(--text-muted);
    }

    .breadcrumb-item a {
      color: var(--primary-color);
      text-decoration: none;
      transition: var(--transition);
    }

    .breadcrumb-item a:hover {
      color: var(--primary-light);
    }

    /* Page Header */
    .page-header {
      background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
      border-radius: var(--border-radius-lg);
      padding: 2rem;
      margin-bottom: 2rem;
      color: white;
      position: relative;
      overflow: hidden;
      box-shadow: var(--shadow-lg);
    }

    .page-header::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -10%;
      width: 120px;
      height: 120px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 50%;
      animation: float 8s ease-in-out infinite;
    }

    .page-header::after {
      content: '';
      position: absolute;
      bottom: -30%;
      left: -5%;
      width: 80px;
      height: 80px;
      background: rgba(255, 255, 255, 0.08);
      border-radius: 50%;
      animation: float 6s ease-in-out infinite reverse;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0px) rotate(0deg); }
      50% { transform: translateY(-20px) rotate(180deg); }
    }

    .page-header-content {
      position: relative;
      z-index: 2;
    }

    .page-title {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .page-subtitle {
      font-size: 1rem;
      opacity: 0.9;
      font-weight: 400;
    }

    /* Application Form */
    .application-container {
      max-width: 800px;
      margin: 0 auto;
    }

    .form-card {
      background: var(--card-bg);
      border-radius: var(--border-radius-lg);
      padding: 2rem;
      border: 1px solid var(--border-color);
      box-shadow: var(--shadow-sm);
      margin-bottom: 2rem;
    }

    .form-section {
      margin-bottom: 2rem;
    }

    .section-header {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 1.5rem;
      padding-bottom: 1rem;
      border-bottom: 1px solid var(--border-color);
    }

    .section-icon {
      width: 36px;
      height: 36px;
      background: var(--primary-color);
      color: white;
      border-radius: var(--border-radius);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .section-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: var(--dark-color);
      margin: 0;
    }

    .section-description {
      color: var(--text-secondary);
      font-size: 0.875rem;
      margin-top: 0.25rem;
    }

    /* Form Controls */
    .form-label {
      font-weight: 600;
      color: var(--text-primary);
      margin-bottom: 0.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .form-label .required {
      color: var(--danger-color);
    }

    .form-label .optional {
      color: var(--text-muted);
      font-weight: 400;
      font-size: 0.875rem;
    }

    .form-control, .form-select {
      border: 2px solid var(--border-color);
      border-radius: var(--border-radius);
      padding: 0.875rem 1rem;
      font-size: 1rem;
      transition: var(--transition);
      background-color: var(--light-color);
    }

    .form-control:focus, .form-select:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 0.2rem rgba(105, 108, 255, 0.15);
      background-color: white;
    }

    .form-control.is-invalid {
      border-color: var(--danger-color);
    }

    .form-control.is-valid {
      border-color: var(--success-color);
    }

    /* File Upload Area */
    .file-upload-area {
      border: 2px dashed var(--border-color);
      border-radius: var(--border-radius-lg);
      padding: 2rem;
      text-align: center;
      background: var(--hover-bg);
      transition: var(--transition);
      cursor: pointer;
      position: relative;
    }

    .file-upload-area:hover {
      border-color: var(--primary-color);
      background: white;
    }

    .file-upload-area.dragover {
      border-color: var(--primary-color);
      background: rgba(105, 108, 255, 0.05);
    }

    .upload-icon {
      font-size: 3rem;
      color: var(--primary-color);
      margin-bottom: 1rem;
    }

    .upload-text {
      font-size: 1.125rem;
      font-weight: 600;
      color: var(--text-primary);
      margin-bottom: 0.5rem;
    }

    .upload-subtext {
      color: var(--text-secondary);
      font-size: 0.875rem;
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
      gap: 0.75rem;
      padding: 0.75rem;
      background: white;
      border: 1px solid var(--border-color);
      border-radius: var(--border-radius);
      margin-bottom: 0.5rem;
    }

    .file-icon {
      width: 32px;
      height: 32px;
      background: var(--primary-color);
      color: white;
      border-radius: var(--border-radius);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.875rem;
    }

    .file-details {
      flex: 1;
    }

    .file-name {
      font-weight: 500;
      color: var(--text-primary);
      margin-bottom: 0.25rem;
    }

    .file-size {
      font-size: 0.75rem;
      color: var(--text-secondary);
    }

    .file-remove {
      background: var(--danger-color);
      color: white;
      border: none;
      border-radius: 50%;
      width: 24px;
      height: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: var(--transition);
    }

    .file-remove:hover {
      background: #d32f2f;
    }

    /* Textarea Enhancement */
    .textarea-container {
      position: relative;
    }

    .textarea-counter {
      position: absolute;
      bottom: 0.75rem;
      right: 0.75rem;
      font-size: 0.75rem;
      color: var(--text-muted);
      background: rgba(255, 255, 255, 0.9);
      padding: 0.25rem 0.5rem;
      border-radius: 4px;
    }

    /* Alerts */
    .alert {
      border: none;
      border-radius: var(--border-radius-lg);
      padding: 1rem 1.5rem;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .alert-success {
      background: rgba(113, 221, 55, 0.1);
      color: var(--success-color);
      border-left: 4px solid var(--success-color);
    }

    .alert-warning {
      background: rgba(255, 180, 0, 0.1);
      color: var(--warning-color);
      border-left: 4px solid var(--warning-color);
    }

    .alert-danger {
      background: rgba(255, 62, 29, 0.1);
      color: var(--danger-color);
      border-left: 4px solid var(--danger-color);
    }

    /* Action Buttons */
    .form-actions {
      display: flex;
      gap: 1rem;
      justify-content: flex-end;
      padding-top: 1.5rem;
      border-top: 1px solid var(--border-color);
    }

    .btn-submit {
      background: linear-gradient(135deg, var(--success-color) 0%, #5cb85c 100%);
      color: white;
      border: none;
      padding: 0.875rem 2rem;
      border-radius: var(--border-radius);
      font-weight: 600;
      font-size: 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: var(--transition);
      box-shadow: 0 4px 12px rgba(113, 221, 55, 0.3);
    }

    .btn-submit:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(113, 221, 55, 0.4);
      color: white;
    }

    .btn-submit:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }

    .btn-cancel {
      background: white;
      color: var(--text-primary);
      border: 2px solid var(--border-color);
      padding: 0.875rem 1.5rem;
      border-radius: var(--border-radius);
      font-weight: 500;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: var(--transition);
    }

    .btn-cancel:hover {
      border-color: var(--primary-color);
      color: var(--primary-color);
      transform: translateY(-1px);
    }

    /* Loading State */
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

    /* Responsive Design */
    @media (max-width: 768px) {
      .main-content {
        margin-left: 0;
        padding: 1rem;
      }

      .page-header {
        padding: 1.5rem;
      }

      .page-title {
        font-size: 1.75rem;
      }

      .form-card {
        padding: 1.5rem;
      }

      .form-actions {
        flex-direction: column;
      }

      .file-upload-area {
        padding: 1.5rem;
      }
    }
  </style>
</head>

<body>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="main-content">
  <!-- Breadcrumb -->
  <div class="breadcrumb-container">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item">
          <a href="student-dashboard.php">
            <i class="bi bi-house"></i> Dashboard
          </a>
        </li>
        <li class="breadcrumb-item">
          <a href="view-internship.php?internship_id=<?= $internship_id ?>">Internship Details</a>
        </li>
        <li class="breadcrumb-item active" aria-current="page">
          Apply
        </li>
      </ol>
    </nav>
  </div>

  <!-- Page Header -->
  <div class="page-header">
    <div class="page-header-content">
      <h1 class="page-title">
        <i class="bi bi-send me-2"></i>
        Apply for Position
      </h1>
      <p class="page-subtitle">
        Submit your application for: <strong><?= htmlspecialchars($title) ?></strong>
      </p>
    </div>
  </div>

  <div class="application-container">
    <!-- Alert Messages -->
    <?php if ($msg): ?>
      <div class="alert alert-<?= $msg_type ?>">
        <i class="bi bi-<?= $msg_type === 'success' ? 'check-circle' : ($msg_type === 'warning' ? 'exclamation-triangle' : 'x-circle') ?>"></i>
        <?= htmlspecialchars($msg) ?>
      </div>
    <?php endif; ?>

    <!-- Application Form -->
    <form method="post" enctype="multipart/form-data" id="applicationForm">
      <!-- Resume Section -->
      <div class="form-card">
        <div class="form-section">
          <div class="section-header">
            <div class="section-icon">
              <i class="bi bi-file-earmark-person"></i>
            </div>
            <div>
              <h3 class="section-title">Resume / CV</h3>
              <div class="section-description">Upload your resume or CV (PDF, DOC, DOCX • Max 5MB each • Up to 3 files)</div>
            </div>
          </div>

          <div class="file-upload-area" onclick="document.getElementById('resumeInput').click()">
            <div class="upload-icon">
              <i class="bi bi-cloud-upload"></i>
            </div>
            <div class="upload-text">Click to upload or drag and drop</div>
            <div class="upload-subtext">
              You can upload multiple files (PDF, DOC, DOCX) • Maximum 5MB per file
            </div>
            <input type="file" 
                   id="resumeInput" 
                   name="resume[]" 
                   class="file-input" 
                   accept=".pdf,.doc,.docx" 
                   multiple>
          </div>

          <div class="file-list" id="fileList"></div>
        </div>
      </div>

      <!-- Cover Letter Section -->
      <div class="form-card">
        <div class="form-section">
          <div class="section-header">
            <div class="section-icon">
              <i class="bi bi-envelope"></i>
            </div>
            <div>
              <h3 class="section-title">Cover Letter</h3>
              <div class="section-description">Tell us why you're interested in this position (Optional)</div>
            </div>
          </div>

          <div class="mb-3">
            <label for="coverLetter" class="form-label">
              <i class="bi bi-pencil"></i>
              Your Message
              <span class="optional">(Optional)</span>
            </label>
            <div class="textarea-container">
              <textarea name="cover_letter" 
                        id="coverLetter"
                        class="form-control" 
                        rows="8" 
                        maxlength="2000"
                        placeholder="Dear Hiring Manager,&#10;&#10;I am writing to express my interest in this internship position because..."></textarea>
              <div class="textarea-counter">
                <span id="charCount">0</span>/2000 characters
              </div>
            </div>
            <div class="form-text">
              <i class="bi bi-lightbulb"></i>
              <strong>Tip:</strong> Mention your relevant skills, experiences, and why you're passionate about this opportunity.
            </div>
          </div>
        </div>
      </div>

      <!-- Form Actions -->
      <div class="form-card">
        <div class="form-actions">
          <a href="view-internship.php?internship_id=<?= $internship_id ?>" class="btn-cancel">
            <i class="bi bi-arrow-left"></i>
            Back to Details
          </a>
          <button type="submit" class="btn-submit" id="submitBtn">
            <i class="bi bi-send"></i>
            Submit Application
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const fileInput = document.getElementById('resumeInput');
  const fileList = document.getElementById('fileList');
  const uploadArea = document.querySelector('.file-upload-area');
  const coverLetter = document.getElementById('coverLetter');
  const charCount = document.getElementById('charCount');
  const submitBtn = document.getElementById('submitBtn');
  const form = document.getElementById('applicationForm');
  
  let selectedFiles = [];

  // Character counter for cover letter
  coverLetter.addEventListener('input', function() {
    charCount.textContent = this.value.length;
    if (this.value.length > 1800) {
      charCount.style.color = 'var(--warning-color)';
    } else if (this.value.length > 1900) {
      charCount.style.color = 'var(--danger-color)';
    } else {
      charCount.style.color = 'var(--text-muted)';
    }
  });

  // File upload handling
  fileInput.addEventListener('change', function(e) {
    handleFiles(e.target.files);
  });

  // Drag and drop handling
  uploadArea.addEventListener('dragover', function(e) {
    e.preventDefault();
    this.classList.add('dragover');
  });

  uploadArea.addEventListener('dragleave', function(e) {
    e.preventDefault();
    this.classList.remove('dragover');
  });

  uploadArea.addEventListener('drop', function(e) {
    e.preventDefault();
    this.classList.remove('dragover');
    handleFiles(e.dataTransfer.files);
  });

  function handleFiles(files) {
    const maxFiles = 3;
    const maxSize = 5 * 1024 * 1024; // 5MB
    const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    
    if (selectedFiles.length + files.length > maxFiles) {
      alert(`Maximum ${maxFiles} files allowed.`);
      return;
    }

    Array.from(files).forEach(file => {
      if (!allowedTypes.includes(file.type)) {
        alert(`${file.name} is not a valid file type. Please upload PDF, DOC, or DOCX files.`);
        return;
      }

      if (file.size > maxSize) {
        alert(`${file.name} is too large. Maximum size is 5MB.`);
        return;
      }

      selectedFiles.push(file);
      displayFile(file, selectedFiles.length - 1);
    });

    updateSubmitButton();
  }

  function displayFile(file, index) {
    const fileItem = document.createElement('div');
    fileItem.className = 'file-item';
    fileItem.innerHTML = `
      <div class="file-icon">
        <i class="bi bi-file-earmark-text"></i>
      </div>
      <div class="file-details">
        <div class="file-name">${file.name}</div>
        <div class="file-size">${formatFileSize(file.size)}</div>
      </div>
      <button type="button" class="file-remove" onclick="removeFile(${index})">
        <i class="bi bi-x"></i>
      </button>
    `;
    fileList.appendChild(fileItem);
  }

  window.removeFile = function(index) {
    selectedFiles.splice(index, 1);
    updateFileDisplay();
    updateSubmitButton();
  };

  function updateFileDisplay() {
    fileList.innerHTML = '';
    selectedFiles.forEach((file, index) => {
      displayFile(file, index);
    });
  }

  function updateSubmitButton() {
    if (selectedFiles.length === 0) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="bi bi-exclamation-circle"></i> Please upload at least one file';
    } else {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="bi bi-send"></i> Submit Application';
    }
  }

  function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }

  // Form submission handling
  form.addEventListener('submit', function(e) {
    if (selectedFiles.length === 0) {
      e.preventDefault();
      alert('Please upload at least one resume/CV file before submitting.');
      return;
    }

    // Create new FormData with selected files
    const formData = new FormData(this);
    formData.delete('resume[]'); // Remove the original file input data
    
    selectedFiles.forEach((file, index) => {
      formData.append('resume[]', file);
    });

    // Update button state
    submitBtn.classList.add('btn-loading');
    submitBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Submitting...';
    submitBtn.disabled = true;

    // Submit form with fetch API
    fetch(this.action, {
      method: 'POST',
      body: formData
    })
    .then(response => response.text())
    .then(data => {
      // Reload page to show results
      window.location.reload();
    })
    .catch(error => {
      console.error('Error:', error);
      submitBtn.classList.remove('btn-loading');
      submitBtn.innerHTML = '<i class="bi bi-send"></i> Submit Application';
      submitBtn.disabled = false;
      alert('An error occurred. Please try again.');
    });

    e.preventDefault();
  });

  // Initialize submit button state
  updateSubmitButton();

  // Auto-resize textarea
  coverLetter.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = this.scrollHeight + 'px';
  });
});
</script>

</body>
</html>