<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/google.php';
require_once __DIR__ . '/../config/database.php';

session_start();

if (!isset($_GET['code'])) {
    header('Location: ../login.php?error=' . urlencode('Google login failed: Authorization code missing.'));
    exit;
}

// Verify state to prevent CSRF
if (empty($_GET['state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    unset($_SESSION['oauth_state']);
    header('Location: ../login.php?error=' . urlencode('Google login failed: Invalid state.'));
    exit;
}
unset($_SESSION['oauth_state']);

try {
    $client = new Google\Client();
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->setRedirectUri(GOOGLE_REDIRECT_URI);

    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    
    if (isset($token['error'])) {
        throw new Exception($token['error_description'] ?? $token['error']);
    }

    $client->setAccessToken($token);

    // Get user profile info
    $oauth2 = new Google\Service\Oauth2($client);
    $googleUser = $oauth2->userinfo->get();

    $email = $googleUser->email;
    $name  = $googleUser->name;
    $gid   = $googleUser->id;

    $db = Database::getInstance();
    $users = $db->getCollection('users');

    // Check if user exists by email or google_id
    $user = $users->findOne([
        '$or' => [
            ['email' => $email],
            ['google_id' => $gid]
        ]
    ]);

    $needsPassword = false;

    if (!$user) {
        // Create new user via Google
        $insertResult = $users->insertOne([
            'name'       => $name,
            'email'      => $email,
            'google_id'  => $gid,
            'role'       => 'user',
            'created_at' => date('Y-m-d H:i:s'),
            'auth_type'  => 'google'
        ]);
        $uid = (string) $insertResult->getInsertedId();
        $userRole = 'user';
        $needsPassword = true; 
    } else {
        $uid = (string) $user['_id'];
        $userRole = $user['role'] ?? 'user';
        
        // Check if they have a password
        if (empty($user['password'])) {
            $needsPassword = true;
        }

        // Ensure google_id is linked
        if (empty($user['google_id'])) {
            $users->updateOne(['_id' => $user['_id']], ['$set' => ['google_id' => $gid, 'auth_type' => 'google']]);
        }
    }

    // Set session
    $_SESSION['user_id'] = $uid;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['role'] = $userRole;
    
    if ($needsPassword) {
        $_SESSION['needs_password_notification'] = true;
    }

    // Redirect to appropriate dashboard
    if ($userRole === 'admin') {
        header('Location: ../admin/admin_dashboard.php');
    } elseif ($userRole === 'officer') {
        header('Location: ../officer/officer_dashboard.php');
    } else {
        header('Location: ../user/dashboard.php');
    }
    exit;

} catch (Exception $e) {
    header('Location: ../login.php?error=' . urlencode('Google login error: ' . $e->getMessage()));
    exit;
}
