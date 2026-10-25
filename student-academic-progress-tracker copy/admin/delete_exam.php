<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: exams.php");
    exit;
}

$exam_id = (int)$_GET["id"];

try {

    $stmt = $conn->prepare("
        DELETE FROM exams
        WHERE id = ?
    ");

    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $stmt->close();

    header("Location: exams.php?deleted=1");
    exit;

} catch (Exception $e) {

    die("Error deleting exam: " . $e->getMessage());

}
?>