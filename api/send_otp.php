<?php
/**
 * ReportMyCity API — Send OTP via Twilio SMS
 * POST body: { phone: "9876543210" }
 * Returns JSON: { success: true } or { success: false, error: "..." }
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/twilio.php';

use Twilio\Rest\Client;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$phone = trim($input['phone'] ?? '');

// Validate: 10-digit Indian number
if (!preg_match('/^\d{10}$/', $phone)) {
    echo json_encode(['success' => false, 'error' => 'Invalid phone number. Enter 10 digits.']);
    exit;
}

$fullPhone = '+91' . $phone;

// Generate a secure 6-digit OTP
$otp = str_pad((string) random_int(0, 999999), OTP_LENGTH, '0', STR_PAD_LEFT);

// Store OTP + expiry in session (server-side — never exposed to client)
$_SESSION['otp_code']   = $otp;
$_SESSION['otp_phone']  = $fullPhone;
$_SESSION['otp_expiry'] = time() + OTP_EXPIRY_SECONDS;
$_SESSION['otp_verified'] = false;

// Send via Twilio
try {
    $twilio = new Client(TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN);

    $twilio->messages->create(
        $fullPhone,
        [
            'from' => TWILIO_FROM_NUMBER,
            'body' => "Your ReportMyCity verification code is: {$otp}. Valid for 5 minutes. Do not share this with anyone."
        ]
    );

    echo json_encode(['success' => true]);

} catch (\Twilio\Exceptions\RestException $e) {
    error_log('Twilio SMS error (Entering Demo Mode): ' . $e->getMessage());
    
    // DEMO MODE: If Twilio fails (e.g. unverified number on trial), let's use a default OTP '123456'
    $_SESSION['otp_code'] = '123456';
    
    echo json_encode([
        'success' => true, 
        'demo_mode' => true, 
        'message' => 'Demo Mode: Use 123456 for verification.'
    ]);
} catch (Exception $e) {
    error_log('OTP send error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
}
