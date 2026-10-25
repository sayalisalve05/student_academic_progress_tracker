<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

$error = "";

/* Check ID */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: backlogs.php");
    exit;
}

$backlog_id = (int)$_GET['id'];

/* Fetch backlog */
$stmt = $conn->prepare("
    SELECT
        id,
        student_id,
        subject_id,
        status,
        attempts,
        remark,
        cleared_date
    FROM backlogs
    WHERE id = ?
");

$stmt->bind_param("i", $backlog_id);
$stmt->execute();

$result = $stmt->get_result();
$backlog = $result->fetch_assoc();

$stmt->close();

if (!$backlog) {
    header("Location: backlogs.php");
    exit;
}


/* Fetch students */
$students = $conn->query("
    SELECT id, full_name, prn
    FROM students
    ORDER BY full_name ASC
");


/* Fetch subjects */
$subjects = $conn->query("
    SELECT
        sub.id,
        sub.subject_code,
        sub.subject_name,
        sem.semester_number
    FROM subjects sub
    INNER JOIN semesters sem
        ON sub.semester_id = sem.id
    ORDER BY sem.semester_number ASC, sub.subject_code ASC
");


/* Update */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $student_id = (int)($_POST["student_id"] ?? 0);
    $subject_id = (int)($_POST["subject_id"] ?? 0);
    $status = $_POST["status"] ?? "Pending";
    $attempts = (int)($_POST["attempts"] ?? 1);
    $remark = trim($_POST["remark"] ?? "");
    $cleared_date = $_POST["cleared_date"] ?? "";


    /* Validation */

    if ($student_id <= 0) {

        $error = "Please select a student.";

    } elseif ($subject_id <= 0) {

        $error = "Please select a subject.";

    } elseif (!in_array($status, ["Pending", "Cleared"], true)) {

        $error = "Invalid backlog status.";

    } elseif ($attempts < 1) {

        $error = "Attempts must be at least 1.";

    } elseif ($status === "Cleared" && $cleared_date === "") {

        $error = "Please select the cleared date.";

    } else {

        /* Check duplicate */

        $check = $conn->prepare("
            SELECT id
            FROM backlogs
            WHERE student_id = ?
            AND subject_id = ?
            AND id != ?
        ");

        $check->bind_param(
            "iii",
            $student_id,
            $subject_id,
            $backlog_id
        );

        $check->execute();

        $existing = $check->get_result();

        if ($existing->num_rows > 0) {

            $error = "A backlog for this student and subject already exists.";

        } else {

            /* Update */

            $stmt = $conn->prepare("
                UPDATE backlogs
                SET
                    student_id = ?,
                    subject_id = ?,
                    status = ?,
                    attempts = ?,
                    remark = NULLIF(?, ''),
                    cleared_date = NULLIF(?, '')
                WHERE id = ?
            ");

            $stmt->bind_param(
                "iisissi",
                $student_id,
                $subject_id,
                $status,
                $attempts,
                $remark,
                $cleared_date,
                $backlog_id
            );

            if ($stmt->execute()) {

                $stmt->close();
                $check->close();

                header("Location: backlogs.php?updated=1");
                exit;

            } else {

                $error = "Unable to update backlog: " . $stmt->error;

            }

            $stmt->close();
        }

        $check->close();
    }

    /* Keep entered values if validation fails */

    $backlog["student_id"] = $student_id;
    $backlog["subject_id"] = $subject_id;
    $backlog["status"] = $status;
    $backlog["attempts"] = $attempts;
    $backlog["remark"] = $remark;
    $backlog["cleared_date"] = $cleared_date;
}

?>

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
    margin-left: 260px !important;
    padding: 40px 42px;
    min-height: 100vh;
}


/* Header */

.page-header {
    margin-bottom: 26px;
}

.page-header h1 {
    margin: 0;
    font-size: 32px;
    font-weight: 700;
    color: #1f2937;
}

.page-header p {
    margin: 7px 0 0;
    color: #7b8494;
    font-size: 14px;
}


/* Card */

.card {
    background: #ffffff;
    border-radius: 14px;
    padding: 28px;
    max-width: 850px;
    box-shadow: 0 3px 15px rgba(15, 23, 42, 0.06);
}


/* Error */

.alert {
    background: #fee2e2;
    color: #b91c1c;
    padding: 13px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
}


/* Form */

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
}

.form-group select,
.form-group input,
.form-group textarea {
    width: 100%;
    border: 1px solid #d9dee7;
    border-radius: 8px;
    padding: 11px 13px;
    font-size: 13px;
    font-family: inherit;
    outline: none;
    background: #ffffff;
}

.form-group select:focus,
.form-group input:focus,
.form-group textarea:focus {
    border-color: #7c3aed;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.08);
}

.form-group textarea {
    min-height: 100px;
    resize: vertical;
}

.required {
    color: #ef4444;
}


/* Two columns */

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}


/* Buttons */

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 26px;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    border: none;
    cursor: pointer;
    font-weight: 600;
    border-radius: 8px;
    padding: 11px 19px;
    font-size: 14px;
}

.btn-primary {
    background: #6d28d9;
    color: white;
}

.btn-primary:hover {
    background: #5b21b6;
}

.btn-secondary {
    background: #e5e7eb;
    color: #374151;
}

.btn-secondary:hover {
    background: #d1d5db;
}


/* Responsive */

@media (max-width: 900px) {

    .main-content {
        margin-left: 0 !important;
        padding: 25px 20px;
    }

    .form-row {
        grid-template-columns: 1fr;
    }

}

</style>


<div class="main-content">

    <div class="page-header">

        <h1>Edit Backlog</h1>

        <p>
            Update the student backlog record.
        </p>

    </div>


    <?php if ($error !== ""): ?>

        <div class="alert">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <div class="card">

        <form method="POST">


            <!-- Student -->

            <div class="form-group">

                <label>
                    Student <span class="required">*</span>
                </label>

                <select name="student_id" required>

                    <option value="">
                        Select Student
                    </option>

                    <?php while ($student = $students->fetch_assoc()): ?>

                        <option
                            value="<?= (int)$student['id'] ?>"
                            <?= ((int)$backlog['student_id'] === (int)$student['id']) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($student['full_name']) ?>
                            — <?= htmlspecialchars($student['prn']) ?>
                        </option>

                    <?php endwhile; ?>

                </select>

            </div>


            <!-- Subject -->

            <div class="form-group">

                <label>
                    Subject <span class="required">*</span>
                </label>

                <select name="subject_id" required>

                    <option value="">
                        Select Subject
                    </option>

                    <?php while ($subject = $subjects->fetch_assoc()): ?>

                        <option
                            value="<?= (int)$subject['id'] ?>"
                            <?= ((int)$backlog['subject_id'] === (int)$subject['id']) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($subject['subject_code']) ?>
                            —
                            <?= htmlspecialchars($subject['subject_name']) ?>
                            (Semester <?= (int)$subject['semester_number'] ?>)
                        </option>

                    <?php endwhile; ?>

                </select>

            </div>


            <!-- Status + Attempts -->

            <div class="form-row">

                <div class="form-group">

                    <label>
                        Status <span class="required">*</span>
                    </label>

                    <select name="status" id="status" required>

                        <option value="Pending"
                            <?= $backlog['status'] === 'Pending' ? 'selected' : '' ?>>
                            Pending
                        </option>

                        <option value="Cleared"
                            <?= $backlog['status'] === 'Cleared' ? 'selected' : '' ?>>
                            Cleared
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Attempts <span class="required">*</span>
                    </label>

                    <input
                        type="number"
                        name="attempts"
                        min="1"
                        value="<?= (int)$backlog['attempts'] ?>"
                        required
                    >

                </div>

            </div>


            <!-- Cleared Date -->

            <div class="form-group">

                <label>
                    Cleared Date
                </label>

                <input
                    type="date"
                    name="cleared_date"
                    id="cleared_date"
                    value="<?= htmlspecialchars($backlog['cleared_date'] ?? '') ?>"
                >

            </div>


            <!-- Remark -->

            <div class="form-group">

                <label>
                    Remark
                </label>

                <textarea
                    name="remark"
                    placeholder="Enter any remark about this backlog..."
                ><?= htmlspecialchars($backlog['remark'] ?? '') ?></textarea>

            </div>


            <!-- Buttons -->

            <div class="form-actions">

                <button type="submit" class="btn btn-primary">
                    Update Backlog
                </button>

                <a href="backlogs.php" class="btn btn-secondary">
                    Cancel
                </a>

            </div>


        </form>

    </div>

</div>


<script>

const statusSelect = document.getElementById("status");
const clearedDate = document.getElementById("cleared_date");

function updateClearedDate() {

    if (statusSelect.value === "Cleared") {
        clearedDate.required = true;
    } else {
        clearedDate.required = false;
    }

}

statusSelect.addEventListener("change", updateClearedDate);

updateClearedDate();

</script>