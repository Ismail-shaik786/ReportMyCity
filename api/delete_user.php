<?php
/**
 * ReportMyCity API — Delete User (Admin only)
 */
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$allowedDeleteRoles = ['admin', 'national_admin', 'state_admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowedDeleteRoles)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Deletion requires State or National level access.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$userId = $input['user_id'] ?? '';

if (empty($userId)) {
    echo json_encode(['success' => false, 'message' => 'User ID is required.']);
    exit;
}

try {
    $db = Database::getInstance();
    $usersCol      = $db->getCollection('users');
    $complaintsCol = $db->getCollection('complaints');
    $notifications = $db->getCollection('notifications');

    $objectId = new MongoDB\BSON\ObjectId($userId);

    // Delete the user
    $result = $usersCol->deleteOne(['_id' => $objectId]);

    if ($result->getDeletedCount() > 0) {
        // Delete all their complaints
        $complaintsCol->deleteMany(['user_id' => $userId]);
        // Delete all their notifications
        $notifications->deleteMany(['user_id' => $userId]);

        echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
