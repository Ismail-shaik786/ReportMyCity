<?php
/**
 * ReportMyCity API — Get Heatmap Data
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance();
    $complaintsCol = $db->getCollection('complaints');

    // The following lines are typically part of a login or user session initialization process.
    // In the context of this heatmap API, these values are usually already set in the session.
    // If $user is not defined here, these assignments would cause an error.
    // Assuming $user is defined elsewhere or this snippet is intended for a different file.
    $role       = $_SESSION['role'] ?? 'user';
    $district   = $_SESSION['district'] ?? '';
    $state      = $_SESSION['state'] ?? '';
    $department = $_SESSION['department'] ?? '';

    $filter = [];
    
    // Apply regional and departmental filtering for restricted roles
    $isGlobalAdmin = in_array($role, ['national_admin', 'admin']);
    if (!$isGlobalAdmin) {
        if (!empty($district)) {
            $filter['district'] = $district;
        }
        if (!empty($state)) {
            $filter['state'] = $state;
        }
        
        // Departmental restriction for specialized officers
        $dept = $_SESSION['department'] ?? '';
        if (!empty($dept)) {
            $filter['target_department'] = $dept;
        }
    }

    $cursor = $complaintsCol->find($filter);
    $points = [];
    $format = $_GET['format'] ?? 'intensity'; // 'intensity' for heatmap, 'detailed' for markers
    
    foreach ($cursor as $doc) {
        if (!empty($doc['location'])) {
            $loc = trim($doc['location']);
            $parts = explode(',', $loc);
            
            // Checking if the location string is a valid lat,lng format
            if (count($parts) === 2 && is_numeric(trim($parts[0])) && is_numeric(trim($parts[1]))) {
                $lat    = floatval(trim($parts[0]));
                $lng    = floatval(trim($parts[1]));
                $status = $doc['status'] ?? 'Pending';
                
                if ($format === 'detailed') {
                    // [lat, lng, status, title, category]
                    $points[] = [
                        $lat, 
                        $lng, 
                        $status, 
                        $doc['title'] ?? 'Untitled', 
                        $doc['category'] ?? 'General'
                    ];
                } else {
                    // Intensity = 1 for each complaint
                    $points[] = [$lat, $lng, 1];
                }
            }
        }
    }

    echo json_encode(['success' => true, 'data' => $points]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
