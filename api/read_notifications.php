<?php
/**
 * ReportMyCity API — Read/Mark Notifications
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
    exit;
}

try {
    $db = Database::getInstance();
    $notifications = $db->getCollection('notifications');

    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'] ?? '';
    $userState = $_SESSION['state'] ?? '';
    $userDept = $_SESSION['department'] ?? '';

    if (isset($input['notification_id'])) {
        $notifications->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($input['notification_id'])],
            ['$addToSet' => ['read_by' => $userId], '$set' => ['is_read' => true]]
        );
    } elseif (isset($input['mark_all'])) {
        $query = ['$or' => [['user_id' => $userId]]];
        if ($role === 'admin' || $role === 'national_admin') {
            $query['$or'][] = ['role' => 'admin'];
        } elseif ($role === 'state_admin') {
            $query['$or'][] = ['role' => 'state_admin', 'state' => $userState];
        } elseif ($role === 'senior_officer' || $role === 'head_officer') {
            $query['$or'][] = ['role' => 'head_officer', 'department' => $userDept, 'state' => $userState];
        }

        $notifications->updateMany(
            $query, 
            ['$addToSet' => ['read_by' => $userId], '$set' => ['is_read' => true]]
        );
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
