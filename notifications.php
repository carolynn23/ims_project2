<?php
session_start();
require_once 'config.php';

// Ensure user is logged in and is an employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch employer data
$stmt = $conn->prepare("SELECT employer_id, company_name FROM employers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();
$company_name = $employer['company_name'];

// Handle "mark as read"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_id'])) {
    $notif_id = $_POST['notification_id'];
    $mark = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    $mark->bind_param("ii", $notif_id, $user_id);
    $mark->execute();
}

// Fetch all notifications for employer
$query = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$query->bind_param("i", $user_id);
$query->execute();
$notifications = $query->get_result();
?>

<!DOCTYPE html>
<html>
<head>
  <title>Notifications</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { margin: 0; }
    .main-content {
        margin-left: 250px;
        padding: 2rem;
        background-color: #f8f9fa;
        min-height: 100vh;
    }
  </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="main-content">
    <h2 class="mb-4">Your Notifications</h2>

    <?php if ($notifications->num_rows > 0): ?>
        <ul class="list-group">
            <?php while ($row = $notifications->fetch_assoc()): ?>
                <li class="list-group-item d-flex justify-content-between align-items-start <?= $row['is_read'] ? 'text-muted' : 'fw-bold' ?>">
                    <div class="ms-2 me-auto">
                        <?= htmlspecialchars($row['message']) ?>
                        <div class="small text-muted"><?= $row['created_at'] ?></div>
                    </div>
                    <?php if (!$row['is_read']): ?>
                        <form method="POST" class="ms-3">
                            <input type="hidden" name="notification_id" value="<?= $row['notification_id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-primary">Mark as Read</button>
                        </form>
                    <?php else: ?>
                        <span class="badge bg-light text-secondary">Read</span>
                    <?php endif; ?>
                </li>
            <?php endwhile; ?>
        </ul>
    <?php else: ?>
        <p class="text-muted">You have no notifications at the moment.</p>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>

