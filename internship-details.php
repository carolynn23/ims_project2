<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') { header('Location: login.php'); exit(); }

$internship_id = (int)($_GET['internship_id'] ?? 0);
if ($internship_id <= 0) { echo "Invalid internship."; exit(); }

$sql = "
  SELECT i.*, e.company_name, e.website
  FROM internships i
  JOIN employers e ON i.employer_id = e.employer_id
  WHERE i.internship_id = ?
";
$st = $conn->prepare($sql);
$st->bind_param("i", $internship_id);
$st->execute();
$intern = $st->get_result()->fetch_assoc();
if (!$intern) { echo "Not found."; exit(); }

// compute isSaved
$u = (int)$_SESSION['user_id'];
$ss = $conn->prepare("SELECT student_id FROM students WHERE user_id=?");
$ss->bind_param("i",$u);
$ss->execute();
$student_id = (int)($ss->get_result()->fetch_assoc()['student_id'] ?? 0);
$isSaved = false;
if ($student_id) {
  $sx = $conn->prepare("SELECT 1 FROM saved_internships WHERE student_id=? AND internship_id=?");
  $sx->bind_param("ii",$student_id,$internship_id);
  $sx->execute();
  $isSaved = (bool)$sx->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html>
<head>
  <title><?= htmlspecialchars($intern['title']) ?> — Details</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{margin:0}.main{margin-left:250px;padding:2rem;background:#f8f9fa;min-height:100vh}
    .poster{max-height:320px;object-fit:cover;width:100%} pre{white-space:pre-wrap}
  </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="main">
  <div class="row g-4">
    <div class="col-lg-6">
      <?php if (!empty($intern['poster'])): ?>
        <img src="uploads/<?= htmlspecialchars($intern['poster']) ?>" class="poster rounded shadow" alt="poster">
      <?php else: ?>
        <img src="https://placehold.co/800x500?text=No+Poster" class="poster rounded shadow" alt="no poster">
      <?php endif; ?>
    </div>
    <div class="col-lg-6">
      <h2 class="mb-1"><?= htmlspecialchars($intern['title']) ?></h2>
      <div class="text-muted mb-2"><?= htmlspecialchars($intern['company_name']) ?> • <?= htmlspecialchars($intern['location']) ?></div>
      <div class="mb-1"><strong>Type:</strong> <?= htmlspecialchars(ucfirst($intern['type'])) ?></div>
      <div class="mb-1"><strong>Duration:</strong> <?= htmlspecialchars($intern['duration']) ?></div>
      <div class="mb-3"><strong>Deadline:</strong> <?= htmlspecialchars($intern['deadline']) ?></div>
      <?php if (!empty($intern['website'])): ?>
        <div class="mb-3"><strong>Company Site:</strong> <a href="<?= htmlspecialchars($intern['website']) ?>" target="_blank"><?= htmlspecialchars($intern['website']) ?></a></div>
      <?php endif; ?>
      <a href="apply-internship.php?internship_id=<?= (int)$internship_id ?>" class="btn btn-primary">Apply Now</a>
      <a href="student-dashboard.php" class="btn btn-outline-secondary">Back</a>
      <form method="post" action="toggle-save.php" class="d-inline">
        <input type="hidden" name="internship_id" value="<?= (int)$internship_id ?>">
        <input type="hidden" name="back" value="view-internship.php?internship_id=<?= (int)$internship_id ?>">
        <button class="btn btn-outline-primary"><?= $isSaved ? 'Unsave' : 'Save' ?></button>
      </form>
    </div>
  </div>

  <hr class="my-4">
  <h4>Description</h4>
  <pre><?= htmlspecialchars($intern['description']) ?></pre>

  <h4 class="mt-4">Requirements</h4>
  <pre><?= htmlspecialchars($intern['requirements']) ?></pre>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
