<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

$errors = [];

$title = "";
$message = "";
$notification_type = "General";
$student_id = "";

/* Fetch students */
$students = [];

$result = $conn->query("
    SELECT id, prn, full_name
    FROM students
    ORDER BY full_name
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
}

/* Submit form */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $message = trim($_POST["message"] ?? "");
    $notification_type = trim($_POST["notification_type"] ?? "General");
    $student_id = trim($_POST["student_id"] ?? "");

    /* Validation */

    if ($title === "") {
        $errors[] = "Notification title is required.";
    }

    if ($message === "") {
        $errors[] = "Notification message is required.";
    }

    $allowed_types = [
        "General",
        "Exam",
        "Marks",
        "Attendance",
        "Backlog"
    ];

    if (!in_array($notification_type, $allowed_types, true)) {
        $errors[] = "Invalid notification type.";
    }

    /* Validate selected student */
    if ($student_id !== "" && is_numeric($student_id)) {

        $student_id_int = (int)$student_id;

        $stmt = $conn->prepare("
            SELECT id
            FROM students
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->bind_param("i", $student_id_int);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $errors[] = "Selected student does not exist.";
        }

        $stmt->close();

    } elseif ($student_id !== "") {

        $errors[] = "Invalid student selected.";
    }


    /* Insert notification */

    if (empty($errors)) {

        try {

            if ($student_id === "") {

                $stmt = $conn->prepare("
                    INSERT INTO notifications
                    (
                        title,
                        message,
                        notification_type,
                        student_id
                    )
                    VALUES (?, ?, ?, NULL)
                ");

                $stmt->bind_param(
                    "sss",
                    $title,
                    $message,
                    $notification_type
                );

            } else {

                $student_id_int = (int)$student_id;

                $stmt = $conn->prepare("
                    INSERT INTO notifications
                    (
                        title,
                        message,
                        notification_type,
                        student_id
                    )
                    VALUES (?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "sssi",
                    $title,
                    $message,
                    $notification_type,
                    $student_id_int
                );
            }

            $stmt->execute();
            $stmt->close();

            header("Location: notifications.php?added=1");
            exit;

        } catch (Exception $e) {

            $errors[] = "Error adding notification: " . $e->getMessage();
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
    min-height: 140px;
    resize: vertical;
}

.help-text {
    margin-top: 7px;
    font-size: 12px;
    color: #6b7280;
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

.save-btn {
    border: none;
    background: #7c3aed;
    color: white;
    padding: 12px 24px;
    border-radius: 9px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
}

.save-btn:hover {
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
            <h1>Add Notification</h1>
            <p>Create an academic notification for students</p>
        </div>

        <a href="notifications.php" class="back-btn">
            ← Back to Notifications
        </a>

    </div>


    <div class="form-card">

        <?php if (!empty($errors)): ?>

            <div class="error-box">

                <ul>

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?= htmlspecialchars($error) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <form method="POST">

            <div class="form-grid">


                <!-- Title -->

                <div class="form-group full">

                    <label>
                        Notification Title <span>*</span>
                    </label>

                    <input
                        type="text"
                        name="title"
                        value="<?= htmlspecialchars($title) ?>"
                        placeholder="Example: Semester 5 Exam Timetable Released"
                        required
                    >

                </div>


                <!-- Type -->

                <div class="form-group">

                    <label>
                        Notification Type <span>*</span>
                    </label>

                    <select name="notification_type" required>

                        <?php foreach (
                            ["General", "Exam", "Marks", "Attendance", "Backlog"]
                            as $type
                        ): ?>

                            <option
                                value="<?= htmlspecialchars($type) ?>"
                                <?= $notification_type === $type ? "selected" : "" ?>
                            >
                                <?= htmlspecialchars($type) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Student -->

                <div class="form-group">

                    <label>
                        Send To
                    </label>

                    <select name="student_id">

                        <option value="">
                            All Students
                        </option>

                        <?php foreach ($students as $student): ?>

                            <option
                                value="<?= (int)$student["id"] ?>"
                                <?= ($student_id == $student["id"]) ? "selected" : "" ?>
                            >

                                <?= htmlspecialchars($student["full_name"]) ?>

                                -
                                <?= htmlspecialchars($student["prn"]) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <div class="help-text">
                        Select "All Students" to send the notification to everyone.
                    </div>

                </div>


                <!-- Message -->

                <div class="form-group full">

                    <label>
                        Message <span>*</span>
                    </label>

                    <textarea
                        name="message"
                        placeholder="Write the notification message here..."
                        required
                    ><?= htmlspecialchars($message) ?></textarea>

                </div>

            </div>


            <div class="form-actions">

                <button type="submit" class="save-btn">
                    + Add Notification
                </button>

                <a href="notifications.php" class="cancel-btn">
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>