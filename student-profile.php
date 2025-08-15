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
  } else {
    // Resume replace (optional)
    $resume_filename = $student['resume'];
    if (!empty($_FILES['resume']['name'])) {
      $allowed = ['pdf','doc','docx'];
      $ext = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext,$allowed)) {
        $msg = "Resume must be PDF/DOC/DOCX.";
      } elseif ($_FILES['resume']['size'] > 5*1024*1024) {
        $msg = "Resume must be ≤ 5MB.";
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
        }
      }
    }

    if ($msg === "") {
      $u = $conn->prepare("
        UPDATE students SET
          full_name=?, department=?, program=?, level=?, field_of_interest=?, skills=?, gpa=?, resume=?, preferences=?
        WHERE user_id=?
      ");
      // types: 7 strings + d + s + s + int? Let's calculate:
      // full_name(s) department(s) program(s) level(s) field_of_interest(s) skills(s) gpa(d) resume(s) preferences(s) user_id(i)
      $u->bind_param(
        "sssssssdsi",
        $full_name, $department, $program, $level, $field_of_interest, $skills, $gpa, $resume_filename, $preferences, $user_id
      );
      if ($u->execute()) {
        $msg = "Profile updated!";
        $student = array_merge($student, compact('full_name','department','program','level','field_of_interest','skills','preferences'));
        $student['gpa'] = $gpa;
        $student['resume'] = $resume_filename;
        $_SESSION['display_name'] = $full_name; // keep navbar in sync
      } else {
        $msg = "Update failed.";
      }
    }
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>My Profile</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>body{margin:0}.main{margin-left:250px;padding:2rem;background:#f8f9fa;min-height:100vh}</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="main">
  <h2 class="mb-4">My Profile</h2>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Full Name</label>
        <input name="full_name" class="form-control" value="<?= htmlspecialchars($student['full_name']) ?>" required>
      </div>

      <div class="col-md-6">
        <label class="form-label">Student Number</label>
        <input class="form-control" value="<?= htmlspecialchars($student['student_number']) ?>" readonly>
        <div class="form-text">Institution ID (not editable)</div>
      </div>

      <div class="col-md-6">
        <label class="form-label">Program</label>
        <input name="program" class="form-control" value="<?= htmlspecialchars($student['program']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Department</label>
        <input name="department" class="form-control" value="<?= htmlspecialchars($student['department']) ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Level</label>
        <input name="level" class="form-control" value="<?= htmlspecialchars($student['level']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">GPA</label>
        <input name="gpa" class="form-control" value="<?= htmlspecialchars($student['gpa']) ?>" type="number" step="0.01" min="0" max="4" placeholder="e.g., 3.45">
      </div>

      <div class="col-md-6">
        <label class="form-label">Field of Interest</label>
        <input name="field_of_interest" class="form-control" value="<?= htmlspecialchars($student['field_of_interest']) ?>" placeholder="e.g., Data Science, UI/UX">
      </div>
      <div class="col-md-6">
        <label class="form-label">Resume (replace)</label>
        <input type="file" name="resume" class="form-control" accept=".pdf,.doc,.docx">
        <div class="form-text">
          <?php if (!empty($student['resume'])): ?>
            Current: <a href="uploads/resumes/<?= rawurlencode($student['resume']) ?>" target="_blank">Download</a>
          <?php else: ?>
            No resume uploaded.
          <?php endif; ?>
        </div>
      </div>

      <div class="col-12">
        <label class="form-label">Skills</label>
        <textarea name="skills" class="form-control" rows="3"><?= htmlspecialchars($student['skills']) ?></textarea>
      </div>

      <div class="col-12">
        <label class="form-label">Preferences</label>
        <textarea name="preferences" class="form-control" rows="3" placeholder="E.g., Prefer hybrid roles, 3–6 months"><?= htmlspecialchars($student['preferences']) ?></textarea>
      </div>

      <div class="col-md-6">
        <label class="form-label">Email</label>
        <input class="form-control" value="<?= htmlspecialchars($student['email']) ?>" readonly>
      </div>
    </div>
    <button class="btn btn-primary mt-3">Save Changes</button>
  </form>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
