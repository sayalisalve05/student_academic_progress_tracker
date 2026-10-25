<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: attendance.php");
    exit;
}

$attendance_id = (int) $_GET['id'];

try {

    // Check whether attendance record exists
    $stmt = $conn->prepare("SELECT id FROM attendance WHERE id = ?");
    $stmt->bind_param("i", $attendance_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if (!$result->fetch_assoc()) {
        header("Location: attendance.php");
        exit;
    }

    $stmt->close();

    // Delete attendance record
    $stmt = $conn->prepare("DELETE FROM attendance WHERE id = ?");
    $stmt->bind_param("i", $attendance_id);
    $stmt->execute();

    $stmt->close();

    header("Location: attendance.php?deleted=1");
    exit;

} catch (Exception $e) {

    die("Error deleting attendance: " . $e->getMessage());

}

?>