<?php
/**
 * ReportMyCity API — Get Notifications
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance();
    $notifications = $db->getCollection('notifications');

    $role = $_SESSION['role'] ?? '';
    $userId = $_SESSION['user_id'] ?? '';
    $userState = $_SESSION['state'] ?? '';
    $userDept = $_SESSION['department'] ?? '';

    $query = ['$or' => [['user_id' => $userId]]]; // Always check direct notifications
    
    if ($role === 'admin' || $role === 'national_admin') {
        $query['$or'][] = ['role' => 'admin'];
    } elseif ($role === 'state_admin') {
        $query['$or'][] = ['role' => 'state_admin', 'state' => $userState];
    } elseif ($role === 'senior_officer' || $role === 'head_officer') {
        $query['$or'][] = ['role' => 'head_officer', 'department' => $userDept, 'state' => $userState];
    }

    $cursor = $notifications->find($query, ['sort' => ['created_at' => -1], 'limit' => 20]);
    $notifs = [];
    $unreadCount = 0;
    
    foreach ($cursor as $doc) {
        $isRead = false;
        if (isset($doc['read_by']) && is_array($doc['read_by'])) {
            $isRead = in_array($userId, $doc['read_by']);
        } elseif (isset($doc['is_read'])) {
            $isRead = $doc['is_read'];
        }
        
        if (!$isRead) $unreadCount++;
        
        $notifs[] = [
            'id' => (string) $doc['_id'],
            'message' => $doc['message'],
            'created_at' => $doc['created_at'],
            'is_read' => $isRead
        ];
    }

    echo json_encode([
        'success' => true, 
        'notifications' => $notifs,
        'unread_count' => $unreadCount
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
