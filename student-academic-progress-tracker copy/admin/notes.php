<?php

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

$subject_id = (int)($_GET["subject_id"] ?? 0);
$error = "";

$success = "";

if (isset($_GET["success"]) && $_GET["success"] === "deleted") {
    $success = "Note deleted successfully!";
}
/* -------------------------------------------------
   DELETE NOTE
------------------------------------------------- */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_note_id"])) {

    $delete_id = (int)$_POST["delete_note_id"];

    if ($delete_id > 0) {

        /* Get file before deleting */

        $stmt = $conn->prepare("
            SELECT note_file
            FROM subject_notes
            WHERE id = ?
        ");

        $stmt->bind_param("i", $delete_id);
        $stmt->execute();

        $file_result = $stmt->get_result();
        $file_data = $file_result->fetch_assoc();

        $stmt->close();


        /* Delete database record */

        $stmt = $conn->prepare("
            DELETE FROM subject_notes
            WHERE id = ?
        ");

        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $stmt->close();


        /* Delete uploaded file */

        if (
            $file_data &&
            !empty($file_data["note_file"])
        ) {

            $file_path =
                "../uploads/notes/"
                . basename($file_data["note_file"]);

            if (is_file($file_path)) {
                unlink($file_path);
            }
        }
    }


   header(
    "Location: notes.php?subject_id="
    . $subject_id
    . "&success=deleted"
);

exit;
    exit;
}


/* -------------------------------------------------
   GET SUBJECT
------------------------------------------------- */

$subject = null;

if ($subject_id > 0) {

    $stmt = $conn->prepare("
        SELECT
            sub.id,
            sub.subject_code,
            sub.subject_name,
            sub.credits,
            sem.semester_number,
            sem.academic_year

        FROM subjects sub

        LEFT JOIN semesters sem
            ON sub.semester_id = sem.id

        WHERE sub.id = ?

        LIMIT 1
    ");

    $stmt->bind_param("i", $subject_id);
    $stmt->execute();

    $subject_result = $stmt->get_result();

    $subject = $subject_result->fetch_assoc();

    $stmt->close();
}


/* -------------------------------------------------
   GET NOTES
------------------------------------------------- */

$notes = null;

if ($subject_id > 0) {

    $stmt = $conn->prepare("
        SELECT
            id,
            note_title,
            note_content,
            note_file,
            created_at,
            updated_at

        FROM subject_notes

        WHERE subject_id = ?

        ORDER BY
            created_at DESC,
            id DESC
    ");

    $stmt->bind_param("i", $subject_id);
    $stmt->execute();

    $notes = $stmt->get_result();

    $stmt->close();
}


/* -------------------------------------------------
   ALL SUBJECTS
------------------------------------------------- */

$subjects = $conn->query("
    SELECT
        sub.id,
        sub.subject_code,
        sub.subject_name,
        sem.semester_number

    FROM subjects sub

    LEFT JOIN semesters sem
        ON sub.semester_id = sem.id

    ORDER BY
        sem.semester_number ASC,
        sub.subject_code ASC
");


?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Notes Management | Academic Tracker
</title>


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

<?php if ($success !== ""): ?>

    <div style="
        background:#d1fae5;
        color:#065f46;
        padding:14px 18px;
        border-radius:10px;
        margin-bottom:20px;
        border:1px solid #a7f3d0;
        font-weight:600;
    ">
        ✅ <?php echo htmlspecialchars($success); ?>
    </div>

<?php endif; ?>

/* HEADER */

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 25px;
}

.page-title h1 {
    margin: 0;
    font-size: 28px;
    color: #111827;
}

.page-title p {
    margin: 7px 0 0;
    color: #6b7280;
    font-size: 14px;
}


/* BUTTONS */

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 11px 18px;
    border-radius: 9px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
}

.btn-primary {
    background: #4f46e5;
    color: white;
}

.btn-primary:hover {
    background: #4338ca;
}

.btn-secondary {
    background: #e5e7eb;
    color: #374151;
}

.btn-secondary:hover {
    background: #d1d5db;
}

.btn-delete {
    background: #fee2e2;
    color: #dc2626;
}

.btn-delete:hover {
    background: #fecaca;
}


/* SUBJECT CARD */

.subject-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 22px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.04);
}

.subject-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.subject-icon {
    width: 52px;
    height: 52px;
    border-radius: 13px;
    background: #ede9fe;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 25px;
}

.subject-code {
    color: #4f46e5;
    font-size: 13px;
    font-weight: 700;
}

.subject-name {
    margin-top: 3px;
    color: #111827;
    font-size: 18px;
    font-weight: 700;
}

.subject-meta {
    margin-top: 5px;
    color: #6b7280;
    font-size: 12px;
}


/* SELECT SUBJECT */

.select-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 22px;
}

.select-card label {
    display: block;
    margin-bottom: 8px;
    font-size: 14px;
    font-weight: 700;
}

select {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid #d1d5db;
    border-radius: 9px;
    background: white;
    font-size: 14px;
    outline: none;
}

select:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
}


/* NOTES */

.notes-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 3px 12px rgba(0,0,0,0.04);
}

.notes-header {
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.notes-header h2 {
    margin: 0;
    font-size: 18px;
}

.notes-header p {
    margin: 5px 0 0;
    color: #6b7280;
    font-size: 13px;
}


/* NOTE ITEM */

.note-item {
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.note-item:last-child {
    border-bottom: none;
}

.note-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
}

.note-title {
    font-size: 17px;
    font-weight: 700;
    color: #111827;
}

.note-date {
    margin-top: 5px;
    font-size: 11px;
    color: #9ca3af;
}

.note-content {
    margin-top: 15px;
    color: #4b5563;
    font-size: 14px;
    line-height: 1.7;
    white-space: pre-wrap;
}

.note-file {
    margin-top: 15px;
}

.file-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 13px;
    background: #eff6ff;
    color: #1d4ed8;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.file-link:hover {
    background: #dbeafe;
}

.note-actions {
    display: flex;
    gap: 8px;
}

.empty {
    padding: 55px 20px;
    text-align: center;
    color: #6b7280;
}

.empty-icon {
    font-size: 42px;
    margin-bottom: 10px;
}

.warning {
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #9a3412;
    border-radius: 10px;
    padding: 14px;
    margin-bottom: 20px;
    font-size: 14px;
}


@media (max-width: 800px) {

    .main-content {
        margin-left: 220px;
        padding: 20px;
    }

    .page-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .note-top {
        flex-direction: column;
    }

}

</style>

</head>


<body>


<?php include "includes/sidebar.php"; ?>


<main class="main-content">
    


<!-- HEADER -->

<div class="page-header">

    <div class="page-title">

        <h1>📝 Notes Management</h1>

        <p>
            Manage study notes and learning materials.
        </p>

    </div>


    <a
        href="syllabus.php"
        class="btn btn-secondary"
    >

        ← Back to Syllabus

    </a>

</div>


<?php if (!$subject): ?>


<!-- SELECT SUBJECT -->

<div class="select-card">

    <label for="subjectSelect">
        Select Subject
    </label>


    <select
        id="subjectSelect"
        onchange="openSubjectNotes()"
    >

        <option value="">
            -- Select Subject --
        </option>


        <?php if ($subjects): ?>

            <?php while ($row = $subjects->fetch_assoc()): ?>

                <option
                    value="<?php echo (int)$row["id"]; ?>"
                >

                    <?php

                    echo htmlspecialchars(
                        $row["subject_code"]
                        . " - "
                        . $row["subject_name"]
                    );

                    if (
                        $row["semester_number"] !== null
                    ) {

                        echo " (Sem "
                            . htmlspecialchars(
                                $row["semester_number"]
                            )
                            . ")";

                    }

                    ?>

                </option>

            <?php endwhile; ?>

        <?php endif; ?>

    </select>

</div>


<div class="empty">

    <div class="empty-icon">
        📝
    </div>

    <strong>
        Select a subject
    </strong>

    <p>
        Select a subject above to manage its notes.
    </p>

</div>


<?php else: ?>


<!-- SUBJECT -->

<div class="subject-card">

    <div class="subject-info">

        <div class="subject-icon">
            📚
        </div>


        <div>

            <div class="subject-code">

                <?php
                echo htmlspecialchars(
                    $subject["subject_code"]
                );
                ?>

            </div>


            <div class="subject-name">

                <?php
                echo htmlspecialchars(
                    $subject["subject_name"]
                );
                ?>

            </div>


            <div class="subject-meta">

                <?php if (
                    $subject["semester_number"] !== null
                ): ?>

                    Semester
                    <?php
                    echo htmlspecialchars(
                        $subject["semester_number"]
                    );
                    ?>

                    &nbsp; • &nbsp;

                <?php endif; ?>

                Credits:
                <?php
                echo htmlspecialchars(
                    $subject["credits"]
                );
                ?>

            </div>

        </div>

    </div>

</div>


<!-- NOTES -->

<div class="notes-card">


<div class="notes-header">

    <div>

        <h2>
            📝 Subject Notes
        </h2>

        <p>
            Notes available for this subject.
        </p>

    </div>


    <a
        href="add_note.php?subject_id=<?php echo $subject_id; ?>"
        class="btn btn-primary"
    >

        ➕ Add Note

    </a>

</div>


<?php if (
    $notes &&
    $notes->num_rows > 0
): ?>


<?php while (
    $note = $notes->fetch_assoc()
): ?>


<div class="note-item">


<div class="note-top">


<div>

    <div class="note-title">

        📝
        <?php
        echo htmlspecialchars(
            $note["note_title"]
        );
        ?>

    </div>


    <div class="note-date">

        Added:
        <?php
        echo date(
            "d M Y, h:i A",
            strtotime(
                $note["created_at"]
            )
        );
        ?>

    </div>

</div>


<div class="note-actions">


<a
    href="edit_note.php?id=<?php echo (int)$note["id"]; ?>"
    class="btn btn-secondary"
>

    ✏️ Edit

</a>


<form
    method="POST"
    style="display:inline;"
    onsubmit="return confirm('Delete this note?');"
>

<input
    type="hidden"
    name="delete_note_id"
    value="<?php echo (int)$note["id"]; ?>"
>


<button
    type="submit"
    class="btn btn-delete"
>

    🗑 Delete

</button>

</form>


</div>


</div>


<?php if (
    !empty($note["note_content"])
): ?>

<div class="note-content">

<?php
echo htmlspecialchars(
    $note["note_content"]
);
?>

</div>

<?php endif; ?>


<?php if (
    !empty($note["note_file"])
): ?>

<div class="note-file">

<a
    href="../uploads/notes/<?php
        echo rawurlencode(
            basename(
                $note["note_file"]
            )
        );
    ?>"
    target="_blank"
    class="file-link"
>

    📎 Open Note File

</a>

</div>

<?php endif; ?>


</div>


<?php endwhile; ?>


<?php else: ?>


<div class="empty">

    <div class="empty-icon">
        📝
    </div>

    <strong>
        No notes added yet
    </strong>

    <p>
        Add notes for this subject using the button above.
    </p>

</div>


<?php endif; ?>


</div>


<?php endif; ?>


</main>


<script>

function openSubjectNotes() {

    const select =
        document.getElementById(
            "subjectSelect"
        );

    const subjectId =
        select.value;


    if (subjectId !== "") {

        window.location.href =
            "notes.php?subject_id="
            + subjectId;

    }

}

</script>


</body>

</html>