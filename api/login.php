<?php
/**
 * ReportMyCity API — User Login
 */
session_start();
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

// Support both JSON and standard POST
$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    $email    = filter_var(trim($input['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $input['password'] ?? '';
} else {
    $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
}

// Validation
if (empty($email) || empty($password)) {
    $errorMsg = 'All fields are required.';
    if ($input) {
        echo json_encode(['success' => false, 'message' => $errorMsg]);
    } else {
        header('Location: ../login.php?error=' . urlencode($errorMsg));
    }
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errorMsg = 'Invalid email format.';
    if ($input) {
        echo json_encode(['success' => false, 'message' => $errorMsg]);
    } else {
        header('Location: ../login.php?error=' . urlencode($errorMsg));
    }
    exit;
}

$db = Database::getInstance();
$users = $db->getCollection('users');
$user = $users->findOne(['email' => $email]);

if (!$user) {
    $admins = $db->getCollection('admins');
    $user = $admins->findOne(['email' => $email]);
}

if (!$user) {
    $stateAdmins = $db->getCollection('state_admins');
    $user = $stateAdmins->findOne(['email' => $email]);
}

if (!$user) {
    $headOfficers = $db->getCollection('head_officers');
    $user = $headOfficers->findOne(['email' => $email]);
}

if (!$user) {
    $fieldOfficers = $db->getCollection('field_officers');
    $user = $fieldOfficers->findOne(['email' => $email]);
}

if (!$user || !password_verify($password, $user['password'])) {
    $errorMsg = 'Invalid email or password.';
    if ($input) {
        echo json_encode(['success' => false, 'message' => $errorMsg]);
    } else {
        header('Location: ../login.php?error=' . urlencode($errorMsg));
    }
    exit;
}

// Load AuthMiddleware
require_once __DIR__ . '/../config/jwt.php';

// Set session
$_SESSION['user_id'] = (string) $user['_id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['role'] = $user['role'] ?? 'user';
$_SESSION['district'] = $user['district'] ?? '';
$_SESSION['state'] = $user['state'] ?? '';
$_SESSION['department'] = $user['department'] ?? '';

// Daily Login Points (only for role 'user')
if (($_SESSION['role'] ?? 'user') === 'user') {
    require_once __DIR__ . '/../config/Gamification.php';
    $userId = $_SESSION['user_id'];
    $today = date('Y-m-d');
    $lastLogin = $user['last_login_at'] ?? '';
    
    if (substr($lastLogin, 0, 10) !== $today) {
        Gamification::awardPoints($userId, 'daily_login');
        $db->getCollection('users')->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($userId)],
            ['$set' => ['last_login_at' => date('Y-m-d H:i:s')]]
        );
    }
}

// Set JWT Token
$tokenData = [
    'user_id' => $_SESSION['user_id'],
    'name' => $_SESSION['user_name'],
    'role' => $_SESSION['role']
];
$jwt = AuthMiddleware::generateToken($tokenData);
setcookie('auth_token', $jwt, time() + (86400 * 30), '/', '', false, true); // HttpOnly Cookie

if ($input) {
    echo json_encode(['success' => true, 'role' => $_SESSION['role']]);
} else {
    // Redirect based on role
    $r = $_SESSION['role'];
    if (in_array($r, ['national_admin', 'admin'])) {
        header('Location: ../admin/dashboard.php');
    } elseif ($r === 'state_admin') {
        header('Location: ../state_admin/dashboard.php');
    } elseif (in_array($r, ['senior_officer', 'district_admin'])) {
        header('Location: ../head_officer/dashboard.php');
    } elseif (in_array($r, ['officer', 'local_officer'])) {
        header('Location: ../officer/officer_dashboard.php');
    } else {
        header('Location: ../user/dashboard.php');
    }
}
exit;
