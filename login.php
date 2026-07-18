<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: profile.php");
    exit();
}

$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userid = trim($_POST['userid'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($userid) || empty($password)) {
        $error = "User ID and Password are required.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :userid");
        $stmt->bindParam(':userid', $userid);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            if ($user['role'] === 'teacher') {
                // Check if teacher is approved
                $stmtTeach = $pdo->prepare("SELECT is_approved FROM teacher WHERE teacherid = :userid");
                $stmtTeach->bindParam(':userid', $user['user_id']);
                $stmtTeach->execute();
                $isApproved = $stmtTeach->fetchColumn();
                if ($isApproved == 0) {
                    $error = "Your account is pending admin approval.";
                }
            }

            if (empty($error)) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role'] = $user['role'];
                if ($user['role'] === 'student') {
                    header("Location: profile.php");
                } elseif ($user['role'] === 'teacher') {
                    header("Location: teacher_dashboard.php");
                } elseif ($user['role'] === 'admin') {
                    header("Location: admin_dashboard.php");
                }
                exit();
            }
        } else {
            $error = "Invalid User ID or Password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VitsEDU - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <script src="theme.js"></script>
</head>
<body>
    <button id="theme-toggle" class="theme-toggle" aria-label="Toggle Dark Mode">
        <svg class="sun-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
        <svg class="moon-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
    </button>

    <div class="full-bg-layout">
        <!-- Full Screen Background Image -->
        <img src="assets/hero_image_1779098484423.png" alt="Students learning background" class="bg-image" style="transform: scale(1.05) scaleX(-1);">
        
        <!-- Gradient Overlay -->
        <div class="bg-overlay"></div>

        <!-- Floating Logo -->
        <a href="index.php" class="floating-logo" style="text-decoration: none;">Vits<span>EDU</span> <span class="floating-logo-tagline">Learn without limits</span></a>

        <!-- Centered Functional Form -->
        <div class="form-container">
            <div class="glass-panel">
                <div class="header">
                    <h2>VitsEDU Login</h2>
                    <p>Access your portal</p>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="message success" style="background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; text-align: center;">
                        <?php echo htmlspecialchars($_GET['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="message error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form action="login.php" method="POST">
                    <div class="form-group">
                        <input type="text" id="userid" name="userid" class="form-control" required placeholder=" ">
                        <label for="userid">User ID (e.g., S00001, T00001, admin)</label>
                    </div>

                    <div class="form-group">
                        <input type="password" id="password" name="password" class="form-control" required placeholder=" ">
                        <label for="password">Password</label>
                    </div>

                    <button type="submit" class="btn-submit">Login</button>
                </form>
                
                <p style="text-align: center; margin-top: 1.5rem; color: var(--text-muted);">
                    Don't have an account? Register as <a href="signup.php" style="color: var(--primary); text-decoration: none; font-weight: 500;">Student</a> or <a href="teacher_signup.php" style="color: var(--primary); text-decoration: none; font-weight: 500;">Teacher</a>
                </p>
            </div>
        </div>
    </div>
    
    <script>
        document.querySelector('form')?.addEventListener('submit', function() {
            const btn = document.querySelector('.btn-submit');
            btn.innerHTML = 'Logging In... <span class="spinner"></span>';
            btn.style.pointerEvents = 'none';
            btn.style.opacity = '0.85';
            btn.style.transform = 'scale(0.98)';
        });
    </script>
</body>
</html>
