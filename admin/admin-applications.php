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

// Function to check if table/column exists
function tableExists($conn, $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

// Check database structure
$tables_exist = [
    'applications' => tableExists($conn, 'applications'),
    'students' => tableExists($conn, 'students'),
    'internships' => tableExists($conn, 'internships'),
    'employers' => tableExists($conn, 'employers')
];

$message = '';
$message_type = '';

// Handle application actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $tables_exist['applications']) {
    $application_id = (int)($_POST['application_id'] ?? 0);
    $action = $_POST['action'];
    
    try {
        switch ($action) {
            case 'approve':
                $stmt = $conn->prepare("UPDATE applications SET status = 'approved' WHERE application_id = ?");
                $stmt->bind_param("i", $application_id);
                if ($stmt->execute()) {
                    $message = "Application approved successfully!";
                    $message_type = "success";
                }
                break;
                
            case 'reject':
                $stmt = $conn->prepare("UPDATE applications SET status = 'rejected' WHERE application_id = ?");
                $stmt->bind_param("i", $application_id);
                if ($stmt->execute()) {
                    $message = "Application rejected successfully!";
                    $message_type = "warning";
                }
                break;
                
            case 'delete':
                $stmt = $conn->prepare("DELETE FROM applications WHERE application_id = ?");
                $stmt->bind_param("i", $application_id);
                if ($stmt->execute()) {
                    $message = "Application deleted successfully!";
                    $message_type = "danger";
                }
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Initialize data
$applications = [];
$total_applications = 0;
$status_counts = [];

if ($tables_exist['applications']) {
    try {
        // Build query conditions
        $where_conditions = [];
        $params = [];
        $param_types = '';
        
        if ($status_filter !== 'all' && columnExists($conn, 'applications', 'status')) {
            $where_conditions[] = "a.status = ?";
            $params[] = $status_filter;
            $param_types .= 's';
        }
        
        if (!empty($search)) {
            if ($tables_exist['students'] && $tables_exist['internships']) {
                $where_conditions[] = "(s.full_name LIKE ? OR s.email LIKE ? OR i.title LIKE ?)";
                $search_param = "%$search%";
                $params = array_merge($params, [$search_param, $search_param, $search_param]);
                $param_types .= 'sss';
            }
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count
        $count_query = "SELECT COUNT(*) as total FROM applications a";
        if ($tables_exist['students']) {
            $count_query .= " LEFT JOIN students s ON a.student_id = s.student_id";
        }
        if ($tables_exist['internships']) {
            $count_query .= " LEFT JOIN internships i ON a.internship_id = i.internship_id";
        }
        $count_query .= " $where_clause";
        
        if (!empty($params)) {
            $count_stmt = $conn->prepare($count_query);
            $count_stmt->bind_param($param_types, ...$params);
            $count_stmt->execute();
            $total_applications = $count_stmt->get_result()->fetch_assoc()['total'];
        } else {
            $total_applications = $conn->query($count_query)->fetch_assoc()['total'];
        }
        
        // Get applications with pagination
        $applications_query = "
            SELECT 
                a.application_id,
                a.student_id,
                a.internship_id,
                " . (columnExists($conn, 'applications', 'status') ? "a.status," : "'pending' as status,") . "
                " . (columnExists($conn, 'applications', 'applied_at') ? "a.applied_at," : "NOW() as applied_at,") . "
                " . (columnExists($conn, 'applications', 'cover_letter') ? "a.cover_letter," : "'' as cover_letter,") . "
                " . ($tables_exist['students'] ? "s.full_name as student_name, s.email as student_email," : "'' as student_name, '' as student_email,") . "
                " . ($tables_exist['internships'] ? "i.title as internship_title," : "'' as internship_title,") . "
                " . ($tables_exist['employers'] ? "e.company_name," : "'' as company_name,") . "
                " . ($tables_exist['internships'] && $tables_exist['employers'] ? "em.user_id as employer_user_id" : "0 as employer_user_id") . "
            FROM applications a
            " . ($tables_exist['students'] ? "LEFT JOIN students s ON a.student_id = s.student_id" : "") . "
            " . ($tables_exist['internships'] ? "LEFT JOIN internships i ON a.internship_id = i.internship_id" : "") . "
            " . ($tables_exist['employers'] && $tables_exist['internships'] ? "LEFT JOIN employers e ON i.employer_id = e.employer_id LEFT JOIN users em ON e.user_id = em.user_id" : "") . "
            $where_clause
            ORDER BY " . (columnExists($conn, 'applications', 'applied_at') ? "a.applied_at" : "a.application_id") . " DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        $param_types .= 'ii';
        
        $stmt = $conn->prepare($applications_query);
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        $stmt->execute();
        $applications_result = $stmt->get_result();
        
        while ($row = $applications_result->fetch_assoc()) {
            $applications[] = $row;
        }
        
        // Get status counts
        if (columnExists($conn, 'applications', 'status')) {
            $status_query = "SELECT status, COUNT(*) as count FROM applications GROUP BY status";
            $status_result = $conn->query($status_query);
            while ($row = $status_result->fetch_assoc()) {
                $status_counts[$row['status']] = $row['count'];
            }
        }
        
    } catch (Exception $e) {
        error_log("Applications Query Error: " . $e->getMessage());
    }
}

$total_pages = ceil($total_applications / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications Management - InternHub Admin</title>
    
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

        /* Main Content */
        .main-content {
            margin-left: 260px;
            margin-top: 70px;
            padding: 1.5rem;
            min-height: calc(100vh - 70px);
            transition: var(--transition);
        }

        /* Page Header */
        .welcome-header {
            background: linear-gradient(135deg, var(--info-color) 0%, #029bc5 100%);
            border-radius: var(--border-radius-lg);
            color: white;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .welcome-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0 0 0.25rem 0;
        }

        .welcome-header p {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0;
        }

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            padding: 1.25rem;
            text-align: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
        }

        .stat-number {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-number.total { color: var(--primary-color); }
        .stat-number.pending { color: var(--warning-color); }
        .stat-number.approved { color: var(--success-color); }
        .stat-number.rejected { color: var(--danger-color); }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        /* Section Cards */
        .section-card {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
        }

        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }

        /* Database Status Alert */
        .status-alert {
            background: var(--info-color);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius-lg);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .status-alert i {
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .status-alert-content h6 {
            margin: 0 0 0.25rem 0;
            font-weight: 600;
        }

        .status-alert-content p {
            margin: 0;
            font-size: 0.875rem;
            opacity: 0.9;
        }

        /* Filter Groups */
        .filter-group {
            margin-bottom: 1.25rem;
        }

        .filter-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .filter-pills {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .filter-pill {
            padding: 0.5rem 0.875rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--transition);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            background: var(--card-bg);
        }

        .filter-pill:hover {
            background: var(--hover-bg);
            color: var(--text-primary);
            text-decoration: none;
        }

        .filter-pill.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        /* Search Form */
        .search-form {
            display: flex;
            gap: 0.5rem;
        }

        .form-control {
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 0.75rem;
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(105, 108, 255, 0.1);
            outline: none;
        }

        .btn-outline-primary {
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
            border-radius: var(--border-radius);
            padding: 0.75rem 1rem;
            font-weight: 500;
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary {
            background: var(--primary-color);
            border: 1px solid var(--primary-color);
            color: white;
            border-radius: var(--border-radius);
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .btn-primary:hover {
            background: var(--primary-light);
            border-color: var(--primary-light);
        }

        /* Table Styling */
        .table-card {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .table-header {
            background: var(--hover-bg);
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .table-header h5 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .table {
            margin-bottom: 0;
            font-size: 0.875rem;
        }

        .table th {
            background: transparent;
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            color: var(--text-primary);
            padding: 1rem;
            font-size: 0.875rem;
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .table tbody tr:hover {
            background: var(--hover-bg);
        }

        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.625rem;
            border-radius: var(--border-radius);
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: rgba(255, 180, 0, 0.1);
            color: var(--warning-color);
        }

        .status-approved {
            background: rgba(113, 221, 55, 0.1);
            color: var(--success-color);
        }

        .status-rejected {
            background: rgba(255, 62, 29, 0.1);
            color: var(--danger-color);
        }

        /* Action Buttons */
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 0.125rem;
            transition: var(--transition);
            cursor: pointer;
            font-size: 0.875rem;
        }

        .action-btn:hover {
            transform: scale(1.1);
        }

        .btn-approve {
            background: rgba(113, 221, 55, 0.1);
            color: var(--success-color);
        }

        .btn-approve:hover {
            background: var(--success-color);
            color: white;
        }

        .btn-reject {
            background: rgba(255, 180, 0, 0.1);
            color: var(--warning-color);
        }

        .btn-reject:hover {
            background: var(--warning-color);
            color: white;
        }

        .btn-delete {
            background: rgba(255, 62, 29, 0.1);
            color: var(--danger-color);
        }

        .btn-delete:hover {
            background: var(--danger-color);
            color: white;
        }

        /* Alerts */
        .alert {
            border-radius: var(--border-radius);
            border: none;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .alert-success {
            background: rgba(113, 221, 55, 0.1);
            color: var(--success-color);
            border-left: 3px solid var(--success-color);
        }

        .alert-warning {
            background: rgba(255, 180, 0, 0.1);
            color: var(--warning-color);
            border-left: 3px solid var(--warning-color);
        }

        .alert-danger {
            background: rgba(255, 62, 29, 0.1);
            color: var(--danger-color);
            border-left: 3px solid var(--danger-color);
        }

        /* No Data State */
        .no-data {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-secondary);
        }

        .no-data i {
            font-size: 3rem;
            opacity: 0.3;
            margin-bottom: 1rem;
        }

        /* User Info */
        .student-info {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.125rem;
        }

        .student-email {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .internship-title {
            font-weight: 500;
            color: var(--text-primary);
        }

        .application-id {
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Pagination */
        .pagination {
            justify-content: center;
            margin-top: 1.5rem;
        }

        .page-link {
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }

        .page-link:hover {
            background: var(--hover-bg);
            color: var(--text-primary);
        }

        .page-item.active .page-link {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .stats-container {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .welcome-header {
                padding: 1.25rem;
            }

            .section-card {
                padding: 1.25rem;
            }

            .filter-pills {
                justify-content: center;
            }

            .search-form {
                flex-direction: column;
            }

            .table-responsive {
                font-size: 0.8125rem;
            }

            .action-btn {
                width: 28px;
                height: 28px;
                font-size: 0.75rem;
            }

            .status-alert {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/navbar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="welcome-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="bi bi-file-earmark-text me-2"></i>Applications Management</h1>
                    <p>Review and manage all internship applications</p>
                </div>
                <div>
                    <button class="btn btn-primary" onclick="exportApplications()">
                        <i class="bi bi-download me-2"></i>Export Data
                    </button>
                </div>
            </div>
        </div>

        <!-- Database Status Check -->
        <?php if (!$tables_exist['applications']): ?>
            <div class="status-alert">
                <i class="bi bi-exclamation-triangle"></i>
                <div class="status-alert-content">
                    <h6>Applications table not found!</h6>
                    <p>The applications management feature requires the 'applications' table to be created in your database.</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Alert Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?= ($message_type === 'success') ? 'check-circle' : (($message_type === 'warning') ? 'exclamation-triangle' : 'x-circle') ?> me-2"></i>
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($tables_exist['applications']): ?>
        <!-- Statistics -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number total"><?= $total_applications ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
            <?php foreach ($status_counts as $status => $count): ?>
                <div class="stat-card">
                    <div class="stat-number <?= $status ?>"><?= $count ?></div>
                    <div class="stat-label"><?= ucfirst($status) ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Filters -->
        <div class="section-card">
            <div class="section-title">
                <i class="bi bi-funnel"></i>
                Filter Applications
            </div>
            
            <div class="row">
                <div class="col-lg-6">
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <div class="filter-pills">
                            <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'all'])) ?>" 
                               class="filter-pill <?= $status_filter === 'all' ? 'active' : '' ?>">
                                All (<?= $total_applications ?>)
                            </a>
                            <?php foreach ($status_counts as $status => $count): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['status' => $status])) ?>" 
                                   class="filter-pill <?= $status_filter === $status ? 'active' : '' ?>">
                                    <?= ucfirst($status) ?> (<?= $count ?>)
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <form method="GET" class="search-form">
                            <?php foreach (['status'] as $param): ?>
                                <?php if (isset($_GET[$param])): ?>
                                    <input type="hidden" name="<?= $param ?>" value="<?= htmlspecialchars($_GET[$param]) ?>">
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Search applications..." value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bi bi-search"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Applications Table -->
        <div class="table-card">
            <div class="table-header">
                <h5>Applications (<?= $total_applications ?> total)</h5>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Internship</th>
                            <th>Company</th>
                            <th>Status</th>
                            <th>Applied Date</th>
                           
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($applications)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="no-data">
                                        <i class="bi bi-file-earmark-text"></i>
                                        <h4>No Applications Found</h4>
                                        <p>
                                            <?php if (!empty($search)): ?>
                                                No applications found matching your search.
                                            <?php else: ?>
                                                No applications found.
                                            <?php endif; ?>
                                        </p>
                                        <?php if (!empty($search)): ?>
                                            <a href="admin-applications.php" class="btn btn-outline-primary">
                                                <i class="bi bi-arrow-left me-2"></i>Show All Applications
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td><span class="application-id">#<?= $app['application_id'] ?></span></td>
                                    <td>
                                        <div class="student-info"><?= htmlspecialchars($app['student_name'] ?: 'N/A') ?></div>
                                        <div class="student-email"><?= htmlspecialchars($app['student_email'] ?: 'N/A') ?></div>
                                    </td>
                                    <td>
                                        <div class="internship-title"><?= htmlspecialchars($app['internship_title'] ?: 'N/A') ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($app['company_name'] ?: 'N/A') ?></td>
                                    <td>
                                        <span class="status-badge status-<?= $app['status'] ?>">
                                            <?= $app['status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div><?= date('M j, Y', strtotime($app['applied_at'])) ?></div>
                                        <div class="student-email"><?= date('g:i A', strtotime($app['applied_at'])) ?></div>
                                    </td>
                                    <td>
                                        
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Applications pagination">
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function exportApplications() {
            // This would normally export applications data
            alert('Export functionality would generate CSV/Excel file with application data.');
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    if (alert.style.display !== 'none') {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                }, 5000);
            });
        });

        console.log('Applications management page initialized');
    </script>
</body>
</html>