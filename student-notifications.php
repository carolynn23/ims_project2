<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') { header('Location: login.php'); exit(); }

$user_id = (int)$_SESSION['user_id'];

// mark as read
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['notification_id'])) {
  $nid = (int)$_POST['notification_id'];
  $mk = $conn->prepare("UPDATE notifications SET is_read=1 WHERE notification_id=? AND user_id=?");
  $mk->bind_param("ii", $nid, $user_id);
  $mk->execute();
}

$qs = $conn->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC");
$qs->bind_param("i", $user_id);
$qs->execute();
$list = $qs->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Notifications</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>body{margin:0}.main{margin-left:250px;padding:2rem;background:#f8f9fa;min-height:100vh}</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="main">
  <h2 class="mb-4">Notifications</h2>

  <?php if ($list->num_rows === 0): ?>
    <p class="text-muted">No notifications yet.</p>
  <?php else: ?>
    <ul class="list-group">
      <?php while ($n = $list->fetch_assoc()): ?>
        <li class="list-group-item d-flex justify-content-between align-items-start <?= $n['is_read'] ? 'text-muted' : 'fw-bold' ?>">
          <div class="ms-2 me-auto">
            <?= htmlspecialchars($n['message']) ?>
            <div class="small text-muted"><?= htmlspecialchars($n['created_at']) ?></div>
          </div>
          <?php if (!$n['is_read']): ?>
            <form method="post" class="ms-3">
              <input type="hidden" name="notification_id" value="<?= (int)$n['notification_id'] ?>">
              <button class="btn btn-sm btn-outline-primary">Mark as Read</button>
            </form>
          <?php else: ?>
            <span class="badge bg-light text-secondary">Read</span>
          <?php endif; ?>
        </li>
      <?php endwhile; ?>
    </ul>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
