<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT employer_id, company_name FROM employers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();

$employer_id = $employer['employer_id'];
$company_name = $employer['company_name'];

// Fetch internships posted by employer
$sql = "
    SELECT i.internship_id, i.title, i.location, i.deadline, 
           COUNT(a.application_id) AS application_count
    FROM internships i
    LEFT JOIN applications a ON i.internship_id = a.internship_id
    WHERE i.employer_id = ?
    GROUP BY i.internship_id
    ORDER BY i.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$internships = $stmt->get_result();


// Handle internship deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];

    // Ensure the internship belongs to the employer before deleting
    $verify = $conn->prepare("SELECT internship_id FROM internships WHERE internship_id = ? AND employer_id = ?");
    $verify->bind_param("ii", $delete_id, $employer_id);
    $verify->execute();
    $result = $verify->get_result();

    if ($result->num_rows > 0) {
        $delete = $conn->prepare("DELETE FROM internships WHERE internship_id = ?");
        $delete->bind_param("i", $delete_id);
        $delete->execute();
        // Optionally delete related applications too (foreign key cascade?)
    }
}

?>

<!DOCTYPE html>
<html>
<head>
  <title>Employer Dashboard</title>
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
  <h2 class="mb-4">Your Posted Internships</h2>

  <a href="post-internship.php" class="btn btn-success mb-3">+ Post New Internship</a>

  <?php if ($internships->num_rows > 0): ?>
    <div class="table-responsive">
      <table class="table table-bordered table-striped align-middle">
        <thead class="table-light">
          <tr>
            <th>Title</th>
            <th>Location</th>
            <th>Deadline</th>
            <th>Applications</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $internships->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($row['title']) ?></td>
              <td><?= htmlspecialchars($row['location']) ?></td>
              <td><?= htmlspecialchars($row['deadline']) ?></td>
              <td><?= $row['application_count'] ?></td>
              <td class="d-flex gap-1">
                    <a href="view-applications.php?internship_id=<?= $row['internship_id'] ?>" class="btn btn-sm btn-primary">View</a>
                    <a href="edit-internship.php?internship_id=<?= $row['internship_id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this internship?');">
                        <input type="hidden" name="delete_id" value="<?= $row['internship_id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                    </form>
              </td>
              
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="text-muted">You haven’t posted any internships yet.</p>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
