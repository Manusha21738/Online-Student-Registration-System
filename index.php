<?php
session_start();
require_once 'db.php';

// Fetch courses from database for the home page list
try {
    $stmtHomeCourses = $pdo->query("
        SELECT c.*, m.name as module_name, t.fullname as teacher_name 
        FROM course c 
        JOIN module m ON c.module_id = m.id 
        LEFT JOIN teacher t ON c.teacher_id = t.teacherid 
        ORDER BY c.id DESC
        LIMIT 6
    ");
    $home_courses = $stmtHomeCourses->fetchAll(PDO::FETCH_ASSOC);
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
                <a href="signup.php" class="lp-btn lp-btn-primary lp-btn-large">Join for Free</a>
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

    <!-- Broad Selection of Courses -->
    <section id="courses" class="lp-courses-section">
        <h2>A broad selection of courses</h2>
        <p class="lp-courses-subtitle">Choose from over 100,000 online video courses with new additions published every month</p>
        
        <div class="lp-course-grid">
            <?php if (empty($home_courses)): ?>
                <!-- Fallback to static courses if none in DB -->
                <a href="signup.php" class="lp-course-card">
                    <div class="lp-course-img-wrapper">
                        <img src="assets/course_1_1779098507828.png" alt="Web Development">
                    </div>
                    <div class="lp-course-info">
                        <h3>The Complete Web Development Bootcamp</h3>
                        <p class="lp-instructor">Dr. Angela Yu</p>
                        <div class="lp-rating">
                            <span class="lp-rating-number">4.7</span>
                            <span class="lp-stars">⭐⭐⭐⭐⭐</span>
                            <span class="lp-reviews">(291,481)</span>
                        </div>
                        <div class="lp-price">$14.99 <span>$84.99</span></div>
                        <span class="lp-bestseller">Bestseller</span>
                    </div>
                </a>
                
                <a href="signup.php" class="lp-course-card">
                    <div class="lp-course-img-wrapper">
                        <img src="assets/course_2_1779098623204.png" alt="Data Science">
                    </div>
                    <div class="lp-course-info">
                        <h3>Machine Learning A-Z™: AI, Python & R</h3>
                        <p class="lp-instructor">Kirill Eremenko, Hadelin de Ponteves</p>
                        <div class="lp-rating">
                            <span class="lp-rating-number">4.5</span>
                            <span class="lp-stars">⭐⭐⭐⭐½</span>
                            <span class="lp-reviews">(162,109)</span>
                        </div>
                        <div class="lp-price">$16.99 <span>$99.99</span></div>
                    </div>
                </a>
            <?php else: ?>
                <?php 
                $img_placeholders = [
                    'assets/course_1_1779098507828.png',
                    'assets/course_2_1779098623204.png',
                    'assets/course_3_1779098678589.png'
                ];
                $idx = 0;
                foreach ($home_courses as $c): 
                    $img = $img_placeholders[$idx % count($img_placeholders)];
                    $idx++;
                    
                    // Link to register if not logged in, or details if logged in
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
                    <a href="<?php echo htmlspecialchars($link); ?>" class="lp-course-card">
                        <div class="lp-course-img-wrapper">
                            <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($c['title']); ?>">
                        </div>
                        <div class="lp-course-info">
                            <span class="lms-badge lms-badge-module" style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-block; margin-bottom: 5px;"><?php echo htmlspecialchars($c['module_name']); ?></span>
                            <h3><?php echo htmlspecialchars($c['title']); ?></h3>
                            <p class="lp-instructor">Instructor: <?php echo htmlspecialchars($c['teacher_name'] ?? 'To be assigned'); ?></p>
                            <p style="font-size: 12px; color: var(--text-muted); line-height: 1.4; margin-top: 5px;"><?php echo htmlspecialchars(substr($c['description'] ?? '', 0, 100)) . (strlen($c['description'] ?? '') > 100 ? '...' : ''); ?></p>
                            <div class="lp-rating" style="margin-top: 10px;">
                                <span class="lp-rating-number">4.8</span>
                                <span class="lp-stars">⭐⭐⭐⭐⭐</span>
                                <span class="lp-reviews">(Dynamic Course)</span>
                            </div>
                            <div class="lp-price">Free <span>$99.00</span></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="lp-footer">
        <div class="lp-footer-content">
            <div class="lp-logo">Vits<span>EDU</span> <span class="lp-logo-tagline">Learn without limits</span></div>
            <p>&copy; <?php echo date('Y'); ?> VitsEDU, Inc. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
