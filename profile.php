<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userid = $_SESSION['user_id'];

$upload_error = '';
$upload_success = '';

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['student_photo'])) {
    $file = $_FILES['student_photo'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $file['tmp_name'];
        $file_name = $file['name'];
        $file_size = $file['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        // Verify mime type using finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_tmp);
        finfo_close($finfo);
        
        if (!in_array($file_ext, $allowed_exts) || !in_array($mime_type, $allowed_mimes)) {
            $upload_error = "Invalid file type. Only JPG, JPEG, PNG, GIF, and WEBP images are allowed.";
        } elseif ($file_size > 5 * 1024 * 1024) { // 5MB
            $upload_error = "File size exceeds the 5MB limit.";
        } else {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Delete old photo if exists
            $stmt = $pdo->prepare("SELECT photo FROM student WHERE studentid = :userid");
            $stmt->bindParam(':userid', $userid);
            $stmt->execute();
            $curr_student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($curr_student && !empty($curr_student['photo'])) {
                $old_file = $upload_dir . $curr_student['photo'];
                if (file_exists($old_file)) {
                    @unlink($old_file);
                }
            }
            
            $new_filename = 'avatar_' . $userid . '_' . time() . '.' . $file_ext;
            $dest_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file_tmp, $dest_path)) {
                $stmt = $pdo->prepare("UPDATE student SET photo = :photo WHERE studentid = :userid");
                $stmt->bindParam(':photo', $new_filename);
                $stmt->bindParam(':userid', $userid);
                $stmt->execute();
                
                $upload_success = "Profile photo updated successfully!";
            } else {
                $upload_error = "Failed to save the uploaded file.";
            }
        }
    } else {
        if ($file['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_error = "Upload error occurred. Error code: " . $file['error'];
        }
    }
}

// Handle photo deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_photo'])) {
    $upload_dir = 'uploads/';
    $stmt = $pdo->prepare("SELECT photo FROM student WHERE studentid = :userid");
    $stmt->bindParam(':userid', $userid);
    $stmt->execute();
    $curr_student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($curr_student && !empty($curr_student['photo'])) {
        $old_file = $upload_dir . $curr_student['photo'];
        if (file_exists($old_file)) {
            @unlink($old_file);
        }
        
        $stmt = $pdo->prepare("UPDATE student SET photo = NULL WHERE studentid = :userid");
        $stmt->bindParam(':userid', $userid);
        $stmt->execute();
        
        $upload_success = "Profile photo removed.";
    }
}

// Fetch student details
$stmt = $pdo->prepare("SELECT s.*, p.name as program_name FROM student s JOIN programme p ON s.programid = p.id WHERE s.studentid = :userid");
$stmt->bindParam(':userid', $userid);
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo "Student record not found.";
    exit();
}

if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$active_tab = $_GET['tab'] ?? 'profile';

// Handle success/error alerts in URL
if (isset($_GET['success'])) {
    $upload_success = $_GET['success'];
}

// Handle student self-enrollment from catalog
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['self_enroll'])) {
    $course_to_enroll = (int)($_POST['course_id'] ?? 0);
    if ($course_to_enroll > 0) {
        try {
            // Check if course is paid
            $stmtC = $pdo->prepare("SELECT is_paid, title FROM course WHERE id = :id");
            $stmtC->execute([':id' => $course_to_enroll]);
            $course_info = $stmtC->fetch(PDO::FETCH_ASSOC);
            
            $slip_path = null;
            if ($course_info && $course_info['is_paid']) {
                if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] === UPLOAD_ERR_OK) {
                    $fileTmpPath = $_FILES['payment_slip']['tmp_name'];
                    $fileName = $_FILES['payment_slip']['name'];
                    $fileSize = $_FILES['payment_slip']['size'];
                    $fileType = $_FILES['payment_slip']['type'];
                    $fileNameCmps = explode(".", $fileName);
                    $fileExtension = strtolower(end($fileNameCmps));
                    
                    // Allow specific formats
                    $allowedfileExtensions = array('jpg', 'jpeg', 'png', 'pdf');
                    if (in_array($fileExtension, $allowedfileExtensions)) {
                        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                        $uploadFileDir = './uploads/slips/';
                        if (!is_dir($uploadFileDir)) {
                            mkdir($uploadFileDir, 0777, true);
                        }
                        $dest_path = $uploadFileDir . $newFileName;
                        if (move_uploaded_file($fileTmpPath, $dest_path)) {
                            $slip_path = 'slips/' . $newFileName;
                        } else {
                            throw new Exception("Error saving the uploaded slip file to disk.");
                        }
                    } else {
                        throw new Exception("Invalid file extension. Only JPG, PNG, and PDF are allowed.");
                    }
                } else {
                    // Check if they already have a slip uploaded previously
                    $stmtCheckSlip = $pdo->prepare("SELECT payment_slip FROM student_course WHERE student_id = :sid AND course_id = :cid");
                    $stmtCheckSlip->execute([':sid' => $student['studentid'], ':cid' => $course_to_enroll]);
                    $existing_slip = $stmtCheckSlip->fetchColumn();
                    if (!$existing_slip) {
                        throw new Exception("Payment slip upload is required for paid courses.");
                    }
                    $slip_path = $existing_slip;
                }
            }
            
            $stmtEnroll = $pdo->prepare("
                INSERT INTO student_course (student_id, course_id, status, payment_slip) 
                VALUES (:sid, :cid, 'pending', :slip) 
                ON DUPLICATE KEY UPDATE status='pending', payment_slip=COALESCE(:slip, payment_slip)
            ");
            $stmtEnroll->execute([
                ':sid' => $student['studentid'], 
                ':cid' => $course_to_enroll,
                ':slip' => $slip_path
            ]);
            
            if ($slip_path) {
                $upload_success = "Course request submitted successfully with payment slip! Waiting for admin approval.";
            } else {
                $upload_success = "Course request submitted successfully! Waiting for admin approval.";
            }
        } catch (Exception $e) {
            $upload_error = "Failed to request course: " . $e->getMessage();
        }
    }
}

// Handle student marking lesson as completed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_lesson'])) {
    $lesson_to_complete = (int)($_POST['lesson_id'] ?? 0);
    $course_context = (int)($_POST['course_id'] ?? 0);
    if ($lesson_to_complete > 0) {
        try {
            $stmtComp = $pdo->prepare("INSERT INTO lesson_completion (student_id, lesson_id) VALUES (:sid, :lid) ON DUPLICATE KEY UPDATE student_id=student_id");
            $stmtComp->execute([':sid' => $student['studentid'], ':lid' => $lesson_to_complete]);
            $upload_success = "Lesson marked as completed!";
            
            // Find the next lesson to redirect automatically
            $stmtNext = $pdo->prepare("
                SELECT id FROM lesson 
                WHERE course_id = :cid AND (
                    order_num > (SELECT order_num FROM lesson WHERE id = :lid)
                    OR (order_num = (SELECT order_num FROM lesson WHERE id = :lid) AND id > :lid)
                )
                ORDER BY order_num ASC, id ASC
                LIMIT 1
            ");
            $stmtNext->execute([':cid' => $course_context, ':lid' => $lesson_to_complete]);
            $next_lesson_id = $stmtNext->fetchColumn();
            
            if ($next_lesson_id) {
                header("Location: profile.php?tab=learning&course_id=" . $course_context . "&lesson_id=" . $next_lesson_id . "&success=" . urlencode("Lesson completed! Moved to next lesson."));
                exit();
            } else {
                // Check if course is fully completed
                $stmtAllLessons = $pdo->prepare("SELECT COUNT(*) FROM lesson WHERE course_id = :cid");
                $stmtAllLessons->execute([':cid' => $course_context]);
                $total_lessons = $stmtAllLessons->fetchColumn();
                
                $stmtCompletedLessons = $pdo->prepare("
                    SELECT COUNT(*) FROM lesson_completion lc 
                    JOIN lesson l ON lc.lesson_id = l.id 
                    WHERE lc.student_id = :sid AND l.course_id = :cid
                ");
                $stmtCompletedLessons->execute([':sid' => $student['studentid'], ':cid' => $course_context]);
                $completed_lessons = $stmtCompletedLessons->fetchColumn();
                
                if ($completed_lessons >= $total_lessons) {
                    header("Location: profile.php?tab=certificates&success=" . urlencode("Congratulations! You completed the course and unlocked your certificate."));
                    exit();
                }
            }
        } catch (PDOException $e) {
            $upload_error = "Failed to update lesson completion: " . $e->getMessage();
        }
    }
}

// Fetch enrolled courses for "My Learning" (only approved ones)
$stmtMyCourses = $pdo->prepare("
    SELECT c.*, m.name as module_name, sc.enrolled_at, t.fullname as teacher_name
    FROM student_course sc
    JOIN course c ON sc.course_id = c.id
    JOIN module m ON c.module_id = m.id
    LEFT JOIN teacher t ON c.teacher_id = t.teacherid
    WHERE sc.student_id = :sid AND sc.status = 'approved'
    ORDER BY sc.enrolled_at DESC
");
$stmtMyCourses->execute([':sid' => $student['studentid']]);
$my_courses = $stmtMyCourses->fetchAll(PDO::FETCH_ASSOC);

// Fetch pending requested courses
$stmtPendingCourses = $pdo->prepare("
    SELECT c.*, sc.payment_slip, m.name as module_name, sc.enrolled_at, t.fullname as teacher_name
    FROM student_course sc
    JOIN course c ON sc.course_id = c.id
    JOIN module m ON c.module_id = m.id
    LEFT JOIN teacher t ON c.teacher_id = t.teacherid
    WHERE sc.student_id = :sid AND sc.status = 'pending'
    ORDER BY sc.enrolled_at DESC
");
$stmtPendingCourses->execute([':sid' => $student['studentid']]);
$pending_courses = $stmtPendingCourses->fetchAll(PDO::FETCH_ASSOC);

// Calculate progress details for each enrolled course
$courses_progress = [];
foreach ($my_courses as $mc) {
    // Total lessons
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM lesson WHERE course_id = :cid");
    $stmtCount->execute([':cid' => $mc['id']]);
    $total = $stmtCount->fetchColumn();
    
    // Completed lessons
    $stmtDone = $pdo->prepare("
        SELECT COUNT(*) FROM lesson_completion lc
        JOIN lesson l ON lc.lesson_id = l.id
        WHERE lc.student_id = :sid AND l.course_id = :cid
    ");
    $stmtDone->execute([':sid' => $student['studentid'], ':cid' => $mc['id']]);
    $done = $stmtDone->fetchColumn();
    
    $courses_progress[$mc['id']] = [
        'total' => $total,
        'completed' => $done,
        'percent' => $total > 0 ? round(($done / $total) * 100) : 0
    ];
}

// Fetch Course Catalog (courses student is NOT enrolled in)
$stmtCatalog = $pdo->prepare("
    SELECT c.*, m.name as module_name, t.fullname as teacher_name
    FROM course c
    JOIN module m ON c.module_id = m.id
    LEFT JOIN teacher t ON c.teacher_id = t.teacherid
    WHERE c.id NOT IN (SELECT course_id FROM student_course WHERE student_id = :sid)
    ORDER BY c.title ASC
");
$stmtCatalog->execute([':sid' => $student['studentid']]);
$catalog_courses = $stmtCatalog->fetchAll(PDO::FETCH_ASSOC);

// Fetch details for Active Course in Learner Interface (if specified)
$course_id = (int)($_GET['course_id'] ?? 0);
$active_course = null;
$lessons = [];
$active_lesson = null;
$lesson_completions = [];

if ($course_id > 0) {
    // 1. Verify enrollment
    $stmtCheck = $pdo->prepare("SELECT 1 FROM student_course WHERE student_id = :sid AND course_id = :cid");
    $stmtCheck->execute([':sid' => $student['studentid'], ':cid' => $course_id]);
    $is_enrolled = $stmtCheck->fetchColumn();
    
    if ($is_enrolled) {
        // 2. Fetch course details
        $stmtC = $pdo->prepare("
            SELECT c.*, m.name as module_name, t.fullname as teacher_name, t.email as teacher_email 
            FROM course c 
            JOIN module m ON c.module_id = m.id
            LEFT JOIN teacher t ON c.teacher_id = t.teacherid
            WHERE c.id = :cid
        ");
        $stmtC->execute([':cid' => $course_id]);
        $active_course = $stmtC->fetch(PDO::FETCH_ASSOC);
        
        // 3. Fetch lessons
        $stmtL = $pdo->prepare("SELECT * FROM lesson WHERE course_id = :cid ORDER BY order_num ASC, id ASC");
        $stmtL->execute([':cid' => $course_id]);
        $lessons = $stmtL->fetchAll(PDO::FETCH_ASSOC);
        
        // 4. Fetch completions
        $stmtComp = $pdo->prepare("
            SELECT lesson_id FROM lesson_completion 
            WHERE student_id = :sid AND lesson_id IN (SELECT id FROM lesson WHERE course_id = :cid)
        ");
        $stmtComp->execute([':sid' => $student['studentid'], ':cid' => $course_id]);
        $lesson_completions = $stmtComp->fetchAll(PDO::FETCH_COLUMN);
        
        // 5. Determine active lesson
        $req_lesson_id = (int)($_GET['lesson_id'] ?? 0);
        if ($req_lesson_id > 0) {
            foreach ($lessons as $l) {
                if ($l['id'] === $req_lesson_id) {
                    $active_lesson = $l;
                    break;
                }
            }
        }
        
        // If not specified or not found, pick the first incomplete lesson, or first lesson overall
        if (!$active_lesson && !empty($lessons)) {
            foreach ($lessons as $l) {
                if (!in_array($l['id'], $lesson_completions)) {
                    $active_lesson = $l;
                    break;
                }
            }
            if (!$active_lesson) {
                $active_lesson = $lessons[0];
            }
        }
    }
}

// Generate initials for avatar
$name_parts = explode(' ', trim($student['fullname']));
$initials = strtoupper(substr($name_parts[0], 0, 1));
if (count($name_parts) > 1) {
    $initials .= strtoupper(substr($name_parts[count($name_parts) - 1], 0, 1));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - VitsEDU</title>
    <!-- Modern Typography -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <script src="theme.js"></script>
</head>
<body class="dashboard-body">
    <!-- Global Navbar -->
    <header class="lp-navbar" style="position: fixed; top: 0; width: 100%; z-index: 1000; background: var(--bg-card); border-bottom: 1px solid var(--border-color);">
        <div class="lp-nav-container">
            <a href="index.php" class="lp-logo">Vits<span>EDU</span> <span class="lp-logo-tagline">Learn without limits</span></a>
            
            <div class="lp-search-bar" style="max-width: 400px; margin-left: 20px;">
                <input type="text" placeholder="Search courses...">
                <button aria-label="Search">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </button>
            </div>

            <div class="lp-nav-actions" style="margin-left: auto;">
                <button id="theme-toggle" class="theme-toggle nav-theme-toggle" aria-label="Toggle Dark Mode" style="position: relative; top: 0; right: 0;">
                    <svg class="sun-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <svg class="moon-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                </button>
                <span style="color: var(--text-muted); margin: 0 15px;">|</span>
                <a href="profile.php" class="nav-user-profile">
                    <div class="nav-avatar" style="overflow: hidden; display: flex; align-items: center; justify-content: center; background: var(--primary);">
                        <?php if (!empty($student['photo']) && file_exists('uploads/' . $student['photo'])): ?>
                            <img src="uploads/<?php echo htmlspecialchars($student['photo']); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        <?php else: ?>
                            <?php echo htmlspecialchars($initials); ?>
                        <?php endif; ?>
                    </div>
                    <span style="color: var(--text-main); font-weight: 500; font-family: 'Inter', sans-serif;"><?php echo htmlspecialchars($student['fullname']); ?></span>
                </a>
            </div>
        </div>
    </header>

    <!-- Dashboard Hero Banner -->
    <div class="dashboard-header">
        <div class="dashboard-container profile-hero">
            <div class="profile-avatar-container">
                <form id="photo-upload-form" method="POST" enctype="multipart/form-data" style="margin: 0;">
                    <div class="profile-avatar" onclick="document.getElementById('student_photo_input').click();">
                        <?php if (!empty($student['photo']) && file_exists('uploads/' . $student['photo'])): ?>
                            <img src="uploads/<?php echo htmlspecialchars($student['photo']); ?>" alt="Profile Photo" class="profile-avatar-img">
                        <?php else: ?>
                            <span class="avatar-initials"><?php echo htmlspecialchars($initials); ?></span>
                        <?php endif; ?>
                        <div class="avatar-overlay">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="camera-icon"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                            <span class="overlay-text">Change</span>
                        </div>
                    </div>
                    <input type="file" id="student_photo_input" name="student_photo" accept="image/*" style="display: none;" onchange="document.getElementById('photo-upload-form').submit();">
                </form>
            </div>
            <div class="profile-hero-info">
                <h1 style="font-family: 'Outfit', sans-serif;">Welcome back, <?php echo htmlspecialchars($student['fullname']); ?>!</h1>
                <div style="display: flex; align-items: center; gap: 10px; opacity: 0.9; font-size: 16px; flex-wrap: wrap;">
                    <span>Student ID: <?php echo htmlspecialchars($student['studentid']); ?></span>
                    <span>&nbsp;•&nbsp;</span>
                    <span>Program: <?php echo htmlspecialchars($student['program_name']); ?></span>
                    <?php if (!empty($student['photo']) && file_exists('uploads/' . $student['photo'])): ?>
                        <span>&nbsp;•&nbsp;</span>
                        <form method="POST" style="display: inline; margin: 0;">
                            <button type="submit" name="delete_photo" class="remove-photo-link" style="background: none; border: none; color: rgba(255,255,255,0.9); cursor: pointer; text-decoration: underline; font-size: 16px; padding: 0; font-family: inherit;">Remove Photo</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- LMS Navigation Tabs -->
    <div class="dashboard-tabs">
        <div class="dashboard-tabs-container">
            <a href="profile.php?tab=profile" class="tab-link <?php echo $active_tab === 'profile' ? 'active' : ''; ?>">My Profile</a>
            <a href="profile.php?tab=learning" class="tab-link <?php echo $active_tab === 'learning' ? 'active' : ''; ?>">My Learning</a>
            <a href="profile.php?tab=certificates" class="tab-link <?php echo $active_tab === 'certificates' ? 'active' : ''; ?>">Certificates</a>
            <a href="profile.php?tab=purchase_history" class="tab-link <?php echo $active_tab === 'purchase_history' ? 'active' : ''; ?>">Purchase History</a>
        </div>
    </div>

    <div class="dashboard-content" style="<?php echo ($active_tab !== 'profile' && !($active_tab === 'learning' && $active_course)) ? 'grid-template-columns: 1fr;' : ''; ?>">
        <!-- Display upload messages if any -->
        <?php if (!empty($upload_error)): ?>
            <div class="message error" style="grid-column: span 2; margin-bottom: 0; text-align: left; padding: 12px 20px; border-radius: 12px; display: flex; align-items: center; gap: 10px;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span><?php echo htmlspecialchars($upload_error); ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($upload_success)): ?>
            <div class="message success" style="grid-column: span 2; margin-bottom: 0; text-align: left; padding: 12px 20px; border-radius: 12px; display: flex; align-items: center; gap: 10px; background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <span><?php echo htmlspecialchars($upload_success); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($active_tab === 'profile'): ?>
            <!-- Left Column: Personal Details -->
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h3 style="font-family: 'Outfit', sans-serif;">Personal Information</h3>
                </div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <label>Full Name</label>
                        <p><?php echo htmlspecialchars($student['fullname']); ?></p>
                    </div>
                    <div class="info-item">
                        <label>Email Address</label>
                        <p style="display: flex; align-items: center; gap: 10px;">
                            <?php echo htmlspecialchars($student['email']); ?>
                            <?php if($student['is_verified']): ?>
                                <span style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">VERIFIED</span>
                            <?php else: ?>
                                <span style="background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">UNVERIFIED</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="info-item">
                        <label>Enrolled Program</label>
                        <p><?php echo htmlspecialchars($student['program_name']); ?></p>
                    </div>
                    <div class="info-item">
                        <label>Registration Year</label>
                        <p><?php echo htmlspecialchars($student['yearofregister']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Right Column: Actions & Security -->
            <div class="dashboard-card" style="align-self: start;">
                <div class="dashboard-card-header">
                    <h3 style="font-family: 'Outfit', sans-serif;">Account Settings</h3>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <a href="change_password.php" class="lp-btn lp-btn-outline" style="text-align: center; width: 100%;">Change Password</a>
                    
                    <form method="POST" style="margin: 0;">
                        <button type="submit" name="logout" class="lp-btn lp-btn-outline" style="width: 100%; border-color: #ef4444; color: #ef4444; background: transparent; cursor: pointer;">Log Out</button>
                    </form>
                </div>
            </div>

        <?php elseif ($active_tab === 'learning' && $active_course): ?>
            <!-- Course Learning Interface -->
            <div class="lms-layout" style="grid-column: span 2;">
                <!-- Sidebar: Lesson List -->
                <div class="lms-sidebar-card">
                    <a href="profile.php?tab=learning" class="lp-btn lp-btn-outline" style="margin-bottom: 20px; display: inline-flex; align-items: center; gap: 8px; width: 100%; justify-content: center; text-decoration: none; font-size: 14px; font-weight: 600;">
                        ← Back to My Learning
                    </a>
                    <div class="lms-sidebar-title" style="margin-top: 10px; font-size: 16px; line-height: 1.3; font-weight: 700;">
                        <?php echo htmlspecialchars($active_course['title']); ?>
                    </div>
                    
                    <!-- Progress meter in sidebar -->
                    <?php 
                    $p = $courses_progress[$active_course['id']];
                    ?>
                    <div class="lms-progress-container" style="margin-top: 15px; margin-bottom: 20px;">
                        <div class="lms-progress-label" style="font-size: 12px; font-weight: 600;">
                            <span>Progress</span>
                            <span><?php echo $p['percent']; ?>%</span>
                        </div>
                        <div class="lms-progress-bar" style="height: 6px;">
                            <div class="lms-progress-fill" style="width: <?php echo $p['percent']; ?>%;"></div>
                        </div>
                    </div>

                    <div class="lms-list-group">
                        <?php foreach ($lessons as $l): 
                            $is_completed = in_array($l['id'], $lesson_completions);
                            $is_active = $active_lesson && $active_lesson['id'] === $l['id'];
                        ?>
                            <a href="profile.php?tab=learning&course_id=<?php echo $active_course['id']; ?>&lesson_id=<?php echo $l['id']; ?>" class="lms-list-item <?php echo $is_active ? 'active' : ''; ?>" style="display: flex; align-items: center; gap: 10px; font-size: 13.5px;">
                                <span class="lms-completion-tick" style="font-size: 14px;">
                                    <?php if ($is_completed): ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="3" width="16" height="16"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    <?php else: ?>
                                        <span style="display: inline-block; width: 14px; height: 14px; border: 2px solid var(--text-muted); border-radius: 50%;"></span>
                                    <?php endif; ?>
                                </span>
                                <span style="line-height: 1.3;">Lesson <?php echo htmlspecialchars($l['order_num']); ?>: <?php echo htmlspecialchars($l['title']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Main Content Area: Lesson Viewer -->
                <div class="lms-main-content">
                    <?php if ($active_lesson): ?>
                        <div style="margin-bottom: 25px;">
                            <span class="lms-badge lms-badge-module" style="margin-bottom: 8px; background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-block;"><?php echo htmlspecialchars($active_course['module_name']); ?></span>
                            <h2 style="font-family: 'Outfit', sans-serif; margin: 0 0 5px 0; color: var(--text-main); font-size: 26px; font-weight: 700;"><?php echo htmlspecialchars($active_lesson['title']); ?></h2>
                            <p style="font-size: 13px; color: var(--text-muted); margin: 0;">
                                Assigned Instructor: <strong><?php echo htmlspecialchars($active_course['teacher_name'] ?? 'To be assigned'); ?></strong>
                                <?php if (!empty($active_course['teacher_email'])): ?>
                                    (<?php echo htmlspecialchars($active_course['teacher_email']); ?>)
                                <?php endif; ?>
                            </p>
                        </div>

                        <?php if (!empty($active_lesson['video_url'])): ?>
                            <div class="video-wrapper">
                                <iframe src="<?php echo htmlspecialchars($active_lesson['video_url']); ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                            </div>
                        <?php endif; ?>

                        <div class="lesson-notes" style="margin-top: 25px;">
                            <h4 style="font-family: 'Outfit', sans-serif; font-size: 18px; margin: 0 0 12px 0; color: var(--text-main); font-weight: 600; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">Lesson Content & Notes</h4>
                            <div style="white-space: pre-wrap; font-size: 15px; line-height: 1.6; color: var(--text-main); font-family: 'Inter', sans-serif;"><?php echo htmlspecialchars($active_lesson['content']); ?></div>
                        </div>

                        <?php if (!empty($active_lesson['attachment'])): ?>
                            <div class="lms-attachment-card">
                                <div class="lms-attachment-info">
                                    <div class="lms-attachment-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; color: var(--text-main); font-size: 14px;">Download Resource</div>
                                        <div style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($active_lesson['attachment']); ?></div>
                                    </div>
                                </div>
                                <a href="uploads/<?php echo htmlspecialchars($active_lesson['attachment']); ?>" download class="lp-btn lp-btn-outline" style="text-decoration: none; padding: 6px 16px; font-size: 13px; font-weight: 600;">Download Attachment</a>
                            </div>
                        <?php endif; ?>

                        <!-- Complete Button Footer -->
                        <div style="margin-top: 35px; border-top: 1px solid var(--border-color); padding-top: 25px; display: flex; justify-content: flex-end; align-items: center;">
                            <?php if (in_array($active_lesson['id'], $lesson_completions)): ?>
                                <div style="display: flex; align-items: center; gap: 8px; color: #10b981; font-weight: 600;">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="20" height="20"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    <span>Lesson Completed</span>
                                </div>
                            <?php else: ?>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="lesson_id" value="<?php echo $active_lesson['id']; ?>">
                                    <input type="hidden" name="course_id" value="<?php echo $active_course['id']; ?>">
                                    <button type="submit" name="complete_lesson" class="lp-btn lp-btn-primary" style="cursor: pointer; border: none; font-size: 15px; font-weight: 600; padding: 10px 24px;">Mark as Completed & Continue</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px 20px;">
                            <p style="color: var(--text-muted); font-style: italic; font-size: 15px;">No lessons have been added to this course yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($active_tab === 'learning'): ?>
            <!-- My Enrolled Courses Section -->
            <div style="width: 100%;">
                <div class="lms-section-header">
                    <h3 class="lms-section-title">My Enrolled Courses</h3>
                </div>
                
                <?php if (empty($my_courses)): ?>
                    <div class="dashboard-card" style="text-align: center; padding: 40px 20px; width: 100%;">
                        <p style="color: var(--text-muted); font-style: italic; margin-bottom: 20px; font-size: 15px;">You are not enrolled in any courses yet.</p>
                        <a href="#catalog" class="lp-btn lp-btn-primary" style="text-decoration: none; display: inline-block;">Browse Catalog</a>
                    </div>
                <?php else: ?>
                    <div class="lms-grid">
                        <?php 
                        $gradients = [
                            'linear-gradient(135deg, #4f46e5 0%, #312e81 100%)',
                            'linear-gradient(135deg, #0891b2 0%, #083344 100%)',
                            'linear-gradient(135deg, #059669 0%, #064e3b 100%)',
                            'linear-gradient(135deg, #d97706 0%, #78350f 100%)',
                            'linear-gradient(135deg, #db2777 0%, #500724 100%)',
                            'linear-gradient(135deg, #7c3aed 0%, #4c1d95 100%)'
                        ];
                        foreach ($my_courses as $mc): 
                            $prog = $courses_progress[$mc['id']];
                        ?>
                            <div class="lms-card">
                                <div class="lms-card-content">
                                    <div style="background: <?php echo $gradients[$mc['id'] % count($gradients)]; ?>; display: flex; align-items: center; justify-content: center; height: 140px; border-radius: 12px; margin-bottom: 18px; padding: 15px; position: relative;">
                                        <span style="color: white; font-weight: 700; font-size: 16px; text-align: center; font-family: 'Outfit', sans-serif; text-shadow: 0 2px 4px rgba(0,0,0,0.3);"><?php echo htmlspecialchars($mc['title']); ?></span>
                                    </div>
                                    <span class="lms-badge lms-badge-module" style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-block; margin-bottom: 5px;"><?php echo htmlspecialchars($mc['module_name']); ?></span>
                                    <h4><?php echo htmlspecialchars($mc['title']); ?></h4>
                                    <p style="margin-bottom: 10px; font-size: 13px; color: var(--text-muted); line-height: 1.4;"><?php echo htmlspecialchars(substr($mc['description'] ?? '', 0, 90)) . (strlen($mc['description'] ?? '') > 90 ? '...' : ''); ?></p>
                                    <p style="font-size: 12.5px; font-weight: 500; color: var(--text-main); margin-bottom: 15px; margin-top: 5px;">Instructor: <?php echo htmlspecialchars($mc['teacher_name'] ?? 'To be assigned'); ?></p>
                                </div>
                                
                                <div class="lms-progress-container" style="margin-top: auto; margin-bottom: 15px;">
                                    <div class="lms-progress-label">
                                        <span>Course Progress</span>
                                        <span><?php echo $prog['percent']; ?>%</span>
                                    </div>
                                    <div class="lms-progress-bar">
                                        <div class="lms-progress-fill" style="width: <?php echo $prog['percent']; ?>%;"></div>
                                    </div>
                                </div>
                                
                                <div class="lms-btn-group">
                                    <a href="profile.php?tab=learning&course_id=<?php echo $mc['id']; ?>" class="lp-btn lp-btn-primary" style="text-decoration: none; width: 100%; text-align: center; justify-content: center;">
                                        <?php echo $prog['percent'] > 0 ? 'Resume Learning' : 'Start Learning'; ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($pending_courses)): ?>
                <!-- Requested Courses (Pending Approval) -->
                <div style="margin-top: 40px; border-top: 1px dashed var(--border-color); padding-top: 35px; width: 100%;">
                    <div class="lms-section-header">
                        <h3 class="lms-section-title">Requested Courses (Pending Approval)</h3>
                    </div>
                    <div class="lms-grid">
                        <?php 
                        $gradients_pending = [
                            'linear-gradient(135deg, #475569 0%, #1e293b 100%)'
                        ];
                        foreach ($pending_courses as $pc): 
                        ?>
                            <div class="lms-card" style="opacity: 0.9; border-style: dashed; border-color: #f59e0b;">
                                <div class="lms-card-content">
                                    <div style="background: <?php echo $gradients_pending[0]; ?>; display: flex; align-items: center; justify-content: center; height: 140px; border-radius: 12px; margin-bottom: 18px; padding: 15px; position: relative;">
                                        <span style="color: white; font-weight: 700; font-size: 16px; text-align: center; font-family: 'Outfit', sans-serif; text-shadow: 0 2px 4px rgba(0,0,0,0.3);"><?php echo htmlspecialchars($pc['title']); ?></span>
                                    </div>
                                    <div style="margin-bottom: 8px;">
                                        <span class="lms-badge" style="background: rgba(245, 158, 11, 0.1); color: #d97706; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-block; text-transform: uppercase;">Pending Approval</span>
                                        <span class="lms-badge" style="background: <?php echo $pc['is_paid'] ? 'rgba(59, 130, 246, 0.1)' : 'rgba(16, 185, 129, 0.1)'; ?>; color: <?php echo $pc['is_paid'] ? 'var(--secondary)' : '#10b981'; ?>; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; margin-left: 5px; display: inline-block;">
                                            <?php echo $pc['is_paid'] ? 'LKR ' . number_format($pc['price'], 2) : 'Free'; ?>
                                        </span>
                                    </div>
                                    <h4 style="margin-top: 0;"><?php echo htmlspecialchars($pc['title']); ?></h4>
                                    <p style="margin-bottom: 10px; font-size: 13px; color: var(--text-muted); line-height: 1.4;"><?php echo htmlspecialchars(substr($pc['description'] ?? '', 0, 90)) . (strlen($pc['description'] ?? '') > 90 ? '...' : ''); ?></p>
                                    <p style="font-size: 12.5px; font-weight: 500; color: var(--text-main); margin-bottom: 15px; margin-top: 5px;">Instructor: <?php echo htmlspecialchars($pc['teacher_name'] ?? 'To be assigned'); ?></p>
                                    
                                    <?php if ($pc['is_paid']): ?>
                                        <div style="background: rgba(245, 158, 11, 0.05); border: 1px solid rgba(245, 158, 11, 0.2); border-radius: 8px; padding: 10px; margin-top: 10px; font-size: 11.5px; color: var(--text-main); line-height: 1.4; text-align: left;">
                                            <strong style="color: #d97706;">Payment Required:</strong> Transfer <strong>LKR <?php echo number_format($pc['price'], 2); ?></strong> to BOC Account <strong>908123456</strong>. Include Student ID <strong><?php echo htmlspecialchars($student['studentid']); ?></strong> as reference.
                                        </div>
                                        
                                        <!-- Slip Status/Upload -->
                                        <div style="margin-top: 12px; text-align: left;">
                                            <?php if (!empty($pc['payment_slip'])): ?>
                                                <div style="background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 8px; padding: 8px 10px; font-size: 12px; color: var(--text-main); display: flex; align-items: center; justify-content: space-between;">
                                                    <span style="color: #10b981; font-weight: 600;">✓ Slip Uploaded</span>
                                                    <a href="uploads/<?php echo htmlspecialchars($pc['payment_slip']); ?>" target="_blank" style="color: var(--secondary); text-decoration: underline; font-weight: 500;">View Slip</a>
                                                </div>
                                            <?php else: ?>
                                                <form method="POST" enctype="multipart/form-data" style="margin: 0; display: flex; flex-direction: column; gap: 6px;">
                                                    <input type="hidden" name="self_enroll" value="1">
                                                    <input type="hidden" name="course_id" value="<?php echo $pc['id']; ?>">
                                                    <span style="font-size: 11.5px; font-weight: 600; color: #ef4444; display: block;">⚠️ No payment slip uploaded yet:</span>
                                                    <div style="display: flex; gap: 8px; align-items: center;">
                                                        <input type="file" name="payment_slip" accept=".jpg,.jpeg,.png,.pdf" required style="font-size: 11px; max-width: 140px; color: var(--text-muted);">
                                                        <button type="submit" class="lp-btn lp-btn-primary" style="padding: 4px 10px; font-size: 11px; height: auto; border: none; cursor: pointer; background: #d97706; color: white !important;">Upload</button>
                                                    </div>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div style="margin-top: auto; padding-top: 12px; text-align: center; font-size: 13px; color: #d97706; font-weight: 600; font-family: 'Inter', sans-serif; border-top: 1px dashed var(--border-color);">
                                    ⌛ Waiting for verification
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Course Catalog Section -->
            <div id="catalog" style="margin-top: 50px; border-top: 1px solid var(--border-color); padding-top: 40px; width: 100%;">
                <div class="lms-section-header">
                    <h3 class="lms-section-title">Explore Course Catalog</h3>
                </div>
                
                <?php if (empty($catalog_courses)): ?>
                    <div class="dashboard-card" style="text-align: center; padding: 40px 20px; width: 100%;">
                        <p style="color: var(--text-muted); font-style: italic; font-size: 15px;">You are already enrolled in all available courses!</p>
                    </div>
                <?php else: ?>
                    <div class="lms-grid">
                        <?php 
                        $gradients_catalog = [
                            'linear-gradient(135deg, #1e293b 0%, #0f172a 100%)',
                            'linear-gradient(135deg, #0c4a6e 0%, #0c4a6e 100%)',
                            'linear-gradient(135deg, #0f766e 0%, #115e59 100%)',
                            'linear-gradient(135deg, #b45309 0%, #92400e 100%)',
                            'linear-gradient(135deg, #be185d 0%, #9d174d 100%)',
                            'linear-gradient(135deg, #6d28d9 0%, #5b21b6 100%)'
                        ];
                        foreach ($catalog_courses as $cc): 
                        ?>
                            <div class="lms-card">
                                <div class="lms-card-content">
                                    <div style="background: <?php echo $gradients_catalog[$cc['id'] % count($gradients_catalog)]; ?>; display: flex; align-items: center; justify-content: center; height: 140px; border-radius: 12px; margin-bottom: 18px; padding: 15px; position: relative;">
                                        <span style="color: white; font-weight: 700; font-size: 16px; text-align: center; font-family: 'Outfit', sans-serif; text-shadow: 0 2px 4px rgba(0,0,0,0.3);"><?php echo htmlspecialchars($cc['title']); ?></span>
                                    </div>
                                    <span class="lms-badge lms-badge-module" style="background: rgba(14, 116, 144, 0.1); color: #0284c7; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-block; margin-bottom: 5px;"><?php echo htmlspecialchars($cc['module_name']); ?></span>
                                    <span class="lms-badge" style="background: <?php echo $cc['is_paid'] ? 'rgba(59, 130, 246, 0.1)' : 'rgba(16, 185, 129, 0.1)'; ?>; color: <?php echo $cc['is_paid'] ? 'var(--secondary)' : '#10b981'; ?>; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; margin-left: 5px; display: inline-block; margin-bottom: 5px;">
                                        <?php echo $cc['is_paid'] ? 'LKR ' . number_format($cc['price'], 2) : 'Free'; ?>
                                    </span>
                                    <h4><?php echo htmlspecialchars($cc['title']); ?></h4>
                                    <p style="margin-bottom: 10px; font-size: 13px; color: var(--text-muted); line-height: 1.4;"><?php echo htmlspecialchars(substr($cc['description'] ?? '', 0, 90)) . (strlen($cc['description'] ?? '') > 90 ? '...' : ''); ?></p>
                                    <p style="font-size: 12.5px; font-weight: 500; color: var(--text-main); margin-bottom: 15px; margin-top: 5px;">Instructor: <?php echo htmlspecialchars($cc['teacher_name'] ?? 'To be assigned'); ?></p>
                                </div>
                                <div class="lms-btn-group" style="margin-top: auto;">
                                    <form method="POST" style="margin: 0; width: 100%;">
                                        <input type="hidden" name="course_id" value="<?php echo $cc['id']; ?>">
                                        <button type="submit" name="self_enroll" onclick="confirmEnrollment(event, <?php echo $cc['is_paid']; ?>, '<?php echo number_format($cc['price'], 2); ?>', '<?php echo htmlspecialchars($cc['title'], ENT_QUOTES); ?>', this.form)" class="lp-btn lp-btn-outline" style="width: 100%; border-color: var(--primary); color: var(--primary); background: transparent; cursor: pointer; font-size: 14px; font-weight: 600; text-align: center; justify-content: center; display: block;">Enroll in Course</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($active_tab === 'certificates'): ?>
            <div class="dashboard-card" style="width: 100%; grid-column: span 2;">
                <div class="dashboard-card-header">
                    <h3 style="font-family: 'Outfit', sans-serif;">Earned Certificates</h3>
                </div>
                
                <?php 
                $completed_courses_count = 0;
                $certificates_list = [];
                foreach ($my_courses as $mc) {
                    if (isset($courses_progress[$mc['id']]) && $courses_progress[$mc['id']]['percent'] === 100) {
                        $certificates_list[] = $mc;
                        $completed_courses_count++;
                    }
                }
                ?>
                
                <?php if ($completed_courses_count === 0): ?>
                    <div style="text-align: center; padding: 40px 20px;">
                        <div style="font-size: 48px; margin-bottom: 15px;">🎓</div>
                        <p style="color: var(--text-muted); font-style: italic; font-size: 16px; margin: 0 0 12px 0;">No certificates earned yet.</p>
                        <p style="color: var(--text-muted); font-size: 13.5px; max-width: 400px; margin: 0 auto; line-height: 1.5;">Complete 100% of the lessons in any course to unlock your official printable Certificate of Completion.</p>
                    </div>
                <?php else: ?>
                    <p style="font-size: 14.5px; color: var(--text-muted); margin-bottom: 25px;">Congratulations! You have completed the following courses and earned printable certificates:</p>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <?php foreach ($certificates_list as $cert): ?>
                            <div style="background: rgba(255,255,255,0.01); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap;">
                                <div style="display: flex; align-items: center; gap: 15px;">
                                    <div style="font-size: 32px; background: rgba(16, 185, 129, 0.1); color: #10b981; width: 56px; height: 56px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">🏆</div>
                                    <div>
                                        <span class="lms-badge lms-badge-module" style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; display: inline-block; margin-bottom: 4px;"><?php echo htmlspecialchars($cert['module_name']); ?></span>
                                        <h4 style="margin: 0; font-family: 'Outfit', sans-serif; font-size: 17px; color: var(--text-main);"><?php echo htmlspecialchars($cert['title']); ?></h4>
                                        <p style="margin: 2px 0 0 0; font-size: 12.5px; color: var(--text-muted);">Instructor: <?php echo htmlspecialchars($cert['teacher_name'] ?? 'To be assigned'); ?></p>
                                    </div>
                                </div>
                                <a href="view_certificate.php?course_id=<?php echo $cert['id']; ?>" target="_blank" class="lp-btn lp-btn-primary" style="text-decoration: none; padding: 8px 20px; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                                    Print Certificate
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($active_tab === 'purchase_history'): ?>
            <div class="dashboard-card" style="width: 100%; grid-column: span 2;">
                <div class="dashboard-card-header">
                    <h3 style="font-family: 'Outfit', sans-serif;">Purchase & Enrollment History</h3>
                </div>
                
                <?php if (empty($my_courses)): ?>
                    <div style="text-align: center; padding: 40px 20px;">
                        <p style="color: var(--text-muted); font-style: italic; font-size: 15px;">No transactions found.</p>
                    </div>
                <?php else: ?>
                    <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 25px;">Below is the transaction log of your registered courses.</p>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; text-align: left; font-family: 'Inter', sans-serif; font-size: 14.5px;">
                            <thead>
                                <tr style="border-bottom: 2px solid var(--border-color); color: var(--text-main); font-weight: 600;">
                                    <th style="padding: 12px 16px;">Transaction ID</th>
                                    <th style="padding: 12px 16px;">Date Enrolled</th>
                                    <th style="padding: 12px 16px;">Course Title</th>
                                    <th style="padding: 12px 16px;">Paid Amount</th>
                                    <th style="padding: 12px 16px;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_courses as $mc): 
                                    $txn_date = date('Y-m-d', strtotime($mc['enrolled_at']));
                                    $txn_id = 'TXN-' . str_pad($mc['id'] * 2783, 6, '0', STR_PAD_LEFT);
                                ?>
                                    <tr style="border-bottom: 1px solid var(--border-color); color: var(--text-muted);">
                                        <td style="padding: 14px 16px; font-family: monospace; font-size: 13.5px; color: var(--text-main); font-weight: 600;"><?php echo $txn_id; ?></td>
                                        <td style="padding: 14px 16px;"><?php echo htmlspecialchars($txn_date); ?></td>
                                        <td style="padding: 14px 16px; color: var(--text-main); font-weight: 500;"><?php echo htmlspecialchars($mc['title']); ?></td>
                                        <td style="padding: 14px 16px; font-weight: 500; color: var(--text-main);"><?php echo $mc['is_paid'] ? 'LKR ' . number_format($mc['price'], 2) : 'Free'; ?></td>
                                        <td style="padding: 14px 16px;"><span style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">COMPLETED</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Student Payment Modal for Paid Course Enrollments -->
    <div id="paymentModal" class="lms-modal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.65); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); align-items: center; justify-content: center;">
        <div class="dashboard-card" style="width: 90%; max-width: 500px; margin: auto; border: 1px solid var(--glass-border); background: var(--glass-bg); padding: 25px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
                <h3 style="font-family: 'Outfit', sans-serif; margin: 0; font-size: 20px; color: var(--text-main);">Payment Required</h3>
                <span onclick="closePaymentModal()" style="cursor: pointer; font-size: 24px; color: var(--text-muted); font-weight: bold; line-height: 1;">&times;</span>
            </div>
            
            <form id="payment_slip_form" method="POST" enctype="multipart/form-data" action="profile.php?tab=learning" style="margin: 0;">
                <input type="hidden" name="self_enroll" value="1">
                <input type="hidden" id="modal_course_id" name="course_id" value="">
                
                <div style="font-size: 14.5px; color: var(--text-main); line-height: 1.5; margin-bottom: 20px;">
                    <p style="margin-bottom: 12px;">You are requesting enrollment in the following paid course:</p>
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; margin-bottom: 15px;">
                        <div style="font-weight: 700; font-size: 16px; color: var(--primary);" id="pay_course_title">-</div>
                        <div style="font-size: 14px; font-weight: 600; margin-top: 5px;" id="pay_course_price">-</div>
                    </div>
                    
                    <p style="margin-bottom: 10px; font-weight: 600; color: #d97706;">Bank Transfer Payment Instructions:</p>
                    <div style="background: rgba(245, 158, 11, 0.05); border: 1px dashed rgba(245, 158, 11, 0.3); border-radius: 8px; padding: 15px; font-size: 13.5px; margin-bottom: 15px;">
                        <div style="margin-bottom: 5px;">🏛️ <strong>Bank:</strong> Bank of Ceylon (BOC)</div>
                        <div style="margin-bottom: 5px;">👤 <strong>Account Name:</strong> VitsEDU (Pvt) Ltd</div>
                        <div style="margin-bottom: 5px;">🔢 <strong>Account Number:</strong> 908123456</div>
                        <div style="margin-bottom: 5px;">📍 <strong>Branch:</strong> Fort Branch</div>
                        <div style="margin-top: 10px; font-weight: 700; color: #ef4444;">
                            ⚠️ IMPORTANT: Enter your Student ID (<?php echo htmlspecialchars($student['studentid']); ?>) as the transfer reference.
                        </div>
                    </div>

                    <!-- Payment Slip File Input -->
                    <div style="margin-bottom: 15px; text-align: left;">
                        <label for="payment_slip_file" style="font-weight: 600; display: block; margin-bottom: 8px; color: var(--text-main);">Upload Transfer Slip (JPG, PNG, PDF)*</label>
                        <input type="file" id="payment_slip_file" name="payment_slip" accept=".jpg,.jpeg,.png,.pdf" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: rgba(0,0,0,0.05); color: var(--text-main); font-size: 13.5px;">
                    </div>
                    
                    <p style="font-size: 12.5px; color: var(--text-muted);">After transferring the amount, please select the bank slip image or PDF and click 'Confirm & Upload' below. Our administrators will verify the transfer and activate your course.</p>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 20px;">
                    <button type="button" onclick="closePaymentModal()" class="lp-btn lp-btn-outline" style="flex: 1; cursor: pointer; text-align: center; justify-content: center;">Cancel</button>
                    <button type="submit" class="lp-btn lp-btn-primary" style="flex: 1; border: none; cursor: pointer; text-align: center; justify-content: center; background: #d97706; color: white !important;">Confirm & Upload</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function confirmEnrollment(event, isPaid, price, title, form) {
        event.preventDefault();
        if (isPaid === 1) {
            document.getElementById('pay_course_title').innerText = title;
            document.getElementById('pay_course_price').innerText = 'Price: LKR ' + price;
            
            // Set the course ID in the modal form
            var cidInput = form.querySelector('input[name="course_id"]');
            if (cidInput) {
                document.getElementById('modal_course_id').value = cidInput.value;
            }
            document.getElementById('paymentModal').style.display = 'flex';
        } else {
            if (confirm('Are you sure you want to enroll in "' + title + '" for free?')) {
                form.submit();
            }
        }
    }

    function closePaymentModal() {
        document.getElementById('paymentModal').style.display = 'none';
        document.getElementById('modal_course_id').value = '';
        document.getElementById('payment_slip_file').value = '';
    }

    // Close modal when clicking outside content area
    var originalWindowClick = window.onclick;
    window.onclick = function(event) {
        if (originalWindowClick) {
            originalWindowClick(event);
        }
        var modal = document.getElementById('paymentModal');
        if (event.target == modal) {
            closePaymentModal();
        }
    }
    </script>
</body>
</html>
