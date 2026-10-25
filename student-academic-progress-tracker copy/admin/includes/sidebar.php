<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>

/* ================================
   ADMIN SIDEBAR
================================ */

.admin-sidebar {
    position: fixed;
    left: 0;
    top: 0;

    width: 260px;
    height: 100vh;

    background: #111827;
    color: #ffffff;

    padding: 22px 14px;

    z-index: 1000;

    overflow-y: auto;

    box-shadow: 4px 0 20px rgba(0,0,0,0.08);
}


/* LOGO */

.admin-sidebar-logo {
    display: flex;
    align-items: center;

    gap: 12px;

    padding: 8px 12px 24px;

    border-bottom: 1px solid rgba(255,255,255,0.08);

    margin-bottom: 20px;
}

.admin-logo-icon {
    width: 44px;
    height: 44px;

    background: #4f46e5;

    border-radius: 12px;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 21px;
}

.admin-logo-text {
    font-size: 17px;

    font-weight: 800;

    color: #ffffff;

    line-height: 1.2;
}

.admin-logo-subtitle {
    display: block;

    font-size: 10px;

    color: #9ca3af;

    font-weight: 500;

    margin-top: 3px;
}


/* SECTION TITLE */

.admin-menu-title {
    font-size: 10px;

    color: #6b7280;

    text-transform: uppercase;

    letter-spacing: 1.2px;

    font-weight: 700;

    padding: 0 12px;

    margin: 20px 0 8px;
}


/* NAVIGATION */

.admin-nav {
    display: flex;

    flex-direction: column;

    gap: 4px;
}

.admin-nav a {
    display: flex;

    align-items: center;

    width: 100%;

    min-height: 44px;

    padding: 11px 13px;

    border-radius: 10px;

    color: #cbd5e1;

    text-decoration: none !important;

    font-size: 13px;

    font-weight: 500;

    transition: all 0.2s ease;
}

.admin-nav a:hover {
    background: rgba(255,255,255,0.07);

    color: #ffffff;

    transform: translateX(2px);
}

.admin-nav a.active {
    background: #4f46e5;

    color: #ffffff;

    font-weight: 700;

    box-shadow: 0 5px 14px rgba(79,70,229,0.25);
}


/* ICON */

.admin-nav-icon {
    width: 28px;

    min-width: 28px;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 17px;
}

.admin-nav-text {
    white-space: nowrap;
}


/* LOGOUT */

.admin-logout {
    margin-top: 8px;
}

.admin-logout a:hover {
    background: rgba(239,68,68,0.12);

    color: #fca5a5;
}


/* MAIN CONTENT ALIGNMENT */

.main-content {
    margin-left: 260px !important;
}


/* SCROLLBAR */

.admin-sidebar::-webkit-scrollbar {
    width: 5px;
}

.admin-sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.admin-sidebar::-webkit-scrollbar-thumb {
    background: #374151;

    border-radius: 10px;
}


/* MOBILE */

@media (max-width: 768px) {

    .admin-sidebar {
        width: 220px;
    }

    .main-content {
        margin-left: 220px !important;
    }
}

</style>


<aside class="admin-sidebar">


    <!-- =========================
         LOGO
    ========================== -->

    <div class="admin-sidebar-logo">

        <div class="admin-logo-icon">
            🎓
        </div>

        <div>

            <div class="admin-logo-text">
                Academic Tracker
            </div>

            <span class="admin-logo-subtitle">
                Admin Panel
            </span>

        </div>

    </div>


    <!-- =========================
         MAIN
    ========================== -->

    <div class="admin-menu-title">
        Main
    </div>

    <nav class="admin-nav">

        <a href="dashboard.php"
           class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">

            <span class="admin-nav-icon">🏠</span>

            <span class="admin-nav-text">
                Dashboard
            </span>

        </a>


        <a href="students.php"
           class="<?php echo $current_page === 'students.php' ? 'active' : ''; ?>">

            <span class="admin-nav-icon">👨‍🎓</span>

            <span class="admin-nav-text">
                Students
            </span>

        </a>


        <a href="subjects.php"
           class="<?php echo $current_page === 'subjects.php' ? 'active' : ''; ?>">

            <span class="admin-nav-icon">📚</span>

            <span class="admin-nav-text">
                Subjects
            </span>

        </a>
        
        
        <a href="semesters.php"
           class="<?php echo $current_page === 'semesters.php' ? 'active' : ''; ?>">

            <span class="admin-nav-icon">🎓</span>

            <span class="admin-nav-text">
                Semesters
            </span>

        </a>

        <a href="syllabus.php"
           class="<?php echo $current_page === 'syllabus.php' ? 'active' : ''; ?>">

            <span class="admin-nav-icon">📖</span>

            <span class="admin-nav-text">
                Syllabus
            </span>

        </a>

        <a href="notes.php"
           class="<?php echo $current_page === 'notes.php' ? 'active' : ''; ?>">

            <span class="admin-nav-icon">📝</span>

            <span class="admin-nav-text">
                Notes
            </span>

        </a>


    </nav>


    <!-- =========================
         ACADEMIC
    ========================== -->

    <div class="admin-menu-title">
        Academic
    </div>

    <nav class="admin-nav">

        <a href="marks.php"
           class="<?php echo $current_page === 'marks.php' ? 'active' : ''; ?>">

            <span class="admin-nav-icon">📝</span>

            <span class="admin-nav-text">
                Marks
            </span>

        </a>


        <a href="attendance.php"
           class="<?php echo $current_page === 'attendance.php' ? 'active' : ''; ?>">

            <span class="admin-nav-icon">📅</span>

            <span class="admin-nav-text">
                Attendance
            </span>

        </a>


        <a href="backlogs.php"
           class="<?php echo $current_page === 'backlogs.php' ? 'active' : ''; ?>">

            <span class="admin-nav-icon">⚠️</span>

            <span class="admin-nav-text">
                Backlogs
            </span>

        </a>


        <a href="exams.php"
           class="<?php echo $current_page === 'exams.php' ? 'active' : ''; ?>">

            <span class="admin-nav-icon">📋</span>

            <span class="admin-nav-text">
                Exams
            </span>

        </a>


        <a href="notifications.php"
           class="<?php echo $current_page === 'notifications.php' ? 'active' : ''; ?>">

            <span class="admin-nav-icon">🔔</span>

            <span class="admin-nav-text">
                Notifications
            </span>

        </a>

    </nav>


    <!-- =========================
         ACCOUNT
    ========================== -->

    <div class="admin-menu-title">
        Account
    </div>

    <nav class="admin-nav">

        <a href="profile.php"
           class="<?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">

            <span class="admin-nav-icon">👤</span>

            <span class="admin-nav-text">
                Profile
            </span>

        </a>


        <div class="admin-logout">

            <a href="../logout.php">

                <span class="admin-nav-icon">🚪</span>

                <span class="admin-nav-text">
                    Logout
                </span>

            </a>

        </div>

    </nav>


</aside>