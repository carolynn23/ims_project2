<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch alumni profile
$query = $conn->prepare("SELECT * FROM alumni WHERE user_id = ?");
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();
$alumni = $result->fetch_assoc();

if (!$alumni) {
    echo "Alumni profile not found.";
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $graduation_year = trim($_POST['graduation_year']);
    $current_position = trim($_POST['current_position']);
    $mentorship_offered = isset($_POST['mentorship_offered']) ? 1 : 0;

    $update = $conn->prepare("UPDATE alumni SET full_name = ?, graduation_year = ?, current_position = ?, mentorship_offered = ? WHERE user_id = ?");
    $update->bind_param("sssii", $full_name, $graduation_year, $current_position, $mentorship_offered, $user_id);

    if ($update->execute()) {
        echo "
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const loader = document.createElement('div');
                loader.innerHTML = `
                    <div style='position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(255,255,255,0.8); display:flex; align-items:center; justify-content:center; z-index:9999;'>
                        <div style='font-size:18px; color:green;'>
                            ✅ Profile updated successfully!<br>
                            Redirecting to dashboard...
                        </div>
                    </div>
                `;
                document.body.appendChild(loader);
                setTimeout(() => {
                    window.location.href = 'alumni-dashboard.php';
                }, 5000);
            });
        </script>";
        exit();
    } else {
        echo "Update failed.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Alumni Profile</title>
    <style>
        body { font-family: Arial; background: #f4f4f4; padding: 30px; }
        .container {
            background: white;
            padding: 25px;
            max-width: 600px;
            margin: auto;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        label { font-weight: bold; display: block; margin-top: 15px; }
        input[type="text"], input[type="number"] {
            width: 100%; padding: 10px; margin-top: 5px;
        }
        .submit-btn {
            background: #007bff;
            color: white;
            padding: 10px 15px;
            margin-top: 20px;
            border: none;
            cursor: pointer;
        }
        .submit-btn:hover { background: #0056b3; }
    </style>
</head>
<body>

<div class="container">
    <h2>Edit Alumni Profile</h2>
    <form method="POST">
        <label>Full Name</label>
        <input type="text" name="full_name" value="<?= htmlspecialchars($alumni['full_name']) ?>" required>

        <label>Graduation Year</label>
        <input type="number" name="graduation_year" value="<?= htmlspecialchars($alumni['graduation_year']) ?>" required>

        <label>Current Position</label>
        <input type="text" name="current_position" value="<?= htmlspecialchars($alumni['current_position']) ?>" required>

        <label>
            <input type="checkbox" name="mentorship_offered" <?= $alumni['mentorship_offered'] ? 'checked' : '' ?>>
            Willing to Offer Mentorship
        </label>
        <label>
            <input type="checkbox" name="mentorship_available" value="1" <?= $alumni['mentorship_available'] ? 'checked' : '' ?>>
            Available for mentorship
        </label>


        <button type="submit" class="submit-btn">Update Profile</button>
    </form>
</div>

</body>
</html>
