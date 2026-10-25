<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

$search = "";

if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

$sql = "
    SELECT
        e.id,
        e.exam_name,
        e.exam_type,
        e.exam_date,
        e.start_time,
        e.end_time,
        e.room_no,
        e.description,
        sub.subject_code,
        sub.subject_name,
        sem.semester_number,
        sem.academic_year
    FROM exams e
    INNER JOIN subjects sub ON e.subject_id = sub.id
    INNER JOIN semesters sem ON e.semester_id = sem.id
";

if ($search !== "") {

    $sql .= "
        WHERE
            e.exam_name LIKE ?
            OR e.exam_type LIKE ?
            OR sub.subject_code LIKE ?
            OR sub.subject_name LIKE ?
            OR e.room_no LIKE ?
    ";
}

$sql .= " ORDER BY e.exam_date ASC, e.start_time ASC";

$stmt = $conn->prepare($sql);

if ($search !== "") {

    $search_param = "%" . $search . "%";

    $stmt->bind_param(
        "sssss",
        $search_param,
        $search_param,
        $search_param,
        $search_param,
        $search_param
    );
}

$stmt->execute();
$result = $stmt->get_result();

?>

<?php include "includes/sidebar.php"; ?>

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
    margin-left: 260px !important;
    padding: 40px 42px;
    min-height: 100vh;
}

/* Header */

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 26px;
}

.page-header h1 {
    margin: 0;
    font-size: 32px;
    font-weight: 700;
    color: #1f2937;
}

.page-header p {
    margin: 7px 0 0;
    color: #7b8494;
    font-size: 14px;
}

/* Buttons */

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    border: none;
    cursor: pointer;
    font-weight: 600;
    border-radius: 8px;
    transition: 0.2s ease;
}

.btn-primary {
    background: #6d28d9;
    color: white;
    padding: 11px 18px;
    font-size: 14px;
}

.btn-primary:hover {
    background: #5b21b6;
}

.btn-secondary {
    background: #e5e7eb;
    color: #374151;
    padding: 11px 18px;
    font-size: 14px;
}

.btn-sm {
    padding: 7px 12px;
    font-size: 12px;
}

.btn-warning {
    background: #e0f2fe;
    color: #0284c7;
}

.btn-warning:hover {
    background: #bae6fd;
}

.btn-danger {
    background: #fee2e2;
    color: #ef4444;
}

.btn-danger:hover {
    background: #fecaca;
}

/* Card */

.card {
    background: #ffffff;
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 22px;
    box-shadow: 0 3px 15px rgba(15, 23, 42, 0.06);
}

/* Search */

.search-form {
    display: flex;
    gap: 10px;
    align-items: center;
}

.search-form input {
    flex: 1;
    height: 38px;
    border: 1px solid #d9dee7;
    border-radius: 7px;
    padding: 0 14px;
    font-size: 13px;
    outline: none;
}

.search-form input:focus {
    border-color: #7c3aed;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.08);
}

.search-form button {
    height: 38px;
    background: #111827;
    color: white;
    border: none;
    border-radius: 7px;
    padding: 0 18px;
    font-weight: 600;
    cursor: pointer;
}

.search-form button:hover {
    background: #1f2937;
}

/* Alerts */

.alert {
    padding: 13px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
}

/* Table */

.table-responsive {
    width: 100%;
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.data-table thead th {
    background: #f8fafc;
    color: #697386;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    text-align: left;
    padding: 15px 12px;
    border-bottom: 1px solid #e5e7eb;
    white-space: nowrap;
}

.data-table tbody td {
    padding: 15px 12px;
    border-bottom: 1px solid #edf0f4;
    vertical-align: middle;
    color: #374151;
}

.data-table tbody tr:hover {
    background: #fafaff;
}

.data-table tbody tr:last-child td {
    border-bottom: none;
}

.data-table strong {
    color: #273142;
}

.data-table small {
    color: #8a94a6;
    font-size: 11px;
}

/* Subject */

.subject-code {
    color: #6d28d9;
    font-weight: 700;
}

/* Exam type */

.exam-type {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 7px;
    background: #ede9fe;
    color: #6d28d9;
    font-size: 11px;
    font-weight: 700;
}

/* Room */

.room {
    font-weight: 600;
    color: #374151;
}

/* Actions */

.action-buttons {
    display: flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}

/* No data */

.no-data {
    text-align: center !important;
    padding: 45px !important;
    color: #8a94a6 !important;
}

/* Responsive */

@media (max-width: 900px) {

    .main-content {
        margin-left: 0 !important;
        padding: 25px 20px;
    }

    .page-header {
        align-items: flex-start;
        gap: 15px;
    }

    .page-header h1 {
        font-size: 27px;
    }

}

</style>


<div class="main-content">

    <div class="page-header">

        <div>
            <h1>Exams</h1>
            <p>Manage upcoming and scheduled examinations.</p>
        </div>

        <a href="add_exam.php" class="btn btn-primary">
            + Add Exam
        </a>

    </div>


    <?php if (isset($_GET['added'])): ?>

        <div class="alert alert-success">
            Exam added successfully.
        </div>

    <?php endif; ?>


    <?php if (isset($_GET['updated'])): ?>

        <div class="alert alert-success">
            Exam updated successfully.
        </div>

    <?php endif; ?>


    <?php if (isset($_GET['deleted'])): ?>

        <div class="alert alert-success">
            Exam deleted successfully.
        </div>

    <?php endif; ?>


    <!-- Search -->

    <div class="card">

        <form method="GET" class="search-form">

            <input
                type="text"
                name="search"
                value="<?= htmlspecialchars($search) ?>"
                placeholder="Search exam, subject, type or room..."
            >

            <button type="submit">
                Search
            </button>

            <?php if ($search !== ""): ?>

                <a href="exams.php" class="btn btn-secondary">
                    Clear
                </a>

            <?php endif; ?>

        </form>

    </div>


    <!-- Exams Table -->

    <div class="card">

        <div class="table-responsive">

            <table class="data-table">

                <thead>

                    <tr>
                        <th>#</th>
                        <th>Exam</th>
                        <th>Subject</th>
                        <th>Semester</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Room</th>
                        <th>Actions</th>
                    </tr>

                </thead>

                <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php $count = 1; ?>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?= $count++ ?>
                            </td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($row['exam_name']) ?>
                                </strong>
                            </td>

                            <td>

                                <span class="subject-code">
                                    <?= htmlspecialchars($row['subject_code']) ?>
                                </span>

                                <br>

                                <small>
                                    <?= htmlspecialchars($row['subject_name']) ?>
                                </small>

                            </td>

                            <td>

                                Sem <?= (int)$row['semester_number'] ?>

                                <br>

                                <small>
                                    <?= htmlspecialchars($row['academic_year']) ?>
                                </small>

                            </td>

                            <td>

                                <span class="exam-type">
                                    <?= htmlspecialchars($row['exam_type']) ?>
                                </span>

                            </td>

                            <td>
                                <?= htmlspecialchars($row['exam_date']) ?>
                            </td>

                            <td>

                                <?php if ($row['start_time'] || $row['end_time']): ?>

                                    <?= $row['start_time']
                                        ? htmlspecialchars(substr($row['start_time'], 0, 5))
                                        : '--' ?>

                                    -

                                    <?= $row['end_time']
                                        ? htmlspecialchars(substr($row['end_time'], 0, 5))
                                        : '--' ?>

                                <?php else: ?>

                                    <span style="color:#9ca3af;">—</span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php if (!empty($row['room_no'])): ?>

                                    <span class="room">
                                        <?= htmlspecialchars($row['room_no']) ?>
                                    </span>

                                <?php else: ?>

                                    <span style="color:#9ca3af;">—</span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <div class="action-buttons">

                                    <a
                                        href="edit_exam.php?id=<?= (int)$row['id'] ?>"
                                        class="btn btn-sm btn-warning"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        href="delete_exam.php?id=<?= (int)$row['id'] ?>"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('Are you sure you want to delete this exam?');"
                                    >
                                        Delete
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="9" class="no-data">
                            No exam records found.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>