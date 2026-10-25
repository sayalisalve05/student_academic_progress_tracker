
<?php
session_start();

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: notes.php");
    exit;
}

if (!isset($_POST["note_id"]) || !is_numeric($_POST["note_id"])) {
    header("Location: notes.php?error=invalid");
    exit;
}

$note_id = (int) $_POST["note_id"];

// Delete the selected note
$stmt = $conn->prepare("DELETE FROM notes WHERE id = ?");
$stmt->bind_param("i", $note_id);

if ($stmt->execute()) {
    header("Location: notes.php?success=deleted");
} else {
    header("Location: notes.php?error=delete_failed");
}

$stmt->close();
$conn->close();
exit;
?>