<?php
require_once 'db.php';

$message = "";
$status = "";

if (isset($_GET['email']) && isset($_GET['token'])) {
    $email = trim($_GET['email']);
    $token = trim($_GET['token']);

    try {
        // Find the user with matching email and token
        $stmt = $pdo->prepare("SELECT is_verified FROM student WHERE email = :email AND verification_token = :token");
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            if ($row['is_verified'] == 0) {
                // Update the user to verified
                $updateStmt = $pdo->prepare("UPDATE student SET is_verified = 1, verification_token = NULL WHERE email = :email");
                $updateStmt->bindParam(':email', $email);
                
                if ($updateStmt->execute()) {
                    $status = "success";
                    $message = "Your email address has been successfully verified! Your account is now active.";
                } else {
                    $status = "error";
                    $message = "Something went wrong while verifying your account. Please try again later.";
                }
            } else {
                // Already verified
                $status = "success";
                $message = "Your email address is already verified.";
            }
        } else {
            // Invalid link
            $status = "error";
            $message = "Invalid or expired verification link. Please make sure you copied the entire link from your email.";
        }
    } catch (PDOException $e) {
        error_log("Database Error during verification: " . $e->getMessage());
        $status = "error";
        $message = "An unexpected server error occurred. Please try again later.";
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="bg-mesh"></div>

    <div class="container">
        <div class="glass-panel" style="text-align: center;">
            <div class="header">
                <h1>Verification <span>Status</span></h1>
            </div>

            <?php if ($status == 'success'): ?>
                <div class="message success" style="margin-bottom: 2rem;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 15px; color: #10b981;">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <br>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php else: ?>
                <div class="message error" style="margin-bottom: 2rem; padding: 2rem; font-size: 1.1rem;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 15px; color: #ef4444;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                    <br>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <a href="index.php" class="btn-submit" style="display: inline-block; text-decoration: none;">Return to Home</a>
        </div>
    </div>
</body>
</html>
