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
        course,
        department,
        current_semester
    FROM students
    WHERE user_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$student_result = $stmt->get_result();
$student = $student_result->fetch_assoc();
$stmt->close();

if (!$student) {
    die("Student profile not found.");
}

$student_id = (int) $student["id"];
$current_semester = (int) $student["current_semester"];


/* =========================
   OVERALL MARKS
========================= */
$stmt = $conn->prepare("
    SELECT
        COALESCE(SUM(m.marks_obtained), 0) AS total_marks,
        COALESCE(SUM(m.max_marks), 0) AS max_marks,
        COUNT(DISTINCT m.subject_id) AS total_subjects
    FROM marks m
    WHERE m.student_id = ?
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$overall_marks = $stmt->get_result()->fetch_assoc();
$stmt->close();

$total_marks = (float) $overall_marks["total_marks"];
$max_marks = (float) $overall_marks["max_marks"];
$total_subjects = (int) $overall_marks["total_subjects"];

$overall_percentage = $max_marks > 0
    ? round(($total_marks / $max_marks) * 100, 2)
    : 0;


/* =========================
   BACKLOG COUNT
========================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS pending_backlogs
    FROM backlogs
    WHERE student_id = ?
      AND status = 'Pending'
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$backlog_result = $stmt->get_result()->fetch_assoc();
$stmt->close();

$pending_backlogs = (int) $backlog_result["pending_backlogs"];


/* =========================
   SEMESTER-WISE PERFORMANCE
========================= */
$semester_data = [];

$stmt = $conn->prepare("
    SELECT
        sem.semester_number,
        COALESCE(SUM(m.marks_obtained), 0) AS total_marks,
        COALESCE(SUM(m.max_marks), 0) AS max_marks
    FROM marks m
    INNER JOIN subjects sub
        ON m.subject_id = sub.id
    INNER JOIN semesters sem
        ON sub.semester_id = sem.id
    WHERE m.student_id = ?
    GROUP BY sem.id, sem.semester_number
    ORDER BY sem.semester_number ASC
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $percentage = ((float)$row["max_marks"] > 0)
        ? round(
            ((float)$row["total_marks"] / (float)$row["max_marks"]) * 100,
            2
        )
        : 0;

    $semester_data[] = [
        "semester" => (int)$row["semester_number"],
        "percentage" => $percentage
    ];
}

$stmt->close();


/* =========================
   CURRENT SEMESTER SUBJECTS
========================= */
$current_subjects = [];

$stmt = $conn->prepare("
    SELECT
        sub.subject_code,
        sub.subject_name,
        COALESCE(SUM(m.marks_obtained), 0) AS total_marks,
        COALESCE(SUM(m.max_marks), 0) AS max_marks
    FROM subjects sub
    LEFT JOIN marks m
        ON m.subject_id = sub.id
       AND m.student_id = ?
    INNER JOIN semesters sem
        ON sub.semester_id = sem.id
    WHERE sem.semester_number = ?
    GROUP BY
        sub.id,
        sub.subject_code,
        sub.subject_name
    ORDER BY sub.subject_code ASC
");

$stmt->bind_param(
    "ii",
    $student_id,
    $current_semester
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $percentage = ((float)$row["max_marks"] > 0)
        ? round(
            ((float)$row["total_marks"] / (float)$row["max_marks"]) * 100,
            2
        )
        : 0;

    $current_subjects[] = [
        "code" => $row["subject_code"],
        "name" => $row["subject_name"],
        "percentage" => $percentage,
        "marks" => $row["total_marks"],
        "max_marks" => $row["max_marks"]
    ];
}

$stmt->close();


/* =========================
   HELPER
========================= */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function progressClass(float $percentage): string
{
    if ($percentage >= 75) {
        return "good";
    }

    if ($percentage >= 40) {
        return "average";
    }

    return "low";
}

function progressStatus(float $percentage): string
{
    if ($percentage >= 75) {
        return "Excellent";
    }

    if ($percentage >= 60) {
        return "Good";
    }

    if ($percentage >= 40) {
        return "Pass";
    }

    if ($percentage > 0) {
        return "Needs Improvement";
    }

    return "No Marks";
}

$semester_labels = [];
$semester_percentages = [];

foreach ($semester_data as $semester) {
    $semester_labels[] = "Sem " . $semester["semester"];
    $semester_percentages[] = $semester["percentage"];
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

    <title>Academic Progress</title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
            font-size: 28px;
            color: #111827;
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
            border-radius: 14px;
            padding: 18px 22px;
            margin-bottom: 22px;
            border: 1px solid #e5e7eb;
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
            margin-top: 8px;
        }

        .student-badge {
            background: #eef2ff;
            color: #4338ca;
            padding: 6px 10px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 22px;
        }

        .stat-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 20px;
        }

        .stat-label {
            color: #6b7280;
            font-size: 13px;
            font-weight: 600;
        }

        .stat-value {
            font-size: 29px;
            font-weight: 800;
            color: #111827;
            margin-top: 8px;
        }

        .stat-note {
            color: #9ca3af;
            font-size: 12px;
            margin-top: 5px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 22px;
            margin-bottom: 22px;
        }

        .card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 22px;
            box-sizing: border-box;
        }

        .card h2 {
            margin: 0;
            color: #111827;
            font-size: 19px;
        }

        .card-subtitle {
            color: #6b7280;
            font-size: 13px;
            margin-top: 5px;
            margin-bottom: 18px;
        }

        .chart-container {
            position: relative;
            height: 320px;
        }

        .current-semester-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 18px;
        }

        .semester-badge {
            background: #eef2ff;
            color: #4338ca;
            padding: 7px 11px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
        }

        .subject-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .subject-item {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 14px;
        }

        .subject-top {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 9px;
        }

        .subject-name {
            font-weight: 700;
            color: #111827;
            font-size: 14px;
        }

        .subject-code {
            color: #6b7280;
            font-size: 11px;
            margin-top: 3px;
        }

        .percentage {
            font-weight: 800;
            font-size: 14px;
        }

        .percentage.good {
            color: #059669;
        }

        .percentage.average {
            color: #d97706;
        }

        .percentage.low {
            color: #dc2626;
        }

        .progress-bar {
            width: 100%;
            height: 9px;
            background: #e5e7eb;
            border-radius: 20px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 20px;
        }

        .progress-fill.good {
            background: #10b981;
        }

        .progress-fill.average {
            background: #f59e0b;
        }

        .progress-fill.low {
            background: #ef4444;
        }

        .subject-bottom {
            display: flex;
            justify-content: space-between;
            margin-top: 7px;
            color: #6b7280;
            font-size: 11px;
        }

        .semester-table {
            width: 100%;
            border-collapse: collapse;
        }

        .semester-table th {
            background: #f9fafb;
            color: #6b7280;
            font-size: 12px;
            text-align: left;
            padding: 12px;
        }

        .semester-table td {
            padding: 13px 12px;
            border-top: 1px solid #e5e7eb;
            font-size: 13px;
            color: #374151;
        }

        .semester-number {
            font-weight: 700;
            color: #111827;
        }

        .status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 7px;
            font-size: 11px;
            font-weight: 700;
        }

        .status.good {
            background: #dcfce7;
            color: #15803d;
        }

        .status.average {
            background: #fef3c7;
            color: #b45309;
        }

        .status.low {
            background: #fee2e2;
            color: #b91c1c;
        }

        .empty {
            text-align: center;
            padding: 35px 15px;
            color: #6b7280;
        }

        @media (max-width: 1100px) {

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 700px) {

            .main {
                margin-left: 260px;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
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

                <h1>Academic Progress</h1>

                <div class="muted">
                    Track your academic performance semester by semester.
                </div>

            </div>

            <a
                class="btn primary"
                href="dashboard.php"
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

                <span class="student-badge">
                    <?= e($student["course"]) ?>
                </span>

            </div>

        </div>


        <!-- STAT CARDS -->
        <div class="stats-grid">

            <div class="stat-card">

                <div class="stat-label">
                    Overall Performance
                </div>

                <div class="stat-value">
                    <?= number_format($overall_percentage, 2) ?>%
                </div>

                <div class="stat-note">
                    Based on recorded marks
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Total Subjects
                </div>

                <div class="stat-value">
                    <?= $total_subjects ?>
                </div>

                <div class="stat-note">
                    Subjects with marks
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Current Semester
                </div>

                <div class="stat-value">
                    <?= $current_semester ?>
                </div>

                <div class="stat-note">
                    Your current semester
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Pending Backlogs
                </div>

                <div class="stat-value">
                    <?= $pending_backlogs ?>
                </div>

                <div class="stat-note">
                    Subjects requiring attention
                </div>

            </div>

        </div>


        <!-- CHART + CURRENT SUBJECTS -->
        <div class="dashboard-grid">

            <!-- CHART -->
            <div class="card">

                <h2>Semester Performance</h2>

                <div class="card-subtitle">
                    Your percentage performance across semesters
                </div>

                <?php if (!empty($semester_data)): ?>

                    <div class="chart-container">
                        <canvas id="semesterChart"></canvas>
                    </div>

                <?php else: ?>

                    <div class="empty">
                        No marks have been recorded yet.
                    </div>

                <?php endif; ?>

            </div>


            <!-- CURRENT SEMESTER -->
            <div class="card">

                <div class="current-semester-title">

                    <div>

                        <h2>Current Semester</h2>

                        <div class="card-subtitle">
                            Subject performance
                        </div>

                    </div>

                    <span class="semester-badge">
                        Sem <?= $current_semester ?>
                    </span>

                </div>


                <?php if (!empty($current_subjects)): ?>

                    <div class="subject-list">

                        <?php foreach ($current_subjects as $subject): ?>

                            <?php
                            $percentage = $subject["percentage"];
                            $class = progressClass($percentage);
                            ?>

                            <div class="subject-item">

                                <div class="subject-top">

                                    <div>

                                        <div class="subject-name">
                                            <?= e($subject["name"]) ?>
                                        </div>

                                        <div class="subject-code">
                                            <?= e($subject["code"]) ?>
                                        </div>

                                    </div>

                                    <div class="percentage <?= $class ?>">
                                        <?= number_format($percentage, 2) ?>%
                                    </div>

                                </div>


                                <div class="progress-bar">

                                    <div
                                        class="progress-fill <?= $class ?>"
                                        style="width: <?= min(100, max(0, $percentage)) ?>%;"
                                    ></div>

                                </div>


                                <div class="subject-bottom">

                                    <span>
                                        <?= e($subject["marks"]) ?>
                                        /
                                        <?= e($subject["max_marks"]) ?>
                                    </span>

                                    <span>
                                        <?= progressStatus($percentage) ?>
                                    </span>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="empty">
                        No subjects or marks found for the current semester.
                    </div>

                <?php endif; ?>

            </div>

        </div>


        <!-- SEMESTER TABLE -->
        <div class="card">

            <h2>Semester-wise Performance</h2>

            <div class="card-subtitle">
                Overall percentage calculated from recorded marks.
            </div>


            <?php if (!empty($semester_data)): ?>

                <div style="overflow-x:auto;">

                    <table class="semester-table">

                        <thead>

                            <tr>
                                <th>Semester</th>
                                <th>Percentage</th>
                                <th>Performance</th>
                                <th>Progress</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($semester_data as $semester): ?>

                                <?php
                                $percentage = $semester["percentage"];
                                $class = progressClass($percentage);
                                ?>

                                <tr>

                                    <td class="semester-number">
                                        Semester <?= e($semester["semester"]) ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= number_format($percentage, 2) ?>%
                                        </strong>
                                    </td>

                                    <td>

                                        <span class="status <?= $class ?>">
                                            <?= progressStatus($percentage) ?>
                                        </span>

                                    </td>

                                    <td style="min-width:200px;">

                                        <div class="progress-bar">

                                            <div
                                                class="progress-fill <?= $class ?>"
                                                style="width: <?= min(100, max(0, $percentage)) ?>%;"
                                            ></div>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty">
                    No semester performance data available.
                </div>

            <?php endif; ?>

        </div>

    </main>

</div>


<?php if (!empty($semester_data)): ?>

<script>

const semesterLabels = <?= json_encode($semester_labels) ?>;

const semesterPercentages = <?= json_encode($semester_percentages) ?>;

const ctx = document.getElementById("semesterChart");

new Chart(ctx, {

    type: "line",

    data: {

        labels: semesterLabels,

        datasets: [

            {

                label: "Percentage",

                data: semesterPercentages,

                borderWidth: 3,

                tension: 0.35,

                fill: false,

                pointRadius: 5,

                pointHoverRadius: 7

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

                display: true

            },

            tooltip: {

                callbacks: {

                    label: function(context) {
                        return " " + context.parsed.y + "%";
                    }

                }

            }

        }

    }

});

</script>

<?php endif; ?>

</body>
</html>