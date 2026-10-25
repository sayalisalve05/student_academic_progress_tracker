<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

/* =========================================================
   DASHBOARD DATA
   ========================================================= */

/* ---------- ADMIN NAME ---------- */
$admin_name = $_SESSION["username"] ?? $_SESSION["email"] ?? "Admin";


/* ---------- TOTAL STUDENTS ---------- */
$total_students = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM students
");

if ($result) {
    $row = $result->fetch_assoc();
    $total_students = (int)$row["total"];
}


/* ---------- TOTAL SUBJECTS ---------- */
$total_subjects = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM subjects
");

if ($result) {
    $row = $result->fetch_assoc();
    $total_subjects = (int)$row["total"];
}


/* ---------- PENDING BACKLOGS ---------- */
/*
   A subject is considered failed when the student's
   highest marks percentage for that subject is below 40%.
*/
$pending_backlogs = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM (
        SELECT
            student_id,
            subject_id,
            MAX(
                CASE
                    WHEN max_marks > 0
                    THEN (marks_obtained / max_marks) * 100
                    ELSE 0
                END
            ) AS percentage
        FROM marks
        GROUP BY student_id, subject_id
        HAVING percentage < 40
    ) AS failed_subjects
");

if ($result) {
    $row = $result->fetch_assoc();
    $pending_backlogs = (int)$row["total"];
}


/* ---------- LOW ATTENDANCE ---------- */
/*
   Attendance below 75%.
   Uses the actual database columns:
   total_classes
   attended_classes
*/
$low_attendance = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM (
        SELECT
            student_id,
            subject_id,
            SUM(attended_classes) AS attended,
            SUM(total_classes) AS total
        FROM attendance
        GROUP BY student_id, subject_id
        HAVING total > 0
        AND (attended / total) * 100 < 75
    ) AS low_attendance_records
");

if ($result) {
    $row = $result->fetch_assoc();
    $low_attendance = (int)$row["total"];
}


/* ---------- AVERAGE PERFORMANCE ---------- */
$average_performance = 0;

$result = $conn->query("
    SELECT
        AVG(
            CASE
                WHEN max_marks > 0
                THEN (marks_obtained / max_marks) * 100
                ELSE NULL
            END
        ) AS average_percentage
    FROM marks
");

if ($result) {
    $row = $result->fetch_assoc();

    if ($row["average_percentage"] !== null) {
        $average_performance = round(
            (float)$row["average_percentage"],
            2
        );
    }
}


/* ---------- UPCOMING EXAMS COUNT ---------- */
$upcoming_exam_count = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM exams
    WHERE exam_date >= CURDATE()
");

if ($result) {
    $row = $result->fetch_assoc();
    $upcoming_exam_count = (int)$row["total"];
}


/* =========================================================
   RECENT STUDENTS
   ========================================================= */

$recent_students = [];

$result = $conn->query("
    SELECT
        id,
        prn,
        roll_no,
        full_name,
        department,
        current_semester
    FROM students
    ORDER BY id DESC
    LIMIT 6
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_students[] = $row;
    }
}


/* =========================================================
   UPCOMING EXAMS
   ========================================================= */

$upcoming_exams = [];

$result = $conn->query("
    SELECT
        e.id,
        e.exam_name,
        e.exam_type,
        e.exam_date,
        e.start_time,
        e.end_time,
        e.room_no,
        s.subject_name
    FROM exams e
    LEFT JOIN subjects s
        ON e.subject_id = s.id
    WHERE e.exam_date >= CURDATE()
    ORDER BY
        e.exam_date ASC,
        e.start_time ASC
    LIMIT 6
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $upcoming_exams[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | Academic Tracker</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }

        /* =================================================
           MAIN CONTENT
           ================================================= */

        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: 100vh;
        }


        /* =================================================
           TOP HEADER
           ================================================= */

        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            gap: 20px;
        }

        .welcome h1 {
            margin: 0 0 8px;
            font-size: 30px;
            color: #111827;
        }

        .welcome p {
            margin: 0;
            color: #6b7280;
            font-size: 15px;
        }

        .admin-box {
            display: flex;
            align-items: center;
            gap: 12px;
            background: white;
            padding: 10px 16px;
            border-radius: 14px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.06);
        }

        .admin-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #4f46e5;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 17px;
        }

        .admin-info strong {
            display: block;
            font-size: 14px;
            color: #111827;
        }

        .admin-info span {
            display: block;
            margin-top: 3px;
            color: #6b7280;
            font-size: 12px;
        }


        /* =================================================
           STAT CARDS
           ================================================= */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 18px;
            padding: 22px;
            display: flex;
            align-items: center;
            gap: 18px;
            box-shadow: 0 7px 25px rgba(0,0,0,0.06);
            transition: 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.09);
        }

        .stat-icon {
            width: 58px;
            height: 58px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
            flex-shrink: 0;
        }

        .students-icon {
            background: #e0e7ff;
        }

        .subjects-icon {
            background: #dcfce7;
        }

        .backlogs-icon {
            background: #fee2e2;
        }

        .attendance-icon {
            background: #fef3c7;
        }

        .performance-icon {
            background: #ede9fe;
        }

        .exam-icon {
            background: #dbeafe;
        }

        .stat-info {
            min-width: 0;
        }

        .stat-info h3 {
            margin: 0;
            font-size: 28px;
            color: #111827;
        }

        .stat-info p {
            margin: 5px 0 0;
            color: #6b7280;
            font-size: 14px;
        }


        /* =================================================
           SECTION GRID
           ================================================= */

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }

        .dashboard-card {
            background: white;
            border-radius: 18px;
            box-shadow: 0 7px 25px rgba(0,0,0,0.06);
            overflow: hidden;
        }

        .card-header {
            padding: 20px 22px;
            border-bottom: 1px solid #eef0f4;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .card-header h2 {
            margin: 0;
            font-size: 18px;
            color: #111827;
        }

        .view-link {
            text-decoration: none;
            color: #4f46e5;
            font-size: 13px;
            font-weight: 600;
        }

        .view-link:hover {
            text-decoration: underline;
        }


        /* =================================================
           STUDENT LIST
           ================================================= */

        .student-list {
            padding: 8px 22px 15px;
        }

        .student-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f1f5;
        }

        .student-row:last-child {
            border-bottom: none;
        }

        .student-left {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .student-avatar {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: #eef2ff;
            color: #4f46e5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            flex-shrink: 0;
        }

        .student-details {
            min-width: 0;
        }

        .student-details strong {
            display: block;
            color: #111827;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .student-details span {
            display: block;
            color: #6b7280;
            font-size: 12px;
            margin-top: 4px;
        }

        .semester-badge {
            background: #f3f4f6;
            color: #374151;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 12px;
            white-space: nowrap;
        }


        /* =================================================
           EXAM LIST
           ================================================= */

        .exam-list {
            padding: 8px 22px 15px;
        }

        .exam-row {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f1f5;
        }

        .exam-row:last-child {
            border-bottom: none;
        }

        .exam-date-box {
            width: 52px;
            min-width: 52px;
            height: 58px;
            border-radius: 12px;
            background: #eef2ff;
            color: #4f46e5;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .exam-date-box .day {
            font-size: 19px;
            font-weight: 700;
        }

        .exam-date-box .month {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .exam-details {
            min-width: 0;
        }

        .exam-details strong {
            display: block;
            font-size: 14px;
            color: #111827;
            margin-bottom: 4px;
        }

        .exam-details span {
            display: block;
            font-size: 12px;
            color: #6b7280;
            margin-top: 3px;
        }

        .exam-type {
            margin-left: auto;
            background: #f3f4f6;
            color: #374151;
            padding: 6px 9px;
            border-radius: 8px;
            font-size: 11px;
            white-space: nowrap;
        }


        /* =================================================
           QUICK ACTIONS
           ================================================= */

        .quick-actions {
            background: white;
            border-radius: 18px;
            box-shadow: 0 7px 25px rgba(0,0,0,0.06);
            padding: 22px;
        }

        .quick-actions h2 {
            margin: 0 0 18px;
            font-size: 18px;
            color: #111827;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }

        .action-card {
            text-decoration: none;
            background: #f8fafc;
            border: 1px solid #eef0f4;
            border-radius: 14px;
            padding: 18px;
            text-align: center;
            transition: 0.2s ease;
        }

        .action-card:hover {
            border-color: #c7d2fe;
            background: #eef2ff;
            transform: translateY(-2px);
        }

        .action-icon {
            font-size: 25px;
            margin-bottom: 8px;
        }

        .action-card strong {
            display: block;
            color: #111827;
            font-size: 13px;
        }


        /* =================================================
           EMPTY STATE
           ================================================= */

        .empty-state {
            text-align: center;
            padding: 35px 20px;
            color: #9ca3af;
            font-size: 14px;
        }


        /* =================================================
           RESPONSIVE
           ================================================= */

        @media (max-width: 1100px) {

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .action-grid {
                grid-template-columns: repeat(2, 1fr);
            }

        }


        @media (max-width: 850px) {

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .top-header {
                align-items: flex-start;
            }

        }


        @media (max-width: 600px) {

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .action-grid {
                grid-template-columns: 1fr 1fr;
            }

            .top-header {
                flex-direction: column;
            }

            .welcome h1 {
                font-size: 24px;
            }

        }

    </style>

</head>


<body>

<?php include "includes/sidebar.php"; ?>


<div class="main-content">


    <!-- ================================================
         HEADER
         ================================================ -->

    <div class="top-header">

        <div class="welcome">

            <h1>Welcome back, <?= htmlspecialchars($admin_name) ?> 👋</h1>

            <p>
                Here's what's happening with your academic tracker today.
            </p>

        </div>


        <div class="admin-box">

            <div class="admin-avatar">
                <?= strtoupper(substr($admin_name, 0, 1)) ?>
            </div>

            <div class="admin-info">

                <strong>
                    <?= htmlspecialchars($admin_name) ?>
                </strong>

                <span>
                    Administrator
                </span>

            </div>

        </div>

    </div>



    <!-- ================================================
         STATISTICS
         ================================================ -->

    <div class="stats-grid">


        <!-- TOTAL STUDENTS -->

        <div class="stat-card">

            <div class="stat-icon students-icon">
                👨‍🎓
            </div>

            <div class="stat-info">

                <h3>
                    <?= $total_students ?>
                </h3>

                <p>
                    Total Students
                </p>

            </div>

        </div>



        <!-- TOTAL SUBJECTS -->

        <div class="stat-card">

            <div class="stat-icon subjects-icon">
                📚
            </div>

            <div class="stat-info">

                <h3>
                    <?= $total_subjects ?>
                </h3>

                <p>
                    Total Subjects
                </p>

            </div>

        </div>



        <!-- PENDING BACKLOGS -->

        <div class="stat-card">

            <div class="stat-icon backlogs-icon">
                ⚠️
            </div>

            <div class="stat-info">

                <h3>
                    <?= $pending_backlogs ?>
                </h3>

                <p>
                    Pending Backlogs
                </p>

            </div>

        </div>



        <!-- LOW ATTENDANCE -->

        <div class="stat-card">

            <div class="stat-icon attendance-icon">
                📅
            </div>

            <div class="stat-info">

                <h3>
                    <?= $low_attendance ?>
                </h3>

                <p>
                    Low Attendance
                </p>

            </div>

        </div>



        <!-- AVERAGE PERFORMANCE -->

        <div class="stat-card">

            <div class="stat-icon performance-icon">
                📈
            </div>

            <div class="stat-info">

                <h3>
                    <?= number_format($average_performance, 2) ?>%
                </h3>

                <p>
                    Average Performance
                </p>

            </div>

        </div>



        <!-- UPCOMING EXAMS -->

        <div class="stat-card">

            <div class="stat-icon exam-icon">
                📝
            </div>

            <div class="stat-info">

                <h3>
                    <?= $upcoming_exam_count ?>
                </h3>

                <p>
                    Upcoming Exams
                </p>

            </div>

        </div>

    </div>



    <!-- ================================================
         RECENT STUDENTS + UPCOMING EXAMS
         ================================================ -->

    <div class="dashboard-grid">


        <!-- RECENT STUDENTS -->

        <div class="dashboard-card">

            <div class="card-header">

                <h2>
                    Recent Students
                </h2>

                <a
                    href="students.php"
                    class="view-link"
                >
                    View All
                </a>

            </div>


            <div class="student-list">

                <?php if (count($recent_students) > 0): ?>

                    <?php foreach ($recent_students as $student): ?>

                        <div class="student-row">


                            <div class="student-left">

                                <div class="student-avatar">

                                    <?= strtoupper(
                                        substr(
                                            $student["full_name"],
                                            0,
                                            1
                                        )
                                    ) ?>

                                </div>


                                <div class="student-details">

                                    <strong>
                                        <?= htmlspecialchars(
                                            $student["full_name"]
                                        ) ?>
                                    </strong>

                                    <span>
                                        PRN:
                                        <?= htmlspecialchars(
                                            $student["prn"]
                                        ) ?>
                                    </span>

                                </div>

                            </div>


                            <div class="semester-badge">

                                Sem
                                <?= (int)$student["current_semester"] ?>

                            </div>


                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="empty-state">
                        No students found.
                    </div>

                <?php endif; ?>

            </div>

        </div>



        <!-- UPCOMING EXAMS -->

        <div class="dashboard-card">

            <div class="card-header">

                <h2>
                    Upcoming Exams
                </h2>

                <a
                    href="exams.php"
                    class="view-link"
                >
                    View All
                </a>

            </div>


            <div class="exam-list">

                <?php if (count($upcoming_exams) > 0): ?>

                    <?php foreach ($upcoming_exams as $exam): ?>

                        <?php

                        $timestamp = strtotime(
                            $exam["exam_date"]
                        );

                        $day = date(
                            "d",
                            $timestamp
                        );

                        $month = date(
                            "M",
                            $timestamp
                        );

                        ?>

                        <div class="exam-row">


                            <div class="exam-date-box">

                                <div class="day">
                                    <?= $day ?>
                                </div>

                                <div class="month">
                                    <?= $month ?>
                                </div>

                            </div>


                            <div class="exam-details">

                                <strong>

                                    <?= htmlspecialchars(
                                        $exam["exam_name"]
                                    ) ?>

                                </strong>


                                <span>

                                    <?= htmlspecialchars(
                                        $exam["subject_name"]
                                        ?? "Subject"
                                    ) ?>

                                </span>


                                <span>

                                    <?php

                                    if (!empty($exam["start_time"])) {

                                        echo date(
                                            "h:i A",
                                            strtotime(
                                                $exam["start_time"]
                                            )
                                        );

                                        if (
                                            !empty(
                                                $exam["end_time"]
                                            )
                                        ) {

                                            echo " - ";

                                            echo date(
                                                "h:i A",
                                                strtotime(
                                                    $exam["end_time"]
                                                )
                                            );

                                        }

                                    } else {

                                        echo "Time not set";

                                    }

                                    ?>

                                    <?php if (!empty($exam["room_no"])): ?>

                                        • Room
                                        <?= htmlspecialchars(
                                            $exam["room_no"]
                                        ) ?>

                                    <?php endif; ?>

                                </span>

                            </div>


                            <div class="exam-type">

                                <?= htmlspecialchars(
                                    $exam["exam_type"]
                                ) ?>

                            </div>


                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="empty-state">
                        No upcoming exams.
                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>



    <!-- ================================================
         QUICK ACTIONS
         ================================================ -->

    <div class="quick-actions">

        <h2>
            Quick Actions
        </h2>


        <div class="action-grid">


            <a
                href="add_student.php"
                class="action-card"
            >

                <div class="action-icon">
                    👨‍🎓
                </div>

                <strong>
                    Add Student
                </strong>

            </a>


            <a
                href="add_subject.php"
                class="action-card"
            >

                <div class="action-icon">
                    📚
                </div>

                <strong>
                    Add Subject
                </strong>

            </a>


            <a
                href="add_marks.php"
                class="action-card"
            >

                <div class="action-icon">
                    📊
                </div>

                <strong>
                    Add Marks
                </strong>

            </a>


            <a
                href="add_attendance.php"
                class="action-card"
            >

                <div class="action-icon">
                    📅
                </div>

                <strong>
                    Add Attendance
                </strong>

            </a>


            <a
                href="add_exam.php"
                class="action-card"
            >

                <div class="action-icon">
                    📝
                </div>

                <strong>
                    Add Exam
                </strong>

            </a>


            <a
                href="add_notification.php"
                class="action-card"
            >

                <div class="action-icon">
                    🔔
                </div>

                <strong>
                    Notification
                </strong>

            </a>


            <a
                href="backlogs.php"
                class="action-card"
            >

                <div class="action-icon">
                    ⚠️
                </div>

                <strong>
                    Backlogs
                </strong>

            </a>


            <a
                href="profile.php"
                class="action-card"
            >

                <div class="action-icon">
                    👤
                </div>

                <strong>
                    My Profile
                </strong>

            </a>


        </div>

    </div>


</div>


</body>

</html>