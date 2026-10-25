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
        s.id,
        s.full_name,
        s.prn,
        s.roll_no,
        s.course,
        s.department,
        s.admission_year,
        s.current_semester,
        s.gender,
        s.birth_date,
        s.phone,
        s.address,
        u.email
    FROM students s
    INNER JOIN users u ON s.user_id = u.id
    WHERE s.user_id = ?
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


/* =========================
   MARKS
========================= */
$marks = [];

$stmt = $conn->prepare("
    SELECT
        m.id,
        m.marks_obtained,
        m.max_marks,
        m.exam_type,
        m.exam_date,
        m.remarks,
        sub.subject_code,
        sub.subject_name,
        sem.semester_number
    FROM marks m
    INNER JOIN subjects sub
        ON m.subject_id = sub.id
    INNER JOIN semesters sem
        ON sub.semester_id = sem.id
    WHERE m.student_id = ?
    ORDER BY
        sem.semester_number ASC,
        sub.subject_code ASC,
        m.exam_date ASC,
        m.id ASC
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $marks[] = $row;
}

$stmt->close();


/* =========================
   ATTENDANCE
========================= */
$attendance = [];

$stmt = $conn->prepare("
    SELECT
        a.total_classes,
        a.attended_classes,
        a.attendance_date,
        a.remarks,
        sub.subject_code,
        sub.subject_name,
        sem.semester_number
    FROM attendance a
    INNER JOIN subjects sub
        ON a.subject_id = sub.id
    LEFT JOIN semesters sem
        ON sub.semester_id = sem.id
    WHERE a.student_id = ?
    ORDER BY
        sem.semester_number ASC,
        sub.subject_code ASC,
        a.attendance_date ASC
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $attendance[] = $row;
}

$stmt->close();


/* =========================
   BACKLOGS
========================= */
$backlogs = [];

$stmt = $conn->prepare("
    SELECT
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
        sub.subject_code ASC
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $backlogs[] = $row;
}

$stmt->close();


/* =========================
   OVERALL MARKS
========================= */
$total_marks = 0;
$total_max_marks = 0;

foreach ($marks as $mark) {
    $total_marks += (float) $mark["marks_obtained"];
    $total_max_marks += (float) $mark["max_marks"];
}

$overall_percentage = $total_max_marks > 0
    ? round(($total_marks / $total_max_marks) * 100, 2)
    : 0;


/* =========================
   OVERALL ATTENDANCE
========================= */
$total_classes = 0;
$total_attended = 0;

foreach ($attendance as $record) {
    $total_classes += (int) $record["total_classes"];
    $total_attended += (int) $record["attended_classes"];
}

$attendance_percentage = $total_classes > 0
    ? round(($total_attended / $total_classes) * 100, 2)
    : 0;


/* =========================
   SEMESTER MARK SUMMARY
========================= */
$semester_summary = [];

foreach ($marks as $mark) {

    $semester = (int) $mark["semester_number"];

    if (!isset($semester_summary[$semester])) {
        $semester_summary[$semester] = [
            "marks" => 0,
            "max_marks" => 0
        ];
    }

    $semester_summary[$semester]["marks"] +=
        (float) $mark["marks_obtained"];

    $semester_summary[$semester]["max_marks"] +=
        (float) $mark["max_marks"];
}


/* =========================
   SUBJECT MARK SUMMARY
========================= */
$subject_summary = [];

foreach ($marks as $mark) {

    $key = $mark["semester_number"] . "_" . $mark["subject_code"];

    if (!isset($subject_summary[$key])) {

        $subject_summary[$key] = [
            "semester" => $mark["semester_number"],
            "code" => $mark["subject_code"],
            "name" => $mark["subject_name"],
            "marks" => 0,
            "max_marks" => 0
        ];
    }

    $subject_summary[$key]["marks"] +=
        (float) $mark["marks_obtained"];

    $subject_summary[$key]["max_marks"] +=
        (float) $mark["max_marks"];
}


/* =========================
   SUBJECT ATTENDANCE SUMMARY
========================= */
$attendance_summary = [];

foreach ($attendance as $record) {

    $key = $record["semester_number"] . "_" . $record["subject_code"];

    if (!isset($attendance_summary[$key])) {

        $attendance_summary[$key] = [
            "semester" => $record["semester_number"],
            "code" => $record["subject_code"],
            "name" => $record["subject_name"],
            "total" => 0,
            "attended" => 0
        ];
    }

    $attendance_summary[$key]["total"] +=
        (int) $record["total_classes"];

    $attendance_summary[$key]["attended"] +=
        (int) $record["attended_classes"];
}


/* =========================
   HELPERS
========================= */
function e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

function percentageClass(float $percentage): string
{
    if ($percentage >= 75) {
        return "good";
    }

    if ($percentage >= 40) {
        return "average";
    }

    return "low";
}

function formatDateValue($value): string
{
    if (!$value) {
        return "-";
    }

    $time = strtotime($value);

    if (!$time) {
        return e($value);
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

    <title>Academic Report</title>

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

        .actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
        }

        .btn.primary {
            background: #4f46e5;
            color: white;
        }

        .btn.secondary {
            background: #e5e7eb;
            color: #374151;
        }

        .report {
            background: white;
            border-radius: 15px;
            padding: 30px;
            border: 1px solid #e5e7eb;
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }

        .college-title {
            color: #111827;
            font-size: 23px;
            font-weight: 800;
            margin: 0;
        }

        .report-title {
            color: #4f46e5;
            font-size: 18px;
            font-weight: 700;
            margin-top: 7px;
        }

        .report-date {
            color: #6b7280;
            font-size: 12px;
        }

        .student-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 28px;
        }

        .info-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 9px;
            padding: 12px;
        }

        .info-label {
            font-size: 11px;
            color: #6b7280;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 14px;
            color: #111827;
            font-weight: 700;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 28px;
        }

        .summary-card {
            border-radius: 11px;
            padding: 18px;
            background: #eef2ff;
        }

        .summary-card.green {
            background: #ecfdf5;
        }

        .summary-card.orange {
            background: #fff7ed;
        }

        .summary-label {
            font-size: 12px;
            color: #6b7280;
            font-weight: 600;
        }

        .summary-value {
            font-size: 25px;
            font-weight: 800;
            color: #111827;
            margin-top: 5px;
        }

        .section {
            margin-top: 30px;
        }

        .section-title {
            color: #111827;
            font-size: 18px;
            margin: 0 0 12px;
            padding-bottom: 9px;
            border-bottom: 1px solid #e5e7eb;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th {
            background: #f9fafb;
            color: #6b7280;
            font-size: 11px;
            text-transform: uppercase;
            padding: 11px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        td {
            padding: 11px;
            color: #374151;
            font-size: 13px;
            border-bottom: 1px solid #e5e7eb;
        }

        .subject-code {
            font-weight: 700;
            color: #4f46e5;
        }

        .badge {
            display: inline-block;
            padding: 5px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
        }

        .badge.good {
            background: #dcfce7;
            color: #15803d;
        }

        .badge.average {
            background: #fef3c7;
            color: #b45309;
        }

        .badge.low {
            background: #fee2e2;
            color: #b91c1c;
        }

        .backlog-pending {
            color: #dc2626;
            font-weight: 700;
        }

        .backlog-cleared {
            color: #059669;
            font-weight: 700;
        }

        .report-footer {
            border-top: 1px solid #e5e7eb;
            margin-top: 35px;
            padding-top: 18px;
            color: #6b7280;
            font-size: 11px;
            text-align: center;
        }

        .empty {
            text-align: center;
            color: #6b7280;
            padding: 25px;
        }

        @media (max-width: 900px) {

            .student-grid,
            .summary-grid {
                grid-template-columns: 1fr 1fr;
            }

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

            .report-header {
                flex-direction: column;
                gap: 10px;
            }

            .student-grid,
            .summary-grid {
                grid-template-columns: 1fr;
            }

        }

        @media print {

            @page {
                size: A4;
                margin: 12mm;
            }

            body {
                background: white;
            }

            .main {
                margin-left: 0;
                padding: 0;
            }

            .sidebar,
            .topbar,
            .actions {
                display: none !important;
            }

            .report {
                border: none;
                padding: 0;
                border-radius: 0;
            }

            .section {
                break-inside: avoid;
            }

            table {
                page-break-inside: auto;
            }

            tr {
                page-break-inside: avoid;
            }

        }

    </style>

</head>

<body>

<div class="app">

    <?php include "includes/sidebar.php"; ?>

    <main class="main">

        <!-- PAGE HEADER -->
        <div class="topbar">

            <div>

                <h1>Academic Report</h1>

                <div class="muted">
                    Complete academic performance report.
                </div>

            </div>

            <div class="actions">

                <a
                    href="dashboard.php"
                    class="btn secondary"
                >
                    Dashboard
                </a>

                <button
                    type="button"
                    class="btn primary"
                    onclick="window.print()"
                >
                    🖨️ Print Report
                </button>

            </div>

        </div>


        <!-- REPORT -->
        <div class="report">

            <!-- REPORT HEADER -->
            <div class="report-header">

                <div>

                    <h2 class="college-title">
                        Student Academic Progress Tracker
                    </h2>

                    <div class="report-title">
                        ACADEMIC PERFORMANCE REPORT
                    </div>

                </div>

                <div class="report-date">
                    Generated on <?= date("d M Y") ?>
                </div>

            </div>


            <!-- STUDENT DETAILS -->
            <div class="student-grid">

                <div class="info-box">

                    <div class="info-label">
                        Student Name
                    </div>

                    <div class="info-value">
                        <?= e($student["full_name"]) ?>
                    </div>

                </div>

                <div class="info-box">

                    <div class="info-label">
                        PRN
                    </div>

                    <div class="info-value">
                        <?= e($student["prn"]) ?>
                    </div>

                </div>

                <div class="info-box">

                    <div class="info-label">
                        Roll Number
                    </div>

                    <div class="info-value">
                        <?= e($student["roll_no"]) ?>
                    </div>

                </div>

                <div class="info-box">

                    <div class="info-label">
                        Course
                    </div>

                    <div class="info-value">
                        <?= e($student["course"]) ?>
                    </div>

                </div>

                <div class="info-box">

                    <div class="info-label">
                        Department
                    </div>

                    <div class="info-value">
                        <?= e($student["department"]) ?>
                    </div>

                </div>

                <div class="info-box">

                    <div class="info-label">
                        Current Semester
                    </div>

                    <div class="info-value">
                        Semester <?= e($student["current_semester"]) ?>
                    </div>

                </div>

                <div class="info-box">

                    <div class="info-label">
                        Admission Year
                    </div>

                    <div class="info-value">
                        <?= e($student["admission_year"]) ?>
                    </div>

                </div>

                <div class="info-box">

                    <div class="info-label">
                        Email
                    </div>

                    <div class="info-value">
                        <?= e($student["email"]) ?>
                    </div>

                </div>

                <div class="info-box">

                    <div class="info-label">
                        Phone
                    </div>

                    <div class="info-value">
                        <?= e($student["phone"]) ?>
                    </div>

                </div>

            </div>


            <!-- SUMMARY -->
            <div class="summary-grid">

                <div class="summary-card">

                    <div class="summary-label">
                        Overall Marks
                    </div>

                    <div class="summary-value">
                        <?= number_format($overall_percentage, 2) ?>%
                    </div>

                </div>

                <div class="summary-card green">

                    <div class="summary-label">
                        Overall Attendance
                    </div>

                    <div class="summary-value">
                        <?= number_format($attendance_percentage, 2) ?>%
                    </div>

                </div>

                <div class="summary-card orange">

                    <div class="summary-label">
                        Backlogs
                    </div>

                    <div class="summary-value">
                        <?= count($backlogs) ?>
                    </div>

                </div>

            </div>


            <!-- SEMESTER PERFORMANCE -->
            <div class="section">

                <h2 class="section-title">
                    Semester-wise Performance
                </h2>

                <?php if (!empty($semester_summary)): ?>

                    <div class="table-wrapper">

                        <table>

                            <thead>

                                <tr>
                                    <th>Semester</th>
                                    <th>Marks Obtained</th>
                                    <th>Maximum Marks</th>
                                    <th>Percentage</th>
                                    <th>Status</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($semester_summary as $semester => $data): ?>

                                    <?php
                                    $percentage = $data["max_marks"] > 0
                                        ? round(
                                            ($data["marks"] / $data["max_marks"]) * 100,
                                            2
                                        )
                                        : 0;

                                    $class = percentageClass($percentage);
                                    ?>

                                    <tr>

                                        <td>
                                            <strong>
                                                Semester <?= e($semester) ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <?= e($data["marks"]) ?>
                                        </td>

                                        <td>
                                            <?= e($data["max_marks"]) ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?= number_format($percentage, 2) ?>%
                                            </strong>
                                        </td>

                                        <td>
                                            <span class="badge <?= $class ?>">
                                                <?php
                                                if ($percentage >= 75) {
                                                    echo "Excellent";
                                                } elseif ($percentage >= 40) {
                                                    echo "Pass";
                                                } else {
                                                    echo "Needs Improvement";
                                                }
                                                ?>
                                            </span>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="empty">
                        No marks have been recorded.
                    </div>

                <?php endif; ?>

            </div>


            <!-- SUBJECT MARKS -->
            <div class="section">

                <h2 class="section-title">
                    Subject-wise Marks
                </h2>

                <?php if (!empty($subject_summary)): ?>

                    <div class="table-wrapper">

                        <table>

                            <thead>

                                <tr>
                                    <th>Semester</th>
                                    <th>Subject</th>
                                    <th>Marks</th>
                                    <th>Percentage</th>
                                    <th>Status</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($subject_summary as $subject): ?>

                                    <?php
                                    $percentage = $subject["max_marks"] > 0
                                        ? round(
                                            ($subject["marks"] / $subject["max_marks"]) * 100,
                                            2
                                        )
                                        : 0;

                                    $class = percentageClass($percentage);
                                    ?>

                                    <tr>

                                        <td>
                                            Sem <?= e($subject["semester"]) ?>
                                        </td>

                                        <td>

                                            <span class="subject-code">
                                                <?= e($subject["code"]) ?>
                                            </span>

                                            <br>

                                            <?= e($subject["name"]) ?>

                                        </td>

                                        <td>
                                            <?= e($subject["marks"]) ?>
                                            /
                                            <?= e($subject["max_marks"]) ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?= number_format($percentage, 2) ?>%
                                            </strong>
                                        </td>

                                        <td>

                                            <span class="badge <?= $class ?>">

                                                <?php
                                                if ($percentage >= 40) {
                                                    echo "Passed";
                                                } else {
                                                    echo "Failed";
                                                }
                                                ?>

                                            </span>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="empty">
                        No subject marks available.
                    </div>

                <?php endif; ?>

            </div>


            <!-- ATTENDANCE -->
            <div class="section">

                <h2 class="section-title">
                    Subject-wise Attendance
                </h2>

                <?php if (!empty($attendance_summary)): ?>

                    <div class="table-wrapper">

                        <table>

                            <thead>

                                <tr>
                                    <th>Semester</th>
                                    <th>Subject</th>
                                    <th>Total Classes</th>
                                    <th>Attended</th>
                                    <th>Attendance</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($attendance_summary as $record): ?>

                                    <?php
                                    $attendance_percent =
                                        $record["total"] > 0
                                        ? round(
                                            ($record["attended"] / $record["total"]) * 100,
                                            2
                                        )
                                        : 0;

                                    $class =
                                        percentageClass(
                                            $attendance_percent
                                        );
                                    ?>

                                    <tr>

                                        <td>
                                            Sem <?= e($record["semester"]) ?>
                                        </td>

                                        <td>

                                            <span class="subject-code">
                                                <?= e($record["code"]) ?>
                                            </span>

                                            <br>

                                            <?= e($record["name"]) ?>

                                        </td>

                                        <td>
                                            <?= e($record["total"]) ?>
                                        </td>

                                        <td>
                                            <?= e($record["attended"]) ?>
                                        </td>

                                        <td>

                                            <strong>
                                                <?= number_format(
                                                    $attendance_percent,
                                                    2
                                                ) ?>%
                                            </strong>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="empty">
                        No attendance records available.
                    </div>

                <?php endif; ?>

            </div>


            <!-- BACKLOGS -->
            <div class="section">

                <h2 class="section-title">
                    Backlog Details
                </h2>

                <?php if (!empty($backlogs)): ?>

                    <div class="table-wrapper">

                        <table>

                            <thead>

                                <tr>
                                    <th>Semester</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Attempts</th>
                                    <th>Cleared Date</th>
                                    <th>Remark</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($backlogs as $backlog): ?>

                                    <tr>

                                        <td>
                                            Sem <?= e($backlog["semester_number"]) ?>
                                        </td>

                                        <td>

                                            <span class="subject-code">
                                                <?= e($backlog["subject_code"]) ?>
                                            </span>

                                            <br>

                                            <?= e($backlog["subject_name"]) ?>

                                        </td>

                                        <td>

                                            <?php if ($backlog["status"] === "Pending"): ?>

                                                <span class="backlog-pending">
                                                    Pending
                                                </span>

                                            <?php else: ?>

                                                <span class="backlog-cleared">
                                                    Cleared
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <td>
                                            <?= e($backlog["attempts"]) ?>
                                        </td>

                                        <td>
                                            <?= formatDateValue(
                                                $backlog["cleared_date"]
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= e(
                                                $backlog["remark"] ?: "-"
                                            ) ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="empty">
                        🎉 No backlog records found.
                    </div>

                <?php endif; ?>

            </div>


            <!-- FOOTER -->
            <div class="report-footer">

                This report is generated from the Student Academic
                Progress Tracker system.

                <br>

                Please verify the information with the college
                academic records if required.

            </div>

        </div>

    </main>

</div>

</body>
</html>