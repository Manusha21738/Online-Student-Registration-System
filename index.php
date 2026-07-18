<?php
session_start();
require_once 'db.php';

// Fetch courses from database for the home page list
$grouped_courses = [];
$grades_list = ['Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11 (O/L)', 'Advanced Level (A/L)'];

try {
    $stmtHomeCourses = $pdo->query("
        SELECT c.*, m.name as module_name, t.fullname as teacher_name 
        FROM course c 
        JOIN module m ON c.module_id = m.id 
        LEFT JOIN teacher t ON c.teacher_id = t.teacherid 
        ORDER BY CASE 
            WHEN c.grade_level = 'Grade 6' THEN 1
            WHEN c.grade_level = 'Grade 7' THEN 2
            WHEN c.grade_level = 'Grade 8' THEN 3
            WHEN c.grade_level = 'Grade 9' THEN 4
            WHEN c.grade_level = 'Grade 10' THEN 5
            WHEN c.grade_level = 'Grade 11 (O/L)' THEN 6
            WHEN c.grade_level = 'Advanced Level (A/L)' THEN 7
            ELSE 8 
        END ASC, c.title ASC
    ");
    $home_courses = $stmtHomeCourses->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($home_courses as $c) {
        $gl = $c['grade_level'] ?: 'Grade 11 (O/L)';
        $subject = 'ICT'; // Default
        if (strpos(strtolower($c['module_name']), 'math') !== false || strpos(strtolower($c['title']), 'math') !== false || strpos(strtolower($c['title']), 'matric') !== false || strpos(strtolower($c['title']), 'graph') !== false) {
            $subject = 'Mathematics';
        }
        $grouped_courses[$gl][$subject][] = $c;
    }
} catch (PDOException $e) {
    $home_courses = [];
}

// Determine dashboard redirect based on role
$dashboard_link = '';
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'student') {
        $dashboard_link = 'profile.php';
    } elseif ($_SESSION['role'] === 'teacher') {
        $dashboard_link = 'teacher_dashboard.php';
    } elseif ($_SESSION['role'] === 'admin') {
        $dashboard_link = 'admin_dashboard.php';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VitsEDU - Learn without limits</title>
    <!-- Modern Typography -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <script src="theme.js"></script>
</head>
<body class="landing-page-body">
    <!-- Navbar -->
    <header class="lp-navbar">
        <div class="lp-nav-container">
            <a href="index.php" class="lp-logo">Vits<span>EDU</span> <span class="lp-logo-tagline">Learn without limits</span></a>
            
            <div class="lp-categories">
                <span>Explore Categories</span>
            </div>

            <div class="lp-search-bar">
                <input type="text" placeholder="Search for anything...">
                <button aria-label="Search">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </button>
            </div>

            <div class="lp-nav-actions">
                <button id="theme-toggle" class="theme-toggle nav-theme-toggle" aria-label="Toggle Dark Mode">
                    <svg class="sun-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <svg class="moon-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                </button>
                <?php if (!empty($dashboard_link)): ?>
                    <a href="<?php echo htmlspecialchars($dashboard_link); ?>" class="lp-btn lp-btn-primary">Go to Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="lp-btn lp-btn-outline">Log In</a>
                    <div class="lp-dropdown">
                        <button class="lp-btn lp-btn-primary lp-dropdown-toggle">Register</button>
                        <div class="lp-dropdown-menu">
                            <a href="signup.php">Student Register</a>
                            <a href="teacher_signup.php">Teacher Register</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="lp-hero">
        <div class="lp-hero-content">
            <h1>Learn without limits</h1>
            <p>Start, switch, or advance your career with more than 10,000 courses, Professional Certificates, and degrees from world-class universities and companies.</p>
            <div class="lp-hero-cta">
                <?php if (!empty($dashboard_link)): ?>
                    <a href="<?php echo htmlspecialchars($dashboard_link); ?>" class="lp-btn lp-btn-primary lp-btn-large">Go to Dashboard</a>
                <?php else: ?>
                    <a href="signup.php" class="lp-btn lp-btn-primary lp-btn-large">Join for Free</a>
                <?php endif; ?>
                <a href="#courses" class="lp-btn lp-btn-outline lp-btn-large">Explore Courses</a>
            </div>
        </div>
        <div class="lp-hero-image">
            <img src="assets/hero_image_1779098484423.png" alt="Students learning">
        </div>
    </section>

    <!-- Trusted By Banner -->
    <section class="lp-trusted">
        <p>Trusted by over 15,000 companies and millions of learners around the world</p>
        <div class="lp-trusted-logos">
            <div class="lp-logo-placeholder">Google</div>
            <div class="lp-logo-placeholder">Microsoft</div>
            <div class="lp-logo-placeholder">Stanford</div>
            <div class="lp-logo-placeholder">IBM</div>
            <div class="lp-logo-placeholder">Meta</div>
        </div>
    </section>

    <!-- Broad Selection of Courses / Syllabus Explorer -->
    <section id="courses" class="lp-courses-section">
        <h2>Syllabus Explorer</h2>
        <p class="lp-courses-subtitle">Explore curriculum courses from Grade 6 to Advanced Level, mapped for school students.</p>
        
        <!-- Grades Tabs Selector -->
        <div class="syllabus-tabs-container">
            <div class="syllabus-tabs">
                <?php 
                $first_grade = true;
                foreach ($grades_list as $grade): 
                    $grade_safe = preg_replace('/[^a-zA-Z0-9]/', '', $grade);
                ?>
                    <button class="syllabus-tab-btn <?php echo $first_grade ? 'active' : ''; ?>" onclick="openSyllabusGrade(event, '<?php echo $grade_safe; ?>')">
                        <?php echo htmlspecialchars($grade); ?>
                    </button>
                <?php 
                    $first_grade = false;
                endforeach; 
                ?>
            </div>
        </div>

        <!-- Grade Content Sections -->
        <?php 
        $first_grade = true;
        foreach ($grades_list as $grade): 
            $grade_safe = preg_replace('/[^a-zA-Z0-9]/', '', $grade);
            $grade_data = $grouped_courses[$grade] ?? [];
        ?>
            <div id="grade-panel-<?php echo $grade_safe; ?>" class="syllabus-grade-panel" style="display: <?php echo $first_grade ? 'block' : 'none'; ?>;">
                
                <div class="syllabus-subjects-grid">
                    
                    <!-- ICT Column -->
                    <div class="syllabus-subject-column">
                        <div class="syllabus-subject-header">
                            <span class="subject-icon">💻</span>
                            <h3>Information & Communication Technology</h3>
                        </div>
                        <div class="syllabus-courses-list">
                            <?php 
                            $ict_courses = $grade_data['ICT'] ?? [];
                            if (empty($ict_courses)):
                            ?>
                                <div class="syllabus-empty-state">No ICT courses available for this grade yet.</div>
                            <?php else: ?>
                                <?php foreach ($ict_courses as $c): 
                                    $link = 'signup.php';
                                    if (isset($_SESSION['role'])) {
                                        if ($_SESSION['role'] === 'student') {
                                            $link = 'profile.php?tab=learning&course_id=' . $c['id'];
                                        } elseif ($_SESSION['role'] === 'teacher') {
                                            $link = 'teacher_dashboard.php?tab=lms';
                                        } else {
                                            $link = 'admin_dashboard.php?tab=lms';
                                        }
                                    }
                                ?>
                                    <div class="syllabus-course-row-card">
                                        <div class="row-card-body">
                                            <h4><?php echo htmlspecialchars($c['title']); ?></h4>
                                            <p><?php echo htmlspecialchars($c['description']); ?></p>
                                            <div class="row-card-meta">
                                                <span class="row-price <?php echo $c['is_paid'] ? 'paid' : 'free'; ?>">
                                                    <?php echo $c['is_paid'] ? 'LKR ' . number_format($c['price'], 2) : 'Free'; ?>
                                                </span>
                                                <span class="row-instructor">Instructor: <?php echo htmlspecialchars($c['teacher_name'] ?? 'To be assigned'); ?></span>
                                            </div>
                                        </div>
                                        <a href="<?php echo htmlspecialchars($link); ?>" class="syllabus-row-btn">
                                            <?php echo $c['is_paid'] ? 'Enroll & Pay' : 'Start Free'; ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Mathematics Column -->
                    <div class="syllabus-subject-column">
                        <div class="syllabus-subject-header">
                            <span class="subject-icon">📐</span>
                            <h3>Mathematics</h3>
                        </div>
                        <div class="syllabus-courses-list">
                            <?php 
                            $math_courses = $grade_data['Mathematics'] ?? [];
                            if (empty($math_courses)):
                            ?>
                                <div class="syllabus-empty-state">No Mathematics courses available for this grade yet.</div>
                            <?php else: ?>
                                <?php foreach ($math_courses as $c): 
                                    $link = 'signup.php';
                                    if (isset($_SESSION['role'])) {
                                        if ($_SESSION['role'] === 'student') {
                                            $link = 'profile.php?tab=learning&course_id=' . $c['id'];
                                        } elseif ($_SESSION['role'] === 'teacher') {
                                            $link = 'teacher_dashboard.php?tab=lms';
                                        } else {
                                            $link = 'admin_dashboard.php?tab=lms';
                                        }
                                    }
                                ?>
                                    <div class="syllabus-course-row-card">
                                        <div class="row-card-body">
                                            <h4><?php echo htmlspecialchars($c['title']); ?></h4>
                                            <p><?php echo htmlspecialchars($c['description']); ?></p>
                                            <div class="row-card-meta">
                                                <span class="row-price <?php echo $c['is_paid'] ? 'paid' : 'free'; ?>">
                                                    <?php echo $c['is_paid'] ? 'LKR ' . number_format($c['price'], 2) : 'Free'; ?>
                                                </span>
                                                <span class="row-instructor">Instructor: <?php echo htmlspecialchars($c['teacher_name'] ?? 'To be assigned'); ?></span>
                                            </div>
                                        </div>
                                        <a href="<?php echo htmlspecialchars($link); ?>" class="syllabus-row-btn">
                                            <?php echo $c['is_paid'] ? 'Enroll & Pay' : 'Start Free'; ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

            </div>
        <?php 
            $first_grade = false;
        endforeach; 
        ?>
    </section>

    <script>
    function openSyllabusGrade(evt, gradeSafe) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("syllabus-grade-panel");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }
        tablinks = document.getElementsByClassName("syllabus-tab-btn");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove("active");
        }
        document.getElementById("grade-panel-" + gradeSafe).style.display = "block";
        evt.currentTarget.classList.add("active");
    }
    </script>

    <!-- Footer -->
    <footer class="lp-footer">
        <div class="lp-footer-content">
            <div class="lp-logo">Vits<span>EDU</span> <span class="lp-logo-tagline">Learn without limits</span></div>
            <p>&copy; <?php echo date('Y'); ?> VitsEDU, Inc. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
