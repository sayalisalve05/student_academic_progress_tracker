<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_student();

$user_id = $_SESSION["user_id"];

/* Get student */
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

/* Get attendance records */
$stmt = $conn->prepare("
    SELECT
        a.id,
        a.total_classes,
        a.attended_classes,
        a.attendance_date,
        a.remarks,
        sub.subject_code,
        sub.subject_name,
        sem.semester_number
    FROM attendance a
    INNER JOIN subjects sub ON a.subject_id = sub.id
    LEFT JOIN semesters sem ON sub.semester_id = sem.id
    WHERE a.student_id = ?
    ORDER BY sem.semester_number ASC,
             sub.subject_code ASC,
             a.attendance_date DESC,
             a.id DESC
");

$stmt->bind_param("i", $student_id);
$stmt->execute();
$attendance_result = $stmt->get_result();

$attendance_records = [];

$total_classes_all = 0;
$attended_classes_all = 0;

while ($row = $attendance_result->fetch_assoc()) {
    $attendance_records[] = $row;

    $total_classes_all += (int)$row["total_classes"];
    $attended_classes_all += (int)$row["attended_classes"];
}

$stmt->close();

/* Overall attendance */
$overall_percentage = 0;

if ($total_classes_all > 0) {
    $overall_percentage =
        ($attended_classes_all / $total_classes_all) * 100;
}

/* Helpers */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function attendancePercentage($attended, $total): float
{
    if ((int)$total <= 0) {
        return 0;
    }

    return ((int)$attended / (int)$total) * 100;
}

function attendanceClass($percentage): string
{
    if ($percentage >= 75) {
        return "good";
    }

    if ($percentage >= 60) {
        return "average";
    }

    return "low";
}

function attendanceStatus($percentage): string
{
    if ($percentage >= 75) {
        return "Good";
    }

    if ($percentage >= 60) {
        return "Warning";
    }

    return "Low";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Attendance | Academic Tracker</title>

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
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 14px;
            padding: 22px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
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

        .green {
            color: #16a34a;
        }

        .orange {
            color: #ea580c;
        }

        .purple {
            color: #4f46e5;
        }

        /* ================= CONTENT CARD ================= */

        .card {
            background: white;
            border-radius: 14px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
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

        .subject-code {
            font-weight: 800;
            color: #4f46e5;
            margin-bottom: 4px;
        }

        .subject-name {
            color: #374151;
            font-weight: 600;
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

        .percentage {
            font-weight: 800;
            font-size: 14px;
        }

        .attendance-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 7px;
            font-size: 11px;
            font-weight: 800;
        }

        .attendance-badge.good {
            background: #dcfce7;
            color: #15803d;
        }

        .attendance-badge.average {
            background: #ffedd5;
            color: #c2410c;
        }

        .attendance-badge.low {
            background: #fee2e2;
            color: #b91c1c;
        }

        .status-text {
            font-size: 11px;
            font-weight: 800;
        }

        .status-text.good {
            color: #16a34a;
        }

        .status-text.average {
            color: #ea580c;
        }

        .status-text.low {
            color: #dc2626;
        }

        .date {
            color: #6b7280;
            white-space: nowrap;
        }

        .remarks {
            max-width: 220px;
            color: #6b7280;
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
            <h1>My Attendance</h1>

            <p>
                Track your class attendance and attendance percentage.
            </p>
        </div>

        <div class="student-info">
            <?php echo e($student["full_name"]); ?>
            &nbsp;•&nbsp;
            <?php echo e($student["prn"]); ?>
        </div>

    </div>


    <!-- ================= STATS ================= -->

    <div class="stats-grid">

        <div class="stat-card">

            <div class="stat-title">
                Overall Attendance
            </div>

            <div class="stat-value green">
                <?php echo number_format($overall_percentage, 2); ?>%
            </div>

            <div class="stat-subtitle">
                Overall attendance percentage
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                Classes Attended
            </div>

            <div class="stat-value purple">
                <?php echo $attended_classes_all; ?>
            </div>

            <div class="stat-subtitle">
                Total classes attended
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                Total Classes
            </div>

            <div class="stat-value orange">
                <?php echo $total_classes_all; ?>
            </div>

            <div class="stat-subtitle">
                Classes recorded
            </div>

        </div>

    </div>


    <!-- ================= ATTENDANCE TABLE ================= -->

    <div class="card">

        <div class="card-header">

            <h2>Attendance Records</h2>

            <p>
                Subject-wise attendance records entered by Teacher/Admin.
            </p>

        </div>


        <?php if (count($attendance_records) > 0): ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>
                            <th>Semester</th>
                            <th>Subject</th>
                            <th>Classes</th>
                            <th>Attended</th>
                            <th>Attendance</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Remarks</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($attendance_records as $row): ?>

                        <?php
                            $total = (int)$row["total_classes"];
                            $attended = (int)$row["attended_classes"];

                            $percentage =
                                attendancePercentage(
                                    $attended,
                                    $total
                                );

                            $class =
                                attendanceClass($percentage);

                            $status =
                                attendanceStatus($percentage);
                        ?>

                        <tr>

                            <td>

                                <?php if (!empty($row["semester_number"])): ?>

                                    <span class="semester-badge">
                                        Semester
                                        <?php echo e($row["semester_number"]); ?>
                                    </span>

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>


                            <td>

                                <div class="subject-code">
                                    <?php echo e($row["subject_code"]); ?>
                                </div>

                                <div class="subject-name">
                                    <?php echo e($row["subject_name"]); ?>
                                </div>

                            </td>


                            <td>
                                <?php echo $total; ?>
                            </td>


                            <td>
                                <?php echo $attended; ?>
                            </td>


                            <td>

                                <span class="attendance-badge <?php echo $class; ?>">

                                    <?php
                                        echo number_format(
                                            $percentage,
                                            2
                                        );
                                    ?>%

                                </span>

                            </td>


                            <td>

                                <span class="status-text <?php echo $class; ?>">
                                    <?php echo $status; ?>
                                </span>

                            </td>


                            <td class="date">

                                <?php
                                    echo formatDateValue(
                                        $row["attendance_date"]
                                    );
                                ?>

                            </td>


                            <td class="remarks">

                                <?php
                                    echo !empty($row["remarks"])
                                        ? e($row["remarks"])
                                        : "-";
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
                    📅
                </div>

                <h3>No attendance records found</h3>

                <p>
                    Your attendance records will appear here
                    once they are entered by Teacher/Admin.
                </p>

            </div>

        <?php endif; ?>

    </div>


    <div class="note">

        <strong>Note:</strong>
        Attendance information is managed by the Teacher/Admin.
        If you notice any incorrect attendance record,
        please contact your Teacher/Admin.

    </div>

</main>

</body>
</html>