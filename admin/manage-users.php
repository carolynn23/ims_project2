<?php
session_start();
require_once '../config.php';

// Only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Fetch users (students, employers, and alumni)
$roleFilter = isset($_GET['role']) ? $_GET['role'] : 'all';
$sql = "SELECT u.user_id, u.email, u.status, r.role, u.created_at FROM users u JOIN roles r ON u.role_id = r.role_id";

// If a specific role filter is applied
if ($roleFilter !== 'all') {
    $sql .= " WHERE r.role = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $roleFilter);
} else {
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$users = $stmt->get_result();

// Handle user status change (activate/deactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['action'])) {
    $user_id = (int)$_POST['user_id'];
    $action = $_POST['action'];
    $new_status = ($action === 'activate') ? 'active' : 'inactive';

    $update_stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
    $update_stmt->bind_param("si", $new_status, $user_id);
    $update_stmt->execute();

    // Send success message
    $message = "User status successfully updated to $new_status.";
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $delete_user_id = (int)$_POST['delete_user_id'];
    $delete_stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $delete_stmt->bind_param("i", $delete_user_id);
    $delete_stmt->execute();

    $message = "User successfully deleted.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .main-content { margin-left: 260px; padding: 2rem; }
        .card { margin-bottom: 1rem; }
    </style>
</head>
<body>

<?php include '../includes/sidebar.php'; ?>
<?php include '../includes/navbar.php'; ?>

<div class="main-content container-fluid">
    <h2 class="mb-4">Manage Users</h2>

    <?php if (isset($message)): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Filter Links for Roles -->
    <div class="mb-3">
        <a href="manage-users.php?role=all" class="btn btn-outline-primary btn-sm <?= $roleFilter === 'all' ? 'active' : '' ?>">All</a>
        <a href="manage-users.php?role=student" class="btn btn-outline-warning btn-sm <?= $roleFilter === 'student' ? 'active' : '' ?>">Students</a>
        <a href="manage-users.php?role=employer" class="btn btn-outline-success btn-sm <?= $roleFilter === 'employer' ? 'active' : '' ?>">Employers</a>
        <a href="manage-users.php?role=alumni" class="btn btn-outline-info btn-sm <?= $roleFilter === 'alumni' ? 'active' : '' ?>">Alumni</a>
    </div>

    <!-- User Table -->
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['role']) ?></td>
                        <td>
                            <span class="badge <?= $user['status'] === 'active' ? 'bg-success' : 'bg-danger' ?>">
                                <?= ucfirst($user['status']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($user['created_at']) ?></td>
                        <td>
                            <!-- Activate/Deactivate Button -->
                            <?php if ($user['status'] === 'inactive'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                    <button type="submit" name="action" value="activate" class="btn btn-success btn-sm">Activate</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                    <button type="submit" name="action" value="deactivate" class="btn btn-warning btn-sm">Deactivate</button>
                                </form>
                            <?php endif; ?>

                            <!-- Delete Button -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="delete_user_id" value="<?= $user['user_id'] ?>">
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
