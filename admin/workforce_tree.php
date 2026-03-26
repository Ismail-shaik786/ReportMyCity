<?php
/**
 * ReportMyCity India — National Admin: Comprehensive Workforce Tree
 */
session_start();
$allowedRoles = ['national_admin', 'admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowedRoles)) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$adminsCol      = $db->getCollection('admins');
$stateAdminsCol = $db->getCollection('state_admins');
$headOfficersCol = $db->getCollection('head_officers');
$fieldOfficersCol = $db->getCollection('field_officers');

// Build the full 4-tier tree
$tree = [];

// 1. National Admins (Top)
$nationalAdmins = iterator_to_array($adminsCol->find([]));
foreach ($nationalAdmins as $na) {
    $naId = (string)$na['_id'];
    $node = [
        'name' => $na['name'],
        'role' => 'National Admin',
        'email' => $na['email'],
        'location' => 'National',
        'children' => []
    ];

    // 2. State Admins created by this Admin (or just all for National)
    $stAdmins = iterator_to_array($stateAdminsCol->find([]));
    foreach ($stAdmins as $sa) {
        $saId = (string)$sa['_id'];
        $saNode = [
            'name' => $sa['name'],
            'role' => 'State Admin',
            'email' => $sa['email'],
            'location' => $sa['state'],
            'children' => []
        ];

        // 3. Head Officers in this State
        $hOfficers = iterator_to_array($headOfficersCol->find(['state' => $sa['state']]));
        foreach ($hOfficers as $ho) {
            $hoId = (string)$ho['_id'];
            $hoNode = [
                'name' => $ho['name'],
                'role' => 'Dept Head',
                'email' => $ho['email'],
                'location' => $ho['department'],
                'children' => []
            ];

            // 4. Field Officers under this Head
            $fOfficers = iterator_to_array($fieldOfficersCol->find(['created_by_id' => $hoId]));
            foreach ($fOfficers as $fo) {
                $hoNode['children'][] = [
                    'name' => $fo['name'],
                    'role' => 'Field Officer',
                    'email' => $fo['email'],
                    'location' => $fo['subcategory'] ?? 'General'
                ];
            }
            $saNode['children'][] = $hoNode;
        }
        $node['children'][] = $saNode;
    }
    $tree[] = $node;
}

$adminName = $_SESSION['user_name'] ?? 'Admin';
$initials = strtoupper(substr($adminName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>National Workforce Tree | ReportMyCity</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .tree-wrapper { padding: 3rem; background: var(--bg-card); border-radius: 12px; margin-top: 2rem; }
        .tier-na { border-left: 4px solid var(--gov-navy); margin-bottom: 2rem; padding-left: 1.5rem; }
        .tier-sa { border-left: 3px solid var(--primary); margin-left: 2rem; margin-top: 1.5rem; padding-left: 1rem; }
        .tier-ho { border-left: 2px solid #f59e0b; margin-left: 4rem; margin-top: 1rem; padding-left: 1rem; }
        .tier-fo { border-left: 1px solid var(--border); margin-left: 6rem; margin-top: 0.5rem; padding-left: 1rem; display: flex; flex-wrap: wrap; gap: 8px; }
        
        .node-card { background: #fff; padding: 0.8rem 1.25rem; border: 1px solid var(--border); border-radius: 8px; display: inline-flex; align-items: center; gap: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .node-name { font-weight: 700; color: var(--text-primary); }
        .node-role { font-size: 0.75rem; background: var(--bg-body); padding: 2px 8px; border-radius: 4px; color: var(--text-muted); text-transform: uppercase; }
        .node-loc { font-size: 0.8rem; color: var(--primary); font-weight: 600; }
    </style>
</head>
<body class="admin-theme">
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <div class="page-header">
                <h1><i class="la la-sitemap"></i> National Personnel Hierarchy</h1>
                <div class="user-info">Welcome, <strong><?php echo htmlspecialchars($adminName); ?></strong></div>
            </div>

            <div class="tree-wrapper">
                <?php if (empty($tree)): ?>
                    <p>No personnel records found.</p>
                <?php else: ?>
                    <?php foreach ($tree as $na): ?>
                        <div class="tier-na">
                            <div class="node-card">
                                <i class="la la-globe"></i>
                                <div>
                                    <div class="node-name"><?php echo htmlspecialchars($na['name']); ?></div>
                                    <span class="node-role"><?php echo $na['role']; ?></span>
                                </div>
                            </div>
                            
                            <?php foreach ($na['children'] as $sa): ?>
                                <div class="tier-sa">
                                    <div class="node-card">
                                        <i class="la la-map-marker"></i>
                                        <div>
                                            <div class="node-name"><?php echo htmlspecialchars($sa['name']); ?></div>
                                            <span class="node-loc"><?php echo htmlspecialchars($sa['location']); ?></span>
                                            <span class="node-role"><?php echo $sa['role']; ?></span>
                                        </div>
                                    </div>

                                    <?php foreach ($sa['children'] as $ho): ?>
                                        <div class="tier-ho">
                                            <div class="node-card">
                                                <i class="la la-building-o"></i>
                                                <div>
                                                    <div class="node-name"><?php echo htmlspecialchars($ho['name']); ?></div>
                                                    <span class="node-loc"><?php echo htmlspecialchars($ho['location']); ?> Head</span>
                                                </div>
                                            </div>

                                            <div class="tier-fo">
                                                <?php foreach ($ho['children'] as $fo): ?>
                                                    <div class="node-card" style="padding: 0.5rem 0.8rem;">
                                                        <i class="la la-user-o" style="font-size: 0.8rem;"></i>
                                                        <div style="font-size: 0.85rem; font-weight: 600;"><?php echo htmlspecialchars($fo['name']); ?></div>
                                                    </div>
                                                <?php endforeach; ?>
                                                <?php if (empty($ho['children'])): ?>
                                                    <span style="font-size:0.8rem; color:var(--text-muted); font-style:italic;">No team members</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
