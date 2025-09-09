<?php
session_start();
require_once 'config.php';

// Guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$internship_id = isset($_GET['internship_id']) ? (int)$_GET['internship_id'] : 0;
if ($internship_id <= 0) { echo "Invalid internship ID."; exit(); }

// employer info
$emp = $conn->prepare("SELECT employer_id, company_name FROM employers WHERE user_id = ?");
$emp->bind_param("i", $user_id);
$emp->execute();
$employer = $emp->get_result()->fetch_assoc();
if (!$employer) { echo "Employer not found."; exit(); }
$employer_id  = (int)$employer['employer_id'];
$company_name = $employer['company_name'];

// load internship (verify ownership)
$fetch = $conn->prepare("SELECT * FROM internships WHERE internship_id = ? AND employer_id = ?");
$fetch->bind_param("ii", $internship_id, $employer_id);
$fetch->execute();
$internship = $fetch->get_result()->fetch_assoc();
if (!$internship) { echo "Internship not found or unauthorized."; exit(); }

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $posting_style = $_POST['posting_style'] ?? $internship['posting_style'] ?? 'details_only';
    $title = trim($_POST['title'] ?? $internship['title']);
    $description = trim($_POST['description'] ?? $internship['description']);
    $requirements = trim($_POST['requirements'] ?? $internship['requirements']);
    $duration = trim($_POST['duration'] ?? $internship['duration']);
    $location = trim($_POST['location'] ?? $internship['location']);
    $type = $_POST['type'] ?? $internship['type'];
    $deadline = $_POST['deadline'] ?? $internship['deadline'];

    // validate type
    $allowedTypes = ['in-person','hybrid','virtual'];
    if (!in_array($type, $allowedTypes, true)) {
        $type = $internship['type'] ?: 'in-person';
    }

    // Validation based on posting style
    $validation_errors = [];
    
    if (empty($title)) {
        $validation_errors[] = "Internship title is required.";
    }

    if (empty($deadline)) {
        $validation_errors[] = "Application deadline is required.";
    }

    // Style-specific validation
    switch ($posting_style) {
        case 'poster_only':
            // Check if poster exists (current or new upload)
            if (empty($internship['poster']) && empty($_FILES['poster']['name'])) {
                $validation_errors[] = "Please upload a poster for poster-only posting.";
            }
            break;

        case 'details_only':
            if (empty($description)) $validation_errors[] = "Description is required for details-only posting.";
            if (empty($requirements)) $validation_errors[] = "Requirements are required for details-only posting.";
            if (empty($duration)) $validation_errors[] = "Duration is required for details-only posting.";
            if (empty($location)) $validation_errors[] = "Location is required for details-only posting.";
            break;

        case 'poster_with_details':
            // Check if poster exists (current or new upload)
            if (empty($internship['poster']) && empty($_FILES['poster']['name'])) {
                $validation_errors[] = "Please upload a poster for poster+details posting.";
            }
            break;
    }

    if (!empty($validation_errors)) {
        $message = implode("<br>", $validation_errors);
        $message_type = "danger";
    } else {
        // poster handling (keep old unless replaced)
        $poster_filename = $internship['poster'];

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
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }

                $file_extension = pathinfo($_FILES['poster']['name'], PATHINFO_EXTENSION);
                $new_name = time() . '_' . $employer_id . '_poster.' . $file_extension;
                $target_path = $upload_dir . $new_name;

                if (move_uploaded_file($_FILES['poster']['tmp_name'], $target_path)) {
                    // remove old poster if exists
                    if (!empty($poster_filename) && file_exists($upload_dir . $poster_filename)) {
                        unlink($upload_dir . $poster_filename);
                    }
                    $poster_filename = $new_name;
                } else {
                    $message = "Failed to upload new poster.";
                    $message_type = "danger";
                }
            }
        }

        // If no upload errors, proceed with update
        if ($message_type !== "danger") {
            try {
                $update = $conn->prepare("
                    UPDATE internships 
                    SET title=?, description=?, requirements=?, duration=?, location=?, type=?, deadline=?, poster=?, posting_style=?
                    WHERE internship_id=? AND employer_id=?
                ");
                $update->bind_param(
                    "sssssssssii",
                    $title, $description, $requirements, $duration, $location, $type, $deadline, $poster_filename, $posting_style, $internship_id, $employer_id
                );

                if ($update->execute()) {
                    $message = "✅ Internship updated successfully!";
                    $message_type = "success";
                    
                    // Refresh internship data
                    $fetch->execute();
                    $internship = $fetch->get_result()->fetch_assoc();
                } else {
                    $message = "Database error: " . $update->error;
                    $message_type = "danger";
                }
            } catch (Exception $e) {
                $message = "Error updating internship: " . $e->getMessage();
                $message_type = "danger";
            }
        }
    }
}

// Set current posting style for display
$current_style = $internship['posting_style'] ?? 'details_only';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Internship - <?= htmlspecialchars($internship['title']) ?></title>
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
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 2rem;
            text-align: center;
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
            border-color: #28a745;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.15);
        }
        .style-option.active {
            border-color: #28a745;
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1) 0%, rgba(32, 201, 151, 0.1) 100%);
        }
        .style-option .check-mark {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #28a745;
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
            border-left: 4px solid #28a745;
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
        }
        .form-control:focus, .form-select:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        .current-poster {
            max-width: 300px;
            height: auto;
            border-radius: 10px;
            border: 2px solid #e9ecef;
        }
        .file-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            background: white;
            transition: all 0.3s ease;
        }
        .file-upload-area:hover {
            border-color: #28a745;
            background: rgba(40, 167, 69, 0.05);
        }
        .btn-update {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
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
                <i class="bi bi-pencil-square me-2"></i>
                Edit Internship
            </h2>
            <p class="mb-0 opacity-75">
                Update your internship posting: <?= htmlspecialchars($internship['title']) ?>
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

            <form method="POST" enctype="multipart/form-data" id="editInternshipForm">
                <!-- Posting Style Selector -->
                <div class="style-selector">
                    <h5 class="mb-3">
                        <i class="bi bi-palette me-2"></i>
                        Posting Style
                    </h5>
                    <p class="text-muted mb-3">Current style: <strong><?= ucfirst(str_replace('_', ' ', $current_style)) ?></strong></p>

                    <div class="style-option <?= $current_style === 'poster_only' ? 'active' : '' ?>" data-style="poster_only">
                        <div class="check-mark"><i class="bi bi-check"></i></div>
                        <h6 class="mb-2"><i class="bi bi-image me-2 text-primary"></i>Poster Only</h6>
                        <p class="text-muted mb-0">Comprehensive poster with minimal additional information.</p>
                    </div>

                    <div class="style-option <?= $current_style === 'details_only' ? 'active' : '' ?>" data-style="details_only">
                        <div class="check-mark"><i class="bi bi-check"></i></div>
                        <h6 class="mb-2"><i class="bi bi-file-text me-2 text-success"></i>Details Only</h6>
                        <p class="text-muted mb-0">All information through text fields. No poster needed.</p>
                    </div>

                    <div class="style-option <?= $current_style === 'poster_with_details' ? 'active' : '' ?>" data-style="poster_with_details">
                        <div class="check-mark"><i class="bi bi-check"></i></div>
                        <h6 class="mb-2"><i class="bi bi-files me-2 text-warning"></i>Poster + Additional Details</h6>
                        <p class="text-muted mb-0">Poster with extra information and specific requirements.</p>
                    </div>

                    <input type="hidden" name="posting_style" id="posting_style" value="<?= $current_style ?>">
                </div>

                <!-- Basic Information -->
                <div class="form-section">
                    <h5 class="section-title">
                        <i class="bi bi-info-circle"></i>
                        Basic Information
                        <span class="requirement-tag">Required</span>
                    </h5>
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Internship Title *</label>
                            <input type="text" name="title" class="form-control" 
                                   value="<?= htmlspecialchars($internship['title']) ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Work Type *</label>
                            <select name="type" class="form-select" required>
                                <option value="in-person" <?= $internship['type'] === 'in-person' ? 'selected' : '' ?>>In-Person</option>
                                <option value="hybrid" <?= $internship['type'] === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                                <option value="virtual" <?= $internship['type'] === 'virtual' ? 'selected' : '' ?>>Remote</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Application Deadline *</label>
                        <input type="date" name="deadline" class="form-control" 
                               value="<?= htmlspecialchars($internship['deadline']) ?>" required>
                    </div>
                </div>

                <!-- Poster Section -->
                <div class="form-section" id="poster_section">
                    <h5 class="section-title">
                        <i class="bi bi-image"></i>
                        Internship Poster
                        <span class="requirement-tag" id="poster_requirement">Required</span>
                    </h5>
                    
                    <?php if (!empty($internship['poster'])): ?>
                        <div class="mb-3">
                            <label class="form-label">Current Poster</label>
                            <div>
                                <img src="uploads/<?= htmlspecialchars($internship['poster']) ?>" 
                                     class="current-poster" alt="Current Poster">
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="file-upload-area">
                        <i class="bi bi-cloud-upload display-4 text-muted mb-3"></i>
                        <h6><?= !empty($internship['poster']) ? 'Upload New Poster (Optional)' : 'Upload Poster' ?></h6>
                        <p class="text-muted mb-2">Drag & drop your poster here, or click to browse</p>
                        <input type="file" name="poster" id="posterFile" class="form-control" 
                               accept="image/*" style="display: none;">
                        <button type="button" class="btn btn-outline-success" onclick="document.getElementById('posterFile').click();">
                            <i class="bi bi-folder2-open me-1"></i>
                            Choose File
                        </button>
                        <small class="d-block text-muted mt-2">Supported: JPG, PNG, GIF, WEBP (Max 10MB)</small>
                    </div>
                </div>

                <!-- Details Section -->
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
                                   value="<?= htmlspecialchars($internship['duration']) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control" 
                                   value="<?= htmlspecialchars($internship['location']) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($internship['description']) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Requirements</label>
                        <textarea name="requirements" class="form-control" rows="3"><?= htmlspecialchars($internship['requirements']) ?></textarea>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="text-center pt-3">
                    <button type="submit" class="btn btn-update">
                        <i class="bi bi-check-circle me-2"></i>
                        Update Internship
                    </button>
                    <a href="employer-dashboard.php" class="btn btn-outline-secondary ms-3">
                        <i class="bi bi-arrow-left me-1"></i>
                        Back to Dashboard
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

    // Style selection handling
    styleOptions.forEach(option => {
        option.addEventListener('click', function() {
            styleOptions.forEach(opt => opt.classList.remove('active'));
            this.classList.add('active');
            
            const style = this.getAttribute('data-style');
            postingStyleInput.value = style;
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
                break;

            case 'details_only':
                posterSection.classList.add('hidden');
                detailsSection.classList.remove('hidden');
                detailsRequirement.textContent = 'Required';
                detailsRequirement.className = 'requirement-tag';
                break;

            case 'poster_with_details':
                posterSection.classList.remove('hidden');
                detailsSection.classList.remove('hidden');
                posterRequirement.textContent = 'Required';
                posterRequirement.className = 'requirement-tag';
                detailsRequirement.textContent = 'Optional';
                detailsRequirement.className = 'optional-tag';
                break;
        }
    }

    // Initialize form sections based on current style
    updateFormSections('<?= $current_style ?>');

    // File upload preview
    document.getElementById('posterFile').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // You can add preview functionality here
            console.log('File selected:', file.name);
        }
    });

    // Form submission
    document.getElementById('editInternshipForm').addEventListener('submit', function(e) {
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Updating...';
        submitBtn.disabled = true;
    });
});
</script>

</body>
</html>