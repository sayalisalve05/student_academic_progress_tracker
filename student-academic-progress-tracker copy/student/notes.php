
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_student();

$user_id = (int) $_SESSION["user_id"];

/* Get logged-in student's current semester */
$stmt = $conn->prepare("
    SELECT id, current_semester, full_name
    FROM students
    WHERE user_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    die("Student profile not found.");
}

$current_semester = (int) $student["current_semester"];

/* Get notes only for subjects in this student's semester */
$stmt = $conn->prepare("
    SELECT
        sn.id,
        sn.note_title,
        sn.note_content,
        sn.note_file,
        sn.created_at,
        sn.updated_at,
        sub.id AS subject_id,
        sub.subject_code,
        sub.subject_name,
        sem.semester_number
    FROM subject_notes sn
    INNER JOIN subjects sub
        ON sub.id = sn.subject_id
    INNER JOIN semesters sem
        ON sem.id = sub.semester_id
    WHERE sem.semester_number = ?
    ORDER BY sub.subject_name ASC, sn.created_at DESC, sn.id DESC
");

$stmt->bind_param("i", $current_semester);
$stmt->execute();
$result = $stmt->get_result();

$notes = [];

while ($row = $result->fetch_assoc()) {
    $notes[] = $row;
}

$stmt->close();

/* Group notes by subject */
$grouped_notes = [];

foreach ($notes as $note) {
    $subject_id = (int) $note["subject_id"];

    if (!isset($grouped_notes[$subject_id])) {
        $grouped_notes[$subject_id] = [
            "subject_code" => $note["subject_code"],
            "subject_name" => $note["subject_name"],
            "notes" => []
        ];
    }

    $grouped_notes[$subject_id]["notes"][] = $note;
}

$current_page = basename($_SERVER["PHP_SELF"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Notes | Academic Tracker</title>

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
    padding: 30px;
    min-height: 100vh;
}

.page-header {
    margin-bottom: 25px;
}

.page-header h1 {
    margin: 0;
    font-size: 28px;
    color: #111827;
}

.page-header p {
    color: #6b7280;
    margin-top: 8px;
    font-size: 14px;
}

.semester-label {
    display: inline-block;
    margin-top: 5px;
    padding: 7px 12px;
    border-radius: 8px;
    background: #ede9fe;
    color: #5b21b6;
    font-size: 12px;
    font-weight: 700;
}

.subject-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    margin-bottom: 22px;
    overflow: hidden;
    box-shadow: 0 4px 14px rgba(0,0,0,.04);
}

.subject-header {
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    background: #faf9ff;
    border-bottom: 1px solid #e5e7eb;
}

.subject-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ede9fe;
    font-size: 23px;
    flex-shrink: 0;
}

.subject-code {
    font-size: 12px;
    font-weight: 700;
    color: #6d28d9;
}

.subject-name {
    font-size: 17px;
    font-weight: 700;
    margin-top: 4px;
}

.note-item {
    padding: 20px;
    border-bottom: 1px solid #eef0f4;
}

.note-item:last-child {
    border-bottom: none;
}

.note-title {
    font-size: 16px;
    font-weight: 700;
    color: #111827;
}

.note-date {
    margin-top: 7px;
    font-size: 11px;
    color: #9ca3af;
}

.note-content {
    margin-top: 14px;
    font-size: 14px;
    color: #4b5563;
    line-height: 1.7;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.file-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 15px;
    padding: 10px 13px;
    background: #eff6ff;
    color: #1d4ed8;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
}

.file-link:hover {
    background: #dbeafe;
}

.no-subject-notes {
    padding: 20px;
    color: #6b7280;
    font-size: 13px;
}

.empty-state {
    background: white;
    padding: 45px 20px;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    text-align: center;
    color: #6b7280;
}

.empty-icon {
    font-size: 42px;
    margin-bottom: 12px;
}

.empty-state strong {
    display: block;
    color: #374151;
    font-size: 16px;
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 220px;
        padding: 20px;
    }
}

@media (max-width: 550px) {
    .main-content {
        margin-left: 0;
        padding: 16px;
    }

    .page-header h1 {
        font-size: 23px;
    }
}
</style>
</head>

<body>

<?php include "includes/sidebar.php"; ?>

<main class="main-content">

    <div class="page-header">
        <h1>📝 My Study Notes</h1>
        <p>
            Hello,
            <?php echo htmlspecialchars($student["full_name"]); ?>.
            View study materials for your current semester.
        </p>
        <span class="semester-label">
            Semester <?php echo $current_semester; ?>
        </span>
    </div>

    <?php if (empty($grouped_notes)): ?>

        <div class="empty-state">
            <div class="empty-icon">📚</div>
            <strong>No notes available yet</strong>
            <p>
                Notes uploaded by your admin for your current
                semester will appear here.
            </p>
        </div>

    <?php else: ?>

        <?php foreach ($grouped_notes as $subject): ?>

            <section class="subject-card">

                <div class="subject-header">
                    <div class="subject-icon">📖</div>
                    <div>
                        <div class="subject-code">
                            <?php echo htmlspecialchars($subject["subject_code"]); ?>
                        </div>
                        <div class="subject-name">
                            <?php echo htmlspecialchars($subject["subject_name"]); ?>
                        </div>
                    </div>
                </div>

                <?php foreach ($subject["notes"] as $note): ?>

                    <div class="note-item">

                        <div class="note-title">
                            📝
                            <?php echo htmlspecialchars($note["note_title"]); ?>
                        </div>

                        <div class="note-date">
                            Added:
                            <?php
                            echo !empty($note["created_at"])
                                ? date("d M Y, h:i A", strtotime($note["created_at"]))
                                : "Date unavailable";
                            ?>
                        </div>

                        <?php if (!empty($note["note_content"])): ?>
                            <div class="note-content"><?php
                                echo htmlspecialchars($note["note_content"]);
                            ?></div>
                        <?php endif; ?>

                        <?php if (!empty($note["note_file"])): ?>
                           
<a
    class="file-link"
    href="../uploads/notes/<?php
        echo rawurlencode(basename($note["note_file"]));
    ?>"
    target="_blank"
    rel="noopener noreferrer"
>
    📎 Open Note
</a>

<a
    class="file-link"
    href="../uploads/notes/<?php
        echo rawurlencode(basename($note["note_file"]));
    ?>"
    download
>
    📥 Download Note
</a>
                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            </section>

        <?php endforeach; ?>

    <?php endif; ?>

</main>
</body>
</html>