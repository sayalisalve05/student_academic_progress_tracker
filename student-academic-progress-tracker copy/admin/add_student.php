<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();
$error = "";
$photo_name = null;
/* =========================================================
   CREATE UPLOAD DIRECTORY
========================================================= */

$upload_dir = "../uploads/students/";

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

/* =========================================================
   FORM SUBMISSION
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $prn = trim($_POST["prn"] ?? "");
    $roll_no = trim($_POST["roll_no"] ?? "");
    $full_name = trim($_POST["full_name"] ?? "");
    $course = trim($_POST["course"] ?? "");
    $department = trim($_POST["department"] ?? "");

    $admission_year = (int)($_POST["admission_year"] ?? 0);
    $current_semester = (int)($_POST["current_semester"] ?? 0);

    $gender = $_POST["gender"] ?? "";
    $birth_date = $_POST["birth_date"] ?? "";
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");

    $photo_name = null; 
     $error = "";


    /* =====================================================
       VALIDATION
    ====================================================== */

    if (
        $username === "" ||
        $email === "" ||
        $password === "" ||
        $prn === "" ||
        $full_name === "" ||
        $course === "" ||
        $department === "" ||
        $admission_year <= 0 ||
        $current_semester <= 0
    ) {

        $error = "Please fill in all required fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif ($current_semester < 1 || $current_semester > 6) {

        $error = "Semester must be between 1 and 6.";

    } else {


        /* =================================================
           PHOTO UPLOAD
        ================================================== */

        if (
            isset($_FILES["photo"]) &&
            $_FILES["photo"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {

            if ($_FILES["photo"]["error"] !== UPLOAD_ERR_OK) {

                $error = "There was an error uploading the photo.";

            } else {

                $file_tmp = $_FILES["photo"]["tmp_name"];
                $file_size = $_FILES["photo"]["size"];

                $original_name =
                    $_FILES["photo"]["name"];

                $extension =
                    strtolower(
                        pathinfo(
                            $original_name,
                            PATHINFO_EXTENSION
                        )
                    );


                /* Allowed image types */

                $allowed_extensions = [
                    "jpg",
                    "jpeg",
                    "png",
                    "webp"
                ];


                if (!in_array(
                    $extension,
                    $allowed_extensions,
                    true
                )) {

                    $error =
                        "Only JPG, JPEG, PNG and WEBP images are allowed.";

                } elseif ($file_size > 2 * 1024 * 1024) {

                    $error =
                        "Photo size must be less than 2 MB.";

                } elseif (
                    @getimagesize($file_tmp) === false
                ) {

                    $error =
                        "The uploaded file is not a valid image.";

                } else {

                    /*
                     * Create a unique filename so two
                     * students cannot overwrite each other.
                     */

                    $photo_name =
                        "student_" .
                        time() .
                        "_" .
                        bin2hex(random_bytes(5)) .
                        "." .
                        $extension;


                    $photo_path =
                        $upload_dir . $photo_name;


                    if (!move_uploaded_file(
                        $file_tmp,
                        $photo_path
                    )) {

                        $error =
                            "Unable to save the uploaded photo.";

                        $photo_name = null;
                    }
                }
            }
        }


        /* =================================================
           DATABASE INSERT
        ================================================== */

        if ($error === "") {

            try {

                $conn->begin_transaction();


                /* -----------------------------------------
                   CHECK USERNAME / EMAIL
                ------------------------------------------ */

                $check_user = $conn->prepare(
                    "SELECT id
                     FROM users
                     WHERE username = ?
                        OR email = ?
                     LIMIT 1"
                );

                $check_user->bind_param(
                    "ss",
                    $username,
                    $email
                );

                $check_user->execute();

                $existing =
                    $check_user->get_result();

                $check_user->close();


                if ($existing->num_rows > 0) {

                    throw new Exception(
                        "Username or email already exists."
                    );
                }


                /* -----------------------------------------
                   CHECK PRN
                ------------------------------------------ */

                $check_prn = $conn->prepare(
                    "SELECT id
                     FROM students
                     WHERE prn = ?
                     LIMIT 1"
                );

                $check_prn->bind_param(
                    "s",
                    $prn
                );

                $check_prn->execute();

                $existing_prn =
                    $check_prn->get_result();

                $check_prn->close();


                if ($existing_prn->num_rows > 0) {

                    throw new Exception(
                        "This PRN already exists."
                    );
                }


                /* -----------------------------------------
                   CREATE USER
                ------------------------------------------ */

                $hashed_password =
                    password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                $user_stmt = $conn->prepare(
                    "INSERT INTO users
                    (
                        username,
                        email,
                        password,
                        role
                    )
                    VALUES (?, ?, ?, 'student')"
                );

                $user_stmt->bind_param(
                    "sss",
                    $username,
                    $email,
                    $hashed_password
                );

                if (!$user_stmt->execute()) {

                    throw new Exception(
                        "Unable to create student account."
                    );
                }

                $user_id =
                    $user_stmt->insert_id;

                $user_stmt->close();


                /* -----------------------------------------
                   CREATE STUDENT
                ------------------------------------------ */

                $student_stmt = $conn->prepare(
                    "INSERT INTO students
                    (
                        user_id,
                        prn,
                        roll_no,
                        full_name,
                        course,
                        admission_year,
                        current_semester,
                        department,
                        gender,
                        birth_date,
                        phone,
                        address,
                        photo
                    )
                    VALUES
                    (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                    )"
                );


                /*
                 * Convert blank optional values to NULL
                 */

                $roll_value =
                    $roll_no !== ""
                    ? $roll_no
                    : null;

                $gender_value =
                    in_array(
                        $gender,
                        ["Male", "Female", "Other"],
                        true
                    )
                    ? $gender
                    : null;

                $birth_value =
                    $birth_date !== ""
                    ? $birth_date
                    : null;

                $phone_value =
                    $phone !== ""
                    ? $phone
                    : null;

                $address_value =
                    $address !== ""
                    ? $address
                    : null;


                $student_stmt->bind_param(
                    "issssiissssss",
                    $user_id,
                    $prn,
                    $roll_value,
                    $full_name,
                    $course,
                    $admission_year,
                    $current_semester,
                    $department,
                    $gender_value,
                    $birth_value,
                    $phone_value,
                    $address_value,
                    $photo_name
                );


                if (!$student_stmt->execute()) {

                    throw new Exception(
                        "Unable to create student record."
                    );
                }

                $student_stmt->close();


                /* -----------------------------------------
                   FINISH
                ------------------------------------------ */

                $conn->commit();

                header(
                    "Location: students.php?added=1"
                );

                exit;


            } catch (Exception $e) {

                $conn->rollback();

                /*
                 * Remove uploaded photo if database
                 * insertion failed.
                 */

                if (
                    $photo_name !== null &&
                    file_exists(
                        $upload_dir . $photo_name
                    )
                ) {

                    unlink(
                        $upload_dir . $photo_name
                    );
                }

                $error =
                    $e->getMessage();
            }
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Add Student | Student Academic Tracker</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f7fb;
    color: #111827;
}

.main-content {
    margin-left: 260px !important;
    min-height: 100vh;
    padding: 30px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.page-header h1 {
    margin: 0 0 6px;
    font-size: 28px;
}

.page-header p {
    margin: 0;
    color: #6b7280;
    font-size: 13px;
}

.back-btn {
    display: inline-block;
    padding: 11px 16px;
    border-radius: 10px;
    background: #111827;
    color: white;
    font-size: 13px;
    font-weight: 700;
}

.form-card {
    max-width: 1050px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 18px;
    padding: 28px;
    box-shadow: 0 5px 20px rgba(15, 23, 42, 0.05);
}

.section-title {
    margin: 0 0 18px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 17px;
    color: #111827;
}

.form-section {
    margin-bottom: 30px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group.full {
    grid-column: 1 / -1;
}

label {
    font-size: 12px;
    font-weight: 700;
    color: #374151;
    margin-bottom: 7px;
}

.required {
    color: #dc2626;
}

input,
select,
textarea {
    width: 100%;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    padding: 11px 12px;
    font-family: inherit;
    font-size: 13px;
    color: #111827;
    background: white;
    outline: none;
}

input,
select {
    height: 44px;
}

textarea {
    min-height: 95px;
    resize: vertical;
}

input:focus,
select:focus,
textarea:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99,102,241,.08);
}

/* PHOTO */

.photo-upload {
    display: flex;
    gap: 22px;
    align-items: center;
    padding: 18px;
    border: 1.5px dashed #c7d2fe;
    border-radius: 14px;
    background: #f8f7ff;
}

.photo-preview {
    width: 105px;
    height: 105px;
    border-radius: 50%;
    overflow: hidden;
    background: #eef2ff;
    border: 3px solid #e0e7ff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6366f1;
    font-size: 35px;
    flex-shrink: 0;
}

.photo-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: none;
}

.photo-info {
    flex: 1;
}

.photo-info strong {
    display: block;
    color: #111827;
    font-size: 14px;
    margin-bottom: 5px;
}

.photo-info p {
    margin: 0 0 10px;
    color: #6b7280;
    font-size: 11px;
    line-height: 1.5;
}

.file-input {
    border: none;
    padding: 0;
    height: auto;
    background: transparent;
}

.alert {
    padding: 13px 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 13px;
}

.alert.error {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #b91c1c;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding-top: 10px;
}

.cancel-btn,
.save-btn {
    border: none;
    border-radius: 10px;
    padding: 12px 20px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.cancel-btn {
    background: #f3f4f6;
    color: #374151;
}

.save-btn {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: white;
    box-shadow: 0 7px 18px rgba(79,70,229,.22);
}

@media (max-width: 850px) {

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

}

@media (max-width: 600px) {

    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .photo-upload {
        flex-direction: column;
        text-align: center;
    }

}

</style>

</head>

<body>

<?php include "includes/sidebar.php"; ?>


<main class="main-content">


    <div class="page-header">

        <div>

            <h1>
                Add Student
            </h1>

            <p>
                Create a student account and academic profile.
            </p>

        </div>

        <a
            href="students.php"
            class="back-btn"
        >
            ← Back to Students
        </a>

    </div>


    <div class="form-card">


        <?php if ($error !== ""): ?>

            <div class="alert error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <form
            method="post"
            enctype="multipart/form-data"
        >


            <!-- =========================================
                 ACCOUNT INFORMATION
            ========================================== -->

            <div class="form-section">

                <h2 class="section-title">
                    🔐 Account Information
                </h2>

                <div class="form-grid">


                    <div class="form-group">

                        <label>
                            Username <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            name="username"
                            value="<?= htmlspecialchars(
                                $_POST["username"] ?? ""
                            ) ?>"
                            placeholder="Enter username"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Email <span class="required">*</span>
                        </label>

                        <input
                            type="email"
                            name="email"
                            value="<?= htmlspecialchars(
                                $_POST["email"] ?? ""
                            ) ?>"
                            placeholder="student@example.com"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Password <span class="required">*</span>
                        </label>

                        <input
                            type="password"
                            name="password"
                            placeholder="Create password"
                            required
                        >

                    </div>


                </div>

            </div>


            <!-- =========================================
                 PHOTO
            ========================================== -->

            <div class="form-section">

                <h2 class="section-title">
                    📷 Student Photo
                </h2>


                <div class="photo-upload">

                    <div class="photo-preview">

                        <span id="photoIcon">
                            👤
                        </span>

                        <img
                            id="photoPreview"
                            alt="Photo Preview"
                        >

                    </div>


                    <div class="photo-info">

                        <strong>
                            Upload Student Photo
                        </strong>

                        <p>
                            JPG, JPEG, PNG or WEBP.
                            Maximum size: 2 MB.
                        </p>

                        <input
                            class="file-input"
                            type="file"
                            name="photo"
                            id="photo"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        >

                    </div>

                </div>

            </div>


            <!-- =========================================
                 PERSONAL INFORMATION
            ========================================== -->

            <div class="form-section">

                <h2 class="section-title">
                    👤 Personal Information
                </h2>

                <div class="form-grid">


                    <div class="form-group">

                        <label>
                            Full Name <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            name="full_name"
                            value="<?= htmlspecialchars(
                                $_POST["full_name"] ?? ""
                            ) ?>"
                            placeholder="Enter full name"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            PRN <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            name="prn"
                            value="<?= htmlspecialchars(
                                $_POST["prn"] ?? ""
                            ) ?>"
                            placeholder="Enter PRN"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Roll Number
                        </label>

                        <input
                            type="text"
                            name="roll_no"
                            value="<?= htmlspecialchars(
                                $_POST["roll_no"] ?? ""
                            ) ?>"
                            placeholder="Enter roll number"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Gender
                        </label>

                        <select name="gender">

                            <option value="">
                                Select Gender
                            </option>

                            <option value="Male"
                                <?= (($_POST["gender"] ?? "") === "Male")
                                    ? "selected"
                                    : "" ?>>
                                Male
                            </option>

                            <option value="Female"
                                <?= (($_POST["gender"] ?? "") === "Female")
                                    ? "selected"
                                    : "" ?>>
                                Female
                            </option>

                            <option value="Other"
                                <?= (($_POST["gender"] ?? "") === "Other")
                                    ? "selected"
                                    : "" ?>>
                                Other
                            </option>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>
                            Birth Date
                        </label>

                        <input
                            type="date"
                            name="birth_date"
                            value="<?= htmlspecialchars(
                                $_POST["birth_date"] ?? ""
                            ) ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Phone
                        </label>

                        <input
                            type="text"
                            name="phone"
                            value="<?= htmlspecialchars(
                                $_POST["phone"] ?? ""
                            ) ?>"
                            placeholder="Enter phone number"
                        >

                    </div>


                    <div class="form-group full">

                        <label>
                            Address
                        </label>

                        <textarea
                            name="address"
                            placeholder="Enter student address"
                        ><?= htmlspecialchars(
                            $_POST["address"] ?? ""
                        ) ?></textarea>

                    </div>


                </div>

            </div>


            <!-- =========================================
                 ACADEMIC INFORMATION
            ========================================== -->

            <div class="form-section">

                <h2 class="section-title">
                    🎓 Academic Information
                </h2>

                <div class="form-grid">


                    <div class="form-group">

                        <label>
                            Course <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            name="course"
                            value="<?= htmlspecialchars(
                                $_POST["course"] ?? "B.Sc. Computer Science"
                            ) ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Department <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            name="department"
                            value="<?= htmlspecialchars(
                                $_POST["department"] ?? "Computer Science"
                            ) ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Admission Year <span class="required">*</span>
                        </label>

                        <input
                            type="number"
                            name="admission_year"
                            value="<?= htmlspecialchars(
                                $_POST["admission_year"] ?? date("Y")
                            ) ?>"
                            min="2000"
                            max="2100"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Current Semester <span class="required">*</span>
                        </label>

                        <select
                            name="current_semester"
                            required
                        >

                            <option value="">
                                Select Semester
                            </option>

                            <?php for ($i = 1; $i <= 6; $i++): ?>

                                <option
                                    value="<?= $i ?>"
                                    <?= (
                                        (int)(
                                            $_POST[
                                                "current_semester"
                                            ] ?? 0
                                        ) === $i
                                    )
                                        ? "selected"
                                        : ""
                                    ?>
                                >
                                    Semester <?= $i ?>
                                </option>

                            <?php endfor; ?>

                        </select>

                    </div>


                </div>

            </div>


            <!-- =========================================
                 BUTTONS
            ========================================== -->

            <div class="form-actions">

                <a
                    href="students.php"
                    class="cancel-btn"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="save-btn"
                >
                    ➕ Add Student
                </button>

            </div>


        </form>

    </div>

</main>


<script>

/* =========================================================
   PHOTO PREVIEW
========================================================= */

const photoInput =
    document.getElementById("photo");

const photoPreview =
    document.getElementById("photoPreview");

const photoIcon =
    document.getElementById("photoIcon");


if (photoInput) {

    photoInput.addEventListener(
        "change",
        function () {

            const file =
                this.files[0];

            if (!file) {

                photoPreview.style.display =
                    "none";

                photoIcon.style.display =
                    "block";

                return;
            }


            const reader =
                new FileReader();


            reader.onload =
                function (event) {

                    photoPreview.src =
                        event.target.result;

                    photoPreview.style.display =
                        "block";

                    photoIcon.style.display =
                        "none";
                };


            reader.readAsDataURL(file);

        }
    );

}

</script>


</body>

</html>