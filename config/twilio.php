<?php
/**
 * ReportMyCity — Twilio Configuration
 * =============================================
 * Credentials are loaded from the root .env file.
 * Do NOT hardcode secrets here.
 * =============================================
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load .env variables only if they exist (local development)
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

define('TWILIO_ACCOUNT_SID', $_ENV['TWILIO_ACCOUNT_SID'] ?? '');
define('TWILIO_AUTH_TOKEN',  $_ENV['TWILIO_AUTH_TOKEN']  ?? '');
define('TWILIO_FROM_NUMBER', $_ENV['TWILIO_FROM_NUMBER'] ?? '');

/**
 * OTP settings
 */
define('OTP_EXPIRY_SECONDS', 300); // OTP valid for 5 minutes
define('OTP_LENGTH', 6);
