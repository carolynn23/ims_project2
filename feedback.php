<?php
session_start();
require_once 'config.php';

// Ensure employer is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch employer profile
$stmt = $conn->prepare("SELECT employer_id, company_name FROM employers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();
$employer_id = $employer['employer_id'];
$company_name = $employer['company_name'];

// Fetch feedback from students
$query = $conn->prepare("
    SELECT f.message, f.rating, f.submitted_at, s.full_name, s.department, s.program
    FROM feedback f
    JOIN students s ON f.student_id = s.student_id
    WHERE f.employer_id = ?
    ORDER BY f.submitted_at DESC
");
$query->bind_param("i", $employer_id);
$query->execute();
$feedback = $query->get_result();
?>

<!DOCTYPE html>
<html>
<head>
  <title>Student Feedback</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { margin: 0; }
    .main-content {
        margin-left: 250px;
        padding: 2rem;
        background-color: #f8f9fa;
        min-height: 100vh;
    }
    .star-rating {
        color: #ffc107;
        font-size: 1rem;
    }
  </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="main-content">
    <h2 class="mb-4">Feedback from Students</h2>

    <?php if ($feedback->num_rows > 0): ?>
        <?php while ($row = $feedback->fetch_assoc()): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title mb-1"><?= htmlspecialchars($row['full_name']) ?> 
                        <small class="text-muted">(<?= $row['program'] . ' / ' . $row['department'] ?>)</small>
                    </h5>
                    <p class="mb-1">
                        <span class="star-rating">
                            <?= str_repeat("★", $row['rating']) . str_repeat("☆", 5 - $row['rating']) ?>
                        </span>
                        <small class="text-muted"><?= $row['submitted_at'] ?></small>
                    </p>
                    <p class="card-text"><?= nl2br(htmlspecialchars($row['message'])) ?></p>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p class="text-muted">No feedback has been submitted by students yet.</p>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
