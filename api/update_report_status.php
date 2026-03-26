<?php
/**
 * ReportMyCity — Update Report Status API (Admin Only)
 */
session_start();
header('Content-Type: application/json');

$allowedAuditRoles = ['admin', 'national_admin', 'state_admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowedAuditRoles)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Audit actions require State or National level access.']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance();

$reportId = $_POST['report_id'] ?? '';
$type     = $_POST['type'] ?? ''; // 'user' or 'officer'
$status   = $_POST['status'] ?? ''; // e.g. 'Reviewed', 'Dismissed', 'Action Taken'

if (!$reportId || !$type || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    $collection = ($type === 'user') ? $db->getCollection('user_reports') : $db->getCollection('officer_reports');
    
    $report = $collection->findOne(['_id' => new MongoDB\BSON\ObjectId($reportId)]);
    
    $result = $collection->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($reportId)],
        ['$set' => ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]]
    );

    if ($result->getModifiedCount() > 0 || $result->getMatchedCount() > 0) {
        // --- Gamification Logic: Penalize if confirmed fake ---
        if ($type === 'user' && $status === 'Block User') {
            require_once __DIR__ . '/../config/Gamification.php';
            if ($report && !empty($report['reported_user_id'])) {
                Gamification::penalize($report['reported_user_id'], 'fake_complaint');
                
                // Also block the user account
                $usersCol = $db->getCollection('users');
                $usersCol->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($report['reported_user_id'])],
                    ['$set' => ['status' => 'blocked', 'blocked_at' => date('Y-m-d H:i:s')]]
                );
            }
        }
        
        // Notify Head Officer that action was taken from their appeal/report
        if ($report && !empty($report['head_officer_id'])) {
            $notifCol = $db->getCollection('notifications');
            $notifCol->insertOne([
                'user_id'    => (string)$report['head_officer_id'],
                'message'    => "✅ Oversight Action: State Admin has finalized review for your report/appeal. Status: " . $status,
                'created_at' => date('Y-m-d H:i:s'),
                'is_read'    => false,
                'read_by'    => []
            ]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Report status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Report not found or no change made']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
