<?php
session_start();
require_once '../config.php';
require_once '../classes/Analytics.php';

// Check admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$analytics = new Analytics($conn);

// Get system statistics
$stats = [
    'total_users' => 0,
    'total_students' => 0,
    'total_employers' => 0,
    'total_alumni' => 0,
    'total_internships' => 0,
    'total_applications' => 0,
    'active_internships' => 0,
    'pending_applications' => 0
];

// Fetch statistics
$queries = [
    'total_users' => "SELECT COUNT(*) as count FROM users WHERE status = 'active'",
    'total_students' => "SELECT COUNT(*) as count FROM students",
    'total_employers' => "SELECT COUNT(*) as count FROM employers",
    'total_alumni' => "SELECT COUNT(*) as count FROM alumni",
    'total_internships' => "SELECT COUNT(*) as count FROM internships",
    'total_applications' => "SELECT COUNT(*) as count FROM applications",
    'active_internships' => "SELECT COUNT(*) as count FROM internships WHERE deadline >= CURDATE() AND status = 'active'",
    'pending_applications' => "SELECT COUNT(*) as count FROM applications WHERE status = 'pending'"
];

foreach ($queries as $key => $query) {
    $result = $conn->query($query);
    $stats[$key] = $result->fetch_assoc()['count'];
}

// Get recent activities
$recentActivities = [];
$activityQuery = "
    SELECT al.*, u.email, u.role 
    FROM activity_logs al 
    LEFT JOIN users u ON al.user_id = u.user_id 
    ORDER BY al.created_at DESC 
    LIMIT 10
";
$result = $conn->query($activityQuery);
while ($row = $result->fetch_assoc()) {
    $recentActivities[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - InternHub</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #696cff;
            --primary-light: #7367f0;
            --success-color: #71dd37;
            --warning-color: #ffb400;
            --danger-color: #ff3e1d;
            --info-color: #03c3ec;
            --dark-color: #233446;
            --text-primary: #566a7f;
            --text-secondary: #a8aaae;
            --border-color: #e4e6e8;
            --card-bg: #fff;
            --shadow-sm: 0 2px 6px 0 rgba(67, 89, 113, 0.12);
            --shadow-lg: 0 6px 14px 0 rgba(67, 89, 113, 0.15);
            --border-radius: 8px;
            --border-radius-lg: 12px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            color: var(--text-primary);
        }

        .main-content {
            margin-left: 260px;
            padding: 2rem;
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
        }

        .stat-card.primary::before { background: var(--primary-color); }
        .stat-card.success::before { background: var(--success-color); }
        .stat-card.warning::before { background: var(--warning-color); }
        .stat-card.danger::before { background: var(--danger-color); }
        .stat-card.info::before { background: var(--info-color); }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.primary { background: var(--primary-color); }
        .stat-icon.success { background: var(--success-color); }
        .stat-icon.warning { background: var(--warning-color); }
        .stat-icon.danger { background: var(--danger-color); }
        .stat-icon.info { background: var(--info-color); }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .chart-card {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .activity-feed {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }

        .activity-header {
            padding: 1.5rem 1.5rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .activity-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            font-size: 0.9375rem;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .activity-time {
            font-size: 0.8125rem;
            color: var(--text-secondary);
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }

        .action-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: inherit;
        }

        .action-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 1rem;
        }

        .action-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .action-description {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="position: relative; z-index: 2;">
                <h1 class="mb-2">Admin Dashboard</h1>
                <p class="mb-0">System overview and management tools</p>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?= number_format($stats['total_users']) ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                    <div class="stat-icon primary">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card success">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?= number_format($stats['active_internships']) ?></div>
                        <div class="stat-label">Active Internships</div>
                    </div>
                    <div class="stat-icon success">
                        <i class="bi bi-briefcase"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card warning">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?= number_format($stats['pending_applications']) ?></div>
                        <div class="stat-label">Pending Applications</div>
                    </div>
                    <div class="stat-icon warning">
                        <i class="bi bi-clock"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card info">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?= number_format($stats['total_applications']) ?></div>
                        <div class="stat-label">Total Applications</div>
                    </div>
                    <div class="stat-icon info">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Quick Actions -->
            <div class="col-lg-8">
                <h5 class="mb-3">Quick Actions</h5>
                <div class="quick-actions">
                    <a href="admin-users.php" class="action-card">
                        <div class="action-icon">
                            <i class="bi bi-person-lines-fill"></i>
                        </div>
                        <div class="action-title">Manage Users</div>
                        <div class="action-description">View and manage user accounts</div>
                    </a>

                    <a href="admin-internships.php" class="action-card">
                        <div class="action-icon" style="background: var(--success-color);">
                            <i class="bi bi-briefcase"></i>
                        </div>
                        <div class="action-title">Manage Internships</div>
                        <div class="action-description">Review and moderate internships</div>
                    </a>

                    <a href="admin-reports.php" class="action-card">
                        <div class="action-icon" style="background: var(--info-color);">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <div class="action-title">Reports</div>
                        <div class="action-description">View system analytics</div>
                    </a>

                    <a href="admin-settings.php" class="action-card">
                        <div class="action-icon" style="background: var(--warning-color);">
                            <i class="bi bi-gear"></i>
                        </div>
                        <div class="action-title">Settings</div>
                        <div class="action-description">Configure system settings</div>
                    </a>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="col-lg-4">
                <div class="activity-feed">
                    <div class="activity-header">
                        <h5 class="mb-3">Recent Activities</h5>
                    </div>
                    
                    <?php if (empty($recentActivities)): ?>
                        <div class="activity-item">
                            <div class="text-muted">No recent activities</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentActivities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-avatar">
                                    <?= strtoupper(substr($activity['email'] ?? 'U', 0, 1)) ?>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-text">
                                        <?= htmlspecialchars($activity['email'] ?? 'Unknown') ?> 
                                        <?= htmlspecialchars($activity['action']) ?> 
                                        <?= htmlspecialchars($activity['table_name']) ?>
                                    </div>
                                    <div class="activity-time">
                                        <?= date('M j, Y g:i A', strtotime($activity['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <div class="p-3 text-center border-top">
                        <a href="admin-activities.php" class="btn btn-sm btn-outline-primary">
                            View All Activities
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>
</html>