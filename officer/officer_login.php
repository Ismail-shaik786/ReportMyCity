<?php
/**
 * ReportMyCity — Officer Login Page
 */
session_start();
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'officer') {
    header('Location: officer_dashboard.php');
    exit;
}

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
        $stateAdmins   = $db->getCollection('state_admins');
        $headOfficers  = $db->getCollection('head_officers');
        $fieldOfficers = $db->getCollection('field_officers');
        
        $officer = $stateAdmins->findOne(['email' => $email]);
        if (!$officer) $officer = $headOfficers->findOne(['email' => $email]);
        if (!$officer) $officer = $fieldOfficers->findOne(['email' => $email]);

        if ($officer && password_verify($password, $officer['password'])) {
            // Load AuthMiddleware
            require_once __DIR__ . '/../config/jwt.php';

            $_SESSION['user_id']    = (string) $officer['_id'];
            $_SESSION['user_name']  = $officer['name'];
            $_SESSION['user_email'] = $officer['email'];
            $_SESSION['role']       = $officer['role'] ?? 'officer';
            $_SESSION['state']      = $officer['state'] ?? '';
            $_SESSION['district']   = $officer['district'] ?? '';
            $_SESSION['department'] = $officer['department'] ?? '';

            // Set JWT Token
            $tokenData = [
                'user_id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'],
                'role' => $_SESSION['role']
            ];
            $jwt = AuthMiddleware::generateToken($tokenData);
            setcookie('auth_token', $jwt, time() + (86400 * 30), '/', '', false, true);

            $r = $_SESSION['role'];
            if ($r === 'state_admin') {
                header('Location: ../state_admin/dashboard.php');
            } elseif ($r === 'senior_officer' || $r === 'district_admin') {
                header('Location: ../head_officer/dashboard.php');
            } else {
                header('Location: officer_dashboard.php');
            }
            exit;
        } else {
            $error = 'Invalid officer credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Login — ReportMyCity India Field Operations</title>
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
            <h1>ReportMyCity India — Field Officer Portal</h1>
            <p>Municipal Field Operations Division · Government of India</p>
        </div>
    </div>

    <!-- Auth Body -->
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="logo">
                <img src="../assets/images/govt_emblem.png" alt="ReportMyCity India Emblem" class="gov-emblem-sm">
                <h1>Officer Sign In</h1>
                <p>Field Operations &amp; Complaint Management</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><i class="la la-times"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['logout'])): ?>
                <div class="alert alert-success">👋 You have been securely logged out.</div>
            <?php endif; ?>

            <div class="auth-section-title">Officer Credentials</div>

            <form method="POST">
                <div class="form-group">
                    <label for="email">Officer Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" placeholder="officer@reportmycity.gov" required>
                </div>
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>
                <div class="form-group" style="text-align: right; margin-top: -0.5rem; margin-bottom: 1rem;">
                    <a href="forgot_password.php" style="font-size: 0.82rem; color: var(--text-muted); text-decoration: underline;">Forgot Password?</a>
                </div>
                <button type="submit" class="btn btn-primary btn-block" style="padding: 0.75rem;">
                    <i class="la la-shield"></i> Sign In to Officer Portal
                </button>
            </form>

            <div class="auth-footer">
                <a href="../login.php">← Citizen Login</a>
                &nbsp;|&nbsp;
                <a href="../admin/admin_login.php">Admin Login →</a>
            </div>
        </div>
    </div>

    <!-- Government Footer -->
    <div class="auth-gov-footer">
        © 2026 ReportMyCity India — Field Officer Portal. Government of India. |
        <a href="#">Help</a> | <a href="#">Terms of Use</a>
    </div>

</div>
</body>
</html>
