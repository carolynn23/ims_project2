<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') { header('Location: login.php'); exit(); }

$u = (int)$_SESSION['user_id'];
// get student_id
$s = $conn->prepare("SELECT student_id FROM students WHERE user_id=?");
$s->bind_param("i",$u);
$s->execute();
$student_id = (int)($s->get_result()->fetch_assoc()['student_id'] ?? 0);

$sql = "
  SELECT i.internship_id, i.title, i.description, i.poster, i.location, i.type, i.deadline, e.company_name
  FROM saved_internships s
  JOIN internships i ON s.internship_id = i.internship_id
  JOIN employers e   ON i.employer_id   = e.employer_id
  WHERE s.student_id = ?
  ORDER BY s.saved_at DESC
";
$q = $conn->prepare($sql);
$q->bind_param("i",$student_id);
$q->execute();
$list = $q->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Saved Internships</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>body{margin:0}.main{margin-left:250px;padding:2rem;background:#f8f9fa;min-height:100vh}.card-img-top{height:160px;object-fit:cover}</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="main">
  <h2 class="mb-4">Saved Internships</h2>

  <?php if ($list->num_rows === 0): ?>
    <p class="text-muted">No saved internships yet.</p>
  <?php else: ?>
    <div class="row g-3">
      <?php while ($row = $list->fetch_assoc()): ?>
        <div class="col-md-6 col-lg-4">
          <div class="card h-100">
            <?php if (!empty($row['poster'])): ?>
              <img src="uploads/<?= rawurlencode($row['poster']) ?>" class="card-img-top" alt="poster">
            <?php else: ?>
              <img src="https://placehold.co/600x400?text=No+Poster" class="card-img-top" alt="no poster">
            <?php endif; ?>
            <div class="card-body d-flex flex-column">
              <h5 class="card-title mb-1"><?= htmlspecialchars($row['title']) ?></h5>
              <div class="small text-muted mb-2"><?= htmlspecialchars($row['company_name']) ?> • <?= htmlspecialchars($row['location']) ?></div>
              <span class="badge bg-secondary align-self-start mb-2"><?= htmlspecialchars(ucfirst($row['type'])) ?></span>
              <p class="card-text"><?= htmlspecialchars(mb_strimwidth($row['description'],0,140,'…')) ?></p>
              <div class="mt-auto small text-muted">Deadline: <?= htmlspecialchars($row['deadline']) ?></div>
            </div>
            <div class="card-footer d-flex gap-2">
              <a href="view-internship.php?internship_id=<?= (int)$row['internship_id'] ?>" class="btn btn-outline-secondary btn-sm w-50">Details</a>
              <a href="apply-internship.php?internship_id=<?= (int)$row['internship_id'] ?>" class="btn btn-primary btn-sm w-50">Apply</a>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
