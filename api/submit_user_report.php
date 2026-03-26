<?php
/**
 * ReportMyCity — Submit User Report API (by Officer)
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'officer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance();

$officerId = $_SESSION['user_id'];
$complaintId = $_POST['complaint_id'] ?? '';
$reason = $_POST['report_reason'] ?? '';

if (!$complaintId || !$reason) {
    echo json_encode(['success' => false, 'message' => 'Missing complaint ID or reason']);
    exit;
}

try {
    $complaints = $db->getCollection('complaints');
    $complaint = $complaints->findOne(['_id' => new MongoDB\BSON\ObjectId($complaintId), 'assigned_officer_id' => $officerId]);

    if (!$complaint) {
        echo json_encode(['success' => false, 'message' => 'Complaint not found or not assigned to you']);
        exit;
    }

    // Handle Proof Photo Upload
    $proofPath = '';
    if (isset($_FILES['proof_photo']) && $_FILES['proof_photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['proof_photo'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'proof_' . uniqid() . '.' . strtolower($ext);
        $uploadDir = __DIR__ . '/../uploads/audit_proof/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            $proofPath = 'uploads/audit_proof/' . $filename;
        }
    }

    if (!$proofPath) {
        echo json_encode(['success' => false, 'message' => 'Proof photo is required for flagging a complaint.']);
        exit;
    }

    // Find the reporting officer's department
    $officers = $db->getCollection('officers');
    $officerDoc = $officers->findOne(['_id' => new MongoDB\BSON\ObjectId($officerId)]);
    $dept = $officerDoc['department'] ?? 'General';
    $state = $officerDoc['state'] ?? '';

    // Find the head officer for this department in this state
    $headOfficers = $db->getCollection('head_officers');
    $headOfficer = $headOfficers->findOne(['department' => $dept, 'state' => $state]);
    $headOfficerId = $headOfficer ? (string)$headOfficer['_id'] : '';

    $user_reports = $db->getCollection('user_reports');
    $user_reports->insertOne([
        'officer_id'         => $officerId,
        'officer_name'       => $_SESSION['user_name'],
        'department'         => $dept,
        'head_officer_id'    => $headOfficerId,
        'complaint_id'       => $complaintId,
        'complaint_title'    => $complaint['title'],
        'reported_user_id'   => (string)$complaint['user_id'],
        'report_reason'      => $reason,
        'proof_photo'        => $proofPath,
        'original_photo'     => $complaint['image'] ?? '', // Store ref to original
        'status'             => 'Audit Pending Head Review',
        'district'           => $complaint['district'] ?? '',
        'state'              => $state,
        'created_at'         => date('Y-m-d H:i:s')
    ]);

    // Notify Head Officer and State Admin
    $notifications = $db->getCollection('notifications');
    $notifMsg = "⚠️ Fraud Flag: Officer " . ($_SESSION['user_name'] ?? 'Unknown') . " flagged complaint '" . ($complaint['title'] ?? 'N/A') . "' for audit ($dept)";
    
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

    echo json_encode(['success' => true, 'message' => 'Citizen has been flagged for administrative review.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
