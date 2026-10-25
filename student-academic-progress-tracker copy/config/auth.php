<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login(): void {
    if (!isset($_SESSION["user_id"], $_SESSION["role"])) {
        header("Location: ../login.php");
        exit;
    }
}

function require_admin(): void {
    require_login();
    if ($_SESSION["role"] !== "admin") {
        header("Location: ../student/dashboard.php");
        exit;
    }
}

function require_student(): void {
    require_login();
    if ($_SESSION["role"] !== "student") {
        header("Location: ../admin/dashboard.php");
        exit;
    }
}
?>