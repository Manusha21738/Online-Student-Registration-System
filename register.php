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
    $programid = trim($_POST['programid'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $yearofregister = trim($_POST['yearofregister'] ?? '');

    // 2. Validate mandatory fields
    if (empty($fullname) || empty($programid) || empty($email) || empty($yearofregister)) {
        // Redirect back to the form with an error message
        header("Location: signup.php?error=All mandatory fields must be filled.");
        exit();
    }


    // Basic email format validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: signup.php?error=Invalid email format.");
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
            header("Location: signup.php?error=Email is already registered. Please use a different email.");
            exit();
        }

        $pdo->beginTransaction();

        // 4. Generate Student ID (e.g., S00001)
        $stmtId = $pdo->query("SELECT MAX(id) FROM student");
        $maxId = $stmtId->fetchColumn() ?: 0;
        $nextId = $maxId + 1;
        $studentid = 'S' . str_pad($nextId, 5, '0', STR_PAD_LEFT);

        // Generate a random verification token
        $verification_token = bin2hex(random_bytes(16));

        // Fetch program name
        $stmtProg = $pdo->prepare("SELECT name FROM programme WHERE id = :id");
        $stmtProg->bindParam(':id', $programid);
        $stmtProg->execute();
        $program_name = $stmtProg->fetchColumn() ?: 'Unknown Program';

        // 5. Verify data insertion (Create unique student record)
        $insertStmt = $pdo->prepare("INSERT INTO student (studentid, fullname, email, yearofregister, programid, verification_token) 
                                     VALUES (:studentid, :fullname, :email, :yearofregister, :programid, :verification_token)");

        $insertStmt->bindParam(':studentid', $studentid);
        $insertStmt->bindParam(':fullname', $fullname);
        $insertStmt->bindParam(':email', $email);
        $insertStmt->bindParam(':yearofregister', $yearofregister);
        $insertStmt->bindParam(':programid', $programid);
        $insertStmt->bindParam(':verification_token', $verification_token);

        if ($insertStmt->execute()) {
            $pdo->commit();

            // 5. Send a verification email
            // Note: Sending email via local XAMPP might not work without proper SMTP configuration in php.ini
            // Create the verification link
            $verifyLink = "http://localhost/Vits-EDU/verify.php?email=" . urlencode($email) . "&token=" . $verification_token;

            $to = $email;
            $subject = "Verify Your Email - Student Registration";

            // HTML Email Content
            $message = "
            <html>
            <head>
                <style>
                    body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f8fafc; padding: 40px 20px; margin: 0; }
                    .container { background-color: #ffffff; padding: 40px; border-radius: 16px; max-width: 600px; margin: 0 auto; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
                    .header { text-align: center; margin-bottom: 30px; }
                    .logo { font-size: 28px; font-weight: 800; color: #0f172a; margin-bottom: 10px; letter-spacing: -0.5px; }
                    .logo span { color: #10b981; }
                    .header h2 { color: #0f172a; margin: 0; font-size: 22px; font-weight: 600; }
                    .details { background-color: #f8fafc; padding: 20px; border-radius: 12px; margin: 25px 0; border: 1px solid #e2e8f0; }
                    .details p { margin: 10px 0; color: #475569; font-size: 15px; }
                    .details strong { color: #0f172a; font-weight: 600; }
                    .btn { display: inline-block; padding: 14px 32px; background-color: #10b981; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: 600; text-align: center; font-size: 16px; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2); }
                    .footer { text-align: center; margin-top: 40px; font-size: 13px; color: #94a3b8; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <div class='logo'>Vits<span>EDU</span></div>
                        <h2>Welcome to VitsEDU!</h2>
                    </div>
                    <p style='color: #334155; font-size: 16px; line-height: 1.6;'>Hello <strong>$fullname</strong>,</p>
                    <p style='color: #334155; font-size: 16px; line-height: 1.6;'>Thank you for starting your educational journey with us! Please verify your email address to activate your account. Here is a copy of your registration details:</p>
                    
                    <div class='details'>
                        <p><strong>User ID:</strong> $studentid</p>
                        <p><strong>User Name:</strong> $fullname</p>
                        <p><strong>User Email:</strong> $email</p>
                        <p><strong>Programme Name:</strong> $program_name</p>
                        <p><strong>Registration Year:</strong> $yearofregister</p>
                    </div>

                    <div style='text-align: center; margin: 35px 0;'>
                        <a href='$verifyLink' class='btn'>Verify Email Address</a>
                    </div>
                    
                    <p style='color: #64748b; font-size: 14px;'>If the button doesn't work, copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; font-size: 14px;'><a href='$verifyLink' style='color: #10b981; text-decoration: underline;'>$verifyLink</a></p>
                    
                    <div class='footer'>
                        &copy; " . date('Y') . " VitsEDU Platform. All rights reserved.
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

            $successMsg = "Registration successful! Your Student ID is: $studentid. Please check your email to verify your account, then log in.";
            if (!$mailSent) {
                // Append note if email could not be sent (common on localhost)
                $successMsg .= " (Note: Verification email could not be sent due to local server configuration).";
            }

            // Clear the form data from session on success
            unset($_SESSION['form_data']);

            header("Location: signup.php?success=" . urlencode($successMsg));
            exit();

        } else {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // If execution failed for another reason
            header("Location: signup.php?error=Registration failed due to a database error.");
            exit();
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Log the error securely and show a generic message to the user
        error_log("Database Error: " . $e->getMessage());
        header("Location: signup.php?error=An unexpected database error occurred.");
        exit();
    }
} else {
    // If not a POST request, redirect to form
    header("Location: signup.php");
    exit();
}
?>