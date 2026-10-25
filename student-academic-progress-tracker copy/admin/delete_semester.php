<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: semesters.php");
    exit;
}

$semester_id = (int)$_GET["id"];

try {

    // Check semester exists
    $stmt = $conn->prepare(
        "SELECT id FROM semesters WHERE id = ?"
    );

    $stmt->bind_param("i", $semester_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        header("Location: semesters.php");
        exit;
    }

    /*
     * Delete semester.
     *
     * subjects.semester_id has ON DELETE CASCADE,
     * so subjects belonging to this semester may also be deleted.
     */

    $stmt = $conn->prepare(
        "DELETE FROM semesters WHERE id = ?"
    );

    $stmt->bind_param("i", $semester_id);
    $stmt->execute();

    header("Location: semesters.php?deleted=1");
    exit;

} catch (Exception $e) {

    die("Error deleting semester: " . $e->getMessage());

}
?>