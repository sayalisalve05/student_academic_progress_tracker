
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_student();

$search = trim($_GET["search"] ?? "");


/* Fetch syllabus only for the logged-in student's semester */

$user_id = (int) $_SESSION["user_id"];

$stmt = $conn->prepare("
    SELECT
        sub.id AS subject_id,
        sub.subject_code,
        sub.subject_name,
        sub.credits,
        sem.semester_number,
        ss.id AS syllabus_id,
        ss.syllabus,
        ss.updated_at
    FROM students st
    INNER JOIN subjects sub
        ON sub.semester_id = (
            SELECT sem2.id
            FROM semesters sem2
            WHERE sem2.semester_number = st.current_semester
            LIMIT 1
        )
    INNER JOIN semesters sem
        ON sem.id = sub.semester_id
    LEFT JOIN subject_syllabus ss
        ON ss.subject_id = sub.id
    WHERE st.user_id = ?
    ORDER BY sub.subject_name ASC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

$subjects = [];

while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}

$stmt->close();

/* Fetch syllabus photos */
$photos_by_syllabus = [];

$photo_result = $conn->query("
    SELECT id, syllabus_id, image_path
    FROM subject_syllabus_images
    ORDER BY id ASC
");

if ($photo_result) {
    while ($photo = $photo_result->fetch_assoc()) {
        $photos_by_syllabus[$photo["syllabus_id"]][] = $photo;
    }
}
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
    margin-left: 260px;
    padding: 35px;
    min-height: 100vh;
}

.page-header {
    margin-bottom: 24px;
}

.page-header h1 {
    margin: 0;
    font-size: 28px;
    color: #111827;
}

.page-header p {
    margin: 7px 0 0;
    color: #6b7280;
    font-size: 14px;
}

.search-card {
    background: #fff;
    padding: 16px;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    margin-bottom: 22px;
}

.search-form {
    display: flex;
    gap: 10px;
}

.search-input {
    flex: 1;
    min-width: 0;
    padding: 12px 14px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
}

.btn {
    display: inline-block;
    border: none;
    border-radius: 8px;
    padding: 11px 16px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}

.btn-search {
    background: #4f46e5;
    color: #fff;
}

.btn-clear {
    background: #e5e7eb;
    color: #374151;
}

.subject-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}

.subject-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 3px 12px rgba(0,0,0,.04);
}

.subject-card-header {
    padding: 18px 20px;
    background: #faf9ff;
    border-bottom: 1px solid #eee8ff;
}

.subject-code {
    color: #4f46e5;
    font-size: 12px;
    font-weight: 700;
}

.subject-card h2 {
    margin: 7px 0;
    font-size: 18px;
    color: #111827;
}

.subject-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}

.meta-badge {
    padding: 5px 9px;
    border-radius: 20px;
    background: #eef2ff;
    color: #4338ca;
    font-size: 11px;
    font-weight: 600;
}

.subject-card-body {
    padding: 20px;
}

.section-title {
    margin: 0 0 12px;
    font-size: 15px;
    color: #1f2937;
}

.syllabus-text {
    font-size: 14px;
    color: #4b5563;
    line-height: 1.7;
    overflow-wrap: anywhere;
}

.syllabus-text p {
    margin: 0 0 10px;
}

.syllabus-text ul,
.syllabus-text ol {
    padding-left: 22px;
}

.no-syllabus {
    padding: 13px;
    border-radius: 8px;
    background: #f9fafb;
    color: #6b7280;
    font-size: 13px;
}

.photo-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-top: 14px;
}

.photo-item img {
    display: block;
    width: 100%;
    height: auto;
    max-height: 300px;
    object-fit: contain;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
}

.updated-text {
    margin-top: 16px;
    color: #9ca3af;
    font-size: 11px;
}

.empty-state {
    grid-column: 1 / -1;
    background: #fff;
    padding: 45px 20px;
    border-radius: 12px;
    text-align: center;
    color: #6b7280;
}

.empty-state h2 {
    color: #374151;
    font-size: 18px;
}

@media (max-width: 1100px) {
    .subject-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 900px) {
    .main-content {
        margin-left: 220px;
        padding: 22px 18px;
    }
}

@media (max-width: 600px) {
    .main-content {
        padding: 18px 12px;
    }

    .search-form {
        flex-wrap: wrap;
    }

    .search-input {
        flex-basis: 100%;
    }

    .subject-card-header,
    .subject-card-body {
        padding: 15px;
    }
}
</style>

<main class="main-content">

    <div class="page-header">
        <h1>📚 My Syllabus</h1>
        <p>View your subject syllabus and syllabus photos uploaded by your college.</p>
    </div>

    <div class="search-card">
        <form method="GET" class="search-form">
            <input
                type="text"
                name="search"
                class="search-input"
                placeholder="Search subject code or subject name..."
                value="<?= htmlspecialchars($search) ?>"
            >

            <button type="submit" class="btn btn-search">
                Search
            </button>

            <?php if ($search !== ""): ?>
                <a href="syllabus.php" class="btn btn-clear">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="subject-grid">

        <?php if ($result && $result->num_rows > 0): ?>

            <?php while ($row = $result->fetch_assoc()): ?>

                <article class="subject-card">

                    <div class="subject-card-header">

                        <div class="subject-code">
                            <?= htmlspecialchars($row["subject_code"] ?? "") ?>
                        </div>

                        <h2>
                            <?= htmlspecialchars($row["subject_name"] ?? "") ?>
                        </h2>

                        <div class="subject-meta">

                            <?php if ($row["semester_number"] !== null): ?>
                                <span class="meta-badge">
                                    Semester <?= htmlspecialchars((string)$row["semester_number"]) ?>
                                </span>
                            <?php endif; ?>

                            <span class="meta-badge">
                                Credits: <?= htmlspecialchars((string)($row["credits"] ?? "—")) ?>
                            </span>

                        </div>

                    </div>

                    <div class="subject-card-body">

                        <h3 class="section-title">Syllabus Content</h3>

                        <?php if (!empty($row["syllabus_id"])): ?>

                            <div class="syllabus-text">
                                <?php
                                // Display saved syllabus as text, not executable HTML.
                                echo nl2br(
                                    htmlspecialchars(
                                        strip_tags($row["syllabus"] ?? "")
                                    )
                                );
                                ?>
                            </div>

                            <?php
                            $syllabus_id = (int)$row["syllabus_id"];
                            $photos = $photos_by_syllabus[$syllabus_id] ?? [];
                            ?>

                            <?php if (!empty($photos)): ?>

                                <h3 class="section-title" style="margin-top:20px;">
                                    📷 Syllabus Photos
                                </h3>

                                <div class="photo-grid">

                                    <?php foreach ($photos as $photo): ?>

                                        <?php
                                        /*
                                         * Uses the saved image_path from the
                                         * subject_syllabus_images table.
                                         */
                                        $image_path = trim($photo["image_path"] ?? "");
                                        $image_url = "";

                                        if (
                                            $image_path !== "" &&
                                            !preg_match('/^(?:[a-z]+:)?\/\//i', $image_path) &&
                                            !str_contains($image_path, '..')
                                        ) {
                                            $image_url = "../" . ltrim($image_path, "/");
                                        }
                                        ?>

                                        <?php if ($image_url !== ""): ?>
                                            <div class="photo-item">
                                                <a
                                                    href="<?= htmlspecialchars($image_url) ?>"
                                                    target="_blank"
                                                    rel="noopener"
                                                >
                                                    <img
                                                        src="<?= htmlspecialchars($image_url) ?>"
                                                        alt="Syllabus for <?= htmlspecialchars($row["subject_name"] ?? "subject") ?>"
                                                        loading="lazy"
                                                    >
                                                </a>
                                            </div>
                                        <?php endif; ?>

                                    <?php endforeach; ?>

                                </div>

                            <?php endif; ?>

                            <?php if (!empty($row["syllabus_updated_at"])): ?>
                                <div class="updated-text">
                                    Last updated:
                                    <?= htmlspecialchars(date("d M Y", strtotime($row["syllabus_updated_at"]))) ?>
                                </div>
                            <?php endif; ?>

                        <?php else: ?>

                            <div class="no-syllabus">
                                Syllabus has not been uploaded for this subject yet.
                            </div>

                        <?php endif; ?>

                    </div>

                </article>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="empty-state">
                <div style="font-size:38px;">📚</div>
                <h2>No subjects found</h2>
                <p>Try another search term.</p>
            </div>

        <?php endif; ?>

    </div>

</main>