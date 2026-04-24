<?php
/**
 * register.php
 * 
 * This script handles the server-side logic for the online student registration form.
 * It receives form data, validates the input, checks for duplicate emails and student IDs,
 * securely inserts the new student record into the database, and sends a verification email.
 */
session_start();

// Include the database connection file to use the $pdo object
require_once 'db.php';

// Check if the form was submitted via POST method to ensure data security
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Store submitted data in session so it can be repopulated in the form if an error occurs
    $_SESSION['form_data'] = $_POST;

    // 1. Retrieve and sanitize form data
    // trim() removes whitespace from both ends of a string to prevent accidental spaces
    $fullname = trim($_POST['fullname'] ?? '');
    $studentid = trim($_POST['studentid'] ?? '');
    $programid = trim($_POST['programid'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $yearofregister = trim($_POST['yearofregister'] ?? '');

    // 2. Validate mandatory fields
    if (empty($fullname) || empty($studentid) || empty($programid) || empty($email) || empty($yearofregister)) {
        // Redirect back to the form with an error message
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

        // Generate a random verification token
        $verification_token = bin2hex(random_bytes(16));

        // 4. Verify data insertion (Create unique student record)
        $insertStmt = $pdo->prepare("INSERT INTO student (studentid, fullname, email, yearofregister, programid, verification_token) 
                                     VALUES (:studentid, :fullname, :email, :yearofregister, :programid, :verification_token)");
        
        $insertStmt->bindParam(':studentid', $studentid);
        $insertStmt->bindParam(':fullname', $fullname);
        $insertStmt->bindParam(':email', $email);
        $insertStmt->bindParam(':yearofregister', $yearofregister);
        $insertStmt->bindParam(':programid', $programid);
        $insertStmt->bindParam(':verification_token', $verification_token);

        if ($insertStmt->execute()) {
            
            // 5. Send a verification email
            // Note: Sending email via local XAMPP might not work without proper SMTP configuration in php.ini
            // Create the verification link
            $verifyLink = "http://localhost/Online-Student-Registration-System/verify.php?email=" . urlencode($email) . "&token=" . $verification_token;

            $to = $email;
            $subject = "Verify Your Email - Student Registration";
            
            // HTML Email Content
            $message = "
            <html>
            <head>
                <style>
                    body { font-family: 'Arial', sans-serif; background-color: #f4f4f5; padding: 20px; margin: 0; }
                    .container { background-color: #ffffff; padding: 30px; border-radius: 12px; max-width: 600px; margin: 0 auto; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
                    .header { text-align: center; border-bottom: 2px solid #f0abfc; padding-bottom: 15px; margin-bottom: 20px; }
                    .header h2 { color: #0f172a; margin: 0; }
                    .details { background-color: #f8fafc; padding: 18px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #e2e8f0; }
                    .details p { margin: 8px 0; color: #475569; font-size: 15px; }
                    .details strong { color: #0f172a; }
                    .btn { display: inline-block; padding: 14px 28px; background: linear-gradient(135deg, #0ea5e9, #2563eb); color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: bold; text-align: center; font-size: 16px; }
                    .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #94a3b8; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <img src='http://localhost/Online-Student-Registration-System/vits.png' alt='VITS Logo' style='max-width: 120px; margin-bottom: 10px;'>
                        <h2>Welcome to the Student Portal!</h2>
                    </div>
                    <p style='color: #334155; font-size: 16px;'>Hello <strong>$fullname</strong>,</p>
                    <p style='color: #334155; font-size: 16px; line-height: 1.5;'>Thank you for registering! Please verify your email address to activate your account. Here is a copy of your registration details for your records:</p>
                    
                    <div class='details'>
                        <p><strong>Student ID:</strong> $studentid</p>
                        <p><strong>Program ID:</strong> $programid</p>
                        <p><strong>Registration Year:</strong> $yearofregister</p>
                    </div>

                    <div style='text-align: center; margin: 35px 0;'>
                        <a href='$verifyLink' class='btn'>Verify My Email Address</a>
                    </div>
                    
                    <p style='color: #64748b; font-size: 13px;'>If the button doesn't work, copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; font-size: 13px;'><a href='$verifyLink' style='color: #0ea5e9;'>$verifyLink</a></p>
                    
                    <div class='footer'>
                        &copy; " . date('Y') . " Online Student Registration System. All rights reserved.
                    </div>
                </div>
            </body>
            </html>
            ";

            // Headers for HTML email
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: noreply@studentportal.local\r\n" .
                       "Reply-To: noreply@studentportal.local\r\n" .
                       "X-Mailer: PHP/" . phpversion();

            // Attempt to send email using PHP mail()
            $mailSent = @mail($to, $subject, $message, $headers);
            
            $successMsg = "Registration successful! Please check your email inbox to verify your account.";
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
