<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

$success = "";
$error = "";

$students = [];
$subjects = [];

try {
    $result = $conn->query("
        SELECT id, full_name, prn
        FROM students
        ORDER BY full_name ASC
    ");

    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }

    $result = $conn->query("
        SELECT
            subjects.id,
            subjects.subject_code,
            subjects.subject_name,
            semesters.semester_number,
            semesters.academic_year
        FROM subjects
        LEFT JOIN semesters
            ON subjects.semester_id = semesters.id
        ORDER BY subjects.subject_code ASC
    ");

    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }

} catch (Exception $e) {
    $error = "Error loading data: " . $e->getMessage();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $student_id = (int)($_POST["student_id"] ?? 0);
    $subject_id = (int)($_POST["subject_id"] ?? 0);
    $total_classes = (int)($_POST["total_classes"] ?? 0);
    $attended_classes = (int)($_POST["attended_classes"] ?? 0);
    $attendance_date = trim($_POST["attendance_date"] ?? "");
    $remarks = trim($_POST["remarks"] ?? "");

    if ($student_id <= 0) {
        $error = "Please select a student.";
    } elseif ($subject_id <= 0) {
        $error = "Please select a subject.";
    } elseif ($total_classes < 0) {
        $error = "Total classes cannot be negative.";
    } elseif ($attended_classes < 0) {
        $error = "Attended classes cannot be negative.";
    } elseif ($attended_classes > $total_classes) {
        $error = "Attended classes cannot be greater than total classes.";
    } else {

        try {

            $stmt = $conn->prepare("
                INSERT INTO attendance
                (
                    student_id,
                    subject_id,
                    total_classes,
                    attended_classes,
                    attendance_date,
                    remarks
                )
                VALUES (?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''))
            ");

            $stmt->bind_param(
                "iiiiss",
                $student_id,
                $subject_id,
                $total_classes,
                $attended_classes,
                $attendance_date,
                $remarks
            );

            $stmt->execute();

            header("Location: attendance.php?added=1");
            exit;

        } catch (Exception $e) {
            $error = "Error adding attendance: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Attendance | Student Academic Tracker</title>

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
            margin-bottom: 25px;
        }

        .page-header h1 {
            margin: 0 0 7px;
            font-size: 30px;
            font-weight: 800;
        }

        .page-header p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }

        .form-card {
            max-width: 850px;
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        label {
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 700;
            color: #374151;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #dfe3ea;
            border-radius: 9px;
            font-size: 14px;
            outline: none;
            background: white;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124,58,237,0.08);
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .hint {
            margin-top: 6px;
            color: #9ca3af;
            font-size: 12px;
        }

        .error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 13px 15px;
            border-radius: 9px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .button-row {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .save-btn {
            border: none;
            background: linear-gradient(135deg, #6d5dfc, #8b5cf6);
            color: white;
            padding: 13px 25px;
            border-radius: 9px;
            font-weight: 700;
            cursor: pointer;
        }

        .cancel-btn {
            text-decoration: none;
            background: #f3f4f6;
            color: #374151;
            padding: 13px 22px;
            border-radius: 9px;
            font-weight: 600;
        }

        @media (max-width: 900px) {
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
        }
    </style>
</head>

<body>

<?php include "includes/sidebar.php"; ?>

<div class="main-content">

    <div class="page-header">
        <h1>Add Attendance</h1>
        <p>Add attendance information for a student and subject.</p>
    </div>

    <div class="form-card">

        <?php if ($error !== ""): ?>
            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <div class="form-grid">

                <div class="form-group">
                    <label for="student_id">Student *</label>

                    <select name="student_id" id="student_id" required>
                        <option value="">Select Student</option>

                        <?php foreach ($students as $student): ?>

                            <option
                                value="<?= (int)$student['id'] ?>"
                                <?= ((int)($_POST['student_id'] ?? 0) === (int)$student['id']) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($student['full_name']) ?>
                                — <?= htmlspecialchars($student['prn']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label for="subject_id">Subject *</label>

                    <select name="subject_id" id="subject_id" required>
                        <option value="">Select Subject</option>

                        <?php foreach ($subjects as $subject): ?>

                            <option
                                value="<?= (int)$subject['id'] ?>"
                                <?= ((int)($_POST['subject_id'] ?? 0) === (int)$subject['id']) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($subject['subject_code']) ?>
                                — <?= htmlspecialchars($subject['subject_name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label for="total_classes">Total Classes *</label>

                    <input
                        type="number"
                        name="total_classes"
                        id="total_classes"
                        min="0"
                        value="<?= htmlspecialchars($_POST['total_classes'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="attended_classes">Attended Classes *</label>

                    <input
                        type="number"
                        name="attended_classes"
                        id="attended_classes"
                        min="0"
                        value="<?= htmlspecialchars($_POST['attended_classes'] ?? '') ?>"
                        required
                    >

                    <div class="hint">
                        Must not be greater than total classes.
                    </div>
                </div>

                <div class="form-group">
                    <label for="attendance_date">Attendance Date</label>

                    <input
                        type="date"
                        name="attendance_date"
                        id="attendance_date"
                        value="<?= htmlspecialchars($_POST['attendance_date'] ?? '') ?>"
                    >
                </div>

                <div class="form-group full-width">
                    <label for="remarks">Remarks</label>

                    <textarea
                        name="remarks"
                        id="remarks"
                        placeholder="Enter any remarks..."
                    ><?= htmlspecialchars($_POST['remarks'] ?? '') ?></textarea>
                </div>

            </div>

            <div class="button-row">

                <button type="submit" class="save-btn">
                    Save Attendance
                </button>

                <a href="attendance.php" class="cancel-btn">
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

</body>
</html>