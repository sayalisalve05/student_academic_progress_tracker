<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

$error = "";


/* =========================================================
   FETCH STUDENTS
   ========================================================= */

$students = [];

$stmt = $conn->prepare(
    "SELECT id, full_name, prn, current_semester
     FROM students
     ORDER BY full_name ASC"
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}


/* =========================================================
   FETCH ALL SUBJECTS
   ========================================================= */

$subjects = [];

$stmt = $conn->prepare(
    "SELECT
        subjects.id,
        subjects.subject_code,
        subjects.subject_name,
        subjects.credits,
        subjects.semester_id,
        semesters.semester_number,
        semesters.academic_year
     FROM subjects
     LEFT JOIN semesters
        ON subjects.semester_id = semesters.id
     ORDER BY
        semesters.semester_number ASC,
        subjects.subject_name ASC"
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}


/* =========================================================
   ADD MARKS
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $student_id = (int)($_POST["student_id"] ?? 0);
    $subject_id = (int)($_POST["subject_id"] ?? 0);
    $exam_type = trim($_POST["exam_type"] ?? "");
    $marks_obtained = trim($_POST["marks_obtained"] ?? "");
    $max_marks = trim($_POST["max_marks"] ?? "");
    $exam_date = trim($_POST["exam_date"] ?? "");
    $remarks = trim($_POST["remarks"] ?? "");


    /* ---------------------------------------------------------
       VALIDATION
       --------------------------------------------------------- */

    if (
        $student_id <= 0 ||
        $subject_id <= 0 ||
        $exam_type === "" ||
        $marks_obtained === "" ||
        $max_marks === ""
    ) {

        $error = "Please fill all required fields.";

    } elseif (
        !is_numeric($marks_obtained) ||
        !is_numeric($max_marks)
    ) {

        $error = "Marks must be numeric.";

    } elseif (
        (float)$marks_obtained < 0 ||
        (float)$max_marks <= 0
    ) {

        $error = "Please enter valid marks.";

    } elseif (
        (float)$marks_obtained > (float)$max_marks
    ) {

        $error = "Marks obtained cannot be greater than maximum marks.";

    } else {

        try {

            /* =================================================
               GET STUDENT'S CURRENT SEMESTER
               ================================================= */

            $stmt = $conn->prepare(
                "SELECT current_semester
                 FROM students
                 WHERE id = ?"
            );

            $stmt->bind_param(
                "i",
                $student_id
            );

            $stmt->execute();

            $student_result = $stmt->get_result();

            if ($student_result->num_rows === 0) {

                $error = "Selected student does not exist.";

            } else {

                $student_data =
                    $student_result->fetch_assoc();

                $current_semester =
                    (int)$student_data["current_semester"];


                /* =============================================
                   CHECK SUBJECT BELONGS TO STUDENT'S SEMESTER
                   ============================================= */

                $stmt = $conn->prepare(
                    "SELECT
                        subjects.id,
                        subjects.semester_id,
                        semesters.semester_number
                     FROM subjects
                     LEFT JOIN semesters
                        ON subjects.semester_id = semesters.id
                     WHERE subjects.id = ?"
                );

                $stmt->bind_param(
                    "i",
                    $subject_id
                );

                $stmt->execute();

                $subject_result =
                    $stmt->get_result();

                if ($subject_result->num_rows === 0) {

                    $error = "Selected subject does not exist.";

                } else {

                    $subject_data =
                        $subject_result->fetch_assoc();

                    $subject_semester =
                        (int)$subject_data["semester_number"];


                    /* =========================================
                       SUBJECT / SEMESTER VALIDATION
                       ========================================= */

                    if (
                        $current_semester <= 0 ||
                        $subject_semester <= 0
                    ) {

                        $error =
                            "Student semester or subject semester is not set.";

                    } elseif (
                        $current_semester !== $subject_semester
                    ) {

                        $error =
                            "This subject does not belong to the student's current semester.";

                    }
                }
            }


            /* =================================================
               SAVE MARKS + AUTOMATIC BACKLOG
               ================================================= */

            if ($error === "") {

                $conn->begin_transaction();

                try {

                    /* -----------------------------------------
                       1. INSERT MARKS
                       ----------------------------------------- */

                    $stmt = $conn->prepare(
                        "INSERT INTO marks
                        (
                            student_id,
                            subject_id,
                            exam_type,
                            marks_obtained,
                            max_marks,
                            exam_date,
                            remarks
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );

                    $stmt->bind_param(
                        "iisddss",
                        $student_id,
                        $subject_id,
                        $exam_type,
                        $marks_obtained,
                        $max_marks,
                        $exam_date,
                        $remarks
                    );

                    if (!$stmt->execute()) {

                        throw new Exception(
                            "Failed to add marks."
                        );
                    }


                    /* -----------------------------------------
                       2. CALCULATE PERCENTAGE
                       ----------------------------------------- */

                    $percentage =
                        ((float)$marks_obtained /
                        (float)$max_marks) * 100;


                    /* -----------------------------------------
                       3. BELOW 40% = AUTOMATIC BACKLOG
                       ----------------------------------------- */

                    if ($percentage < 40) {

                        /* Check existing pending backlog */

                        $stmt = $conn->prepare(
                            "SELECT id
                             FROM backlogs
                             WHERE student_id = ?
                             AND subject_id = ?
                             AND status = 'Pending'
                             LIMIT 1"
                        );

                        $stmt->bind_param(
                            "ii",
                            $student_id,
                            $subject_id
                        );

                        $stmt->execute();

                        $backlog_result =
                            $stmt->get_result();


                        /* -------------------------------------
                           No pending backlog → create one
                           ------------------------------------- */

                        if ($backlog_result->num_rows === 0) {

                            $backlog_remark =
                                "Automatically added because marks are below 40%.";

                            $stmt = $conn->prepare(
                                "INSERT INTO backlogs
                                (
                                    student_id,
                                    subject_id,
                                    status,
                                    attempts,
                                    remark
                                )
                                VALUES (?, ?, 'Pending', 1, ?)"
                            );

                            $stmt->bind_param(
                                "iis",
                                $student_id,
                                $subject_id,
                                $backlog_remark
                            );

                            if (!$stmt->execute()) {

                                throw new Exception(
                                    "Failed to create backlog."
                                );
                            }

                        } else {

                            /* ---------------------------------
                               Pending backlog already exists
                               Increase attempt count
                               --------------------------------- */

                            $backlog =
                                $backlog_result->fetch_assoc();

                            $backlog_id =
                                (int)$backlog["id"];


                            $stmt = $conn->prepare(
                                "UPDATE backlogs
                                 SET attempts = attempts + 1
                                 WHERE id = ?"
                            );

                            $stmt->bind_param(
                                "i",
                                $backlog_id
                            );

                            if (!$stmt->execute()) {

                                throw new Exception(
                                    "Failed to update backlog attempts."
                                );
                            }
                        }


                    /* -----------------------------------------
                       4. 40% OR ABOVE = CLEAR PENDING BACKLOG
                       ----------------------------------------- */

                    } else {

                        $stmt = $conn->prepare(
                            "UPDATE backlogs
                             SET
                                status = 'Cleared',
                                cleared_date = CURDATE(),
                                remark = 'Automatically cleared because marks reached 40% or above.'
                             WHERE student_id = ?
                             AND subject_id = ?
                             AND status = 'Pending'"
                        );

                        $stmt->bind_param(
                            "ii",
                            $student_id,
                            $subject_id
                        );

                        if (!$stmt->execute()) {

                            throw new Exception(
                                "Failed to clear backlog."
                            );
                        }
                    }


                    /* -----------------------------------------
                       5. SAVE EVERYTHING
                       ----------------------------------------- */

                    $conn->commit();

                    header(
                        "Location: marks.php?added=1"
                    );

                    exit;


                } catch (Exception $e) {

                    $conn->rollback();

                    $error =
                        "Database error: " .
                        $e->getMessage();
                }
            }

        } catch (Exception $e) {

            $error =
                "Database error: " .
                $e->getMessage();
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

    <title>Add Marks - Student Academic Tracker</title>

    <link
        rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
    >

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

        .card {
            max-width: 850px;
            background: #ffffff;
            border-radius: 18px;
            padding: 30px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }

        .form-group {
            margin-bottom: 4px;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .required {
            color: #dc2626;
        }

        select,
        input,
        textarea {
            width: 100%;
            padding: 13px 15px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 15px;
            font-family: inherit;
            outline: none;
            background: white;
        }

        select:focus,
        input:focus,
        textarea:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        .help {
            margin-top: 7px;
            font-size: 13px;
            color: #6b7280;
        }

        .passing-info {
            margin-top: 7px;
            font-size: 13px;
            color: #4f46e5;
            font-weight: 600;
        }

        .student-semester {
            margin-top: 7px;
            font-size: 13px;
            color: #059669;
            font-weight: 600;
        }

        .alert {
            padding: 13px 16px;
            border-radius: 10px;
            margin-bottom: 22px;
            font-weight: 600;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .form-actions {
            margin-top: 28px;
            display: flex;
            gap: 12px;
        }

        .submit-btn {
            border: none;
            background: linear-gradient(
                135deg,
                #6366f1,
                #8b5cf6
            );
            color: white;
            padding: 13px 24px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }

        .cancel-btn {
            text-decoration: none;
            background: #f3f4f6;
            color: #374151;
            padding: 13px 24px;
            border-radius: 10px;
            font-weight: 700;
        }

        .no-subjects {
            color: #9ca3af;
        }

        @media (max-width: 800px) {

            .main-content {
                margin-left: 0 !important;
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .full-width {
                grid-column: auto;
            }

            .page-header {
                align-items: flex-start;
                gap: 15px;
                flex-direction: column;
            }

        }

    </style>

</head>


<body>

<?php include "includes/sidebar.php"; ?>


<div class="main-content">

    <div class="page-header">

        <div>

            <h1>Add Marks</h1>

            <p>
                Enter examination marks for a student
            </p>

        </div>


        <a
            href="marks.php"
            class="back-btn"
        >
            ← Back to Marks
        </a>

    </div>


    <div class="card">

        <?php if ($error): ?>

            <div class="alert error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <form method="POST">

            <div class="form-grid">


                <!-- =================================================
                     STUDENT
                     ================================================= -->

                <div class="form-group">

                    <label for="student_id">

                        Student
                        <span class="required">*</span>

                    </label>


                    <select
                        name="student_id"
                        id="student_id"
                        required
                    >

                        <option value="">
                            Select Student
                        </option>


                        <?php foreach ($students as $student): ?>

                            <option
                                value="<?= $student["id"] ?>"
                                data-semester="<?= (int)$student["current_semester"] ?>"
                                <?= (
                                    isset($_POST["student_id"]) &&
                                    $_POST["student_id"] == $student["id"]
                                )
                                ? "selected"
                                : "" ?>
                            >

                                <?= htmlspecialchars(
                                    $student["full_name"]
                                ) ?>

                                —

                                <?= htmlspecialchars(
                                    $student["prn"]
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>


                    <div
                        id="studentSemester"
                        class="student-semester"
                    ></div>

                </div>


                <!-- =================================================
                     SUBJECT
                     ================================================= -->

                <div class="form-group">

                    <label for="subject_id">

                        Subject
                        <span class="required">*</span>

                    </label>


                    <select
                        name="subject_id"
                        id="subject_id"
                        required
                        disabled
                    >

                        <option value="">
                            Select Student First
                        </option>

                    </select>


                    <div class="help">
                        Only subjects from the student's current semester will be shown.
                    </div>

                </div>


                <!-- =================================================
                     EXAM TYPE
                     ================================================= -->

                <div class="form-group">

                    <label for="exam_type">

                        Exam Type
                        <span class="required">*</span>

                    </label>


                    <select
                        name="exam_type"
                        id="exam_type"
                        required
                    >

                        <option value="">
                            Select Exam Type
                        </option>


                        <option
                            value="Internal"
                            <?= (
                                ($_POST["exam_type"] ?? "") ===
                                "Internal"
                            )
                            ? "selected"
                            : "" ?>
                        >
                            Internal
                        </option>


                        <option
                            value="External"
                            <?= (
                                ($_POST["exam_type"] ?? "") ===
                                "External"
                            )
                            ? "selected"
                            : "" ?>
                        >
                            External
                        </option>


                        <option
                            value="Practical"
                            <?= (
                                ($_POST["exam_type"] ?? "") ===
                                "Practical"
                            )
                            ? "selected"
                            : "" ?>
                        >
                            Practical
                        </option>


                        <option
                            value="Unit Test"
                            <?= (
                                ($_POST["exam_type"] ?? "") ===
                                "Unit Test"
                            )
                            ? "selected"
                            : "" ?>
                        >
                            Unit Test
                        </option>


                        <option
                            value="Assignment"
                            <?= (
                                ($_POST["exam_type"] ?? "") ===
                                "Assignment"
                            )
                            ? "selected"
                            : "" ?>
                        >
                            Assignment
                        </option>

                    </select>

                </div>


                <!-- =================================================
                     EXAM DATE
                     ================================================= -->

                <div class="form-group">

                    <label for="exam_date">
                        Exam Date
                    </label>


                    <input
                        type="date"
                        name="exam_date"
                        id="exam_date"
                        value="<?= htmlspecialchars(
                            $_POST["exam_date"] ?? ""
                        ) ?>"
                    >

                </div>


                <!-- =================================================
                     MARKS OBTAINED
                     ================================================= -->

                <div class="form-group">

                    <label for="marks_obtained">

                        Marks Obtained
                        <span class="required">*</span>

                    </label>


                    <input
                        type="number"
                        name="marks_obtained"
                        id="marks_obtained"
                        step="0.01"
                        min="0"
                        placeholder="Example: 35"
                        value="<?= htmlspecialchars(
                            $_POST["marks_obtained"] ?? ""
                        ) ?>"
                        required
                    >


                    <div class="help">
                        Enter the marks obtained by the student.
                    </div>

                </div>


                <!-- =================================================
                     MAXIMUM MARKS
                     ================================================= -->

                <div class="form-group">

                    <label for="max_marks">

                        Maximum Marks
                        <span class="required">*</span>

                    </label>


                    <input
                        type="number"
                        name="max_marks"
                        id="max_marks"
                        step="0.01"
                        min="1"
                        placeholder="Example: 100"
                        value="<?= htmlspecialchars(
                            $_POST["max_marks"] ?? ""
                        ) ?>"
                        required
                    >


                    <div class="passing-info">
                        Passing marks: 40%
                    </div>

                </div>


                <!-- =================================================
                     REMARKS
                     ================================================= -->

                <div class="form-group full-width">

                    <label for="remarks">
                        Remarks
                    </label>


                    <textarea
                        name="remarks"
                        id="remarks"
                        placeholder="Enter any remarks about the marks..."
                    ><?= htmlspecialchars(
                        $_POST["remarks"] ?? ""
                    ) ?></textarea>

                </div>

            </div>


            <div class="form-actions">

                <button
                    type="submit"
                    class="submit-btn"
                >
                    + Add Marks
                </button>


                <a
                    href="marks.php"
                    class="cancel-btn"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>


<script>

/* =========================================================
   SUBJECT DATA FROM PHP
   ========================================================= */

const subjects = <?= json_encode($subjects) ?>;


/* =========================================================
   ELEMENTS
   ========================================================= */

const studentSelect =
    document.getElementById("student_id");

const subjectSelect =
    document.getElementById("subject_id");

const studentSemester =
    document.getElementById("studentSemester");


/* =========================================================
   LOAD SUBJECTS FOR SELECTED STUDENT
   ========================================================= */

function loadStudentSubjects() {

    const selectedOption =
        studentSelect.options[
            studentSelect.selectedIndex
        ];


    subjectSelect.innerHTML = "";


    if (
        !selectedOption ||
        !selectedOption.value
    ) {

        subjectSelect.disabled = true;

        const option =
            document.createElement("option");

        option.value = "";

        option.textContent =
            "Select Student First";

        subjectSelect.appendChild(option);

        studentSemester.textContent = "";

        return;
    }


    const semester =
        parseInt(
            selectedOption.dataset.semester || "0"
        );


    /* -----------------------------------------
       Show student's semester
       ----------------------------------------- */

    if (semester > 0) {

        let yearText = "";

        if (semester === 1 || semester === 2) {
            yearText = "1st Year";
        }
        else if (semester === 3 || semester === 4) {
            yearText = "2nd Year";
        }
        else if (semester === 5 || semester === 6) {
            yearText = "3rd Year";
        }

        studentSemester.textContent =
            "📚 " +
            yearText +
            " — Semester " +
            semester;

    } else {

        studentSemester.textContent =
            "Student semester not set.";
    }


    /* -----------------------------------------
       Filter subjects
       ----------------------------------------- */

    const studentSubjects =
        subjects.filter(function(subject) {

            return parseInt(
                subject.semester_number || "0"
            ) === semester;

        });


    /* -----------------------------------------
       No subjects
       ----------------------------------------- */

    if (studentSubjects.length === 0) {

        subjectSelect.disabled = true;

        const option =
            document.createElement("option");

        option.value = "";

        option.textContent =
            "No subjects available for this semester";

        option.className =
            "no-subjects";

        subjectSelect.appendChild(option);

        return;
    }


    /* -----------------------------------------
       Add subjects
       ----------------------------------------- */

    subjectSelect.disabled = false;


    const firstOption =
        document.createElement("option");

    firstOption.value = "";

    firstOption.textContent =
        "Select Subject";

    subjectSelect.appendChild(firstOption);


    studentSubjects.forEach(function(subject) {

        const option =
            document.createElement("option");

        option.value = subject.id;

        option.textContent =
            subject.subject_code +
            " - " +
            subject.subject_name;

        option.dataset.semester =
            subject.semester_number;

        subjectSelect.appendChild(option);

    });


    /* -----------------------------------------
       Restore subject after validation error
       ----------------------------------------- */

    const previousSubject =
        "<?= htmlspecialchars(
            $_POST["subject_id"] ?? "",
            ENT_QUOTES
        ) ?>";


    if (previousSubject !== "") {

        const matchingOption =
            Array.from(
                subjectSelect.options
            ).find(function(option) {

                return option.value === previousSubject;

            });


        if (matchingOption) {

            matchingOption.selected = true;
        }
    }
}


/* =========================================================
   WHEN STUDENT CHANGES
   ========================================================= */

studentSelect.addEventListener(
    "change",
    loadStudentSubjects
);


/* =========================================================
   LOAD SUBJECTS WHEN PAGE OPENS
   ========================================================= */

loadStudentSubjects();

</script>


</body>

</html>