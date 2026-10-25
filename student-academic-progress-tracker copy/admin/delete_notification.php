<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

/* Check notification ID */

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: notifications.php");
    exit;
}

$id = (int) $_GET["id"];

/* Delete notification */

try {

    $stmt = $conn->prepare("
        DELETE FROM notifications
        WHERE id = ?
    ");

    $stmt->bind_param("i", $id);

    $stmt->execute();

    $stmt->close();

    header("Location: notifications.php?deleted=1");
    exit;

} catch (Exception $e) {

    die("Error deleting notification: " . $e->getMessage());

}

?>