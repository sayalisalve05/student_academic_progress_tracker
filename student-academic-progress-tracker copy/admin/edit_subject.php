<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: subjects.php");
    exit;
}

$subject_id = (int) $_GET['id'];

$success = "";
$error = "";

// Get subject details
$stmt = $conn->prepare("
    SELECT 
        id,
        subject_code,
        subject_name,
        semester_id,
        credits
    FROM subjects
    WHERE id = ?
");

$stmt->bind_param("i", $subject_id);
$stmt->execute();

$result = $stmt->get_result();
$subject = $result->fetch_assoc();

if (!$subject) {
    die("Subject not found.");
}

// Current values
$subject_code = $subject['subject_code'];
$subject_name = $subject['subject_name'];
$semester_id = $subject['semester_id'];
$credits = $subject['credits'];

// Handle update
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $subject_code = trim($_POST["subject_code"] ?? "");
    $subject_name = trim($_POST["subject_name"] ?? "");
    $semester_id = (int) ($_POST["semester_id"] ?? 0);
    $credits = (int) ($_POST["credits"] ?? 0);

    if (
        $subject_code === "" ||
        $subject_name === "" ||
        $semester_id <= 0 ||
        $credits <= 0
    ) {

        $error = "Please fill all required fields correctly.";

    } else {

        // Check duplicate subject code
        $stmt = $conn->prepare("
            SELECT id
            FROM subjects
            WHERE subject_code = ?
            AND id != ?
        ");

        $stmt->bind_param("si", $subject_code, $subject_id);
        $stmt->execute();

        $duplicate = $stmt->get_result();

        if ($duplicate->num_rows > 0) {

            $error = "This subject code already exists.";

        } else {

            // Update subject
            $stmt = $conn->prepare("
                UPDATE subjects
                SET
                    subject_code = ?,
                    subject_name = ?,
                    semester_id = ?,
                    credits = ?
                WHERE id = ?
            ");

            $stmt->bind_param(
                "ssiii",
                $subject_code,
                $subject_name,
                $semester_id,
                $credits,
                $subject_id
            );

            if ($stmt->execute()) {

                $success = "Subject updated successfully.";

            } else {

                $error = "Error updating subject: " . $stmt->error;
            }
        }
    }
}

// Fetch semesters
$semester_result = $conn->query("
    SELECT id, semester_number, academic_year
    FROM semesters
    ORDER BY semester_number ASC
");

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Subject | Student Academic Tracker</title>

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
            padding: 35px;
            min-height: 100vh;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h1 {
            margin: 0;
            font-size: 30px;
        }

        .page-header p {
            margin-top: 7px;
            color: #6b7280;
            font-size: 14px;
        }

        .card {
            background: white;
            max-width: 800px;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.06);
        }

        .alert {
            padding: 13px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
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

        .full {
            grid-column: 1 / -1;
        }

        label {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        label span {
            color: #dc2626;
        }

        input,
        select {
            width: 100%;
            padding: 12px 13px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            background: white;
        }

        input:focus,
        select:focus {
            border-color: #7c3aed;
        }

        .buttons {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .save-btn {
            border: none;
            background: #6d28d9;
            color: white;
            padding: 12px 22px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .save-btn:hover {
            background: #5b21b6;
        }

        .cancel-btn {
            display: inline-flex;
            align-items: center;
            text-decoration: none;
            background: #e5e7eb;
            color: #374151;
            padding: 12px 22px;
            border-radius: 8px;
            font-weight: 600;
        }

        .cancel-btn:hover {
            background: #d1d5db;
        }

        @media (max-width: 900px) {

            .main-content {
                margin-left: 0 !important;
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .full {
                grid-column: auto;
            }

        }

    </style>

</head>

<body>

<?php include "includes/sidebar.php"; ?>

<div class="main-content">

    <div class="page-header">

        <h1>Edit Subject</h1>

        <p>
            Update academic subject information.
        </p>

    </div>

    <div class="card">

        <?php if ($success !== ""): ?>

            <div class="alert success">
                <?php echo htmlspecialchars($success); ?>
            </div>

        <?php endif; ?>

        <?php if ($error !== ""): ?>

            <div class="alert error">
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <div class="form-grid">

                <div class="form-group">

                    <label>
                        Subject Code <span>*</span>
                    </label>

                    <input
                        type="text"
                        name="subject_code"
                        value="<?php echo htmlspecialchars($subject_code); ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>
                        Credits <span>*</span>
                    </label>

                    <input
                        type="number"
                        name="credits"
                        min="1"
                        max="20"
                        value="<?php echo htmlspecialchars($credits); ?>"
                        required
                    >

                </div>

                <div class="form-group full">

                    <label>
                        Subject Name <span>*</span>
                    </label>

                    <input
                        type="text"
                        name="subject_name"
                        value="<?php echo htmlspecialchars($subject_name); ?>"
                        required
                    >

                </div>

                <div class="form-group full">

                    <label>
                        Semester <span>*</span>
                    </label>

                    <select name="semester_id" required>

                        <option value="">
                            -- Select Semester --
                        </option>

                        <?php if ($semester_result && $semester_result->num_rows > 0): ?>

                            <?php while ($semester = $semester_result->fetch_assoc()): ?>

                                <option
                                    value="<?php echo $semester['id']; ?>"
                                    <?php
                                    if ($semester_id == $semester['id']) {
                                        echo "selected";
                                    }
                                    ?>
                                >

                                    Semester
                                    <?php echo htmlspecialchars($semester['semester_number']); ?>

                                    <?php if (!empty($semester['academic_year'])): ?>

                                        -
                                        <?php echo htmlspecialchars($semester['academic_year']); ?>

                                    <?php endif; ?>

                                </option>

                            <?php endwhile; ?>

                        <?php endif; ?>

                    </select>

                </div>

            </div>

            <div class="buttons">

                <button type="submit" class="save-btn">
                    Save Changes
                </button>

                <a href="subjects.php" class="cancel-btn">
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

</body>
</html>