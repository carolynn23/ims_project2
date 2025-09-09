<?php
session_start();
require_once 'config.php';

// Guard: employer only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Employer details
$emp = $conn->prepare("SELECT employer_id, company_name FROM employers WHERE user_id = ?");
$emp->bind_param("i", $user_id);
$emp->execute();
$employer = $emp->get_result()->fetch_assoc();
if (!$employer) { echo "Employer not found."; exit(); }

$employer_id  = (int)$employer['employer_id'];
$company_name = $employer['company_name'];
$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $posting_style = $_POST['posting_style'] ?? 'full_details';
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $type = $_POST['type'] ?? 'in-person';
    $deadline = $_POST['deadline'] ?? '';

    // Validate posting style
    $allowed_styles = ['poster_only', 'details_only', 'poster_with_details'];
    if (!in_array($posting_style, $allowed_styles)) {
        $posting_style = 'details_only';
    }

    // Validate type
    $allowedTypes = ['in-person', 'hybrid', 'virtual'];
    if (!in_array($type, $allowedTypes, true)) {
        $type = 'in-person';
    }

    // Validation based on posting style
    $validation_errors = [];
    
    // Title is always required for identification
    if (empty($title)) {
        $validation_errors[] = "Internship title is required.";
    }

    // Deadline is always required
    if (empty($deadline)) {
        $validation_errors[] = "Application deadline is required.";
    } elseif (strtotime($deadline) <= time()) {
        $validation_errors[] = "Application deadline must be in the future.";
    }

    // Style-specific validation
    switch ($posting_style) {
        case 'poster_only':
            // Only poster and basic info required
            if (empty($_FILES['poster']['name'])) {
                $validation_errors[] = "Please upload a poster for poster-only posting.";
            }
            // Make other fields optional but provide defaults
            if (empty($location)) $location = "See poster for details";
            if (empty($duration)) $duration = "See poster for details";
            if (empty($description)) $description = "Please refer to the attached poster for complete details about this internship opportunity.";
            if (empty($requirements)) $requirements = "Please refer to the attached poster for application requirements.";
            break;

        case 'details_only':
            // All text details required
            if (empty($description)) $validation_errors[] = "Description is required for details-only posting.";
            if (empty($requirements)) $validation_errors[] = "Requirements are required for details-only posting.";
            if (empty($duration)) $validation_errors[] = "Duration is required for details-only posting.";
            if (empty($location)) $validation_errors[] = "Location is required for details-only posting.";
            break;

        case 'poster_with_details':
            // Both poster and some details required
            if (empty($_FILES['poster']['name'])) {
                $validation_errors[] = "Please upload a poster for poster+details posting.";
            }
            // At least basic details required
            if (empty($description) && empty($requirements)) {
                $validation_errors[] = "Please provide either description or requirements when posting with details.";
            }
            if (empty($location)) $location = "See poster for details";
            if (empty($duration)) $duration = "See poster for details";
            break;
    }

    if (!empty($validation_errors)) {
        $message = implode("<br>", $validation_errors);
        $message_type = "danger";
    } else {
        // Handle poster upload
        $poster_filename = null;
        if (!empty($_FILES['poster']['name'])) {
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['poster']['type'];
            $file_size = $_FILES['poster']['size'];
            $max_size = 10 * 1024 * 1024; // 10MB

            if (!in_array($file_type, $allowed_types)) {
                $message = "Please upload a valid image file (JPG, PNG, GIF, WEBP).";
                $message_type = "danger";
            } elseif ($file_size > $max_size) {
                $message = "Poster file size must be less than 10MB.";
                $message_type = "danger";
            } else {
                $upload_dir = __DIR__ . "/uploads/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = pathinfo($_FILES['poster']['name'], PATHINFO_EXTENSION);
                $poster_filename = time() . '_' . $employer_id . '_poster.' . $file_extension;
                $target_path = $upload_dir . $poster_filename;

                if (!move_uploaded_file($_FILES['poster']['tmp_name'], $target_path)) {
                    $message = "Failed to upload poster. Please try again.";
                    $message_type = "danger";
                    $poster_filename = null;
                }
            }
        }

        // If no upload errors, proceed with database insertion
        if ($message_type !== "danger") {
            try {
                // Check if posting_style column exists
                $column_check = $conn->query("SHOW COLUMNS FROM internships LIKE 'posting_style'");
                $has_posting_style = $column_check->num_rows > 0;
                
                if ($has_posting_style) {
                    // Use new query with posting_style column
                    $stmt = $conn->prepare("
                        INSERT INTO internships 
                        (employer_id, title, description, requirements, duration, location, type, deadline, poster, posting_style)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->bind_param(
                        "isssssssss",
                        $employer_id, $title, $description, $requirements, $duration, $location, $type, $deadline, $poster_filename, $posting_style
                    );
                } else {
                    // Use original query without posting_style column
                    $stmt = $conn->prepare("
                        INSERT INTO internships 
                        (employer_id, title, description, requirements, duration, location, type, deadline, poster)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->bind_param(
                        "issssssss",
                        $employer_id, $title, $description, $requirements, $duration, $location, $type, $deadline, $poster_filename
                    );
                }

                if ($stmt->execute()) {
                    $message = "🎉 Internship posted successfully! Students can now view and apply for your position.";
                    $message_type = "success";
                    
                    // Clear form data on success
                    $_POST = array();
                } else {
                    $message = "Database error: " . $stmt->error;
                    $message_type = "danger";
                }
            } catch (Exception $e) {
                $message = "Error posting internship: " . $e->getMessage();
                $message_type = "danger";
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
    <title>Post New Internship - <?= htmlspecialchars($company_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { 
            margin: 0; 
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        .main-content {
            margin-left: 250px;
            padding: 2rem;
            min-height: 100vh;
        }
        .form-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 0;
            overflow: hidden;
            margin-bottom: 2rem;
        }
        .form-header {
            background: linear-gradient(135deg, #696cff 0%, #7367f0 100%);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .form-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        .form-body {
            padding: 2rem;
        }
        .style-selector {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 2px solid #e9ecef;
        }
        .style-option {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        .style-option:hover {
            border-color: #696cff;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(105, 108, 255, 0.15);
        }
        .style-option.active {
            border-color: #696cff;
            background: linear-gradient(135deg, rgba(105, 108, 255, 0.1) 0%, rgba(115, 103, 240, 0.1) 100%);
        }
        .style-option .check-mark {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #696cff;
            color: white;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        .style-option.active .check-mark {
            display: flex;
        }
        .form-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid #696cff;
            transition: all 0.3s ease;
        }
        .form-section.hidden {
            display: none;
        }
        .section-title {
            color: #495057;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #696cff;
            box-shadow: 0 0 0 0.2rem rgba(105, 108, 255, 0.25);
        }
        .file-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            background: white;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .file-upload-area:hover {
            border-color: #696cff;
            background: rgba(105, 108, 255, 0.05);
        }
        .btn-submit {
            background: linear-gradient(135deg, #696cff 0%, #7367f0 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(105, 108, 255, 0.3);
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(105, 108, 255, 0.4);
            color: white;
        }
        .alert {
            border: none;
            border-radius: 12px;
            font-weight: 500;
            margin-bottom: 1.5rem;
        }
        .requirement-tag {
            background: #ffeaa7;
            color: #2d3436;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .optional-tag {
            background: #74b9ff;
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 1rem; }
            .form-header { padding: 1.5rem; }
            .form-body { padding: 1.5rem; }
        }
    </style>
</head>

<body>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="main-content">
    <div class="form-container">
        <!-- Form Header -->
        <div class="form-header">
            <h2 class="mb-2">
                <i class="bi bi-plus-circle me-2"></i>
                Post New Internship
            </h2>
            <p class="mb-0 opacity-75">
                Share opportunities and connect with talented students
            </p>
        </div>

        <!-- Form Body -->
        <div class="form-body">
            <!-- Success/Error Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="postInternshipForm">
                <!-- Posting Style Selector -->
                <div class="style-selector">
                    <h5 class="mb-3">
                        <i class="bi bi-palette me-2"></i>
                        Choose Your Posting Style
                    </h5>
                    <p class="text-muted mb-3">Select how you'd like to post your internship opportunity:</p>

                    <div class="style-option" data-style="poster_only">
                        <div class="check-mark">
                            <i class="bi bi-check"></i>
                        </div>
                        <h6 class="mb-2">
                            <i class="bi bi-image me-2 text-primary"></i>
                            Poster Only
                        </h6>
                        <p class="text-muted mb-0">
                            I have a comprehensive poster with all the details. Minimal additional information needed.
                        </p>
                    </div>

                    <div class="style-option" data-style="details_only">
                        <div class="check-mark">
                            <i class="bi bi-check"></i>
                        </div>
                        <h6 class="mb-2">
                            <i class="bi bi-file-text me-2 text-success"></i>
                            Details Only
                        </h6>
                        <p class="text-muted mb-0">
                            I want to provide all information through text fields. No poster needed.
                        </p>
                    </div>

                    <div class="style-option" data-style="poster_with_details">
                        <div class="check-mark">
                            <i class="bi bi-check"></i>
                        </div>
                        <h6 class="mb-2">
                            <i class="bi bi-files me-2 text-warning"></i>
                            Poster + Additional Details
                        </h6>
                        <p class="text-muted mb-0">
                            I have a poster and want to add extra information or specific requirements.
                        </p>
                    </div>

                    <input type="hidden" name="posting_style" id="posting_style" value="details_only">
                </div>

                <!-- Basic Information (Always Required) -->
                <div class="form-section" id="basic_info">
                    <h5 class="section-title">
                        <i class="bi bi-info-circle"></i>
                        Basic Information
                        <span class="requirement-tag">Required</span>
                    </h5>
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Internship Title *</label>
                            <input type="text" name="title" class="form-control" 
                                   placeholder="e.g., Software Development Intern"
                                   value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Work Type *</label>
                            <select name="type" class="form-select" required>
                                <option value="in-person" <?= ($_POST['type'] ?? '') === 'in-person' ? 'selected' : '' ?>>In-Person</option>
                                <option value="hybrid" <?= ($_POST['type'] ?? '') === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                                <option value="virtual" <?= ($_POST['type'] ?? '') === 'virtual' ? 'selected' : '' ?>>Remote</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Application Deadline *</label>
                        <input type="date" name="deadline" class="form-control" 
                               min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                               value="<?= htmlspecialchars($_POST['deadline'] ?? '') ?>" required>
                    </div>
                </div>

                <!-- Poster Upload Section -->
                <div class="form-section" id="poster_section">
                    <h5 class="section-title">
                        <i class="bi bi-image"></i>
                        Internship Poster
                        <span class="requirement-tag" id="poster_requirement">Required</span>
                    </h5>
                    
                    <div class="file-upload-area" id="fileUploadArea">
                        <i class="bi bi-cloud-upload display-4 text-muted mb-3"></i>
                        <h6>Upload Internship Poster</h6>
                        <p class="text-muted mb-2">Drag & drop your poster here, or click to browse</p>
                        <input type="file" name="poster" id="posterFile" class="form-control" 
                               accept="image/*" style="display: none;">
                        <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('posterFile').click();">
                            <i class="bi bi-folder2-open me-1"></i>
                            Choose File
                        </button>
                        <small class="d-block text-muted mt-2">Supported: JPG, PNG, GIF, WEBP (Max 10MB)</small>
                    </div>
                    
                    <div id="imagePreview" class="preview-area" style="display: none;">
                        <img id="previewImg" src="" alt="Preview" style="max-width: 100%; height: auto; border-radius: 8px;">
                        <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="removeImage()">
                            <i class="bi bi-trash"></i> Remove
                        </button>
                    </div>
                </div>

                <!-- Detailed Information Section -->
                <div class="form-section" id="details_section">
                    <h5 class="section-title">
                        <i class="bi bi-file-text"></i>
                        Detailed Information
                        <span class="optional-tag" id="details_requirement">Required</span>
                    </h5>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Duration</label>
                            <input type="text" name="duration" class="form-control" 
                                   placeholder="e.g., 3 months, Summer 2024"
                                   value="<?= htmlspecialchars($_POST['duration'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control" 
                                   placeholder="e.g., Accra, Remote, Hybrid"
                                   value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4" 
                                  placeholder="Describe the internship role, responsibilities, and what students will learn..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Requirements</label>
                        <textarea name="requirements" class="form-control" rows="3" 
                                  placeholder="List the skills, qualifications, or experience required..."><?= htmlspecialchars($_POST['requirements'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="text-center pt-3">
                    <button type="submit" class="btn btn-submit">
                        <i class="bi bi-send me-2"></i>
                        Post Internship
                    </button>
                    <a href="employer-dashboard.php" class="btn btn-outline-secondary ms-3">
                        <i class="bi bi-arrow-left me-1"></i>
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const styleOptions = document.querySelectorAll('.style-option');
    const postingStyleInput = document.getElementById('posting_style');
    const posterSection = document.getElementById('poster_section');
    const detailsSection = document.getElementById('details_section');
    const posterRequirement = document.getElementById('poster_requirement');
    const detailsRequirement = document.getElementById('details_requirement');
    const posterFile = document.getElementById('posterFile');

    // Style selection handling
    styleOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Remove active class from all options
            styleOptions.forEach(opt => opt.classList.remove('active'));
            
            // Add active class to clicked option
            this.classList.add('active');
            
            // Update hidden input
            const style = this.getAttribute('data-style');
            postingStyleInput.value = style;
            
            // Update form sections based on style
            updateFormSections(style);
        });
    });

    function updateFormSections(style) {
        const posterInput = document.querySelector('input[name="poster"]');
        const requiredFields = ['description', 'requirements', 'duration', 'location'];

        switch (style) {
            case 'poster_only':
                posterSection.classList.remove('hidden');
                detailsSection.classList.remove('hidden');
                posterRequirement.textContent = 'Required';
                posterRequirement.className = 'requirement-tag';
                detailsRequirement.textContent = 'Optional';
                detailsRequirement.className = 'optional-tag';
                
                // Make poster required, details optional
                posterInput.required = true;
                requiredFields.forEach(field => {
                    const input = document.querySelector(`[name="${field}"]`);
                    if (input) input.required = false;
                });
                break;

            case 'details_only':
                posterSection.classList.add('hidden');
                detailsSection.classList.remove('hidden');
                detailsRequirement.textContent = 'Required';
                detailsRequirement.className = 'requirement-tag';
                
                // Make details required, poster optional
                posterInput.required = false;
                requiredFields.forEach(field => {
                    const input = document.querySelector(`[name="${field}"]`);
                    if (input) input.required = true;
                });
                break;

            case 'poster_with_details':
                posterSection.classList.remove('hidden');
                detailsSection.classList.remove('hidden');
                posterRequirement.textContent = 'Required';
                posterRequirement.className = 'requirement-tag';
                detailsRequirement.textContent = 'Optional';
                detailsRequirement.className = 'optional-tag';
                
                // Make poster required, some details optional
                posterInput.required = true;
                requiredFields.forEach(field => {
                    const input = document.querySelector(`[name="${field}"]`);
                    if (input && (field === 'description' || field === 'requirements')) {
                        input.required = false; // At least one should be filled (handled by PHP)
                    } else if (input) {
                        input.required = false;
                    }
                });
                break;
        }
    }

    // Set default style (details_only)
    document.querySelector('[data-style="details_only"]').classList.add('active');
    updateFormSections('details_only');

    // File upload handling
    const fileUploadArea = document.getElementById('fileUploadArea');
    const imagePreview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');

    posterFile.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                imagePreview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });

    // Drag and drop functionality
    fileUploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
    });

    fileUploadArea.addEventListener('dragleave', function() {
        this.classList.remove('dragover');
    });

    fileUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            posterFile.files = files;
            posterFile.dispatchEvent(new Event('change'));
        }
    });

    // Click to upload
    fileUploadArea.addEventListener('click', function() {
        posterFile.click();
    });
});

function removeImage() {
    document.getElementById('posterFile').value = '';
    document.getElementById('imagePreview').style.display = 'none';
}

// Form submission handling
document.getElementById('postInternshipForm').addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Posting...';
    submitBtn.disabled = true;
});
</script>

</body>
</html>