<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

$search = trim($_GET["search"] ?? "");

if ($search !== "") {

    $stmt = $conn->prepare(
        "SELECT
            marks.id,
            students.full_name,
            students.prn,
            subjects.subject_code,
            subjects.subject_name,
            semesters.semester_number,
            semesters.academic_year,
            marks.exam_type,
            marks.marks_obtained,
            marks.max_marks,
            marks.exam_date,
            marks.remarks
        FROM marks
        INNER JOIN students
            ON marks.student_id = students.id
        INNER JOIN subjects
            ON marks.subject_id = subjects.id
        LEFT JOIN semesters
            ON subjects.semester_id = semesters.id
        WHERE
            students.full_name LIKE ?
            OR students.prn LIKE ?
            OR subjects.subject_code LIKE ?
            OR subjects.subject_name LIKE ?
            OR marks.exam_type LIKE ?
        ORDER BY marks.id DESC"
    );

    $search_value = "%" . $search . "%";

    $stmt->bind_param(
        "sssss",
        $search_value,
        $search_value,
        $search_value,
        $search_value,
        $search_value
    );

} else {

    $stmt = $conn->prepare(
        "SELECT
            marks.id,
            students.full_name,
            students.prn,
            subjects.subject_code,
            subjects.subject_name,
            semesters.semester_number,
            semesters.academic_year,
            marks.exam_type,
            marks.marks_obtained,
            marks.max_marks,
            marks.exam_date,
            marks.remarks
        FROM marks
        INNER JOIN students
            ON marks.student_id = students.id
        INNER JOIN subjects
            ON marks.subject_id = subjects.id
        LEFT JOIN semesters
            ON subjects.semester_id = semesters.id
        ORDER BY marks.id DESC"
    );
}

$stmt->execute();
$marks_result = $stmt->get_result();

$total_marks = $marks_result->num_rows;
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Marks - Student Academic Tracker</title>

    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">

    
<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f5f7fb;
    color: #1f2937;
}

.main-content {
    margin-left: 260px !important;
    padding: 40px 42px;
    min-height: 100vh;
    font-family: Arial, sans-serif;
}

.main-content h1,
.main-content h2,
.main-content h3,
.main-content p,
.main-content input,
.main-content button,
.main-content a,
.main-content table {
    font-family: Arial, sans-serif;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 22px;
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

.add-btn {
    display: inline-block;
    text-decoration: none;
    background: #7c3aed;
    color: white;
    padding: 11px 18px;
    border-radius: 9px;
    font-size: 14px;
    font-weight: 600;
    white-space: nowrap;
}

.add-btn:hover {
    background: #6d28d9;
}

.search-card {
    background: white;
    padding: 16px;
    border-radius: 12px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.05);
    margin-bottom: 16px;
}

.search-form {
    display: flex;
    gap: 10px;
    align-items: center;
}

.search-form input {
    flex: 1;
    min-width: 0;
    padding: 11px 13px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    outline: none;
}

.search-form input:focus {
    border-color: #7c3aed;
}

.search-btn {
    border: none;
    background: #374151;
    color: white;
    padding: 11px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
}

.search-btn:hover {
    background: #1f2937;
}

.clear-btn {
    text-decoration: none;
    background: #e5e7eb;
    color: #374151;
    padding: 11px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
}

.table-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.05);
    overflow: hidden;
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.main-content table {
    width: 100%;
    border-collapse: collapse;
    border-spacing: 0;
    margin: 0;
    font-size: 14px;
}

.main-content thead {
    background: #f8f7ff;
}

.main-content th {
    text-align: left;
    padding: 13px 14px;
    font-size: 13px;
    color: #4b5563;
    font-weight: 700;
    white-space: nowrap;
}

.main-content td {
    padding: 12px 14px;
    border-top: 1px solid #f0f0f0;
    font-size: 14px;
    color: #374151;
    vertical-align: top;
}

.main-content tbody tr:hover {
    background: #fafafa;
}

.title-text {
    font-size: 14px;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 4px;
}

.message-text {
    font-size: 13px;
    color: #6b7280;
    line-height: 1.45;
    max-width: 300px;
    overflow-wrap: anywhere;
}

.type-badge,
.student-badge,
.all-students {
    display: inline-block;
    font-size: 12px;
    border-radius: 7px;
    white-space: nowrap;
}

.type-badge {
    padding: 5px 9px;
    font-weight: 700;
    background: #ede9fe;
    color: #6d28d9;
    border-radius: 20px;
}

.student-badge {
    background: #f3f4f6;
    color: #374151;
    padding: 5px 9px;
    font-weight: 600;
    line-height: 1.6;
}

.all-students {
    background: #dcfce7;
    color: #166534;
    padding: 5px 9px;
    font-weight: 700;
}

.date-text {
    white-space: nowrap;
    color: #6b7280;
    font-size: 13px;
}

.action-buttons {
    display: flex;
    gap: 6px;
    align-items: center;
}

.edit-btn,
.delete-btn {
    display: inline-block;
    text-decoration: none;
    padding: 7px 10px;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 600;
}

.edit-btn {
    background: #ede9fe;
    color: #6d28d9;
}

.edit-btn:hover {
    background: #ddd6fe;
}

.delete-btn {
    background: #fee2e2;
    color: #dc2626;
}

.delete-btn:hover {
    background: #fecaca;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #6b7280;
}

.empty-icon {
    font-size: 36px;
    margin-bottom: 10px;
}

.empty-state h3 {
    margin: 0 0 7px;
    color: #374151;
    font-size: 17px;
}

.empty-state p {
    margin: 0;
    font-size: 14px;
}

.alert {
    padding: 12px 16px;
    border-radius: 9px;
    margin-bottom: 16px;
    font-size: 14px;
    font-weight: 600;
}

.alert-success {
    background: #dcfce7;
    border: 1px solid #bbf7d0;
    color: #166534;
}

@media (max-width: 900px) {
    .main-content {
        margin-left: 220px !important;
        padding: 25px 20px;
    }

    .page-header {
        align-items: flex-start;
        gap: 15px;
    }
}

@media (max-width: 600px) {
    .main-content {
        padding: 20px 12px;
    }

    .page-header {
        flex-direction: column;
    }

    .search-form {
        flex-wrap: wrap;
    }

    .search-form input {
        flex-basis: 100%;
    }

    .action-buttons {
        flex-wrap: wrap;
    }
}
</style>

</head>

<body>

<?php include "includes/sidebar.php"; ?>

<div class="main-content">

    <div class="page-header">

        <div>
            <h1>Marks</h1>
            <p>Manage student examination marks</p>
        </div>

        <a href="add_marks.php" class="add-btn">
            + Add Marks
        </a>

    </div>

    <div class="top-card">

        <form method="GET" class="search-form">

            <input
                type="text"
                name="search"
                class="search-input"
                placeholder="Search by student, PRN, subject or exam type..."
                value="<?= htmlspecialchars($search) ?>"
            >

            <button type="submit" class="search-btn">
                Search
            </button>

            <?php if ($search !== ""): ?>

                <a href="marks.php" class="clear-btn">
                    Clear
                </a>

            <?php endif; ?>

        </form>

    </div>

    <div class="summary">
        <?= $total_marks ?> mark record<?= $total_marks != 1 ? 's' : '' ?> found
    </div>

    <div class="table-card">

        <div class="table-wrapper">

            <?php if ($marks_result->num_rows > 0): ?>

                <table>

                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Subject</th>
                            <th>Semester</th>
                            <th>Exam Type</th>
                            <th>Marks</th>
                            <th>Exam Date</th>
                            <th>Remarks</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php while ($row = $marks_result->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <div class="student-name">
                                    <?= htmlspecialchars($row["full_name"]) ?>
                                </div>

                                <div class="prn">
                                    <?= htmlspecialchars($row["prn"]) ?>
                                </div>
                            </td>

                            <td>

                                <div class="subject-code">
                                    <?= htmlspecialchars($row["subject_code"]) ?>
                                </div>

                                <div>
                                    <?= htmlspecialchars($row["subject_name"]) ?>
                                </div>

                            </td>

                            <td>

                                <span class="semester">
                                    Sem <?= htmlspecialchars($row["semester_number"] ?? "-") ?>
                                </span>

                                <?php if (!empty($row["academic_year"])): ?>

                                    <div class="prn">
                                        <?= htmlspecialchars($row["academic_year"]) ?>
                                    </div>

                                <?php endif; ?>

                            </td>

                            <td>

                                <span class="exam-type">
                                    <?= htmlspecialchars($row["exam_type"]) ?>
                                </span>

                            </td>

                            <td>

                                <span class="marks">
                                    <?= htmlspecialchars($row["marks_obtained"]) ?>
                                    /
                                    <?= htmlspecialchars($row["max_marks"]) ?>
                                </span>

                            </td>

                            <td>
                                <?= !empty($row["exam_date"])
                                    ? htmlspecialchars($row["exam_date"])
                                    : "-" ?>
                            </td>

                            <td>
                                <?= !empty($row["remarks"])
                                    ? htmlspecialchars($row["remarks"])
                                    : "-" ?>
                            </td>

                            <td>

                                <div class="actions">

                                    <a
                                        href="edit_marks.php?id=<?= $row["id"] ?>"
                                        class="edit-btn"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        href="delete_marks.php?id=<?= $row["id"] ?>"
                                        class="delete-btn"
                                        onclick="return confirm('Are you sure you want to delete this mark record?');"
                                    >
                                        Delete
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                    </tbody>

                </table>

            <?php else: ?>

                <div class="empty">

                    <div class="empty-icon">📊</div>

                    <h3>No marks found</h3>

                    <p>
                        No mark records are available yet.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

</body>

</html>