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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title        = $_POST['title'] ?? '';
    $description  = $_POST['description'] ?? '';
    $requirements = $_POST['requirements'] ?? '';
    $duration     = $_POST['duration'] ?? '';
    $location     = $_POST['location'] ?? '';
    $type         = $_POST['type'] ?? 'in-person';
    $deadline     = $_POST['deadline'] ?? '';

    // validate type
    $allowedTypes = ['in-person','hybrid','virtual'];
    if (!in_array($type, $allowedTypes, true)) {
        $type = 'in-person';
    }

    // handle poster upload
    $poster_filename = null;
    if (!empty($_FILES['poster']['name'])) {
        $upload_dir = __DIR__ . "/uploads/"; // absolute path
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES['poster']['name']));
        $poster_filename = time() . '_' . $safeName;
        $target_path = $upload_dir . $poster_filename;

        if (!move_uploaded_file($_FILES['poster']['tmp_name'], $target_path)) {
            $message = "Failed to upload poster.";
            $poster_filename = null;
        }
    }

    // insert
    $stmt = $conn->prepare("
        INSERT INTO internships 
        (employer_id, title, description, requirements, duration, location, type, deadline, poster)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "issssssss",
        $employer_id, $title, $description, $requirements, $duration, $location, $type, $deadline, $poster_filename
    );

    if ($stmt->execute()) {
        $message = "Internship posted successfully!";
    } else {
        $message = "Error posting internship: " . $stmt->error;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Post Internship</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { margin: 0; }
    .main-content { margin-left: 250px; padding: 2rem; background-color: #f8f9fa; min-height: 100vh; }
  </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="main-content">
  <h2 class="mb-4">Post a New Internship</h2>

  <?php if ($message): ?>
    <div class="alert <?= strpos($message,'successfully')!==false ? 'alert-success' : 'alert-info' ?>">
      <?= htmlspecialchars($message) ?>
    </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <div class="mb-3">
      <label class="form-label">Internship Title</label>
      <input type="text" name="title" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Description</label>
      <textarea name="description" class="form-control" rows="4" required></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Requirements</label>
      <textarea name="requirements" class="form-control" rows="3" required></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Duration</label>
      <input type="text" name="duration" class="form-control" placeholder="e.g., 3 months" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Location</label>
      <input type="text" name="location" class="form-control" placeholder="e.g., Accra or Remote" required>
    </div>

    <!-- TYPE placed after Location -->
    <div class="mb-3">
      <label class="form-label">Type</label>
      <select name="type" class="form-select" required>
        <option value="in-person">In-person</option>
        <option value="hybrid">Hybrid</option>
        <option value="virtual">Virtual</option>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Application Deadline</label>
      <input type="date" name="deadline" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Upload Internship Poster (optional)</label>
      <input type="file" name="poster" class="form-control" accept="image/*">
    </div>

    <button type="submit" class="btn btn-primary">Post Internship</button>
  </form>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
