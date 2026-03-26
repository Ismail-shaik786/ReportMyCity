<?php
/**
 * ReportMyCity — Head Officer: Workforce Tree
 */
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'senior_officer') {
    header('Location: ../officer/officer_login.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$stateAdminsCol  = $db->getCollection('state_admins');
$headOfficersCol = $db->getCollection('head_officers');
$fieldOfficersCol = $db->getCollection('field_officers');
$userId = $_SESSION['user_id'];

$officerDoc  = $headOfficersCol->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
$department  = $officerDoc['department'] ?? 'Department';
$state       = $officerDoc['state'] ?? 'National';
$officerName = $_SESSION['user_name'] ?? 'Senior Officer';
$initials    = strtoupper(substr($officerName, 0, 1));
$officerPhoto = $officerDoc['photo'] ?? '';

// Fetch all field officers in this department
$filter = [
    'department' => $department,
    'state' => $state
];
$allTeam = iterator_to_array($fieldOfficersCol->find($filter));

// Organize into a tree structure
$tree = [];
$node = [
    'name' => $officerName,
    'role' => 'Department Head',
    'email' => $_SESSION['user_email'] ?? '',
    'children' => []
];

foreach ($allTeam as $l) {
    $node['children'][] = [
        'name' => $l['name'],
        'role' => 'Field Officer',
        'email' => $l['email'],
        'specialization' => $l['subcategory'] ?? 'General'
    ];
}
$tree[] = $node;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workforce Tree | <?php echo htmlspecialchars($department); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/main.js" defer></script>
    <style>
        .tree-container {
            padding: 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3rem;
        }
        .tree-branch {
            display: flex;
            gap: 2rem;
            justify-content: center;
            flex-wrap: wrap;
            position: relative;
        }
        .tree-node {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            width: 250px;
            text-align: center;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            position: relative;
        }
        .tree-node:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); border-color: var(--primary); }
        .node-avatar {
            width: 60px; height: 60px;
            background: linear-gradient(135deg, var(--gov-navy), var(--gov-navy-light));
            color: #fff;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem; font-weight: 700;
        }
        .node-name { font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem; }
        .node-role { font-size: 0.75rem; color: var(--primary); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .node-meta { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem; }
        
        /* Connector lines */
        .tree-branch::before {
            content: '';
            position: absolute;
            top: -1.5rem;
            left: 50%;
            width: 2px;
            height: 1.5rem;
            background: var(--border);
            display: none;
        }
        .tree-container > .tree-branch::before { display: none; }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="sidebar-brand-inner">
                    <img src="../assets/images/govt_emblem.png" alt="Emblem" class="sidebar-emblem">
                    <div class="sidebar-brand-text">
                        <h2>ReportMyCity India</h2>
                        <span>Department Head Console</span>
                    </div>
                </div>
            </div>
            <div class="sidebar-gold-stripe"></div>
            <nav class="sidebar-nav">
                <div class="sidebar-section-label">Navigation</div>
                <a href="dashboard.php">
                    <span class="nav-icon"><i class="la la-bar-chart-o"></i></span> Team Overview
                </a>
                <a href="my_department.php">
                    <span class="nav-icon"><i class="la la-list-alt"></i></span> Dept. Complaints
                </a>
                <a href="manage_team.php">
                    <span class="nav-icon"><i class="la la-users"></i></span> Manage Team
                </a>
                <a href="workforce_tree.php" class="active">
                    <span class="nav-icon"><i class="la la-sitemap"></i></span> Workforce Tree
                </a>
                <a href="heatmap.php">
                    <span class="nav-icon"><i class="la la-map"></i></span> Heatmap
                </a>
                <a href="profile.php">
                    <span class="nav-icon"><i class="la la-user-o"></i></span> My Profile
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="sidebar-user-info">
                    <div class="sidebar-user-avatar">
                        <?php if ($officerPhoto): ?>
                            <img src="../<?php echo htmlspecialchars($officerPhoto); ?>" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                        <?php else: ?>
                            <?php echo $initials; ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="sidebar-user-name"><?php echo htmlspecialchars($officerName); ?></div>
                        <div class="sidebar-user-role"><?php echo htmlspecialchars($department); ?> Head</div>
                    </div>
                </div>
                <a href="../logout.php"><i class="la la-sign-out"></i> Logout</a>
            </div>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div class="header-left">
                    <button class="sidebar-toggle"><i class="la la-bars"></i></button>
                    <div class="header-logo-group">
                        <img src="../assets/images/govt_emblem.png" alt="Emblem">
                        <span>ReportMyCity India</span>
                    </div>
                    <h1>Workforce Hierarchy — <?php echo htmlspecialchars($department); ?></h1>
                </div>
                <div class="user-info">
                    <span style="font-size:0.82rem; color:var(--text-muted);"><?php echo date('d M Y'); ?></span>
                    <span>Welcome, <?php echo htmlspecialchars($officerName); ?></span>
                    <div class="profile-dropdown-wrapper" id="profileDropdownWrapper">
                        <div class="user-avatar">
                            <?php if ($officerPhoto): ?>
                                <img src="../<?php echo htmlspecialchars($officerPhoto); ?>" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                            <?php else: ?>
                                <?php echo $initials; ?>
                            <?php endif; ?>
                        </div>
                        <div class="profile-dropdown-menu">
                            <div class="profile-dropdown-header">
                                <strong><?php echo htmlspecialchars($officerName); ?></strong>
                                <span><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></span>
                            </div>
                            <a href="../logout.php" class="dropdown-logout"><i class="la la-sign-out"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="page-body">
                <div class="tree-container">
                    <?php foreach ($tree as $senior): ?>
                        <div class="tree-node" style="border-bottom: 4px solid var(--primary);">
                            <div class="node-avatar"><?php echo strtoupper(substr($senior['name'], 0, 1)); ?></div>
                            <div class="node-name"><?php echo htmlspecialchars($senior['name']); ?></div>
                            <div class="node-role"><?php echo $senior['role']; ?></div>
                            <div class="node-meta"><?php echo htmlspecialchars($senior['email']); ?></div>
                        </div>

                        <div class="tree-branch">
                            <?php foreach ($senior['children'] as $child): ?>
                                <div class="tree-node">
                                    <div class="node-avatar" style="background: var(--bg-input); color: var(--text-primary); border: 2px solid var(--border);">
                                        <?php echo strtoupper(substr($child['name'], 0, 1)); ?>
                                    </div>
                                    <div class="node-name"><?php echo htmlspecialchars($child['name']); ?></div>
                                    <div class="node-role"><?php echo $child['role']; ?></div>
                                    <div class="node-meta"><?php echo htmlspecialchars($child['specialization']); ?></div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($senior['children'])): ?>
                                <p style="color: var(--text-muted); font-size: 0.9rem;">No field officers assigned to this team.</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Use global listeners from main.js, but explicitly initialize just in case
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof initSidebar === 'function') initSidebar();
            if (typeof initProfileDropdown === 'function') initProfileDropdown();
        });
    </script>
</body>
</html>
