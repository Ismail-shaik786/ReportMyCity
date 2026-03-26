<?php
/**
 * ReportMyCity API — Merge Complaints
 */
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized — admin access required.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid request body.']);
    exit;
}

$primaryIdStr = $input['primary_id'] ?? '';
$secondaryIdsStr = $input['secondary_ids'] ?? [];

if (empty($primaryIdStr) || !is_array($secondaryIdsStr) || count($secondaryIdsStr) < 1) {
    echo json_encode(['success' => false, 'message' => 'Primary ID and at least one secondary ID are required.']);
    exit;
}

try {
    $db = Database::getInstance();
    $complaints = $db->getCollection('complaints');

    $primaryObjId = new MongoDB\BSON\ObjectId($primaryIdStr);
    
    $secondaryObjIds = [];
    foreach ($secondaryIdsStr as $sId) {
        $secondaryObjIds[] = new MongoDB\BSON\ObjectId($sId);
    }

    // Ensure the primary complaint exists
    $primary = $complaints->findOne(['_id' => $primaryObjId]);
    if (!$primary) {
        echo json_encode(['success' => false, 'message' => 'Primary complaint not found.']);
        exit;
    }

    // Find secondary complaints
    $secondaries = $complaints->find(['_id' => ['$in' => $secondaryObjIds]]);
    
    $mergedUserIds = [];
    foreach ($secondaries as $sec) {
        // Collect primary author of the secondary complaint
        if (!empty($sec['user_id'])) {
            $mergedUserIds[] = $sec['user_id'];
        }
        
        // Collect any already merged users from the secondary complaint
        if (!empty($sec['additional_user_ids']) && is_array($sec['additional_user_ids'])) {
            // Unpack array elements into mergedUserIds
            array_push($mergedUserIds, ...$sec['additional_user_ids']);
        }
    }

    if (!empty($mergedUserIds)) {
        // Filter out empty and any user identical to the primary complainter
        $mergedUserIds = array_filter(array_unique($mergedUserIds));
        if (($key = array_search($primary['user_id'], $mergedUserIds)) !== false) {
            unset($mergedUserIds[$key]);
        }

        // Add them to the primary complaint
        $complaints->updateOne(
            ['_id' => $primaryObjId],
            ['$addToSet' => ['additional_user_ids' => ['$each' => array_values($mergedUserIds)]]]
        );
    }

    // Delete the secondary complaints
    $deleteResult = $complaints->deleteMany(['_id' => ['$in' => $secondaryObjIds]]);

    if ($deleteResult->getDeletedCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Complaints merged successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to merge complaints. They may not exist.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
