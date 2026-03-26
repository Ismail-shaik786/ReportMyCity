<?php
/**
 * ReportMyCity India — State Admin: Workforce Hierarchy View
 */
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'state_admin') {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$state = $_SESSION['state'];
$headOfficersCol = $db->getCollection('head_officers');
$fieldOfficersCol = $db->getCollection('field_officers');

// Fetch hierarchy for this state
$hierarchy = [];
$hOfficers = iterator_to_array($headOfficersCol->find(['state' => $state]));

foreach ($hOfficers as $ho) {
    $hoId = (string)$ho['_id'];
    $dept = $ho['department'] ?? 'General';
    
    if (!isset($hierarchy[$dept])) {
        $hierarchy[$dept] = [];
    }
    
    $hoNode = [
        'name' => $ho['name'],
        'role' => 'Dept Head',
        'email' => $ho['email'],
        'children' => []
    ];

    // Field Officers in this department
    $fOfficers = iterator_to_array($fieldOfficersCol->find(['department' => $dept, 'state' => $state]));
    foreach ($fOfficers as $fo) {
        $hoNode['children'][] = [
            'name' => $fo['name'],
            'role' => 'Field Officer',
            'email' => $fo['email'],
            'subcategory' => $fo['subcategory'] ?? 'General'
        ];
    }
    $hierarchy[$dept][] = $hoNode;
}

$adminName = $_SESSION['user_name'] ?? 'State Admin';
$initials = strtoupper(substr($adminName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>State Workforce Hierarchy | ReportMyCity</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .tree-card { background: var(--bg-card); border-radius: 12px; padding: 2rem; margin-top: 1.5rem; border: 1px solid var(--border); }
        .dept-title { font-size: 1.1rem; font-weight: 800; color: var(--gov-navy); margin-bottom: 2rem; border-bottom: 2px solid var(--primary); display: inline-block; padding-bottom: 4px; }
        .ho-node { background: #fff; border: 1px solid var(--border); padding: 1rem 1.5rem; border-radius: 10px; margin-left: 2rem; position: relative; display: inline-flex; flex-direction: column; gap: 4px; box-shadow: var(--shadow-sm); }
        .ho-node::before { content: ''; position: absolute; left: -1.5rem; top: 1.5rem; width: 1.5rem; height: 2px; background: var(--border); }
        .team-list { margin-left: 5rem; margin-top: 1rem; display: flex; flex-wrap: wrap; gap: 1rem; position: relative; padding-bottom: 2rem; }
        .team-list::before { content: ''; position: absolute; left: -1rem; top: -1rem; bottom: 2rem; width: 2px; border-left: 2px solid var(--border); border-bottom: 2px solid var(--border); border-radius: 0 0 0 8px; }
        .fo-chip { background: var(--bg-body); border: 1px solid var(--border); padding: 0.5rem 1rem; border-radius: 30px; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; }
    </style>
</head>
<body class="admin-theme">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <h2>ReportMyCity</h2>
                <span>State Administration</span>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php"><i class="la la-dashboard"></i> Dashboard</a>
                <a href="manage_departments.php"><i class="la la-building-o"></i> Departments</a>
                <a href="manage_officers.php"><i class="la la-users"></i> Manage Personnel</a>
                <a href="workforce_tree.php" class="active"><i class="la la-sitemap"></i> Workforce Tree</a>
                <a href="manage_complaints.php"><i class="la la-file-text-o"></i> Complaints</a>
                <a href="../logout.php"><i class="la la-sign-out"></i> Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h1>State Hierarchy — <?php echo htmlspecialchars($state); ?></h1>
            </div>

            <div class="grid" style="display: grid; grid-template-columns: 1fr; gap: 2rem;">
                <?php if (empty($hierarchy)): ?>
                    <p>No departments or officers identified in this state.</p>
                <?php else: ?>
                    <?php foreach ($hierarchy as $dept => $heads): ?>
                        <div class="tree-card">
                            <div class="dept-title"><i class="la la-university"></i> <?php echo htmlspecialchars($dept); ?></div>
                            <?php foreach ($heads as $ho): ?>
                                <div style="display:block; margin-bottom: 2rem;">
                                    <div class="ho-node">
                                        <div style="font-weight:700;"><?php echo htmlspecialchars($ho['name']); ?></div>
                                        <div style="font-size:0.7rem; color:var(--primary); font-weight:600;">DEPARTMENT HEAD</div>
                                        <div style="font-size:0.8rem; color:var(--text-muted);"><?php echo htmlspecialchars($ho['email']); ?></div>
                                    </div>
                                    
                                    <div class="team-list">
                                        <?php foreach ($ho['children'] as $fo): ?>
                                            <div class="fo-chip">
                                                <i class="la la-user-circle-o"></i>
                                                <span><strong><?php echo htmlspecialchars($fo['name']); ?></strong> — <?php echo htmlspecialchars($fo['subcategory']); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (empty($ho['children'])): ?>
                                            <span style="font-size:0.8rem; color:var(--text-muted); font-style:italic;">No field officers assigned</span>
                                        <?php endif; ?>
                                    </div>
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
