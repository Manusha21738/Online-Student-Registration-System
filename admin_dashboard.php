<?php
/**
 * admin_dashboard.php
 * 
 * Dashboard for Administrator to manage pending teacher registrations
 * and pending teaching module requests.
 */
session_start();
require_once 'db.php';

// Route protection: Must be logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$userid = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Handle Logout
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Handle Approve Teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_teacher'])) {
    $tid = $_POST['teacher_id'] ?? '';
    if (!empty($tid)) {
        try {
            $stmt = $pdo->prepare("UPDATE teacher SET is_approved = 1 WHERE teacherid = :tid");
            $stmt->execute([':tid' => $tid]);
            $success_msg = "Teacher application approved successfully!";
        } catch (PDOException $e) {
            $error_msg = "Failed to approve teacher: " . $e->getMessage();
        }
    }
}

// Handle Reject/Delete Teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_teacher'])) {
    $tid = $_POST['teacher_id'] ?? '';
    if (!empty($tid)) {
        try {
            $pdo->beginTransaction();
            // Delete from users table
            $stmtUser = $pdo->prepare("DELETE FROM users WHERE user_id = :tid");
            $stmtUser->execute([':tid' => $tid]);
            
            // Delete from teacher table
            $stmtTeach = $pdo->prepare("DELETE FROM teacher WHERE teacherid = :tid");
            $stmtTeach->execute([':tid' => $tid]);
            
            $pdo->commit();
            $success_msg = "Teacher application rejected and account removed.";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_msg = "Failed to reject teacher: " . $e->getMessage();
        }
    }
}

// Handle Approve Module Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_module'])) {
    $tid = $_POST['teacher_id'] ?? '';
    $mid = (int)($_POST['module_id'] ?? 0);
    if (!empty($tid) && $mid > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE teacher_module SET status = 'approved' WHERE teacher_id = :tid AND module_id = :mid");
            $stmt->execute([':tid' => $tid, ':mid' => $mid]);
            $success_msg = "Teaching module request approved!";
        } catch (PDOException $e) {
            $error_msg = "Failed to approve request: " . $e->getMessage();
        }
    }
}

// Handle Reject Module Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_module'])) {
    $tid = $_POST['teacher_id'] ?? '';
    $mid = (int)($_POST['module_id'] ?? 0);
    if (!empty($tid) && $mid > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE teacher_module SET status = 'rejected' WHERE teacher_id = :tid AND module_id = :mid");
            $stmt->execute([':tid' => $tid, ':mid' => $mid]);
            $success_msg = "Teaching module request rejected.";
        } catch (PDOException $e) {
            $error_msg = "Failed to reject request: " . $e->getMessage();
        }
    }
}

// --- LMS Action Handlers ---

// File upload helper
function handleAttachmentUpload($file) {
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
}

// Add Course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $module_id = (int)($_POST['module_id'] ?? 0);
    $teacher_id = trim($_POST['teacher_id'] ?? '');
    if ($teacher_id === '') $teacher_id = null;
    
    if (empty($title) || $module_id <= 0) {
        $error_msg = "Course title and module are required.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO course (module_id, teacher_id, title, description) VALUES (:mid, :tid, :title, :desc)");
            $stmt->execute([':mid' => $module_id, ':tid' => $teacher_id, ':title' => $title, ':desc' => $description]);
            $success_msg = "Course created successfully!";
        } catch (PDOException $e) {
            $error_msg = "Failed to create course: " . $e->getMessage();
        }
    }
}

// Edit Course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_course'])) {
    $cid = (int)($_POST['course_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $module_id = (int)($_POST['module_id'] ?? 0);
    
    if ($cid <= 0 || empty($title) || $module_id <= 0) {
        $error_msg = "All course fields are required.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE course SET module_id = :mid, title = :title, description = :desc WHERE id = :cid");
            $stmt->execute([':mid' => $module_id, ':title' => $title, ':desc' => $description, ':cid' => $cid]);
            $success_msg = "Course updated successfully!";
        } catch (PDOException $e) {
            $error_msg = "Failed to update course: " . $e->getMessage();
        }
    }
}

// Delete Course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_course'])) {
    $cid = (int)($_POST['course_id'] ?? 0);
    if ($cid > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM course WHERE id = :cid");
            $stmt->execute([':cid' => $cid]);
            $success_msg = "Course deleted successfully!";
        } catch (PDOException $e) {
            $error_msg = "Failed to delete course: " . $e->getMessage();
        }
    }
}

// Add Lesson
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_lesson'])) {
    $cid = (int)($_POST['course_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $video_url = trim($_POST['video_url'] ?? '');
    $order_num = (int)($_POST['order_num'] ?? 0);
    
    $attachment = handleAttachmentUpload($_FILES['attachment'] ?? null);
    
    if ($cid <= 0 || empty($title)) {
        $error_msg = "Course and lesson title are required.";
    } else {
        try {
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
        } catch (PDOException $e) {
            $error_msg = "Failed to add lesson: " . $e->getMessage();
        }
    }
}

// Edit Lesson
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_lesson'])) {
    $lid = (int)($_POST['lesson_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $video_url = trim($_POST['video_url'] ?? '');
    $order_num = (int)($_POST['order_num'] ?? 0);
    
    if ($lid <= 0 || empty($title)) {
        $error_msg = "Lesson ID and title are required.";
    } else {
        try {
            $attachment = handleAttachmentUpload($_FILES['attachment'] ?? null);
            
            if ($attachment) {
                $stmtOld = $pdo->prepare("SELECT attachment FROM lesson WHERE id = :lid");
                $stmtOld->execute([':lid' => $lid]);
                $old_attach = $stmtOld->fetchColumn();
                if ($old_attach && file_exists('uploads/' . $old_attach)) {
                    @unlink('uploads/' . $old_attach);
                }
                
                $stmt = $pdo->prepare("UPDATE lesson SET title = :title, content = :content, video_url = :video, attachment = :attachment, order_num = :order WHERE id = :lid");
                $stmt->execute([
                    ':title' => $title,
                    ':content' => $content,
                    ':video' => $video_url,
                    ':attachment' => $attachment,
                    ':order' => $order_num,
                    ':lid' => $lid
                ]);
            } else {
                $stmt = $pdo->prepare("UPDATE lesson SET title = :title, content = :content, video_url = :video, order_num = :order WHERE id = :lid");
                $stmt->execute([
                    ':title' => $title,
                    ':content' => $content,
                    ':video' => $video_url,
                    ':order' => $order_num,
                    ':lid' => $lid
                ]);
            }
            $success_msg = "Lesson updated successfully!";
        } catch (PDOException $e) {
            $error_msg = "Failed to update lesson: " . $e->getMessage();
        }
    }
}

// Delete Lesson
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_lesson'])) {
    $lid = (int)($_POST['lesson_id'] ?? 0);
    if ($lid > 0) {
        try {
            $stmtOld = $pdo->prepare("SELECT attachment FROM lesson WHERE id = :lid");
            $stmtOld->execute([':lid' => $lid]);
            $old_attach = $stmtOld->fetchColumn();
            if ($old_attach && file_exists('uploads/' . $old_attach)) {
                @unlink('uploads/' . $old_attach);
            }
            
            $stmt = $pdo->prepare("DELETE FROM lesson WHERE id = :lid");
            $stmt->execute([':lid' => $lid]);
            $success_msg = "Lesson deleted successfully!";
        } catch (PDOException $e) {
            $error_msg = "Failed to delete lesson: " . $e->getMessage();
        }
    }
}

// Enroll Student in Course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_student'])) {
    $sid = trim($_POST['student_id'] ?? '');
    $cid = (int)($_POST['course_id'] ?? 0);
    
    if (empty($sid) || $cid <= 0) {
        $error_msg = "Please select both a student and a course.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO student_course (student_id, course_id) VALUES (:sid, :cid) ON DUPLICATE KEY UPDATE student_id=student_id");
            $stmt->execute([':sid' => $sid, ':cid' => $cid]);
            $success_msg = "Student enrolled in course successfully!";
        } catch (PDOException $e) {
            $error_msg = "Failed to enroll student: " . $e->getMessage();
        }
    }
}

// Fetch modules, courses, and lessons
$modules_list = $pdo->query("SELECT id, name FROM module ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$courses_list = $pdo->query("
    SELECT c.*, m.name as module_name, t.fullname as teacher_name 
    FROM course c 
    JOIN module m ON c.module_id = m.id 
    LEFT JOIN teacher t ON c.teacher_id = t.teacherid 
    ORDER BY c.title ASC
")->fetchAll(PDO::FETCH_ASSOC);

$lessons_raw = $pdo->query("SELECT * FROM lesson ORDER BY course_id ASC, order_num ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
$lessons_list = [];
foreach ($lessons_raw as $l) {
    $lessons_list[$l['course_id']][] = $l;
}

// Fetch pending teachers
$stmtPendT = $pdo->query("SELECT * FROM teacher WHERE is_approved = 0 ORDER BY created_at DESC");
$pending_teachers = $stmtPendT->fetchAll(PDO::FETCH_ASSOC);

// Fetch pending module requests
$stmtPendM = $pdo->query("
    SELECT tm.teacher_id, tm.module_id, t.fullname, t.email, m.name as module_name 
    FROM teacher_module tm
    JOIN teacher t ON tm.teacher_id = t.teacherid
    JOIN module m ON tm.module_id = m.id
    WHERE tm.status = 'pending'
    ORDER BY t.fullname ASC
");
$pending_modules = $stmtPendM->fetchAll(PDO::FETCH_ASSOC);

// Fetch approved teachers list
$stmtAppT = $pdo->query("SELECT * FROM teacher WHERE is_approved = 1 ORDER BY fullname ASC");
$approved_teachers = $stmtAppT->fetchAll(PDO::FETCH_ASSOC);

// Fetch approved modules for teachers
$stmtAppM = $pdo->query("
    SELECT tm.teacher_id, m.name as module_name 
    FROM teacher_module tm
    JOIN module m ON tm.module_id = m.id
    WHERE tm.status = 'approved'
");
$app_mods_raw = $stmtAppM->fetchAll(PDO::FETCH_ASSOC);
$approved_modules = [];
foreach ($app_mods_raw as $row) {
    $approved_modules[$row['teacher_id']][] = $row['module_name'];
}

// Fetch all registered students for enrollment
$students_list = $pdo->query("SELECT studentid, fullname FROM student ORDER BY fullname ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch enrolled students list per course
$enrolled_students_raw = $pdo->query("
    SELECT sc.course_id, s.fullname, s.studentid 
    FROM student_course sc 
    JOIN student s ON sc.student_id = s.studentid
    ORDER BY sc.course_id, s.fullname ASC
")->fetchAll(PDO::FETCH_ASSOC);
$enrolled_students = [];
foreach ($enrolled_students_raw as $row) {
    $enrolled_students[$row['course_id']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - VitsEDU</title>
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
                    <div class="nav-avatar" style="overflow: hidden; display: flex; align-items: center; justify-content: center; background: var(--secondary);">
                        AD
                    </div>
                    <span style="color: var(--text-main); font-weight: 500; font-family: 'Inter', sans-serif;">Admin Portal</span>
                </div>
            </div>
        </div>
    </header>

    <!-- Dashboard Hero Banner -->
    <div class="dashboard-header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
        <div class="dashboard-container profile-hero">
            <div class="profile-avatar-container">
                <div class="profile-avatar" style="background: var(--secondary);">
                    <span class="avatar-initials">AD</span>
                </div>
            </div>
            <div class="profile-hero-info">
                <h1 style="font-family: 'Outfit', sans-serif; color: white;">Welcome, Administrator!</h1>
                <div style="display: flex; align-items: center; gap: 10px; opacity: 0.9; font-size: 16px; flex-wrap: wrap; color: white;">
                    <span>System Role: Admin</span>
                    <span>&nbsp;•&nbsp;</span>
                    <span>User ID: admin</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <?php
    $active_tab = $_GET['tab'] ?? 'registrations';
    ?>
    <div class="dashboard-tabs">
        <div class="dashboard-tabs-container">
            <a href="admin_dashboard.php?tab=registrations" class="tab-link <?php echo $active_tab === 'registrations' ? 'active' : ''; ?>">Registrations & Modules</a>
            <a href="admin_dashboard.php?tab=lms" class="tab-link <?php echo $active_tab === 'lms' ? 'active' : ''; ?>">Course & Lesson Management</a>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="dashboard-content" style="grid-template-columns: 1fr 1fr; gap: 30px; max-width: 1400px; padding: 30px 24px;">
        
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

        <?php if ($active_tab === 'registrations'): ?>
            <!-- Pending Teacher Applications -->
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h3 style="font-family: 'Outfit', sans-serif;">Pending Teacher Applications (<?php echo count($pending_teachers); ?>)</h3>
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 4px;">Review and approve teachers to grant them dashboard and module request access.</p>
                </div>

                <div style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 16px;">
                    <?php if (empty($pending_teachers)): ?>
                        <p style="color: var(--text-muted); font-style: italic; text-align: center; padding: 20px;">No pending teacher applications.</p>
                    <?php else: ?>
                        <?php foreach ($pending_teachers as $pt): ?>
                            <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 12px;">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div>
                                        <h4 style="margin: 0; font-family: 'Outfit', sans-serif; font-size: 16px; color: var(--text-main);"><?php echo htmlspecialchars($pt['fullname']); ?></h4>
                                        <span style="font-size: 13px; color: var(--text-muted); display: block; margin-top: 2px;">Email: <?php echo htmlspecialchars($pt['email']); ?> | Tel: <?php echo htmlspecialchars($pt['phone']); ?></span>
                                        <span style="font-size: 13px; color: var(--text-muted); display: block; margin-top: 2px;">NIC: <?php echo htmlspecialchars($pt['nic']); ?></span>
                                        <div style="margin-top: 6px; font-size: 13px; color: var(--text-muted); font-family: 'Inter', sans-serif;">
                                            <strong>Address:</strong> <?php echo htmlspecialchars($pt['address']); ?>
                                        </div>
                                        <div style="margin-top: 8px; font-size: 13px; color: var(--text-main); background: rgba(255,255,255,0.01); padding: 8px; border-radius: 6px; border: 1px dashed var(--border-color); font-family: 'Inter', sans-serif; line-height: 1.4;">
                                            <strong>Qualifications:</strong> <?php echo nl2br(htmlspecialchars($pt['qualifications'])); ?>
                                        </div>
                                    </div>
                                    <span style="font-family: 'Inter', sans-serif; font-size: 12px; font-weight: 600; color: var(--primary); background: rgba(6,78,59,0.1); padding: 2px 8px; border-radius: 8px;">ID: <?php echo htmlspecialchars($pt['teacherid']); ?></span>
                                </div>
                                <div style="display: flex; gap: 10px; margin-top: 5px;">
                                    <form action="admin_dashboard.php" method="POST" style="margin: 0; flex-grow: 1;">
                                        <input type="hidden" name="teacher_id" value="<?php echo htmlspecialchars($pt['teacherid']); ?>">
                                        <button type="submit" name="approve_teacher" class="lp-btn lp-btn-primary" style="width: 100%; border: none; font-size: 13px; padding: 8px 12px; height: auto; cursor: pointer;">Approve</button>
                                    </form>
                                    <form action="admin_dashboard.php" method="POST" style="margin: 0; flex-grow: 1;">
                                        <input type="hidden" name="teacher_id" value="<?php echo htmlspecialchars($pt['teacherid']); ?>">
                                        <button type="submit" name="reject_teacher" class="lp-btn lp-btn-outline" style="width: 100%; border-color: #ef4444; color: #ef4444; font-size: 13px; padding: 8px 12px; height: auto; background: transparent; cursor: pointer;">Reject</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pending Module requests -->
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h3 style="font-family: 'Outfit', sans-serif;">Pending Module Teaching Requests (<?php echo count($pending_modules); ?>)</h3>
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 4px;">Approve or reject module assignments requested by approved teachers.</p>
                </div>

                <div style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 16px;">
                    <?php if (empty($pending_modules)): ?>
                        <p style="color: var(--text-muted); font-style: italic; text-align: center; padding: 20px;">No pending module teaching requests.</p>
                    <?php else: ?>
                        <?php foreach ($pending_modules as $pm): ?>
                            <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 12px;">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div>
                                        <h4 style="margin: 0; font-family: 'Outfit', sans-serif; font-size: 16px; color: var(--text-main);"><?php echo htmlspecialchars($pm['fullname']); ?> (<?php echo htmlspecialchars($pm['teacher_id']); ?>)</h4>
                                        <p style="font-size: 14px; margin-top: 6px; font-weight: 600; color: var(--secondary);">Module: <?php echo htmlspecialchars($pm['module_name']); ?></p>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 10px; margin-top: 5px;">
                                    <form action="admin_dashboard.php" method="POST" style="margin: 0; flex-grow: 1;">
                                        <input type="hidden" name="teacher_id" value="<?php echo htmlspecialchars($pm['teacher_id']); ?>">
                                        <input type="hidden" name="module_id" value="<?php echo $pm['module_id']; ?>">
                                        <button type="submit" name="approve_module" class="lp-btn lp-btn-primary" style="width: 100%; border: none; font-size: 13px; padding: 8px 12px; height: auto; cursor: pointer;">Approve</button>
                                    </form>
                                    <form action="admin_dashboard.php" method="POST" style="margin: 0; flex-grow: 1;">
                                        <input type="hidden" name="teacher_id" value="<?php echo htmlspecialchars($pm['teacher_id']); ?>">
                                        <input type="hidden" name="module_id" value="<?php echo $pm['module_id']; ?>">
                                        <button type="submit" name="reject_module" class="lp-btn lp-btn-outline" style="width: 100%; border-color: #ef4444; color: #ef4444; font-size: 13px; padding: 8px 12px; height: auto; background: transparent; cursor: pointer;">Reject</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Approved Teachers & Modules Overview -->
            <div class="dashboard-card" style="grid-column: span 2;">
                <div class="dashboard-card-header">
                    <h3 style="font-family: 'Outfit', sans-serif;">Approved Teachers Overview</h3>
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 4px;">A complete overview of approved teachers and their corresponding teaching modules.</p>
                </div>

                <div style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 16px;">
                    <?php if (empty($approved_teachers)): ?>
                        <p style="color: var(--text-muted); font-style: italic; text-align: center; padding: 20px;">No approved teachers in the system.</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; text-align: left; font-family: 'Inter', sans-serif; font-size: 14px;">
                                <thead>
                                    <tr style="border-bottom: 2px solid var(--border-color); color: var(--text-muted);">
                                        <th style="padding: 12px;">ID</th>
                                        <th style="padding: 12px;">Name & NIC</th>
                                        <th style="padding: 12px;">Contact Details</th>
                                        <th style="padding: 12px;">Qualifications & Address</th>
                                        <th style="padding: 12px;">Teaching Modules</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($approved_teachers as $at): ?>
                                        <tr style="border-bottom: 1px solid var(--border-color);">
                                            <td style="padding: 12px; font-weight: 600;"><?php echo htmlspecialchars($at['teacherid']); ?></td>
                                            <td style="padding: 12px;">
                                                <div style="font-weight: 500; color: var(--text-main);"><?php echo htmlspecialchars($at['fullname']); ?></div>
                                                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">NIC: <?php echo htmlspecialchars($at['nic']); ?></div>
                                            </td>
                                            <td style="padding: 12px; font-size: 13px;">
                                                <div style="color: var(--text-main);"><?php echo htmlspecialchars($at['email']); ?></div>
                                                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Tel: <?php echo htmlspecialchars($at['phone']); ?></div>
                                            </td>
                                            <td style="padding: 12px; font-size: 13px; max-width: 250px;">
                                                <div style="text-overflow: ellipsis; overflow: hidden; white-space: nowrap; color: var(--text-main);" title="Qualifications: <?php echo htmlspecialchars($at['qualifications']); ?>">
                                                    <strong>Qual:</strong> <?php echo htmlspecialchars($at['qualifications']); ?>
                                                </div>
                                                <div style="text-overflow: ellipsis; overflow: hidden; white-space: nowrap; color: var(--text-muted); margin-top: 2px;" title="Address: <?php echo htmlspecialchars($at['address']); ?>">
                                                    <strong>Addr:</strong> <?php echo htmlspecialchars($at['address']); ?>
                                                </div>
                                            </td>
                                            <td style="padding: 12px;">
                                                <?php 
                                                $t_mods = $approved_modules[$at['teacherid']] ?? [];
                                                if (empty($t_mods)) {
                                                    echo '<span style="color: var(--text-muted); font-style: italic; font-size: 12px;">None assigned yet</span>';
                                                } else {
                                                    foreach ($t_mods as $tm) {
                                                        echo '<span style="display: inline-block; background: rgba(59,130,246,0.15); color: var(--secondary); padding: 2px 8px; border-radius: 8px; font-size: 12px; font-weight: 600; margin-right: 5px; margin-bottom: 5px;">' . htmlspecialchars($tm) . '</span>';
                                                    }
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($active_tab === 'lms'): ?>
            <!-- LMS Course & Lesson Panel -->
            <!-- Left Panel: Create Course / Create Lesson Form -->
            <div style="display: flex; flex-direction: column; gap: 30px;">
                <!-- Add Course Card -->
                <div class="dashboard-card" style="width: 100%;">
                    <div class="dashboard-card-header">
                        <h3 style="font-family: 'Outfit', sans-serif;">Create New Course</h3>
                    </div>
                    <form action="admin_dashboard.php?tab=lms" method="POST" class="lms-form">
                        <div class="lms-form-group">
                            <label for="title">Course Title</label>
                            <input type="text" id="title" name="title" required class="lms-input" placeholder="e.g. Intro to CSS Flexbox">
                        </div>
                        <div class="lms-form-group">
                            <label for="module_id">Associated Module</label>
                            <select id="module_id" name="module_id" required class="lms-select">
                                <option value="" disabled selected>Select Module</option>
                                <?php foreach ($modules_list as $mod): ?>
                                    <option value="<?php echo $mod['id']; ?>"><?php echo htmlspecialchars($mod['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="lms-form-group">
                            <label for="description">Course Description</label>
                            <textarea id="description" name="description" rows="3" class="lms-textarea" placeholder="Provide a brief course description..."></textarea>
                        </div>
                        <div class="lms-form-group">
                            <label for="teacher_id">Assign Teacher</label>
                            <select id="teacher_id" name="teacher_id" class="lms-select">
                                <option value="" selected>Select Teacher (Unassigned)</option>
                                <?php foreach ($approved_teachers as $t): ?>
                                    <option value="<?php echo htmlspecialchars($t['teacherid']); ?>"><?php echo htmlspecialchars($t['fullname']); ?> (<?php echo htmlspecialchars($t['teacherid']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="add_course" class="lp-btn lp-btn-primary" style="border: none; cursor: pointer; width: 100%;">Create Course</button>
                    </form>
                </div>

                <!-- Add Lesson Card -->
                <div class="dashboard-card" style="width: 100%;">
                    <div class="dashboard-card-header">
                        <h3 style="font-family: 'Outfit', sans-serif;">Add Lesson to Course</h3>
                    </div>
                    <form action="admin_dashboard.php?tab=lms" method="POST" enctype="multipart/form-data" class="lms-form">
                        <div class="lms-form-group">
                            <label for="course_select">Target Course</label>
                            <select id="course_select" name="course_id" required class="lms-select">
                                <option value="" disabled selected>Select Course</option>
                                <?php foreach ($courses_list as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['title']); ?> (<?php echo htmlspecialchars($c['module_name']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="lms-form-group">
                            <label for="lesson_title">Lesson Title</label>
                            <input type="text" id="lesson_title" name="title" required class="lms-input" placeholder="e.g. Understanding Flex Containers">
                        </div>
                        <div class="lms-form-group">
                            <label for="video_url">Video Embed URL (YouTube/Vimeo)</label>
                            <input type="url" id="video_url" name="video_url" class="lms-input" placeholder="e.g. https://www.youtube.com/embed/jV8B24rSN5o">
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
                </div>

                <!-- Enroll Student Card -->
                <div class="dashboard-card" style="width: 100%;">
                    <div class="dashboard-card-header">
                        <h3 style="font-family: 'Outfit', sans-serif;">Enroll Student in Course</h3>
                    </div>
                    <form action="admin_dashboard.php?tab=lms" method="POST" class="lms-form">
                        <div class="lms-form-group">
                            <label for="enroll_student_select">Target Student</label>
                            <select id="enroll_student_select" name="student_id" required class="lms-select">
                                <option value="" disabled selected>Select Student</option>
                                <?php foreach ($students_list as $st): ?>
                                    <option value="<?php echo htmlspecialchars($st['studentid']); ?>"><?php echo htmlspecialchars($st['fullname']); ?> (<?php echo htmlspecialchars($st['studentid']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="lms-form-group">
                            <label for="enroll_course_select">Target Course</label>
                            <select id="enroll_course_select" name="course_id" required class="lms-select">
                                <option value="" disabled selected>Select Course</option>
                                <?php foreach ($courses_list as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['title']); ?> (<?php echo htmlspecialchars($c['module_name']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="enroll_student" class="lp-btn lp-btn-primary" style="border: none; cursor: pointer; width: 100%;">Enroll Student</button>
                    </form>
                </div>
            </div>

            <!-- Right Panel: Courses & Lessons Overview -->
            <div style="display: flex; flex-direction: column; gap: 30px;">
                <div class="dashboard-card" style="width: 100%;">
                    <div class="dashboard-card-header">
                        <h3 style="font-family: 'Outfit', sans-serif;">Course Catalog & Lessons</h3>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 20px; margin-top: 1.5rem;">
                        <?php if (empty($courses_list)): ?>
                            <p style="color: var(--text-muted); font-style: italic; text-align: center; padding: 20px;">No courses created yet.</p>
                        <?php else: ?>
                            <?php foreach ($courses_list as $c): ?>
                                <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 12px; padding: 18px; display: flex; flex-direction: column; gap: 15px;">
                                    <div style="display: flex; justify-content: space-between; align-items: start; gap: 15px;">
                                        <div>
                                            <span class="lms-badge lms-badge-module" style="margin-bottom: 6px;"><?php echo htmlspecialchars($c['module_name']); ?></span>
                                            <h4 style="margin: 0; font-family: 'Outfit', sans-serif; font-size: 18px; color: var(--text-main);"><?php echo htmlspecialchars($c['title']); ?></h4>
                                            <p style="font-size: 12px; color: var(--primary); font-weight: 600; margin-top: 4px; margin-bottom: 2px;">
                                                Instructor: <?php echo htmlspecialchars($c['teacher_name'] ?? 'Unassigned'); ?>
                                            </p>
                                            <?php if (!empty($c['description'])): ?>
                                                <p style="font-size: 13px; color: var(--text-muted); margin-top: 6px; line-height: 1.4;"><?php echo htmlspecialchars($c['description']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <form action="admin_dashboard.php?tab=lms" method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete this course and all its lessons?');">
                                            <input type="hidden" name="course_id" value="<?php echo $c['id']; ?>">
                                            <button type="submit" name="delete_course" class="lp-btn lp-btn-outline" style="border-color: #ef4444; color: #ef4444; font-size: 12px; padding: 4px 10px; cursor: pointer; background: transparent;">Delete Course</button>
                                        </form>
                                    </div>
                                    
                                    <!-- Course Lessons -->
                                    <div style="border-top: 1px solid var(--border-color); padding-top: 12px;">
                                        <h5 style="margin: 0 0 10px 0; font-family: 'Outfit', sans-serif; font-size: 14px; color: var(--text-main); font-weight: 600;">Lessons in this Course:</h5>
                                        <?php 
                                        $c_lessons = $lessons_list[$c['id']] ?? [];
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
                                                        
                                                        <form action="admin_dashboard.php?tab=lms" method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete this lesson?');">
                                                            <input type="hidden" name="lesson_id" value="<?php echo $l['id']; ?>">
                                                            <button type="submit" name="delete_lesson" style="border: none; background: none; color: #ef4444; font-size: 11px; cursor: pointer; text-decoration: underline; font-family: inherit; font-weight: 500;">Delete</button>
                                                        </form>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Enrolled Students list -->
                                    <div style="border-top: 1px solid var(--border-color); padding-top: 12px; margin-top: 5px;">
                                         <h5 style="margin: 0 0 10px 0; font-family: 'Outfit', sans-serif; font-size: 14px; color: var(--text-main); font-weight: 600;">Enrolled Students (<?php echo count($enrolled_students[$c['id']] ?? []); ?>):</h5>
                                         <?php 
                                         $c_students = $enrolled_students[$c['id']] ?? [];
                                         if (empty($c_students)):
                                         ?>
                                             <p style="color: var(--text-muted); font-size: 13px; font-style: italic; margin: 5px 0;">No students enrolled yet.</p>
                                         <?php else: ?>
                                             <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                                 <?php foreach ($c_students as $st): ?>
                                                     <span style="background: rgba(14, 116, 144, 0.08); border: 1px solid rgba(14, 116, 144, 0.15); color: #0e7490; font-size: 12px; padding: 4px 10px; border-radius: 20px; font-family: 'Inter', sans-serif; font-weight: 500;">
                                                         <?php echo htmlspecialchars($st['fullname']); ?> (<?php echo htmlspecialchars($st['studentid']); ?>)
                                                     </span>
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

        <!-- Global Action Card -->
        <div class="dashboard-card" style="grid-column: span 2; align-self: start; text-align: center;">
            <form method="POST" style="margin: 0; display: inline-block;">
                <button type="submit" name="logout" class="lp-btn lp-btn-outline" style="border-color: #ef4444; color: #ef4444; background: transparent; cursor: pointer; padding: 10px 40px;">Log Out Administrator</button>
            </form>
        </div>

    </div>
</body>
</html>
