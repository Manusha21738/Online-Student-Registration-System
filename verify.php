<?php
/**
 * verify.php
 * 
 * This script handles the email verification process. When a user clicks
 * the verification link sent to their email, this script checks the token.
 * If valid, it prompts the user to set their password to complete registration.
 */
require_once 'db.php';

$message = "";
$status = "";
$email = "";
$token = "";

if (isset($_GET['email']) && isset($_GET['token'])) {
    $email = trim($_GET['email']);
    $token = trim($_GET['token']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && isset($_POST['confirm_password'])) {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (strlen($password) < 8 || !preg_match("/[A-Z]/", $password) || !preg_match("/[a-z]/", $password) || !preg_match("/[0-9]/", $password) || !preg_match("/[^a-zA-Z0-9]/", $password)) {
             $status = "error";
             $message = "Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character.";
        } elseif ($password !== $confirm_password) {
             $status = "error";
             $message = "Passwords do not match. Please try again.";
        } else {
             // Validate token again
             $stmt = $pdo->prepare("SELECT studentid, is_verified FROM student WHERE email = :email AND verification_token = :token");
             $stmt->bindParam(':email', $email);
             $stmt->bindParam(':token', $token);
             $stmt->execute();
             $row = $stmt->fetch(PDO::FETCH_ASSOC);

             if ($row && $row['is_verified'] == 0) {
                  $pdo->beginTransaction();
                  try {
                      // Update student to verified
                      $updateStmt = $pdo->prepare("UPDATE student SET is_verified = 1, verification_token = NULL WHERE email = :email");
                      $updateStmt->bindParam(':email', $email);
                      $updateStmt->execute();

                      // Insert into users table
                      $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                      $userStmt = $pdo->prepare("INSERT INTO users (user_id, password, role) VALUES (:userid, :password, 'student')");
                      $userStmt->bindParam(':userid', $row['studentid']);
                      $userStmt->bindParam(':password', $hashed_password);
                      $userStmt->execute();

                      $pdo->commit();
                      $successMsg = "Your email is verified and password is set! Your Student ID is " . $row['studentid'] . ".";
                      header("Location: login.php?success=" . urlencode($successMsg));
                      exit();
                  } catch (PDOException $e) {
                      $pdo->rollBack();
                      error_log("Database Error during password set: " . $e->getMessage());
                      $status = "error";
                      $message = "An unexpected server error occurred. Please try again.";
                  }
             } else {
                 $status = "error";
                 $message = "Invalid or already used verification link.";
             }
        }
    } else {
        // Initial GET request - verify token and show password form if valid
        $stmt = $pdo->prepare("SELECT is_verified FROM student WHERE email = :email AND verification_token = :token");
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            if ($row['is_verified'] == 0) {
                $status = "needs_password";
            } else {
                $status = "success";
                $message = "Your account is already verified and active.";
            }
        } else {
            $status = "error";
            $message = "Invalid or expired verification link. Please make sure you copied the entire link from your email.";
        }
    }
} else {
    $status = "error";
    $message = "No verification token provided. Please use the link sent to your email.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <!-- Use Google Fonts for modern typography -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        <div class="form-container">
            <div class="glass-panel" style="text-align: center;">
            <div class="header">
                <h2>Verification Status</h2>
            </div>

            <?php if ($status == 'needs_password' || ($status == 'error' && strpos($message, 'Password') !== false)): ?>
                <div style="margin-bottom: 2rem;">
                    <h3 style="color: var(--text-main); margin-bottom: 1rem;">Email Verified!</h3>
                    <p style="color: var(--text-muted); margin-bottom: 2rem;">Please set a password to activate your account and access your portal.</p>
                    
                    <?php if ($status == 'error'): ?>
                        <div class="message error" style="margin-bottom: 1.5rem; padding: 1rem;"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>

                    <form action="verify.php?email=<?php echo urlencode($email); ?>&token=<?php echo urlencode($token); ?>" method="POST" style="text-align: left;">
                        <div class="form-group" style="margin-bottom: 2rem;">
                            <input type="password" id="password" name="password" class="form-control" required placeholder=" " minlength="8" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[^a-zA-Z0-9]).{8,}" title="Must contain at least one number and one uppercase and lowercase letter, one special character, and at least 8 or more characters">
                            <label for="password">Set Password</label>
                            <small style="color: var(--text-muted); font-size: 0.75rem; display: block; text-align: left; margin-top: 8px;">Requires: 8+ chars, 1 uppercase, 1 lowercase, 1 number, 1 symbol.</small>
                        </div>
                        <div class="form-group">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required placeholder=" " minlength="8">
                            <label for="confirm_password">Re-enter Password</label>
                        </div>
                        <button type="submit" class="btn-submit">Set Password & Activate</button>
                    </form>
                </div>
            <?php elseif ($status == 'success'): ?>
                <div class="message success" style="margin-bottom: 2rem;">
                    <!-- Success Checkmark Icon -->
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 15px; color: #10b981;">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <br>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <a href="login.php" class="btn-submit" style="display: inline-block; text-decoration: none;">Go to Login</a>
            <?php else: ?>
                <div class="message error" style="margin-bottom: 2rem; padding: 2rem; font-size: 1.1rem;">
                    <!-- Error Cross Icon -->
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 15px; color: #ef4444;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                    <br>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <a href="signup.php" class="btn-submit" style="display: inline-block; text-decoration: none;">Return to Registration</a>
            <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
