<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_student();

$user_id = (int) $_SESSION["user_id"];

/* Get logged-in student */
$stmt = $conn->prepare("
    SELECT
        s.id,
        s.full_name,
        s.prn,
        s.roll_no,
        s.course,
        s.department,
        s.current_semester
    FROM students s
    WHERE s.user_id = ?
    LIMIT 1
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


/*
 * Get upcoming exams.
 *
 * Exams are connected to subjects and semesters,
 * so we show the subject and semester information.
 */
$stmt = $conn->prepare("
    SELECT
        e.id,
        e.exam_name,
        e.exam_type,
        e.exam_date,
        e.start_time,
        e.end_time,
        e.room_no,
        e.description,
        sub.subject_code,
        sub.subject_name,
        sem.semester_number
    FROM exams e
    INNER JOIN subjects sub
        ON e.subject_id = sub.id
    LEFT JOIN semesters sem
        ON e.semester_id = sem.id
    WHERE e.exam_date >= CURDATE()
      AND e.semester_id = (
          SELECT semester_id
          FROM subjects
          WHERE id = sub.id
          LIMIT 1
      )
    ORDER BY
        e.exam_date ASC,
        e.start_time ASC,
        e.id ASC
");

$stmt->execute();

$exam_result = $stmt->get_result();

$exam_records = [];

while ($row = $exam_result->fetch_assoc()) {
    $exam_records[] = $row;
}

$stmt->close();


/* Helper functions */

function e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function formatDateValue($date): string
{
    if (empty($date)) {
        return "-";
    }

    $time = strtotime($date);

    if (!$time) {
        return e($date);
    }

    return date("d M Y", $time);
}


function formatTimeValue($time): string
{
    if (empty($time)) {
        return "-";
    }

    $timestamp = strtotime($time);

    if (!$timestamp) {
        return e($time);
    }

    return date("h:i A", $timestamp);
}


function examTypeClass($type): string
{
    $type = strtolower(trim((string) $type));

    if (strpos($type, "external") !== false) {
        return "external";
    }

    if (strpos($type, "internal") !== false) {
        return "internal";
    }

    if (strpos($type, "practical") !== false) {
        return "practical";
    }

    return "general";
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

    <title>Upcoming Exams | Academic Tracker</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f3f4f6;
            color: #111827;
        }


        /* ================= MAIN ================= */

        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: 100vh;
        }


        /* ================= HEADER ================= */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .page-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
        }

        .page-header p {
            margin: 7px 0 0;
            color: #6b7280;
            font-size: 14px;
        }

        .student-info {
            background: white;
            padding: 10px 16px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            color: #374151;
            font-size: 13px;
            font-weight: 600;
        }


        /* ================= STATS ================= */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 14px;
            padding: 22px;
            border: 1px solid #e5e7eb;
            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, 0.04);
        }

        .stat-title {
            color: #6b7280;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 29px;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .stat-subtitle {
            color: #9ca3af;
            font-size: 12px;
        }

        .purple {
            color: #4f46e5;
        }

        .blue {
            color: #2563eb;
        }

        .orange {
            color: #ea580c;
        }


        /* ================= CARD ================= */

        .card {
            background: white;
            border-radius: 14px;
            border: 1px solid #e5e7eb;
            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        .card-header {
            padding: 20px 22px;
            border-bottom: 1px solid #e5e7eb;
        }

        .card-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
        }

        .card-header p {
            margin: 6px 0 0;
            color: #6b7280;
            font-size: 13px;
        }


        /* ================= EXAM LIST ================= */

        .exam-list {
            padding: 18px;
        }

        .exam-item {
            display: grid;

            grid-template-columns:
                90px
                minmax(220px, 1fr)
                180px
                180px;

            gap: 18px;
            align-items: center;

            padding: 18px;
            margin-bottom: 12px;

            border: 1px solid #e5e7eb;
            border-radius: 12px;

            transition: 0.2s;
            background: #ffffff;
        }

        .exam-item:last-child {
            margin-bottom: 0;
        }

        .exam-item:hover {
            border-color: #c7d2fe;

            box-shadow:
                0 4px 12px
                rgba(79, 70, 229, 0.08);
        }


        /* ================= DATE ================= */

        .exam-date {
            text-align: center;
            background: #eef2ff;
            border-radius: 11px;
            padding: 12px 8px;
            color: #4338ca;
        }

        .exam-day {
            font-size: 25px;
            font-weight: 800;
            line-height: 1;
        }

        .exam-month {
            margin-top: 5px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .exam-year {
            margin-top: 3px;
            font-size: 10px;
            color: #6366f1;
        }


        /* ================= EXAM INFO ================= */

        .exam-name {
            font-size: 16px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 7px;
        }

        .subject-code {
            color: #4f46e5;
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 3px;
        }

        .subject-name {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 9px;
        }

        .semester-badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 7px;
            background: #f3f4f6;
            color: #374151;
            font-size: 10px;
            font-weight: 800;
        }


        /* ================= TYPE ================= */

        .exam-type {
            display: inline-block;
            padding: 7px 11px;
            border-radius: 7px;
            font-size: 11px;
            font-weight: 800;
            margin-bottom: 9px;
        }

        .exam-type.external {
            background: #fee2e2;
            color: #b91c1c;
        }

        .exam-type.internal {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .exam-type.practical {
            background: #dcfce7;
            color: #15803d;
        }

        .exam-type.general {
            background: #f3e8ff;
            color: #7e22ce;
        }

        .time {
            color: #374151;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.7;
        }


        /* ================= ROOM ================= */

        .room-info {
            color: #374151;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 7px;
        }

        .room-icon {
            margin-right: 5px;
        }

        .description {
            color: #6b7280;
            font-size: 12px;
            line-height: 1.5;
            max-width: 250px;
        }


        /* ================= EMPTY ================= */

        .empty-state {
            padding: 65px 20px;
            text-align: center;
        }

        .empty-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 15px;
            border-radius: 18px;
            background: #eef2ff;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 30px;
        }

        .empty-state h3 {
            margin: 0 0 8px;
            font-size: 18px;
        }

        .empty-state p {
            margin: 0;
            color: #6b7280;
            font-size: 13px;
        }


        /* ================= NOTE ================= */

        .note {
            margin-top: 20px;
            background: #eef2ff;
            border: 1px solid #c7d2fe;
            color: #3730a3;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 12px;
            line-height: 1.6;
        }


        /* ================= RESPONSIVE ================= */

        @media (max-width: 1100px) {

            .exam-item {
                grid-template-columns:
                    80px
                    1fr
                    150px;
            }

            .room-column {
                grid-column: 2 / 4;
            }
        }


        @media (max-width: 850px) {

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .exam-item {
                grid-template-columns:
                    75px
                    1fr;
            }

            .exam-type-column,
            .room-column {
                grid-column: 2;
            }
        }


        @media (max-width: 550px) {

            .main-content {
                padding: 15px;
            }

            .page-header h1 {
                font-size: 23px;
            }

            .exam-item {
                grid-template-columns: 1fr;
            }

            .exam-type-column,
            .room-column {
                grid-column: auto;
            }

            .exam-date {
                max-width: 90px;
            }
        }

    </style>

</head>


<body>

<?php include "includes/sidebar.php"; ?>


<main class="main-content">


    <div class="page-header">

        <div>

            <h1>
                Upcoming Exams
            </h1>

            <p>
                Keep track of your upcoming examinations.
            </p>

        </div>


        <div class="student-info">

            <?php
            echo e($student["full_name"]);
            ?>

            &nbsp;•&nbsp;

            <?php
            echo e($student["prn"]);
            ?>

        </div>

    </div>


    <!-- ================= STATS ================= -->

    <div class="stats-grid">


        <div class="stat-card">

            <div class="stat-title">
                Upcoming Exams
            </div>

            <div class="stat-value purple">

                <?php
                echo count($exam_records);
                ?>

            </div>

            <div class="stat-subtitle">
                Scheduled examinations
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                Current Semester
            </div>

            <div class="stat-value blue">

                <?php
                echo e(
                    $student["current_semester"]
                );
                ?>

            </div>

            <div class="stat-subtitle">
                Your current semester
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                Student
            </div>

            <div class="stat-value orange">

                <?php
                echo e(
                    $student["roll_no"]
                    ?: "-"
                );
                ?>

            </div>

            <div class="stat-subtitle">
                Roll number
            </div>

        </div>


    </div>


    <!-- ================= EXAMS ================= -->

    <div class="card">


        <div class="card-header">

            <h2>
                Exam Schedule
            </h2>

            <p>
                Upcoming exams entered by Teacher/Admin.
            </p>

        </div>


        <?php if (count($exam_records) > 0): ?>


            <div class="exam-list">


                <?php foreach ($exam_records as $exam): ?>


                    <?php

                    $date_timestamp =
                        strtotime(
                            $exam["exam_date"]
                        );

                    $day =
                        $date_timestamp
                        ? date(
                            "d",
                            $date_timestamp
                        )
                        : "-";

                    $month =
                        $date_timestamp
                        ? date(
                            "M",
                            $date_timestamp
                        )
                        : "-";

                    $year =
                        $date_timestamp
                        ? date(
                            "Y",
                            $date_timestamp
                        )
                        : "-";

                    $type_class =
                        examTypeClass(
                            $exam["exam_type"]
                        );

                    ?>


                    <div class="exam-item">


                        <!-- DATE -->

                        <div class="exam-date">

                            <div class="exam-day">
                                <?php echo e($day); ?>
                            </div>

                            <div class="exam-month">
                                <?php echo e($month); ?>
                            </div>

                            <div class="exam-year">
                                <?php echo e($year); ?>
                            </div>

                        </div>


                        <!-- SUBJECT -->

                        <div>

                            <div class="exam-name">

                                <?php
                                echo e(
                                    $exam["exam_name"]
                                );
                                ?>

                            </div>


                            <div class="subject-code">

                                <?php
                                echo e(
                                    $exam["subject_code"]
                                );
                                ?>

                            </div>


                            <div class="subject-name">

                                <?php
                                echo e(
                                    $exam["subject_name"]
                                );
                                ?>

                            </div>


                            <?php
                            if (
                                !empty(
                                    $exam["semester_number"]
                                )
                            ):
                            ?>

                                <span class="semester-badge">

                                    Semester

                                    <?php
                                    echo e(
                                        $exam["semester_number"]
                                    );
                                    ?>

                                </span>

                            <?php endif; ?>


                        </div>


                        <!-- TYPE + TIME -->

                        <div class="exam-type-column">


                            <span
                                class="exam-type <?php
                                echo e($type_class);
                                ?>"
                            >

                                <?php
                                echo e(
                                    $exam["exam_type"]
                                );
                                ?>

                            </span>


                            <div class="time">

                                🕐

                                <?php
                                echo formatTimeValue(
                                    $exam["start_time"]
                                );
                                ?>

                                -

                                <?php
                                echo formatTimeValue(
                                    $exam["end_time"]
                                );
                                ?>

                            </div>


                        </div>


                        <!-- ROOM / DESCRIPTION -->

                        <div class="room-column">


                            <div class="room-info">

                                <span class="room-icon">
                                    🏫
                                </span>

                                <?php

                                echo !empty(
                                    $exam["room_no"]
                                )
                                    ? "Room " .
                                      e(
                                          $exam["room_no"]
                                      )
                                    : "Room not specified";

                                ?>

                            </div>


                            <div class="description">

                                <?php

                                echo !empty(
                                    $exam["description"]
                                )
                                    ? e(
                                        $exam["description"]
                                    )
                                    : "No additional information.";

                                ?>

                            </div>


                        </div>


                    </div>


                <?php endforeach; ?>


            </div>


        <?php else: ?>


            <div class="empty-state">


                <div class="empty-icon">
                    📝
                </div>


                <h3>
                    No upcoming exams
                </h3>


                <p>
                    There are currently no upcoming
                    examinations scheduled for you.
                </p>


            </div>


        <?php endif; ?>


    </div>


    <!-- ================= NOTE ================= -->

    <div class="note">

        <strong>
            Note:
        </strong>

        Exam schedules are managed by the Teacher/Admin.
        Please check this page regularly for the latest
        examination dates, timings and room information.

    </div>


</main>


</body>

</html>