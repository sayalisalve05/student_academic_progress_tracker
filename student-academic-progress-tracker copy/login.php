<?php
session_start();
require_once __DIR__ . "/config/database.php";

if (isset($_SESSION["user_id"], $_SESSION["role"])) {
    header(
        "Location: " .
        ($_SESSION["role"] === "admin"
            ? "admin/dashboard.php"
            : "student/dashboard.php")
    );
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $login_type = $_POST["login_type"] ?? "";
    $login_value = trim($_POST["login_value"] ?? "");
    $password = $_POST["password"] ?? "";

    if (
        !in_array($login_type, ["admin", "student"], true) ||
        $login_value === "" ||
        $password === ""
    ) {
        $error = "Please fill in all fields.";
    } else {

        $stmt = $conn->prepare(
            "SELECT id, username, email, password, role
             FROM users
             WHERE (username = ? OR email = ?)
             AND role = ?
             LIMIT 1"
        );

        $stmt->bind_param(
            "sss",
            $login_value,
            $login_value,
            $login_type
        );

        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        $stmt->close();

        if ($user && password_verify($password, $user["password"])) {

            session_regenerate_id(true);

            $_SESSION["user_id"] = (int)$user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["email"] = $user["email"];
            $_SESSION["role"] = $user["role"];

            header(
                "Location: " .
                ($user["role"] === "admin"
                    ? "admin/dashboard.php"
                    : "student/dashboard.php")
            );

            exit;
        }

        $error = "Invalid username/email, password, or selected login type.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Login | Student Academic Tracker</title>

<style>

/* =========================
   RESET
========================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html,
body {
    width: 100%;
    min-height: 100%;
}

body {
    font-family: Arial, Helvetica, sans-serif;
}


/* =========================
   LOGIN BACKGROUND
========================= */

.login-page {

    min-height: 100vh;

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 30px;

    background:
        radial-gradient(
            circle at 10% 20%,
            rgba(124, 58, 237, 0.35),
            transparent 30%
        ),

        radial-gradient(
            circle at 90% 80%,
            rgba(79, 70, 229, 0.30),
            transparent 30%
        ),

        linear-gradient(
            135deg,
            #111827 0%,
            #1e1b4b 50%,
            #312e81 100%
        );

}


/* =========================
   MAIN LOGIN BOX
========================= */

.login-shell {

    width: 100%;

    max-width: 1050px;

    min-height: 620px;

    display: grid;

    grid-template-columns:
        minmax(0, 1fr)
        430px;

    background: white;

    border-radius: 28px;

    overflow: hidden;

    box-shadow:
        0 30px 80px
        rgba(0, 0, 0, 0.35);

}


/* =========================
   LEFT SIDE
========================= */

.login-brand {

    position: relative;

    overflow: hidden;

    padding: 60px;

    color: white;

    display: flex;

    flex-direction: column;

    justify-content: center;

    background:
        radial-gradient(
            circle at 80% 20%,
            rgba(168, 85, 247, 0.45),
            transparent 28%
        ),

        linear-gradient(
            145deg,
            #4f46e5,
            #6d28d9 55%,
            #7c3aed
        );

}


/* Decorative circles */

.login-brand::before {

    content: "";

    position: absolute;

    width: 250px;

    height: 250px;

    border-radius: 50%;

    background:
        rgba(255,255,255,0.08);

    top: -100px;

    right: -70px;

}

.login-brand::after {

    content: "";

    position: absolute;

    width: 190px;

    height: 190px;

    border-radius: 50%;

    background:
        rgba(255,255,255,0.07);

    bottom: -80px;

    left: -60px;

}


/* =========================
   LOGO
========================= */

.brand-mark {

    width: 78px;

    height: 78px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 20px;

    font-size: 40px;

    margin-bottom: 25px;

    background:
        rgba(255,255,255,0.16);

    border:
        1px solid
        rgba(255,255,255,0.25);

    backdrop-filter: blur(10px);

}


/* =========================
   BRAND TEXT
========================= */

.login-brand h1 {

    position: relative;

    z-index: 2;

    max-width: 550px;

    font-size: 40px;

    line-height: 1.15;

    font-weight: 800;

    margin-bottom: 18px;

}

.login-brand > p {

    position: relative;

    z-index: 2;

    max-width: 500px;

    font-size: 17px;

    line-height: 1.6;

    color:
        rgba(255,255,255,0.88);

    margin-bottom: 35px;

}


/* =========================
   FEATURES
========================= */

.login-points {

    position: relative;

    z-index: 2;

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 12px;

    max-width: 520px;

}

.login-points span {

    padding: 14px 16px;

    border-radius: 12px;

    font-size: 14px;

    font-weight: 500;

    color: white;

    background:
        rgba(255,255,255,0.12);

    border:
        1px solid
        rgba(255,255,255,0.12);

    backdrop-filter: blur(8px);

}


/* =========================
   RIGHT LOGIN CARD
========================= */

.login-card {

    padding: 55px 45px;

    background: #ffffff;

    display: flex;

    flex-direction: column;

    justify-content: center;

}

.login-card h2 {

    font-size: 31px;

    font-weight: 800;

    color: #111827;

    margin-bottom: 8px;

}

.muted {

    color: #6b7280;

    font-size: 14px;

    margin-bottom: 25px;

}


/* =========================
   ERROR
========================= */

.alert {

    padding: 13px 15px;

    border-radius: 10px;

    margin-bottom: 18px;

    font-size: 13px;

}

.alert.error {

    background: #fef2f2;

    border: 1px solid #fecaca;

    color: #b91c1c;

}


/* =========================
   FORM LABEL
========================= */

.form-label {

    display: block;

    font-size: 13px;

    font-weight: 700;

    color: #374151;

    margin-top: 17px;

    margin-bottom: 8px;

}


/* =========================
   ROLE SELECTOR
========================= */

.role-switch {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 10px;

}

.role-option {

    cursor: pointer;

}

.role-option input {

    display: none;

}

.role-option span {

    min-height: 50px;

    display: flex;

    align-items: center;

    justify-content: center;

    text-align: center;

    padding: 8px;

    border-radius: 11px;

    border: 1.5px solid #e5e7eb;

    background: #f9fafb;

    color: #4b5563;

    font-size: 13px;

    font-weight: 700;

    transition: 0.2s;

}

.role-option span:hover {

    border-color: #a5b4fc;

}

.role-option input:checked + span {

    border-color: #6366f1;

    background: #eef2ff;

    color: #4338ca;

    box-shadow:
        0 0 0 3px
        rgba(99,102,241,0.08);

}


/* =========================
   INPUTS
========================= */

.login-input {

    width: 100%;

    height: 50px;

    padding: 0 14px;

    border-radius: 11px;

    border: 1.5px solid #e5e7eb;

    background: white;

    color: #111827;

    font-size: 14px;

    outline: none;

    transition: 0.2s;

}

.login-input:focus {

    border-color: #6366f1;

    box-shadow:
        0 0 0 4px
        rgba(99,102,241,0.10);

}

.login-input::placeholder {

    color: #9ca3af;

}


/* =========================
   PASSWORD
========================= */

.password-box {

    position: relative;

}

.password-box .login-input {

    padding-right: 50px;

}

.password-toggle {

    position: absolute;

    right: 5px;

    top: 5px;

    width: 40px;

    height: 40px;

    border: none;

    background: transparent;

    border-radius: 8px;

    cursor: pointer;

    font-size: 17px;

}

.password-toggle:hover {

    background: #f3f4f6;

}


/* =========================
   SIGN IN BUTTON
========================= */

.sign-in-btn {

    width: 100%;

    height: 52px;

    margin-top: 25px;

    border: none;

    border-radius: 11px;

    cursor: pointer;

    color: white;

    font-size: 15px;

    font-weight: 700;

    background:
        linear-gradient(
            135deg,
            #4f46e5,
            #7c3aed
        );

    box-shadow:
        0 8px 20px
        rgba(79,70,229,0.25);

    transition: 0.2s;

}

.sign-in-btn:hover {

    transform: translateY(-2px);

    box-shadow:
        0 12px 28px
        rgba(79,70,229,0.32);

}


/* =========================
   DEMO BOX
========================= */

.demo-box {

    margin-top: 24px;

    padding: 14px 16px;

    border-radius: 11px;

    background: #f8f7ff;

    border: 1px solid #e0e7ff;

    color: #4b5563;

    font-size: 12px;

    line-height: 1.7;

}

.demo-box strong {

    color: #4338ca;

    font-size: 13px;

}

.demo-box b {

    color: #111827;

}


/* =========================
   MOBILE
========================= */

@media (max-width: 850px) {

    .login-shell {

        grid-template-columns: 1fr;

        max-width: 520px;

    }

    .login-brand {

        padding: 45px 35px;

    }

    .login-brand h1 {

        font-size: 30px;

    }

    .login-card {

        padding: 40px 35px;

    }

}

@media (max-width: 520px) {

    .login-page {

        padding: 15px;

    }

    .login-shell {

        border-radius: 20px;

    }

    .login-brand {

        padding: 35px 25px;

    }

    .login-brand h1 {

        font-size: 26px;

    }

    .login-points {

        grid-template-columns: 1fr;

    }

    .login-card {

        padding: 35px 25px;

    }

    .role-switch {

        grid-template-columns: 1fr;

    }

}

</style>

</head>


<body class="login-page">


<div class="login-shell">


    <!-- =========================
         LEFT SIDE
    ========================== -->

    <div class="login-brand">

        <div class="brand-mark">
            🎓
        </div>

        <h1>
            Student Academic Tracker
        </h1>

        <p>
            Manage academics. Track progress. Achieve more.
        </p>

        <div class="login-points">

            <span>
                📊 Performance analytics
            </span>

            <span>
                📚 Marks & subjects
            </span>

            <span>
                📅 Attendance & exams
            </span>

            <span>
                📈 Academic progress
            </span>

        </div>

    </div>


    <!-- =========================
         RIGHT SIDE
    ========================== -->

    <div class="login-card">

        <h2>
            Welcome back
        </h2>

        <p class="muted">
            Sign in to continue to your dashboard.
        </p>


        <?php if ($error): ?>

            <div class="alert error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <form method="post">


            <label class="form-label">
                Login as
            </label>


            <div class="role-switch">

                <label class="role-option">

                    <input
                        type="radio"
                        name="login_type"
                        value="admin"
                        checked
                    >

                    <span>
                        👨‍🏫 Admin / Teacher
                    </span>

                </label>


                <label class="role-option">

                    <input
                        type="radio"
                        name="login_type"
                        value="student"
                    >

                    <span>
                        🎓 Student
                    </span>

                </label>

            </div>


            <label
                class="form-label"
                for="login_value"
            >
                Username or Email
            </label>


            <input
                class="login-input"
                id="login_value"
                name="login_value"
                type="text"
                placeholder="Enter username or email"
                autocomplete="username"
                required
            >


            <label
                class="form-label"
                for="password"
            >
                Password
            </label>


            <div class="password-box">

                <input
                    class="login-input"
                    id="password"
                    name="password"
                    type="password"
                    placeholder="Enter password"
                    autocomplete="current-password"
                    required
                >

                <button
                    type="button"
                    class="password-toggle"
                    id="togglePassword"
                >
                    👁️
                </button>

            </div>


            <button
                type="submit"
                class="sign-in-btn"
            >
                Sign In →
            </button>


        </form>


        <div class="demo-box">

            <strong>
                Demo Admin
            </strong>

            <br>

            Username:
            <b>admin</b>

            <br>

            Password:
            <b>admin123</b>

        </div>


    </div>

</div>


<script>

const togglePassword =
    document.getElementById("togglePassword");

const password =
    document.getElementById("password");

if (togglePassword && password) {

    togglePassword.addEventListener("click", function () {

        if (password.type === "password") {

            password.type = "text";

            togglePassword.textContent = "🙈";

        } else {

            password.type = "password";

            togglePassword.textContent = "👁️";

        }

    });

}

</script>


</body>

</html>