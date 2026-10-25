<?php

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

/* -------------------------------------------------
   DELETE SYLLABUS
------------------------------------------------- */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_id"])) {

    $delete_id = (int)$_POST["delete_id"];

    if ($delete_id > 0) {

        $stmt = $conn->prepare("
            DELETE FROM subject_syllabus
            WHERE id = ?
        ");

        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: syllabus.php");
    exit;
}


/* -------------------------------------------------
   DELETE NOTE
------------------------------------------------- */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_note_id"])) {

    $delete_note_id = (int)$_POST["delete_note_id"];

    if ($delete_note_id > 0) {

        $stmt = $conn->prepare("
            DELETE FROM subject_notes
            WHERE id = ?
        ");

        $stmt->bind_param("i", $delete_note_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: syllabus.php");
    exit;
}


/* -------------------------------------------------
   SEARCH
------------------------------------------------- */

$search = trim($_GET["search"] ?? "");


if ($search !== "") {

    $like = "%" . $search . "%";

    $stmt = $conn->prepare("
        SELECT
            sub.id AS subject_id,
            sub.subject_code,
            sub.subject_name,
            sub.credits,

            sem.semester_number,
            sem.academic_year,

            ss.id AS syllabus_id,
            ss.syllabus,
            ss.updated_at AS syllabus_updated_at,

            (
                SELECT COUNT(*)
                FROM subject_syllabus_images ssi
                WHERE ssi.syllabus_id = ss.id
            ) AS photo_count,

            (
                SELECT COUNT(*)
                FROM subject_notes sn
                WHERE sn.subject_id = sub.id
            ) AS note_count

        FROM subjects sub

        LEFT JOIN semesters sem
            ON sub.semester_id = sem.id

        LEFT JOIN subject_syllabus ss
            ON sub.id = ss.subject_id

        WHERE
            sub.subject_code LIKE ?
            OR sub.subject_name LIKE ?

        ORDER BY
            sem.semester_number ASC,
            sub.subject_code ASC
    ");

    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();

    $result = $stmt->get_result();

} else {

    $result = $conn->query("
        SELECT
            sub.id AS subject_id,
            sub.subject_code,
            sub.subject_name,
            sub.credits,

            sem.semester_number,
            sem.academic_year,

            ss.id AS syllabus_id,
            ss.syllabus,
            ss.updated_at AS syllabus_updated_at,

            (
                SELECT COUNT(*)
                FROM subject_syllabus_images ssi
                WHERE ssi.syllabus_id = ss.id
            ) AS photo_count,

            (
                SELECT COUNT(*)
                FROM subject_notes sn
                WHERE sn.subject_id = sub.id
            ) AS note_count

        FROM subjects sub

        LEFT JOIN semesters sem
            ON sub.semester_id = sem.id

        LEFT JOIN subject_syllabus ss
            ON sub.id = ss.subject_id

        ORDER BY
            sem.semester_number ASC,
            sub.subject_code ASC
    ");
}


/* -------------------------------------------------
   STATISTICS
------------------------------------------------- */

$total_subjects = 0;
$total_syllabus = 0;
$total_photos = 0;
$total_notes = 0;


$count_result = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM subjects) AS total_subjects,

        (
            SELECT COUNT(*)
            FROM subject_syllabus
        ) AS total_syllabus,

        (
            SELECT COUNT(*)
            FROM subject_syllabus_images
        ) AS total_photos,

        (
            SELECT COUNT(*)
            FROM subject_notes
        ) AS total_notes
");


if ($count_result) {

    $count_data = $count_result->fetch_assoc();

    $total_subjects =
        (int)$count_data["total_subjects"];

    $total_syllabus =
        (int)$count_data["total_syllabus"];

    $total_photos =
        (int)$count_data["total_photos"];

    $total_notes =
        (int)$count_data["total_notes"];
}


$missing_syllabus =
    $total_subjects - $total_syllabus;

if ($missing_syllabus < 0) {
    $missing_syllabus = 0;
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

<title>Syllabus Management | Academic Tracker</title>


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
    padding: 30px;
    min-height: 100vh;
}


/* HEADER */

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 25px;
}

.page-title h1 {
    margin: 0;
    font-size: 28px;
    color: #111827;
}

.page-title p {
    margin: 7px 0 0;
    color: #6b7280;
    font-size: 14px;
}


/* BUTTONS */

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 11px 18px;
    border-radius: 9px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
}

.btn-primary {
    background: #4f46e5;
    color: white;
}

.btn-primary:hover {
    background: #4338ca;
}

.btn-edit {
    background: #eef2ff;
    color: #4338ca;
}

.btn-edit:hover {
    background: #e0e7ff;
}

.btn-delete {
    background: #fee2e2;
    color: #dc2626;
}

.btn-delete:hover {
    background: #fecaca;
}

.btn-notes {
    background: #fff7ed;
    color: #c2410c;
}

.btn-notes:hover {
    background: #ffedd5;
}


/* STATS */

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 14px;
    padding: 20px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 3px 12px rgba(0,0,0,0.04);
}

.stat-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.stat-label {
    color: #6b7280;
    font-size: 12px;
    font-weight: 700;
}

.stat-value {
    font-size: 27px;
    font-weight: 700;
    margin-top: 8px;
    color: #111827;
}

.stat-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 21px;
}

.purple {
    background: #ede9fe;
}

.green {
    background: #dcfce7;
}

.orange {
    background: #ffedd5;
}

.blue {
    background: #dbeafe;
}


/* SEARCH */

.search-card {
    background: white;
    border-radius: 14px;
    padding: 18px;
    border: 1px solid #e5e7eb;
    margin-bottom: 20px;
}

.search-form {
    display: flex;
    gap: 10px;
}

.search-input {
    flex: 1;
    padding: 12px 14px;
    border: 1px solid #d1d5db;
    border-radius: 9px;
    font-size: 14px;
    outline: none;
}

.search-input:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
}

.btn-search {
    background: #111827;
    color: white;
}

.btn-clear {
    background: #f3f4f6;
    color: #374151;
}


/* TABLE */

.table-card {
    background: white;
    border-radius: 14px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 3px 12px rgba(0,0,0,0.04);
    overflow: hidden;
}

.table-header {
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.table-header h2 {
    margin: 0;
    font-size: 18px;
}

.table-header p {
    margin: 5px 0 0;
    color: #6b7280;
    font-size: 13px;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1150px;
}

th {
    background: #f9fafb;
    color: #4b5563;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    padding: 14px 16px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

td {
    padding: 15px 16px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 14px;
    vertical-align: middle;
}

tr:hover td {
    background: #fafafa;
}

.subject-code {
    font-weight: 700;
    color: #4f46e5;
}

.subject-name {
    font-weight: 600;
    color: #111827;
}

.semester-badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    background: #eef2ff;
    color: #4338ca;
    font-size: 12px;
    font-weight: 600;
}


/* STATUS */

.status-badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.status-added {
    background: #dcfce7;
    color: #15803d;
}

.status-missing {
    background: #fee2e2;
    color: #dc2626;
}


/* COUNTS */

.count-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 10px;
    border-radius: 20px;
    background: #f3f4f6;
    color: #374151;
    font-size: 12px;
    font-weight: 700;
}

.photo-count {
    background: #eff6ff;
    color: #1d4ed8;
}

.note-count {
    background: #fff7ed;
    color: #c2410c;
}


/* SYLLABUS */

.syllabus-preview {
    max-width: 270px;
    color: #6b7280;
    line-height: 1.5;
}

.actions {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
}

.action-btn {
    padding: 8px 11px;
    border-radius: 7px;
    border: none;
    text-decoration: none;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
}

.empty {
    text-align: center;
    padding: 50px 20px;
    color: #6b7280;
}

.empty-icon {
    font-size: 40px;
    margin-bottom: 10px;
}


@media (max-width: 1100px) {

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

}


@media (max-width: 900px) {

    .main-content {
        margin-left: 220px;
        padding: 20px;
    }

    .page-header {
        align-items: flex-start;
        flex-direction: column;
    }

}


@media (max-width: 600px) {

    .stats {
        grid-template-columns: 1fr;
    }

}

</style>

</head>


<body>


<?php include "includes/sidebar.php"; ?>


<main class="main-content">


<!-- HEADER -->

<div class="page-header">

    <div class="page-title">

        <h1>📚 Syllabus Management</h1>

        <p>
            Manage syllabus, syllabus photos and notes for all subjects.
        </p>

    </div>


    <a
        href="add_syllabus.php"
        class="btn btn-primary"
    >

        ➕ Add Syllabus

    </a>

</div>


<!-- STATISTICS -->

<div class="stats">


<div class="stat-card">

    <div class="stat-top">

        <div>

            <div class="stat-label">
                TOTAL SUBJECTS
            </div>

            <div class="stat-value">
                <?php echo $total_subjects; ?>
            </div>

        </div>

        <div class="stat-icon purple">
            📚
        </div>

    </div>

</div>


<div class="stat-card">

    <div class="stat-top">

        <div>

            <div class="stat-label">
                SYLLABUS ADDED
            </div>

            <div class="stat-value">
                <?php echo $total_syllabus; ?>
            </div>

        </div>

        <div class="stat-icon green">
            ✅
        </div>

    </div>

</div>


<div class="stat-card">

    <div class="stat-top">

        <div>

            <div class="stat-label">
                SYLLABUS PHOTOS
            </div>

            <div class="stat-value">
                <?php echo $total_photos; ?>
            </div>

        </div>

        <div class="stat-icon blue">
            📷
        </div>

    </div>

</div>


<div class="stat-card">

    <div class="stat-top">

        <div>

            <div class="stat-label">
                TOTAL NOTES
            </div>

            <div class="stat-value">
                <?php echo $total_notes; ?>
            </div>

        </div>

        <div class="stat-icon orange">
            📝
        </div>

    </div>

</div>


</div>


<!-- SEARCH -->

<div class="search-card">

<form
    method="GET"
    class="search-form"
>

<input
    type="text"
    name="search"
    class="search-input"
    placeholder="Search by subject code or subject name..."
    value="<?php echo htmlspecialchars($search); ?>"
>


<button
    type="submit"
    class="btn btn-search"
>

    🔍 Search

</button>


<?php if ($search !== ""): ?>

<a
    href="syllabus.php"
    class="btn btn-clear"
>

    Clear

</a>

<?php endif; ?>

</form>

</div>


<!-- TABLE -->

<div class="table-card">


<div class="table-header">

    <h2>Subject Learning Materials</h2>

    <p>
        Manage syllabus, photos and notes for each subject.
    </p>

</div>


<div class="table-wrapper">


<table>


<thead>

<tr>

    <th>#</th>

    <th>Subject</th>

    <th>Subject Name</th>

    <th>Semester</th>

    <th>Credits</th>

    <th>Syllabus</th>

    <th>Photos</th>

    <th>Notes</th>

    <th>Status</th>

    <th>Actions</th>

</tr>

</thead>


<tbody>


<?php

$count = 1;


if (
    $result &&
    $result->num_rows > 0
):


while (
    $row = $result->fetch_assoc()
):


?>


<tr>


<td>

    <?php echo $count++; ?>

</td>


<td>

<span class="subject-code">

<?php

echo htmlspecialchars(
    $row["subject_code"]
);

?>

</span>

</td>


<td>

<span class="subject-name">

<?php

echo htmlspecialchars(
    $row["subject_name"]
);

?>

</span>

</td>


<td>

<?php if ($row["semester_number"] !== null): ?>

<span class="semester-badge">

Semester

<?php

echo htmlspecialchars(
    $row["semester_number"]
);

?>

</span>

<?php else: ?>

—

<?php endif; ?>

</td>


<td>

<?php

echo htmlspecialchars(
    $row["credits"]
);

?>

</td>


<td>


<?php if (!empty($row["syllabus_id"])): ?>


<div class="syllabus-preview">

<?php

$preview =
    strip_tags(
        $row["syllabus"] ?? ""
    );


if ($preview === "") {

    echo "Text not added.";

} else {

    if (
        strlen($preview) > 80
    ) {

        $preview =
            substr(
                $preview,
                0,
                80
            )
            . "...";

    }

    echo htmlspecialchars(
        $preview
    );

}

?>

</div>


<?php else: ?>

<span style="color:#9ca3af;">
No syllabus
</span>

<?php endif; ?>


</td>


<td>


<span class="count-badge photo-count">

📷

<?php

echo (int)$row["photo_count"];

?>

</span>


</td>


<td>


<span class="count-badge note-count">

📝

<?php

echo (int)$row["note_count"];

?>

</span>


</td>


<td>


<?php if (!empty($row["syllabus_id"])): ?>

<span class="status-badge status-added">

✓ Added

</span>

<?php else: ?>

<span class="status-badge status-missing">

⚠ Pending

</span>

<?php endif; ?>


</td>


<td>


<div class="actions">


<?php if (!empty($row["syllabus_id"])): ?>


<a
    href="edit_syllabus.php?id=<?php echo (int)$row["syllabus_id"]; ?>"
    class="action-btn btn-edit"
>

    ✏️ Edit

</a>


<form
    method="POST"
    style="display:inline;"
    onsubmit="return confirm('Are you sure you want to delete this syllabus?');"
>

<input
    type="hidden"
    name="delete_id"
    value="<?php echo (int)$row["syllabus_id"]; ?>"
>


<button
    type="submit"
    class="action-btn btn-delete"
>

    🗑 Delete

</button>

</form>


<?php else: ?>


<a
    href="add_syllabus.php?subject_id=<?php echo (int)$row["subject_id"]; ?>"
    class="action-btn btn-edit"
>

    ➕ Add

</a>


<?php endif; ?>


<a
    href="notes.php?subject_id=<?php echo (int)$row["subject_id"]; ?>"
    class="action-btn btn-notes"
>

    📝 Notes

</a>


</div>


</td>


</tr>


<?php

endwhile;


else:

?>


<tr>

<td colspan="10">


<div class="empty">

<div class="empty-icon">
📚
</div>


<strong>
No subjects found
</strong>


<p>
Add subjects first from the Subjects section.
</p>


</div>


</td>

</tr>


<?php endif; ?>


</tbody>


</table>


</div>


</div>


</main>


</body>

</html>