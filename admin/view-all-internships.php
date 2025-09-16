<?php
session_start();
require_once '../config.php';

// Only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$message = '';

// Handle delete internship
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_internship_id'])) {
    $internship_id = (int)$_POST['delete_internship_id'];
    
    // Delete internship
    $delete_stmt = $conn->prepare("DELETE FROM internships WHERE internship_id = ?");
    $delete_stmt->bind_param("i", $internship_id);
    $delete_stmt->execute();
    
    $message = "Internship deleted successfully.";
}

// Fetch all internships (pagination can be added if needed)
$internships_stmt = $conn->prepare("
    SELECT i.internship_id, i.title, i.location, i.deadline, e.company_name
    FROM internships i
    JOIN employers e ON i.employer_id = e.employer_id
    ORDER BY i.deadline DESC
");
$internships_stmt->execute();
$internships = $internships_stmt->get_result();

// Get total count for stats
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM internships");
$count_stmt->execute();
$total_internships = $count_stmt->get_result()->fetch_assoc()['total'];

// Get active internships (those with future deadlines)
$active_stmt = $conn->prepare("SELECT COUNT(*) as active FROM internships WHERE deadline >= CURDATE()");
$active_stmt->execute();
$active_internships = $active_stmt->get_result()->fetch_assoc()['active'];

// Get expired internships
$expired_internships = $total_internships - $active_internships;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View All Internships - InternHub Admin</title>
    
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
            background: linear-gradient(135deg, var(--primary-color) 0%, #696cff 100%);
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
        .stat-number.active { color: var(--success-color); }
        .stat-number.expired { color: var(--warning-color); }

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

        /* Table Styling */
        .table-card {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
            font-size: 0.875rem;
        }

        .table th {
            background: var(--hover-bg);
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

        /* Action Buttons */
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: var(--border-radius);
            transition: var(--transition);
            text-decoration: none;
            border: none;
            cursor: pointer;
        }

        .btn-warning {
            background: rgba(255, 180, 0, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(255, 180, 0, 0.3);
        }

        .btn-warning:hover {
            background: var(--warning-color);
            color: white;
            border-color: var(--warning-color);
        }

        .btn-danger {
            background: rgba(255, 62, 29, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(255, 62, 29, 0.3);
        }

        .btn-danger:hover {
            background: var(--danger-color);
            color: white;
            border-color: var(--danger-color);
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            border: 1px solid var(--primary-color);
        }

        .btn-primary:hover {
            background: var(--primary-light);
            border-color: var(--primary-light);
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

        .alert-info {
            background: rgba(3, 195, 236, 0.1);
            color: var(--info-color);
            border-left: 3px solid var(--info-color);
        }

        .alert-success {
            background: rgba(113, 221, 55, 0.1);
            color: var(--success-color);
            border-left: 3px solid var(--success-color);
        }

        /* Deadline Status */
        .deadline-status {
            padding: 0.25rem 0.625rem;
            border-radius: var(--border-radius);
            font-size: 0.75rem;
            font-weight: 500;
        }

        .deadline-active {
            background: rgba(113, 221, 55, 0.1);
            color: var(--success-color);
        }

        .deadline-expired {
            background: rgba(255, 62, 29, 0.1);
            color: var(--danger-color);
        }

        .deadline-soon {
            background: rgba(255, 180, 0, 0.1);
            color: var(--warning-color);
        }

        /* Company Name */
        .company-name {
            font-weight: 500;
            color: var(--text-primary);
        }

        .internship-title {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.125rem;
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

        /* Action Buttons Container */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            align-items: center;
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

            .action-buttons {
                flex-direction: column;
                gap: 0.25rem;
            }

            .btn-sm {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
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
        <h1><i class="bi bi-briefcase me-2"></i>View All Internships</h1>
        <p>Manage and review all internship postings</p>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-number total"><?= $total_internships ?></div>
            <div class="stat-label">Total Internships</div>
        </div>
        <div class="stat-card">
            <div class="stat-number active"><?= $active_internships ?></div>
            <div class="stat-label">Active Postings</div>
        </div>
        <div class="stat-card">
            <div class="stat-number expired"><?= $expired_internships ?></div>
            <div class="stat-label">Expired</div>
        </div>
    </div>

    

    <!-- Internships Table -->
    <?php if ($internships->num_rows === 0): ?>
        <div class="section-card">
            <div class="no-data">
                <i class="bi bi-briefcase"></i>
                <h4>No Internships Found</h4>
                <p>No internships have been posted yet.</p>
                <a href="post-internship.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Post Your First Internship
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="table-card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Internship Details</th>
                            <th>Company</th>
                            <th>Location</th>
                            <th>Deadline</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($internship = $internships->fetch_assoc()): 
                            $deadline_date = strtotime($internship['deadline']);
                            $current_date = time();
                            $days_until_deadline = floor(($deadline_date - $current_date) / (60 * 60 * 24));
                            
                            if ($days_until_deadline < 0) {
                                $deadline_class = 'deadline-expired';
                                $deadline_text = 'Expired';
                            } elseif ($days_until_deadline <= 7) {
                                $deadline_class = 'deadline-soon';
                                $deadline_text = $days_until_deadline . ' days left';
                            } else {
                                $deadline_class = 'deadline-active';
                                $deadline_text = 'Active';
                            }
                        ?>
                            <tr>
                                <td>
                                    <div class="internship-title"><?= htmlspecialchars($internship['title']) ?></div>
                                    <small class="text-muted">ID: <?= $internship['internship_id'] ?></small>
                                </td>
                                <td>
                                    <div class="company-name"><?= htmlspecialchars($internship['company_name']) ?></div>
                                </td>
                                <td>
                                    <span class="text-muted"><?= htmlspecialchars($internship['location']) ?></span>
                                </td>
                                <td>
                                    <div><?= date('M j, Y', strtotime($internship['deadline'])) ?></div>
                                    <span class="deadline-status <?= $deadline_class ?>"><?= $deadline_text ?></span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                       

                                        <!-- Delete Button -->
                                        <form method="POST" style="display:inline;" 
                                              onsubmit="return confirm('Are you sure you want to delete this internship? This action cannot be undone.')">
                                            <input type="hidden" name="delete_internship_id" value="<?= $internship['internship_id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="bi bi-trash me-1"></i>Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                alert.style.display = 'none';
            }, 5000);
        });
    });

    console.log('Internships listing page initialized');
</script>

</body>
</html>