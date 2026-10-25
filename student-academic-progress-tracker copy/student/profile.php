<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_student();

$user_id = (int) $_SESSION["user_id"];

/* =====================================================
   FETCH STUDENT PROFILE
===================================================== */

$stmt = $conn->prepare("
    SELECT
        s.id,
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
        s.photo,
        s.address,
        s.created_at,
        u.username,
        u.email
    FROM students s
    INNER JOIN users u
        ON u.id = s.user_id
    WHERE s.user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$student = $result->fetch_assoc();

$stmt->close();

if (!$student) {
    die("Student profile not found.");
}


/* =====================================================
   INITIAL
===================================================== */

$full_name = trim($student["full_name"]);

$name_parts = explode(" ", $full_name);

$initial = strtoupper(
    substr($name_parts[0], 0, 1)
);


/* =====================================================
   FORMAT DATE
===================================================== */

$birth_date = "Not provided";

if (!empty($student["birth_date"])) {

    $birth_date = date(
        "d M Y",
        strtotime($student["birth_date"])
    );
}


$created_date = "Not available";

if (!empty($student["created_at"])) {

    $created_date = date(
        "d M Y",
        strtotime($student["created_at"])
    );
}


/* =====================================================
   PHOTO
===================================================== */

$photo_path = "";

if (!empty($student["photo"])) {

    $possible_photo = "../" . $student["photo"];

    if (file_exists($possible_photo)) {

        $photo_path = $possible_photo;

    } elseif (file_exists("../uploads/students/" . $student["photo"])) {

        $photo_path =
            "../uploads/students/" . $student["photo"];
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

<title>My Profile | Student Academic Tracker</title>


<style>

/* =====================================================
   RESET
===================================================== */

* {
    box-sizing: border-box;
}


body {
    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f5f6fa;

    color: #1f2937;
}




/* =====================================================
   MAIN CONTENT
===================================================== */

.main-content {
    margin-left: 260px;

    padding: 32px;

    min-height: 100vh;
}


/* =====================================================
   PAGE HEADER
===================================================== */

.page-header {
    margin-bottom: 25px;
}


.page-header h1 {
    margin: 0;

    color: #1f2937;

    font-size: 30px;

    font-weight: 800;

    letter-spacing: -0.5px;
}


.page-header p {
    margin: 7px 0 0;

    color: #6b7280;

    font-size: 14px;
}


/* =====================================================
   PROFILE HERO
===================================================== */

.profile-hero {
    background: white;

    border-radius: 18px;

    padding: 26px;

    margin-bottom: 22px;

    box-shadow:
        0 6px 20px
        rgba(0, 0, 0, 0.05);

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 25px;
}


.profile-hero-left {
    display: flex;

    align-items: center;

    gap: 18px;

    min-width: 0;
}


/* =====================================================
   PROFILE PHOTO
===================================================== */

.profile-photo {
    width: 92px;
    height: 92px;

    border-radius: 18px;

    object-fit: cover;

    border: 4px solid #ede9fe;

    flex-shrink: 0;
}


.profile-avatar {
    width: 92px;
    height: 92px;

    border-radius: 18px;

    background:
        linear-gradient(
            135deg,
            #7c3aed,
            #4f46e5
        );

    color: white;

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 34px;

    font-weight: 800;

    flex-shrink: 0;
}


.profile-name h2 {
    margin: 0;

    color: #1f2937;

    font-size: 24px;

    font-weight: 800;
}


.profile-name p {
    margin: 6px 0 0;

    color: #6b7280;

    font-size: 13px;
}


.profile-name .prn {
    margin-top: 8px;

    color: #6d28d9;

    font-size: 12px;

    font-weight: 800;
}


/* =====================================================
   PROFILE HERO TAGS
===================================================== */

.hero-tags {
    display: flex;

    flex-wrap: wrap;

    justify-content: flex-end;

    gap: 8px;
}


.hero-tag {
    padding:
        8px
        11px;

    border-radius: 8px;

    background: #f3f4f6;

    color: #4b5563;

    font-size: 11px;

    font-weight: 700;

    white-space: nowrap;
}


/* =====================================================
   CONTENT GRID
===================================================== */

.profile-grid {
    display: grid;

    grid-template-columns:
        minmax(0, 1fr)
        minmax(0, 1fr);

    gap: 22px;

    max-width: 1100px;
}


/* =====================================================
   CARD
===================================================== */

.profile-card {
    background: white;

    border-radius: 16px;

    padding: 24px;

    box-shadow:
        0 6px 20px
        rgba(0, 0, 0, 0.04);
}


.card-title {
    margin: 0 0 18px;

    font-size: 18px;

    font-weight: 800;

    color: #1f2937;
}


/* =====================================================
   INFORMATION ROW
===================================================== */

.info-row {
    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    padding: 13px 0;

    border-bottom:
        1px solid #eef0f4;
}


.info-row:last-child {
    border-bottom: none;
}


.info-label {
    color: #6b7280;

    font-size: 12px;

    font-weight: 600;

    flex-shrink: 0;
}


.info-value {
    color: #1f2937;

    font-size: 13px;

    font-weight: 700;

    text-align: right;

    word-break: break-word;
}


/* =====================================================
   ACCOUNT STATUS
===================================================== */

.status-card {
    margin-top: 22px;

    background:
        linear-gradient(
            135deg,
            #f5f3ff,
            #eef2ff
        );

    border:
        1px solid #e5e7eb;

    border-radius: 14px;

    padding: 18px;
}


.status-title {
    font-size: 13px;

    font-weight: 800;

    color: #374151;

    margin-bottom: 12px;
}


.status-item {
    display: flex;

    justify-content: space-between;

    align-items: center;

    padding: 7px 0;
}


.status-label {
    color: #6b7280;

    font-size: 11px;
}


.status-value {
    color: #1f2937;

    font-size: 11px;

    font-weight: 700;
}


.active-badge {
    display: inline-block;

    padding:
        5px 9px;

    border-radius: 20px;

    background: #dcfce7;

    color: #15803d;

    font-size: 10px;

    font-weight: 800;
}


/* =====================================================
   CONTACT CARD
===================================================== */

.contact-icon {
    width: 38px;
    height: 38px;

    border-radius: 10px;

    background: #ede9fe;

    color: #6d28d9;

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 17px;

    flex-shrink: 0;
}


.contact-row {
    display: flex;

    align-items: center;

    gap: 12px;

    padding: 13px 0;

    border-bottom:
        1px solid #eef0f4;
}


.contact-row:last-child {
    border-bottom: none;
}


.contact-info {
    min-width: 0;
}


.contact-info small {
    display: block;

    color: #9ca3af;

    font-size: 10px;

    margin-bottom: 3px;
}


.contact-info strong {
    display: block;

    color: #374151;

    font-size: 13px;

    word-break: break-word;
}


/* =====================================================
   NOTE
===================================================== */

.profile-note {
    margin-top: 22px;

    padding: 15px 17px;

    border-radius: 11px;

    background: #f8fafc;

    border: 1px solid #e5e7eb;

    color: #64748b;

    font-size: 11px;

    line-height: 1.6;
}


/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 950px) {

    .profile-hero {
        align-items: flex-start;

        flex-direction: column;
    }

    .hero-tags {
        justify-content: flex-start;
    }

    .profile-grid {
        grid-template-columns: 1fr;
    }

}


@media (max-width: 700px) {

    .sidebar {
        position: relative;

        width: 100%;

        height: auto;
    }

    .main-content {
        margin-left: 0;

        padding: 20px;
    }

    .profile-hero-left {
        align-items: flex-start;

        flex-direction: column;
    }

    .profile-name h2 {
        font-size: 21px;
    }

}


@media (max-width: 500px) {

    .info-row {
        align-items: flex-start;

        flex-direction: column;

        gap: 5px;
    }

    .info-value {
        text-align: left;
    }

}

</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<?php include "includes/sidebar.php"; ?>

<!-- =====================================================
     MAIN CONTENT
===================================================== -->

<main class="main-content">


    <!-- PAGE HEADER -->

    <div class="page-header">

        <h1>
            My Profile
        </h1>

        <p>
            View your personal and academic information
        </p>

    </div>


    <!-- =================================================
         PROFILE HERO
    ================================================= -->

    <div class="profile-hero">


        <div class="profile-hero-left">


            <?php if ($photo_path !== ""): ?>

                <img
                    src="<?php echo htmlspecialchars($photo_path); ?>"
                    alt="Student Photo"
                    class="profile-photo"
                >

            <?php else: ?>

                <div class="profile-avatar">

                    <?php
                    echo htmlspecialchars($initial);
                    ?>

                </div>

            <?php endif; ?>


            <div class="profile-name">

                <h2>

                    <?php
                    echo htmlspecialchars(
                        $student["full_name"]
                    );
                    ?>

                </h2>


                <p>

                    <?php
                    echo htmlspecialchars(
                        $student["course"]
                    );
                    ?>

                    •

                    <?php
                    echo htmlspecialchars(
                        $student["department"]
                    );
                    ?>

                </p>


                <div class="prn">

                    PRN:
                    <?php
                    echo htmlspecialchars(
                        $student["prn"]
                    );
                    ?>

                </div>

            </div>

        </div>


        <div class="hero-tags">


            <div class="hero-tag">

                Semester
                <?php
                echo htmlspecialchars(
                    $student["current_semester"]
                );
                ?>

            </div>


            <div class="hero-tag">

                Roll No:
                <?php
                echo htmlspecialchars(
                    $student["roll_no"] ?: "N/A"
                );
                ?>

            </div>


            <div class="hero-tag">

                <?php
                echo htmlspecialchars(
                    $student["department"]
                );
                ?>

            </div>

        </div>

    </div>


    <!-- =================================================
         PROFILE INFORMATION
    ================================================= -->

    <div class="profile-grid">


        <!-- =============================================
             PERSONAL INFORMATION
        ============================================== -->

        <div class="profile-card">

            <h2 class="card-title">
                Personal Information
            </h2>


            <div class="info-row">

                <div class="info-label">
                    Full Name
                </div>

                <div class="info-value">

                    <?php
                    echo htmlspecialchars(
                        $student["full_name"]
                    );
                    ?>

                </div>

            </div>


            <div class="info-row">

                <div class="info-label">
                    PRN
                </div>

                <div class="info-value">

                    <?php
                    echo htmlspecialchars(
                        $student["prn"]
                    );
                    ?>

                </div>

            </div>


            <div class="info-row">

                <div class="info-label">
                    Roll Number
                </div>

                <div class="info-value">

                    <?php
                    echo htmlspecialchars(
                        $student["roll_no"] ?: "Not provided"
                    );
                    ?>

                </div>

            </div>


            <div class="info-row">

                <div class="info-label">
                    Gender
                </div>

                <div class="info-value">

                    <?php
                    echo htmlspecialchars(
                        $student["gender"] ?: "Not provided"
                    );
                    ?>

                </div>

            </div>


            <div class="info-row">

                <div class="info-label">
                    Birth Date
                </div>

                <div class="info-value">

                    <?php
                    echo htmlspecialchars(
                        $birth_date
                    );
                    ?>

                </div>

            </div>


            <div class="info-row">

                <div class="info-label">
                    Phone Number
                </div>

                <div class="info-value">

                    <?php
                    echo htmlspecialchars(
                        $student["phone"] ?: "Not provided"
                    );
                    ?>

                </div>

            </div>


            <!-- ACCOUNT STATUS -->

            <div class="status-card">

                <div class="status-title">
                    Account Status
                </div>


                <div class="status-item">

                    <span class="status-label">
                        Account
                    </span>

                    <span class="active-badge">
                        ACTIVE
                    </span>

                </div>


                <div class="status-item">

                    <span class="status-label">
                        Member Since
                    </span>

                    <span class="status-value">

                        <?php
                        echo htmlspecialchars(
                            $created_date
                        );
                        ?>

                    </span>

                </div>

            </div>

        </div>


        <!-- =============================================
             ACADEMIC + ACCOUNT INFORMATION
        ============================================== -->

        <div>


            <!-- ACADEMIC INFORMATION -->

            <div class="profile-card">

                <h2 class="card-title">
                    Academic Information
                </h2>


                <div class="info-row">

                    <div class="info-label">
                        Course
                    </div>

                    <div class="info-value">

                        <?php
                        echo htmlspecialchars(
                            $student["course"]
                        );
                        ?>

                    </div>

                </div>


                <div class="info-row">

                    <div class="info-label">
                        Department
                    </div>

                    <div class="info-value">

                        <?php
                        echo htmlspecialchars(
                            $student["department"]
                        );
                        ?>

                    </div>

                </div>


                <div class="info-row">

                    <div class="info-label">
                        Admission Year
                    </div>

                    <div class="info-value">

                        <?php
                        echo htmlspecialchars(
                            $student["admission_year"]
                        );
                        ?>

                    </div>

                </div>


                <div class="info-row">

                    <div class="info-label">
                        Current Semester
                    </div>

                    <div class="info-value">

                        Semester
                        <?php
                        echo htmlspecialchars(
                            $student["current_semester"]
                        );
                        ?>

                    </div>

                </div>

            </div>


            <!-- ACCOUNT INFORMATION -->

            <div
                class="profile-card"
                style="margin-top:22px;"
            >

                <h2 class="card-title">
                    Account Information
                </h2>


                <div class="contact-row">

                    <div class="contact-icon">
                        👤
                    </div>

                    <div class="contact-info">

                        <small>
                            Username
                        </small>

                        <strong>

                            <?php
                            echo htmlspecialchars(
                                $student["username"]
                            );
                            ?>

                        </strong>

                    </div>

                </div>


                <div class="contact-row">

                    <div class="contact-icon">
                        ✉️
                    </div>

                    <div class="contact-info">

                        <small>
                            Email Address
                        </small>

                        <strong>

                            <?php
                            echo htmlspecialchars(
                                $student["email"]
                            );
                            ?>

                        </strong>

                    </div>

                </div>


                <div class="contact-row">

                    <div class="contact-icon">
                        📱
                    </div>

                    <div class="contact-info">

                        <small>
                            Phone Number
                        </small>

                        <strong>

                            <?php
                            echo htmlspecialchars(
                                $student["phone"]
                                ?: "Not provided"
                            );
                            ?>

                        </strong>

                    </div>

                </div>


                <!-- ADDRESS -->

                <div class="contact-row">

                    <div class="contact-icon">
                        📍
                    </div>

                    <div class="contact-info">

                        <small>
                            Address
                        </small>

                        <strong>

                            <?php
                            echo nl2br(
                                htmlspecialchars(
                                    $student["address"]
                                    ?: "Not provided"
                                )
                            );
                            ?>

                        </strong>

                    </div>

                </div>

            </div>


        </div>


    </div>


    <!-- NOTE -->

    <div class="profile-note">

        <strong>
            Note:
        </strong>

        Your profile information is managed by the
        Teacher/Admin. If any information is incorrect,
        please contact your college administrator.

    </div>


</main>


</body>

</html>