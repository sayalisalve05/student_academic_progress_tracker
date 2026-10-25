<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

$search = trim($_GET["search"] ?? "");

/* Fetch notifications */
$notifications = [];

if ($search !== "") {

    $stmt = $conn->prepare("
        SELECT
            n.id,
            n.title,
            n.message,
            n.notification_type,
            n.student_id,
            n.created_at,
            s.full_name,
            s.prn
        FROM notifications n
        LEFT JOIN students s ON n.student_id = s.id
        WHERE
            n.title LIKE ?
            OR n.message LIKE ?
            OR n.notification_type LIKE ?
            OR s.full_name LIKE ?
            OR s.prn LIKE ?
        ORDER BY n.created_at DESC
    ");

    $like = "%" . $search . "%";

    $stmt->bind_param(
        "sssss",
        $like,
        $like,
        $like,
        $like,
        $like
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }

    $stmt->close();

} else {

    $result = $conn->query("
        SELECT
            n.id,
            n.title,
            n.message,
            n.notification_type,
            n.student_id,
            n.created_at,
            s.full_name,
            s.prn
        FROM notifications n
        LEFT JOIN students s ON n.student_id = s.id
        ORDER BY n.created_at DESC
    ");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
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

<div class="main-content">

    <div class="page-header">

        <div>
            <h1>Notifications</h1>
            <p>Manage academic notifications for students</p>
        </div>

        <a href="add_notification.php" class="add-btn">
            + Add Notification
        </a>

    </div>


    <?php if (isset($_GET["added"])): ?>

        <div class="alert alert-success">
            ✓ Notification added successfully.
        </div>

    <?php endif; ?>


    <?php if (isset($_GET["updated"])): ?>

        <div class="alert alert-success">
            ✓ Notification updated successfully.
        </div>

    <?php endif; ?>


    <?php if (isset($_GET["deleted"])): ?>

        <div class="alert alert-success">
            ✓ Notification deleted successfully.
        </div>

    <?php endif; ?>


    <!-- Search -->

    <div class="search-card">

        <form method="GET" class="search-form">

            <input
                type="text"
                name="search"
                value="<?= htmlspecialchars($search) ?>"
                placeholder="Search by title, message, type, student name or PRN..."
            >

            <button type="submit" class="search-btn">
                Search
            </button>

            <?php if ($search !== ""): ?>

                <a href="notifications.php" class="clear-btn">
                    Clear
                </a>

            <?php endif; ?>

        </form>

    </div>


    <!-- Notification Table -->

    <div class="table-card">

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>#</th>
                        <th>Notification</th>
                        <th>Type</th>
                        <th>Student</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (empty($notifications)): ?>

                    <tr>

                        <td colspan="6">

                            <div class="empty-state">

                                <div class="empty-icon">
                                    🔔
                                </div>

                                <h3>No notifications found</h3>

                                <p>
                                    Add a notification to display it here.
                                </p>

                            </div>

                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($notifications as $index => $notification): ?>

                        <tr>

                            <td>
                                <?= $index + 1 ?>
                            </td>


                            <td>

                                <div class="title-text">
                                    <?= htmlspecialchars($notification["title"]) ?>
                                </div>

                                <div class="message-text">
                                    <?= htmlspecialchars($notification["message"]) ?>
                                </div>

                            </td>


                            <td>

                                <span class="type-badge">
                                    <?= htmlspecialchars($notification["notification_type"]) ?>
                                </span>

                            </td>


                            <td>

                                <?php if ($notification["student_id"] === null): ?>

                                    <span class="all-students">
                                        All Students
                                    </span>

                                <?php else: ?>

                                    <span class="student-badge">

                                        <?= htmlspecialchars($notification["full_name"] ?? "Unknown") ?>

                                        <?php if (!empty($notification["prn"])): ?>

                                            <br>
                                            <?= htmlspecialchars($notification["prn"]) ?>

                                        <?php endif; ?>

                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <span class="date-text">

                                    <?= date(
                                        "d M Y, h:i A",
                                        strtotime($notification["created_at"])
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <div class="action-buttons">

                                    <a
                                        href="edit_notification.php?id=<?= (int)$notification["id"] ?>"
                                        class="edit-btn"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        href="delete_notification.php?id=<?= (int)$notification["id"] ?>"
                                        class="delete-btn"
                                        onclick="return confirm('Are you sure you want to delete this notification?');"
                                    >
                                        Delete
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>