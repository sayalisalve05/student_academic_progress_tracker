<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

$error = "";

// Get mark ID
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: marks.php");
    exit;
}

$mark_id = (int)$_GET["id"];

// Get existing mark
$stmt = $conn->prepare(
    "SELECT
        id,
        student_id,
        subject_id,
        exam_type,
        marks_obtained,
        max_marks,
        exam_date,
        remarks
     FROM marks
     WHERE id = ?"
);

$stmt->bind_param("i", $mark_id);
$stmt->execute();

$result = $stmt->get_result();
$mark = $result->fetch_assoc();

if (!$mark) {
    header("Location: marks.php");
    exit;
}


// Fetch students
$students = [];

$stmt = $conn->prepare(
    "SELECT id, full_name, prn
     FROM students
     ORDER BY full_name ASC"
);

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}


// Fetch subjects
$subjects = [];

$stmt = $conn->prepare(
    "SELECT
        subjects.id,
        subjects.subject_code,
        subjects.subject_name,
        semesters.semester_number,
        semesters.academic_year
     FROM subjects
     LEFT JOIN semesters
        ON subjects.semester_id = semesters.id
     ORDER BY semesters.semester_number ASC,
              subjects.subject_name ASC"
);

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}


// Update marks
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $student_id = (int)($_POST["student_id"] ?? 0);
    $subject_id = (int)($_POST["subject_id"] ?? 0);
    $exam_type = trim($_POST["exam_type"] ?? "");
    $marks_obtained = trim($_POST["marks_obtained"] ?? "");
    $max_marks = trim($_POST["max_marks"] ?? "");
    $exam_date = trim($_POST["exam_date"] ?? "");
    $remarks = trim($_POST["remarks"] ?? "");

    if (
        $student_id <= 0 ||
        $subject_id <= 0 ||
        $exam_type === "" ||
        $marks_obtained === "" ||
        $max_marks === ""
    ) {

        $error = "Please fill all required fields.";

    } elseif (
        !is_numeric($marks_obtained) ||
        !is_numeric($max_marks)
    ) {

        $error = "Marks must be numeric.";

    } elseif (
        (float)$marks_obtained < 0 ||
        (float)$max_marks <= 0
    ) {

        $error = "Please enter valid marks.";

    } elseif (
        (float)$marks_obtained > (float)$max_marks
    ) {

        $error = "Marks obtained cannot be greater than maximum marks.";

    } else {

        try {

            // Check student
            $stmt = $conn->prepare(
                "SELECT id FROM students WHERE id = ?"
            );

            $stmt->bind_param("i", $student_id);
            $stmt->execute();

            if ($stmt->get_result()->num_rows === 0) {
                $error = "Selected student does not exist.";
            }


            // Check subject
            if ($error === "") {

                $stmt = $conn->prepare(
                    "SELECT id FROM subjects WHERE id = ?"
                );

                $stmt->bind_param("i", $subject_id);
                $stmt->execute();

                if ($stmt->get_result()->num_rows === 0) {
                    $error = "Selected subject does not exist.";
                }
            }


            // Update
            if ($error === "") {

                $stmt = $conn->prepare(
                    "UPDATE marks
                     SET
                        student_id = ?,
                        subject_id = ?,
                        exam_type = ?,
                        marks_obtained = ?,
                        max_marks = ?,
                        exam_date = ?,
                        remarks = ?
                     WHERE id = ?"
                );

                $stmt->bind_param(
                    "iisddssi",
                    $student_id,
                    $subject_id,
                    $exam_type,
                    $marks_obtained,
                    $max_marks,
                    $exam_date,
                    $remarks,
                    $mark_id
                );

                if ($stmt->execute()) {

                    header("Location: marks.php?updated=1");
                    exit;

                } else {

                    $error = "Failed to update marks.";
                }
            }

        } catch (Exception $e) {

            $error = "Database error: " . $e->getMessage();
        }
    }

    // Keep entered values if validation fails
    $mark["student_id"] = $student_id;
    $mark["subject_id"] = $subject_id;
    $mark["exam_type"] = $exam_type;
    $mark["marks_obtained"] = $marks_obtained;
    $mark["max_marks"] = $max_marks;
    $mark["exam_date"] = $exam_date;
    $mark["remarks"] = $remarks;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Marks - Student Academic Tracker</title>

    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">

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
            margin-left: 260px !important;
            padding: 35px;
            min-height: 100vh;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
        }

        .page-header p {
            margin: 7px 0 0;
            color: #6b7280;
        }

        .back-btn {
            text-decoration: none;
            background: #ffffff;
            color: #374151;
            padding: 11px 18px;
            border-radius: 10px;
            font-weight: 600;
            border: 1px solid #e5e7eb;
        }

        .card {
            max-width: 850px;
            background: #ffffff;
            border-radius: 18px;
            padding: 30px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }

        .form-group {
            margin-bottom: 4px;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .required {
            color: #dc2626;
        }

        select,
        input,
        textarea {
            width: 100%;
            padding: 13px 15px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 15px;
            font-family: inherit;
            outline: none;
            background: white;
        }

        select:focus,
        input:focus,
        textarea:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        .alert {
            padding: 13px 16px;
            border-radius: 10px;
            margin-bottom: 22px;
            font-weight: 600;
            background: #fee2e2;
            color: #991b1b;
        }

        .form-actions {
            margin-top: 28px;
            display: flex;
            gap: 12px;
        }

        .submit-btn {
            border: none;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            padding: 13px 24px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }

        .cancel-btn {
            text-decoration: none;
            background: #f3f4f6;
            color: #374151;
            padding: 13px 24px;
            border-radius: 10px;
            font-weight: 700;
        }

        @media (max-width: 800px) {

            .main-content {
                margin-left: 0 !important;
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .full-width {
                grid-column: auto;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
                gap: 15px;
            }
        }

    </style>

</head>

<body>

<?php include "includes/sidebar.php"; ?>

<div class="main-content">

    <div class="page-header">

        <div>
            <h1>Edit Marks</h1>
            <p>Update student examination marks</p>
        </div>

        <a href="marks.php" class="back-btn">
            ← Back to Marks
        </a>

    </div>


    <div class="card">

        <?php if ($error): ?>

            <div class="alert">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <form method="POST">

            <div class="form-grid">


                <!-- Student -->

                <div class="form-group">

                    <label for="student_id">
                        Student <span class="required">*</span>
                    </label>

                    <select
                        name="student_id"
                        id="student_id"
                        required
                    >

                        <option value="">
                            Select Student
                        </option>

                        <?php foreach ($students as $student): ?>

                            <option
                                value="<?= $student["id"] ?>"
                                <?= ((int)$mark["student_id"] === (int)$student["id"])
                                    ? "selected"
                                    : "" ?>
                            >

                                <?= htmlspecialchars($student["full_name"]) ?>
                                —
                                <?= htmlspecialchars($student["prn"]) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Subject -->

                <div class="form-group">

                    <label for="subject_id">
                        Subject <span class="required">*</span>
                    </label>

                    <select
                        name="subject_id"
                        id="subject_id"
                        required
                    >

                        <option value="">
                            Select Subject
                        </option>

                        <?php foreach ($subjects as $subject): ?>

                            <option
                                value="<?= $subject["id"] ?>"
                                <?= ((int)$mark["subject_id"] === (int)$subject["id"])
                                    ? "selected"
                                    : "" ?>
                            >

                                <?= htmlspecialchars($subject["subject_code"]) ?>
                                -
                                <?= htmlspecialchars($subject["subject_name"]) ?>

                                <?php if ($subject["semester_number"]): ?>

                                    (Sem <?= htmlspecialchars($subject["semester_number"]) ?>)

                                <?php endif; ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Exam Type -->

                <div class="form-group">

                    <label for="exam_type">
                        Exam Type <span class="required">*</span>
                    </label>

                    <select
                        name="exam_type"
                        id="exam_type"
                        required
                    >

                        <option value="">
                            Select Exam Type
                        </option>

                        <option value="Internal"
                            <?= $mark["exam_type"] === "Internal" ? "selected" : "" ?>>
                            Internal
                        </option>

                        <option value="External"
                            <?= $mark["exam_type"] === "External" ? "selected" : "" ?>>
                            External
                        </option>

                        <option value="Practical"
                            <?= $mark["exam_type"] === "Practical" ? "selected" : "" ?>>
                            Practical
                        </option>

                        <option value="Unit Test"
                            <?= $mark["exam_type"] === "Unit Test" ? "selected" : "" ?>>
                            Unit Test
                        </option>

                        <option value="Assignment"
                            <?= $mark["exam_type"] === "Assignment" ? "selected" : "" ?>>
                            Assignment
                        </option>

                    </select>

                </div>


                <!-- Exam Date -->

                <div class="form-group">

                    <label for="exam_date">
                        Exam Date
                    </label>

                    <input
                        type="date"
                        name="exam_date"
                        id="exam_date"
                        value="<?= htmlspecialchars($mark["exam_date"] ?? "") ?>"
                    >

                </div>


                <!-- Marks Obtained -->

                <div class="form-group">

                    <label for="marks_obtained">
                        Marks Obtained <span class="required">*</span>
                    </label>

                    <input
                        type="number"
                        name="marks_obtained"
                        id="marks_obtained"
                        step="0.01"
                        min="0"
                        value="<?= htmlspecialchars($mark["marks_obtained"]) ?>"
                        required
                    >

                </div>


                <!-- Maximum Marks -->

                <div class="form-group">

                    <label for="max_marks">
                        Maximum Marks <span class="required">*</span>
                    </label>

                    <input
                        type="number"
                        name="max_marks"
                        id="max_marks"
                        step="0.01"
                        min="1"
                        value="<?= htmlspecialchars($mark["max_marks"]) ?>"
                        required
                    >

                </div>


                <!-- Remarks -->

                <div class="form-group full-width">

                    <label for="remarks">
                        Remarks
                    </label>

                    <textarea
                        name="remarks"
                        id="remarks"
                        placeholder="Enter remarks..."
                    ><?= htmlspecialchars($mark["remarks"] ?? "") ?></textarea>

                </div>

            </div>


            <div class="form-actions">

                <button
                    type="submit"
                    class="submit-btn"
                >
                    Save Changes
                </button>

                <a
                    href="marks.php"
                    class="cancel-btn"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

</body>

</html>