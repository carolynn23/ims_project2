<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') { header('Location: login.php'); exit(); }

$app_id = (int)($_POST['application_id'] ?? 0);
if ($app_id <= 0) { header('Location: applications.php'); exit(); }

// find student_id
$st = $conn->prepare("SELECT student_id FROM students WHERE user_id=?");
$st->bind_param("i", $_SESSION['user_id']);
$st->execute();
$student_id = (int)($st->get_result()->fetch_assoc()['student_id'] ?? 0);

// only allow withdrawing your own PENDING applications
$ck = $conn->prepare("SELECT status FROM applications WHERE application_id=? AND student_id=?");
$ck->bind_param("ii", $app_id, $student_id);
$ck->execute();
$row = $ck->get_result()->fetch_assoc();
if (!$row) { header('Location: applications.php'); exit(); }

if ($row['status'] === 'pending') {
  // mark as withdrawn
  $upd = $conn->prepare("UPDATE applications SET status='withdrawn' WHERE application_id=? AND student_id=?");
  $upd->bind_param("ii", $app_id, $student_id);
  $upd->execute();

  /* If you didn't alter ENUM, you can delete instead:
  $del = $conn->prepare("DELETE FROM applications WHERE application_id=? AND student_id=? AND status='pending'");
  $del->bind_param("ii", $app_id, $student_id);
  $del->execute();
  */
}

header('Location: applications.php');
exit();
