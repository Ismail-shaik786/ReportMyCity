<?php
/**
 * ReportMyCity API — Update User/Officer Profile (Optimized for Split Tables)
 */
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    exit;
}

$name  = htmlspecialchars(trim($input['name'] ?? ''), ENT_QUOTES, 'UTF-8');
$email = filter_var(trim($input['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$phone = htmlspecialchars(trim($input['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
$password = $input['password'] ?? ''; 

if (empty($name) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Name and email are required.']);
    exit;
}

$db = Database::getInstance();
$userId = new MongoDB\BSON\ObjectId($_SESSION['user_id']);

// Find which collection the user belongs to
$collections = ['users', 'head_officers', 'field_officers'];
$targetCol = null;
$userDoc = null;

foreach ($collections as $colName) {
    $col = $db->getCollection($colName);
    $doc = $col->findOne(['_id' => $userId]);
    if ($doc) {
        $targetCol = $col;
        $userDoc = $doc;
        break;
    }
}

if (!$targetCol) {
    echo json_encode(['success' => false, 'message' => 'User account not found in any registry.']);
    exit;
}

// Check for duplicate email in all collections
foreach ($collections as $colName) {
    $col = $db->getCollection($colName);
    $existing = $col->findOne(['email' => $email, '_id' => ['$ne' => $userId]]);
    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'Email is already in use by another account.']);
        exit;
    }
}

$updateFields = [
    'name'  => $name,
    'email' => $email,
    'phone' => $phone
];

// Handle Photo Base64
if (!empty($input['photo']) && strpos($input['photo'], 'data:image') === 0) {
    $data = explode(',', $input['photo']);
    if (count($data) > 1) {
        $imgData = base64_decode($data[1]);
        $info = getimagesizefromstring($imgData);
        if ($info) {
            $ext = ($info[2] === IMAGETYPE_PNG) ? 'png' : (($info[2] === IMAGETYPE_GIF) ? 'gif' : 'jpg');
            $filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
            $filepath = __DIR__ . '/../uploads/profiles/' . $filename;
            if (!is_dir(dirname($filepath))) mkdir(dirname($filepath), 0777, true);
            if (file_put_contents($filepath, $imgData)) {
                $updateFields['photo'] = 'uploads/profiles/' . $filename;
                if (!empty($userDoc['photo'])) {
                    $oldPath = __DIR__ . '/../' . $userDoc['photo'];
                    if (file_exists($oldPath)) @unlink($oldPath);
                }
            }
        }
    }
}

if (!empty($password)) {
    $updateFields['password'] = password_hash($password, PASSWORD_BCRYPT);
}

$targetCol->updateOne(['_id' => $userId], ['$set' => $updateFields]);

// Sync sessions
$_SESSION['user_name'] = $name;
$_SESSION['user_email'] = $email;

// Sync assignment names
$complaints = $db->getCollection('complaints');
$complaints->updateMany(
    ['assigned_officer_id' => (string)$userId],
    ['$set' => ['assigned_officer_name' => $name]]
);

echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
