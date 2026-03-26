<?php
/**
 * ReportMyCity API — User Registration
 * Requires: session otp_verified = true (set by verify_otp.php)
 */
session_start();
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../register.php');
    exit;
}

// Block registration if phone OTP was not verified
if (empty($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
    header('Location: ../register.php?error=' . urlencode('Phone verification required. Please complete OTP verification.'));
    exit;
}

$name     = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
$email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$phone    = htmlspecialchars(trim($_POST['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';
$state    = htmlspecialchars(trim($_POST['state'] ?? ''), ENT_QUOTES, 'UTF-8');
$district = htmlspecialchars(trim($_POST['district'] ?? ''), ENT_QUOTES, 'UTF-8');

// Validation
if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($confirm)) {
    header('Location: ../register.php?error=' . urlencode('All fields are required.'));
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../register.php?error=' . urlencode('Invalid email format.'));
    exit;
}

if (strlen($password) < 6) {
    header('Location: ../register.php?error=' . urlencode('Password must be at least 6 characters.'));
    exit;
}

if ($password !== $confirm) {
    header('Location: ../register.php?error=' . urlencode('Passwords do not match.'));
    exit;
}

$db = Database::getInstance();
$users = $db->getCollection('users');

// Check duplicate email
$existing = $users->findOne(['email' => $email]);
if ($existing) {
    header('Location: ../register.php?error=' . urlencode('Email already registered.'));
    exit;
}

// Check duplicate phone
$existingPhone = $users->findOne(['phone' => $phone]);
if ($existingPhone) {
    header('Location: ../register.php?error=' . urlencode('Phone number already registered.'));
    exit;
}

// Insert user
$users->insertOne([
    'name'       => $name,
    'email'      => $email,
    'phone'      => $phone,
    'state'      => $state,
    'district'   => $district,
    'password'   => password_hash($password, PASSWORD_BCRYPT),
    'role'       => 'user',
    'status'     => 'active',
    'created_at' => date('Y-m-d H:i:s')
]);

// Clear OTP session flags after successful registration
unset($_SESSION['otp_verified'], $_SESSION['otp_phone'], $_SESSION['otp_expiry']);

header('Location: ../login.php?registered=1');
exit;
