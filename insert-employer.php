<?php
session_start();
require_once 'config.php'; // Ensure this file connects to your DB using $conn



if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and sanitize form data
    $company_name = trim($_POST['company_name']);
    $industry = trim($_POST['industry']);
    $company_profile = trim($_POST['company_profile']);
    $contact_person = trim($_POST['contact_person']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $website = trim($_POST['website']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert into users table
    $insertUser = $conn->prepare("INSERT INTO users (username, email, password_hash, role, status) VALUES (?, ?, ?, 'employer', 'active')");
    $insertUser->bind_param("sss", $company_name, $email, $password_hash);

    if ($insertUser->execute()) {
        $user_id = $insertUser->insert_id;

        // Insert into employers table
        $insertEmployer = $conn->prepare("INSERT INTO employers (user_id, company_name, industry, company_profile, contact_person, phone, address, website) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $insertEmployer->bind_param("isssssss", $user_id, $company_name, $industry, $company_profile, $contact_person, $phone, $address, $website);

        if ($insertEmployer->execute()) {
            // Success - redirect to login or dashboard
            $_SESSION['success'] = "Employer registered successfully!";
            header("Location: employer-dashboard.php");
            exit();
        } else {
            echo "Error inserting employer details: " . $insertEmployer->error;
        }

        $insertEmployer->close();
    } else {
        echo "Error inserting user: " . $insertUser->error;
    }

    $insertUser->close();
    $conn->close();
} else {
    echo "Invalid request.";
}
?>

