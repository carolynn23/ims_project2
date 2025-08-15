<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
  header('Location: login.php'); exit();
}

$user_id = (int)$_SESSION['user_id'];

// which are saved?
$ss = $conn->prepare("SELECT student_id FROM students WHERE user_id=?");
$ss->bind_param("i",$user_id);
$ss->execute();
$student_id = (int)($ss->get_result()->fetch_assoc()['student_id'] ?? 0);

$saved = [];
if ($student_id) {
  $sx = $conn->prepare("SELECT internship_id FROM saved_internships WHERE student_id=?");
  $sx->bind_param("i",$student_id);
  $sx->execute();
  $res = $sx->get_result();
  while ($r = $res->fetch_assoc()) $saved[(int)$r['internship_id']] = true;
}

// filters
$q    = trim($_GET['q']   ?? '');
$loc  = trim($_GET['loc'] ?? '');
$type = trim($_GET['type']?? '');
$today = date('Y-m-d');

// build query
$sql = "
  SELECT i.internship_id, i.title, i.description, i.requirements, i.duration,
         i.location, i.type, i.deadline, i.poster, e.company_name
  FROM internships i
  JOIN employers e ON i.employer_id = e.employer_id
  WHERE i.deadline >= ?
";
$params = [$today]; $types = "s";

if ($q !== '')   { $sql .= " AND (i.title LIKE CONCAT('%',?,'%') OR e.company_name LIKE CONCAT('%',?,'%') OR i.description LIKE CONCAT('%',?,'%'))";
                   $params[]=$q; $params[]=$q; $params[]=$q; $types .="sss"; }
if ($loc !== '') { $sql .= " AND i.location LIKE CONCAT('%',?,'%')"; $params[]=$loc; $types.="s"; }
if ($type!=='')  { $sql .= " AND i.type = ?"; $params[]=$type; $types.="s"; }

$sql .= " ORDER BY i.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$list = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Student Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{margin:0}
    .main{margin-left:250px;padding:2rem;background:#f8f9fa;min-height:100vh}
    .card-img-top{height:160px;object-fit:cover}
    .truncate-2{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
   
    .star-overlay {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(255, 255, 255, 0.85);
    border-radius: 50%;
    padding: 4px;
    font-size: 1.2rem;
    color: gold;
    z-index: 5;
    }
    .card {
        position: relative;
    }

  </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="main">
  <h2 class="mb-4">Find Internships</h2>
  <form class="row g-2 mb-4" method="get">
    <div class="col-md-5"><input class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search title, company, description"></div>
    <div class="col-md-3"><input class="form-control" name="loc" value="<?= htmlspecialchars($loc) ?>" placeholder="Location"></div>
    <div class="col-md-2">
      <select name="type" class="form-select">
        <option value="">Any type</option>
        <option value="in-person" <?= $type==='in-person'?'selected':'' ?>>In-person</option>
        <option value="hybrid"    <?= $type==='hybrid'?'selected':'' ?>>Hybrid</option>
        <option value="virtual"   <?= $type==='virtual'?'selected':'' ?>>Virtual</option>
      </select>
    </div>
    <div class="col-md-2 d-grid"><button class="btn btn-primary">Search</button></div>
  </form>

  <?php if ($list->num_rows === 0): ?>
    <p class="text-muted">No internships match your filters.</p>
  <?php else: ?>
    <div class="row g-3">
      <?php while ($row = $list->fetch_assoc()): ?>
        <div class="col-md-6 col-lg-4">
          <div class="card h-100">
            <?php if (!empty($row['poster'])): ?>
              <div class="star-overlay">★</div>
              <img src="uploads/<?= rawurlencode($row['poster']) ?>" class="card-img-top" alt="poster">
            <?php else: ?>
              <img src="https://placehold.co/600x400?text=No+Poster" class="card-img-top" alt="no poster">
            <?php endif; ?>
            <div class="card-body d-flex flex-column">
              <h5 class="card-title mb-1"><?= htmlspecialchars($row['title']) ?></h5>
              <div class="small text-muted mb-2"><?= htmlspecialchars($row['company_name']) ?> • <?= htmlspecialchars($row['location']) ?></div>
              <span class="badge bg-secondary align-self-start mb-2"><?= htmlspecialchars(ucfirst($row['type'])) ?></span>
              <p class="card-text truncate-2"><?= htmlspecialchars($row['description']) ?></p>
              <div class="mt-auto small text-muted">Deadline: <?= htmlspecialchars($row['deadline']) ?></div>
            </div>
            <div class="card-footer d-flex gap-2">
              <a href="view-internship.php?internship_id=<?= (int)$row['internship_id'] ?>" class="btn btn-outline-secondary btn-sm w-50">Details</a>
              <form method="post" action="toggle-save.php" class="w-50">
                <input type="hidden" name="internship_id" value="<?= (int)$row['internship_id'] ?>">
                <input type="hidden" name="back" value="student-dashboard.php">
                <button class="btn btn-outline-primary btn-sm w-100">
                  <?= isset($saved[(int)$row['internship_id']]) ? 'Unsave' : 'Save' ?>
                </button>
              </form>
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
