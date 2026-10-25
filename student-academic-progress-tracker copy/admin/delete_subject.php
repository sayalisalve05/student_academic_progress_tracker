<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

// Check subject ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: subjects.php");
    exit;
}

$subject_id = (int) $_GET['id'];

try {

    // Check whether subject exists
    $stmt = $conn->prepare("
        SELECT id
        FROM subjects
        WHERE id = ?
    ");

    $stmt->bind_param("i", $subject_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        header("Location: subjects.php");
        exit;
    }

    // Start transaction
    $conn->begin_transaction();

    /*
     * Delete the subject.
     *
     * Related records in marks, attendance,
     * backlogs, and exams will be handled by
     * the database foreign-key relationships.
     */
    $stmt = $conn->prepare("
        DELETE FROM subjects
        WHERE id = ?
    ");

    $stmt->bind_param("i", $subject_id);
    $stmt->execute();

    // Save changes
    $conn->commit();

    // Return to subjects page
    header("Location: subjects.php?deleted=1");
    exit;

} catch (Exception $e) {

    // Undo changes if an error occurs
    if ($conn->in_transaction) {
        $conn->rollback();
    }

    die("Error deleting subject: " . $e->getMessage());
}

?>