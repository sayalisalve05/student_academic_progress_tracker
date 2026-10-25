<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

$search = trim($_GET["search"] ?? "");

/*
|--------------------------------------------------------------------------
| Get Students
|--------------------------------------------------------------------------
| Student details come from students table.
| Username and email come from users table.
*/

if ($search !== "") {

    $searchTerm = "%" . $search . "%";

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
        WHERE
            s.prn LIKE ?
            OR s.roll_no LIKE ?
            OR s.full_name LIKE ?
            OR s.course LIKE ?
            OR s.department LIKE ?
            OR u.username LIKE ?
            OR u.email LIKE ?
        ORDER BY s.id DESC
    ");

    $stmt->bind_param(
        "sssssss",
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm
    );

} else {

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
        ORDER BY s.id DESC
    ");
}

$stmt->execute();
$result = $stmt->get_result();

function student_value($value, $default = "-")
{
    if ($value === null || $value === "") {
        return $default;
    }

    return htmlspecialchars($value);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Students | Student Academic Tracker</title>

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

/* Header */

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    gap: 20px;
}

.page-header h1 {
    margin: 0;
    font-size: 30px;
    color: #111827;
}

.page-header p {
    margin: 7px 0 0;
    color: #6b7280;
    font-size: 14px;
}

.add-btn {
    display: inline-block;
    padding: 12px 20px;
    background: #4f46e5;
    color: white;
    text-decoration: none;
    border-radius: 9px;
    font-weight: 600;
    transition: 0.2s;
}

.add-btn:hover {
    background: #4338ca;
}

/* Search */

.search-card {
    background: white;
    padding: 20px;
    border-radius: 14px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    margin-bottom: 25px;
}

.search-form {
    display: flex;
    gap: 12px;
}

.search-input {
    flex: 1;
    padding: 12px 15px;
    border: 1px solid #d1d5db;
    border-radius: 9px;
    font-size: 15px;
    outline: none;
}

.search-input:focus {
    border-color: #4f46e5;
}

.search-btn {
    border: none;
    padding: 12px 22px;
    border-radius: 9px;
    background: #111827;
    color: white;
    font-weight: 600;
    cursor: pointer;
}

.clear-btn {
    display: flex;
    align-items: center;
    padding: 12px 18px;
    border-radius: 9px;
    background: #e5e7eb;
    color: #374151;
    text-decoration: none;
    font-weight: 600;
}

/* Table */

.table-card {
    background: white;
    border-radius: 14px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    overflow: hidden;
}

.table-header {
    padding: 20px 22px;
    border-bottom: 1px solid #e5e7eb;
}

.table-header h2 {
    margin: 0;
    font-size: 18px;
    color: #111827;
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1100px;
}

thead {
    background: #f8fafc;
}

th {
    padding: 15px 16px;
    text-align: left;
    font-size: 13px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    white-space: nowrap;
}

td {
    padding: 16px;
    border-top: 1px solid #eef0f4;
    font-size: 14px;
    vertical-align: middle;
}

tbody tr:hover {
    background: #fafaff;
}

/* Student */

.student-name {
    font-weight: 700;
    color: #111827;
}

.student-email {
    color: #6b7280;
    font-size: 12px;
    margin-top: 4px;
}
/* Student Photo */

.student-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.student-photo {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e5e7eb;
    background: #f3f4f6;
    flex-shrink: 0;
}

.student-photo-placeholder {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #ede9fe;
    color: #6d28d9;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 21px;
    border: 2px solid #ddd6fe;
    flex-shrink: 0;
}

/* Badges */

.badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.badge-sem {
    background: #ede9fe;
    color: #6d28d9;
}

.badge-course {
    background: #e0f2fe;
    color: #0369a1;
}

/* Actions */

.actions {
    display: flex;
    gap: 8px;
}

.action-btn {
    display: inline-block;
    padding: 7px 11px;
    border-radius: 7px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
}

.edit-btn {
    background: #e0e7ff;
    color: #3730a3;
}

.delete-btn {
    background: #fee2e2;
    color: #b91c1c;
}

.edit-btn:hover {
    background: #c7d2fe;
}

.delete-btn:hover {
    background: #fecaca;
}

/* Empty */

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6b7280;
}

.empty-icon {
    font-size: 45px;
    margin-bottom: 12px;
}

.empty-state h3 {
    margin: 0 0 7px;
    color: #374151;
}

.empty-state p {
    margin: 0;
}

/* Responsive */

@media (max-width: 900px) {

    .main-content {
        margin-left: 0 !important;
        padding: 20px;
    }

    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .search-form {
        flex-wrap: wrap;
    }

    .search-input {
        min-width: 200px;
    }

}

</style>

</head>

<body>

<?php include "includes/sidebar.php"; ?>


<div class="main-content">

    <!-- Page Header -->

    <div class="page-header">

        <div>
            <h1>Students</h1>
            <p>Manage student profiles and academic information.</p>
        </div>

        <a href="add_student.php" class="add-btn">
            + Add Student
        </a>

    </div>


    <!-- Search -->

    <div class="search-card">

        <form method="GET" class="search-form">

            <input
                type="text"
                name="search"
                class="search-input"
                placeholder="Search by name, PRN, roll number, course, username or email..."
                value="<?= htmlspecialchars($search) ?>"
            >

            <button type="submit" class="search-btn">
                Search
            </button>

            <?php if ($search !== ""): ?>

                <a href="students.php" class="clear-btn">
                    Clear
                </a>

            <?php endif; ?>

        </form>

    </div>


    <!-- Students Table -->

    <div class="table-card">

        <div class="table-header">

            <h2>
                Student Records
                <?php if ($result): ?>
                    (<?= $result->num_rows ?>)
                <?php endif; ?>
            </h2>

        </div>


        <?php if ($result && $result->num_rows > 0): ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>#</th>

                            <th>Student</th>

                            <th>PRN</th>

                            <th>Roll No.</th>

                            <th>Course</th>

                            <th>Department</th>

                            <th>Semester</th>

                            <th>Phone</th>

                            <th>Actions</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php $count = 1; ?>

                    <?php while ($student = $result->fetch_assoc()): ?>

                        <tr>

                            <!-- Number -->

                            <td>
                                <?= $count++ ?>
                            </td>


                            <!-- Student -->

<td>

    <div class="student-info">

        <?php if (!empty($student["photo"])): ?>

            <img
                src="../uploads/students/<?= htmlspecialchars($student["photo"]) ?>"
                alt="Student Photo"
                class="student-photo"
            >

        <?php else: ?>

            <div class="student-photo-placeholder">
                🎓
            </div>

        <?php endif; ?>

        <div>

            <div class="student-name">
                <?= student_value($student["full_name"]) ?>
            </div>

            <div class="student-email">
                <?= student_value($student["email"]) ?>
            </div>

        </div>

    </div>

</td>
                            <!-- PRN -->

                            <td>

                                <?= student_value($student["prn"]) ?>

                            </td>


                            <!-- Roll -->

                            <td>

                                <?= student_value($student["roll_no"]) ?>

                            </td>


                            <!-- Course -->

                            <td>

                                <span class="badge badge-course">

                                    <?= student_value($student["course"]) ?>

                                </span>

                            </td>


                            <!-- Department -->

                            <td>

                                <?= student_value($student["department"]) ?>

                            </td>


                            <!-- Semester -->

                            <td>

                                <span class="badge badge-sem">

                                    Semester
                                    <?= student_value($student["current_semester"]) ?>

                                </span>

                            </td>


                            <!-- Phone -->

                            <td>

                                <?= student_value($student["phone"]) ?>

                            </td>


                            <!-- Actions -->

                            <td>

                                <div class="actions">

                                    <a
                                        href="edit_student.php?id=<?= (int)$student["id"] ?>"
                                        class="action-btn edit-btn"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        href="delete_student.php?id=<?= (int)$student["id"] ?>"
                                        class="action-btn delete-btn"
                                        onclick="return confirm('Are you sure you want to delete this student?');"
                                    >
                                        Delete
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty-state">

                <div class="empty-icon">
                    🎓
                </div>

                <h3>No Students Found</h3>

                <?php if ($search !== ""): ?>

                    <p>
                        No student matches
                        "<strong><?= htmlspecialchars($search) ?></strong>".
                    </p>

                <?php else: ?>

                    <p>
                        No student records have been added yet.
                    </p>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>

</div>


</body>

</html>