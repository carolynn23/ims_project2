<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
  header('Location: login.php'); exit();
}

// Query for total available internships
$total_internships = $conn->query("SELECT COUNT(*) FROM internships WHERE deadline >= CURDATE()")->fetch_row()[0];

// Query for total students who have applied
$total_applications = $conn->query("SELECT COUNT(DISTINCT student_id) FROM applications")->fetch_row()[0];
?>
<!DOCTYPE html>
<html>
<head>
  <title>Student Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { margin: 0; }
    .main { margin-left: 250px; padding: 2rem; background-color: #f8f9fa; min-height: 100vh; }
    .card { margin-bottom: 1rem; }
  </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="main">
  <h2 class="mb-4">Dashboard</h2>
  
  <!-- Analytics Section -->
  <div class="row">
    <div class="col-md-6">
      <div class="card bg-light">
        <div class="card-body">
          <h5 class="card-title">Total Available Internships</h5>
          <p class="card-text"><?= $total_internships ?> internships available</p>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card bg-light">
        <div class="card-body">
          <h5 class="card-title">Total Students Applied</h5>
          <p class="card-text"><?= $total_applications ?> students have applied</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Dummy Text or Additional Information -->
  <div class="row">
    <div class="col-md-12">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Dummy Text Section</h5>
          <p class="card-text">Here you can add any basic text or instructions for the students regarding internships and applications. For example: "Check the number of available internships, and apply early for the best chances!"</p>
        </div>
      </div>
    </div>
  </div>

</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
