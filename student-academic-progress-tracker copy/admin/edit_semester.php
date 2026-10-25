<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

$error = "";

// Get semester ID
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: semesters.php");
    exit;
}

$semester_id = (int)$_GET["id"];

// Get existing semester
$stmt = $conn->prepare(
    "SELECT id, semester_number, academic_year
     FROM semesters
     WHERE id = ?"
);

$stmt->bind_param("i", $semester_id);
$stmt->execute();

$result = $stmt->get_result();
$semester = $result->fetch_assoc();

if (!$semester) {
    header("Location: semesters.php");
    exit;
}

// Update semester
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $semester_number = (int)($_POST["semester_number"] ?? 0);
    $academic_year = trim($_POST["academic_year"] ?? "");

    if ($semester_number <= 0 || $academic_year === "") {

        $error = "Please fill all fields.";

    } else {

        try {

            // Check duplicate semester
            $stmt = $conn->prepare(
                "SELECT id FROM semesters
                 WHERE semester_number = ?
                 AND academic_year = ?
                 AND id != ?"
            );

            $stmt->bind_param(
                "isi",
                $semester_number,
                $academic_year,
                $semester_id
            );

            $stmt->execute();

            $check = $stmt->get_result();

            if ($check->num_rows > 0) {

                $error = "This semester already exists.";

            } else {

                // Update semester
                $stmt = $conn->prepare(
                    "UPDATE semesters
                     SET semester_number = ?,
                         academic_year = ?
                     WHERE id = ?"
                );

                $stmt->bind_param(
                    "isi",
                    $semester_number,
                    $academic_year,
                    $semester_id
                );

                if ($stmt->execute()) {

                    header("Location: semesters.php?updated=1");
                    exit;

                } else {

                    $error = "Failed to update semester.";
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

    <title>Edit Semester - Student Academic Tracker</title>

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
            <h1>Edit Semester</h1>
            <p>Update semester information</p>
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
                    value="<?= htmlspecialchars($semester['semester_number']) ?>"
                    required
                >

                <div class="help">
                    Enter the semester number, for example 1 to 6.
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
                    value="<?= htmlspecialchars($semester['academic_year']) ?>"
                    placeholder="Example: 2026-27"
                    required
                >

                <div class="help">
                    Example: 2026-27
                </div>

            </div>

            <button type="submit" class="submit-btn">
                Save Changes
            </button>

        </form>

    </div>

</div>

</body>

</html>