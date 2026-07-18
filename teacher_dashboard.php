<?php
/**
 * teacher_dashboard.php
 * 
 * Main dashboard for approved teachers.
 * Allows teachers to view their profile and request modules to teach.
 */
session_start();
require_once 'db.php';

// Route protection: Must be logged in as teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$userid = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Fetch teacher details
$stmt = $pdo->prepare("SELECT * FROM teacher WHERE teacherid = :userid");
$stmt->execute([':userid' => $userid]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher || $teacher['is_approved'] == 0) {
    session_destroy();
    header("Location: login.php?error=" . urlencode("Your account is pending admin approval."));
    exit();
}

// Generate avatar initials
$name_parts = explode(' ', $teacher['fullname']);
$initials = '';
if (count($name_parts) > 0) {
    $initials .= strtoupper(substr($name_parts[0], 0, 1));
}
if (count($name_parts) > 1) {
    $initials .= strtoupper(substr($name_parts[count($name_parts) - 1], 0, 1));
}

// Handle Logout
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Handle Module Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_modules'])) {
    $selected_modules = $_POST['modules'] ?? [];
    
    if (empty($selected_modules)) {
        $error_msg = "Please select at least one module to request.";
    } else {
        try {
            $pdo->beginTransaction();
            
            foreach ($selected_modules as $mid) {
                $mid = (int)$mid;
                
                // Fetch current status for this module if exists
                $stmtCheck = $pdo->prepare("SELECT status FROM teacher_module WHERE teacher_id = :tid AND module_id = :mid");
                $stmtCheck->execute([':tid' => $teacher['teacherid'], ':mid' => $mid]);
                $current_status = $stmtCheck->fetchColumn();
                
                if ($current_status === false) {
                    // Not requested yet
                    $stmtIns = $pdo->prepare("INSERT INTO teacher_module (teacher_id, module_id, status) VALUES (:tid, :mid, 'pending')");
                    $stmtIns->execute([':tid' => $teacher['teacherid'], ':mid' => $mid]);
                } elseif ($current_status === 'rejected') {
                    // Re-requesting rejected module
                    $stmtUpd = $pdo->prepare("UPDATE teacher_module SET status = 'pending' WHERE teacher_id = :tid AND module_id = :mid");
                    $stmtUpd->execute([':tid' => $teacher['teacherid'], ':mid' => $mid]);
                }
            }
            
            $pdo->commit();
            $success_msg = "Your module teaching requests have been submitted successfully!";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_msg = "Error submitting request: " . $e->getMessage();
        }
    }
}

// Fetch all modules with registration status for this teacher
$stmtModules = $pdo->prepare("
    SELECT m.id, m.name, tm.status 
    FROM module m 
    LEFT JOIN teacher_module tm ON m.id = tm.module_id AND tm.teacher_id = :tid
    ORDER BY m.id ASC
");
$stmtModules->execute([':tid' => $teacher['teacherid']]);
$approved_module_ids = [];
foreach ($modules as $m) {
    if ($m['status'] === 'approved') {
        $approved_module_ids[] = (int)$m['id'];
    }
}

$teacher_courses = [];
if (!empty($approved_module_ids)) {
    $in_query = implode(',', $approved_module_ids);
    $teacher_courses = $pdo->query("
        SELECT c.*, m.name as module_name 
        FROM course c 
        JOIN module m ON c.module_id = m.id 
        WHERE c.module_id IN ($in_query) 
        ORDER BY c.title ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

$teacher_course_ids = array_column($teacher_courses, 'id');
$teacher_lessons = [];
if (!empty($teacher_course_ids)) {
    $in_query_c = implode(',', $teacher_course_ids);
    $lessons_raw = $pdo->query("
        SELECT * FROM lesson 
        WHERE course_id IN ($in_query_c) 
        ORDER BY course_id ASC, order_num ASC, id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($lessons_raw as $l) {
        $teacher_lessons[$l['course_id']][] = $l;
    }
}

// Action Handlers for Teacher (Lessons only)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Helper to verify course ownership/approval
    $verify_course = function($cid) use ($teacher_course_ids) {
        return in_array((int)$cid, $teacher_course_ids);
    };
    
    // File upload helper
    $handle_upload = function($file) {
        if (isset($file) && $file['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $file['tmp_name'];
            $file_name = $file['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_exts = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'zip', 'png', 'jpg', 'jpeg'];
            
            if (in_array($file_ext, $allowed_exts)) {
                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $new_filename = 'attach_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
                if (move_uploaded_file($file_tmp, $upload_dir . $new_filename)) {
                    return $new_filename;
                }
            }
        }
        return null;
    };

    // Add Lesson
    if (isset($_POST['add_lesson'])) {
        $cid = (int)($_POST['course_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $video_url = trim($_POST['video_url'] ?? '');
        $order_num = (int)($_POST['order_num'] ?? 0);
        
        if (!$verify_course($cid)) {
            $error_msg = "Unauthorized course selection.";
        } elseif (empty($title)) {
            $error_msg = "Lesson title is required.";
        } else {
            try {
                $attachment = $handle_upload($_FILES['attachment'] ?? null);
                $stmt = $pdo->prepare("INSERT INTO lesson (course_id, title, content, video_url, attachment, order_num) VALUES (:cid, :title, :content, :video, :attachment, :order)");
                $stmt->execute([
                    ':cid' => $cid,
                    ':title' => $title,
                    ':content' => $content,
                    ':video' => $video_url,
                    ':attachment' => $attachment,
                    ':order' => $order_num
                ]);
                $success_msg = "Lesson added successfully!";
                
                // Refresh lessons
                $teacher_lessons = [];
                $lessons_raw = $pdo->query("
                    SELECT * FROM lesson 
                    WHERE course_id IN (" . implode(',', $teacher_course_ids) . ") 
                    ORDER BY course_id ASC, order_num ASC, id ASC
                ")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($lessons_raw as $l) {
                    $teacher_lessons[$l['course_id']][] = $l;
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to add lesson: " . $e->getMessage();
            }
        }
    }

    // Delete Lesson
    if (isset($_POST['delete_lesson'])) {
        $lid = (int)($_POST['lesson_id'] ?? 0);
        if ($lid > 0) {
            try {
                // Verify lesson belongs to an authorized course
                $stmtL = $pdo->prepare("SELECT course_id, attachment FROM lesson WHERE id = :lid");
                $stmtL->execute([':lid' => $lid]);
                $lesson = $stmtL->fetch(PDO::FETCH_ASSOC);
                
                if ($lesson && $verify_course($lesson['course_id'])) {
                    if ($lesson['attachment'] && file_exists('uploads/' . $lesson['attachment'])) {
                        @unlink('uploads/' . $lesson['attachment']);
                    }
                    $stmtDel = $pdo->prepare("DELETE FROM lesson WHERE id = :lid");
                    $stmtDel->execute([':lid' => $lid]);
                    $success_msg = "Lesson deleted successfully!";
                    
                    // Refresh lessons
                    $teacher_lessons = [];
                    $lessons_raw = $pdo->query("
                        SELECT * FROM lesson 
                        WHERE course_id IN (" . implode(',', $teacher_course_ids) . ") 
                        ORDER BY course_id ASC, order_num ASC, id ASC
                    ")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($lessons_raw as $l) {
                        $teacher_lessons[$l['course_id']][] = $l;
                    }
                } else {
                    $error_msg = "Unauthorized lesson selection.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to delete lesson: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - VitsEDU</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <script src="theme.js"></script>
</head>
<body class="dashboard-body">
    <!-- Global Navbar -->
    <header class="lp-navbar" style="position: fixed; top: 0; width: 100%; z-index: 1000; background: var(--bg-card); border-bottom: 1px solid var(--border-color);">
        <div class="lp-nav-container">
            <a href="index.php" class="lp-logo">Vits<span>EDU</span> <span class="lp-logo-tagline">Learn without limits</span></a>
            
            <div class="lp-nav-actions" style="margin-left: auto;">
                <button id="theme-toggle" class="theme-toggle nav-theme-toggle" aria-label="Toggle Dark Mode" style="position: relative; top: 0; right: 0;">
                    <svg class="sun-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <svg class="moon-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                </button>
                <span style="color: var(--text-muted); margin: 0 15px;">|</span>
                <div class="nav-user-profile">
                    <div class="nav-avatar" style="overflow: hidden; display: flex; align-items: center; justify-content: center; background: var(--primary);">
                        <?php echo htmlspecialchars($initials); ?>
                    </div>
                    <span style="color: var(--text-main); font-weight: 500; font-family: 'Inter', sans-serif;"><?php echo htmlspecialchars($teacher['fullname']); ?></span>
                </div>
            </div>
        </div>
    </header>

    <!-- Dashboard Hero Banner -->
    <div class="dashboard-header">
        <div class="dashboard-container profile-hero">
            <div class="profile-avatar-container">
                <div class="profile-avatar">
                    <span class="avatar-initials"><?php echo htmlspecialchars($initials); ?></span>
                </div>
            </div>
            <div class="profile-hero-info">
                <h1 style="font-family: 'Outfit', sans-serif;">Welcome, Instructor <?php echo htmlspecialchars($teacher['fullname']); ?>!</h1>
                <div style="display: flex; align-items: center; gap: 10px; opacity: 0.9; font-size: 16px; flex-wrap: wrap;">
                    <span>Teacher ID: <?php echo htmlspecialchars($teacher['teacherid']); ?></span>
                    <span>&nbsp;•&nbsp;</span>
                    <span>Portal: Teacher Dashboard</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <?php
    $active_tab = $_GET['tab'] ?? 'dashboard';
    ?>
    <div class="dashboard-tabs">
        <div class="dashboard-tabs-container">
            <a href="teacher_dashboard.php?tab=dashboard" class="tab-link <?php echo $active_tab === 'dashboard' ? 'active' : ''; ?>">My Dashboard</a>
            <a href="teacher_dashboard.php?tab=lms" class="tab-link <?php echo $active_tab === 'lms' ? 'active' : ''; ?>">My Courses & Lessons</a>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="dashboard-content" style="<?php echo $active_tab === 'lms' ? 'grid-template-columns: 1fr 2fr;' : ''; ?>">
        <?php if (!empty($success_msg)): ?>
            <div class="message success" style="grid-column: span 2; margin-bottom: 0; text-align: left; padding: 12px 20px; border-radius: 12px; display: flex; align-items: center; gap: 10px; background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <span><?php echo htmlspecialchars($success_msg); ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="message error" style="grid-column: span 2; margin-bottom: 0; text-align: left; padding: 12px 20px; border-radius: 12px; display: flex; align-items: center; gap: 10px;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span><?php echo htmlspecialchars($error_msg); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($active_tab === 'dashboard'): ?>
            <!-- Left Column: Request Modules to Teach -->
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h3 style="font-family: 'Outfit', sans-serif;">Select Modules to Teach</h3>
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 4px;">Choose the courses you are qualified to teach. Your selection will require Admin approval.</p>
                </div>
                
                <form action="teacher_dashboard.php?tab=dashboard" method="POST" style="margin-top: 1.5rem;">
                    <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 2rem;">
                        <?php foreach ($modules as $mod): ?>
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px;">
                                <label style="display: flex; align-items: center; gap: 12px; font-weight: 500; cursor: pointer; flex-grow: 1;">
                                    <input type="checkbox" name="modules[]" value="<?php echo $mod['id']; ?>" 
                                        <?php echo ($mod['status'] === 'pending' || $mod['status'] === 'approved') ? 'checked disabled' : ''; ?>
                                        style="width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer;">
                                    <span style="font-family: 'Inter', sans-serif; font-size: 15px;"><?php echo htmlspecialchars($mod['name']); ?></span>
                                </label>
                                
                                <div>
                                    <?php if ($mod['status'] === 'approved'): ?>
                                        <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">Approved</span>
                                    <?php elseif ($mod['status'] === 'pending'): ?>
                                        <span style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">Pending Approval</span>
                                    <?php elseif ($mod['status'] === 'rejected'): ?>
                                        <span style="background: rgba(239, 68, 68, 0.15); color: #ef4444; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">Rejected</span>
                                    <?php else: ?>
                                        <span style="background: rgba(148, 163, 184, 0.15); color: var(--text-muted); padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 500;">Not Requested</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="submit" name="request_modules" class="lp-btn lp-btn-primary" style="width: 100%; border: none; cursor: pointer;">Submit Module Requests</button>
                </form>
            </div>

            <!-- Right Column: Personal Info & Settings -->
            <div style="display: flex; flex-direction: column; gap: 24px;">
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h3 style="font-family: 'Outfit', sans-serif;">Instructor Information</h3>
                    </div>
                    
                    <div class="info-grid" style="margin-top: 1rem;">
                        <div class="info-item">
                            <label>Full Name</label>
                            <p><?php echo htmlspecialchars($teacher['fullname']); ?></p>
                        </div>
                        <div class="info-item">
                            <label>Email Address</label>
                            <p><?php echo htmlspecialchars($teacher['email']); ?></p>
                        </div>
                        <div class="info-item">
                            <label>Qualifications</label>
                            <p><?php echo nl2br(htmlspecialchars($teacher['qualifications'])); ?></p>
                        </div>
                        <div class="info-item">
                            <label>Account Status</label>
                            <p style="display: flex; align-items: center; gap: 6px;">
                                <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">APPROVED & ACTIVE</span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h3 style="font-family: 'Outfit', sans-serif;">Account Settings</h3>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 15px; margin-top: 1rem;">
                        <a href="change_password.php" class="lp-btn lp-btn-outline" style="text-align: center; width: 100%;">Change Password</a>
                        
                        <form method="POST" style="margin: 0;">
                            <button type="submit" name="logout" class="lp-btn lp-btn-outline" style="width: 100%; border-color: #ef4444; color: #ef4444; background: transparent; cursor: pointer;">Log Out</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php elseif ($active_tab === 'lms'): ?>
            <!-- LMS Left Panel: Add Lesson Form -->
            <div style="display: flex; flex-direction: column; gap: 30px;">
                <div class="dashboard-card" style="width: 100%;">
                    <div class="dashboard-card-header">
                        <h3 style="font-family: 'Outfit', sans-serif;">Add Lesson to Course</h3>
                    </div>
                    
                    <?php if (empty($teacher_courses)): ?>
                        <p style="color: var(--text-muted); font-style: italic; line-height: 1.5; padding: 10px 0;">You do not have any approved courses/modules to manage lessons. Please submit a request on the dashboard tab and wait for Admin approval.</p>
                    <?php else: ?>
                        <form action="teacher_dashboard.php?tab=lms" method="POST" enctype="multipart/form-data" class="lms-form">
                            <div class="lms-form-group">
                                <label for="course_select">Target Course</label>
                                <select id="course_select" name="course_id" required class="lms-select">
                                    <option value="" disabled selected>Select Course</option>
                                    <?php foreach ($teacher_courses as $tc): ?>
                                        <option value="<?php echo $tc['id']; ?>"><?php echo htmlspecialchars($tc['title']); ?> (<?php echo htmlspecialchars($tc['module_name']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="lms-form-group">
                                <label for="lesson_title">Lesson Title</label>
                                <input type="text" id="lesson_title" name="title" required class="lms-input" placeholder="e.g. Intro to NumPy Arrays">
                            </div>
                            <div class="lms-form-group">
                                <label for="video_url">Video Embed URL (YouTube/Vimeo)</label>
                                <input type="url" id="video_url" name="video_url" class="lms-input" placeholder="e.g. https://www.youtube.com/embed/IHZwWFHWa-w">
                                <small style="color: var(--text-muted); font-size: 11px;">Must be the embed URL (with /embed/ in the link).</small>
                            </div>
                            <div class="lms-form-group">
                                <label for="attachment">Attachment Document (PDF, Slides, Zip)</label>
                                <input type="file" id="attachment" name="attachment" class="lms-input" style="padding: 6px;">
                            </div>
                            <div class="lms-form-group">
                                <label for="order_num">Lesson Order (Position Number)</label>
                                <input type="number" id="order_num" name="order_num" value="1" min="1" class="lms-input">
                            </div>
                            <div class="lms-form-group">
                                <label for="content">Lesson Content/Notes</label>
                                <textarea id="content" name="content" rows="4" class="lms-textarea" placeholder="Add lecture notes, reading materials, or instructions..."></textarea>
                            </div>
                            <button type="submit" name="add_lesson" class="lp-btn lp-btn-primary" style="border: none; cursor: pointer; width: 100%;">Add Lesson</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- LMS Right Panel: Courses & Lessons list -->
            <div style="display: flex; flex-direction: column; gap: 30px;">
                <div class="dashboard-card" style="width: 100%;">
                    <div class="dashboard-card-header">
                        <h3 style="font-family: 'Outfit', sans-serif;">My Teaching Courses & Lessons</h3>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 20px; margin-top: 1.5rem;">
                        <?php if (empty($teacher_courses)): ?>
                            <p style="color: var(--text-muted); font-style: italic; text-align: center; padding: 20px;">No courses assigned under your approved teaching modules.</p>
                        <?php else: ?>
                            <?php foreach ($teacher_courses as $tc): ?>
                                <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 12px; padding: 18px; display: flex; flex-direction: column; gap: 15px;">
                                    <div>
                                        <span class="lms-badge lms-badge-module" style="margin-bottom: 6px;"><?php echo htmlspecialchars($tc['module_name']); ?></span>
                                        <h4 style="margin: 0; font-family: 'Outfit', sans-serif; font-size: 18px; color: var(--text-main);"><?php echo htmlspecialchars($tc['title']); ?></h4>
                                        <?php if (!empty($tc['description'])): ?>
                                            <p style="font-size: 13px; color: var(--text-muted); margin-top: 6px; line-height: 1.4;"><?php echo htmlspecialchars($tc['description']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div style="border-top: 1px solid var(--border-color); padding-top: 12px;">
                                        <h5 style="margin: 0 0 10px 0; font-family: 'Outfit', sans-serif; font-size: 14px; color: var(--text-main); font-weight: 600;">Lessons in this Course:</h5>
                                        <?php 
                                        $c_lessons = $teacher_lessons[$tc['id']] ?? [];
                                        if (empty($c_lessons)):
                                        ?>
                                            <p style="color: var(--text-muted); font-size: 13px; font-style: italic; margin: 5px 0;">No lessons added yet.</p>
                                        <?php else: ?>
                                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                                <?php foreach ($c_lessons as $l): ?>
                                                    <div style="background: rgba(255,255,255,0.01); border: 1px dashed var(--border-color); border-radius: 8px; padding: 12px; display: flex; justify-content: space-between; align-items: center; gap: 15px;">
                                                        <div style="font-size: 13px;">
                                                            <strong style="color: var(--primary);">Lesson <?php echo $l['order_num']; ?>:</strong> 
                                                            <span style="color: var(--text-main); font-weight: 500;"><?php echo htmlspecialchars($l['title']); ?></span>
                                                            <div style="display: flex; gap: 10px; margin-top: 4px; font-size: 11px; color: var(--text-muted);">
                                                                <?php if (!empty($l['video_url'])): ?>
                                                                    <span>🎥 Video Linked</span>
                                                                <?php endif; ?>
                                                                <?php if (!empty($l['attachment'])): ?>
                                                                    <span>📁 Attachment: <a href="uploads/<?php echo htmlspecialchars($l['attachment']); ?>" target="_blank" style="color: var(--secondary); text-decoration: none;"><?php echo htmlspecialchars($l['attachment']); ?></a></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <form action="teacher_dashboard.php?tab=lms" method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete this lesson?');">
                                                            <input type="hidden" name="lesson_id" value="<?php echo $l['id']; ?>">
                                                            <button type="submit" name="delete_lesson" style="border: none; background: none; color: #ef4444; font-size: 11px; cursor: pointer; text-decoration: underline; font-family: inherit; font-weight: 500;">Delete</button>
                                                        </form>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
