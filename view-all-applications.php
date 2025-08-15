<?php
session_start();
require_once 'config.php';

// Verify employer login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get employer data
$stmt = $conn->prepare("SELECT employer_id, company_name FROM employers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();
$employer_id = $employer['employer_id'];
$company_name = $employer['company_name'];

// Handle Accept/Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id'], $_POST['action'])) {
    $application_id = $_POST['application_id'];
    $action = $_POST['action'];

    if (in_array($action, ['accepted', 'rejected'])) {
        $update = $conn->prepare("UPDATE applications SET status = ? WHERE application_id = ?");
        $update->bind_param("si", $action, $application_id);
        $update->execute();
    }
}

// Fetch all applications to employer’s internships
$sql = "
    SELECT 
        a.application_id, a.cover_letter, a.status, a.applied_at, a.resume,
        s.full_name, s.email, s.department, s.program,
        i.title AS internship_title
    FROM applications a
    JOIN students s ON a.student_id = s.student_id
    JOIN internships i ON a.internship_id = i.internship_id
    WHERE i.employer_id = ?
    ORDER BY a.applied_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$applications = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>All Applications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { margin: 0; }
        .main-content {
            margin-left: 250px;
            padding: 2rem;
            background-color: #f8f9fa;
            min-height: 100vh;
        }
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="main-content">
    <h2 class="mb-4">All Internship Applications</h2>

    <?php if ($applications->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Internship Title</th>
                        <th>Student Name</th>
                        <th>Program / Department</th>
                        <th>Email</th>
                        <th>Resume</th>
                        <th>Cover Letter</th>
                        <th>Status</th>
                        <th>Applied On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = $applications->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['internship_title']) ?></td>
                        <td><?= htmlspecialchars($row['full_name']) ?></td>
                        <td><?= htmlspecialchars($row['program'] . " / " . $row['department']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td>
                            <?php if (!empty($row['resume'])): ?>
                                <a href="../uploads/<?= $row['resume'] ?>" target="_blank">Download</a>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td><?= nl2br(htmlspecialchars($row['cover_letter'])) ?></td>
                        <td>
                            <span class="badge bg-<?= 
                                $row['status'] === 'accepted' ? 'success' : 
                                ($row['status'] === 'rejected' ? 'danger' : 'secondary'
                            ) ?>">
                                <?= ucfirst($row['status']) ?>
                            </span>
                        </td>
                        <td><?= $row['applied_at'] ?></td>
                        <td>
                            <?php if ($row['status'] === 'pending'): ?>
                                <form method="POST" class="d-flex gap-1">
                                    <input type="hidden" name="application_id" value="<?= $row['application_id'] ?>">
                                    <button name="action" value="accepted" class="btn btn-success btn-sm">Accept</button>
                                    <button name="action" value="rejected" class="btn btn-danger btn-sm">Reject</button>
                                </form>
                            <?php else: ?>
                                <em>No further action</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">No applications received yet.</p>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>

