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
        b.id,
        b.status,
        b.attempts,
        b.remark,
        b.cleared_date,
        s.full_name,
        s.prn,
        sub.subject_code,
        sub.subject_name
    FROM backlogs b
    INNER JOIN students s ON b.student_id = s.id
    INNER JOIN subjects sub ON b.subject_id = sub.id
";

if ($search !== "") {
    $sql .= "
        WHERE
            s.full_name LIKE ?
            OR s.prn LIKE ?
            OR sub.subject_code LIKE ?
            OR sub.subject_name LIKE ?
            OR b.status LIKE ?
    ";
}

$sql .= " ORDER BY b.id DESC";

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

/* Main content */

.main-content {
    margin-left: 260px !important;
    padding: 40px 42px;
    min-height: 100vh;
}

/* Page Header */

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
    margin-right: 4px;
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

/* Cards */

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

/* Subject code */

.subject-code {
    color: #6d28d9;
    font-weight: 700;
}

/* Status badges */

.badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 7px;
    font-size: 11px;
    font-weight: 700;
}

.badge-success {
    background: #dcfce7;
    color: #15803d;
}

.badge-danger {
    background: #fee2e2;
    color: #dc2626;
}

/* Attempts */

.attempts {
    font-weight: 600;
}

/* No data */

.no-data {
    text-align: center !important;
    padding: 45px !important;
    color: #8a94a6 !important;
}

/* Actions */

.action-buttons {
    display: flex;
    align-items: center;
    gap: 5px;
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
            <h1>Backlogs</h1>
            <p>Manage student backlog records.</p>
        </div>

        <a href="add_backlog.php" class="btn btn-primary">
            + Add Backlog
        </a>

    </div>


    <?php if (isset($_GET['added'])): ?>

        <div class="alert alert-success">
            Backlog added successfully.
        </div>

    <?php endif; ?>


    <?php if (isset($_GET['updated'])): ?>

        <div class="alert alert-success">
            Backlog updated successfully.
        </div>

    <?php endif; ?>


    <?php if (isset($_GET['deleted'])): ?>

        <div class="alert alert-success">
            Backlog deleted successfully.
        </div>

    <?php endif; ?>


    <!-- Search Card -->

    <div class="card">

        <form method="GET" class="search-form">

            <input
                type="text"
                name="search"
                value="<?= htmlspecialchars($search) ?>"
                placeholder="Search by student, PRN, subject or status..."
            >

            <button type="submit">
                Search
            </button>

            <?php if ($search !== ""): ?>

                <a href="backlogs.php" class="btn btn-secondary">
                    Clear
                </a>

            <?php endif; ?>

        </form>

    </div>


    <!-- Backlogs Table -->

    <div class="card">

        <div class="table-responsive">

            <table class="data-table">

                <thead>

                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>PRN</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Attempts</th>
                        <th>Remark</th>
                        <th>Cleared Date</th>
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
                                    <?= htmlspecialchars($row['full_name']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['prn']) ?>
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

                                <?php if ($row['status'] === 'Cleared'): ?>

                                    <span class="badge badge-success">
                                        Cleared
                                    </span>

                                <?php else: ?>

                                    <span class="badge badge-danger">
                                        Pending
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>
                                <span class="attempts">
                                    <?= (int)$row['attempts'] ?>
                                </span>
                            </td>

                            <td>

                                <?php if (!empty($row['remark'])): ?>

                                    <?= htmlspecialchars($row['remark']) ?>

                                <?php else: ?>

                                    <span style="color:#9ca3af;">—</span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php if (!empty($row['cleared_date'])): ?>

                                    <?= htmlspecialchars($row['cleared_date']) ?>

                                <?php else: ?>

                                    <span style="color:#9ca3af;">—</span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <div class="action-buttons">

                                    <a
                                        href="edit_backlog.php?id=<?= (int)$row['id'] ?>"
                                        class="btn btn-sm btn-warning"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        href="delete_backlog.php?id=<?= (int)$row['id'] ?>"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('Are you sure you want to delete this backlog record?');"
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
                            No backlog records found.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>