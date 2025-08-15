<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') { header('Location: login.php'); exit(); }

$user_id = (int)$_SESSION['user_id'];
// get student_id
$s = $conn->prepare("SELECT student_id FROM students WHERE user_id=?");
$s->bind_param("i",$user_id);
$s->execute();
$student_id = (int)($s->get_result()->fetch_assoc()['student_id'] ?? 0);

$sql = "
  SELECT a.application_id, a.status, a.applied_at, a.resume, a.cover_letter,
         i.title, i.location, i.type, e.company_name
  FROM applications a
  JOIN internships i ON a.internship_id = i.internship_id
  JOIN employers   e ON i.employer_id   = e.employer_id
  WHERE a.student_id = ?
  ORDER BY a.applied_at DESC
";
$q = $conn->prepare($sql);
$q->bind_param("i", $student_id);
$q->execute();
$app = $q->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <title>My Applications</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>body{margin:0}.main{margin-left:250px;padding:2rem;background:#f8f9fa;min-height:100vh}</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="main">
  <h2 class="mb-4">My Applications</h2>

  <?php if ($app->num_rows === 0): ?>
    <p class="text-muted">You haven’t applied to any internships yet.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th>Internship</th>
            <th>Company</th>
            <th>Type</th>
            <th>Location</th>
            <th>Status</th>
            <th>Applied On</th>
            <th>Resume</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $app->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($row['title']) ?></td>
              <td><?= htmlspecialchars($row['company_name']) ?></td>
              <td><span class="badge bg-secondary"><?= htmlspecialchars(ucfirst($row['type'])) ?></span></td>
              <td><?= htmlspecialchars($row['location']) ?></td>
              <td>
                <span class="badge bg-<?= 
                  $row['status']==='accepted'?'success':
                  ($row['status']==='rejected'?'danger':
                  ($row['status']==='withdrawn'?'warning':'secondary')) ?>">
                  <?= htmlspecialchars(ucfirst($row['status'])) ?>
                </span>
              </td>
              <td><?= htmlspecialchars($row['applied_at']) ?></td>
              <td>
                <?php if ($row['resume']): ?>
                  <a href="uploads/<?= rawurlencode($row['resume']) ?>" target="_blank">Download</a>
                <?php else: ?>N/A<?php endif; ?>
              </td>
              <td>
                <?php if ($row['status'] === 'pending'): ?>
                  <form method="post" action="withdraw-application.php" onsubmit="return confirm('Withdraw this application?');">
                    <input type="hidden" name="application_id" value="<?= (int)$row['application_id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Withdraw</button>
                  </form>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
