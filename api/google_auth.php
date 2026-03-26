<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/google.php';

session_start();

if (empty(GOOGLE_CLIENT_ID) || empty(GOOGLE_CLIENT_SECRET)) {
    die("Google OAuth credentials are not configured. Please check config/google.php.");
}

$client = new Google\Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);

foreach (GOOGLE_SCOPES as $scope) {
    $client->addScope($scope);
}

// Generate state to prevent CSRF
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;
$client->setState($state);

$authUrl = $client->createAuthUrl();

header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
exit;
