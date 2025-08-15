<?php
session_start();
require_once 'config.php';

// Ensure user is logged in as employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: login.php");
    exit();
}

$internship_id = $_GET['internship_id'] ?? null;

if (!$internship_id) {
    echo "No internship selected.";
    exit();
}

// Fetch employer ID and company name
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT employer_id, company_name FROM employers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();
$employer_id = $employer['employer_id'];
$company_name = $employer['company_name'];

// Verify this internship belongs to the employer
$check = $conn->prepare("SELECT title FROM internships WHERE internship_id = ? AND employer_id = ?");
$check->bind_param("ii", $internship_id, $employer_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    echo "Unauthorized access.";
    exit();
}

$internship_title = $result->fetch_assoc()['title'];

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

// Fetch applications
$sql = "
    SELECT a.application_id, a.cover_letter, a.status, a.applied_at, a.resume,
           s.full_name, s.email, s.department, s.program
    FROM applications a
    JOIN students s ON a.student_id = s.student_id
    WHERE a.internship_id = ?
    ORDER BY a.applied_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $internship_id);
$stmt->execute();
$applications = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Applications for <?= htmlspecialchars($internship_title) ?></title>
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
    <h2 class="mb-4">Applications for: <strong><?= htmlspecialchars($internship_title) ?></strong></h2>

    <?php if ($applications->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
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
        <p class="text-muted">No students have applied yet.</p>
    <?php endif; ?>

    <a href="employer-dashboard.php" class="btn btn-secondary mt-4">← Back to Dashboard</a>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
