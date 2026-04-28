<?php
/**
 * verify.php
 * 
 * This script handles the email verification process. When a user clicks
 * the verification link sent to their email, this script checks the token
 * against the database and updates their verification status if valid.
 */
require_once 'db.php';

// Initialize variables to store verification status and user messages
$message = "";
$status = "";

// Check if both email and token are provided in the URL (GET request)
if (isset($_GET['email']) && isset($_GET['token'])) {
    $email = trim($_GET['email']);
    $token = trim($_GET['token']);

    try {
        // Find the user with the matching email and verification token
        // We use prepared statements to prevent SQL injection attacks
        $stmt = $pdo->prepare("SELECT is_verified FROM student WHERE email = :email AND verification_token = :token");
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        // Fetch the result as an associative array
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // If a matching record is found
        if ($row) {
            // Check if the user is not already verified (is_verified == 0)
            if ($row['is_verified'] == 0) {
                // Update the user's status to verified (1) and clear the verification token
                $updateStmt = $pdo->prepare("UPDATE student SET is_verified = 1, verification_token = NULL WHERE email = :email");
                $updateStmt->bindParam(':email', $email);
                
                // Execute the update query and check for success
                if ($updateStmt->execute()) {
                    $status = "success";
                    $message = "Your email address has been successfully verified! Your account is now active.";
                } else {
                    $status = "error";
                    $message = "Something went wrong while verifying your account. Please try again later.";
                }
            } else {
                // User is already verified, no need to update again
                $status = "success";
                $message = "Your email address is already verified.";
            }
        } else {
            // No matching record found, which means the link is either invalid or already used/expired
            $status = "error";
            $message = "Invalid or expired verification link. Please make sure you copied the entire link from your email.";
        }
    } catch (PDOException $e) {
        // Log the actual database error to the server's error log for debugging
        error_log("Database Error during verification: " . $e->getMessage());
        // Show a generic, user-friendly error message
        $status = "error";
        $message = "An unexpected server error occurred. Please try again later.";
    }
} else {
    // Missing required parameters in the URL
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
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Decorative background element -->
    <div class="bg-mesh"></div>

    <div class="container">
        <div class="glass-panel" style="text-align: center;">
            <div class="header">
                <img src="vits.png" alt="VITS Logo" class="logo" style="max-width: 100px; margin-bottom: 1rem;">
                <h1>Verification <span>Status</span></h1>
            </div>

            <?php 
            // Display the appropriate message and icon based on the verification status
            if ($status == 'success'): 
            ?>
                <div class="message success" style="margin-bottom: 2rem;">
                    <!-- Success Checkmark Icon -->
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 15px; color: #10b981;">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <br>
                    <?php echo htmlspecialchars($message); ?>
                </div>
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
            <?php endif; ?>

            <!-- Button to return to the main registration page -->
            <a href="index.php" class="btn-submit" style="display: inline-block; text-decoration: none;">Return to Home</a>
        </div>
    </div>
</body>
</html>
