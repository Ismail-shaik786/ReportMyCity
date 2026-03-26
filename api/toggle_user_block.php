<?php
/**
 * ReportMyCity — Toggle User Block Status (Admin)
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance();
$usersCol = $db->getCollection('users');

$input = json_decode(file_get_contents('php://input'), true);
$userId = $input['user_id'] ?? '';

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Missing User ID']);
    exit;
}

try {
    $user = $usersCol->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    $newStatus = !(isset($user['is_blocked']) && $user['is_blocked'] === true);
    
    $usersCol->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($userId)],
        ['$set' => ['is_blocked' => $newStatus]]
    );

    $msg = $newStatus ? 'User has been blocked.' : 'User has been unblocked.';
    echo json_encode(['success' => true, 'message' => $msg, 'is_blocked' => $newStatus]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
