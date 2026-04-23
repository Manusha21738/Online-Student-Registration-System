<?php
session_start();
// register.php - Handles the registration logic

// Include the database connection file
require_once 'db.php';

// Check if the form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Store submitted data in session so it can be repopulated on error
    $_SESSION['form_data'] = $_POST;

    // 1. Retrieve and sanitize form data
    $fullname = trim($_POST['fullname'] ?? '');
    $studentid = trim($_POST['studentid'] ?? '');
    $programid = trim($_POST['programid'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $yearofregister = trim($_POST['yearofregister'] ?? '');

    // 2. Validate mandatory fields
    if (empty($fullname) || empty($studentid) || empty($programid) || empty($email) || empty($yearofregister)) {
        header("Location: index.php?error=All mandatory fields must be filled.");
        exit();
    }

    // Basic email format validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: index.php?error=Invalid email format.");
        exit();
    }

    try {
        // 3. Check email uniqueness
        // We query the database to see if this email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM student WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        $emailExists = $stmt->fetchColumn();

        if ($emailExists > 0) {
            // Email is already registered
            header("Location: index.php?error=Email is already registered. Please use a different email.");
            exit();
        }

        // Check if student ID already exists (as it is the Primary Key)
        $stmtId = $pdo->prepare("SELECT COUNT(*) FROM student WHERE studentid = :studentid");
        $stmtId->bindParam(':studentid', $studentid);
        $stmtId->execute();
        
        if ($stmtId->fetchColumn() > 0) {
            header("Location: index.php?error=Student ID is already registered.");
            exit();
        }

        // 4. Verify data insertion (Create unique student record)
        $insertStmt = $pdo->prepare("INSERT INTO student (studentid, fullname, email, yearofregister, programid) 
                                     VALUES (:studentid, :fullname, :email, :yearofregister, :programid)");
        
        $insertStmt->bindParam(':studentid', $studentid);
        $insertStmt->bindParam(':fullname', $fullname);
        $insertStmt->bindParam(':email', $email);
        $insertStmt->bindParam(':yearofregister', $yearofregister);
        $insertStmt->bindParam(':programid', $programid);

        if ($insertStmt->execute()) {
            
            // 5. Send a verification email
            // Note: Sending email via local XAMPP might not work without proper SMTP configuration in php.ini
            $to = $email;
            $subject = "Registration Successful - Online Student Registration System";
            $message = "Hello $fullname,\n\nYour registration for the program $programid was successful.\nYour Student ID is: $studentid\n\nWelcome aboard!";
            $headers = "From: noreply@studentportal.local\r\n" .
                       "Reply-To: noreply@studentportal.local\r\n" .
                       "X-Mailer: PHP/" . phpversion();

            // Attempt to send email using PHP mail()
            $mailSent = @mail($to, $subject, $message, $headers);
            
            $successMsg = "Registration successful!";
            if (!$mailSent) {
                // Append note if email could not be sent (common on localhost)
                $successMsg .= " (Note: Verification email could not be sent due to local server configuration).";
            }

            // Clear the form data from session on success
            unset($_SESSION['form_data']);

            header("Location: index.php?success=" . urlencode($successMsg));
            exit();
            
        } else {
            // If execution failed for another reason
            header("Location: index.php?error=Registration failed due to a database error.");
            exit();
        }

    } catch (PDOException $e) {
        // Log the error securely and show a generic message to the user
        error_log("Database Error: " . $e->getMessage());
        header("Location: index.php?error=An unexpected database error occurred.");
        exit();
    }
} else {
    // If not a POST request, redirect to form
    header("Location: index.php");
    exit();
}
?>
