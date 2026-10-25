<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

$success = "";
$error = "";

$student_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($student_id <= 0) {
    die("Invalid student ID.");
}


/*
|--------------------------------------------------------------------------
| Fetch Student
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        s.id,
        s.user_id,
        s.prn,
        s.roll_no,
        s.full_name,
        s.course,
        s.department,
        s.admission_year,
        s.current_semester,
        s.gender,
        s.birth_date,
        s.phone,
        s.address,
        s.photo,
        u.username,
        u.email
    FROM students s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.id = ?
    LIMIT 1
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Student not found.");
}

$student = $result->fetch_assoc();


/*
|--------------------------------------------------------------------------
| Update Student
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = trim($_POST["full_name"] ?? "");
    $prn = trim($_POST["prn"] ?? "");
    $roll_no = trim($_POST["roll_no"] ?? "");
    $gender = $_POST["gender"] ?? "";
    $birth_date = $_POST["birth_date"] ?? "";
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");

    $course = trim($_POST["course"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $admission_year = trim($_POST["admission_year"] ?? "");
    $current_semester = trim($_POST["current_semester"] ?? "");

    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");

    $new_password = $_POST["password"] ?? "";
    $new_photo = null;
$upload_dir = "../uploads/students/";

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] !== UPLOAD_ERR_NO_FILE) {

    if ($_FILES["photo"]["error"] !== UPLOAD_ERR_OK) {
        throw new Exception("Photo upload failed.");
    }

    if ($_FILES["photo"]["size"] > 2 * 1024 * 1024) {
        throw new Exception("Photo size must be less than 2 MB.");
    }

    $allowed_extensions = ["jpg", "jpeg", "png", "webp"];

    $extension = strtolower(
        pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION)
    );

    if (!in_array($extension, $allowed_extensions, true)) {
        throw new Exception("Only JPG, JPEG, PNG and WEBP photos are allowed.");
    }

    if (getimagesize($_FILES["photo"]["tmp_name"]) === false) {
        throw new Exception("Please upload a valid image.");
    }

    $new_photo = "student_" . time() . "_" . bin2hex(random_bytes(5)) . "." . $extension;

    if (!move_uploaded_file(
        $_FILES["photo"]["tmp_name"],
        $upload_dir . $new_photo
    )) {
        throw new Exception("Unable to save the uploaded photo.");
    }
}


    /*
    |--------------------------------------------------------------------------
    | Required Fields
    |--------------------------------------------------------------------------
    */

    if (
        $full_name === "" ||
        $prn === "" ||
        $course === "" ||
        $department === "" ||
        $admission_year === "" ||
        $current_semester === "" ||
        $username === "" ||
        $email === ""
    ) {

        $error = "Please fill all required fields.";

    } else {

        // Convert empty optional values to NULL
        $roll_no = ($roll_no === "") ? null : $roll_no;
        $gender = ($gender === "") ? null : $gender;
        $birth_date = ($birth_date === "") ? null : $birth_date;
        $phone = ($phone === "") ? null : $phone;
        $address = ($address === "") ? null : $address;


        try {

            $conn->begin_transaction();


            /*
            |--------------------------------------------------------------------------
            | Check Duplicate Username / Email
            |--------------------------------------------------------------------------
            */

            $checkUser = $conn->prepare("
                SELECT id
                FROM users
                WHERE (username = ? OR email = ?)
                AND id != ?
                LIMIT 1
            ");

            $checkUser->bind_param(
                "ssi",
                $username,
                $email,
                $student["user_id"]
            );

            $checkUser->execute();

            $checkResult = $checkUser->get_result();

            if ($checkResult->num_rows > 0) {
                throw new Exception("Username or email already exists.");
            }


            /*
            |--------------------------------------------------------------------------
            | Check Duplicate PRN
            |--------------------------------------------------------------------------
            */

            $checkPRN = $conn->prepare("
                SELECT id
                FROM students
                WHERE prn = ?
                AND id != ?
                LIMIT 1
            ");

            $checkPRN->bind_param(
                "si",
                $prn,
                $student_id
            );

            $checkPRN->execute();

            $prnResult = $checkPRN->get_result();

            if ($prnResult->num_rows > 0) {
                throw new Exception("PRN already exists.");
            }


            /*
            |--------------------------------------------------------------------------
            | Update Users Table
            |--------------------------------------------------------------------------
            */

            if ($new_password !== "") {

                $hashedPassword = password_hash(
                    $new_password,
                    PASSWORD_DEFAULT
                );

                $userStmt = $conn->prepare("
                    UPDATE users
                    SET username = ?,
                        email = ?,
                        password = ?
                    WHERE id = ?
                ");

                $userStmt->bind_param(
                    "sssi",
                    $username,
                    $email,
                    $hashedPassword,
                    $student["user_id"]
                );

            } else {

                $userStmt = $conn->prepare("
                    UPDATE users
                    SET username = ?,
                        email = ?
                    WHERE id = ?
                ");

                $userStmt->bind_param(
                    "ssi",
                    $username,
                    $email,
                    $student["user_id"]
                );
            }

            $userStmt->execute();


            /*
            |--------------------------------------------------------------------------
            | Update Students Table
            |--------------------------------------------------------------------------
            */

            $studentStmt = $conn->prepare("
                UPDATE students
SET
    prn = ?,
    roll_no = ?,
    full_name = ?,
    course = ?,
    admission_year = ?,
    current_semester = ?,
    department = ?,
    gender = ?,
    birth_date = ?,
    phone = ?,
    address = ?,
    photo = COALESCE(?, photo)
WHERE id = ?
            ");

            $studentStmt->bind_param(
    "ssssisssssssi",
    $prn,
    $roll_no,
    $full_name,
    $course,
    $admission_year,
    $current_semester,
    $department,
    $gender,
    $birth_date,
    $phone,
    $address,
    $new_photo,
    $student_id
);
            $studentStmt->execute();


            /*
            |--------------------------------------------------------------------------
            | Finish
            |--------------------------------------------------------------------------
            */

            $conn->commit();

            $success = "Student updated successfully.";


            // Reload updated student
            $stmt = $conn->prepare("
                SELECT
                    s.id,
                    s.user_id,
                    s.prn,
                    s.roll_no,
                    s.full_name,
                    s.course,
                    s.department,
                    s.admission_year,
                    s.current_semester,
                    s.gender,
                    s.birth_date,
                    s.phone,
                    s.address,
                    u.username,
                    u.email
                FROM students s
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.id = ?
                LIMIT 1
            ");

            $stmt->bind_param("i", $student_id);
            $stmt->execute();

            $student = $stmt->get_result()->fetch_assoc();

        } catch (Exception $e) {

            $conn->rollback();

            $error = "Database Error: " . $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Edit Student | Student Academic Tracker</title>

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
    color: #111827;
}

.page-header p {
    margin-top: 8px;
    color: #6b7280;
}

.form-container {
    background: white;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.06);
}

.section {
    margin-bottom: 35px;
}

.section-title {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
    color: #4f46e5;
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

label {
    font-weight: 600;
    margin-bottom: 7px;
    color: #374151;
}

.required {
    color: #ef4444;
}

input,
select,
textarea {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid #d1d5db;
    border-radius: 9px;
    font-size: 15px;
    outline: none;
    background: white;
}

input:focus,
select:focus,
textarea:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
}

textarea {
    min-height: 100px;
    resize: vertical;
}

.password-note {
    margin-top: 6px;
    font-size: 12px;
    color: #6b7280;
}

.buttons {
    display: flex;
    gap: 12px;
    margin-top: 10px;
}

.btn {
    padding: 12px 22px;
    border: none;
    border-radius: 9px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
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

.alert {
    padding: 14px 18px;
    border-radius: 9px;
    margin-bottom: 20px;
    font-weight: 600;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
}

@media (max-width: 800px) {

    .main-content {
        margin-left: 0 !important;
        padding: 20px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

}

</style>

</head>

<body>

<?php include "includes/sidebar.php"; ?>


<div class="main-content">

    <div class="page-header">

        <h1>Edit Student</h1>

        <p>
            Update student profile, academic information and login details.
        </p>

    </div>


    <?php if ($success): ?>

        <div class="alert alert-success">
            <?= htmlspecialchars($success) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="alert alert-error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <div class="form-container">

    <form method="POST" enctype="multipart/form-data">


            <!-- Personal Information -->

            <div class="section">

                <div class="section-title">
                    Personal Information
                </div>

                <div class="form-grid">
                    <div class="form-group full">

    <label>
        Student Photo
    </label>

    <?php if (!empty($student["photo"])): ?>

        <div style="margin-bottom: 12px;">

            <img
                src="../uploads/students/<?= htmlspecialchars($student["photo"]) ?>"
                alt="Current Student Photo"
                style="
                    width: 100px;
                    height: 100px;
                    object-fit: cover;
                    border-radius: 12px;
                    border: 2px solid #e5e7eb;
                "
            >

        </div>

    <?php endif; ?>

    <input
        type="file"
        name="photo"
        accept=".jpg,.jpeg,.png,.webp"
        id="photoInput"
    >

    <div class="password-note">
        JPG, JPEG, PNG or WEBP. Maximum size: 2 MB.
    </div>

    <div id="photoPreview" style="margin-top: 12px;"></div>

</div>


                    <div class="form-group">

                        <label>
                            Full Name <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            name="full_name"
                            value="<?= htmlspecialchars($student["full_name"] ?? "") ?>"
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
                            value="<?= htmlspecialchars($student["prn"] ?? "") ?>"
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
                            value="<?= htmlspecialchars($student["roll_no"] ?? "") ?>"
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

                            <option
                                value="Male"
                                <?= (($student["gender"] ?? "") === "Male") ? "selected" : "" ?>
                            >
                                Male
                            </option>

                            <option
                                value="Female"
                                <?= (($student["gender"] ?? "") === "Female") ? "selected" : "" ?>
                            >
                                Female
                            </option>

                            <option
                                value="Other"
                                <?= (($student["gender"] ?? "") === "Other") ? "selected" : "" ?>
                            >
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
                            value="<?= htmlspecialchars($student["birth_date"] ?? "") ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Phone
                        </label>

                        <input
                            type="text"
                            name="phone"
                            value="<?= htmlspecialchars($student["phone"] ?? "") ?>"
                        >

                    </div>


                    <div class="form-group full">

                        <label>
                            Address
                        </label>

                        <textarea name="address"><?= htmlspecialchars($student["address"] ?? "") ?></textarea>

                    </div>

                </div>

            </div>


            <!-- Academic Information -->

            <div class="section">

                <div class="section-title">
                    Academic Information
                </div>

                <div class="form-grid">


                    <div class="form-group">

                        <label>
                            Course <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            name="course"
                            value="<?= htmlspecialchars($student["course"] ?? "") ?>"
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
                            value="<?= htmlspecialchars($student["department"] ?? "") ?>"
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
                            min="2000"
                            max="2100"
                            value="<?= htmlspecialchars($student["admission_year"] ?? "") ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Current Semester <span class="required">*</span>
                        </label>

                        <select name="current_semester" required>

                            <option value="">
                                Select Semester
                            </option>

                            <?php for ($i = 1; $i <= 6; $i++): ?>

                                <option
                                    value="<?= $i ?>"
                                    <?= ((int)($student["current_semester"] ?? 0) === $i) ? "selected" : "" ?>
                                >
                                    Semester <?= $i ?>
                                </option>

                            <?php endfor; ?>

                        </select>

                    </div>

                </div>

            </div>


            <!-- Login Information -->

            <div class="section">

                <div class="section-title">
                    Student Login
                </div>

                <div class="form-grid">


                    <div class="form-group">

                        <label>
                            Username <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            name="username"
                            value="<?= htmlspecialchars($student["username"] ?? "") ?>"
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
                            value="<?= htmlspecialchars($student["email"] ?? "") ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            New Password
                        </label>

                        <input
                            type="password"
                            name="password"
                            placeholder="Leave blank to keep current password"
                        >

                        <div class="password-note">
                            Only enter a password if you want to change it.
                        </div>

                    </div>

                </div>

            </div>


            <div class="buttons">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Save Changes
                </button>


                <a
                    href="students.php"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>
<script>

document.getElementById("photoInput").addEventListener("change", function () {

    const preview = document.getElementById("photoPreview");
    const file = this.files[0];

    preview.innerHTML = "";

    if (!file) {
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        alert("Photo size must be less than 2 MB.");
        this.value = "";
        return;
    }

    const allowedTypes = [
        "image/jpeg",
        "image/png",
        "image/webp"
    ];

    if (!allowedTypes.includes(file.type)) {
        alert("Only JPG, JPEG, PNG and WEBP photos are allowed.");
        this.value = "";
        return;
    }

    const img = document.createElement("img");

    img.src = URL.createObjectURL(file);

    img.style.width = "100px";
    img.style.height = "100px";
    img.style.objectFit = "cover";
    img.style.borderRadius = "12px";
    img.style.border = "2px solid #6366f1";

    preview.appendChild(img);

});

</script>

</body>

</html>