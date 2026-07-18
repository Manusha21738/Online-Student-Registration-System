<?php
/**
 * teacher_signup.php
 * 
 * Handles registration for teachers. Newly registered teachers are set as pending
 * and require Admin approval before they can log in.
 */
session_start();
require_once 'db.php';

// Fetch all modules
$stmtModules = $pdo->query("SELECT * FROM module ORDER BY id ASC");
$modules = $stmtModules->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $qualifications = trim($_POST['qualifications'] ?? '');
    $nic = trim($_POST['nic'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // Validate fields
    if (empty($fullname) || empty($email) || empty($password) || empty($confirm_password) || empty($qualifications) || empty($nic) || empty($address) || empty($phone)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (strlen($password) < 8 || !preg_match("/[A-Z]/", $password) || !preg_match("/[a-z]/", $password) || !preg_match("/[0-9]/", $password) || !preg_match("/[^a-zA-Z0-9]/", $password)) {
        $error = "Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        try {
            // Check email uniqueness across both students and teachers
            $stmtStudent = $pdo->prepare("SELECT COUNT(*) FROM student WHERE email = :email");
            $stmtStudent->execute([':email' => $email]);
            $studentCount = $stmtStudent->fetchColumn();

            $stmtTeacher = $pdo->prepare("SELECT COUNT(*) FROM teacher WHERE email = :email");
            $stmtTeacher->execute([':email' => $email]);
            $teacherCount = $stmtTeacher->fetchColumn();

            if ($studentCount > 0 || $teacherCount > 0) {
                $error = "This email is already registered.";
            } else {
                $pdo->beginTransaction();

                // Generate Teacher ID (e.g., T00001)
                $stmtId = $pdo->query("SELECT MAX(id) FROM teacher");
                $maxId = $stmtId->fetchColumn() ?: 0;
                $nextId = $maxId + 1;
                $teacherid = 'T' . str_pad($nextId, 5, '0', STR_PAD_LEFT);

                // Insert into teacher table (pending approval)
                $insertTeacher = $pdo->prepare("INSERT INTO teacher (teacherid, fullname, email, qualifications, nic, address, phone, is_approved) VALUES (:teacherid, :fullname, :email, :qualifications, :nic, :address, :phone, 0)");
                $insertTeacher->execute([
                    ':teacherid' => $teacherid,
                    ':fullname' => $fullname,
                    ':email' => $email,
                    ':qualifications' => $qualifications,
                    ':nic' => $nic,
                    ':address' => $address,
                    ':phone' => $phone
                ]);

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $insertUser = $pdo->prepare("INSERT INTO users (user_id, password, role) VALUES (:userid, :password, 'teacher')");
                $insertUser->execute([
                    ':userid' => $teacherid,
                    ':password' => $hashed_password
                ]);

                // Insert selected teaching modules
                $selected_modules = $_POST['modules'] ?? [];
                if (!empty($selected_modules)) {
                    $insertMod = $pdo->prepare("INSERT INTO teacher_module (teacher_id, module_id, status) VALUES (:tid, :mid, 'pending')");
                    foreach ($selected_modules as $mid) {
                        $insertMod->execute([
                            ':tid' => $teacherid,
                            ':mid' => (int)$mid
                        ]);
                    }
                }

                $pdo->commit();
                $success = "Registration successful! Your application and teaching module requests are pending Admin approval. Your Teacher ID is <strong>" . htmlspecialchars($teacherid) . "</strong>. Please note this down for logging in.";
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "An unexpected error occurred: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VitsEDU - Teacher Registration</title>
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
        <div class="form-container" style="max-width: 500px;">
            <div class="glass-panel">
                <div class="header">
                    <h2>Teacher Registration</h2>
                    <p>Register to teach on VitsEDU</p>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="message success" style="background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); padding: 16px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.95rem; line-height: 1.5;">
                        <?php echo $success; ?>
                    </div>
                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="login.php" class="btn-submit" style="display: block; text-decoration: none; text-align: center; line-height: 44px; height: 44px;">Go to Login</a>
                    </div>
                <?php else: ?>
                    <?php if (!empty($error)): ?>
                        <div class="message error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form action="teacher_signup.php" method="POST">
                        <div class="form-group">
                            <input type="text" id="fullname" name="fullname" class="form-control" required placeholder=" " value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>">
                            <label for="fullname">Full Name</label>
                        </div>

                        <div class="form-group">
                            <input type="email" id="email" name="email" class="form-control" required placeholder=" " value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            <label for="email">Email Address</label>
                        </div>

                        <div class="form-group">
                            <input type="text" id="nic" name="nic" class="form-control" required placeholder=" " value="<?php echo htmlspecialchars($_POST['nic'] ?? ''); ?>">
                            <label for="nic">NIC Number</label>
                        </div>

                        <div class="form-group">
                            <input type="tel" id="phone" name="phone" class="form-control" required placeholder=" " value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                            <label for="phone">Telephone Number</label>
                        </div>

                        <div class="form-group" style="margin-bottom: 2.2rem;">
                            <input type="password" id="password" name="password" class="form-control" required placeholder=" " minlength="8" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[^a-zA-Z0-9]).{8,}" title="Must contain at least one number and one uppercase and lowercase letter, one special character, and at least 8 or more characters">
                            <label for="password">Password</label>
                            <small style="color: var(--text-muted); font-size: 0.75rem; position: absolute; bottom: -20px; left: 0;">Requires: 8+ chars, 1 uppercase, 1 lowercase, 1 number, 1 symbol.</small>
                        </div>

                        <div class="form-group">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required placeholder=" ">
                            <label for="confirm_password">Confirm Password</label>
                        </div>

                        <div class="form-group" style="margin-bottom: 2rem;">
                            <textarea id="qualifications" name="qualifications" class="form-control" required style="height: 60px; padding: 5px 0; resize: none;" placeholder=" "><?php echo htmlspecialchars($_POST['qualifications'] ?? ''); ?></textarea>
                            <label for="qualifications">Qualifications (degrees, experience, certifications)</label>
                        </div>

                        <div class="form-group" style="margin-bottom: 2rem;">
                            <textarea id="address" name="address" class="form-control" required style="height: 60px; padding: 5px 0; resize: none;" placeholder=" "><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                            <label for="address">Home Address</label>
                        </div>

                        <div style="margin-bottom: 2rem;">
                            <label style="color: var(--text-main); font-weight: 600; display: block; margin-bottom: 0.8rem; font-family: 'Inter', sans-serif;">Select Modules You Can Teach</label>
                            <div style="display: flex; flex-direction: column; gap: 10px; padding: 12px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px;">
                                <?php foreach ($modules as $mod): ?>
                                    <label style="display: flex; align-items: center; gap: 10px; font-weight: 500; cursor: pointer;">
                                        <input type="checkbox" name="modules[]" value="<?php echo $mod['id']; ?>" style="width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer;">
                                        <span style="font-family: 'Inter', sans-serif; font-size: 14px; color: var(--text-main);"><?php echo htmlspecialchars($mod['name']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <button type="submit" class="btn-submit">Register Application</button>
                    </form>
                    
                    <p style="text-align: center; margin-top: 1.5rem; color: var(--text-muted);">
                        Already have an account? <a href="login.php" style="color: var(--primary); text-decoration: none; font-weight: 500;">Log in here</a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        document.querySelector('form')?.addEventListener('submit', function() {
            const btn = document.querySelector('.btn-submit');
            btn.innerHTML = 'Submitting Application... <span class="spinner"></span>';
            btn.style.pointerEvents = 'none';
            btn.style.opacity = '0.85';
            btn.style.transform = 'scale(0.98)';
        });
    </script>
</body>
</html>
