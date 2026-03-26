<?php
/**
 * ReportMyCity API — Password Reset Request Handler
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$email = filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$role  = $data['role'] ?? 'user'; // 'user' or 'officer' or 'admin'

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

try {
    $db = Database::getInstance();
    $collectionName = ($role === 'officer') ? 'officers' : (($role === 'admin') ? 'admins' : 'users');
    $collection = $db->getCollection($collectionName);

    $user = $collection->findOne(['email' => $email]);

    if (!$user) {
        // For security, don't reveal if email exists, but the user specifically asked to "check if exist" 
        // In a real gov app we might say "If exists, link sent". 
        // But I will follow the user's logic: "check if officer exist".
        echo json_encode(['success' => false, 'message' => 'No account found with this email.']);
        exit;
    }

    // Generate token
    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Save to DB
    $collection->updateOne(
        ['_id' => $user['_id']],
        ['$set' => [
            'reset_token' => $token,
            'reset_expiry' => $expiry
        ]]
    );

    // SIMULATE EMAIL SENDING
    $resetLink = "http://localhost:8000/pass_reset.php?token=$token&role=$role";
    
    // Log "email" to the filesystem for testing
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) mkdir($logDir, 0777, true);
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] TO: $email | ACTION: Password Reset | LINK: $resetLink\n";
    file_put_contents($logDir . '/emails.txt', $logMessage, FILE_APPEND);

    echo json_encode(['success' => true, 'message' => 'A reset link has been sent to your email. (Check local logs/emails.txt)']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}
