<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: marks.php");
    exit;
}

$mark_id = (int)$_GET["id"];

try {

    // Check if mark record exists
    $stmt = $conn->prepare(
        "SELECT id FROM marks WHERE id = ?"
    );

    $stmt->bind_param("i", $mark_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        header("Location: marks.php");
        exit;
    }

    // Delete mark
    $stmt = $conn->prepare(
        "DELETE FROM marks WHERE id = ?"
    );

    $stmt->bind_param("i", $mark_id);
    $stmt->execute();

    header("Location: marks.php?deleted=1");
    exit;

} catch (Exception $e) {

    die("Error deleting marks: " . $e->getMessage());

}
?>