<?php
/**
 * ReportMyCity API — Get Complaints
 * 
 * Query params:
 *   user_id  — filter by user (for user's own complaints)
 *   status   — filter by status
 *   search   — search title/category/location
 *   all      — return all complaints (admin)
 */
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$complaints = $db->getCollection('complaints');

$filter = [];

// If not admin requesting all, limit to user's own
if (isset($_GET['user_id'])) {
    $filter['user_id'] = $_GET['user_id'];
} elseif (!isset($_GET['all']) || $_SESSION['role'] !== 'admin') {
    $filter['user_id'] = $_SESSION['user_id'];
}

// Status filter
if (!empty($_GET['status'])) {
    $filter['status'] = htmlspecialchars(trim($_GET['status']), ENT_QUOTES, 'UTF-8');
}

// Search
if (!empty($_GET['search'])) {
    $search = htmlspecialchars(trim($_GET['search']), ENT_QUOTES, 'UTF-8');
    $filter['$or'] = [
        ['title'    => new MongoDB\BSON\Regex($search, 'i')],
        ['category' => new MongoDB\BSON\Regex($search, 'i')],
        ['location' => new MongoDB\BSON\Regex($search, 'i')]
    ];
}

$cursor = $complaints->find($filter, ['sort' => ['created_at' => -1]]);

$results = [];
foreach ($cursor as $doc) {
    $results[] = [
        '_id'                 => (string) $doc['_id'],
        'user_id'             => $doc['user_id'] ?? '',
        'title'               => $doc['title'] ?? '',
        'category'            => $doc['category'] ?? '',
        'description'         => $doc['description'] ?? '',
        'location'            => $doc['location'] ?? '',
        'image'               => $doc['image'] ?? '',
        'officer_proof_image' => $doc['officer_proof_image'] ?? '',
        'date'                => $doc['date'] ?? '',
        'status'              => $doc['status'] ?? 'Pending',
        'admin_reply'         => $doc['admin_reply'] ?? '',
        'created_at'          => $doc['created_at'] ?? ''
    ];
}

echo json_encode(['success' => true, 'data' => $results]);
exit;
