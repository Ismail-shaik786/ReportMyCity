<?php
/**
 * ReportMyCity API — Toggle AI Mode
 */
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'get'; // 'get' or 'toggle'

$db = Database::getInstance();
$settings = $db->getCollection('settings');

if ($action === 'toggle') {
    $current = $settings->findOne(['key' => 'ai_mode']);
    $newValue = $current ? !($current['value']) : true;
    
    $settings->updateOne(
        ['key' => 'ai_mode'],
        ['$set' => ['value' => $newValue, 'updated_at' => date('Y-m-d H:i:s')]],
        ['upsert' => true]
    );
    
    echo json_encode(['success' => true, 'ai_mode' => $newValue, 'message' => 'AI Mode ' . ($newValue ? 'Activated' : 'Deactivated')]);
} else {
    $current = $settings->findOne(['key' => 'ai_mode']);
    echo json_encode(['success' => true, 'ai_mode' => $current ? (bool)$current['value'] : false]);
}
