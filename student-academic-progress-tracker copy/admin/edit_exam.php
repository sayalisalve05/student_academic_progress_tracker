<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: exams.php");
    exit;
}

$exam_id = (int)$_GET["id"];

$errors = [];

/* Fetch exam */
$stmt = $conn->prepare("
    SELECT
        id,
        subject_id,
        semester_id,
        exam_name,
        exam_type,
        exam_date,
        start_time,
        end_time,
        room_no,
        description
    FROM exams
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $exam_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    die("Exam not found.");
}

$exam = $result->fetch_assoc();
$stmt->close();

/* Default values */
$subject_id = $exam["subject_id"];
$semester_id = $exam["semester_id"];
$exam_name = $exam["exam_name"];
$exam_type = $exam["exam_type"];
$exam_date = $exam["exam_date"];
$start_time = $exam["start_time"] ?? "";
$end_time = $exam["end_time"] ?? "";
$room_no = $exam["room_no"] ?? "";
$description = $exam["description"] ?? "";

/* Fetch subjects */
$subjects = [];

$result = $conn->query("
    SELECT
        s.id,
        s.subject_code,
        s.subject_name,
        s.semester_id,
        se.semester_number,
        se.academic_year
    FROM subjects s
    LEFT JOIN semesters se ON s.semester_id = se.id
    ORDER BY se.semester_number, s.subject_code
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
}

/* Fetch semesters */
$semesters = [];

$result = $conn->query("
    SELECT
        id,
        semester_number,
        academic_year
    FROM semesters
    ORDER BY semester_number
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $semesters[] = $row;
    }
}

/* Form submit */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $subject_id = (int)($_POST["subject_id"] ?? 0);
    $semester_id = (int)($_POST["semester_id"] ?? 0);

    $exam_name = trim($_POST["exam_name"] ?? "");
    $exam_type = trim($_POST["exam_type"] ?? "");
    $exam_date = trim($_POST["exam_date"] ?? "");
    $start_time = trim($_POST["start_time"] ?? "");
    $end_time = trim($_POST["end_time"] ?? "");
    $room_no = trim($_POST["room_no"] ?? "");
    $description = trim($_POST["description"] ?? "");

    /* Validation */

    if ($subject_id <= 0) {
        $errors[] = "Please select a subject.";
    }

    if ($semester_id <= 0) {
        $errors[] = "Please select a semester.";
    }

    if ($exam_name === "") {
        $errors[] = "Exam name is required.";
    }

    if ($exam_type === "") {
        $errors[] = "Exam type is required.";
    }

    if ($exam_date === "") {
        $errors[] = "Exam date is required.";
    }

    if ($start_time !== "" && $end_time !== "") {
        if ($end_time <= $start_time) {
            $errors[] = "End time must be later than start time.";
        }
    }

    /* Check subject */
    if (empty($errors)) {

        $stmt = $conn->prepare("
            SELECT id
            FROM subjects
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->bind_param("i", $subject_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $errors[] = "Selected subject does not exist.";
        }

        $stmt->close();
    }

    /* Check semester */
    if (empty($errors)) {

        $stmt = $conn->prepare("
            SELECT id
            FROM semesters
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->bind_param("i", $semester_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $errors[] = "Selected semester does not exist.";
        }

        $stmt->close();
    }

    /* Update exam */
    if (empty($errors)) {

        try {

            $stmt = $conn->prepare("
                UPDATE exams
                SET
                    subject_id = ?,
                    semester_id = ?,
                    exam_name = ?,
                    exam_type = ?,
                    exam_date = ?,
                    start_time = NULLIF(?, ''),
                    end_time = NULLIF(?, ''),
                    room_no = NULLIF(?, ''),
                    description = NULLIF(?, '')
                WHERE id = ?
            ");

            $stmt->bind_param(
                "iisssssssi",
                $subject_id,
                $semester_id,
                $exam_name,
                $exam_type,
                $exam_date,
                $start_time,
                $end_time,
                $room_no,
                $description,
                $exam_id
            );

            $stmt->execute();
            $stmt->close();

            header("Location: exams.php?updated=1");
            exit;

        } catch (Exception $e) {
            $errors[] = "Error updating exam: " . $e->getMessage();
        }
    }
}
?>

<?php include "includes/sidebar.php"; ?>

<style>

body {
    background: #f5f6fa;
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
    margin-bottom: 25px;
}

.page-header h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
    color: #1f2937;
}

.page-header p {
    margin: 7px 0 0;
    color: #6b7280;
    font-size: 14px;
}

.back-btn {
    text-decoration: none;
    background: #374151;
    color: white;
    padding: 11px 18px;
    border-radius: 9px;
    font-size: 14px;
    font-weight: 600;
}

.back-btn:hover {
    background: #1f2937;
}

.form-card {
    background: white;
    border-radius: 14px;
    padding: 30px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.06);
    max-width: 1000px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group.full {
    grid-column: 1 / -1;
}

.form-group label {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
}

.form-group label span {
    color: #ef4444;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid #d1d5db;
    border-radius: 9px;
    font-size: 14px;
    outline: none;
    background: white;
    color: #1f2937;
    font-family: inherit;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: #7c3aed;
    box-shadow: 0 0 0 3px rgba(124,58,237,0.10);
}

.form-group textarea {
    min-height: 110px;
    resize: vertical;
}

.error-box {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #b91c1c;
    padding: 14px 18px;
    border-radius: 9px;
    margin-bottom: 22px;
}

.error-box ul {
    margin: 0;
    padding-left: 20px;
}

.form-actions {
    margin-top: 28px;
    display: flex;
    gap: 12px;
}

.update-btn {
    border: none;
    background: #7c3aed;
    color: white;
    padding: 12px 24px;
    border-radius: 9px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
}

.update-btn:hover {
    background: #6d28d9;
}

.cancel-btn {
    text-decoration: none;
    background: #e5e7eb;
    color: #374151;
    padding: 12px 24px;
    border-radius: 9px;
    font-size: 14px;
    font-weight: 600;
}

.cancel-btn:hover {
    background: #d1d5db;
}

@media (max-width: 800px) {

    .main-content {
        margin-left: 0 !important;
        padding: 20px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-group.full {
        grid-column: auto;
    }

    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
}

</style>

<div class="main-content">

    <div class="page-header">

        <div>
            <h1>Edit Exam</h1>
            <p>Update examination schedule details</p>
        </div>

        <a href="exams.php" class="back-btn">
            ← Back to Exams
        </a>

    </div>

    <div class="form-card">

        <?php if (!empty($errors)): ?>

            <div class="error-box">

                <ul>

                    <?php foreach ($errors as $error): ?>

                        <li><?= htmlspecialchars($error) ?></li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <form method="POST">

            <div class="form-grid">

                <!-- Subject -->

                <div class="form-group">

                    <label>
                        Subject <span>*</span>
                    </label>

                    <select name="subject_id" required>

                        <option value="">
                            Select Subject
                        </option>

                        <?php foreach ($subjects as $subject): ?>

                            <option
                                value="<?= (int)$subject["id"] ?>"
                                <?= ($subject_id == $subject["id"]) ? "selected" : "" ?>
                            >

                                <?= htmlspecialchars($subject["subject_code"]) ?>
                                -
                                <?= htmlspecialchars($subject["subject_name"]) ?>

                                <?php if ($subject["semester_number"] !== null): ?>

                                    (Sem <?= htmlspecialchars($subject["semester_number"]) ?>)

                                <?php endif; ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Semester -->

                <div class="form-group">

                    <label>
                        Semester <span>*</span>
                    </label>

                    <select name="semester_id" required>

                        <option value="">
                            Select Semester
                        </option>

                        <?php foreach ($semesters as $semester): ?>

                            <option
                                value="<?= (int)$semester["id"] ?>"
                                <?= ($semester_id == $semester["id"]) ? "selected" : "" ?>
                            >

                                Semester
                                <?= htmlspecialchars($semester["semester_number"]) ?>

                                -
                                <?= htmlspecialchars($semester["academic_year"]) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Exam Name -->

                <div class="form-group">

                    <label>
                        Exam Name <span>*</span>
                    </label>

                    <input
                        type="text"
                        name="exam_name"
                        value="<?= htmlspecialchars($exam_name) ?>"
                        placeholder="Example: Semester 5 End Semester Exam"
                        required
                    >

                </div>


                <!-- Exam Type -->

                <div class="form-group">

                    <label>
                        Exam Type <span>*</span>
                    </label>

                    <select name="exam_type" required>

                        <option value="">
                            Select Exam Type
                        </option>

                        <?php

                        $exam_types = [
                            "Internal",
                            "External",
                            "Practical",
                            "Unit Test",
                            "Assignment",
                            "Viva",
                            "Other"
                        ];

                        ?>

                        <?php foreach ($exam_types as $type): ?>

                            <option
                                value="<?= htmlspecialchars($type) ?>"
                                <?= ($exam_type === $type) ? "selected" : "" ?>
                            >

                                <?= htmlspecialchars($type) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Exam Date -->

                <div class="form-group">

                    <label>
                        Exam Date <span>*</span>
                    </label>

                    <input
                        type="date"
                        name="exam_date"
                        value="<?= htmlspecialchars($exam_date) ?>"
                        required
                    >

                </div>


                <!-- Room -->

                <div class="form-group">

                    <label>
                        Room No.
                    </label>

                    <input
                        type="text"
                        name="room_no"
                        value="<?= htmlspecialchars($room_no) ?>"
                        placeholder="Example: Room 204"
                    >

                </div>


                <!-- Start Time -->

                <div class="form-group">

                    <label>
                        Start Time
                    </label>

                    <input
                        type="time"
                        name="start_time"
                        value="<?= htmlspecialchars($start_time) ?>"
                    >

                </div>


                <!-- End Time -->

                <div class="form-group">

                    <label>
                        End Time
                    </label>

                    <input
                        type="time"
                        name="end_time"
                        value="<?= htmlspecialchars($end_time) ?>"
                    >

                </div>


                <!-- Description -->

                <div class="form-group full">

                    <label>
                        Description
                    </label>

                    <textarea
                        name="description"
                        placeholder="Enter any additional exam instructions or details..."
                    ><?= htmlspecialchars($description) ?></textarea>

                </div>

            </div>


            <div class="form-actions">

                <button type="submit" class="update-btn">
                    ✓ Update Exam
                </button>

                <a href="exams.php" class="cancel-btn">
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>