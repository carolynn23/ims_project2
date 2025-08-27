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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View All Internships</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { margin: 0; background: #f8f9fa; }
        .main-content { margin-left: 260px; padding: 2rem; }
        .card { margin-bottom: 1rem; }
        .card-title { font-weight: 600; }
        .badge-status { padding: 0.3rem 0.6rem; font-size: 0.85rem; }
    </style>
</head>
<body>

<?php include '../includes/sidebar.php'; ?>
<?php include '../includes/navbar.php'; ?>

<div class="main-content container-fluid">
    <h2 class="mb-4">View All Internships</h2>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Internships Table -->
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Internship Title</th>
                    <th>Company</th>
                    <th>Location</th>
                    <th>Deadline</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($internship = $internships->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($internship['title']) ?></td>
                        <td><?= htmlspecialchars($internship['company_name']) ?></td>
                        <td><?= htmlspecialchars($internship['location']) ?></td>
                        <td><?= htmlspecialchars($internship['deadline']) ?></td>
                        <td>
                            <!-- Edit Button (Optional for future) -->
                            <a href="edit-internship.php?id=<?= $internship['internship_id'] ?>" class="btn btn-warning btn-sm">Edit</a>

                            <!-- Delete Button -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="delete_internship_id" value="<?= $internship['internship_id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
