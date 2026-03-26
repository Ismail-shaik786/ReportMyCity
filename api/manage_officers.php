<?php
/**
 * ReportMyCity API — Manage Personnel (4-Tier Architecture)
 */
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$allowedAdminRoles = ['admin', 'national_admin', 'state_admin', 'senior_officer', 'district_admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowedAdminRoles)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$db = Database::getInstance();
$adminsCol       = $db->getCollection('admins');
$stateAdminsCol  = $db->getCollection('state_admins');
$headOfficersCol = $db->getCollection('head_officers'); // Using original names but logically separated now
$fieldOfficersCol = $db->getCollection('field_officers');

$input  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? $_GET['action'] ?? '';
$currentUserRole = $_SESSION['role'];
$currentUserId   = $_SESSION['user_id'];

if ($action === 'add') {
    $name        = htmlspecialchars(trim($input['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $email       = filter_var(trim($input['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password    = $input['password'] ?? '';
    $role        = $input['role'] ?? '';
    $department  = $input['department'] ?? 'General';
    $state       = $input['state'] ?? 'National';
    $district    = $input['district'] ?? 'All Districts';
    $subcategory = $input['subcategory'] ?? 'General';
    $phone       = $input['phone'] ?? '';
    $designation = htmlspecialchars(trim($input['designation'] ?? ''), ENT_QUOTES, 'UTF-8');
    $address     = htmlspecialchars(trim($input['office_address'] ?? ''), ENT_QUOTES, 'UTF-8');

    if (empty($name) || empty($email) || empty($password) || empty($role)) {
        echo json_encode(['success' => false, 'message' => 'Name, email, password, and role are required.']);
        exit;
    }

    // Hierarchical Rules Enforcement
    $targetCol = null;
    if (in_array($currentUserRole, ['admin', 'national_admin'])) {
        if ($role !== 'state_admin') {
            echo json_encode(['success' => false, 'message' => 'Admin can only add State Admins.']);
            exit;
        }
        $targetCol = $stateAdminsCol;
    } elseif ($currentUserRole === 'state_admin') {
        if (!in_array($role, ['senior_officer', 'district_admin'])) {
            echo json_encode(['success' => false, 'message' => 'State Admin can only add Department Heads (Senior Officers).']);
            exit;
        }
        $targetCol = $headOfficersCol;
    } elseif (in_array($currentUserRole, ['senior_officer', 'district_admin'])) {
        if (!in_array($role, ['officer', 'local_officer'])) {
            echo json_encode(['success' => false, 'message' => 'Head Officer can only add Local Officers.']);
            exit;
        }
        $targetCol = $fieldOfficersCol;
    }

    if (!$targetCol) {
        echo json_encode(['success' => false, 'message' => 'Hierarchy rule violation or role not allowed to add personnel.']);
        exit;
    }

    // Duplicate Check
    $query = ['email' => $email];
    if ($adminsCol->findOne($query) || $stateAdminsCol->findOne($query) || $headOfficersCol->findOne($query) || $fieldOfficersCol->findOne($query)) {
        echo json_encode(['success' => false, 'message' => 'Email already registered in the system.']);
        exit;
    }

    $newUser = [
        'name'          => $name,
        'email'         => $email,
        'password'      => password_hash($password, PASSWORD_BCRYPT),
        'role'          => $role,
        'department'    => $department,
        'state'         => $state,
        'district'      => $district,
        'subcategory'   => $subcategory,
        'phone'         => $phone,
        'designation'   => $designation,
        'role' => $role,
        'office_address' => $input['office_address'] ?? '',
        'created_by_id' => $currentUserId,
        'created_at'    => date('Y-m-d H:i:s')
    ];

    $insertedResult = $targetCol->insertOne($newUser);
    $newOfficerId = (string) $insertedResult->getInsertedId();

    // Automatic Department Assignment if requested
    $assignDeptId = $input['assign_dept_id'] ?? '';
    if (!empty($assignDeptId)) {
        try {
            $db->getCollection('departments')->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($assignDeptId)],
                ['$set' => ['head_id' => $newOfficerId]]
            );
        } catch (Exception $e) {
             // Continue if failed, log error
        }
    }

    echo json_encode(['success' => true, 'message' => 'Official added successfully ' . (empty($assignDeptId) ? '' : 'and assigned as Head.'), 'officer_id' => $newOfficerId]);
    exit;
}

if ($action === 'delete') {
    $targetId = $input['officer_id'] ?? $input['user_id'] ?? '';
    if (empty($targetId)) {
        echo json_encode(['success' => false, 'message' => 'ID required for deletion.']);
        exit;
    }

    try {
        $oid = new MongoDB\BSON\ObjectId($targetId);
        // Sequential deletion attempt (it results in 0 if not found in that col)
        $adminsCol->deleteOne(['_id' => $oid]);
        $stateAdminsCol->deleteOne(['_id' => $oid]);
        $headOfficersCol->deleteOne(['_id' => $oid]);
        $fieldOfficersCol->deleteOne(['_id' => $oid]);

        echo json_encode(['success' => true, 'message' => 'Personnel record removed successfully.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID format or system error.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
