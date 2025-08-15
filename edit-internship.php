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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title        = $_POST['title'] ?? $internship['title'];
    $description  = $_POST['description'] ?? $internship['description'];
    $requirements = $_POST['requirements'] ?? $internship['requirements'];
    $duration     = $_POST['duration'] ?? $internship['duration'];
    $location     = $_POST['location'] ?? $internship['location'];
    $type         = $_POST['type'] ?? $internship['type'];
    $deadline     = $_POST['deadline'] ?? $internship['deadline'];

    // validate type
    $allowedTypes = ['in-person','hybrid','virtual'];
    if (!in_array($type, $allowedTypes, true)) {
        $type = $internship['type'] ?: 'in-person';
    }

    // poster handling (keep old unless replaced)
    $poster_filename = $internship['poster'];

    if (!empty($_FILES['poster']['name'])) {
        $allowedImg = ['jpg','jpeg','png','gif','webp'];
        $ext = strtolower(pathinfo($_FILES['poster']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedImg, true)) {
            $message = "Poster must be an image (JPG, PNG, GIF, WEBP).";
        } elseif (!is_uploaded_file($_FILES['poster']['tmp_name'])) {
            $message = "Invalid file upload.";
        } else {
            $upload_dir = __DIR__ . "/uploads/";
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

            $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES['poster']['name']));
            $new_name = time() . '_' . $safeName;
            $target_path = $upload_dir . $new_name;

            if (move_uploaded_file($_FILES['poster']['tmp_name'], $target_path)) {
                // remove old if exists
                if (!empty($poster_filename)) {
                    $old = $upload_dir . $poster_filename;
                    if (is_file($old)) { @unlink($old); }
                }
                $poster_filename = $new_name;
            } else {
                $message = "Failed to upload new poster.";
            }
        }
    }

    if ($message === "") {
        $upd = $conn->prepare("
            UPDATE internships
               SET title = ?, description = ?, requirements = ?, duration = ?, location = ?, type = ?, deadline = ?, poster = ?
             WHERE internship_id = ? AND employer_id = ?
        ");
        // 8 strings + 2 ints
        $upd->bind_param(
            "ssssssssii",
            $title, $description, $requirements, $duration, $location, $type, $deadline, $poster_filename,
            $internship_id, $employer_id
        );

        if ($upd->execute()) {
            $message = "Internship updated successfully!";
            // refresh
            $fetch->execute();
            $internship = $fetch->get_result()->fetch_assoc();
        } else {
            $message = "Error updating internship: " . $upd->error;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Edit Internship</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { margin: 0; }
    .main-content { margin-left: 250px; padding: 2rem; background: #f8f9fa; min-height: 100vh; }
    .poster-preview { max-height: 180px; object-fit: cover; border: 1px solid #ddd; }
  </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="main-content">
  <h2 class="mb-4">Edit Internship</h2>

  <?php if ($message): ?>
    <div class="alert <?= strpos($message,'successfully')!==false ? 'alert-success' : 'alert-info' ?>">
      <?= htmlspecialchars($message) ?>
    </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <div class="mb-3">
      <label class="form-label">Internship Title</label>
      <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($internship['title']) ?>" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Description</label>
      <textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($internship['description']) ?></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Requirements</label>
      <textarea name="requirements" class="form-control" rows="3" required><?= htmlspecialchars($internship['requirements']) ?></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Duration</label>
      <input type="text" name="duration" class="form-control" value="<?= htmlspecialchars($internship['duration']) ?>" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Location</label>
      <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($internship['location']) ?>" required>
    </div>

    <!-- TYPE placed after Location -->
    <div class="mb-3">
      <label class="form-label">Type</label>
      <select name="type" class="form-select" required>
        <option value="in-person" <?= ($internship['type']==='in-person'?'selected':'') ?>>In-person</option>
        <option value="hybrid"    <?= ($internship['type']==='hybrid'   ?'selected':'') ?>>Hybrid</option>
        <option value="virtual"   <?= ($internship['type']==='virtual'  ?'selected':'') ?>>Virtual</option>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Application Deadline</label>
      <input type="date" name="deadline" class="form-control" value="<?= htmlspecialchars($internship['deadline']) ?>" required>
    </div>

    <div class="mb-3">
      <label class="form-label d-block">Current Poster</label>
      <?php if (!empty($internship['poster'])): ?>
        <img src="uploads/<?= htmlspecialchars($internship['poster']) ?>" class="poster-preview rounded mb-2" alt="Current Poster">
      <?php else: ?>
        <span class="text-muted">No poster uploaded.</span>
      <?php endif; ?>
    </div>

    <div class="mb-3">
      <label class="form-label">Upload New Poster (optional)</label>
      <input type="file" name="poster" class="form-control" accept="image/*">
    </div>

    <button type="submit" class="btn btn-primary">Update Internship</button>
    <a href="employer-dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
  </form>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
