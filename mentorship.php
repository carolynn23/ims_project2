<?php
session_start();
require_once 'config.php';

// Only students can access
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = (int)$_SESSION['student_id'];

// Handle sending a mentorship request
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alumni_id'])) {
    $alumni_id = (int)$_POST['alumni_id'];

    // Check if a request already exists
    $check = $conn->prepare("SELECT * FROM mentorship_requests WHERE student_id = ? AND alumni_id = ?");
    $check->bind_param("ii", $student_id, $alumni_id);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();

    if ($exists) {
        $message = "You already sent a request to this alumni. Status: " . ucfirst($exists['status']);
    } else {
        // Insert new mentorship request
        $insert = $conn->prepare("INSERT INTO mentorship_requests (student_id, alumni_id) VALUES (?, ?)");
        $insert->bind_param("ii", $student_id, $alumni_id);
        if ($insert->execute()) {
            $message = "Mentorship request sent successfully!";
        } else {
            $message = "Failed to send request. Please try again.";
        }
    }
}

// Fetch all alumni who are available mentors
$alumniQuery = $conn->prepare("
    SELECT alumni_id, full_name, graduation_year, department, program, current_position, company, mentorship_available
    FROM alumni
    WHERE mentorship_available = 1
    ORDER BY graduation_year DESC, full_name ASC
");
$alumniQuery->execute();
$alumniList = $alumniQuery->get_result();

// Fetch student's existing requests for status display
$requestStatus = [];
$reqRes = $conn->prepare("SELECT alumni_id, status FROM mentorship_requests WHERE student_id = ?");
$reqRes->bind_param("i", $student_id);
$reqRes->execute();
$res = $reqRes->get_result();
while ($row = $res->fetch_assoc()) {
    $requestStatus[$row['alumni_id']] = $row['status'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Mentorship</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { margin: 0; background: #f8f9fa; }
        .main-content { margin-left: 260px; padding: 2rem; }
        .mentor-card { margin-bottom: 1rem; }
        .badge-available { background-color: #28a745; color: white; }
        .badge-unavailable { background-color: #6c757d; color: white; }
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="main-content">
    <h2 class="mb-4">Available Alumni Mentors</h2>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($alumniList->num_rows === 0): ?>
        <p class="text-muted">No mentors are currently available.</p>
    <?php else: ?>
        <div class="row">
            <?php while ($alumni = $alumniList->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card mentor-card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($alumni['full_name']) ?></h5>
                            <p class="card-text">
                                <strong>Graduation Year:</strong> <?= htmlspecialchars($alumni['graduation_year']) ?><br>
                                <strong>Program:</strong> <?= htmlspecialchars($alumni['program']) ?><br>
                                <strong>Department:</strong> <?= htmlspecialchars($alumni['department']) ?><br>
                                <strong>Current Position:</strong> <?= htmlspecialchars($alumni['current_position']) ?><br>
                                <strong>Company:</strong> <?= htmlspecialchars($alumni['company']) ?><br>
                                <span class="badge badge-available">Available</span>
                            </p>
                            <div class="text-center">
                                <?php if (isset($requestStatus[$alumni['alumni_id']])): ?>
                                    <span class="badge <?= $requestStatus[$alumni['alumni_id']] === 'pending' ? 'bg-warning' : ($requestStatus[$alumni['alumni_id']] === 'accepted' ? 'bg-success' : 'bg-danger') ?>">
                                        <?= ucfirst($requestStatus[$alumni['alumni_id']]) ?>
                                    </span>
                                <?php else: ?>
                                    <form method="post">
                                        <input type="hidden" name="alumni_id" value="<?= $alumni['alumni_id'] ?>">
                                        <button type="submit" class="btn btn-primary btn-sm mt-2">Request Mentorship</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
