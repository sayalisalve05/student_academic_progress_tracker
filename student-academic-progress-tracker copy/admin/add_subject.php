<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

$success = "";
$error = "";

// Fetch semesters
$semester_result = $conn->query("
    SELECT id, semester_number, academic_year
    FROM semesters
    ORDER BY semester_number ASC
");

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $subject_code = trim($_POST["subject_code"] ?? "");
    $subject_name = trim($_POST["subject_name"] ?? "");
    $semester_id = (int) ($_POST["semester_id"] ?? 0);
    $credits = (int) ($_POST["credits"] ?? 0);

    if ($subject_code === "" || $subject_name === "" || $semester_id <= 0 || $credits <= 0) {

        $error = "Please fill all required fields correctly.";

    } else {

        // Check duplicate subject code
        $stmt = $conn->prepare("
            SELECT id 
            FROM subjects 
            WHERE subject_code = ?
        ");

        $stmt->bind_param("s", $subject_code);
        $stmt->execute();

        $duplicate = $stmt->get_result();

        if ($duplicate->num_rows > 0) {

            $error = "This subject code already exists.";

        } else {

            // Insert subject
            $stmt = $conn->prepare("
                INSERT INTO subjects
                (
                    subject_code,
                    subject_name,
                    semester_id,
                    credits
                )
                VALUES (?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "ssii",
                $subject_code,
                $subject_name,
                $semester_id,
                $credits
            );

            if ($stmt->execute()) {

                $success = "Subject added successfully.";

                // Clear form
                $subject_code = "";
                $subject_name = "";
                $semester_id = 0;
                $credits = 0;

            } else {

                $error = "Error adding subject: " . $stmt->error;
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Subject | Student Academic Tracker</title>

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

        <h1>Add Subject</h1>

        <p>
            Add a new academic subject to the tracker.
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
                        placeholder="Example: CS301"
                        value="<?php echo htmlspecialchars($subject_code ?? ''); ?>"
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
                        placeholder="Example: 4"
                        value="<?php echo $credits > 0 ? htmlspecialchars($credits) : ''; ?>"
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
                        placeholder="Example: Data Structures"
                        value="<?php echo htmlspecialchars($subject_name ?? ''); ?>"
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
                                    if (
                                        isset($semester_id) &&
                                        $semester_id == $semester['id']
                                    ) {
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
                    Add Subject
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