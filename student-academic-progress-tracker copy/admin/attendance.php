<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        attendance.id,
        attendance.total_classes,
        attendance.attended_classes,
        attendance.attendance_date,
        attendance.remarks,
        students.full_name,
        students.prn,
        subjects.subject_code,
        subjects.subject_name
    FROM attendance
    INNER JOIN students
        ON attendance.student_id = students.id
    INNER JOIN subjects
        ON attendance.subject_id = subjects.id
";

if ($search !== '') {
    $sql .= "
        WHERE
            students.full_name LIKE ?
            OR students.prn LIKE ?
            OR subjects.subject_code LIKE ?
            OR subjects.subject_name LIKE ?
    ";
}

$sql .= " ORDER BY attendance.id DESC";

$stmt = $conn->prepare($sql);

if ($search !== '') {
    $like = "%" . $search . "%";
    $stmt->bind_param("ssss", $like, $like, $like, $like);
}

$stmt->execute();
$result = $stmt->get_result();

function attendancePercentage($attended, $total) {
    if ($total <= 0) {
        return 0;
    }

    return round(($attended / $total) * 100, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance | Student Academic Tracker</title>

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
            padding: 35px;
            min-height: 100vh;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .page-title h1 {
            margin: 0 0 7px;
            font-size: 30px;
            font-weight: 800;
        }

        .page-title p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }

        .add-btn {
            background: linear-gradient(135deg, #6d5dfc, #8b5cf6);
            color: white;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 5px 15px rgba(109, 93, 252, 0.25);
        }

        .search-box {
            background: white;
            padding: 18px;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .search-form {
            display: flex;
            gap: 10px;
        }

        .search-form input {
            flex: 1;
            padding: 12px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 9px;
            font-size: 14px;
            outline: none;
        }

        .search-form input:focus {
            border-color: #7c3aed;
        }

        .search-btn {
            border: none;
            background: #111827;
            color: white;
            padding: 12px 22px;
            border-radius: 9px;
            cursor: pointer;
            font-weight: 600;
        }

        .clear-btn {
            display: flex;
            align-items: center;
            text-decoration: none;
            padding: 12px 18px;
            border-radius: 9px;
            background: #f3f4f6;
            color: #374151;
            font-size: 14px;
            font-weight: 600;
        }

        .table-card {
            background: white;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 18px rgba(0,0,0,0.05);
        }

        .table-header {
            padding: 20px 22px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h2 {
            margin: 0;
            font-size: 18px;
        }

        .record-count {
            color: #6b7280;
            font-size: 13px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1050px;
        }

        th {
            background: #f8fafc;
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        tr:hover {
            background: #fafaff;
        }

        .student-name {
            font-weight: 700;
            color: #111827;
        }

        .prn {
            color: #6b7280;
            font-size: 12px;
            margin-top: 4px;
        }

        .subject-code {
            font-weight: 700;
            color: #6d28d9;
        }

        .percentage {
            font-weight: 800;
        }

        .good {
            color: #16a34a;
        }

        .warning {
            color: #d97706;
        }

        .danger {
            color: #dc2626;
        }

        .action-btn {
            text-decoration: none;
            padding: 7px 10px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 4px;
        }

        .edit-btn {
            background: #ede9fe;
            color: #6d28d9;
        }

        .delete-btn {
            background: #fee2e2;
            color: #dc2626;
        }

        .empty {
            text-align: center;
            padding: 50px;
            color: #9ca3af;
        }

        @media (max-width: 900px) {
            .main-content {
                margin-left: 0 !important;
                padding: 20px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>

<?php include "includes/sidebar.php"; ?>

<div class="main-content">

    <div class="page-header">
        <div class="page-title">
            <h1>Attendance</h1>
            <p>Manage and monitor student attendance records.</p>
        </div>

        <a href="add_attendance.php" class="add-btn">
            + Add Attendance
        </a>
    </div>

    <div class="search-box">
        <form method="GET" class="search-form">

            <input
                type="text"
                name="search"
                placeholder="Search by student, PRN, subject code or subject name..."
                value="<?= htmlspecialchars($search) ?>"
            >

            <button type="submit" class="search-btn">
                Search
            </button>

            <?php if ($search !== ''): ?>
                <a href="attendance.php" class="clear-btn">
                    Clear
                </a>
            <?php endif; ?>

        </form>
    </div>

    <div class="table-card">

        <div class="table-header">
            <h2>Attendance Records</h2>

            <span class="record-count">
                <?= $result->num_rows ?> record(s)
            </span>
        </div>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Subject</th>
                        <th>Total Classes</th>
                        <th>Attended</th>
                        <th>Attendance %</th>
                        <th>Date</th>
                        <th>Remarks</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <?php
                        $percentage = attendancePercentage(
                            (int)$row['attended_classes'],
                            (int)$row['total_classes']
                        );

                        if ($percentage >= 75) {
                            $percentageClass = "good";
                        } elseif ($percentage >= 60) {
                            $percentageClass = "warning";
                        } else {
                            $percentageClass = "danger";
                        }
                        ?>

                        <tr>

                            <td>
                                <div class="student-name">
                                    <?= htmlspecialchars($row['full_name']) ?>
                                </div>

                                <div class="prn">
                                    <?= htmlspecialchars($row['prn']) ?>
                                </div>
                            </td>

                            <td>
                                <div class="subject-code">
                                    <?= htmlspecialchars($row['subject_code']) ?>
                                </div>

                                <div class="prn">
                                    <?= htmlspecialchars($row['subject_name']) ?>
                                </div>
                            </td>

                            <td>
                                <?= (int)$row['total_classes'] ?>
                            </td>

                            <td>
                                <?= (int)$row['attended_classes'] ?>
                            </td>

                            <td>
                                <span class="percentage <?= $percentageClass ?>">
                                    <?= number_format($percentage, 2) ?>%
                                </span>
                            </td>

                            <td>
                                <?= !empty($row['attendance_date'])
                                    ? htmlspecialchars($row['attendance_date'])
                                    : '-' ?>
                            </td>

                            <td>
                                <?= !empty($row['remarks'])
                                    ? htmlspecialchars($row['remarks'])
                                    : '-' ?>
                            </td>

                            <td>

                                <a
                                    href="edit_attendance.php?id=<?= (int)$row['id'] ?>"
                                    class="action-btn edit-btn"
                                >
                                    Edit
                                </a>

                                <a
                                    href="delete_attendance.php?id=<?= (int)$row['id'] ?>"
                                    class="action-btn delete-btn"
                                    onclick="return confirm('Are you sure you want to delete this attendance record?');"
                                >
                                    Delete
                                </a>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8" class="empty">
                            No attendance records found.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>
    </div>

</div>

</body>
</html>