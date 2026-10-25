<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_student();

$user_id = $_SESSION["user_id"];

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

$student_id = (int)$student["id"];


/* Get backlog records */
$stmt = $conn->prepare("
    SELECT
        b.id,
        b.status,
        b.attempts,
        b.remark,
        b.cleared_date,
        sub.subject_code,
        sub.subject_name,
        sem.semester_number
    FROM backlogs b
    INNER JOIN subjects sub
        ON b.subject_id = sub.id
    LEFT JOIN semesters sem
        ON sub.semester_id = sem.id
    WHERE b.student_id = ?
    ORDER BY
        sem.semester_number ASC,
        sub.subject_code ASC,
        b.id DESC
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$backlog_result = $stmt->get_result();

$backlog_records = [];

$pending_count = 0;
$cleared_count = 0;

while ($row = $backlog_result->fetch_assoc()) {

    $backlog_records[] = $row;

    if ($row["status"] === "Pending") {
        $pending_count++;
    }

    if ($row["status"] === "Cleared") {
        $cleared_count++;
    }
}

$stmt->close();

$total_backlogs = count($backlog_records);


/* Helper functions */

function e($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function statusClass($status): string
{
    return $status === "Cleared"
        ? "cleared"
        : "pending";
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

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Backlogs | Academic Tracker</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
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


        /* ================= STAT CARDS ================= */

        .stats-grid {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

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


        .red {
            color: #dc2626;
        }


        .green {
            color: #16a34a;
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


        /* ================= TABLE ================= */

        .table-wrapper {
            width: 100%;

            overflow-x: auto;
        }


        table {
            width: 100%;

            border-collapse: collapse;

            min-width: 850px;
        }


        th {
            background: #f9fafb;

            color: #6b7280;

            font-size: 12px;

            text-transform: uppercase;

            letter-spacing: 0.5px;

            font-weight: 800;

            padding: 14px 16px;

            text-align: left;

            border-bottom: 1px solid #e5e7eb;
        }


        td {
            padding: 15px 16px;

            border-bottom: 1px solid #f1f5f9;

            font-size: 13px;

            color: #374151;

            vertical-align: middle;
        }


        tr:hover td {
            background: #fafafa;
        }


        .semester-badge {
            display: inline-block;

            padding: 5px 9px;

            border-radius: 7px;

            background: #eef2ff;

            color: #4338ca;

            font-size: 11px;

            font-weight: 800;
        }


        .subject-code {
            font-weight: 800;

            color: #4f46e5;

            margin-bottom: 4px;
        }


        .subject-name {
            color: #374151;

            font-weight: 600;
        }


        .status-badge {
            display: inline-block;

            padding: 6px 11px;

            border-radius: 7px;

            font-size: 11px;

            font-weight: 800;
        }


        .status-badge.pending {
            background: #fee2e2;

            color: #b91c1c;
        }


        .status-badge.cleared {
            background: #dcfce7;

            color: #15803d;
        }


        .attempts {
            font-weight: 800;

            color: #374151;
        }


        .remark {
            max-width: 220px;

            color: #6b7280;
        }


        .date {
            color: #6b7280;

            white-space: nowrap;
        }


        /* ================= EMPTY ================= */

        .empty-state {
            padding: 60px 20px;

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


            .stats-grid {
                grid-template-columns: 1fr;
            }


            .page-header {
                flex-direction: column;

                align-items: flex-start;
            }
        }


        @media (max-width: 550px) {

            .main-content {
                padding: 15px;
            }


            .page-header h1 {
                font-size: 23px;
            }


            .brand {
                padding: 18px;
            }
        }

    </style>

</head>


<body>


<!-- ================= SIDEBAR ================= -->

     


       <?php include "includes/sidebar.php"; ?>

<!-- ================= MAIN CONTENT ================= -->

<main class="main-content">


    <div class="page-header">

        <div>

            <h1>
                My Backlogs
            </h1>

            <p>
                View your pending and cleared backlog subjects.
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


    <!-- ================= STAT CARDS ================= -->

    <div class="stats-grid">


        <div class="stat-card">

            <div class="stat-title">
                Total Backlogs
            </div>

            <div class="stat-value purple">

                <?php
                echo $total_backlogs;
                ?>

            </div>

            <div class="stat-subtitle">
                Total backlog records
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                Pending Backlogs
            </div>

            <div class="stat-value red">

                <?php
                echo $pending_count;
                ?>

            </div>

            <div class="stat-subtitle">
                Subjects that need to be cleared
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                Cleared Backlogs
            </div>

            <div class="stat-value green">

                <?php
                echo $cleared_count;
                ?>

            </div>

            <div class="stat-subtitle">
                Successfully cleared records
            </div>

        </div>

    </div>


    <!-- ================= TABLE ================= -->

    <div class="card">


        <div class="card-header">

            <h2>
                Backlog Records
            </h2>

            <p>
                Details of subjects with backlog records.
            </p>

        </div>


        <?php if ($total_backlogs > 0): ?>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Semester
                            </th>

                            <th>
                                Subject
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Attempts
                            </th>

                            <th>
                                Remark
                            </th>

                            <th>
                                Cleared Date
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach ($backlog_records as $row): ?>


                        <?php
                        $status_class =
                            statusClass(
                                $row["status"]
                            );
                        ?>


                        <tr>


                            <td>

                                <?php
                                if (
                                    !empty(
                                        $row["semester_number"]
                                    )
                                ):
                                ?>

                                    <span
                                        class="semester-badge"
                                    >

                                        Semester
                                        <?php
                                        echo e(
                                            $row["semester_number"]
                                        );
                                        ?>

                                    </span>

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>


                            <td>

                                <div
                                    class="subject-code"
                                >

                                    <?php
                                    echo e(
                                        $row["subject_code"]
                                    );
                                    ?>

                                </div>


                                <div
                                    class="subject-name"
                                >

                                    <?php
                                    echo e(
                                        $row["subject_name"]
                                    );
                                    ?>

                                </div>

                            </td>


                            <td>

                                <span
                                    class="status-badge
                                    <?php
                                    echo $status_class;
                                    ?>"
                                >

                                    <?php
                                    echo e(
                                        $row["status"]
                                    );
                                    ?>

                                </span>

                            </td>


                            <td>

                                <span class="attempts">

                                    <?php
                                    echo (int)$row["attempts"];
                                    ?>

                                </span>

                            </td>


                            <td class="remark">

                                <?php

                                echo !empty(
                                    $row["remark"]
                                )
                                    ? e($row["remark"])
                                    : "-";

                                ?>

                            </td>


                            <td class="date">

                                <?php
                                echo formatDateValue(
                                    $row["cleared_date"]
                                );
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
                    🎉
                </div>


                <h3>
                    No backlog records
                </h3>


                <p>
                    You currently have no backlog records
                    entered in the system.
                </p>

            </div>


        <?php endif; ?>


    </div>


    <!-- ================= NOTE ================= -->

    <div class="note">

        <strong>
            Note:
        </strong>

        Backlog information is managed by the Teacher/Admin.
        If you find any incorrect information, please contact
        your Teacher/Admin.

    </div>


</main>


</body>

</html>