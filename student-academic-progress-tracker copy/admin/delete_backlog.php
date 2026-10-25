<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: backlogs.php");
    exit;
}

$backlog_id = (int)$_GET['id'];

try {

    $stmt = $conn->prepare("
        DELETE FROM backlogs
        WHERE id = ?
    ");

    $stmt->bind_param("i", $backlog_id);
    $stmt->execute();

    $stmt->close();

    header("Location: backlogs.php?deleted=1");
    exit;

} catch (Exception $e) {

    die("Error deleting backlog: " . $e->getMessage());

}

?>