<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['alumni_id'])) {
    header("Location: login.php");
    exit();
}

// Get alumni info from the database
$alumni_id = $_SESSION['alumni_id'];
$sql = "SELECT * FROM alumni WHERE alumni_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $alumni_id);
$stmt->execute();
$result = $stmt->get_result();
$alumni = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: #f8f9fa;
            color: #2c3e50;
            line-height: 1.6;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: white;
            border-right: 1px solid #e9ecef;
            padding: 2rem 0;
            z-index: 1000;
            transition: transform 0.3s ease;
        }

        .sidebar-header {
            padding: 0 2rem;
            margin-bottom: 2rem;
        }

        .sidebar-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .sidebar-nav {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 0.25rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 2rem;
            color: #6c757d;
            text-decoration: none;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .nav-link:hover {
            background-color: #f8f9fa;
            color: #2c3e50;
        }

        .nav-link.active {
            background-color: #2c3e50;
            color: white;
            border-right: 3px solid #2c3e50;
        }

        .nav-icon {
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }

        /* Mobile Toggle */
        .sidebar-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 0.5rem;
            z-index: 1001;
            cursor: pointer;
            font-size: 1.2rem;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }

        /* Welcome Section */
        .welcome {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border: 1px solid #e9ecef;
        }

        .welcome h1 {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }

        .welcome p {
            color: #6c757d;
            font-size: 1.1rem;
        }

        /* Stats Grid */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .dashboard-card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            transition: all 0.2s ease;
        }

        .dashboard-card:hover {
            border-color: #2c3e50;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .card-description {
            color: #6c757d;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .card-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
        }

        .btn-primary {
            background-color: #2c3e50;
            color: white;
        }

        .btn-primary:hover {
            background-color: #1a252f;
        }

        .btn-secondary {
            background-color: transparent;
            color: #6c757d;
            border: 1px solid #e9ecef;
        }

        .btn-secondary:hover {
            background-color: #f8f9fa;
            color: #2c3e50;
            border-color: #2c3e50;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .sidebar-toggle {
                display: block;
            }

            .main-content {
                margin-left: 0;
                padding: 4rem 1rem 1rem;
            }

            .welcome {
                padding: 1.5rem;
            }

            .welcome h1 {
                font-size: 1.5rem;
            }

            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .card-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .stats {
                grid-template-columns: 1fr;
            }

            .main-content {
                padding: 4rem 0.5rem 0.5rem;
            }
        }

        /* Overlay for mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .sidebar-overlay.active {
            display: block;
        }
    </style>
</head>
<body>

<!-- Mobile Sidebar Toggle -->
<button class="sidebar-toggle" onclick="toggleSidebar()">
    ☰
</button>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h2 class="sidebar-title">Alumni Portal</h2>
    </div>
    <ul class="sidebar-nav">
        <li class="nav-item">
            <a href="alumni-dashboard.php" class="nav-link active">
                <span class="nav-icon">🏠</span>
                Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="mentor-students.php" class="nav-link">
                <span class="nav-icon">🎓</span>
                Mentor Students
            </a>
        </li>
        <li class="nav-item">
            <a href="share-experience.php" class="nav-link">
                <span class="nav-icon">📝</span>
                Share Experiences
            </a>
        </li>
        <li class="nav-item">
            <a href="alumni-directory.php" class="nav-link">
                <span class="nav-icon">🤝</span>
                Alumni Directory
            </a>
        </li>
        <li class="nav-item">
            <a href="post-job.php" class="nav-link">
                <span class="nav-icon">📢</span>
                Post Jobs
            </a>
        </li>
        <li class="nav-item">
            <a href="alumni-community.php" class="nav-link">
                <span class="nav-icon">🌐</span>
                Community
            </a>
        </li>
        <li class="nav-item">
            <a href="edit-alumni-profile.php" class="nav-link">
                <span class="nav-icon">⚙️</span>
                Profile Settings
            </a>
        </li>
        <li class="nav-item">
            <a href="logout.php" class="nav-link">
                <span class="nav-icon">🚪</span>
                Logout
            </a>
        </li>
    </ul>
</nav>

<!-- Main Content -->
<main class="main-content">
    
    <!-- Welcome Section -->
    <div class="welcome">
        <h1>Welcome back, <?php echo htmlspecialchars($alumni['full_name']); ?></h1>
        <p>Your personalized space to connect, mentor, and grow with the alumni community.</p>
    </div>

    <!-- Quick Stats -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-number">1,247</div>
            <div class="stat-label">Total Alumni</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">456</div>
            <div class="stat-label">Active Mentors</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">89</div>
            <div class="stat-label">Current Students</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">23</div>
            <div class="stat-label">Job Openings</div>
        </div>
    </div>

    <!-- Dashboard Features -->
    <div class="dashboard-grid">
        
        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">Student Mentorship</h3>
                <p class="card-description">Guide current students with your expertise and experience</p>
            </div>
            <div class="card-actions">
                <a href="mentor-students.php" class="btn btn-primary">Start Mentoring</a>
                <a href="mentorship-requests.php" class="btn btn-secondary">View Requests</a>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">Share Experiences</h3>
                <p class="card-description">Share your career journey to inspire others</p>
            </div>
            <div class="card-actions">
                <a href="share-experience.php" class="btn btn-primary">Share Story</a>
                <a href="view-experiences.php" class="btn btn-secondary">Browse Stories</a>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">Alumni Directory</h3>
                <p class="card-description">Connect with fellow alumni and expand your network</p>
            </div>
            <div class="card-actions">
                <a href="alumni-directory.php" class="btn btn-primary">Browse Alumni</a>
                <a href="search-alumni.php" class="btn btn-secondary">Search</a>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">Job Opportunities</h3>
                <p class="card-description">Post openings and help alumni advance their careers</p>
            </div>
            <div class="card-actions">
                <a href="post-job.php" class="btn btn-primary">Post Job</a>
                <a href="job-board.php" class="btn btn-secondary">View Jobs</a>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">Community Hub</h3>
                <p class="card-description">Join discussions, events, and community activities</p>
            </div>
            <div class="card-actions">
                <a href="alumni-community.php" class="btn btn-primary">Join Community</a>
                <a href="events.php" class="btn btn-secondary">View Events</a>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">Profile Settings</h3>
                <p class="card-description">Update your information and manage your profile</p>
            </div>
            <div class="card-actions">
                <a href="edit-alumni-profile.php" class="btn btn-primary">Edit Profile</a>
                <a href="privacy-settings.php" class="btn btn-secondary">Privacy</a>
            </div>
        </div>

    </div>
</main>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }

    function closeSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    }

    // Close sidebar when clicking on links (mobile)
    document.addEventListener('DOMContentLoaded', function() {
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    closeSidebar();
                }
            });
        });
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            closeSidebar();
        }
    });
</script>

</body>
</html>