<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get employer data
$stmt = $conn->prepare("
    SELECT e.*, u.email 
    FROM employers e 
    JOIN users u ON e.user_id = u.user_id 
    WHERE e.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$employer = $result->fetch_assoc();

if (!$employer) {
    echo "Employer not found.";
    exit();
}

$message = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $company_name = $_POST['company_name'];
    $industry = $_POST['industry'];
    $company_profile = $_POST['company_profile'];
    $contact_person = $_POST['contact_person'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $website = $_POST['website'];
    $email = $_POST['email'];

    // Update employer table
    $update1 = $conn->prepare("
        UPDATE employers 
        SET company_name = ?, industry = ?, company_profile = ?, contact_person = ?, phone = ?, address = ?, website = ? 
        WHERE user_id = ?
    ");
    $update1->bind_param("sssssssi", $company_name, $industry, $company_profile, $contact_person, $phone, $address, $website, $user_id);

    // Update users table email
    $update2 = $conn->prepare("UPDATE users SET email = ? WHERE user_id = ?");
    $update2->bind_param("si", $email, $user_id);

    if ($update1->execute() && $update2->execute()) {
        $message = "Profile updated successfully!";
        $employer = array_merge($employer, $_POST); // update data for redisplay
    } else {
        $message = "Update failed. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Employer Profile</title>
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
    <h2 class="mb-4">Edit Your Profile</h2>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= $message ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-3">
            <label class="form-label">Company Name</label>
            <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($employer['company_name']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Industry</label>
            <input type="text" name="industry" class="form-control" value="<?= htmlspecialchars($employer['industry']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Company Profile</label>
            <textarea name="company_profile" class="form-control" rows="3" required><?= htmlspecialchars($employer['company_profile']) ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Contact Person</label>
            <input type="text" name="contact_person" class="form-control" value="<?= htmlspecialchars($employer['contact_person']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($employer['phone']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Address</label>
            <textarea name="address" class="form-control" rows="2" required><?= htmlspecialchars($employer['address']) ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Website</label>
            <input type="url" name="website" class="form-control" value="<?= htmlspecialchars($employer['website']) ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Email (Login)</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($employer['email']) ?>" required>
        </div>

        <button type="submit" class="btn btn-primary">Update Profile</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
