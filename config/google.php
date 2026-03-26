<?php
/**
 * ReportMyCity — Google OAuth Configuration
 * =============================================
 * Credentials are loaded from the root .env file.
 * Do NOT hardcode secrets here.
 * =============================================
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load .env variables (safe to call multiple times — returns early if already loaded)
if (!isset($_ENV['GOOGLE_CLIENT_ID'])) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

define('GOOGLE_CLIENT_ID',     $_ENV['GOOGLE_CLIENT_ID']     ?? '');
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? '');

// Detect base URL dynamically for Redirect URI
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
define('GOOGLE_REDIRECT_URI', $protocol . $host . '/api/google_callback.php');

// Scopes required for authentication
define('GOOGLE_SCOPES', [
    'email',
    'profile'
]);
