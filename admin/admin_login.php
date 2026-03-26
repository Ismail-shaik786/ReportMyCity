<?php
/**
 * ReportMyCity — Admin Login Page
 */
session_start();
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

// Handle login
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../config/database.php';

    $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        $db = Database::getInstance();
        $admins = $db->getCollection('admins');
        $admin = $admins->findOne(['email' => $email]);

        if ($admin && password_verify($password, $admin['password'])) {
            // Reset AI Mode to OFF on login
            $settings = $db->getCollection('settings');
            $settings->updateOne(
                ['key' => 'ai_mode'],
                ['$set' => ['value' => false, 'updated_at' => date('Y-m-d H:i:s')]],
                ['upsert' => true]
            );

            // Load AuthMiddleware
            require_once __DIR__ . '/../config/jwt.php';

            $_SESSION['user_id']    = (string) $admin['_id'];
            $_SESSION['user_name']  = $admin['name'];
            $_SESSION['user_email'] = $admin['email'];
            $role = $_SESSION['role'] = $admin['role'] ?? 'admin';
            $_SESSION['district']   = $admin['district'] ?? '';
            $_SESSION['state']      = $admin['state'] ?? '';

            // Set JWT Token
            $tokenData = [
                'user_id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'],
                'role' => $_SESSION['role']
            ];
            $jwt = AuthMiddleware::generateToken($tokenData);
            setcookie('auth_token', $jwt, time() + (86400 * 30), '/', '', false, true);

            if ($role === 'national_admin') {
                header('Location: dashboard.php');
            } elseif ($role === 'state_admin') {
                header('Location: ../state_admin/dashboard.php');
            } else {
                header('Location: admin_dashboard.php');
            }
            exit;
        } else {
            $error = 'Invalid admin credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — ReportMyCity India National Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="auth-page-wrapper">

    <!-- Government Header -->
    <div class="auth-gov-header">
        <img src="../assets/images/govt_emblem.png" alt="Government Emblem" class="emblem">
        <div class="portal-text">
            <h1>ReportMyCity India — Administration Portal</h1>
            <p>Restricted Area · Authorised Personnel Only</p>
        </div>
    </div>

    <!-- Auth Body -->
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="logo">
                <img src="../assets/images/govt_emblem.png" alt="ReportMyCity India Emblem" class="gov-emblem-sm">
                <h1>Admin Sign In</h1>
                <p>Administration &amp; System Management Portal</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><i class="la la-times"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['logout'])): ?>
                <div class="alert alert-success">👋 You have been securely logged out.</div>
            <?php endif; ?>

            <div class="auth-section-title">Administrator Credentials</div>

            <form method="POST">
                <div class="form-group">
                    <label for="email">Admin Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" placeholder="admin@reportmycity.gov" required>
                </div>
                <div class="form-group">
                    <label for="password">Secure Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block" style="margin-top:0.5rem; padding: 0.75rem;">
                    <i class="la la-shield"></i> Sign In to Admin Console
                </button>
            </form>

            <div class="alert alert-warning" style="margin-top: 1.1rem; font-size: 0.8rem;">
                ⚠️ This portal is for authorised government administrators only. Unauthorised access is a punishable offence.
            </div>

            <div class="auth-footer">
                <a href="../login.php">← Return to Citizen Login</a>
            </div>
        </div>
    </div>

    <!-- Government Footer -->
    <div class="auth-gov-footer">
        © 2026 ReportMyCity India — Official Administration Portal. Authorised Use Only. |
        <a href="#">Privacy Policy</a> | <a href="#">Terms of Use</a>
    </div>

</div>
</body>
</html>
