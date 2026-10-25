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

if ($search !== "") {

    $like = "%" . $search . "%";

    $stmt = $conn->prepare("
        SELECT 
            subjects.id,
            subjects.subject_code,
            subjects.subject_name,
            subjects.credits,
            semesters.semester_number,
            semesters.academic_year
        FROM subjects
        LEFT JOIN semesters 
            ON subjects.semester_id = semesters.id
        WHERE 
            subjects.subject_code LIKE ?
            OR subjects.subject_name LIKE ?
        ORDER BY semesters.semester_number ASC, subjects.subject_code ASC
    ");

    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();

    $result = $stmt->get_result();

} else {

    $result = $conn->query("
        SELECT 
            subjects.id,
            subjects.subject_code,
            subjects.subject_name,
            subjects.credits,
            semesters.semester_number,
            semesters.academic_year
        FROM subjects
        LEFT JOIN semesters 
            ON subjects.semester_id = semesters.id
        ORDER BY semesters.semester_number ASC, subjects.subject_code ASC
    ");
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Subjects | Student Academic Tracker</title>

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
            transition: 0.2s;
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

        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 22px;
        }

        .search-box input {
            flex: 1;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
        }

        .search-box input:focus {
            border-color: #7c3aed;
        }

        .search-btn {
            border: none;
            background: #111827;
            color: white;
            padding: 0 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        .clear-btn {
            display: flex;
            align-items: center;
            padding: 0 16px;
            background: #e5e7eb;
            color: #374151;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
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
            padding: 15px 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        tr:hover td {
            background: #fafafa;
        }

        .subject-code {
            font-weight: 700;
            color: #6d28d9;
        }

        .semester-badge {
            display: inline-block;
            padding: 5px 9px;
            background: #ede9fe;
            color: #6d28d9;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
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
                min-width: 750px;
            }
        }

    </style>

</head>

<body>

<?php include "includes/sidebar.php"; ?>

<div class="main-content">

    <div class="page-header">

        <div>
            <h1>Subjects</h1>
            <p>Manage all academic subjects and semester details.</p>
        </div>

        <a href="add_subject.php" class="add-btn">
            + Add Subject
        </a>

    </div>

    <div class="card">

        <form method="GET" class="search-box">

            <input
                type="text"
                name="search"
                placeholder="Search by subject code or subject name..."
                value="<?php echo htmlspecialchars($search); ?>"
            >

            <button type="submit" class="search-btn">
                Search
            </button>

            <?php if ($search !== ""): ?>

                <a href="subjects.php" class="clear-btn">
                    Clear
                </a>

            <?php endif; ?>

        </form>

        <?php if ($result && $result->num_rows > 0): ?>

            <table>

                <thead>

                    <tr>
                        <th>#</th>
                        <th>Subject Code</th>
                        <th>Subject Name</th>
                        <th>Semester</th>
                        <th>Academic Year</th>
                        <th>Credits</th>
                        <th>Actions</th>
                    </tr>

                </thead>

                <tbody>

                <?php
                $count = 1;

                while ($row = $result->fetch_assoc()):
                ?>

                    <tr>

                        <td><?php echo $count++; ?></td>

                        <td>
                            <span class="subject-code">
                                <?php echo htmlspecialchars($row['subject_code']); ?>
                            </span>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($row['subject_name']); ?>
                        </td>

                        <td>

                            <?php if ($row['semester_number'] !== null): ?>

                                <span class="semester-badge">
                                    Semester <?php echo htmlspecialchars($row['semester_number']); ?>
                                </span>

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </td>

                        <td>
                            <?php echo htmlspecialchars($row['academic_year'] ?? '-'); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($row['credits'] ?? '-'); ?>
                        </td>

                        <td>

                            <a
                                href="edit_subject.php?id=<?php echo $row['id']; ?>"
                                class="action-btn edit-btn"
                            >
                                Edit
                            </a>

                            <a
                                href="delete_subject.php?id=<?php echo $row['id']; ?>"
                                class="action-btn delete-btn"
                                onclick="return confirm('Are you sure you want to delete this subject?');"
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

                <div class="empty-icon">📚</div>

                <h3>No subjects found</h3>

                <p>
                    <?php
                    if ($search !== "") {
                        echo "No subjects match your search.";
                    } else {
                        echo "No subjects have been added yet.";
                    }
                    ?>
                </p>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>