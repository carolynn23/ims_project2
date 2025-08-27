<?php
session_start();
require_once '../config.php';

// Only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$message = '';

// Get the internship ID from the URL (GET)
if (isset($_GET['id'])) {
    $internship_id = (int)$_GET['id'];

    // Fetch internship details from the database
    $stmt = $conn->prepare("SELECT * FROM internships WHERE internship_id = ?");
    $stmt->bind_param("i", $internship_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $internship = $result->fetch_assoc();

    if (!$internship) {
        $message = "Internship not found!";
    }
} else {
    $message = "No internship ID provided.";
}

// Handle form submission (update internship)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['internship_id'])) {
    $internship_id = (int)$_POST['internship_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $requirements = $_POST['requirements'];
    $location = $_POST['location'];
    $deadline = $_POST['deadline'];

    // Update internship details in the database
    $update_stmt = $conn->prepare("
        UPDATE internships 
        SET title = ?, description = ?, requirements = ?, location = ?, deadline = ?
        WHERE internship_id = ?
    ");
    $update_stmt->bind_param("sssssi", $title, $description, $requirements, $location, $deadline, $internship_id);
    if ($update_stmt->execute()) {
        $message = "Internship updated successfully!";
        header("Location: view-all-internships.php");
        exit();
    } else {
        $message = "Failed to update internship. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Internship</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include '../includes/sidebar.php'; ?>
<?php include '../includes/navbar.php'; ?>

<div class="main-content container-fluid">
    <h2 class="mb-4">Edit Internship</h2>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($internship): ?>
        <form method="POST">
            <input type="hidden" name="internship_id" value="<?= $internship['internship_id'] ?>">

            <div class="mb-3">
                <label for="title" class="form-label">Internship Title</label>
                <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($internship['title']) ?>" required>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4" required><?= htmlspecialchars($internship['description']) ?></textarea>
            </div>

            <div class="mb-3">
                <label for="requirements" class="form-label">Requirements</label>
                <textarea class="form-control" id="requirements" name="requirements" rows="3" required><?= htmlspecialchars($internship['requirements']) ?></textarea>
            </div>

            <div class="mb-3">
                <label for="location" class="form-label">Location</label>
                <input type="text" class="form-control" id="location" name="location" value="<?= htmlspecialchars($internship['location']) ?>" required>
            </div>

            <div class="mb-3">
                <label for="deadline" class="form-label">Deadline</label>
                <input type="date" class="form-control" id="deadline" name="deadline" value="<?= htmlspecialchars($internship['deadline']) ?>" required>
            </div>

            <button type="submit" class="btn btn-primary">Update Internship</button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>
