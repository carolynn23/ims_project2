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
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
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
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }

        .filter-card {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }

        .applications-table {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: var(--hover-bg);
        }

        .table thead th {
            border: none;
            background: transparent;
            font-weight: 600;
            color: var(--text-primary);
            padding: 1rem;
        }

        .table tbody td {
            border: none;
            padding: 1rem;
            vertical-align: middle;
        }

        .table tbody tr {
            border-bottom: 1px solid var(--border-color);
        }

        .table tbody tr:hover {
            background: var(--hover-bg);
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
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

        .filter-pills {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .filter-pill {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--transition);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        .filter-pill.active,
        .filter-pill:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            text-decoration: none;
        }

        .no-data {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .pagination {
            justify-content: center;
            margin-top: 2rem;
        }

        .page-link {
            border-radius: var(--border-radius);
            margin: 0 0.125rem;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .page-link:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .page-item.active .page-link {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .database-status {
            background: var(--info-color);
            color: white;
            padding: 1rem;
            border-radius: var(--border-radius-lg);
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .filter-pills {
                justify-content: center;
            }
            
            .table-responsive {
                font-size: 0.875rem;
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
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">Applications Management</h1>
                    <p class="text-muted mb-0">Oversee and manage all internship applications</p>
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
            <div class="database-status">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle me-3" style="font-size: 1.5rem;"></i>
                    <div>
                        <strong>Applications table not found!</strong>
                        <p class="mb-0">The applications management feature requires the 'applications' table to be created in your database.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Alert Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($tables_exist['applications']): ?>
        <!-- Filters -->
        <div class="filter-card">
            <div class="row align-items-end">
                <div class="col-lg-8">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small text-muted">Filter by Status</label>
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
                </div>
                <div class="col-lg-4 mb-3">
                    <form method="GET" class="d-flex">
                        <?php foreach (['status'] as $param): ?>
                            <?php if (isset($_GET[$param])): ?>
                                <input type="hidden" name="<?= $param ?>" value="<?= htmlspecialchars($_GET[$param]) ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search applications..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-outline-primary ms-2">
                            <i class="bi bi-search"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Applications Table -->
        <div class="applications-table">
            <div class="table-header">
                <h5 class="mb-0">Applications (<?= $total_applications ?> total)</h5>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Internship</th>
                            <th>Company</th>
                            <th>Status</th>
                            <th>Applied Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($applications)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="no-data">
                                        <i class="bi bi-file-earmark-text" style="font-size: 3rem; opacity: 0.3;"></i>
                                        <p class="mt-3">
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
                                    <td><strong>#<?= $app['application_id'] ?></strong></td>
                                    <td>
                                        <div>
                                            <div class="fw-semibold"><?= htmlspecialchars($app['student_name'] ?: 'N/A') ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($app['student_email'] ?: 'N/A') ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($app['internship_title'] ?: 'N/A') ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($app['company_name'] ?: 'N/A') ?></td>
                                    <td>
                                        <span class="status-badge status-<?= $app['status'] ?>">
                                            <?= $app['status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div><?= date('M j, Y', strtotime($app['applied_at'])) ?></div>
                                        <div class="small text-muted"><?= date('g:i A', strtotime($app['applied_at'])) ?></div>
                                    </td>
                                    <td>
                                        <div class="d-flex">
                                            <?php if ($app['status'] !== 'approved'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="application_id" value="<?= $app['application_id'] ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="action-btn btn-approve" 
                                                            title="Approve Application" onclick="return confirm('Approve this application?')">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($app['status'] !== 'rejected'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="application_id" value="<?= $app['application_id'] ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="action-btn btn-reject" 
                                                            title="Reject Application" onclick="return confirm('Reject this application?')">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="application_id" value="<?= $app['application_id'] ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="action-btn btn-delete" 
                                                        title="Delete Application" onclick="return confirm('Are you sure you want to delete this application? This action cannot be undone.')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function exportApplications() {
            // This would normally export applications data
            alert('Export functionality would generate CSV/Excel file with application data.');
        }
    </script>
</body>
</html>