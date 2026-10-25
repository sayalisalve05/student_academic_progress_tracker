<aside class="sidebar">

    <div class="sidebar-brand">
        <div class="brand-mark">🎓</div>

        <div class="brand-text">
            <div class="brand-title">Academic Tracker</div>
            <div class="brand-subtitle">Student Panel</div>
        </div>
    </div>

    <nav class="nav">

        <div class="nav-title">MY ACADEMIC</div>

        <a href="dashboard.php">
            <span class="nav-icon">🏠</span>
            <span>Dashboard</span>
        </a>

        <a href="profile.php">
            <span class="nav-icon">👤</span>
            <span>My Profile</span>
        </a>
        

        <a href="marks.php">
            <span class="nav-icon">📊</span>
            <span>My Marks</span>
        </a>


<a href="syllabus.php"
   class="<?php echo $current_page === 'syllabus.php' ? 'active' : ''; ?>">
    <span class="admin-nav-icon">📚</span>
    <span class="admin-nav-text">Syllabus</span>
</a>

<a href="notes.php"
   class="<?php echo $current_page === 'notes.php' ? 'active' : ''; ?>">
    <span class="admin-nav-icon">📝</span>
    <span class="admin-nav-text">Notes</span>
</a>

        <a href="attendance.php">
            <span class="nav-icon">📅</span>
            <span>Attendance</span>
        </a>

        <a href="backlogs.php">
            <span class="nav-icon">⚠️</span>
            <span>Backlogs</span>
        </a>

        <a href="progress.php">
            <span class="nav-icon">📈</span>
            <span>Academic Progress</span>
        </a>

        <a href="exams.php">
            <span class="nav-icon">📝</span>
            <span>Upcoming Exams</span>
        </a>

        <a href="notifications.php">
            <span class="nav-icon">🔔</span>
            <span>Notifications</span>
        </a>

        <a href="report.php">
            <span class="nav-icon">📄</span>
            <span>Academic Report</span>
        </a>



        <div class="nav-title account-title">ACCOUNT</div>

        <a href="../logout.php">
            <span class="nav-icon">🚪</span>
            <span>Logout</span>
        </a>

    </nav>
</aside>

<style>
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 260px;
    height: 100vh;
    background: #111827;
    color: #fff;
    z-index: 1000;
    overflow-y: auto;
    box-sizing: border-box;
}

.sidebar-brand {
    height: 90px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 13px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    box-sizing: border-box;
}

.brand-mark {
    width: 52px;
    height: 52px;
    background: #4f46e5;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 25px;
    flex-shrink: 0;
}

.brand-title {
    font-size: 16px;
    font-weight: 700;
    white-space: nowrap;
}

.brand-subtitle {
    font-size: 11px;
    color: #9ca3af;
    margin-top: 4px;
}

.nav {
    padding: 20px 14px;
}

.nav-title {
    font-size: 10px;
    font-weight: 700;
    color: #6b7280;
    letter-spacing: 1px;
    padding: 0 12px;
    margin: 5px 0 10px;
}

.nav a {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    min-height: 46px;
    padding: 11px 13px;
    margin-bottom: 5px;
    border-radius: 9px;
    color: #d1d5db;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    box-sizing: border-box;
    transition: all 0.2s ease;
}

.nav a:hover {
    background: #1f2937;
    color: #fff;
}

.nav a.active {
    background: #4f46e5;
    color: #fff;
    box-shadow: 0 4px 12px rgba(79,70,229,0.25);
}

.nav-icon {
    width: 22px;
    min-width: 22px;
    text-align: center;
    font-size: 17px;
}

.account-title {
    margin-top: 25px;
}

.sidebar::-webkit-scrollbar {
    width: 5px;
}

.sidebar::-webkit-scrollbar-track {
    background: #111827;
}

.sidebar::-webkit-scrollbar-thumb {
    background: #374151;
    border-radius: 10px;
}

@media (max-width: 800px) {
    .sidebar {
        width: 220px;
    }

    .nav a {
        font-size: 13px;
    }
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const currentPage = window.location.pathname.split("/").pop();

    document.querySelectorAll(".nav a").forEach(function (link) {

        const linkPage = link.getAttribute("href").split("/").pop();

        if (linkPage === currentPage) {
            link.classList.add("active");
        }

    });

});
</script>