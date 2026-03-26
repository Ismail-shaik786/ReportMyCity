<?php
/**
 * ReportMyCity — Head Officer: AJAX Add Local Officer
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'senior_officer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance();

// Get input
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$name     = htmlspecialchars(trim($input['name'] ?? ''), ENT_QUOTES, 'UTF-8');
$email    = filter_var(trim($input['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$password = $input['password'] ?? '';
$phone    = htmlspecialchars(trim($input['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
$dept     = $_SESSION['department'] ?? 'General';
$state    = $_SESSION['state'] ?? 'National';
$district = $_SESSION['district'] ?? 'All Districts';
$subcat   = $input['subcategory'] ?? 'General';

if (!$name || !$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'Name, Email, and Password are required.']);
    exit;
}

try {
    $fieldOfficers = $db->getCollection('field_officers');

    // Check for existing email in all relevant collections
    $query = ['email' => $email];
    $exists = $db->getCollection('admins')->findOne($query) || 
              $db->getCollection('state_admins')->findOne($query) || 
              $db->getCollection('head_officers')->findOne($query) || 
              $fieldOfficers->findOne($query);

    if ($exists) {
        echo json_encode(['success' => false, 'message' => 'This email address is already registered.']);
        exit;
    }

    $newOfficer = [
        'name'           => $name,
        'email'          => $email,
        'password'       => password_hash($password, PASSWORD_BCRYPT),
        'phone'          => $phone,
        'role'           => 'local_officer',
        'department'     => $dept,
        'subcategory'    => $subcat,
        'state'          => $state,
        'district'       => $district,
        'office_address' => $input['office_address'] ?? '',
        'designation'    => $input['designation'] ?? 'Field Officer',
        'created_by_id'  => $_SESSION['user_id'],
        'created_at'     => date('Y-m-d H:i:s'),
        'status'         => 'active'
    ];

    $result = $fieldOfficers->insertOne($newOfficer);

    if ($result->getInsertedCount()) {
        echo json_encode(['success' => true, 'message' => 'Local Officer added successfully to your team.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to insert record into database.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}