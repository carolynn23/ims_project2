<?php
session_start();
require_once 'config.php';

// Only alumni can access
if (!isset($_SESSION['alumni_id'])) {
    header("Location: login.php");
    exit();
}

// Get alumni info
$alumni_id = (int)$_SESSION['alumni_id'];
$stmt = $conn->prepare("SELECT * FROM alumni WHERE alumni_id = ?");
$stmt->bind_param("i", $alumni_id);
$stmt->execute();
$alumni = $stmt->get_result()->fetch_assoc();

// --- Live stats ---
$totalAlumni = $conn->query("SELECT COUNT(*) FROM alumni")->fetch_row()[0];

// Active mentors (assuming mentorship_available = 1 flag in alumni)
$totalMentors = $conn->query("SELECT COUNT(*) FROM alumni WHERE mentorship_offered = 1")->fetch_row()[0];

// Current students
$totalStudents = $conn->query("SELECT COUNT(*) FROM students")->fetch_row()[0];

// Job openings (assuming jobs table exists)
// Total Job Openings (if table exists)
$totalJobs = 0;
$checkJobsTable = $conn->query("SHOW TABLES LIKE 'job_posts'");
if ($checkJobsTable && $checkJobsTable->num_rows > 0) {
    $totalJobs = $conn->query("SELECT COUNT(*) FROM job_posts")->fetch_row()[0];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Alumni Dashboard</title>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

<style>
    body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8f9fa; }
    .sidebar { position: fixed; top: 0; left: 0; width: 260px; height: 100vh; background: #343a40; color: #fff; padding: 2rem 0; }
    .sidebar a { color: #adb5bd; display: flex; align-items: center; padding: 0.75rem 2rem; text-decoration: none; transition: 0.2s; }
    .sidebar a:hover { background: #495057; color: #fff; }
    .sidebar a .bi { margin-right: 0.75rem; font-size: 1.2rem; }
    .main-content { margin-left: 260px; padding: 2rem; }
    .welcome { background: #fff; padding: 2rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #e9ecef; }
    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
    .stat-card { background: #fff; padding: 1.5rem; border-radius: 8px; border: 1px solid #e9ecef; text-align: center; }
    .stat-number { font-size: 2rem; font-weight: 600; color: #2c3e50; margin-bottom: 0.5rem; }
    .stat-label { font-size: 0.95rem; color: #6c757d; }
    .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
    .dashboard-card { background: #fff; padding: 2rem; border-radius: 8px; border: 1px solid #e9ecef; transition: 0.2s; }
    .dashboard-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-color: #2c3e50; }
    .card-title { font-size: 1.25rem; font-weight: 600; color: #2c3e50; margin-bottom: 0.5rem; }
    .card-description { color: #6c757d; font-size: 0.95rem; line-height: 1.5; margin-bottom: 1rem; }
</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/navbar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <!-- Welcome Section -->
    <div class="welcome">
        <h1>Welcome back, <?= htmlspecialchars($alumni['full_name']) ?></h1>
        <p>Your personalized space to connect, mentor, and share experiences with the alumni community.</p>
    </div>

    <!-- Stats Section -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-number"><?= $totalAlumni ?></div>
            <div class="stat-label">Total Alumni</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $totalMentors ?></div>
            <div class="stat-label">Active Mentors</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $totalStudents ?></div>
            <div class="stat-label">Current Students</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $totalJobs ?></div>
            <div class="stat-label">Job Openings</div>
        </div>
    </div>

    <!-- Dashboard Feature Cards -->
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <div class="card-title">Student Mentorship</div>
            <div class="card-description">Guide current students with your expertise and experience.</div>
            <a href="mentor-students.php" class="btn btn-primary">Start Mentoring</a>
            <a href="mentorship-requests.php" class="btn btn-secondary">View Requests</a>
        </div>

        <div class="dashboard-card">
            <div class="card-title">Share Experiences</div>
            <div class="card-description">Share your career journey to inspire current students.</div>
            <a href="share-experience.php" class="btn btn-primary">Share Story</a>
            <a href="view-experiences.php" class="btn btn-secondary">Browse Stories</a>
        </div>

        <div class="dashboard-card">
            <div class="card-title">Alumni Directory</div>
            <div class="card-description">Connect with fellow alumni and expand your network.</div>
            <a href="alumni-directory.php" class="btn btn-primary">Browse Alumni</a>
            <a href="search-alumni.php" class="btn btn-secondary">Search</a>
        </div>

        <div class="dashboard-card">
            <div class="card-title">Job Opportunities</div>
            <div class="card-description">Post job opportunities and help alumni and students grow.</div>
            <a href="post-job.php" class="btn btn-primary">Post Job</a>
            <a href="job-board.php" class="btn btn-secondary">View Jobs</a>
        </div>

        <div class="dashboard-card">
            <div class="card-title">Community Hub</div>
            <div class="card-description">Join discussions, events, and alumni activities.</div>
            <a href="alumni-community.php" class="btn btn-primary">Join Community</a>
            <a href="events.php" class="btn btn-secondary">View Events</a>
        </div>

        <div class="dashboard-card">
            <div class="card-title">Profile Settings</div>
            <div class="card-description">Update your information and manage your profile.</div>
            <a href="edit-alumni-profile.php" class="btn btn-primary">Edit Profile</a>
            <a href="privacy-settings.php" class="btn btn-secondary">Privacy</a>
        </div>
    </div>
</div>

</body>
</html>
