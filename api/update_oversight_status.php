<?php
/**
 * ReportMyCity — Update Oversight Status API
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'senior_officer') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance();

$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'] ?? ''; // 'officer' or 'citizen'
$reportId = $input['reportId'] ?? '';
$action = $input['action'] ?? '';

if (!$reportId || !$type || !$action) {
    echo json_encode(['success' => false, 'error' => 'Missing data']);
    exit;
}

try {
    if ($type === 'officer') {
        $col = $db->getCollection('officer_reports');
        $status = ($action === 'warn') ? 'Officer Warned' : 'Dismissed by Head';
        $col->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($reportId), 'head_officer_id' => $_SESSION['user_id']],
            ['$set' => ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]]
        );

        // Notify Officer if warned
        if ($action === 'warn') {
            $report = $col->findOne(['_id' => new MongoDB\BSON\ObjectId($reportId)]);
            $notifCol = $db->getCollection('notifications');
            $notifCol->insertOne([
                'user_id'    => (string)$report['officer_id'],
                'message'    => "⚠️ Disciplinary Warning: Your Department Head has issued a warning regarding complaint ID: " . ($report['complaint_id'] ?? 'N/A'),
                'created_at' => date('Y-m-d H:i:s'),
                'is_read'    => false,
                'read_by'    => []
            ]);
        }
        echo json_encode(['success' => true, 'message' => "Officer report marked as $status."]);
    } 
    elseif ($type === 'citizen') {
        $col = $db->getCollection('user_reports');
        
        if ($action === 'appeal') {
            $col->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($reportId), 'head_officer_id' => $_SESSION['user_id']],
                ['$set' => [
                    'status' => 'Appealed to State Admin', 
                    'appeal_by_head' => $_SESSION['user_name'],
                    'appeal_at' => date('Y-m-d H:i:s')
                ]]
            );

            // Notify State Admin
            $report = $col->findOne(['_id' => new MongoDB\BSON\ObjectId($reportId)]);
            $notifCol = $db->getCollection('notifications');
            $notifCol->insertOne([
                'message'    => "🚀 New Appeal: Head of " . ($report['department'] ?? 'Dept') . " has appealed a user audit for State Admin review.",
                'role'       => 'state_admin',
                'state'      => $report['state'] ?? '',
                'created_at' => date('Y-m-d H:i:s'),
                'is_read'    => false,
                'read_by'    => []
            ]);

            echo json_encode(['success' => true, 'message' => 'Appeal successfully forwarded to State Admin.']);
        } else {
            $col->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($reportId), 'head_officer_id' => $_SESSION['user_id']],
                ['$set' => ['status' => 'Dismissed by Head', 'updated_at' => date('Y-m-d H:i:s')]]
            );
            echo json_encode(['success' => true, 'message' => 'Audit request dismissed.']);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
