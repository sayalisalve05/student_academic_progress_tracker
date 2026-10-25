<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_student();

$student_id = 0;
$student = null;
$marks = [];
$error = "";


/* =========================================================
   GET LOGGED-IN STUDENT
   ========================================================= */

$stmt = $conn->prepare(
    "SELECT
        students.id,
        students.full_name,
        students.prn,
        students.roll_no,
        students.course,
        students.department,
        students.current_semester,
        students.photo,
        users.email
     FROM students
     INNER JOIN users
        ON students.user_id = users.id
     WHERE students.user_id = ?
     LIMIT 1"
);

$stmt->bind_param(
    "i",
    $_SESSION["user_id"]
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $error = "Student profile not found.";

} else {

    $student = $result->fetch_assoc();
    $student_id = (int)$student["id"];
}


/* =========================================================
   FETCH STUDENT MARKS
   ========================================================= */

if ($student_id > 0) {

    $stmt = $conn->prepare(
        "SELECT
            marks.id,
            marks.exam_type,
            marks.marks_obtained,
            marks.max_marks,
            marks.exam_date,
            marks.remarks,

            subjects.subject_code,
            subjects.subject_name,
            subjects.credits,

            semesters.semester_number,
            semesters.academic_year

         FROM marks

         INNER JOIN subjects
            ON marks.subject_id = subjects.id

         LEFT JOIN semesters
            ON subjects.semester_id = semesters.id

         WHERE marks.student_id = ?

         ORDER BY
            semesters.semester_number ASC,
            subjects.subject_name ASC,
            marks.exam_date DESC,
            marks.id DESC"
    );

    $stmt->bind_param(
        "i",
        $student_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $marks[] = $row;
    }
}


/* =========================================================
   CALCULATE SUMMARY
   ========================================================= */

$total_records = count($marks);

$total_obtained = 0;
$total_max = 0;

$passed_records = 0;
$failed_records = 0;

foreach ($marks as $mark) {

    $obtained = (float)$mark["marks_obtained"];
    $maximum = (float)$mark["max_marks"];

    $total_obtained += $obtained;
    $total_max += $maximum;

    if ($maximum > 0) {

        $percentage =
            ($obtained / $maximum) * 100;

        if ($percentage >= 40) {
            $passed_records++;
        } else {
            $failed_records++;
        }
    }
}


$overall_percentage = 0;

if ($total_max > 0) {

    $overall_percentage =
        ($total_obtained / $total_max) * 100;
}


/* =========================================================
   GROUP MARKS BY SUBJECT
   ========================================================= */

$subject_marks = [];

foreach ($marks as $mark) {

    $subject_key = $mark["subject_code"];

    if (!isset($subject_marks[$subject_key])) {

        $subject_marks[$subject_key] = [
            "subject_code" =>
                $mark["subject_code"],

            "subject_name" =>
                $mark["subject_name"],

            "credits" =>
                $mark["credits"],

            "semester_number" =>
                $mark["semester_number"],

            "records" => []
        ];
    }

    $subject_marks[$subject_key]["records"][] =
        $mark;
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

    <title>My Marks - Student Academic Tracker</title>

    <link
        rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
    >

    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }


        .main-content {
            margin-left: 260px;
            padding: 35px;
            min-height: 100vh;
        }


        /* =====================================================
           HEADER
           ===================================================== */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }


        .page-header h1 {
            margin: 0;
            font-size: 30px;
            font-weight: 800;
            color: #111827;
        }


        .page-header p {
            margin: 7px 0 0;
            color: #6b7280;
            font-size: 14px;
        }


        .student-badge {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            padding: 10px 15px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            color: #4f46e5;
        }


        /* =====================================================
           ERROR
           ===================================================== */

        .alert {
            padding: 15px 18px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 600;
        }


        .error {
            background: #fee2e2;
            color: #991b1b;
        }


        /* =====================================================
           SUMMARY CARDS
           ===================================================== */

        .summary-grid {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));

            gap: 20px;
            margin-bottom: 30px;
        }


        .summary-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 22px;
            box-shadow:
                0 8px 25px rgba(0,0,0,0.05);

            border: 1px solid #f0f0f0;
        }


        .summary-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }


        .summary-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 21px;
            background: #ede9fe;
            color: #6d28d9;
        }


        .summary-card h3 {
            margin: 18px 0 5px;
            font-size: 28px;
            font-weight: 800;
        }


        .summary-card p {
            margin: 0;
            color: #6b7280;
            font-size: 13px;
            font-weight: 500;
        }


        .passed-icon {
            background: #dcfce7;
            color: #15803d;
        }


        .failed-icon {
            background: #fee2e2;
            color: #dc2626;
        }


        .percentage-icon {
            background: #dbeafe;
            color: #2563eb;
        }


        /* =====================================================
           SUBJECT CARD
           ===================================================== */

        .subject-card {
            background: #ffffff;
            border-radius: 18px;
            margin-bottom: 24px;

            box-shadow:
                0 8px 30px rgba(0,0,0,0.05);

            border: 1px solid #f0f0f0;

            overflow: hidden;
        }


        .subject-header {
            padding: 20px 24px;

            display: flex;
            justify-content: space-between;
            align-items: center;

            background:
                linear-gradient(
                    135deg,
                    #faf9ff,
                    #ffffff
                );

            border-bottom: 1px solid #eee;
        }


        .subject-info {
            display: flex;
            align-items: center;
            gap: 14px;
        }


        .subject-icon {
            width: 46px;
            height: 46px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 12px;

            background: #ede9fe;
            color: #6d28d9;

            font-size: 20px;
            font-weight: 800;
        }


        .subject-title h2 {
            margin: 0;
            font-size: 17px;
            font-weight: 800;
            color: #111827;
        }


        .subject-title p {
            margin: 5px 0 0;
            color: #6b7280;
            font-size: 13px;
        }


        .semester-badge {
            padding: 7px 11px;
            background: #eef2ff;
            color: #4f46e5;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
        }


        /* =====================================================
           TABLE
           ===================================================== */

        .table-wrapper {
            overflow-x: auto;
        }


        table {
            width: 100%;
            border-collapse: collapse;
        }


        th {
            text-align: left;
            padding: 14px 24px;

            font-size: 12px;
            text-transform: uppercase;

            color: #6b7280;

            background: #fafafa;

            border-bottom: 1px solid #e5e7eb;

            font-weight: 700;
        }


        td {
            padding: 15px 24px;

            border-bottom: 1px solid #f1f1f1;

            font-size: 14px;
        }


        tr:last-child td {
            border-bottom: none;
        }


        .exam-type {
            font-weight: 700;
            color: #374151;
        }


        .marks-value {
            font-weight: 800;
            color: #111827;
        }


        .marks-percentage {
            font-size: 13px;
            color: #6b7280;
            margin-left: 5px;
        }


        .pass-badge,
        .fail-badge {
            display: inline-flex;
            align-items: center;

            padding: 6px 10px;

            border-radius: 7px;

            font-size: 12px;
            font-weight: 700;
        }


        .pass-badge {
            background: #dcfce7;
            color: #15803d;
        }


        .fail-badge {
            background: #fee2e2;
            color: #b91c1c;
        }


        .date-text {
            color: #6b7280;
            font-size: 13px;
        }


        .remarks {
            color: #6b7280;
            font-size: 13px;
            max-width: 220px;
        }


        /* =====================================================
           EMPTY STATE
           ===================================================== */

        .empty-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 60px 30px;

            text-align: center;

            box-shadow:
                0 8px 30px rgba(0,0,0,0.05);
        }


        .empty-icon {
            width: 75px;
            height: 75px;

            margin: 0 auto 20px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 50%;

            background: #ede9fe;
            color: #6d28d9;

            font-size: 32px;
        }


        .empty-card h2 {
            margin: 0 0 8px;
            font-size: 21px;
        }


        .empty-card p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }


        /* =====================================================
           RESPONSIVE
           ===================================================== */

        @media (max-width: 1100px) {

            .summary-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

        }


        @media (max-width: 800px) {

            .main-content {
                margin-left: 0;
                padding: 20px;
            }


            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }


            .summary-grid {
                grid-template-columns: 1fr;
            }


            .subject-header {
                align-items: flex-start;
                gap: 15px;
                flex-direction: column;
            }

        }

    </style>

</head>


<body>


<?php include "includes/sidebar.php"; ?>



<div class="main-content">


    <!-- =====================================================
         PAGE HEADER
         ===================================================== -->

    <div class="page-header">

        <div>

            <h1>My Marks</h1>

            <p>
                View your examination marks and academic performance
            </p>

        </div>
<?php include "includes/sidebar.php"; ?>

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

.main-content {
    margin-left: 260px;
    padding: 35px;
    min-height: 100vh;
}

.page-header {
    margin-bottom: 24px;
}

.page-header h1 {
    margin: 0;
    font-size: 28px;
    color: #111827;
}

.page-header p {
    margin: 7px 0 0;
    color: #6b7280;
    font-size: 14px;
}

.search-card {
    background: #fff;
    padding: 16px;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    margin-bottom: 22px;
}

.search-form {
    display: flex;
    gap: 10px;
}

.search-input {
    flex: 1;
    min-width: 0;
    padding: 12px 14px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
}

.btn {
    display: inline-block;
    border: none;
    border-radius: 8px;
    padding: 11px 16px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}

.btn-search {
    background: #4f46e5;
    color: #fff;
}

.btn-clear {
    background: #e5e7eb;
    color: #374151;
}

.subject-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}

.subject-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 3px 12px rgba(0,0,0,.04);
}

.subject-card-header {
    padding: 18px 20px;
    background: #faf9ff;
    border-bottom: 1px solid #eee8ff;
}

.subject-code {
    color: #4f46e5;
    font-size: 12px;
    font-weight: 700;
}

.subject-card h2 {
    margin: 7px 0;
    font-size: 18px;
    color: #111827;
}

.subject-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}

.meta-badge {
    padding: 5px 9px;
    border-radius: 20px;
    background: #eef2ff;
    color: #4338ca;
    font-size: 11px;
    font-weight: 600;
}

.subject-card-body {
    padding: 20px;
}

.section-title {
    margin: 0 0 12px;
    font-size: 15px;
    color: #1f2937;
}

.syllabus-text {
    font-size: 14px;
    color: #4b5563;
    line-height: 1.7;
    overflow-wrap: anywhere;
}

.syllabus-text p {
    margin: 0 0 10px;
}

.syllabus-text ul,
.syllabus-text ol {
    padding-left: 22px;
}

.no-syllabus {
    padding: 13px;
    border-radius: 8px;
    background: #f9fafb;
    color: #6b7280;
    font-size: 13px;
}

.photo-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-top: 14px;
}

.photo-item img {
    display: block;
    width: 100%;
    height: auto;
    max-height: 300px;
    object-fit: contain;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
}

.updated-text {
    margin-top: 16px;
    color: #9ca3af;
    font-size: 11px;
}

.empty-state {
    grid-column: 1 / -1;
    background: #fff;
    padding: 45px 20px;
    border-radius: 12px;
    text-align: center;
    color: #6b7280;
}

.empty-state h2 {
    color: #374151;
    font-size: 18px;
}

@media (max-width: 1100px) {
    .subject-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 900px) {
    .main-content {
        margin-left: 220px;
        padding: 22px 18px;
    }
}

@media (max-width: 600px) {
    .main-content {
        padding: 18px 12px;
    }

    .search-form {
        flex-wrap: wrap;
    }

    .search-input {
        flex-basis: 100%;
    }

    .subject-card-header,
    .subject-card-body {
        padding: 15px;
    }
}
</style>


        <?php if ($student): ?>

            <div class="student-badge">

                PRN:
                <?= htmlspecialchars(
                    $student["prn"]
                ) ?>

            </div>

        <?php endif; ?>

    </div>


    <?php if ($error): ?>

        <div class="alert error">

            <?= htmlspecialchars($error) ?>

        </div>


    <?php elseif ($student): ?>


        <!-- =================================================
             SUMMARY
             ================================================= -->

        <div class="summary-grid">


            <!-- TOTAL RECORDS -->

            <div class="summary-card">

                <div class="summary-top">

                    <div>
                        <div class="summary-icon">
                            📊
                        </div>
                    </div>

                </div>


                <h3>
                    <?= $total_records ?>
                </h3>

                <p>
                    Total Mark Records
                </p>

            </div>


            <!-- PASSED -->

            <div class="summary-card">

                <div class="summary-top">

                    <div>
                        <div class="summary-icon passed-icon">
                            ✓
                        </div>
                    </div>

                </div>


                <h3>
                    <?= $passed_records ?>
                </h3>

                <p>
                    Passed Records
                </p>

            </div>


            <!-- FAILED -->

            <div class="summary-card">

                <div class="summary-top">

                    <div>
                        <div class="summary-icon failed-icon">
                            !
                        </div>
                    </div>

                </div>


                <h3>
                    <?= $failed_records ?>
                </h3>

                <p>
                    Failed Records
                </p>

            </div>


            <!-- OVERALL -->

            <div class="summary-card">

                <div class="summary-top">

                    <div>
                        <div class="summary-icon percentage-icon">
                            %
                        </div>
                    </div>

                </div>


                <h3>
                    <?= number_format(
                        $overall_percentage,
                        2
                    ) ?>%
                </h3>

                <p>
                    Overall Percentage
                </p>

            </div>

        </div>


        <!-- =================================================
             MARKS
             ================================================= -->

        <?php if (count($subject_marks) > 0): ?>


            <?php foreach ($subject_marks as $subject): ?>


                <div class="subject-card">


                    <!-- SUBJECT HEADER -->

                    <div class="subject-header">


                        <div class="subject-info">


                            <div class="subject-icon">
                                📚
                            </div>


                            <div class="subject-title">

                                <h2>

                                    <?= htmlspecialchars(
                                        $subject["subject_name"]
                                    ) ?>

                                </h2>


                                <p>

                                    <?= htmlspecialchars(
                                        $subject["subject_code"]
                                    ) ?>


                                    <?php if (
                                        $subject["credits"] !== null &&
                                        $subject["credits"] !== ""
                                    ): ?>

                                        •
                                        <?= htmlspecialchars(
                                            $subject["credits"]
                                        ) ?>
                                        Credits

                                    <?php endif; ?>

                                </p>

                            </div>

                        </div>


                        <?php if (
                            !empty(
                                $subject["semester_number"]
                            )
                        ): ?>

                            <div class="semester-badge">

                                Semester
                                <?= htmlspecialchars(
                                    $subject[
                                        "semester_number"
                                    ]
                                ) ?>

                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- MARKS TABLE -->

                    <div class="table-wrapper">

                        <table>

                            <thead>

                                <tr>

                                    <th>
                                        Exam Type
                                    </th>

                                    <th>
                                        Marks
                                    </th>

                                    <th>
                                        Percentage
                                    </th>

                                    <th>
                                        Result
                                    </th>

                                    <th>
                                        Exam Date
                                    </th>

                                    <th>
                                        Remarks
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php foreach (
                                $subject["records"]
                                as $mark
                            ): ?>


                                <?php

                                $obtained =
                                    (float)
                                    $mark[
                                        "marks_obtained"
                                    ];

                                $maximum =
                                    (float)
                                    $mark[
                                        "max_marks"
                                    ];

                                $percentage = 0;

                                if ($maximum > 0) {

                                    $percentage =
                                        (
                                            $obtained /
                                            $maximum
                                        ) * 100;
                                }

                                $is_pass =
                                    $percentage >= 40;

                                ?>


                                <tr>


                                    <!-- EXAM TYPE -->

                                    <td>

                                        <span
                                            class="exam-type"
                                        >

                                            <?= htmlspecialchars(
                                                $mark[
                                                    "exam_type"
                                                ]
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- MARKS -->

                                    <td>

                                        <span
                                            class="marks-value"
                                        >

                                            <?= htmlspecialchars(
                                                $mark[
                                                    "marks_obtained"
                                                ]
                                            ) ?>

                                            /

                                            <?= htmlspecialchars(
                                                $mark[
                                                    "max_marks"
                                                ]
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- PERCENTAGE -->

                                    <td>

                                        <span
                                            class="marks-value"
                                        >

                                            <?= number_format(
                                                $percentage,
                                                2
                                            ) ?>%

                                        </span>

                                    </td>


                                    <!-- RESULT -->

                                    <td>

                                        <?php if ($is_pass): ?>

                                            <span
                                                class="pass-badge"
                                            >
                                                ✓ Passed
                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="fail-badge"
                                            >
                                                ✕ Failed
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- DATE -->

                                    <td>

                                        <?php if (
                                            !empty(
                                                $mark[
                                                    "exam_date"
                                                ]
                                            )
                                        ): ?>

                                            <span
                                                class="date-text"
                                            >

                                                <?= date(
                                                    "d M Y",
                                                    strtotime(
                                                        $mark[
                                                            "exam_date"
                                                        ]
                                                    )
                                                ) ?>

                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="date-text"
                                            >
                                                —
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- REMARKS -->

                                    <td>

                                        <div
                                            class="remarks"
                                        >

                                            <?php if (
                                                !empty(
                                                    $mark[
                                                        "remarks"
                                                    ]
                                                )
                                            ): ?>

                                                <?= htmlspecialchars(
                                                    $mark[
                                                        "remarks"
                                                    ]
                                                ) ?>

                                            <?php else: ?>

                                                —

                                            <?php endif; ?>

                                        </div>

                                    </td>

                                </tr>


                            <?php endforeach; ?>


                            </tbody>

                        </table>

                    </div>

                </div>


            <?php endforeach; ?>


        <?php else: ?>


            <!-- =================================================
                 EMPTY STATE
                 ================================================= -->

            <div class="empty-card">

                <div class="empty-icon">
                    📊
                </div>


                <h2>
                    No Marks Available
                </h2>


                <p>
                    Your examination marks will appear here
                    once they are added by the administrator.
                </p>

            </div>


        <?php endif; ?>


    <?php endif; ?>


</div>


</body>

</html> 