<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

$error = "";
$notification = null;

/* -----------------------------
   GET NOTIFICATION ID
----------------------------- */
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: notifications.php");
    exit;
}

$id = (int) $_GET["id"];

/* -----------------------------
   FETCH NOTIFICATION
----------------------------- */
$stmt = $conn->prepare("
    SELECT id, title, message, notification_type, student_id
    FROM notifications
    WHERE id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
$notification = $result->fetch_assoc();

$stmt->close();

if (!$notification) {
    header("Location: notifications.php");
    exit;
}

/* -----------------------------
   FETCH STUDENTS
----------------------------- */
$students = [];

$student_stmt = $conn->prepare("
    SELECT id, prn, full_name
    FROM students
    ORDER BY full_name ASC
");

$student_stmt->execute();

$student_result = $student_stmt->get_result();

while ($row = $student_result->fetch_assoc()) {
    $students[] = $row;
}

$student_stmt->close();

/* -----------------------------
   NOTIFICATION TYPES
----------------------------- */
$notification_types = [
    "General",
    "Exam",
    "Marks",
    "Attendance",
    "Backlog"
];

/* -----------------------------
   UPDATE NOTIFICATION
----------------------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $message = trim($_POST["message"] ?? "");
    $notification_type = trim($_POST["notification_type"] ?? "");
    $student_id = trim($_POST["student_id"] ?? "");

    /* Validation */

    if ($title === "") {
        $error = "Please enter notification title.";
    } elseif ($message === "") {
        $error = "Please enter notification message.";
    } elseif (!in_array($notification_type, $notification_types, true)) {
        $error = "Please select a valid notification type.";
    } elseif ($student_id !== "" && !is_numeric($student_id)) {
        $error = "Invalid student selected.";
    } else {

        try {

            /* Send to all students */
            if ($student_id === "") {

                $stmt = $conn->prepare("
                    UPDATE notifications
                    SET
                        title = ?,
                        message = ?,
                        notification_type = ?,
                        student_id = NULL
                    WHERE id = ?
                ");

                $stmt->bind_param(
                    "sssi",
                    $title,
                    $message,
                    $notification_type,
                    $id
                );

            }

            /* Send to selected student */
            else {

                $selected_student_id = (int) $student_id;

                /* Check student exists */
                $check = $conn->prepare("
                    SELECT id
                    FROM students
                    WHERE id = ?
                ");

                $check->bind_param("i", $selected_student_id);
                $check->execute();

                $check_result = $check->get_result();

                if ($check_result->num_rows === 0) {
                    $check->close();
                    throw new Exception("Selected student does not exist.");
                }

                $check->close();

                $stmt = $conn->prepare("
                    UPDATE notifications
                    SET
                        title = ?,
                        message = ?,
                        notification_type = ?,
                        student_id = ?
                    WHERE id = ?
                ");

                $stmt->bind_param(
                    "sssii",
                    $title,
                    $message,
                    $notification_type,
                    $selected_student_id,
                    $id
                );
            }

            $stmt->execute();
            $stmt->close();

            header("Location: notifications.php?updated=1");
            exit;

        } catch (Exception $e) {
            $error = "Error updating notification: " . $e->getMessage();
        }
    }

    /* Keep entered values if there is an error */
    $notification["title"] = $title;
    $notification["message"] = $message;
    $notification["notification_type"] = $notification_type;
    $notification["student_id"] = ($student_id === "")
        ? null
        : (int) $student_id;
}
?>

<?php include "includes/sidebar.php"; ?>

<style>

.main-content {
    margin-left: 260px !important;
    padding: 35px;
    background: #f5f6fa;
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
    font-weight: 800;
    color: #1f2937;
}

.page-header p {
    margin: 7px 0 0;
    color: #6b7280;
    font-size: 14px;
}

.form-card {
    background: white;
    border-radius: 18px;
    padding: 30px;
    max-width: 850px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.06);
}

.form-group {
    margin-bottom: 22px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-size: 14px;
    font-weight: 700;
    color: #374151;
}

.form-group label span {
    color: #ef4444;
}

.form-control {
    width: 100%;
    padding: 13px 15px;
    border: 1px solid #d9dce5;
    border-radius: 10px;
    font-size: 14px;
    outline: none;
    background: white;
    color: #1f2937;
    transition: 0.2s;
}

.form-control:focus {
    border-color: #7c3aed;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.10);
}

textarea.form-control {
    min-height: 150px;
    resize: vertical;
    font-family: inherit;
}

select.form-control {
    cursor: pointer;
}

.help-text {
    margin-top: 7px;
    font-size: 12px;
    color: #6b7280;
}

.error-box {
    background: #fee2e2;
    color: #b91c1c;
    padding: 13px 15px;
    border-radius: 10px;
    margin-bottom: 22px;
    font-size: 14px;
    font-weight: 600;
    border: 1px solid #fecaca;
}

.button-row {
    display: flex;
    gap: 12px;
    margin-top: 28px;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 12px 22px;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: 0.2s;
}

.btn-update {
    background: #7c3aed;
    color: white;
}

.btn-update:hover {
    background: #6d28d9;
}

.btn-back {
    background: #1f2937;
    color: white;
}

.btn-back:hover {
    background: #111827;
}

.notification-preview {
    margin-top: 25px;
    padding: 18px;
    background: #f8f7ff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
}

.notification-preview-title {
    font-size: 13px;
    font-weight: 700;
    color: #6b7280;
    margin-bottom: 8px;
}

.notification-preview-text {
    font-size: 14px;
    color: #374151;
    line-height: 1.6;
}

@media (max-width: 900px) {

    .main-content {
        margin-left: 0 !important;
        padding: 20px;
    }

    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .form-card {
        padding: 22px;
    }
}

</style>

<div class="main-content">

    <div class="page-header">

        <div>
            <h1>Edit Notification</h1>
            <p>Update notification information</p>
        </div>

    </div>

    <div class="form-card">

        <?php if ($error !== ""): ?>

            <div class="error-box">
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <!-- TITLE -->
            <div class="form-group">

                <label>
                    Notification Title <span>*</span>
                </label>

                <input
                    type="text"
                    name="title"
                    class="form-control"
                    maxlength="150"
                    placeholder="Enter notification title"
                    value="<?php echo htmlspecialchars($notification["title"]); ?>"
                    required
                >

            </div>


            <!-- MESSAGE -->
            <div class="form-group">

                <label>
                    Message <span>*</span>
                </label>

                <textarea
                    name="message"
                    class="form-control"
                    placeholder="Enter notification message"
                    required
                ><?php echo htmlspecialchars($notification["message"]); ?></textarea>

            </div>


            <!-- TYPE -->
            <div class="form-group">

                <label>
                    Notification Type <span>*</span>
                </label>

                <select
                    name="notification_type"
                    class="form-control"
                    required
                >

                    <option value="">Select notification type</option>

                    <?php foreach ($notification_types as $type): ?>

                        <option
                            value="<?php echo htmlspecialchars($type); ?>"
                            <?php
                            echo ($notification["notification_type"] === $type)
                                ? "selected"
                                : "";
                            ?>
                        >
                            <?php echo htmlspecialchars($type); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- STUDENT -->
            <div class="form-group">

                <label>
                    Send To
                </label>

                <select
                    name="student_id"
                    class="form-control"
                >

                    <option value="">
                        All Students
                    </option>

                    <?php foreach ($students as $student): ?>

                        <option
                            value="<?php echo (int) $student["id"]; ?>"
                            <?php
                            echo (
                                $notification["student_id"] !== null &&
                                (int)$notification["student_id"] === (int)$student["id"]
                            )
                                ? "selected"
                                : "";
                            ?>
                        >

                            <?php
                            echo htmlspecialchars(
                                $student["full_name"] .
                                " (" .
                                $student["prn"] .
                                ")"
                            );
                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <div class="help-text">
                    Select "All Students" to send this notification to everyone.
                </div>

            </div>


            <!-- PREVIEW -->
            <div class="notification-preview">

                <div class="notification-preview-title">
                    Notification
                </div>

                <div class="notification-preview-text">
                    Update the information above and click
                    <strong>Update Notification</strong>.
                </div>

            </div>


            <!-- BUTTONS -->
            <div class="button-row">

                <button
                    type="submit"
                    class="btn btn-update"
                >
                    Update Notification
                </button>

                <a
                    href="notifications.php"
                    class="btn btn-back"
                >
                    Back
                </a>

            </div>

        </form>

    </div>

</div>