<?php
session_start();
require_once 'config.php';

function redirect_with($params) {
    $qs = http_build_query($params);
    header("Location: register-student.php?$qs");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register-student.php'); exit();
}

// Account
$username         = trim($_POST['username'] ?? '');
$email            = trim($_POST['email'] ?? '');
$student_number   = trim($_POST['student_number'] ?? '');
$password         = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Profile
$full_name        = trim($_POST['full_name'] ?? '');
$field_of_interest= trim($_POST['field_of_interest'] ?? '');
$department       = trim($_POST['department'] ?? '');
$program          = trim($_POST['program'] ?? '');
$level            = trim($_POST['level'] ?? '');
$skills           = trim($_POST['skills'] ?? '');
$preferences      = trim($_POST['preferences'] ?? '');
$gpa_input        = $_POST['gpa'] ?? '';
$gpa              = ($gpa_input !== '' ? (float)$gpa_input : null);

// Basic validation
if ($username==='' || $email==='' || $student_number==='' || $password==='' || $confirm_password==='' || $full_name==='') {
    redirect_with(['error' => 'Please fill all required fields.']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_with(['error' => 'Invalid email address.']);
}
if ($password !== $confirm_password) {
    redirect_with(['error' => 'Passwords do not match.']);
}
if ($gpa !== null && ($gpa < 0 || $gpa > 4)) {
    redirect_with(['error' => 'GPA must be between 0.00 and 4.00.']);
}

// Uniqueness checks
$ck = $conn->prepare("SELECT 1 FROM users WHERE username=? LIMIT 1");
$ck->bind_param("s", $username);
$ck->execute();
if ($ck->get_result()->fetch_assoc()) redirect_with(['error' => 'Username already taken.']);

$ck = $conn->prepare("SELECT 1 FROM users WHERE email=? LIMIT 1");
$ck->bind_param("s", $email);
$ck->execute();
if ($ck->get_result()->fetch_assoc()) redirect_with(['error' => 'Email already in use.']);

$ck = $conn->prepare("SELECT 1 FROM students WHERE student_number=? LIMIT 1");
$ck->bind_param("s", $student_number);
$ck->execute();
if ($ck->get_result()->fetch_assoc()) redirect_with(['error' => 'Student Number already exists.']);

// Handle resume upload (optional)
$resume_filename = null;
if (!empty($_FILES['resume']['name'])) {
    $allowed = ['pdf','doc','docx'];
    $ext = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext,$allowed)) {
        redirect_with(['error'=>'Resume must be PDF/DOC/DOCX.']);
    }
    if ($_FILES['resume']['size'] > 5*1024*1024) {
        redirect_with(['error'=>'Resume must be ≤ 5MB.']);
    }
    $upload_dir = __DIR__ . "/uploads/resumes/";
    if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
    $safe = preg_replace('/[^A-Za-z0-9._-]/','_', basename($_FILES['resume']['name']));
    $resume_filename = time().'_'. $student_number .'_'. $safe;
    $target = $upload_dir . $resume_filename;
    if (!move_uploaded_file($_FILES['resume']['tmp_name'], $target)) {
        redirect_with(['error' => 'Failed to upload resume.']);
    }
}

$conn->begin_transaction();
try {
    // Insert user
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $u = $conn->prepare("INSERT INTO users (username, password_hash, email, role, status) VALUES (?, ?, ?, 'student', 'active')");
    $u->bind_param("sss", $username, $hash, $email);
    if (!$u->execute()) throw new Exception("User create failed: " . $u->error);
    $user_id = (int)$conn->insert_id;

    // Insert student
    $s = $conn->prepare("
      INSERT INTO students
        (user_id, student_number, full_name, email, department, program, level, field_of_interest, skills, gpa, resume, preferences)
      VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    // types: i + 8 strings + d + 2 strings = "issssssssdss"
    $s->bind_param(
        "issssssssdss",
        $user_id, $student_number, $full_name, $email, $department, $program, $level,
        $field_of_interest, $skills, $gpa, $resume_filename, $preferences
    );
    if (!$s->execute()) throw new Exception("Student save failed: " . $s->error);

    $conn->commit();

    // Auto-login
    $_SESSION['user_id']      = $user_id;
    $_SESSION['email']        = $email;
    $_SESSION['role']         = 'student';
    $_SESSION['display_name'] = $full_name;

    // fetch & store student_id for convenience
    $_SESSION['student_id'] = (int)$conn->insert_id;

    header("Location: student-dashboard.php");
    exit();

} catch (Throwable $e) {
    $conn->rollback();
    $msg = $e->getMessage();
    if (stripos($msg,'Duplicate')!==false) {
        if (stripos($msg,'student_number')!==false) redirect_with(['error'=>'Student Number already exists.']);
        if (stripos($msg,'email')!==false) redirect_with(['error'=>'Email already in use.']);
        if (stripos($msg,'username')!==false) redirect_with(['error'=>'Username already taken.']);
    }
    redirect_with(['error'=>'Registration failed. Please try again.']);
}
