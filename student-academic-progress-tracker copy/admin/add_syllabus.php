<?php

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

$error = "";

$selected_subject_id = isset($_GET["subject_id"])
    ? (int)$_GET["subject_id"]
    : 0;


/* -------------------------------------------------
   HANDLE FORM SUBMISSION
------------------------------------------------- */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $subject_id = (int)($_POST["subject_id"] ?? 0);
    $syllabus = trim($_POST["syllabus"] ?? "");

    /* Validate subject */

    if ($subject_id <= 0) {

        $error = "Please select a subject.";

    } else {

        /* Check subject exists */

        $check_subject = $conn->prepare("
            SELECT id
            FROM subjects
            WHERE id = ?
            LIMIT 1
        ");

        $check_subject->bind_param("i", $subject_id);
        $check_subject->execute();

        $subject_result = $check_subject->get_result();

        if ($subject_result->num_rows === 0) {

            $error = "Selected subject does not exist.";

        }

        $check_subject->close();
    }


    /* Check whether syllabus already exists */

    if ($error === "") {

        $check = $conn->prepare("
            SELECT id
            FROM subject_syllabus
            WHERE subject_id = ?
            LIMIT 1
        ");

        $check->bind_param("i", $subject_id);
        $check->execute();

        $check_result = $check->get_result();

        if ($check_result->num_rows > 0) {

            $error = "Syllabus already exists for this subject. Please edit the existing syllabus instead.";

        }

        $check->close();
    }


    /* -------------------------------------------------
       SAVE SYLLABUS
    ------------------------------------------------- */

    if ($error === "") {

        $stmt = $conn->prepare("
            INSERT INTO subject_syllabus
            (subject_id, syllabus)
            VALUES (?, ?)
        ");

        $stmt->bind_param(
            "is",
            $subject_id,
            $syllabus
        );

        if ($stmt->execute()) {

            $syllabus_id = $stmt->insert_id;

            $stmt->close();


            /* -------------------------------------------------
               IMAGE UPLOAD
            ------------------------------------------------- */

            if (
                isset($_FILES["syllabus_images"]) &&
                is_array($_FILES["syllabus_images"]["name"])
            ) {

                $upload_dir = "../uploads/syllabus/";

                /* Create folder if it does not exist */

                if (!is_dir($upload_dir)) {

                    mkdir(
                        $upload_dir,
                        0755,
                        true
                    );
                }


                $allowed_extensions = [
                    "jpg",
                    "jpeg",
                    "png",
                    "webp"
                ];

                $allowed_mime_types = [
                    "image/jpeg",
                    "image/png",
                    "image/webp"
                ];


                $file_count = count(
                    $_FILES["syllabus_images"]["name"]
                );


                for ($i = 0; $i < $file_count; $i++) {

                    $error_code =
                        $_FILES["syllabus_images"]["error"][$i];

                    /* Ignore empty file slots */

                    if (
                        $error_code === UPLOAD_ERR_NO_FILE
                    ) {
                        continue;
                    }


                    /* Skip files with upload errors */

                    if (
                        $error_code !== UPLOAD_ERR_OK
                    ) {
                        continue;
                    }


                    $original_name =
                        $_FILES["syllabus_images"]["name"][$i];

                    $tmp_name =
                        $_FILES["syllabus_images"]["tmp_name"][$i];

                    $file_size =
                        (int)$_FILES["syllabus_images"]["size"][$i];


                    /* Maximum 2 MB */

                    if ($file_size > 2 * 1024 * 1024) {
                        continue;
                    }


                    $extension = strtolower(
                        pathinfo(
                            $original_name,
                            PATHINFO_EXTENSION
                        )
                    );


                    if (
                        !in_array(
                            $extension,
                            $allowed_extensions,
                            true
                        )
                    ) {
                        continue;
                    }


                    /* Verify actual image */

                    $image_info = @getimagesize(
                        $tmp_name
                    );

                    if ($image_info === false) {
                        continue;
                    }


                    $mime_type =
                        $image_info["mime"] ?? "";


                    if (
                        !in_array(
                            $mime_type,
                            $allowed_mime_types,
                            true
                        )
                    ) {
                        continue;
                    }


                    /* Generate unique filename */

                    $new_filename =
                        "syllabus_"
                        . time()
                        . "_"
                        . bin2hex(random_bytes(5))
                        . "."
                        . $extension;


                    $destination =
                        $upload_dir . $new_filename;


                    if (
                        move_uploaded_file(
                            $tmp_name,
                            $destination
                        )
                    ) {

                        /* Save filename in database */

                        $image_stmt = $conn->prepare("
                            INSERT INTO subject_syllabus_images
                            (syllabus_id, image_path)
                            VALUES (?, ?)
                        ");

                        $image_stmt->bind_param(
                            "is",
                            $syllabus_id,
                            $new_filename
                        );

                        $image_stmt->execute();

                        $image_stmt->close();
                    }
                }
            }


            header(
                "Location: syllabus.php"
            );

            exit;


        } else {

            $error =
                "Failed to save syllabus: "
                . $conn->error;

            $stmt->close();
        }
    }
}


/* -------------------------------------------------
   GET SUBJECTS
------------------------------------------------- */

$subjects = $conn->query("
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

<title>Add Syllabus | Academic Tracker</title>


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
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    gap: 20px;
}

.page-header h1 {
    margin: 0;
    font-size: 28px;
    color: #111827;
}

.page-header p {
    margin: 7px 0 0;
    color: #6b7280;
    font-size: 14px;
}

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

.form-card {
    max-width: 950px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 15px;
    padding: 28px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.04);
}

.form-title {
    margin-bottom: 25px;
}

.form-title h2 {
    margin: 0;
    font-size: 20px;
    color: #111827;
}

.form-title p {
    margin: 6px 0 0;
    color: #6b7280;
    font-size: 13px;
}

.alert {
    padding: 13px 15px;
    border-radius: 9px;
    margin-bottom: 20px;
    font-size: 14px;
}

.alert-error {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
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

.required {
    color: #dc2626;
}

select,
textarea {
    width: 100%;
    border: 1px solid #d1d5db;
    border-radius: 9px;
    padding: 12px 14px;
    font-size: 14px;
    font-family: inherit;
    outline: none;
    background: white;
}

select:focus,
textarea:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79,70,229,0.10);
}

textarea {
    min-height: 300px;
    resize: vertical;
    line-height: 1.6;
}

.help-text {
    margin-top: 7px;
    color: #6b7280;
    font-size: 12px;
}

.subject-info {
    margin-top: 10px;
    padding: 12px 14px;
    background: #f5f3ff;
    border: 1px solid #ddd6fe;
    border-radius: 9px;
    color: #4c1d95;
    font-size: 13px;
    display: none;
}


/* IMAGE UPLOAD */

.upload-box {
    border: 2px dashed #c7d2fe;
    background: #f8f7ff;
    border-radius: 12px;
    padding: 25px;
    text-align: center;
}

.upload-icon {
    font-size: 42px;
    margin-bottom: 8px;
}

.upload-title {
    font-size: 15px;
    font-weight: 700;
    color: #374151;
}

.upload-subtitle {
    margin-top: 6px;
    font-size: 12px;
    color: #6b7280;
}

.file-input {
    margin-top: 15px;
    width: 100%;
    padding: 12px;
    border: 1px solid #d1d5db;
    border-radius: 9px;
    background: white;
}

.preview-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.preview-item {
    position: relative;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
    background: #fff;
}

.preview-item img {
    display: block;
    width: 100%;
    height: 150px;
    object-fit: cover;
}

.preview-name {
    padding: 8px;
    font-size: 11px;
    color: #6b7280;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
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

}

</style>

</head>


<body>

<?php include "includes/sidebar.php"; ?>


<main class="main-content">


<div class="page-header">

    <div>

        <h1>📚 Add Syllabus</h1>

        <p>
            Add syllabus text and syllabus page photos.
        </p>

    </div>


    <a
        href="syllabus.php"
        class="btn btn-secondary"
    >
        ← Back to Syllabus
    </a>

</div>


<div class="form-card">


<div class="form-title">

    <h2>Subject Syllabus Details</h2>

    <p>
        Select a subject and add its complete syllabus.
    </p>

</div>


<?php if ($error !== ""): ?>

<div class="alert alert-error">

    ❌
    <?php echo htmlspecialchars($error); ?>

</div>

<?php endif; ?>


<form
    method="POST"
    enctype="multipart/form-data"
>


<!-- SUBJECT -->

<div class="form-group">

<label for="subject_id">

    Subject
    <span class="required">*</span>

</label>


<select
    name="subject_id"
    id="subject_id"
    required
    onchange="showSubjectInfo()"
>

<option value="">
    -- Select Subject --
</option>


<?php if ($subjects && $subjects->num_rows > 0): ?>

<?php while ($subject = $subjects->fetch_assoc()): ?>

<option
    value="<?php echo (int)$subject["id"]; ?>"

    data-code="<?php
        echo htmlspecialchars($subject["subject_code"]);
    ?>"

    data-name="<?php
        echo htmlspecialchars($subject["subject_name"]);
    ?>"

    data-semester="<?php
        echo htmlspecialchars($subject["semester_number"] ?? "");
    ?>"

    data-credits="<?php
        echo htmlspecialchars($subject["credits"]);
    ?>"

    <?php

    if (
        $selected_subject_id ===
        (int)$subject["id"]
    ) {
        echo "selected";
    }

    ?>

>

<?php

echo htmlspecialchars(
    $subject["subject_code"]
    . " - "
    . $subject["subject_name"]
);

if ($subject["semester_number"] !== null) {

    echo " (Sem "
        . htmlspecialchars(
            $subject["semester_number"]
        )
        . ")";

}

?>

</option>

<?php endwhile; ?>

<?php endif; ?>

</select>


<div
    id="subjectInfo"
    class="subject-info"
></div>


<div class="help-text">

    Select the subject for which you want to add the syllabus.

</div>

</div>


<!-- SYLLABUS TEXT -->

<div class="form-group">

<label for="syllabus">

    Syllabus Text

</label>


<textarea
    name="syllabus"
    id="syllabus"
    placeholder="Enter syllabus text here...

Example:

Unit 1: Introduction
- Topic 1
- Topic 2
- Topic 3

Unit 2: Core Concepts
- Topic 1
- Topic 2

Unit 3: Advanced Concepts
- Topic 1
- Topic 2"
></textarea>


<div class="help-text">

    You can enter the syllabus in text format.
    This field is optional if you are uploading syllabus photos.

</div>

</div>


<!-- IMAGE UPLOAD -->

<div class="form-group">

<label>

    📷 Syllabus Photos

</label>


<div class="upload-box">

    <div class="upload-icon">
        📚
    </div>

    <div class="upload-title">

        Upload syllabus page photos

    </div>

    <div class="upload-subtitle">

        JPG, JPEG, PNG or WEBP • Maximum 2 MB per image

    </div>


    <input
        type="file"
        name="syllabus_images[]"
        id="syllabus_images"
        class="file-input"
        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
        multiple
    >


    <div
        id="previewContainer"
        class="preview-container"
    ></div>

</div>


<div class="help-text">

    You can select multiple syllabus pages at once.

</div>

</div>


<!-- ACTIONS -->

<div class="form-actions">

<button
    type="submit"
    class="btn btn-primary"
>

    💾 Save Syllabus

</button>


<a
    href="syllabus.php"
    class="btn btn-secondary"
>

    Cancel

</a>

</div>


</form>


</div>

</main>


<script>

/* -------------------------------------------------
   SUBJECT INFORMATION
------------------------------------------------- */

function showSubjectInfo() {

    const select =
        document.getElementById("subject_id");

    const info =
        document.getElementById("subjectInfo");

    const option =
        select.options[select.selectedIndex];


    if (
        !option ||
        !option.value
    ) {

        info.style.display = "none";
        info.innerHTML = "";

        return;
    }


    const code =
        option.dataset.code || "";

    const name =
        option.dataset.name || "";

    const semester =
        option.dataset.semester || "";

    const credits =
        option.dataset.credits || "";


    let text =
        "<strong>"
        + code
        + "</strong> — "
        + name;


    if (semester !== "") {

        text +=
            " &nbsp; | &nbsp; Semester "
            + semester;

    }


    if (credits !== "") {

        text +=
            " &nbsp; | &nbsp; Credits "
            + credits;

    }


    info.innerHTML = text;

    info.style.display = "block";
}


/* -------------------------------------------------
   IMAGE PREVIEW
------------------------------------------------- */

document
    .getElementById("syllabus_images")
    .addEventListener(
        "change",
        function () {

            const preview =
                document.getElementById(
                    "previewContainer"
                );

            preview.innerHTML = "";


            const files =
                this.files;


            for (
                let i = 0;
                i < files.length;
                i++
            ) {

                const file =
                    files[i];


                if (
                    !file.type.startsWith(
                        "image/"
                    )
                ) {
                    continue;
                }


                const reader =
                    new FileReader();


                reader.onload =
                    function (event) {

                        const item =
                            document.createElement(
                                "div"
                            );

                        item.className =
                            "preview-item";


                        const img =
                            document.createElement(
                                "img"
                            );

                        img.src =
                            event.target.result;


                        const name =
                            document.createElement(
                                "div"
                            );

                        name.className =
                            "preview-name";

                        name.textContent =
                            file.name;


                        item.appendChild(img);

                        item.appendChild(name);

                        preview.appendChild(item);

                    };


                reader.readAsDataURL(file);

            }

        }
    );


/* Show subject information on page load */

document.addEventListener(
    "DOMContentLoaded",
    function () {

        showSubjectInfo();

    }
);

</script>


</body>

</html>