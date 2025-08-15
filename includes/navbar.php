<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$displayName = $_SESSION['display_name'] ?? 'User';
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow px-4" style="margin-left:250px;">
  <div class="container-fluid d-flex justify-content-between">
    <a class="navbar-brand fw-bold" href="dashboard.php">Internship Portal</a>
    <div class="d-flex align-items-center gap-3">
      <span class="fw-medium"><?= htmlspecialchars($displayName) ?></span>
      <img src="https://i.pravatar.cc/30" class="rounded-circle" width="30" height="30" alt="user">
    </div>
  </div>
</nav>
>
