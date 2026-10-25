
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once "../config/auth.php";

require_admin();

$user_id = (int) $_SESSION["user_id"];

$error = "";
$success = "";

/* FETCH ADMIN DETAILS */
$stmt = $conn->prepare("
    SELECT id, username, email, role, created_at
    FROM users
    WHERE id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

if (!$admin) {
    die("Admin account not found.");
}

/* CHANGE PASSWORD */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $current_password = $_POST["current_password"] ?? "";
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (
        $current_password === "" ||
        $new_password === "" ||
        $confirm_password === ""
    ) {
        $error = "Please fill in all password fields.";
    } else {
        $password_stmt = $conn->prepare("
            SELECT password
            FROM users
            WHERE id = ?
        ");

        $password_stmt->bind_param("i", $user_id);
        $password_stmt->execute();

        $password_result = $password_stmt->get_result();
        $password_row = $password_result->fetch_assoc();
        $password_stmt->close();

        if (
            !$password_row ||
            !password_verify(
                $current_password,
                $password_row["password"]
            )
        ) {
            $error = "Current password is incorrect.";
        } elseif (strlen($new_password) < 6) {
            $error = "New password must contain at least 6 characters.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New password and confirm password do not match.";
        } else {
            $hashed_password = password_hash(
                $new_password,
                PASSWORD_DEFAULT
            );

            $update_stmt = $conn->prepare("
                UPDATE users
                SET password = ?
                WHERE id = ?
            ");

            $update_stmt->bind_param(
                "si",
                $hashed_password,
                $user_id
            );

            if ($update_stmt->execute()) {
                $success = "Password changed successfully.";
            } else {
                $error = "Unable to change password.";
            }

            $update_stmt->close();
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
    background: #f5f7fb;
}

.page-header {
    margin-bottom: 22px;
}

.page-header h1 {
    margin: 0;
    color: #1f2937;
    font-size: 28px;
    font-weight: 700;
}

.page-header p {
    margin: 7px 0 0;
    color: #6b7280;
    font-size: 14px;
    line-height: 1.5;
}

.profile-grid {
    display: grid;
    grid-template-columns: 280px minmax(0, 1fr);
    gap: 20px;
    max-width: 1100px;
    align-items: start;
}

.profile-card,
.info-card,
.password-card {
    background: #fff;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.05);
    min-width: 0;
}

.profile-card {
    text-align: center;
}

.profile-icon {
    width: 76px;
    height: 76px;
    margin: 0 auto 15px;
    border-radius: 50%;
    background: linear-gradient(135deg, #7c3aed, #9333ea);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: 700;
}

.profile-card h2 {
    margin: 0;
    color: #1f2937;
    font-size: 20px;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.profile-card .email {
    margin-top: 8px;
    color: #6b7280;
    font-size: 13px;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.role-badge {
    display: inline-block;
    margin-top: 14px;
    padding: 6px 12px;
    border-radius: 20px;
    background: #ede9fe;
    color: #6d28d9;
    font-size: 11px;
    font-weight: 700;
}

.right-column {
    display: flex;
    flex-direction: column;
    gap: 18px;
    min-width: 0;
}

.card-title {
    margin: 0 0 15px;
    color: #1f2937;
    font-size: 18px;
    font-weight: 700;
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    padding: 12px 0;
    border-bottom: 1px solid #eef0f4;
}

.info-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.info-label {
    color: #6b7280;
    font-size: 13px;
    font-weight: 600;
}

.info-value {
    color: #1f2937;
    font-size: 13px;
    font-weight: 600;
    text-align: right;
    overflow-wrap: anywhere;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 7px;
    color: #374151;
    font-size: 13px;
    font-weight: 600;
}

.form-control {
    display: block;
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: #fff;
    color: #1f2937;
    font-family: Arial, sans-serif;
    font-size: 13px;
    outline: none;
}

.form-control:focus {
    border-color: #7c3aed;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
}

.password-help {
    margin-top: 6px;
    color: #6b7280;
    font-size: 12px;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 11px 18px;
    border: none;
    border-radius: 8px;
    background: #7c3aed;
    color: #fff;
    font-family: Arial, sans-serif;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}

.btn:hover {
    background: #6d28d9;
}

.success-box,
.error-box {
    margin-bottom: 16px;
    padding: 11px 13px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.5;
}

.success-box {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.error-box {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

@media (max-width: 900px) {
    .main-content {
        margin-left: 220px !important;
        padding: 25px 20px;
    }

    .profile-grid {
        grid-template-columns: minmax(0, 1fr);
        max-width: 100%;
    }

    .profile-card {
        text-align: left;
    }

    .profile-icon {
        margin-left: 0;
    }
}

@media (max-width: 600px) {
    .main-content {
        padding: 20px 12px;
    }

    .page-header h1 {
        font-size: 25px;
    }

    .profile-card,
    .info-card,
    .password-card {
        padding: 18px;
    }

    .info-row {
        flex-direction: column;
        gap: 5px;
    }

    .info-value {
        text-align: left;
    }

    .btn {
        width: 100%;
    }
}
</style>

<div class="main-content">

    <div class="page-header">
        <h1>Admin Profile</h1>
        <p>Manage your administrator account and password</p>
    </div>

    <div class="profile-grid">

        <div class="profile-card">

            <div class="profile-icon">
                <?= htmlspecialchars(strtoupper(substr($admin["username"], 0, 1))) ?>
            </div>

            <h2><?= htmlspecialchars($admin["username"]) ?></h2>

            <div class="email">
                <?= htmlspecialchars($admin["email"]) ?>
            </div>

            <div class="role-badge">ADMINISTRATOR</div>

        </div>

        <div class="right-column">

            <div class="info-card">

                <h2 class="card-title">Account Information</h2>

                <div class="info-row">
                    <div class="info-label">Username</div>
                    <div class="info-value">
                        <?= htmlspecialchars($admin["username"]) ?>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-label">Email Address</div>
                    <div class="info-value">
                        <?= htmlspecialchars($admin["email"]) ?>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-label">Account Role</div>
                    <div class="info-value">
                        <?= htmlspecialchars(ucfirst($admin["role"])) ?>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-label">Account Created</div>
                    <div class="info-value">
                        <?= htmlspecialchars(date("d M Y", strtotime($admin["created_at"]))) ?>
                    </div>
                </div>

            </div>

            <div class="password-card">

                <h2 class="card-title">Change Password</h2>

                <?php if ($success !== ""): ?>
                    <div class="success-box">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error !== ""): ?>
                    <div class="error-box">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">

                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input
                            type="password"
                            id="current_password"
                            name="current_password"
                            class="form-control"
                            placeholder="Enter current password"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            class="form-control"
                            placeholder="Enter new password"
                            minlength="6"
                            required
                        >
                        <div class="password-help">
                            Minimum 6 characters.
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            class="form-control"
                            placeholder="Re-enter new password"
                            minlength="6"
                            required
                        >
                    </div>

                    <button type="submit" class="btn">
                        Change Password
                    </button>

                </form>

            </div>

        </div>

    </div>

</div>