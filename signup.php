<?php
/**
 * index.php
 * 
 * This is the main entry point for the Online Student Registration System.
 * It displays the registration form and handles session data to pre-fill
 * the form fields if validation fails on submission.
 */
session_start();
require_once 'db.php';

// Retrieve previously submitted form data from session (if any) to prevent data loss on error
$formData = $_SESSION['form_data'] ?? [];

// Sanitize output to prevent Cross-Site Scripting (XSS) attacks
$fullname = htmlspecialchars($formData['fullname'] ?? '');
$programid = htmlspecialchars($formData['programid'] ?? '');
$email = htmlspecialchars($formData['email'] ?? '');
$yearofregister = htmlspecialchars($formData['yearofregister'] ?? date('Y'));

// Fetch programmes for the dropdown
$programmes = [];
try {
    $programmes = $pdo->query("SELECT id, name FROM programme")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Suppress error if table doesn't exist yet during setup
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VitsEDU - Learn without limits</title>
    <!-- Use Google Fonts for modern typography -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Link to external stylesheet for application styling -->
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
        <img src="assets/hero_image_1779098484423.png" alt="Students learning background" class="bg-image">
        
        <!-- Gradient Overlay -->
        <div class="bg-overlay"></div>

        <!-- Floating Logo -->
        <a href="index.php" class="floating-logo" style="text-decoration: none;">Vits<span>EDU</span> <span class="floating-logo-tagline">Learn without limits</span></a>

        <!-- Centered Functional Form -->
        <div class="form-container">
            <div class="glass-panel">
                <div class="header">
                    <h2>Join for Free</h2>
                    <p>Sign up and start learning today</p>
                </div>

                <?php if (isset($_GET['error'])): ?>
                    <div class="message error"><?php echo htmlspecialchars($_GET['error']); ?></div>
                <?php endif; ?>
                <?php if (isset($_GET['success'])): ?>
                    <div class="message success"><?php echo htmlspecialchars($_GET['success']); ?></div>
                <?php else: ?>
                <form action="register.php" method="POST">
                    <div class="form-group">
                        <input type="text" id="fullname" name="fullname" class="form-control" required placeholder=" " value="<?php echo $fullname; ?>">
                        <label for="fullname">Full Name</label>
                    </div>
                    <div class="form-group">
                        <select id="programid" name="programid" class="form-control" required>
                            <option value="" disabled <?php echo empty($programid) ? 'selected' : ''; ?>>Select a Program</option>
                            <?php foreach ($programmes as $prog): ?>
                                <option value="<?php echo $prog['id']; ?>" <?php echo ($programid == $prog['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prog['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="programid">Program</label>
                    </div>
                    <div class="form-group">
                        <input type="email" id="email" name="email" class="form-control" required placeholder=" " value="<?php echo $email; ?>">
                        <label for="email">Email Address</label>
                    </div>
                    <div class="form-group">
                        <input type="number" id="yearofregister" name="yearofregister" class="form-control" required min="2000" max="2100" placeholder=" " value="<?php echo $yearofregister; ?>">
                        <label for="yearofregister">Year of Registration</label>
                    </div>
                    <button type="submit" class="btn-submit">Register Now</button>
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
            btn.innerHTML = 'Processing... <span class="spinner"></span>';
            btn.style.pointerEvents = 'none';
            btn.style.opacity = '0.85';
            btn.style.transform = 'scale(0.98)';
        });
    </script>
</body>
</html>
