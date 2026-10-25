<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $semester_number = (int)($_POST["semester_number"] ?? 0);
    $academic_year = trim($_POST["academic_year"] ?? "");

    if ($semester_number <= 0 || $academic_year === "") {
        $error = "Please fill all fields.";
    } else {

        try {
            // Check if semester already exists
            $stmt = $conn->prepare(
                "SELECT id FROM semesters
                 WHERE semester_number = ? AND academic_year = ?"
            );

            $stmt->bind_param(
                "is",
                $semester_number,
                $academic_year
            );

            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {

                $error = "This semester already exists.";

            } else {

                // Insert semester
                $stmt = $conn->prepare(
                    "INSERT INTO semesters
                    (semester_number, academic_year)
                    VALUES (?, ?)"
                );

                $stmt->bind_param(
                    "is",
                    $semester_number,
                    $academic_year
                );

                if ($stmt->execute()) {
                    header("Location: semesters.php?added=1");
                    exit;
                } else {
                    $error = "Failed to add semester.";
                }
            }

        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Semester - Student Academic Tracker</title>

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

        .back-btn:hover {
            background: #f3f4f6;
        }

        .card {
            max-width: 700px;
            background: #ffffff;
            border-radius: 18px;
            padding: 30px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
        }

        .form-group {
            margin-bottom: 22px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        input {
            width: 100%;
            padding: 13px 15px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 15px;
            outline: none;
        }

        input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
        }

        .help {
            margin-top: 7px;
            font-size: 13px;
            color: #6b7280;
        }

        .alert {
            padding: 13px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
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

        .submit-btn:hover {
            opacity: 0.92;
        }
    </style>
</head>

<body>

<?php include "includes/sidebar.php"; ?>

<div class="main-content">

    <div class="page-header">
        <div>
            <h1>Add Semester</h1>
            <p>Create a new academic semester</p>
        </div>

        <a href="semesters.php" class="back-btn">
            ← Back to Semesters
        </a>
    </div>

    <div class="card">

        <?php if ($error): ?>
            <div class="alert error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <div class="form-group">
                <label for="semester_number">
                    Semester Number
                </label>

                <input
                    type="number"
                    id="semester_number"
                    name="semester_number"
                    min="1"
                    max="12"
                    placeholder="Example: 6"
                    required
                >

                <div class="help">
                    Enter the semester number, for example 1, 2, 3, 4, 5 or 6.
                </div>
            </div>

            <div class="form-group">
                <label for="academic_year">
                    Academic Year
                </label>

                <input
                    type="text"
                    id="academic_year"
                    name="academic_year"
                    placeholder="Example: 2026-27"
                    required
                >

                <div class="help">
                    Enter the academic year such as 2026-27.
                </div>
            </div>

            <button type="submit" class="submit-btn">
                + Add Semester
            </button>

        </form>

    </div>

</div>

</body>
</html>