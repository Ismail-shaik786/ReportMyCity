<?php
/**
 * ReportMyCity API — AI Automation Engine
 * This script performs one cycle of automated tasks.
 */
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$db = Database::getInstance();
$settings = $db->getCollection('settings');
$aiMode = $settings->findOne(['key' => 'ai_mode']);

if (!$aiMode || !$aiMode['value']) {
    echo json_encode(['success' => false, 'message' => 'AI Mode is disabled.']);
    exit;
}

$complaintsCol = $db->getCollection('complaints');
$officersCol   = $db->getCollection('officers');
$notifications = $db->getCollection('notifications');

$logs = [];

// 0. AI DUPLICATE DETECTION & MERGING
$allPending = $complaintsCol->find(['status' => 'Pending']);
$groups = [];
foreach ($allPending as $cp) {
    if (empty($cp['location']) || empty($cp['category'])) continue;
    $key = strtolower(trim($cp['location'])) . '|' . strtolower(trim($cp['category']));
    $groups[$key][] = $cp;
}

foreach ($groups as $key => $items) {
    if (count($items) > 1) {
        // Sort by created_at to keep the earliest one
        usort($items, fn($a, $b) => strcmp($a['created_at'], $b['created_at']));
        
        $primary = array_shift($items);
        $primaryId = $primary['_id'];
        $secondaryIds = array_map(fn($it) => $it['_id'], $items);
        
        $mergedUserIds = [];
        foreach ($items as $sec) {
            if (!empty($sec['user_id'])) $mergedUserIds[] = $sec['user_id'];
            if (!empty($sec['additional_user_ids'])) {
                array_push($mergedUserIds, ...$sec['additional_user_ids']);
            }
        }
        
        if (!empty($mergedUserIds)) {
            $mergedUserIds = array_filter(array_unique($mergedUserIds));
            if (($uIdx = array_search($primary['user_id'], $mergedUserIds)) !== false) {
                unset($mergedUserIds[$uIdx]);
            }
            
            $complaintsCol->updateOne(
                ['_id' => $primaryId],
                ['$addToSet' => ['additional_user_ids' => ['$each' => array_values($mergedUserIds)]]]
            );
        }
        
        // Delete duplicates
        $complaintsCol->deleteMany(['_id' => ['$in' => $secondaryIds]]);
        
        $msg = "Merged " . count($secondaryIds) . " duplicate(s) into '" . $primary['title'] . "'";
        $logs[] = $msg;
        
        // Notify admin of the merge
        $notifications->insertOne([
            'role' => 'admin',
            'message' => "AI BOT: " . $msg . " at " . $primary['location'],
            'is_read' => false,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}

// 1. Find "Pending" complaints with no officer
$unassigned = $complaintsCol->find([
    'status' => 'Pending',
    'assigned_officer_id' => ['$in' => ['', null]]
]);

foreach ($unassigned as $c) {
    // Find Busy Officer IDs
    $busyIdsRaw = $complaintsCol->distinct('assigned_officer_id', [
        'status' => ['$in' => ['Pending', 'In Progress']],
        'assigned_officer_id' => ['$ne' => '']
    ]);
    $busyOfficerIds = array_map(fn($id) => (string)$id, (array)$busyIdsRaw);

    // Find a free officer
    $freeOfficer = $officersCol->findOne([
        '_id' => ['$nin' => array_map(fn($id) => new MongoDB\BSON\ObjectId($id), $busyOfficerIds)]
    ]);

    if ($freeOfficer) {
        $oid = (string)$freeOfficer['_id'];
        $complaintsCol->updateOne(
            ['_id' => $c['_id']],
            ['$set' => [
                'assigned_officer_id' => $oid,
                'assigned_officer_name' => $freeOfficer['name'],
                'status' => 'In Progress', // Bot moves it to In Progress immediately
                'admin_reply' => 'AI BOT: Automatically assigned to ' . $freeOfficer['name'] . '.'
            ]]
        );

        // Notify
        $notifications->insertOne([
            'user_id' => $oid,
            'role' => 'officer',
            'message' => "AI BOT assigned you to: " . $c['title'],
            'is_read' => false,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $notifications->insertOne([
            'user_id' => $c['user_id'],
            'role' => 'user',
            'message' => "AI BOT assigned your complaint '" . $c['title'] . "' to Officer " . $freeOfficer['name'],
            'is_read' => false,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $logs[] = "Assigned '" . $c['title'] . "' to " . $freeOfficer['name'];
    }
}

// 2. Find "Officer Completed" complaints and Resolve them
$completed = $complaintsCol->find(['status' => 'Officer Completed']);
foreach ($completed as $c) {
    if (empty($c['_id'])) continue;
    
    $complaintsCol->updateOne(
        ['_id' => $c['_id']],
        ['$set' => [
            'status' => 'Resolved',
            'admin_reply' => 'AI BOT: Automatically verified and resolved officer completion.'
        ]]
    );

    // Notify user
    $notifications->insertOne([
        'user_id' => $c['user_id'],
        'role' => 'user',
        'message' => "AI BOT resolved your complaint: " . $c['title'],
        'is_read' => false,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    $logs[] = "Resolved '" . $c['title'] . "' (completed by officer)";
}

echo json_encode([
    'success' => true,
    'actions_taken' => count($logs),
    'logs' => $logs
]);
