<?php
session_start();
require_once '../config.php';

// Check admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../secure_login.php");
    exit();
}

// Function to check if column exists in table
function columnExists($conn, $table, $column) {
    try {
        $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $result && $result->num_rows > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Function to check if table exists
function tableExists($conn, $table) {
    try {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        return $result && $result->num_rows > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Check database structure
$users_has_status = columnExists($conn, 'users', 'status');
$users_has_created_at = columnExists($conn, 'users', 'created_at');

$tables_exist = [
    'students' => tableExists($conn, 'students'),
    'employers' => tableExists($conn, 'employers'),
    'alumni' => tableExists($conn, 'alumni'),
    'internships' => tableExists($conn, 'internships'),
    'applications' => tableExists($conn, 'applications')
];

// Initialize statistics
$stats = [
    'total_users' => 0,
    'total_students' => 0,
    'total_employers' => 0,
    'total_alumni' => 0,
    'total_internships' => 0,
    'total_applications' => 0,
    'active_internships' => 0,
    'pending_applications' => 0,
    'recent_signups' => 0,
    'pending_users' => 0
];

// Initialize analytics data
$analytics = [
    'user_breakdown' => [],
    'recent_activities' => []
];

// System health data
$systemHealth = [
    'database_size' => '0 MB',
    'total_tables' => 0,
    'admin_users' => 0,
    'php_version' => PHP_VERSION,
    'mysql_version' => 'Unknown'
];

try {
    // === BASIC STATISTICS ===
    
    // Total users
    if ($users_has_status) {
        $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
        if ($result) $stats['total_users'] = $result->fetch_assoc()['count'];
        
        $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'pending'");
        if ($result) $stats['pending_users'] = $result->fetch_assoc()['count'];
    } else {
        $result = $conn->query("SELECT COUNT(*) as count FROM users");
        if ($result) $stats['total_users'] = $result->fetch_assoc()['count'];
    }
    
    // Recent signups
    if ($users_has_created_at) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) $stats['recent_signups'] = $result->fetch_assoc()['count'];
    }
    
    // Students count
    if ($tables_exist['students']) {
        $result = $conn->query("SELECT COUNT(*) as count FROM students");
        if ($result) $stats['total_students'] = $result->fetch_assoc()['count'];
    }
    
    // Employers count
    if ($tables_exist['employers']) {
        $result = $conn->query("SELECT COUNT(*) as count FROM employers");
        if ($result) $stats['total_employers'] = $result->fetch_assoc()['count'];
    }
    
    // Alumni count
    if ($tables_exist['alumni']) {
        $result = $conn->query("SELECT COUNT(*) as count FROM alumni");
        if ($result) $stats['total_alumni'] = $result->fetch_assoc()['count'];
    }
    
    // Internships statistics
    if ($tables_exist['internships']) {
        $result = $conn->query("SELECT COUNT(*) as count FROM internships");
        if ($result) $stats['total_internships'] = $result->fetch_assoc()['count'];
        
        // Active internships
        if (columnExists($conn, 'internships', 'deadline')) {
            $result = $conn->query("SELECT COUNT(*) as count FROM internships WHERE deadline >= CURDATE()");
            if ($result) $stats['active_internships'] = $result->fetch_assoc()['count'];
        } else {
            $stats['active_internships'] = $stats['total_internships'];
        }
    }
    
    // Applications statistics
    if ($tables_exist['applications']) {
        $result = $conn->query("SELECT COUNT(*) as count FROM applications");
        if ($result) $stats['total_applications'] = $result->fetch_assoc()['count'];
        
        if (columnExists($conn, 'applications', 'status')) {
            $result = $conn->query("SELECT COUNT(*) as count FROM applications WHERE status = 'pending'");
            if ($result) $stats['pending_applications'] = $result->fetch_assoc()['count'];
        } else {
            $stats['pending_applications'] = $stats['total_applications'];
        }
    }
    
    // === USER BREAKDOWN ANALYTICS ===
    $user_breakdown_query = "SELECT role, COUNT(*) as count FROM users GROUP BY role ORDER BY count DESC";
    $user_breakdown_result = $conn->query($user_breakdown_query);
    
    if ($user_breakdown_result && $user_breakdown_result->num_rows > 0) {
        while ($row = $user_breakdown_result->fetch_assoc()) {
            $analytics['user_breakdown'][] = [
                'role' => ucfirst($row['role']),
                'count' => (int)$row['count']
            ];
        }
    }
    
    // === RECENT ACTIVITIES ===
    if ($users_has_created_at) {
        $activities_query = "
            SELECT 
                email,
                role,
                created_at
            FROM users 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY created_at DESC 
            LIMIT 5
        ";
        $activities_result = $conn->query($activities_query);
        
        if ($activities_result && $activities_result->num_rows > 0) {
            while ($row = $activities_result->fetch_assoc()) {
                $analytics['recent_activities'][] = [
                    'email' => $row['email'],
                    'role' => $row['role'],
                    'created_at' => $row['created_at']
                ];
            }
        }
    }
    
    // === SYSTEM HEALTH ===
    
    // Database size
    $db_size_result = $conn->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS db_size FROM information_schema.tables WHERE table_schema = DATABASE()");
    if ($db_size_result) {
        $size = $db_size_result->fetch_assoc()['db_size'];
        $systemHealth['database_size'] = $size ? $size . ' MB' : '0 MB';
    }
    
    // Table count
    $tables_result = $conn->query("SHOW TABLES");
    if ($tables_result) {
        $systemHealth['total_tables'] = $tables_result->num_rows;
    }
    
    // Admin users count
    $admin_result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
    if ($admin_result) {
        $systemHealth['admin_users'] = $admin_result->fetch_assoc()['count'];
    }
    
    // MySQL version
    $version_result = $conn->query("SELECT VERSION() as version");
    if ($version_result) {
        $version = $version_result->fetch_assoc()['version'];
        $systemHealth['mysql_version'] = substr($version, 0, 10);
    }
    
} catch (Exception $e) {
    error_log("Admin Dashboard Error: " . $e->getMessage());
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
            --secondary-color: #8592a3;
            --success-color: #71dd37;
            --warning-color: #ffb400;
            --danger-color: #ff3e1d;
            --info-color: #03c3ec;
            --light-color: #fcfdfd;
            --dark-color: #233446;
            --text-primary: #566a7f;
            --text-secondary: #a8aaae;
            --text-muted: #c7c8cc;
            --border-color: #e4e6e8;
            --card-bg: #fff;
            --hover-bg: #f8f9fa;
            --shadow-sm: 0 2px 6px 0 rgba(67, 89, 113, 0.12);
            --shadow-md: 0 4px 8px -4px rgba(67, 89, 113, 0.1);
            --shadow-lg: 0 6px 14px 0 rgba(67, 89, 113, 0.15);
            --border-radius: 8px;
            --border-radius-lg: 12px;
            --transition: all 0.2s ease-in-out;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            color: var(--text-primary);
            line-height: 1.6;
        }

        /* Main Content - Account for navbar height */
        .main-content {
            margin-left: 260px;
            margin-top: 70px; /* Account for fixed navbar */
            padding: 1rem;
            min-height: calc(100vh - 70px);
            transition: var(--transition);
        }

        /* Compact Welcome Header */
        .welcome-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            border-radius: var(--border-radius);
            color: white;
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow);
        }

        .welcome-content {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .welcome-icon {
            font-size: 1.5rem;
            opacity: 0.9;
        }

        .welcome-text h1 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            line-height: 1.3;
        }

        .welcome-text p {
            font-size: 0.875rem;
            opacity: 0.85;
            margin: 0;
            font-weight: 400;
        }

        /* Status Badge */
        .status-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.6875rem;
            font-weight: 600;
            margin-left: 1rem;
        }

        /* Compact Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            flex-shrink: 0;
        }

        .stat-icon.primary { background: var(--primary-color); }
        .stat-icon.success { background: var(--success-color); }
        .stat-icon.danger { background: var(--danger-color); }
        .stat-icon.warning { background: var(--warning-color); }
        .stat-icon.info { background: var(--info-color); }

        .stat-content {
            flex: 1;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.125rem;
            line-height: 1;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.8125rem;
            font-weight: 500;
        }

        .stat-meta {
            font-size: 0.6875rem;
            color: var(--text-muted);
            margin-top: 0.125rem;
        }

        /* Section Cards */
        .section-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }

        /* User Breakdown */
        .user-breakdown {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.75rem;
        }

        .role-item {
            text-align: center;
            padding: 0.75rem;
            background: var(--hover-bg);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }

        .role-badge {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            color: white;
            margin: 0 auto 0.5rem;
        }

        .role-badge.admin { background: var(--danger-color); }
        .role-badge.student { background: var(--primary-color); }
        .role-badge.employer { background: var(--success-color); }
        .role-badge.alumni { background: var(--info-color); }

        .role-count {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 1.125rem;
        }

        .role-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 0.75rem;
        }

        .action-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            text-align: center;
            transition: var(--transition);
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .action-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: inherit;
            text-decoration: none;
            border-color: var(--primary-color);
        }

        .action-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            margin: 0 auto 0.75rem;
        }

        .action-icon.success { background: var(--success-color); }
        .action-icon.info { background: var(--info-color); }
        .action-icon.warning { background: var(--warning-color); }

        .action-title {
            font-weight: 600;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }

        .action-description {
            color: var(--text-secondary);
            font-size: 0.75rem;
        }

        /* Activity Feed */
        .activity-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-avatar {
            width: 28px;
            height: 28px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            margin-right: 0.75rem;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            font-size: 0.8125rem;
            color: var(--text-primary);
            margin-bottom: 0.125rem;
        }

        .activity-time {
            font-size: 0.6875rem;
            color: var(--text-secondary);
        }

        /* System Health */
        .health-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .health-item:last-child {
            border-bottom: none;
        }

        .health-label {
            font-size: 0.8125rem;
            color: var(--text-secondary);
        }

        .health-value {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.8125rem;
        }

        /* Database Status */
        .db-status {
            background: var(--hover-bg);
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-top: 1rem;
        }

        .db-status h6 {
            color: var(--text-primary);
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
        }

        .status-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        .status-item {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .status-check {
            margin-right: 0.5rem;
        }

        .status-check.success { color: var(--success-color); }
        .status-check.danger { color: var(--danger-color); }

        /* Responsive Design */
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                margin-top: 70px;
                padding: 0.75rem;
            }

            .stats-container {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 0.5rem;
            }

            .stat-card {
                padding: 0.75rem;
            }

            .stat-icon {
                width: 32px;
                height: 32px;
                font-size: 1rem;
            }

            .stat-number {
                font-size: 1.25rem;
            }

            .user-breakdown {
                grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
                gap: 0.5rem;
            }

            .quick-actions {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                gap: 0.5rem;
            }

            .status-grid {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
        }

        @media (max-width: 768px) {
            .welcome-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .status-badge {
                margin-left: 0;
                margin-top: 0.5rem;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/navbar.php'; ?>

    <div class="main-content">
        <!-- Compact Welcome Header -->
        <div class="welcome-header">
            <div class="welcome-content">
                <i class="bi bi-speedometer2 welcome-icon"></i>
                <div class="welcome-text">
                    <h1>Admin Dashboard</h1>
                    <p>System overview and management tools</p>
                </div>
                <?php if (!$users_has_status): ?>
                    <span class="status-badge">Basic Mode</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Compact Stats Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="bi bi-people"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= number_format($stats['total_users']) ?></div>
                    <div class="stat-label">Total Users</div>
                    <?php if ($stats['pending_users'] > 0): ?>
                        <div class="stat-meta"><?= $stats['pending_users'] ?> pending</div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($tables_exist['students']): ?>
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="bi bi-mortarboard"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= number_format($stats['total_students']) ?></div>
                    <div class="stat-label">Students</div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($tables_exist['employers']): ?>
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="bi bi-building"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= number_format($stats['total_employers']) ?></div>
                    <div class="stat-label">Employers</div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($tables_exist['internships']): ?>
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="bi bi-briefcase"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= number_format($stats['active_internships']) ?></div>
                    <div class="stat-label">Active Internships</div>
                    <div class="stat-meta">of <?= number_format($stats['total_internships']) ?> total</div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($tables_exist['applications']): ?>
            <div class="stat-card">
                <div class="stat-icon danger">
                    <i class="bi bi-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= number_format($stats['pending_applications']) ?></div>
                    <div class="stat-label">Pending Applications</div>
                    <div class="stat-meta">of <?= number_format($stats['total_applications']) ?> total</div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($users_has_created_at && $stats['recent_signups'] > 0): ?>
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="bi bi-person-plus"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= number_format($stats['recent_signups']) ?></div>
                    <div class="stat-label">Recent Signups</div>
                    <div class="stat-meta">last 7 days</div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <!-- User Breakdown -->
                <div class="section-card">
                    <div class="section-title">
                        <i class="bi bi-pie-chart text-primary"></i>
                        User Distribution
                    </div>
                    
                    <?php if (!empty($analytics['user_breakdown'])): ?>
                        <div class="user-breakdown">
                            <?php 
                            $role_icons = ['Admin' => 'shield-check', 'Student' => 'mortarboard', 'Employer' => 'building', 'Alumni' => 'star'];
                            foreach ($analytics['user_breakdown'] as $role_data): 
                                $role_lower = strtolower($role_data['role']);
                                $icon = $role_icons[$role_data['role']] ?? 'person';
                            ?>
                                <div class="role-item">
                                    <div class="role-badge <?= $role_lower ?>">
                                        <i class="bi bi-<?= $icon ?>"></i>
                                    </div>
                                    <div class="role-count"><?= number_format($role_data['count']) ?></div>
                                    <div class="role-label"><?= $role_data['role'] ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3 text-muted">
                            <i class="bi bi-pie-chart" style="font-size: 2rem; opacity: 0.3;"></i>
                            <p class="mt-2">No user data available</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="section-card">
                    <div class="section-title">
                        <i class="bi bi-lightning text-primary"></i>
                        Quick Actions
                    </div>
                    
                    <div class="quick-actions">
                        <a href="manage-users.php" class="action-card">
                            <div class="action-icon">
                                <i class="bi bi-person-lines-fill"></i>
                            </div>
                            <div class="action-title">Manage Users</div>
                            <div class="action-description">View and manage accounts</div>
                        </a>

                        <?php if ($tables_exist['internships']): ?>
                        <a href="view-all-internships.php" class="action-card">
                            <div class="action-icon success">
                                <i class="bi bi-briefcase"></i>
                            </div>
                            <div class="action-title">View Internships</div>
                            <div class="action-description">Review postings</div>
                        </a>
                        <?php endif; ?>

                        <a href="#" class="action-card">
                            <div class="action-icon info">
                                <i class="bi bi-graph-up"></i>
                            </div>
                            <div class="action-title">Analytics</div>
                            <div class="action-description">View detailed reports</div>
                        </a>

                        <a href="#" class="action-card">
                            <div class="action-icon warning">
                                <i class="bi bi-gear"></i>
                            </div>
                            <div class="action-title">Settings</div>
                            <div class="action-description">System configuration</div>
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Recent Activities -->
                <div class="section-card">
                    <div class="section-title">
                        <i class="bi bi-activity text-primary"></i>
                        Recent Activities
                    </div>
                    
                    <?php if (empty($analytics['recent_activities'])): ?>
                        <div class="text-center py-3 text-muted">
                            <i class="bi bi-activity" style="font-size: 1.5rem; opacity: 0.3;"></i>
                            <p class="mt-2 mb-0">
                                <?= $users_has_created_at ? 'No recent activities' : 'Activity tracking unavailable' ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($analytics['recent_activities'] as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-avatar">
                                    <?= strtoupper(substr($activity['email'], 0, 1)) ?>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-text">
                                        <strong><?= htmlspecialchars($activity['email']) ?></strong> joined as <?= ucfirst($activity['role']) ?>
                                    </div>
                                    <div class="activity-time">
                                        <?= date('M j, g:i A', strtotime($activity['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- System Health -->
                <div class="section-card">
                    <div class="section-title">
                        <i class="bi bi-cpu text-primary"></i>
                        System Health
                    </div>
                    
                    <div class="health-item">
                        <span class="health-label">Database Size</span>
                        <span class="health-value"><?= $systemHealth['database_size'] ?></span>
                    </div>
                    <div class="health-item">
                        <span class="health-label">Total Tables</span>
                        <span class="health-value"><?= $systemHealth['total_tables'] ?></span>
                    </div>
                    <div class="health-item">
                        <span class="health-label">Admin Users</span>
                        <span class="health-value"><?= $systemHealth['admin_users'] ?></span>
                    </div>
                    <div class="health-item">
                        <span class="health-label">PHP Version</span>
                        <span class="health-value"><?= $systemHealth['php_version'] ?></span>
                    </div>
                    <div class="health-item">
                        <span class="health-label">MySQL Version</span>
                        <span class="health-value"><?= $systemHealth['mysql_version'] ?></span>
                    </div>
                </div>

                <!-- Database Status -->
                <div class="db-status">
                    <h6><i class="bi bi-database me-2"></i>Database Status</h6>
                    <div class="status-grid">
                        <div class="status-item">
                            <span class="status-check <?= $users_has_status ? 'success' : 'danger' ?>">
                                <?= $users_has_status ? '✓' : '✗' ?>
                            </span>
                            Status Column
                        </div>
                        <div class="status-item">
                            <span class="status-check <?= $users_has_created_at ? 'success' : 'danger' ?>">
                                <?= $users_has_created_at ? '✓' : '✗' ?>
                            </span>
                            Created Date
                        </div>
                        <div class="status-item">
                            <span class="status-check <?= $tables_exist['students'] ? 'success' : 'danger' ?>">
                                <?= $tables_exist['students'] ? '✓' : '✗' ?>
                            </span>
                            Students Table
                        </div>
                        <div class="status-item">
                            <span class="status-check <?= $tables_exist['employers'] ? 'success' : 'danger' ?>">
                                <?= $tables_exist['employers'] ? '✓' : '✗' ?>
                            </span>
                            Employers Table
                        </div>
                        <div class="status-item">
                            <span class="status-check <?= $tables_exist['internships'] ? 'success' : 'danger' ?>">
                                <?= $tables_exist['internships'] ? '✓' : '✗' ?>
                            </span>
                            Internships Table
                        </div>
                        <div class="status-item">
                            <span class="status-check <?= $tables_exist['applications'] ? 'success' : 'danger' ?>">
                                <?= $tables_exist['applications'] ? '✓' : '✗' ?>
                            </span>
                            Applications Table
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enhanced interactivity
        document.querySelectorAll('.stat-card, .action-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-4px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Debug information
        console.log('InternHub Admin Dashboard - Simplified Design');
        console.log('Database status:', {
            users_has_status: <?= $users_has_status ? 'true' : 'false' ?>,
            users_has_created_at: <?= $users_has_created_at ? 'true' : 'false' ?>,
            tables_exist: <?= json_encode($tables_exist) ?>
        });
    </script>
</body>
</html>