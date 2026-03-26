<?php
/**
 * ReportMyCity — Submit Officer Complaint API
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance();

$userId = $_SESSION['user_id'];
$complaintId = $_POST['complaint_id'] ?? '';
$description = $_POST['report_description'] ?? '';

if (!$complaintId || !$description) {
    echo json_encode(['success' => false, 'message' => 'Missing complaint ID or description']);
    exit;
}

try {
    $complaints = $db->getCollection('complaints');
    $complaint = $complaints->findOne(['_id' => new MongoDB\BSON\ObjectId($complaintId), 'user_id' => $userId]);

    if (!$complaint || !isset($complaint['assigned_officer_id'])) {
        echo json_encode(['success' => false, 'message' => 'Complaint not found or no officer assigned']);
        exit;
    }

    $officers = $db->getCollection('officers');
    $officerDoc = $officers->findOne(['_id' => new MongoDB\BSON\ObjectId($complaint['assigned_officer_id'])]);
    $dept = $officerDoc['department'] ?? '';
    $state = $complaint['state'] ?? '';

    // Find the head officer for this department in this state
    $headOfficers = $db->getCollection('head_officers');
    $headOfficer = $headOfficers->findOne(['department' => $dept, 'state' => $state]);
    $headOfficerId = $headOfficer ? (string)$headOfficer['_id'] : '';

    $officer_reports = $db->getCollection('officer_reports');
    $officer_reports->insertOne([
        'user_id'            => $userId,
        'user_name'          => $_SESSION['user_name'],
        'complaint_id'       => $complaintId,
        'original_title'     => $complaint['title'],
        'officer_id'         => $complaint['assigned_officer_id'],
        'officer_name'       => $officerDoc['name'] ?? 'Unknown',
        'department'         => $dept,
        'head_officer_id'    => $headOfficerId,
        'report_description' => $description,
        'status'             => 'Pending Head Review',
        'district'           => $complaint['district'] ?? '',
        'state'              => $state,
        'created_at'         => date('Y-m-d H:i:s')
    ]);

    // Notify Head Officer and State Admin
    $notifications = $db->getCollection('notifications');
    $notifMsg = "🚨 New Misconduct Report: " . ($_SESSION['user_name'] ?? 'User') . " reported officer " . ($officerDoc['name'] ?? 'Unknown') . " ($dept)";
    
    // For Head Officer
    $notifications->insertOne([
        'message'    => $notifMsg,
        'role'       => 'head_officer',
        'department' => $dept,
        'state'      => $state,
        'created_at' => date('Y-m-d H:i:s'),
        'is_read'    => false,
        'read_by'    => []
    ]);

    // For State Admin
    $notifications->insertOne([
        'message'    => $notifMsg,
        'role'       => 'state_admin',
        'state'      => $state,
        'created_at' => date('Y-m-d H:i:s'),
        'is_read'    => false,
        'read_by'    => []
    ]);

    echo json_encode(['success' => true, 'message' => 'Report submitted successfully']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
