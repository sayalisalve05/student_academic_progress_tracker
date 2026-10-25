<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: students.php");
    exit;
}

$student_id = (int) $_GET['id'];

try {

    // Get the student's user_id first
    $stmt = $conn->prepare("SELECT user_id FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $student = $result->fetch_assoc();

    if (!$student) {
        header("Location: students.php");
        exit;
    }

    $user_id = (int) $student['user_id'];

    // Start transaction
    $conn->begin_transaction();

    // Delete student record first
    $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();

    // Delete login account
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    // Save changes
    $conn->commit();

    header("Location: students.php?deleted=1");
    exit;

} catch (Exception $e) {

    // Undo changes if something goes wrong
    $conn->rollback();

    die("Error deleting student: " . $e->getMessage());
}
?>