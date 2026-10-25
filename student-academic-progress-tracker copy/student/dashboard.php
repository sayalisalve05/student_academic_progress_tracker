<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_student();

$user_id = (int) $_SESSION["user_id"];

/* =====================================================
   FETCH STUDENT
===================================================== */

$stmt = $conn->prepare("
    SELECT
        s.id,
        s.prn,
        s.roll_no,
        s.full_name,
        s.course,
        s.department,
        s.admission_year,
        s.current_semester,
        s.gender,
        s.birth_date,
        s.phone,
        s.photo,
        u.username,
        u.email
    FROM students s
    INNER JOIN users u ON u.id = s.user_id
    WHERE s.user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$student = $result->fetch_assoc();

$stmt->close();

if (!$student) {
    die("Student profile not found.");
}

$student_id = (int) $student["id"];


/* =====================================================
   TOTAL SUBJECTS
===================================================== */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM subjects
    WHERE semester_id = (
        SELECT id
        FROM semesters
        WHERE semester_number = ?
        LIMIT 1
    )
");

$stmt->bind_param("i", $student["current_semester"]);
$stmt->execute();

$result = $stmt->get_result();
$row = $result->fetch_assoc();

$total_subjects = (int) ($row["total"] ?? 0);

$stmt->close();


/* =====================================================
   AVERAGE MARKS
===================================================== */

$stmt = $conn->prepare("
    SELECT
        SUM(marks_obtained) AS obtained,
        SUM(max_marks) AS maximum
    FROM marks
    WHERE student_id = ?
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$result = $stmt->get_result();
$marks_summary = $result->fetch_assoc();

$stmt->close();

$total_obtained = (float) ($marks_summary["obtained"] ?? 0);
$total_maximum = (float) ($marks_summary["maximum"] ?? 0);

$average_marks = 0;

if ($total_maximum > 0) {
    $average_marks = round(
        ($total_obtained / $total_maximum) * 100,
        2
    );
}


/* =====================================================
   ATTENDANCE
===================================================== */

$stmt = $conn->prepare("
    SELECT
        SUM(total_classes) AS total_classes,
        SUM(attended_classes) AS attended_classes
    FROM attendance
    WHERE student_id = ?
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$result = $stmt->get_result();
$attendance_summary = $result->fetch_assoc();

$stmt->close();

$total_classes = (int) ($attendance_summary["total_classes"] ?? 0);
$attended_classes = (int) ($attendance_summary["attended_classes"] ?? 0);

$attendance_percentage = 0;

if ($total_classes > 0) {
    $attendance_percentage = round(
        ($attended_classes / $total_classes) * 100,
        2
    );
}


/* =====================================================
   BACKLOG COUNT
===================================================== */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM backlogs
    WHERE student_id = ?
    AND status = 'Pending'
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$result = $stmt->get_result();
$row = $result->fetch_assoc();

$pending_backlogs = (int) ($row["total"] ?? 0);

$stmt->close();


/* =====================================================
   CLEARED BACKLOG COUNT
===================================================== */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM backlogs
    WHERE student_id = ?
    AND status = 'Cleared'
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$result = $stmt->get_result();
$row = $result->fetch_assoc();

$cleared_backlogs = (int) ($row["total"] ?? 0);

$stmt->close();


/* =====================================================
   RECENT MARKS
===================================================== */

$recent_marks = [];

$stmt = $conn->prepare("
    SELECT
        m.exam_type,
        m.marks_obtained,
        m.max_marks,
        m.exam_date,
        s.subject_code,
        s.subject_name
    FROM marks m
    INNER JOIN subjects s
        ON s.id = m.subject_id
    WHERE m.student_id = ?
    ORDER BY
        m.exam_date DESC,
        m.id DESC
    LIMIT 6
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $recent_marks[] = $row;
}

$stmt->close();


/* =====================================================
   SUBJECT PERFORMANCE
===================================================== */

$subject_performance = [];

$stmt = $conn->prepare("
    SELECT
        s.subject_code,
        s.subject_name,
        SUM(m.marks_obtained) AS obtained,
        SUM(m.max_marks) AS maximum
    FROM marks m
    INNER JOIN subjects s
        ON s.id = m.subject_id
    WHERE m.student_id = ?
    GROUP BY
        s.id,
        s.subject_code,
        s.subject_name
    ORDER BY s.subject_name ASC
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $percentage = 0;

    if ((float) $row["maximum"] > 0) {
        $percentage = round(
            ((float) $row["obtained"] / (float) $row["maximum"]) * 100,
            2
        );
    }

    $row["percentage"] = $percentage;

    $subject_performance[] = $row;
}

$stmt->close();


/* =====================================================
   UPCOMING EXAMS
===================================================== */

$upcoming_exams = [];

$stmt = $conn->prepare("
    SELECT
        e.exam_name,
        e.exam_type,
        e.exam_date,
        e.start_time,
        e.end_time,
        e.room_no,
        s.subject_code,
        s.subject_name
    FROM exams e
    INNER JOIN subjects s
        ON s.id = e.subject_id
    WHERE e.semester_id = (
        SELECT id
        FROM semesters
        WHERE semester_number = ?
        LIMIT 1
    )
    AND e.exam_date >= CURDATE()
    ORDER BY e.exam_date ASC, e.start_time ASC
    LIMIT 5
");

$stmt->bind_param("i", $student["current_semester"]);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $upcoming_exams[] = $row;
}

$stmt->close();


/* =====================================================
   NOTIFICATIONS
===================================================== */

$notifications = [];

$stmt = $conn->prepare("
    SELECT
        id,
        title,
        message,
        notification_type,
        created_at
    FROM notifications
    WHERE student_id IS NULL
       OR student_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

$stmt->close();


/* =====================================================
   ATTENDANCE STATUS
===================================================== */

if ($attendance_percentage >= 75) {
    $attendance_status = "Good";
    $attendance_class = "good";
} elseif ($attendance_percentage >= 60) {
    $attendance_status = "Warning";
    $attendance_class = "warning";
} else {
    $attendance_status = "Low";
    $attendance_class = "danger";
}


/* =====================================================
   PERFORMANCE STATUS
===================================================== */

if ($average_marks >= 75) {
    $performance_status = "Excellent";
    $performance_class = "excellent";
} elseif ($average_marks >= 60) {
    $performance_status = "Good";
    $performance_class = "good";
} elseif ($average_marks >= 40) {
    $performance_status = "Average";
    $performance_class = "warning";
} else {
    $performance_status = "Needs Improvement";
    $performance_class = "danger";
}


/* =====================================================
   INITIAL
===================================================== */

$name_parts = explode(" ", trim($student["full_name"]));
$initial = strtoupper(substr($name_parts[0], 0, 1));

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Student Dashboard | Academic Tracker</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>

/* =====================================================
   RESET
===================================================== */

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f6fa;
    color: #1f2937;
}



/* =====================================================
   MAIN
===================================================== */

.main-content {
    margin-left: 260px;

    padding: 30px;

    min-height: 100vh;
}


/* =====================================================
   HEADER
===================================================== */

.top-header {
    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 28px;
}


.welcome h1 {
    margin: 0;

    font-size: 28px;

    font-weight: 800;

    color: #1f2937;
}


.welcome p {
    margin: 7px 0 0;

    color: #6b7280;

    font-size: 14px;
}


.student-mini-profile {
    display: flex;

    align-items: center;

    gap: 11px;

    background: white;

    padding: 8px 13px 8px 8px;

    border-radius: 12px;

    box-shadow:
        0 4px 15px
        rgba(0, 0, 0, 0.04);
}


.student-avatar {
    width: 40px;
    height: 40px;

    border-radius: 50%;

    background: linear-gradient(
        135deg,
        #7c3aed,
        #4f46e5
    );

    color: white;

    display: flex;
    align-items: center;
    justify-content: center;

    font-weight: 800;
}


.student-mini-profile strong {
    display: block;

    font-size: 13px;

    color: #1f2937;
}


.student-mini-profile span {
    display: block;

    margin-top: 3px;

    font-size: 11px;

    color: #6b7280;
}


/* =====================================================
   PROFILE STRIP
===================================================== */

.profile-strip {
    background: white;

    border-radius: 16px;

    padding: 18px 22px;

    margin-bottom: 22px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    box-shadow:
        0 5px 18px
        rgba(0, 0, 0, 0.04);
}


.profile-details {
    display: flex;

    align-items: center;

    gap: 16px;
}


.profile-large-avatar {
    width: 58px;
    height: 58px;

    border-radius: 14px;

    background: #ede9fe;

    color: #6d28d9;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 22px;

    font-weight: 800;
}


.profile-details h2 {
    margin: 0;

    font-size: 18px;
}


.profile-details p {
    margin: 5px 0 0;

    color: #6b7280;

    font-size: 12px;
}


.profile-tags {
    display: flex;

    gap: 8px;

    flex-wrap: wrap;
}


.profile-tag {
    padding: 7px 10px;

    background: #f3f4f6;

    color: #4b5563;

    border-radius: 8px;

    font-size: 11px;

    font-weight: 700;
}


/* =====================================================
   STAT CARDS
===================================================== */

.stats-grid {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 22px;
}


.stat-card {
    background: white;

    border-radius: 15px;

    padding: 20px;

    box-shadow:
        0 5px 18px
        rgba(0, 0, 0, 0.04);
}


.stat-top {
    display: flex;

    justify-content: space-between;

    align-items: center;
}


.stat-icon {
    width: 42px;
    height: 42px;

    border-radius: 11px;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 20px;
}


.icon-purple {
    background: #ede9fe;
}

.icon-blue {
    background: #dbeafe;
}

.icon-green {
    background: #dcfce7;
}

.icon-red {
    background: #fee2e2;
}


.stat-label {
    margin-top: 16px;

    color: #6b7280;

    font-size: 12px;

    font-weight: 600;
}


.stat-value {
    margin-top: 5px;

    font-size: 25px;

    font-weight: 800;

    color: #1f2937;
}


.stat-small {
    margin-top: 5px;

    color: #9ca3af;

    font-size: 11px;
}


/* =====================================================
   CONTENT GRID
===================================================== */

.content-grid {
    display: grid;

    grid-template-columns:
        minmax(0, 1.35fr)
        minmax(300px, 0.65fr);

    gap: 22px;

    margin-bottom: 22px;
}


.card {
    background: white;

    border-radius: 16px;

    padding: 22px;

    box-shadow:
        0 5px 18px
        rgba(0, 0, 0, 0.04);
}


.card-header {
    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 18px;
}


.card-header h2 {
    margin: 0;

    font-size: 17px;

    font-weight: 800;
}


.card-header span {
    color: #9ca3af;

    font-size: 11px;
}


/* =====================================================
   CHART
===================================================== */

.chart-container {
    height: 280px;

    position: relative;
}


/* =====================================================
   PERFORMANCE OVERVIEW
===================================================== */

.performance-box {
    display: flex;

    align-items: center;

    gap: 22px;
}


.performance-circle {
    width: 125px;
    height: 125px;

    border-radius: 50%;

    background:
        conic-gradient(
            #7c3aed
            <?php echo min($average_marks, 100); ?>%,
            #eeeef3
            0
        );

    display: flex;

    align-items: center;

    justify-content: center;

    flex-shrink: 0;
}


.performance-circle-inner {
    width: 94px;
    height: 94px;

    border-radius: 50%;

    background: white;

    display: flex;

    align-items: center;

    justify-content: center;

    flex-direction: column;
}


.performance-circle-inner strong {
    font-size: 22px;
}


.performance-circle-inner span {
    color: #9ca3af;

    font-size: 10px;
}


.performance-info h3 {
    margin: 0 0 8px;

    font-size: 18px;
}


.status-badge {
    display: inline-block;

    padding: 6px 10px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 800;
}


.status-badge.excellent,
.status-badge.good {
    background: #dcfce7;

    color: #15803d;
}


.status-badge.warning {
    background: #fef3c7;

    color: #b45309;
}


.status-badge.danger {
    background: #fee2e2;

    color: #b91c1c;
}


.performance-info p {
    margin: 12px 0 0;

    color: #6b7280;

    font-size: 12px;

    line-height: 1.6;
}


/* =====================================================
   RECENT MARKS TABLE
===================================================== */

.table-wrapper {
    overflow-x: auto;
}


.data-table {
    width: 100%;

    border-collapse: collapse;
}


.data-table th {
    padding: 10px;

    text-align: left;

    color: #6b7280;

    font-size: 11px;

    font-weight: 700;

    border-bottom: 1px solid #eef0f4;
}


.data-table td {
    padding: 12px 10px;

    font-size: 12px;

    color: #374151;

    border-bottom: 1px solid #f0f1f4;
}


.data-table tr:last-child td {
    border-bottom: none;
}


.subject-code {
    color: #6d28d9;

    font-weight: 800;

    font-size: 11px;
}


.mark-badge {
    display: inline-block;

    padding: 5px 8px;

    border-radius: 7px;

    background: #ede9fe;

    color: #6d28d9;

    font-weight: 800;

    font-size: 11px;
}


/* =====================================================
   EXAMS
===================================================== */

.exam-item {
    display: flex;

    align-items: center;

    gap: 13px;

    padding: 13px 0;

    border-bottom: 1px solid #eef0f4;
}


.exam-item:last-child {
    border-bottom: none;
}


.exam-date {
    width: 48px;
    height: 48px;

    border-radius: 10px;

    background: #ede9fe;

    color: #6d28d9;

    display: flex;

    align-items: center;

    justify-content: center;

    flex-direction: column;

    flex-shrink: 0;
}


.exam-date strong {
    font-size: 15px;
}


.exam-date span {
    font-size: 9px;

    text-transform: uppercase;
}


.exam-info {
    min-width: 0;
}


.exam-info strong {
    display: block;

    font-size: 12px;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}


.exam-info span {
    display: block;

    margin-top: 4px;

    color: #6b7280;

    font-size: 10px;
}


/* =====================================================
   NOTIFICATIONS
===================================================== */

.notification-item {
    padding: 13px 0;

    border-bottom: 1px solid #eef0f4;
}


.notification-item:last-child {
    border-bottom: none;
}


.notification-top {
    display: flex;

    justify-content: space-between;

    gap: 10px;
}


.notification-title {
    font-size: 12px;

    font-weight: 800;

    color: #1f2937;
}


.notification-type {
    padding: 4px 7px;

    border-radius: 6px;

    background: #ede9fe;

    color: #6d28d9;

    font-size: 9px;

    font-weight: 800;

    white-space: nowrap;
}


.notification-message {
    margin-top: 5px;

    color: #6b7280;

    font-size: 11px;

    line-height: 1.5;
}


.notification-date {
    margin-top: 5px;

    color: #9ca3af;

    font-size: 9px;
}


/* =====================================================
   EMPTY STATE
===================================================== */

.empty-state {
    text-align: center;

    padding: 30px 15px;

    color: #9ca3af;

    font-size: 12px;
}


.empty-icon {
    font-size: 28px;

    margin-bottom: 8px;
}


/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 1100px) {

    .stats-grid {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .profile-strip {
        align-items: flex-start;

        flex-direction: column;

        gap: 15px;
    }

}


@media (max-width: 850px) {

    .sidebar {
        position: relative;

        width: 100%;

        height: auto;
    }

    .main-content {
        margin-left: 0;

        padding: 20px;
    }

    .content-grid {
        grid-template-columns: 1fr;
    }

    .top-header {
        align-items: flex-start;

        flex-direction: column;

        gap: 15px;
    }

}


@media (max-width: 550px) {

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .profile-details {
        align-items: flex-start;

        flex-direction: column;
    }

    .profile-tags {
        width: 100%;
    }

    .performance-box {
        align-items: flex-start;

        flex-direction: column;
    }

}

</style>

</head>


<body>

<?php include "includes/sidebar.php"; ?>


<!-- =====================================================
     MAIN CONTENT
===================================================== -->

<main class="main-content">


    <!-- HEADER -->

    <div class="top-header">

        <div class="welcome">

            <h1>
                Welcome back, <?php
                echo htmlspecialchars(
                    $student["full_name"]
                );
                ?> 👋
            </h1>

            <p>
                Here's your academic performance overview.
            </p>

        </div>


        <div class="student-mini-profile">

            <div class="student-avatar">

                <?php
                echo htmlspecialchars($initial);
                ?>

            </div>

            <div>

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $student["username"]
                    );
                    ?>
                </strong>

                <span>
                    PRN:
                    <?php
                    echo htmlspecialchars(
                        $student["prn"]
                    );
                    ?>
                </span>

            </div>

        </div>

    </div>


    <!-- PROFILE STRIP -->

    <div class="profile-strip">

        <div class="profile-details">

            <div class="profile-large-avatar">

                <?php
                echo htmlspecialchars($initial);
                ?>

            </div>


            <div>

                <h2>
                    <?php
                    echo htmlspecialchars(
                        $student["full_name"]
                    );
                    ?>
                </h2>

                <p>
                    <?php
                    echo htmlspecialchars(
                        $student["course"]
                    );
                    ?>
                    •
                    <?php
                    echo htmlspecialchars(
                        $student["department"]
                    );
                    ?>
                </p>

            </div>

        </div>


        <div class="profile-tags">

            <div class="profile-tag">

                PRN:
                <?php
                echo htmlspecialchars(
                    $student["prn"]
                );
                ?>

            </div>


            <div class="profile-tag">

                Semester
                <?php
                echo htmlspecialchars(
                    $student["current_semester"]
                );
                ?>

            </div>


            <div class="profile-tag">

                Roll No:
                <?php
                echo htmlspecialchars(
                    $student["roll_no"] ?: "N/A"
                );
                ?>

            </div>

        </div>

    </div>


    <!-- STAT CARDS -->

    <div class="stats-grid">


        <!-- AVERAGE -->

        <div class="stat-card">

            <div class="stat-top">

                <div class="stat-label">
                    Average Marks
                </div>

                <div class="stat-icon icon-purple">
                    📊
                </div>

            </div>

            <div class="stat-value">
                <?php
                echo number_format(
                    $average_marks,
                    1
                );
                ?>%
            </div>

            <div class="stat-small">
                Overall performance
            </div>

        </div>


        <!-- ATTENDANCE -->

        <div class="stat-card">

            <div class="stat-top">

                <div class="stat-label">
                    Attendance
                </div>

                <div class="stat-icon icon-blue">
                    📅
                </div>

            </div>

            <div class="stat-value">
                <?php
                echo number_format(
                    $attendance_percentage,
                    1
                );
                ?>%
            </div>

            <div class="stat-small">
                <?php
                echo $attended_classes;
                ?>
                /
                <?php
                echo $total_classes;
                ?>
                classes attended
            </div>

        </div>


        <!-- BACKLOG -->

        <div class="stat-card">

            <div class="stat-top">

                <div class="stat-label">
                    Pending Backlogs
                </div>

                <div class="stat-icon icon-red">
                    ⚠️
                </div>

            </div>

            <div class="stat-value">
                <?php
                echo $pending_backlogs;
                ?>
            </div>

            <div class="stat-small">
                <?php
                echo $cleared_backlogs;
                ?>
                backlog(s) cleared
            </div>

        </div>


        <!-- SUBJECTS -->

        <div class="stat-card">

            <div class="stat-top">

                <div class="stat-label">
                    Subjects
                </div>

                <div class="stat-icon icon-green">
                    📚
                </div>

            </div>

            <div class="stat-value">
                <?php
                echo $total_subjects;
                ?>
            </div>

            <div class="stat-small">
                Current semester
            </div>

        </div>

    </div>


    <!-- CHART + PERFORMANCE -->

    <div class="content-grid">


        <!-- SUBJECT PERFORMANCE CHART -->

        <div class="card">

            <div class="card-header">

                <h2>
                    Subject-wise Performance
                </h2>

                <span>
                    Marks %
                </span>

            </div>


            <div class="chart-container">

                <canvas id="subjectChart"></canvas>

            </div>

        </div>


        <!-- PERFORMANCE OVERVIEW -->

        <div class="card">

            <div class="card-header">

                <h2>
                    Performance Overview
                </h2>

            </div>


            <div class="performance-box">

                <div class="performance-circle">

                    <div class="performance-circle-inner">

                        <strong>
                            <?php
                            echo number_format(
                                $average_marks,
                                0
                            );
                            ?>%
                        </strong>

                        <span>
                            Average
                        </span>

                    </div>

                </div>


                <div class="performance-info">

                    <h3>
                        Academic Performance
                    </h3>

                    <span
                        class="status-badge
                        <?php
                        echo $performance_class;
                        ?>"
                    >

                        <?php
                        echo htmlspecialchars(
                            $performance_status
                        );
                        ?>

                    </span>

                    <p>
                        Your overall academic
                        performance is based on
                        recorded marks.
                    </p>

                </div>

            </div>

        </div>

    </div>


    <!-- RECENT MARKS + EXAMS -->

    <div class="content-grid">


        <!-- RECENT MARKS -->

        <div class="card">

            <div class="card-header">

                <h2>
                    Recent Marks
                </h2>

                <span>
                    Latest records
                </span>

            </div>


            <?php if (count($recent_marks) > 0): ?>

                <div class="table-wrapper">

                    <table class="data-table">

                        <thead>

                            <tr>

                                <th>
                                    Subject
                                </th>

                                <th>
                                    Exam
                                </th>

                                <th>
                                    Marks
                                </th>

                                <th>
                                    Date
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($recent_marks as $mark): ?>

                            <tr>

                                <td>

                                    <span class="subject-code">

                                        <?php
                                        echo htmlspecialchars(
                                            $mark["subject_code"]
                                        );
                                        ?>

                                    </span>

                                    <br>

                                    <?php
                                    echo htmlspecialchars(
                                        $mark["subject_name"]
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $mark["exam_type"]
                                    );
                                    ?>

                                </td>


                                <td>

                                    <span class="mark-badge">

                                        <?php
                                        echo htmlspecialchars(
                                            $mark["marks_obtained"]
                                        );
                                        ?>

                                        /

                                        <?php
                                        echo htmlspecialchars(
                                            $mark["max_marks"]
                                        );
                                        ?>

                                    </span>

                                </td>


                                <td>

                                    <?php

                                    if (!empty($mark["exam_date"])) {

                                        echo date(
                                            "d M Y",
                                            strtotime(
                                                $mark["exam_date"]
                                            )
                                        );

                                    } else {

                                        echo "—";

                                    }

                                    ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">

                    <div class="empty-icon">
                        📊
                    </div>

                    No marks have been recorded yet.

                </div>

            <?php endif; ?>

        </div>


        <!-- UPCOMING EXAMS -->

        <div class="card">

            <div class="card-header">

                <h2>
                    Upcoming Exams
                </h2>

                <span>
                    Next exams
                </span>

            </div>


            <?php if (count($upcoming_exams) > 0): ?>

                <?php foreach ($upcoming_exams as $exam): ?>

                    <div class="exam-item">

                        <div class="exam-date">

                            <strong>

                                <?php
                                echo date(
                                    "d",
                                    strtotime(
                                        $exam["exam_date"]
                                    )
                                );
                                ?>

                            </strong>

                            <span>

                                <?php
                                echo date(
                                    "M",
                                    strtotime(
                                        $exam["exam_date"]
                                    )
                                );
                                ?>

                            </span>

                        </div>


                        <div class="exam-info">

                            <strong>

                                <?php
                                echo htmlspecialchars(
                                    $exam["subject_name"]
                                );
                                ?>

                            </strong>

                            <span>

                                <?php
                                echo htmlspecialchars(
                                    $exam["exam_type"]
                                );
                                ?>

                                •

                                <?php
                                echo htmlspecialchars(
                                    $exam["exam_name"]
                                );
                                ?>

                            </span>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty-state">

                    <div class="empty-icon">
                        📝
                    </div>

                    No upcoming exams.

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- NOTIFICATIONS -->

    <div class="card">

        <div class="card-header">

            <h2>
                Recent Notifications
            </h2>

            <span>
                Latest updates
            </span>

        </div>


        <?php if (count($notifications) > 0): ?>

            <?php foreach ($notifications as $notification): ?>

                <div class="notification-item">

                    <div class="notification-top">

                        <div class="notification-title">

                            <?php
                            echo htmlspecialchars(
                                $notification["title"]
                            );
                            ?>

                        </div>


                        <div class="notification-type">

                            <?php
                            echo htmlspecialchars(
                                $notification["notification_type"]
                            );
                            ?>

                        </div>

                    </div>


                    <div class="notification-message">

                        <?php
                        echo htmlspecialchars(
                            $notification["message"]
                        );
                        ?>

                    </div>


                    <div class="notification-date">

                        <?php
                        echo date(
                            "d M Y, h:i A",
                            strtotime(
                                $notification["created_at"]
                            )
                        );
                        ?>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="empty-state">

                <div class="empty-icon">
                    🔔
                </div>

                No notifications available.

            </div>

        <?php endif; ?>

    </div>

</main>


<!-- =====================================================
     CHART
===================================================== -->

<script>

const subjectLabels = <?php

$labels = [];

foreach ($subject_performance as $subject) {

    $labels[] = $subject["subject_name"];

}

echo json_encode($labels);

?>;


const subjectValues = <?php

$values = [];

foreach ($subject_performance as $subject) {

    $values[] = (float) $subject["percentage"];

}

echo json_encode($values);

?>;


const chartElement =
    document.getElementById("subjectChart");


if (chartElement) {

    new Chart(
        chartElement,
        {

            type: "bar",

            data: {

                labels: subjectLabels,

                datasets: [

                    {

                        label: "Performance %",

                        data: subjectValues,

                        backgroundColor:
                            "rgba(124, 58, 237, 0.75)",

                        borderColor:
                            "rgba(124, 58, 237, 1)",

                        borderWidth: 1,

                        borderRadius: 7

                    }

                ]

            },


            options: {

                responsive: true,

                maintainAspectRatio: false,

                scales: {

                    y: {

                        beginAtZero: true,

                        max: 100,

                        ticks: {

                            callback: function(value) {

                                return value + "%";

                            }

                        }

                    }

                },

                plugins: {

                    legend: {

                        display: false

                    }

                }

            }

        }
    );

}

</script>


</body>

</html>