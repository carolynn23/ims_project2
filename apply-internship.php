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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $cover = $_POST['cover_letter'] ?? '';

  // prevent duplicate
  $ck = $conn->prepare("SELECT 1 FROM applications WHERE internship_id=? AND student_id=?");
  $ck->bind_param("ii", $internship_id, $student_id);
  $ck->execute();
  if ($ck->get_result()->num_rows > 0) {
    $msg = "You already applied.";
  } else {
    $resume_name = null;
    if (!empty($_FILES['resume']['name'])) {
      $allowed = ['pdf','doc','docx'];
      $ext = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext,$allowed)) {
        $msg = "Resume must be PDF/DOC/DOCX.";
      } elseif ($_FILES['resume']['size'] > 5*1024*1024) {
        $msg = "Resume must be ≤ 5MB.";
      } else {
        $resume_name = time().'_'.$student_id.'.'.$ext;
        $target = __DIR__ . "/uploads/" . $resume_name;
        if (!move_uploaded_file($_FILES['resume']['tmp_name'], $target)) {
          $msg = "Upload failed.";
        }
      }
    }

    if ($msg === "") {
      $ins = $conn->prepare("INSERT INTO applications (internship_id, student_id, resume, cover_letter) VALUES (?,?,?,?)");
      $ins->bind_param("iiss", $internship_id, $student_id, $resume_name, $cover);
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
        $msg = "Application submitted successfully!";
      } else {
        $msg = "Error submitting application.";
      }
    }
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Apply — <?= htmlspecialchars($title) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>body{margin:0}.main{margin-left:250px;padding:2rem;background:#f8f9fa;min-height:100vh}</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="main">
  <h2 class="mb-1">Apply: <?= htmlspecialchars($title) ?></h2>
  <p class="text-muted mb-4">Attach your resume and write a short cover letter.</p>

  <?php if ($msg): ?>
    <div class="alert <?= str_contains($msg,'success') ? 'alert-success' : 'alert-warning' ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <div class="mb-3">
      <label class="form-label">Resume (PDF/DOC/DOCX, ≤5MB)</label>
      <input type="file" name="resume" class="form-control" accept=".pdf,.doc,.docx">
    </div>
    <div class="mb-3">
      <label class="form-label">Cover Letter</label>
      <textarea name="cover_letter" class="form-control" rows="6" required></textarea>
    </div>
    <button class="btn btn-primary">Submit Application</button>
    <a href="student-dashboard.php" class="btn btn-outline-secondary">Cancel</a>
  </form>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
