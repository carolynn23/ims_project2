<?php
session_start();

// Smart path detection for config.php
if (file_exists('../config.php')) {
    require_once '../config.php';
} elseif (file_exists('config.php')) {
    require_once 'config.php';
} else {
    die('Error: config.php not found');
}

// Check admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../secure_login.php");
    exit();
}

// Function to check if table exists
function tableExists($conn, $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

// Function to check if column exists
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

// Check available tables and columns
$tables_available = [
    'users' => tableExists($conn, 'users'),
    'students' => tableExists($conn, 'students'),
    'employers' => tableExists($conn, 'employers'),
    'alumni' => tableExists($conn, 'alumni'),
    'internships' => tableExists($conn, 'internships'),
    'applications' => tableExists($conn, 'applications')
];

$users_has_created_at = columnExists($conn, 'users', 'created_at');
$users_has_status = columnExists($conn, 'users', 'status');

// Get date range for reports
$date_range = $_GET['range'] ?? '30';
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime("-{$date_range} days"));

// Initialize analytics data
$analytics = [
    'overview' => [
        'total_users' => 0,
        'new_users_period' => 0,
        'total_internships' => 0,
        'total_applications' => 0,
        'active_internships' => 0
    ],
    'user_breakdown' => [],
    'recent_activity' => [],
    'popular_internships' => [],
    'application_trends' => []
];

try {
    // Overview Statistics
    if ($tables_available['users']) {
        $user_query = $users_has_status ? 
            "SELECT COUNT(*) as count FROM users WHERE status = 'active'" : 
            "SELECT COUNT(*) as count FROM users";
        $result = $conn->query($user_query);
        $analytics['overview']['total_users'] = $result->fetch_assoc()['count'];
        
        // New users in period
        if ($users_has_created_at) {
            $new_users_query = "SELECT COUNT(*) as count FROM users WHERE created_at >= ?";
            $stmt = $conn->prepare($new_users_query);
            $stmt->bind_param("s", $start_date);
            $stmt->execute();
            $analytics['overview']['new_users_period'] = $stmt->get_result()->fetch_assoc()['count'];
        }
    }
    
    if ($tables_available['internships']) {
        $internship_result = $conn->query("SELECT COUNT(*) as count FROM internships");
        $analytics['overview']['total_internships'] = $internship_result->fetch_assoc()['count'];
        
        // Active internships (if deadline column exists)
        if (columnExists($conn, 'internships', 'deadline')) {
            $active_query = "SELECT COUNT(*) as count FROM internships WHERE deadline >= CURDATE()";
            $active_result = $conn->query($active_query);
            $analytics['overview']['active_internships'] = $active_result->fetch_assoc()['count'];
        }
    }
    
    if ($tables_available['applications']) {
        $app_result = $conn->query("SELECT COUNT(*) as count FROM applications");
        $analytics['overview']['total_applications'] = $app_result->fetch_assoc()['count'];
    }
    
    // User breakdown by role
    if ($tables_available['users']) {
        $role_query = "SELECT role, COUNT(*) as count FROM users GROUP BY role ORDER BY count DESC";
        $role_result = $conn->query($role_query);
        while ($row = $role_result->fetch_assoc()) {
            $analytics['user_breakdown'][] = $row;
        }
    }
    
    // Recent activity (if created_at available)
    if ($tables_available['users'] && $users_has_created_at) {
        $activity_query = "
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as registrations
            FROM users 
            WHERE created_at >= ? 
            GROUP BY DATE(created_at) 
            ORDER BY date DESC 
            LIMIT 7
        ";
        $stmt = $conn->prepare($activity_query);
        $stmt->bind_param("s", $start_date);
        $stmt->execute();
        $activity_result = $stmt->get_result();
        while ($row = $activity_result->fetch_assoc()) {
            $analytics['recent_activity'][] = $row;
        }
    }
    
    // Popular internships (if applications table exists)
    if ($tables_available['internships'] && $tables_available['applications']) {
        $popular_query = "
            SELECT 
                i.title,
                COUNT(a.application_id) as application_count
            FROM internships i
            LEFT JOIN applications a ON i.internship_id = a.internship_id
            GROUP BY i.internship_id, i.title
            ORDER BY application_count DESC
            LIMIT 5
        ";
        $popular_result = $conn->query($popular_query);
        while ($row = $popular_result->fetch_assoc()) {
            $analytics['popular_internships'][] = $row;
        }
    }
    
} catch (Exception $e) {
    error_log("Analytics Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - InternHub Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-color: #696cff;
            --success-color: #71dd37;
            --warning-color: #ffb400;
            --danger-color: #ff3e1d;
            --info-color: #03c3ec;
            --text-primary: #566a7f;
            --text-secondary: #a8aaae;
            --border-color: #e4e6e8;
            --card-bg: #fff;
            --hover-bg: #f8f9fa;
            --shadow-sm: 0 2px 6px 0 rgba(67, 89, 113, 0.12);
            --shadow-lg: 0 6px 14px 0 rgba(67, 89, 113, 0.15);
            --border-radius: 8px;
            --border-radius-lg: 12px;
            --transition: all 0.2s ease-in-out;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f5f5f9;
        }

        .main-content {
            margin-left: 260px;
            padding: 2rem;
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, var(--info-color) 0%, var(--primary-color) 100%);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-15px) rotate(180deg); }
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            line-height: 1;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-top: 0.5rem;
            font-weight: 500;
        }

        .stat-change {
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }

        .stat-change.positive {
            color: var(--success-color);
        }

        .stat-change.negative {
            color: var(--danger-color);
        }

        .dashboard-card {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            background: var(--hover-bg);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .card-body {
            padding: 1.5rem;
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin: 1rem 0;
        }

        .filter-controls {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--border-color);
            padding: 1rem;
            margin-bottom: 2rem;
        }

        .btn-filter {
            background: var(--hover-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: var(--border-radius);
            padding: 0.5rem 1rem;
            margin: 0.25rem;
            transition: var(--transition);
        }

        .btn-filter.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .btn-filter:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .data-table {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        .table {
            margin: 0;
        }

        .table thead th {
            background: var(--hover-bg);
            border: none;
            font-weight: 600;
            color: var(--text-primary);
            padding: 1rem;
        }

        .table tbody td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .no-data {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }

        .export-btn {
            background: var(--success-color);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: var(--transition);
        }

        .export-btn:hover {
            background: #66c732;
            transform: translateY(-1px);
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
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/navbar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="position: relative; z-index: 2;">
                <h1 class="mb-2">Reports & Analytics</h1>
                <p class="mb-0">Comprehensive insights into your InternHub system</p>
            </div>
        </div>

        <!-- Filter Controls -->
        <div class="filter-controls">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <span class="me-3"><strong>Date Range:</strong></span>
                    <a href="?range=7" class="btn btn-filter <?= $date_range == '7' ? 'active' : '' ?>">Last 7 days</a>
                    <a href="?range=30" class="btn btn-filter <?= $date_range == '30' ? 'active' : '' ?>">Last 30 days</a>
                    <a href="?range=90" class="btn btn-filter <?= $date_range == '90' ? 'active' : '' ?>">Last 3 months</a>
                    <a href="?range=365" class="btn btn-filter <?= $date_range == '365' ? 'active' : '' ?>">Last year</a>
                </div>
                <div>
                    <button class="export-btn" onclick="exportReport()">
                        <i class="bi bi-download me-2"></i>Export Report
                    </button>
                </div>
            </div>
        </div>

        <!-- Overview Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($analytics['overview']['total_users']) ?></div>
                <div class="stat-label">Total Users</div>
                <?php if ($analytics['overview']['new_users_period'] > 0): ?>
                    <div class="stat-change positive">
                        <i class="bi bi-arrow-up"></i> +<?= $analytics['overview']['new_users_period'] ?> this period
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= number_format($analytics['overview']['total_internships']) ?></div>
                <div class="stat-label">Total Internships</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= number_format($analytics['overview']['active_internships']) ?></div>
                <div class="stat-label">Active Internships</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= number_format($analytics['overview']['total_applications']) ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
        </div>

        <div class="row">
            <!-- User Breakdown Chart -->
            <div class="col-lg-6">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-pie-chart me-2"></i>User Distribution
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($analytics['user_breakdown'])): ?>
                            <div class="chart-container">
                                <canvas id="userBreakdownChart"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="bi bi-pie-chart" style="font-size: 3rem; opacity: 0.3;"></i>
                                <p class="mt-2">No user data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Chart -->
            <div class="col-lg-6">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-activity me-2"></i>Registration Activity
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($analytics['recent_activity'])): ?>
                            <div class="chart-container">
                                <canvas id="activityChart"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="bi bi-activity" style="font-size: 3rem; opacity: 0.3;"></i>
                                <p class="mt-2">
                                    <?= $users_has_created_at ? 'No recent activity' : 'Activity tracking requires created_at column' ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Popular Internships -->
        <?php if (!empty($analytics['popular_internships'])): ?>
        <div class="dashboard-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-trophy me-2"></i>Most Popular Internships
                </h5>
            </div>
            <div class="card-body">
                <div class="data-table">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Internship Title</th>
                                <th>Applications</th>
                                <th>Popularity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analytics['popular_internships'] as $index => $internship): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-primary">#<?= $index + 1 ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($internship['title']) ?></td>
                                    <td><?= number_format($internship['application_count']) ?></td>
                                    <td>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-success" 
                                                 style="width: <?= ($internship['application_count'] / max(array_column($analytics['popular_internships'], 'application_count'))) * 100 ?>%">
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Database Status -->
        <div class="dashboard-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-database me-2"></i>Database Status & Available Features
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Available Tables:</h6>
                        <ul class="list-unstyled">
                            <?php foreach ($tables_available as $table => $exists): ?>
                                <li class="mb-1">
                                    <i class="bi bi-<?= $exists ? 'check-circle text-success' : 'x-circle text-danger' ?> me-2"></i>
                                    <?= ucfirst($table) ?> Table
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Analytics Features:</h6>
                        <ul class="list-unstyled">
                            <li class="mb-1">
                                <i class="bi bi-<?= $users_has_created_at ? 'check-circle text-success' : 'x-circle text-danger' ?> me-2"></i>
                                Registration Tracking
                            </li>
                            <li class="mb-1">
                                <i class="bi bi-<?= $users_has_status ? 'check-circle text-success' : 'x-circle text-danger' ?> me-2"></i>
                                User Status Filtering
                            </li>
                            <li class="mb-1">
                                <i class="bi bi-<?= $tables_available['applications'] ? 'check-circle text-success' : 'x-circle text-danger' ?> me-2"></i>
                                Application Analytics
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // User Breakdown Pie Chart
        <?php if (!empty($analytics['user_breakdown'])): ?>
        const userBreakdownCtx = document.getElementById('userBreakdownChart').getContext('2d');
        new Chart(userBreakdownCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($analytics['user_breakdown'], 'role')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($analytics['user_breakdown'], 'count')) ?>,
                    backgroundColor: [
                        '#696cff',
                        '#71dd37', 
                        '#ffb400',
                        '#ff3e1d',
                        '#03c3ec'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Activity Line Chart
        <?php if (!empty($analytics['recent_activity'])): ?>
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        new Chart(activityCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_reverse(array_column($analytics['recent_activity'], 'date'))) ?>,
                datasets: [{
                    label: 'Registrations',
                    data: <?= json_encode(array_reverse(array_column($analytics['recent_activity'], 'registrations'))) ?>,
                    borderColor: '#696cff',
                    backgroundColor: 'rgba(105, 108, 255, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        function exportReport() {
            // This would normally generate and download a report
            alert('Export functionality would be implemented here to generate PDF/Excel reports.');
        }
    </script>
</body>
</html>