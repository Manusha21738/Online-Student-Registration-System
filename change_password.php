<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userid = $_SESSION['user_id'];
$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $status = 'error';
        $message = 'All fields are required.';
    } elseif (strlen($new_password) < 8 || !preg_match("/[A-Z]/", $new_password) || !preg_match("/[a-z]/", $new_password) || !preg_match("/[0-9]/", $new_password) || !preg_match("/[^a-zA-Z0-9]/", $new_password)) {
        $status = 'error';
        $message = 'New password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character.';
    } elseif ($new_password !== $confirm_password) {
        $status = 'error';
        $message = 'New passwords do not match.';
    } else {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = :userid");
        $stmt->bindParam(':userid', $userid);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($current_password, $user['password'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE users SET password = :password WHERE user_id = :userid");
            $updateStmt->bindParam(':password', $hashed_password);
            $updateStmt->bindParam(':userid', $userid);
            
            if ($updateStmt->execute()) {
                $status = 'success';
                $message = 'Password changed successfully.';
            } else {
                $status = 'error';
                $message = 'Failed to change password. Please try again.';
            }
        } else {
            $status = 'error';
            $message = 'Incorrect current password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="theme.js"></script>
</head>
<body>
    <button id="theme-toggle" class="theme-toggle" aria-label="Toggle Dark Mode">
        <svg class="sun-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
        <svg class="moon-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
    </button>
    <div class="bg-mesh"></div>

    <div class="container">
        <div class="glass-panel">
            <div class="header">
                <h1>Change <span>Password</span></h1>
                <p>Update your account security</p>
            </div>

            <?php if ($status === 'error'): ?>
                <div class="message error"><?php echo htmlspecialchars($message); ?></div>
            <?php elseif ($status === 'success'): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form action="change_password.php" method="POST">
                <div class="form-group">
                    <input type="password" id="current_password" name="current_password" class="form-control" required placeholder=" ">
                    <label for="current_password">Current Password</label>
                </div>

                <div class="form-group" style="margin-bottom: 2.5rem;">
                    <input type="password" id="new_password" name="new_password" class="form-control" required placeholder=" " minlength="8" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[^a-zA-Z0-9]).{8,}" title="Must contain at least one number and one uppercase and lowercase letter, one special character, and at least 8 or more characters">
                    <label for="new_password">New Password</label>
                    <small style="color: var(--text-muted); font-size: 0.75rem; position: absolute; bottom: -20px; left: 0;">Requires: 8+ chars, 1 uppercase, 1 lowercase, 1 number, 1 symbol.</small>
                </div>

                <div class="form-group">
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required placeholder=" " minlength="8">
                    <label for="confirm_password">Confirm New Password</label>
                </div>

                <button type="submit" class="btn-submit">Update Password</button>
            </form>
            
            <a href="profile.php" class="btn-secondary">Back to Profile</a>
        </div>
    </div>
</body>
</html>
