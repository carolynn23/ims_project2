<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
  header('Location: login.php'); exit();
}

$internship_id = (int)($_POST['internship_id'] ?? 0);
if ($internship_id <= 0) { header('Location: student-dashboard.php'); exit(); }

// find student_id
$st = $conn->prepare("SELECT student_id FROM students WHERE user_id=? LIMIT 1");
$st->bind_param("i", $_SESSION['user_id']);
$st->execute();
$student_id = (int)($st->get_result()->fetch_assoc()['student_id'] ?? 0);
if ($student_id <= 0) { header('Location: student-dashboard.php'); exit(); }

// check if saved
$ck = $conn->prepare("SELECT id FROM saved_internships WHERE student_id=? AND internship_id=?");
$ck->bind_param("ii", $student_id, $internship_id);
$ck->execute();
$exists = $ck->get_result()->fetch_assoc();

if ($exists) {
  $del = $conn->prepare("DELETE FROM saved_internships WHERE student_id=? AND internship_id=?");
  $del->bind_param("ii", $student_id, $internship_id);
  $del->execute();
} else {
  $ins = $conn->prepare("INSERT INTO saved_internships (student_id, internship_id) VALUES (?,?)");
  $ins->bind_param("ii", $student_id, $internship_id);
  $ins->execute();
}

$back = $_POST['back'] ?? 'student-dashboard.php';
header("Location: " . $back);
exit();
