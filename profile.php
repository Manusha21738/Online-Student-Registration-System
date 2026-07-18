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
            <a href="profile.php" class="tab-link active">My Profile</a>
            <a href="#" class="tab-link">My Learning</a>
            <a href="#" class="tab-link">Certificates</a>
            <a href="#" class="tab-link">Purchase History</a>
        </div>
    </div>

    <div class="dashboard-content">
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
    </div>
</body>
</html>
