<?php
/**
 * ReportMyCity API — Verify OTP
 * POST body: { otp: "123456" }
 * Returns JSON: { success: true } or { success: false, error: "..." }
 */
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$enteredOtp = trim($input['otp'] ?? '');

// Check session has OTP data
if (empty($_SESSION['otp_code']) || empty($_SESSION['otp_expiry'])) {
    echo json_encode(['success' => false, 'error' => 'No OTP found. Please request a new one.']);
    exit;
}

// Check expiry (5 minutes)
if (time() > $_SESSION['otp_expiry']) {
    // Clear expired OTP
    unset($_SESSION['otp_code'], $_SESSION['otp_expiry'], $_SESSION['otp_phone']);
    echo json_encode(['success' => false, 'error' => 'OTP has expired. Please request a new one.']);
    exit;
}

// Constant-time comparison to prevent timing attacks
if (!hash_equals($_SESSION['otp_code'], $enteredOtp)) {
    echo json_encode(['success' => false, 'error' => 'Incorrect OTP. Please try again.']);
    exit;
}

// OTP is correct — mark verified in session
$_SESSION['otp_verified'] = true;
unset($_SESSION['otp_code']); // Remove OTP from session after use

echo json_encode(['success' => true]);
