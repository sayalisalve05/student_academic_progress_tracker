<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

$result = $conn->query("
    SELECT
        id,
        semester_number,
        academic_year
    FROM semesters
    ORDER BY semester_number ASC
");

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Semesters | Student Academic Tracker</title>

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
            padding: 35px;
            min-height: 100vh;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
        }

        .page-header h1 {
            margin: 0;
            font-size: 30px;
            font-weight: 700;
        }

        .page-header p {
            margin: 7px 0 0;
            color: #6b7280;
            font-size: 14px;
        }

        .add-btn {
            background: #6d28d9;
            color: white;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 600;
        }

        .add-btn:hover {
            background: #5b21b6;
        }

        .card {
            background: white;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.06);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f9fafb;
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        td {
            padding: 16px 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        tr:hover td {
            background: #fafafa;
        }

        .semester-badge {
            display: inline-block;
            padding: 6px 11px;
            background: #ede9fe;
            color: #6d28d9;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 700;
        }

        .year {
            color: #4b5563;
            font-weight: 500;
        }

        .action-btn {
            display: inline-block;
            padding: 7px 11px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            margin-right: 5px;
        }

        .edit-btn {
            background: #e0f2fe;
            color: #0369a1;
        }

        .delete-btn {
            background: #fee2e2;
            color: #dc2626;
        }

        .empty {
            text-align: center;
            padding: 45px 20px;
            color: #6b7280;
        }

        .empty-icon {
            font-size: 42px;
            margin-bottom: 10px;
        }

        @media (max-width: 900px) {

            .main-content {
                margin-left: 0 !important;
                padding: 20px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .card {
                overflow-x: auto;
            }

            table {
                min-width: 600px;
            }

        }

    </style>

</head>

<body>

<?php include "includes/sidebar.php"; ?>

<div class="main-content">

    <div class="page-header">

        <div>

            <h1>Semesters</h1>

            <p>
                Manage academic semesters and academic years.
            </p>

        </div>

        <a href="add_semester.php" class="add-btn">
            + Add Semester
        </a>

    </div>

    <div class="card">

        <?php if ($result && $result->num_rows > 0): ?>

            <table>

                <thead>

                    <tr>
                        <th>#</th>
                        <th>Semester</th>
                        <th>Academic Year</th>
                        <th>Actions</th>
                    </tr>

                </thead>

                <tbody>

                <?php
                $count = 1;

                while ($row = $result->fetch_assoc()):
                ?>

                    <tr>

                        <td>
                            <?php echo $count++; ?>
                        </td>

                        <td>

                            <span class="semester-badge">

                                Semester
                                <?php echo htmlspecialchars($row['semester_number']); ?>

                            </span>

                        </td>

                        <td class="year">

                            <?php
                            echo htmlspecialchars(
                                $row['academic_year'] ?? '-'
                            );
                            ?>

                        </td>

                        <td>

                            <a
                                href="edit_semester.php?id=<?php echo $row['id']; ?>"
                                class="action-btn edit-btn"
                            >
                                Edit
                            </a>

                            <a
                                href="delete_semester.php?id=<?php echo $row['id']; ?>"
                                class="action-btn delete-btn"
                                onclick="return confirm('Are you sure you want to delete this semester? Related subjects may also be affected.');"
                            >
                                Delete
                            </a>

                        </td>

                    </tr>

                <?php endwhile; ?>

                </tbody>

            </table>

        <?php else: ?>

            <div class="empty">

                <div class="empty-icon">🎓</div>

                <h3>No semesters found</h3>

                <p>
                    No semesters have been added yet.
                </p>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>