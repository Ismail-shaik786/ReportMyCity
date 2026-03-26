<?php
/**
 * ReportMyCity — Secure Password Reset Page
 */
require_once __DIR__ . '/config/database.php';

$token = $_GET['token'] ?? '';
$role  = $_GET['role'] ?? 'user';
$error = '';
$success = false;

if (empty($token)) {
    $error = 'Invalid or missing reset token.';
} else {
    try {
        $db = Database::getInstance();
        $collectionName = ($role === 'officer') ? 'officers' : (($role === 'admin') ? 'admins' : 'users');
        $collection = $db->getCollection($collectionName);

        $user = $collection->findOne([
            'reset_token' => $token,
            'reset_expiry' => ['$gt' => date('Y-m-d H:i:s')]
        ]);

        if (!$user) {
            $error = 'This reset link is invalid or has expired.';
        }
    } catch (Exception $e) {
        $error = 'Database connection error.';
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $collection->updateOne(
                ['_id' => $user['_id']],
                ['$set' => ['password' => $hashedPassword], '$unset' => ['reset_token' => '', 'reset_expiry' => '']]
            );
            $success = true;
        } catch (Exception $e) {
            $error = 'Failed to update password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password — ReportMyCity</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .reset-box { max-width: 400px; margin: 100px auto; padding: 2rem; background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-top: 5px solid var(--gov-navy); }
        .reset-box h1 { font-size: 1.5rem; color: var(--gov-navy); margin-bottom: 0.5rem; text-align: center; }
        .reset-box p { font-size: 0.9rem; color: var(--text-muted); text-align: center; margin-bottom: 1.5rem; }
    </style>
</head>
<body style="background: #f8fafc;">
    <div class="reset-box">
        <h1><i class="la la-lock"></i> Reset Password</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success" style="text-align: center;">
                <i class="la la-check-square-o"></i> Password updated successfully!
                <br><br>
                <a href="<?php echo ($role === 'officer') ? 'officer/officer_login.php' : (($role === 'admin') ? 'admin/admin_login.php' : 'login.php'); ?>" class="btn btn-primary btn-sm">Go to Login</a>
            </div>
        <?php elseif ($error): ?>
            <div class="alert alert-error"><i class="la la-times"></i> <?php echo htmlspecialchars($error); ?></div>
            <a href="index.php" class="btn btn-outline btn-block">Back to Home</a>
        <?php else: ?>
            <p>For account: 📧 <?php echo htmlspecialchars($user['email']); ?></p>
            <form method="POST">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Update Password</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
