<?php
/**
 * ReportMyCity API — Update Complaint Status (Admin + Officer)
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/Mailer.php';

header('Content-Type: application/json');

// Auth check
$allowedRoles = ['admin', 'national_admin', 'senior_officer', 'district_admin', 'state_admin', 'officer', 'local_officer'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowedRoles)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    if (!empty($_POST)) {
        $input = $_POST;
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request body.']);
        exit;
    }
}

$complaintId = $input['complaint_id'] ?? '';
$action      = $input['action'] ?? 'update';

if (empty($complaintId)) {
    echo json_encode(['success' => false, 'message' => 'Complaint ID is required.']);
    exit;
}

$db = Database::getInstance();
$complaints = $db->getCollection('complaints');

try {
    $objectId = new MongoDB\BSON\ObjectId($complaintId);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Invalid complaint ID.']);
    exit;
}

// Delete — admin only
if ($action === 'delete') {
    $adminRoles = ['admin', 'national_admin', 'state_admin', 'district_admin'];
    if (!in_array($_SESSION['role'], $adminRoles)) {
        echo json_encode(['success' => false, 'message' => 'Only admins can delete complaints.']);
        exit;
    }

    $toDelete = $complaints->findOne(['_id' => $objectId]);
    $result = $complaints->deleteOne(['_id' => $objectId]);

    if ($result->getDeletedCount() > 0) {
        if ($toDelete) {
            $notifications = $db->getCollection('notifications');
            $delMsg = "Your complaint '" . ($toDelete['title'] ?? 'Unknown') . "' has been deleted by an administrator.";
            if (!empty($toDelete['user_id'])) {
                $notifications->insertOne([
                    'user_id'    => $toDelete['user_id'],
                    'role'       => 'user',
                    'message'    => $delMsg,
                    'is_read'    => false,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            if (!empty($toDelete['additional_user_ids']) && is_array($toDelete['additional_user_ids'])) {
                foreach ($toDelete['additional_user_ids'] as $uid) {
                    if ($uid) {
                        $notifications->insertOne([
                            'user_id'    => $uid,
                            'role'       => 'user',
                            'message'    => $delMsg,
                            'is_read'    => false,
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
            }
        }
        echo json_encode(['success' => true, 'message' => 'Complaint deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Complaint not found.']);
    }
    exit;
}

// Officers can only update their assigned complaints
if (in_array($_SESSION['role'], ['officer', 'local_officer'])) {
    $complaint = $complaints->findOne(['_id' => $objectId]);
    if (!$complaint || ($complaint['assigned_officer_id'] ?? '') !== $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'You can only update complaints assigned to you.']);
        exit;
    }
}

// Build update fields
$validStatuses = ['Pending', 'Submitted', 'Assigned', 'Under Review', 'In Progress', 'Escalated', 'Resolved', 'Officer Completed', 'Closed', 'Reopened'];
$updateFields = [];

if (!empty($input['status'])) {
    $status = htmlspecialchars(trim($input['status']), ENT_QUOTES, 'UTF-8');
    if (!in_array($status, $validStatuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
        exit;
    }
    $updateFields['status'] = $status;
    if ($status === 'In Progress' || $status === 'Officer Completed') $updateFields['in_progress_timestamp'] = date('d M Y, h:i A');
    elseif ($status === 'Resolved') $updateFields['resolved_timestamp'] = date('d M Y, h:i A');
}

if (isset($input['admin_reply'])) $updateFields['admin_reply'] = htmlspecialchars(trim($input['admin_reply']), ENT_QUOTES, 'UTF-8');
if (isset($input['officer_notes'])) $updateFields['officer_notes'] = htmlspecialchars(trim($input['officer_notes']), ENT_QUOTES, 'UTF-8');

$canAssign = ['admin', 'national_admin', 'senior_officer', 'district_admin', 'state_admin'];
if (isset($input['assigned_officer_id']) && in_array($_SESSION['role'], $canAssign)) {
    $officerId = htmlspecialchars(trim($input['assigned_officer_id']), ENT_QUOTES, 'UTF-8');
    $updateFields['assigned_officer_id'] = $officerId;
    
    if (!empty($officerId)) {
        $updateFields['assigned_timestamp'] = date('d M Y, h:i A');
        $headCol = $db->getCollection('head_officers');
        $fieldCol = $db->getCollection('field_officers');
        try {
            $oid = new MongoDB\BSON\ObjectId($officerId);
            $officer = $headCol->findOne(['_id' => $oid]) ?? $fieldCol->findOne(['_id' => $oid]);
            if ($officer) {
                $updateFields['assigned_officer_name'] = $officer['name'];
                
                // SEND EMAIL TO OFFICER
                if (!empty($officer['email'])) {
                    $complaint = $complaints->findOne(['_id' => $objectId]);
                    $cTitle = $complaint['title'] ?? 'Civic Issue';
                    $subj = "New Assignment: " . $cTitle;
                    $body = "<p>You have been assigned to handle a new complaint.</p>
                             <p><strong>Title:</strong> $cTitle</p>
                             <p><strong>Priority:</strong> " . ($complaint['priority'] ?? 'Medium') . "</p>
                             <p>Please log in to your portal to review and take action.</p>";
                    Mailer::send($officer['email'], $officer['name'], $subj, $body);
                }
            }
        } catch (Exception $e) { $updateFields['assigned_officer_name'] = ''; }
    } else {
        $updateFields['assigned_officer_name'] = '';
    }
}

if (isset($_FILES['officer_proof_image']) && $_FILES['officer_proof_image']['name'] !== '') {
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $ext = strtolower(pathinfo($_FILES['officer_proof_image']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
        $newName = uniqid('proof_') . '.' . $ext;
        if (move_uploaded_file($_FILES['officer_proof_image']['tmp_name'], $uploadDir . $newName)) {
            $updateFields['officer_proof_image'] = 'uploads/' . $newName;
        }
    }
}

if (empty($updateFields)) {
    echo json_encode(['success' => false, 'message' => 'No fields to update.']);
    exit;
}

$result = $complaints->updateOne(['_id' => $objectId], ['$set' => $updateFields]);

if ($result->getModifiedCount() > 0 || $result->getMatchedCount() > 0) {
    $notifications = $db->getCollection('notifications');
    $complaint = $complaints->findOne(['_id' => $objectId]);
    if ($complaint) {
        $msg = "Complaint '" . ($complaint['title'] ?? 'Unknown') . "' was updated.";
        if (!empty($complaint['user_id'])) {
            $notifications->insertOne(['user_id' => $complaint['user_id'], 'role' => 'user', 'message' => $msg, 'is_read' => false, 'created_at' => date('Y-m-d H:i:s')]);
        }
        if (!empty($complaint['additional_user_ids']) && is_array($complaint['additional_user_ids'])) {
            foreach ($complaint['additional_user_ids'] as $uid) {
                if ($uid) $notifications->insertOne(['user_id' => $uid, 'role' => 'user', 'message' => $msg, 'is_read' => false, 'created_at' => date('Y-m-d H:i:s')]);
            }
        }
        if (in_array($_SESSION['role'], ['officer', 'local_officer', 'senior_officer'])) {
            $notifications->insertOne(['role' => 'admin', 'message' => "Officer updated complaint: " . ($complaint['title'] ?? 'Unknown'), 'is_read' => false, 'created_at' => date('Y-m-d H:i:s')]);
        }
        if (in_array($_SESSION['role'], $canAssign) && !empty($updateFields['assigned_officer_id'])) {
             $notifications->insertOne(['user_id' => $updateFields['assigned_officer_id'], 'role' => 'officer', 'message' => "You have been assigned to complaint: " . ($complaint['title'] ?? 'Unknown'), 'is_read' => false, 'created_at' => date('Y-m-d H:i:s')]);
        }

        // --- EMAIL NOTIFICATION TO USER ---
        if (!empty($updateFields['status']) || !empty($updateFields['admin_reply'])) {
            $userCol = $db->getCollection('users');
            try {
                $uid = new MongoDB\BSON\ObjectId($complaint['user_id']);
                $user = $userCol->findOne(['_id' => $uid]);
                if ($user && !empty($user['email'])) {
                    $uEmail = $user['email'];
                    $uName = $user['name'];
                    $cTitle = $complaint['title'] ?? 'Civic Issue';
                    $newStatus = $updateFields['status'] ?? ($complaint['status'] ?? 'Updated');
                    
                    $subj = "Update on your Complaint: " . $cTitle;
                    $body = "<p>There has been an update regarding your complaint #<strong>" . substr((string)$objectId, -6) . "</strong>.</p>
                             <div style='background:#f1f5f9; padding:15px; border-radius:8px; margin:15px 0;'>
                                <p><strong>Current Status:</strong> <span style='color:#2563eb;'>$newStatus</span></p>";
                    if (!empty($updateFields['admin_reply'])) {
                        $body .= "<p><strong>Officer/Admin Reply:</strong><br><em>" . nl2br($updateFields['admin_reply']) . "</em></p>";
                    }
                    $body .= "</div><p>You can view more details in your citizen dashboard.</p>";
                    
                    Mailer::send($uEmail, $uName, $subj, $body);
                }
            } catch (Exception $e) {}
        }

        // --- Gamification Logic: Award points on Resolution ---
        if (!empty($updateFields['status']) && $updateFields['status'] === 'Resolved') {
            require_once __DIR__ . '/../config/Gamification.php';
            if (!empty($complaint['user_id'])) Gamification::awardPoints($complaint['user_id'], 'resolved');
            if (!empty($complaint['additional_user_ids']) && is_array($complaint['additional_user_ids'])) {
                foreach ($complaint['additional_user_ids'] as $uid) { if ($uid) Gamification::awardPoints($uid, 'resolved'); }
            }
        }
    }
    echo json_encode(['success' => true, 'message' => 'Complaint updated successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Complaint not found or no changes made.']);
}
exit;
