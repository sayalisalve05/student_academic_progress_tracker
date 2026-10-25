<?php
require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

$note_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($note_id <= 0) {
    header("Location: notes.php");
    exit;
}

/* Get existing note */
$stmt = $conn->prepare("
    SELECT
        n.id,
        n.subject_id,
        n.note_title,
        n.note_content,
        n.note_file,
        s.subject_code,
        s.subject_name
    FROM subject_notes n
    JOIN subjects s
        ON s.id = n.subject_id
    WHERE n.id = ?
");

$stmt->bind_param("i", $note_id);
$stmt->execute();

$note = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$note) {
    header("Location: notes.php");
    exit;
}

$errors = [];

/* Update note */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $subject_id = (int)($_POST["subject_id"] ?? 0);
    $note_title = trim($_POST["note_title"] ?? "");
    $note_content = trim($_POST["note_content"] ?? "");

    if ($subject_id <= 0) {
        $errors[] = "Please select a valid subject.";
    }

    if ($note_title === "") {
        $errors[] = "Note title is required.";
    }

    /* Verify subject */
    if (empty($errors)) {

        $stmt = $conn->prepare("
            SELECT id
            FROM subjects
            WHERE id = ?
        ");

        $stmt->bind_param("i", $subject_id);
        $stmt->execute();

        $subject_exists = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$subject_exists) {
            $errors[] = "Selected subject does not exist.";
        }
    }

    $new_note_file = $note["note_file"];

    /* File replacement */
    if (
        empty($errors) &&
        isset($_FILES["note_file"]) &&
        $_FILES["note_file"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES["note_file"]["error"] !== UPLOAD_ERR_OK) {

            $errors[] = "There was an error uploading the file.";

        } else {

            $file = $_FILES["note_file"];

            /* Maximum 10 MB */
            if ($file["size"] > 10 * 1024 * 1024) {
                $errors[] = "Note file must be less than 10 MB.";
            }

            $extension = strtolower(
                pathinfo($file["name"], PATHINFO_EXTENSION)
            );

            $allowed_extensions = [
                "pdf",
                "doc",
                "docx",
                "ppt",
                "pptx",
                "txt"
            ];

            if (!in_array($extension, $allowed_extensions, true)) {
                $errors[] =
                    "Invalid file type. Allowed: PDF, DOC, DOCX, PPT, PPTX, TXT.";
            }

            /* MIME validation */
            $allowed_mimes = [
                "application/pdf",
                "application/msword",
                "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
                "application/vnd.ms-powerpoint",
                "application/vnd.openxmlformats-officedocument.presentationml.presentation",
                "text/plain"
            ];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file["tmp_name"]);
            finfo_close($finfo);

            if (!in_array($mime, $allowed_mimes, true)) {
                $errors[] = "The uploaded file type is not allowed.";
            }

            if (empty($errors)) {

                $upload_dir = "../uploads/notes/";

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $new_filename =
                    "note_" .
                    time() .
                    "_" .
                    bin2hex(random_bytes(5)) .
                    "." .
                    $extension;

                $destination =
                    $upload_dir . $new_filename;

                if (move_uploaded_file(
                    $file["tmp_name"],
                    $destination
                )) {

                    $new_note_file = $new_filename;

                } else {

                    $errors[] =
                        "Failed to save the uploaded file.";
                }
            }
        }
    }

    /* Save changes */
    if (empty($errors)) {

        $stmt = $conn->prepare("
            UPDATE subject_notes
            SET
                subject_id = ?,
                note_title = ?,
                note_content = ?,
                note_file = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "isssi",
            $subject_id,
            $note_title,
            $note_content,
            $new_note_file,
            $note_id
        );

        if ($stmt->execute()) {

            $stmt->close();

            /* Delete old file after successful DB update */
            if (
                $new_note_file !== $note["note_file"] &&
                !empty($note["note_file"])
            ) {

                $old_file =
                    "../uploads/notes/" .
                    basename($note["note_file"]);

                if (file_exists($old_file)) {
                    unlink($old_file);
                }
            }

            header(
                "Location: notes.php?subject_id=" .
                $subject_id .
                "&success=updated"
            );

            exit;

        } else {

            $stmt->close();

            /* Remove newly uploaded file if database update failed */
            if (
                $new_note_file !== $note["note_file"] &&
                !empty($new_note_file)
            ) {

                $new_file =
                    "../uploads/notes/" .
                    basename($new_note_file);

                if (file_exists($new_file)) {
                    unlink($new_file);
                }
            }

            $errors[] = "Failed to update the note.";
        }
    }

    /* Preserve entered values after validation error */
    $note["subject_id"] = $subject_id;
    $note["note_title"] = $note_title;
    $note["note_content"] = $note_content;
}

/* Get subjects */
$subjects = $conn->query("
    SELECT
        s.id,
        s.subject_code,
        s.subject_name,
        sem.semester_number
    FROM subjects s
    LEFT JOIN semesters sem
        ON sem.id = s.semester_id
    ORDER BY
        sem.semester_number ASC,
        s.subject_code ASC
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

    <title>Edit Note | Academic Tracker</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6fb;
            color: #1f2937;
        }

        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: 100vh;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .page-title {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }

        .page-subtitle {
            margin-top: 6px;
            color: #6b7280;
            font-size: 14px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 16px;
            border-radius: 8px;
            background: #e5e7eb;
            color: #374151;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .back-btn:hover {
            background: #d1d5db;
        }

        .card {
            max-width: 850px;
            background: #fff;
            border-radius: 14px;
            padding: 28px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.06);
        }

        .form-group {
            margin-bottom: 22px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
        }

        .required {
            color: #ef4444;
        }

        input[type="text"],
        select,
        textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 9px;
            font-size: 14px;
            outline: none;
            background: #fff;
        }

        input[type="text"]:focus,
        select:focus,
        textarea:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79,70,229,0.10);
        }

        textarea {
            min-height: 180px;
            resize: vertical;
            line-height: 1.6;
        }

        .current-file {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 13px 15px;
            margin-bottom: 12px;
            border-radius: 9px;
            background: #f3f4f6;
            font-size: 13px;
        }

        .current-file-name {
            font-weight: 600;
            color: #374151;
            word-break: break-all;
        }

        .file-box {
            border: 2px dashed #c7d2fe;
            background: #f8faff;
            border-radius: 12px;
            padding: 22px;
        }

        input[type="file"] {
            width: 100%;
            font-size: 14px;
        }

        .help-text {
            margin-top: 8px;
            color: #6b7280;
            font-size: 12px;
            line-height: 1.5;
        }

        .error-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 22px;
        }

        .error-box ul {
            margin: 0;
            padding-left: 20px;
        }

        .submit-row {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 28px;
        }

        .cancel-btn,
        .save-btn {
            padding: 12px 22px;
            border-radius: 9px;
            border: none;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .cancel-btn {
            background: #e5e7eb;
            color: #374151;
        }

        .save-btn {
            background: #4f46e5;
            color: #fff;
        }

        .save-btn:hover {
            background: #4338ca;
        }

        .cancel-btn:hover {
            background: #d1d5db;
        }

        @media (max-width: 800px) {

            .main-content {
                margin-left: 220px;
                padding: 20px;
            }

            .page-header {
                align-items: flex-start;
                gap: 15px;
            }

        }

    </style>

</head>

<body>

<?php include "includes/sidebar.php"; ?>

<div class="main-content">

    <div class="page-header">

        <div>

            <h1 class="page-title">
                ✏️ Edit Note
            </h1>

            <div class="page-subtitle">
                Update study note information
            </div>

        </div>

        <a
            href="notes.php?subject_id=<?php echo (int)$note["subject_id"]; ?>"
            class="back-btn"
        >
            ← Back to Notes
        </a>

    </div>

    <?php if (!empty($errors)): ?>

        <div class="error-box">

            <strong>Please fix the following:</strong>

            <ul>

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?php echo htmlspecialchars($error); ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>

    <div class="card">

        <form
            method="POST"
            enctype="multipart/form-data"
        >

            <div class="form-group">

                <label>
                    Subject <span class="required">*</span>
                </label>

                <select
                    name="subject_id"
                    required
                >

                    <option value="">
                        -- Select Subject --
                    </option>

                    <?php if ($subjects): ?>

                        <?php while ($row = $subjects->fetch_assoc()): ?>

                            <option
                                value="<?php echo (int)$row["id"]; ?>"
                                <?php
                                echo (
                                    (int)$note["subject_id"] ===
                                    (int)$row["id"]
                                )
                                ? "selected"
                                : "";
                                ?>
                            >

                                Semester
                                <?php echo htmlspecialchars($row["semester_number"] ?? "-"); ?>

                                —
                                <?php echo htmlspecialchars($row["subject_code"]); ?>

                                —
                                <?php echo htmlspecialchars($row["subject_name"]); ?>

                            </option>

                        <?php endwhile; ?>

                    <?php endif; ?>

                </select>

            </div>

            <div class="form-group">

                <label>
                    Note Title <span class="required">*</span>
                </label>

                <input
                    type="text"
                    name="note_title"
                    value="<?php
                        echo htmlspecialchars(
                            $note["note_title"]
                        );
                    ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label>
                    Note Content
                </label>

                <textarea
                    name="note_content"
                    placeholder="Write your notes here..."
                ><?php
                    echo htmlspecialchars(
                        $note["note_content"] ?? ""
                    );
                ?></textarea>

            </div>

            <div class="form-group">

                <label>
                    Attached File
                </label>

                <?php if (!empty($note["note_file"])): ?>

                    <div class="current-file">

                        <span class="current-file-name">
                            📎
                            <?php
                            echo htmlspecialchars(
                                $note["note_file"]
                            );
                            ?>
                        </span>

                        <a
                            href="../uploads/notes/<?php
                                echo rawurlencode(
                                    basename($note["note_file"])
                                );
                            ?>"
                            target="_blank"
                            style="
                                color:#4f46e5;
                                text-decoration:none;
                                font-weight:600;
                            "
                        >
                            View
                        </a>

                    </div>

                    <div class="help-text">
                        Upload a new file below only if you want
                        to replace the existing file.
                    </div>

                <?php else: ?>

                    <div class="help-text">
                        No file is currently attached.
                    </div>

                <?php endif; ?>

                <br>

                <div class="file-box">

                    <input
                        type="file"
                        name="note_file"
                        accept=".pdf,.doc,.docx,.ppt,.pptx,.txt"
                    >

                    <div class="help-text">
                        Allowed formats: PDF, DOC, DOCX, PPT,
                        PPTX and TXT. Maximum size: 10 MB.
                    </div>

                </div>

            </div>

            <div class="submit-row">

                <a
                    href="notes.php?subject_id=<?php echo (int)$note["subject_id"]; ?>"
                    class="cancel-btn"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="save-btn"
                >
                    💾 Update Note
                </button>

            </div>

        </form>

    </div>

</div>

</body>

</html>