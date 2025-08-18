<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['alumni_id'])) {
    header("Location: login.php");
    exit();
}

$alumni_id = (int)$_SESSION['alumni_id'];
$message = '';
$filter = $_GET['status'] ?? 'all';

// Handle accept/decline actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    $stmt = $conn->prepare("SELECT student_id, status FROM mentorship_requests WHERE request_id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();

    if ($req && $req['status'] === 'pending') {
        $status = ($action === 'accept') ? 'accepted' : 'declined';
        $update = $conn->prepare("UPDATE mentorship_requests SET status = ? WHERE request_id = ?");
        $update->bind_param("si", $status, $request_id);
        $update->execute();

        $student_id = (int)$req['student_id'];
        $notif_msg = "Your mentorship request has been " . ucfirst($status) . ".";
        $notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES ((SELECT user_id FROM students WHERE student_id=?), ?)");
        $notif->bind_param("is", $student_id, $notif_msg);
        $notif->execute();

        $message = "Request successfully " . $status . ".";
    } else {
        $message = "Cannot update request.";
    }
}

// Fetch all mentorship requests (all requests, not just for this alumni)
$query = "
    SELECT mr.request_id, mr.status, mr.created_at, 
           s.full_name AS student_name, s.program, s.department, s.level,
           a.full_name AS alumni_name
    FROM mentorship_requests mr
    JOIN students s ON mr.student_id = s.student_id
    JOIN alumni a ON mr.alumni_id = a.alumni_id
";
$params = [];
$types = "";
if (in_array($filter, ['pending', 'accepted', 'declined'])) {
    $query .= " WHERE mr.status = ?";
    $params[] = $filter;
    $types .= "s";
}
$query .= " ORDER BY mr.created_at DESC";

$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$requests = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Mentorship Requests</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
    body { margin: 0; background: #f8f9fa; }
    /* Sidebar */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 260px;
        height: 100vh;
        background-color: #343a40;
        color: #fff;
        padding-top: 2rem;
        z-index: 1000;
    }
    .sidebar a {
        display: flex;
        align-items: center;
        padding: 0.75rem 1.5rem;
        color: #adb5bd;
        text-decoration: none;
        transition: 0.2s;
    }
    .sidebar a:hover { background-color: #495057; color: #fff; }
    .sidebar a .bi { margin-right: 0.75rem; font-size: 1.2rem; }
    .sidebar h4 { text-align: center; margin-bottom: 1.5rem; }

    /* Main content */
    .main-content {
        margin-left: 260px; /* matches sidebar width */
        padding: 2rem;
    }

    /* Cards */
    .mentor-card { height: 100%; }
    .card-title { font-weight: 600; }
    .badge-status { padding: 0.3rem 0.6rem; font-size: 0.85rem; }
    .filter-btns a { margin-right: 0.5rem; }

    /* Responsive */
    @media (max-width: 768px) {
        .sidebar { left: -260px; transition: 0.3s; }
        .sidebar.active { left: 0; }
        .main-content { margin-left: 0; padding: 1rem; }
    }
</style>
</head>
<body>

<!-- Sidebar -->
<nav class="sidebar">
    <h4>Alumni Portal</h4>
    <a href="alumni-dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
    <a href="mentor-students.php" class="active"><i class="bi bi-person-check"></i> Mentorship</a>
    <a href="share-experience.php"><i class="bi bi-journal-text"></i> Share Experiences</a>
    <a href="alumni-directory.php"><i class="bi bi-people"></i> Alumni Directory</a>
    <a href="post-job.php"><i class="bi bi-briefcase"></i> Post Jobs</a>
    <a href="alumni-community.php"><i class="bi bi-globe"></i> Community</a>
    <a href="edit-alumni-profile.php"><i class="bi bi-gear"></i> Profile</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
</nav>

<!-- Main Content -->
<div class="main-content container-fluid">
    <h2 class="mb-4">Mentorship Requests</h2>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Filter Buttons -->
    <div class="mb-3 filter-btns">
        <a href="mentor-students.php?status=all" class="btn btn-outline-primary btn-sm <?= $filter === 'all' ? 'active' : '' ?>">All</a>
        <a href="mentor-students.php?status=pending" class="btn btn-outline-warning btn-sm <?= $filter === 'pending' ? 'active' : '' ?>">Pending</a>
        <a href="mentor-students.php?status=accepted" class="btn btn-outline-success btn-sm <?= $filter === 'accepted' ? 'active' : '' ?>">Accepted</a>
        <a href="mentor-students.php?status=declined" class="btn btn-outline-danger btn-sm <?= $filter === 'declined' ? 'active' : '' ?>">Declined</a>
    </div>

    <?php if ($requests->num_rows === 0): ?>
        <p class="text-muted">No mentorship requests found for this filter.</p>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php while ($req = $requests->fetch_assoc()): ?>
                <div class="col">
                    <div class="card mentor-card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?= htmlspecialchars($req['student_name']) ?> → <?= htmlspecialchars($req['alumni_name']) ?></h5>
                            <p class="card-text flex-grow-1">
                                <strong>Program:</strong> <?= htmlspecialchars($req['program']) ?><br>
                                <strong>Department:</strong> <?= htmlspecialchars($req['department']) ?><br>
                                <strong>Level:</strong> <?= htmlspecialchars($req['level']) ?><br>
                                <strong>Status:</strong> 
                                <span class="badge badge-status <?= $req['status'] === 'pending' ? 'bg-warning text-dark' : ($req['status'] === 'accepted' ? 'bg-success' : 'bg-danger') ?>">
                                    <?= ucfirst($req['status']) ?>
                                </span><br>
                                <small class="text-muted">Requested on: <?= htmlspecialchars($req['created_at']) ?></small>
                            </p>
                            <?php if ($req['status'] === 'pending'): ?>
                                <form method="post" class="mt-2 d-flex gap-2">
                                    <input type="hidden" name="request_id" value="<?= $req['request_id'] ?>">
                                    <button type="submit" name="action" value="accept" class="btn btn-success btn-sm flex-fill">Accept</button>
                                    <button type="submit" name="action" value="decline" class="btn btn-danger btn-sm flex-fill">Decline</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
