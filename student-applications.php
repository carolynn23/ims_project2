<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php'); 
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Get student ID
$st = $conn->prepare("SELECT student_id FROM students WHERE user_id=?");
$st->bind_param("i", $user_id);
$st->execute();
$student_result = $st->get_result()->fetch_assoc();
$student_id = (int)($student_result['student_id'] ?? 0);

if ($student_id <= 0) {
    echo "Student profile not found."; 
    exit();
}

// Handle feedback messages
$success_message = '';
$error_message = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'application_withdrawn':
            $success_message = '✅ Application successfully withdrawn.';
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'invalid_application':
            $error_message = '❌ Invalid application ID.';
            break;
        case 'invalid_student':
            $error_message = '❌ Student profile error.';
            break;
        case 'application_not_found':
            $error_message = '❌ Application not found or doesn\'t belong to you.';
            break;
        case 'withdrawal_failed':
            $error_message = '❌ Failed to withdraw application. Please try again.';
            break;
        case 'cannot_withdraw':
            $status = htmlspecialchars($_GET['status'] ?? 'unknown');
            $error_message = "❌ Cannot withdraw application with status: " . ucfirst($status);
            break;
    }
}

// Fetch all applications for this student
$sql = "
    SELECT a.application_id, a.cover_letter, a.status, a.applied_at, a.resume,
           i.title, i.location, i.duration, 
           e.company_name
    FROM applications a
    JOIN internships i ON a.internship_id = i.internship_id
    JOIN employers e ON i.employer_id = e.employer_id
    WHERE a.student_id = ?
    ORDER BY a.applied_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$applications = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications - Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { margin: 0; }
        .main-content {
            margin-left: 250px;
            padding: 2rem;
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .status-badge {
            font-size: 0.85rem;
            font-weight: 500;
        }
        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
        }
        .alert {
            border: none;
            border-radius: 10px;
            font-weight: 500;
        }
        .table th {
            background-color: #fff;
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        .application-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        .application-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }
    </style>
</head>

<body>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-file-text"></i> My Applications</h2>
        <a href="student-dashboard.php" class="btn btn-outline-primary">
            <i class="bi bi-house-door"></i> Back to Dashboard
        </a>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $success_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $error_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($applications->num_rows > 0): ?>
        <div class="application-card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Internship</th>
                            <th>Company</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Applied Date</th>
                            <th>Resume</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $applications->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($row['title']) ?></strong>
                                <br><small class="text-muted"><?= htmlspecialchars($row['duration']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($row['company_name']) ?></td>
                            <td><?= htmlspecialchars($row['location']) ?></td>
                            <td>
                                <span class="badge status-badge bg-<?= 
                                    $row['status'] === 'accepted' ? 'success' : 
                                    ($row['status'] === 'rejected' ? 'danger' : 
                                    ($row['status'] === 'withdrawn' ? 'warning' : 'secondary')) ?>">
                                    <?= htmlspecialchars(ucfirst($row['status'])) ?>
                                </span>
                            </td>
                            <td><?= date('M j, Y', strtotime($row['applied_at'])) ?></td>
                            <td>
                                <?php if (!empty($row['resume'])): ?>
                                    <?php 
                                    $resume_data = json_decode($row['resume'], true);
                                    if (is_array($resume_data)): ?>
                                        <?php foreach($resume_data as $resume_file): ?>
                                            <a href="uploads/<?= rawurlencode($resume_file) ?>" target="_blank" class="btn btn-sm btn-outline-info me-1">
                                                <i class="bi bi-download"></i> Resume
                                            </a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <a href="uploads/<?= rawurlencode($row['resume']) ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                            <i class="bi bi-download"></i> Download
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['status'] === 'pending'): ?>
                                    <form method="post" action="withdraw-application.php" style="display: inline;" 
                                          onsubmit="return confirm('⚠️ Are you sure you want to withdraw this application? This action cannot be undone.');">
                                        <input type="hidden" name="application_id" value="<?= (int)$row['application_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-x-circle"></i> Withdraw
                                        </button>
                                    </form>
                                <?php elseif ($row['status'] === 'withdrawn'): ?>
                                    <span class="text-muted small">
                                        <i class="bi bi-x-circle-fill"></i> Withdrawn
                                    </span>
                                <?php elseif ($row['status'] === 'accepted'): ?>
                                    <span class="text-success small">
                                        <i class="bi bi-check-circle-fill"></i> Accepted
                                    </span>
                                <?php elseif ($row['status'] === 'rejected'): ?>
                                    <span class="text-danger small">
                                        <i class="bi bi-x-circle-fill"></i> Rejected
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="row mt-4">
            <?php
            $applications->data_seek(0); // Reset pointer
            $stats = ['pending' => 0, 'accepted' => 0, 'rejected' => 0, 'withdrawn' => 0];
            while ($row = $applications->fetch_assoc()) {
                $stats[$row['status']]++;
            }
            ?>
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5><?= $stats['pending'] ?></h5>
                        <small>Pending</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5><?= $stats['accepted'] ?></h5>
                        <small>Accepted</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h5><?= $stats['rejected'] ?></h5>
                        <small>Rejected</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h5><?= $stats['withdrawn'] ?></h5>
                        <small>Withdrawn</small>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <div class="text-center py-5">
            <div class="application-card">
                <div class="card-body py-5">
                    <i class="bi bi-inbox display-1 text-muted"></i>
                    <h4 class="mt-3 text-muted">No Applications Yet</h4>
                    <p class="text-muted mb-4">You haven't applied to any internships yet. Start exploring opportunities!</p>
                    <a href="student-dashboard.php" class="btn btn-primary">
                        <i class="bi bi-search"></i> Browse Internships
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>