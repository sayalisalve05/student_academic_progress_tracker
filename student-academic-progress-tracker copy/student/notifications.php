<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/auth.php";

require_student();

$user_id = (int) $_SESSION["user_id"];

/* =========================
   GET STUDENT
========================= */
$stmt = $conn->prepare("
    SELECT
        id,
        full_name,
        prn,
        roll_no,
        current_semester
    FROM students
    WHERE user_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    die("Student profile not found.");
}

$student_id = (int) $student["id"];
$current_semester = (int) $student["current_semester"];


/* =========================
   GET NOTIFICATIONS
========================= */
$notifications = [];

$stmt = $conn->prepare("
    SELECT
        id,
        title,
        message,
        created_at
    FROM notifications
    ORDER BY created_at DESC, id DESC
");

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

$stmt->close();


/* =========================
   HELPER
========================= */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function formatDateTimeValue($value): string
{
    if (!$value) {
        return "";
    }

    $time = strtotime($value);

    if (!$time) {
        return $value;
    }

    return date("d M Y, h:i A", $time);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Notifications</title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <style>

        body {
            background: #f3f4f6;
        }

        .main {
            margin-left: 260px;
            padding: 30px;
            min-height: 100vh;
            box-sizing: border-box;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .topbar h1 {
            margin: 0;
            color: #111827;
            font-size: 28px;
        }

        .muted {
            color: #6b7280;
            margin-top: 6px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        .btn.primary {
            background: #4f46e5;
            color: white;
        }

        .btn.primary:hover {
            background: #4338ca;
        }

        .student-info {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 18px 22px;
            margin-bottom: 22px;
        }

        .student-name {
            font-size: 18px;
            font-weight: 700;
            color: #111827;
        }

        .student-details {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 9px;
        }

        .student-badge {
            background: #eef2ff;
            color: #4338ca;
            padding: 6px 10px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
        }

        .notification-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 16px;
            transition: 0.2s ease;
        }

        .notification-card:hover {
            border-color: #c7d2fe;
            box-shadow: 0 5px 18px rgba(0,0,0,0.05);
        }

        .notification-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
        }

        .notification-title-area {
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }

        .notification-icon {
            width: 44px;
            height: 44px;
            min-width: 44px;
            border-radius: 12px;
            background: #eef2ff;
            color: #4f46e5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 21px;
        }

        .notification-title {
            margin: 0;
            color: #111827;
            font-size: 17px;
            font-weight: 700;
        }

        .notification-date {
            color: #9ca3af;
            font-size: 12px;
            white-space: nowrap;
        }

        .notification-message {
            margin: 15px 0 0 58px;
            color: #4b5563;
            font-size: 14px;
            line-height: 1.7;
            white-space: pre-line;
        }

        .empty {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            text-align: center;
            padding: 65px 20px;
        }

        .empty-icon {
            font-size: 48px;
            margin-bottom: 12px;
        }

        .empty h2 {
            margin: 0;
            color: #111827;
            font-size: 20px;
        }

        .empty p {
            color: #6b7280;
            margin-top: 8px;
        }

        @media (max-width: 700px) {

            .main {
                margin-left: 220px;
                padding: 20px;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .notification-header {
                flex-direction: column;
                gap: 10px;
            }

            .notification-message {
                margin-left: 0;
            }

        }

    </style>

</head>

<body>

<div class="app">

    <?php include "includes/sidebar.php"; ?>

    <main class="main">

        <!-- HEADER -->
        <div class="topbar">

            <div>

                <h1>Notifications</h1>

                <div class="muted">
                    Stay updated with important academic announcements.
                </div>

            </div>

            <a
                href="dashboard.php"
                class="btn primary"
            >
                Dashboard
            </a>

        </div>


        <!-- STUDENT INFO -->
        <div class="student-info">

            <div class="student-name">
                <?= e($student["full_name"]) ?>
            </div>

            <div class="student-details">

                <span class="student-badge">
                    PRN: <?= e($student["prn"]) ?>
                </span>

                <span class="student-badge">
                    Roll No: <?= e($student["roll_no"]) ?>
                </span>

                <span class="student-badge">
                    Semester <?= e($current_semester) ?>
                </span>

            </div>

        </div>


        <!-- NOTIFICATIONS -->

        <?php if (!empty($notifications)): ?>

            <?php foreach ($notifications as $notification): ?>

                <div class="notification-card">

                    <div class="notification-header">

                        <div class="notification-title-area">

                            <div class="notification-icon">
                                🔔
                            </div>

                            <div>

                                <h2 class="notification-title">
                                    <?= e($notification["title"]) ?>
                                </h2>

                            </div>

                        </div>

                        <div class="notification-date">
                            <?= e(formatDateTimeValue($notification["created_at"])) ?>
                        </div>

                    </div>


                    <div class="notification-message">
                        <?= e($notification["message"]) ?>
                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="empty">

                <div class="empty-icon">
                    🔔
                </div>

                <h2>No Notifications</h2>

                <p>
                    There are currently no announcements or notifications.
                </p>

            </div>

        <?php endif; ?>

    </main>

</div>

</body>
</html>