<?php
/**
 * ReportMyCity India — National Admin Dashboard (Super Admin)
 */
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'national_admin') {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$complaintsCol = $db->getCollection('complaints');
$usersCol      = $db->getCollection('users');
$adminsCol     = $db->getCollection('admins');
$stateAdminsCol = $db->getCollection('state_admins');
$headOfficersCol = $db->getCollection('head_officers');
$fieldOfficersCol = $db->getCollection('field_officers');

// Stats Calculation (National - All)
// Stats Calculation (National - All)
$totalUsers = $usersCol->countDocuments();
$totalOfficers = $headOfficersCol->countDocuments() + $fieldOfficersCol->countDocuments();
$totalStateAdmins = $stateAdminsCol->countDocuments();



$adminName = $_SESSION['user_name'] ?? 'National Admin';
$adminEmail = $_SESSION['user_email'] ?? 'admin@reportmycity.gov';
$initials = strtoupper(substr($adminName, 0, 1));

// Hierarchy Data Collection
$hierarchy = [];
$saFilter = ['role' => 'state_admin'];
if (!empty($filter['state'])) $saFilter['state'] = $filter['state'];
$stateAdmins = $adminsCol->find($saFilter);
foreach ($stateAdmins as $sa) {
    $state = $sa['state'] ?? 'Unknown';
    $hierarchy[$state] = [
        'admin' => $sa['name'],
        'email' => $sa['email'],
        'departments' => []
    ];
}

$allOfficers = $officersCol->find($filter, ['sort' => ['state' => 1, 'department' => 1, 'role' => 1]]);
foreach ($allOfficers as $o) {
    $state = $o['state'] ?? 'Unknown';
    $dept  = $o['department'] ?? 'General';
    $role  = $o['role'] ?? 'local_officer';
    
    if (!isset($hierarchy[$state])) {
        $hierarchy[$state] = ['admin' => '—', 'email' => '—', 'departments' => []];
    }
    if (!isset($hierarchy[$state]['departments'][$dept])) {
        $hierarchy[$state]['departments'][$dept] = ['head' => '—', 'team' => []];
    }
    
    if ($role === 'senior_officer') {
        $hierarchy[$state]['departments'][$dept]['head'] = $o['name'];
    } else {
        $hierarchy[$state]['departments'][$dept]['team'][] = $o['name'];
    }
}

// Contributions Collection (Top 8 Performers)
$contribResults = [];
$resolvedIter = $complaintsCol->find(['status' => ['$in' => ['Resolved', 'Closed', 'Officer Completed']]]);
foreach ($resolvedIter as $c) {
    $oid = (string)($c['assigned_officer_id'] ?? '');
    // The following block seems to be misplaced from an API update script.
    // It's being commented out to maintain syntactic correctness and context.
    // If this logic is intended for this dashboard, it needs to be re-evaluated.
    /*
    if (isset($input['assigned_officer_id']) && in_array($_SESSION['role'], $canAssign)) {
        $officerId = htmlspecialchars(trim($input['assigned_officer_id'] ?? ''), ENT_QUOTES, 'UTF-8');
        $updateFields['assigned_officer_id'] = $officerId;
        if (!empty($officerId)) {
            $contribResults[$oid] = ($contribResults[$oid] ?? 0) + 1;
        }
    }
    */
    // Original logic for contributions:
    if ($oid) {
        $contribResults[$oid] = ($contribResults[$oid] ?? 0) + 1;
    }
}
arsort($contribResults);
$topOfficers = [];
$count = 0;
foreach ($contribResults as $oid => $resCount) {
    if ($count >= 8) break;
    try {
        $off = $officersCol->findOne(['_id' => new \MongoDB\BSON\ObjectId($oid)]);
        if ($off) {
            $topOfficers[] = [
                'name'  => $off['name'],
                'dept'  => $off['department'] ?? 'General',
                'state' => $off['state'] ?? 'Unknown',
                'res'   => $resCount
            ];
            $count++;
        }
    } catch (Exception $e) {}
}

// ── Complaint stats ──────────────────────────────────────────────────────────
$totalComplaints = $complaintsCol->countDocuments($filter);
$pendingCount    = $complaintsCol->countDocuments(['status' => ['$in' => ['Pending','Submitted','Assigned','Under Review']]] + $filter);
$progressCount   = $complaintsCol->countDocuments(['status' => ['$in' => ['In Progress','Escalated']]] + $filter);
$resolvedCount   = $complaintsCol->countDocuments(['status' => ['$in' => ['Resolved','Closed','Officer Completed']]] + $filter);

// ── Recent 10 complaints ─────────────────────────────────────────────────────
$recentComplaints = iterator_to_array(
    $complaintsCol->find($filter, ['sort' => ['created_at' => -1], 'limit' => 10])
);

// Pre-fetch distinct states/districts for filters
$statesList = $complaintsCol->distinct('state');
$districtsList = [];
if (!empty($filter['state'])) {
    $districtsList = $complaintsCol->distinct('district', ['state' => $filter['state']]);
}

// ── Sidebar badge counts ─────────────────────────────────────────────────────
$officerReportsCount = $db->getCollection('officer_reports')->countDocuments(['status' => 'Pending Admin Review']);
$userReportsCount    = $db->getCollection('user_reports')->countDocuments(['status' => 'Audit Requested']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>National Admin Dashboard | ReportMyCity India</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        #map-container {
            height: 500px; 
            width: 100%; 
            border-radius: 0 0 var(--radius-md) var(--radius-md);
            z-index: 10;
        }
        .map-stats-bar {
            padding: 1rem 1.5rem; 
            border-bottom: 1px solid var(--border); 
            display: flex; 
            flex-wrap: wrap;
            gap: 20px;
            background: var(--bg-card);
            align-items: center;
        }
        .marker-pin {
            width: 30px;
            height: 30px;
            border-radius: 50% 50% 50% 0;
            background: #c30b82;
            position: absolute;
            transform: rotate(-45deg);
            left: 50%;
            top: 50%;
            margin: -15px 0 0 -15px;
        }
        .custom-div-icon i {
            color: #fff;
            position: absolute;
            width: 22px;
            font-size: 14px;
            left: 4px;
            top: 4px;
            text-align: center;
        }
    </style>
</head>
<body class="admin-theme">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <div class="header-left">
                    <button class="sidebar-toggle"><i class="la la-bars"></i></button>
                    <div class="header-logo-group">
                        <img src="../assets/images/govt_emblem.png" alt="Emblem">
                        <span>ReportMyCity India National</span>
                    </div>
                    <div>
                        <h1>National Overview</h1>
                        <div class="breadcrumb">
                            <a href="dashboard.php">Home</a>
                            <span>›</span>
                            <span>National Console</span>
                        </div>
                    </div>
                </div>
                <div class="user-info">
                    <div style="display:flex; gap:0.5rem; align-items:center; margin-right:1rem;">
                        <span style="font-size:0.8rem; color:var(--text-muted);">Region:</span>
                        <select id="state_filter" class="filter-select" onchange="applyDashFilters()" style="padding:0.4rem; border-radius:5px; border:1px solid var(--border); font-size:0.8rem;">
                            <option value="">All States</option>
                            <?php foreach ($statesList as $s): ?>
                                <option value="<?php echo htmlspecialchars($s); ?>" <?php echo ($_GET['state_filter'] ?? '') === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="district_filter" class="filter-select" onchange="applyDashFilters()" style="padding:0.4rem; border-radius:5px; border:1px solid var(--border); font-size:0.8rem;">
                            <option value="">All Districts</option>
                            <?php foreach ($districtsList as $d): ?>
                                <option value="<?php echo htmlspecialchars($d); ?>" <?php echo ($_GET['district_filter'] ?? '') === $d ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if(!empty($_GET['state_filter'])): ?>
                            <a href="dashboard.php" style="font-size:1.1rem; color:var(--danger); text-decoration:none;" title="Reset Filters">&times;</a>
                        <?php endif; ?>
                    </div>
                    <span>Welcome, <?php echo htmlspecialchars($adminName); ?></span>
                </div>
            </div>

            <!-- Welcome Banner -->
            <div class="profile-card" style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%); color: white;">
                <div class="profile-avatar" style="background: rgba(255,255,255,0.2); border: 2px solid white;"><?php echo $initials; ?></div>
                <div class="profile-info">
                    <h3>National Command Center — <?php echo htmlspecialchars($adminName); ?></h3>
                    <p>Highest level of governance and system oversight across all states.</p>
                    <div class="profile-meta">
                        <span style="background: rgba(255,255,255,0.2);"><i class="la la-landmark"></i> National Portal</span>
                        <span style="background: rgba(255,255,255,0.2);"><i class="la la-calendar"></i> <?php echo date('d M Y'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid" style="grid-template-columns: 1fr 1fr;">
                <div class="card" style="padding: 1.5rem; display: flex; flex-direction: column; align-items: center;">
                    <h3 style="margin-bottom: 1rem; font-size: 1rem;">Workforce (Officers & Admins)</h3>
                    <div style="width: 100%; height: 220px;">
                        <canvas id="userDistChart"></canvas>
                    </div>
                </div>

                <div class="card" style="padding: 1.5rem;">
                    <h3>National Metrics</h3>
                    <div style="margin-top: 1rem; display: flex; flex-direction: column; gap: 0.8rem;">

                        <div style="display: flex; justify-content: space-between; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border);">
                            <span style="color: var(--text-muted); font-size: 0.85rem;">Active State Admins</span>
                            <span style="font-weight: 700;"><?php echo $totalStateAdmins; ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border);">
                            <span style="color: var(--text-muted); font-size: 0.85rem;">Total Registered Citizens</span>
                            <span style="font-weight: 700;"><?php echo $totalUsers; ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-muted); font-size: 0.85rem;">Average Response Time</span>
                            <span style="font-weight: 700;">2.4 Days</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================
                 COMPLAINTS OVERVIEW — Attractive Card Section
                 ============================================================ -->
            <div class="card" style="margin-top: 2rem; overflow: hidden;">
                <!-- Header -->
                <div class="card-header" style="background: linear-gradient(135deg, #0f172a, #1e293b); color:#fff; padding: 1.2rem 1.5rem; flex-wrap: wrap; gap: 0.8rem;">
                    <div>
                        <h3 style="color:#fff; margin:0; font-size:1.15rem;"><i class="la la-file-text-o"></i> &nbsp;Recent Complaints</h3>
                        <p style="font-size:0.78rem; color:rgba(255,255,255,0.55); margin:4px 0 0;">Latest 10 citizen reports — live from the database</p>
                    </div>
                    <a href="manage_complaints.php" style="padding: 0.45rem 1.1rem; border-radius: 5px; background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.25); color:#fff; font-size:0.8rem; text-decoration:none; white-space:nowrap;">
                        View All &rarr;
                    </a>
                </div>

                <!-- Stat Pills Row -->
                <div style="display:flex; flex-wrap:wrap; border-bottom: 1px solid var(--border);">
                    <?php
                    $pills = [
                        ['total',     '#6366f1', '<i class="la la-clipboard"></i>', 'Total',       $totalComplaints],
                        ['pending',   '#f59e0b', '<i class="la la-hourglass-half"></i>', 'Pending',     $pendingCount],
                        ['progress',  '#3b82f6', '<i class="la la-sync-alt"></i>', 'In Progress', $progressCount],
                        ['resolved',  '#10b981', '<i class="la la-check-circle"></i>', 'Resolved',    $resolvedCount],
                    ];
                    foreach ($pills as [$key, $col, $icon, $label, $val]):
                    ?>
                    <div style="flex:1; min-width:120px; padding:1rem 1.4rem; border-right: 1px solid var(--border); display:flex; align-items:center; gap:10px; background: var(--bg-card);">
                        <span style="font-size:1.4rem;"><?php echo $icon; ?></span>
                        <div>
                            <div style="font-size:1.6rem; font-weight:800; color:<?php echo $col; ?>; line-height:1;"><?php echo $val; ?></div>
                            <div style="font-size:0.68rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.06em; margin-top:2px;"><?php echo $label; ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Complaint Cards -->
                <?php if (empty($recentComplaints)): ?>
                <div style="padding: 3rem; text-align:center; color: var(--text-muted);">
                    <i class="la la-folder-open-o" style="font-size:2.5rem; opacity:0.3;"></i>
                    <p style="margin-top:0.8rem; font-size:0.9rem;">No complaints registered yet.</p>
                </div>
                <?php else: ?>
                <div style="padding: 1.2rem 1.5rem; display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1rem;">
                    <?php foreach ($recentComplaints as $c):
                        $cid    = (string)$c['_id'];
                        $title  = htmlspecialchars($c['title'] ?? 'Untitled Complaint');
                        $cat    = htmlspecialchars($c['category'] ?? 'General');
                        $loc    = htmlspecialchars($c['location'] ?? '—');
                        $state  = htmlspecialchars($c['state'] ?? '');
                        $status = $c['status'] ?? 'Pending';
                        $date   = htmlspecialchars($c['created_at'] ?? '');

                        // Status look-up
                        $sGroup = 'pending';
                        $sLower = strtolower($status);
                        if (in_array($sLower, ['resolved','closed','officer completed'])) $sGroup = 'resolved';
                        elseif (in_array($sLower, ['in progress','escalated']))            $sGroup = 'progress';

                        $sColor = ['pending' => '#f59e0b', 'progress' => '#3b82f6', 'resolved' => '#10b981'][$sGroup];
                        $sBg    = ['pending' => '#fffbeb', 'progress' => '#eff6ff', 'resolved' => '#ecfdf5'][$sGroup];

                        // Category icon
                        $catIcons = [
                            'Road' => '<i class="la la-road"></i>', 'Water' => '<i class="la la-tint"></i>', 'Electricity' => '<i class="la la-bolt"></i>',
                            'Sanitation' => '<i class="la la-trash"></i>', 'Cyber Crime' => '<i class="la la-lock"></i>',
                            'Public Safety' => '<i class="la la-bell"></i>', 'General' => '<i class="la la-thumbtack"></i>'
                        ];
                        $catIcon = $catIcons[$cat] ?? '<i class="la la-thumbtack"></i>';
                    ?>
                    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; padding: 1rem 1.1rem; display:flex; flex-direction:column; gap:0.6rem; transition: box-shadow 0.2s; position:relative; overflow:hidden;">
                        <!-- Color accent strip -->
                        <div style="position:absolute; top:0; left:0; width:4px; height:100%; background:<?php echo $sColor; ?>; border-radius:10px 0 0 10px;"></div>

                        <!-- Title row -->
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:6px;">
                            <div style="font-weight:700; font-size:0.9rem; color:var(--text-primary); line-height:1.3; flex:1;">
                                <?php echo $catIcon; ?> <?php echo $title; ?>
                            </div>
                            <span style="display:inline-block; padding:3px 9px; border-radius:20px; background:<?php echo $sBg; ?>; color:<?php echo $sColor; ?>; font-size:0.68rem; font-weight:700; white-space:nowrap; border:1px solid <?php echo $sColor; ?>40;">
                                <?php echo htmlspecialchars($status); ?>
                            </span>
                        </div>

                        <!-- Meta row -->
                        <div style="display:flex; flex-wrap:wrap; gap:0.5rem; font-size:0.73rem; color:var(--text-muted);">
                            <span style="background:var(--bg-input); padding:2px 7px; border-radius:4px; border:1px solid var(--border);">
                                <i class="la la-tag"></i> <?php echo $cat; ?>
                            </span>
                            <?php if ($state): ?>
                            <span style="background:var(--bg-input); padding:2px 7px; border-radius:4px; border:1px solid var(--border);">
                                <i class="la la-map-marker"></i> <?php echo $state; ?>
                            </span>
                            <?php endif; ?>
                            <?php if ($date): ?>
                            <span style="background:var(--bg-input); padding:2px 7px; border-radius:4px; border:1px solid var(--border);">
                                <i class="la la-clock-o"></i> <?php echo date('d M', strtotime($date)); ?>
                            </span>
                            <?php endif; ?>
                        </div>

                        <!-- Location -->
                        <?php if ($loc && $loc !== '—' && !str_contains($loc, ',')): ?>
                        <div style="font-size:0.74rem; color:var(--text-muted); display:flex; align-items:center; gap:4px;">
                            <i class="la la-map-pin" style="color:<?php echo $sColor; ?>;"></i>
                            <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?php echo $loc; ?></span>
                        </div>
                        <?php endif; ?>

                        <!-- Action link -->
                        <div style="margin-top:auto; padding-top:0.4rem; border-top:1px solid var(--border);">
                            <a href="manage_complaints.php" style="font-size:0.72rem; color:var(--primary); text-decoration:none; font-weight:600;">
                                View in complaint manager &rarr;
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- ============================================================
                 INDIA COMPLAINT MAP — Live Report Points
                 ============================================================ -->
            <div class="card" style="margin-top: 2rem; overflow: hidden;">
                <div class="card-header" style="background: linear-gradient(135deg, var(--gov-navy), var(--gov-navy-mid)); color:#fff; padding: 1.2rem 1.5rem;">
                    <div>
                        <h3 style="color:#fff; margin: 0; font-size: 1.15rem;">
                            <i class="la la-map-marker"></i> &nbsp;India Complaint Map
                        </h3>
                        <p style="font-size: 0.78rem; color: rgba(255,255,255,0.65); margin: 4px 0 0;">
                            Live view of all citizen complaint locations across the nation
                        </p>
                    </div>
                    <div style="display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap;">
                        <select id="map-category-filter" style="padding: 0.4rem 0.8rem; border-radius: 5px; border: 1px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.12); color:#fff; font-size: 0.8rem; cursor: pointer;">
                            <option value="">All Categories</option>
                            <option value="Road">Road</option>
                            <option value="Water">Water</option>
                            <option value="Electricity">Electricity</option>
                            <option value="Sanitation">Sanitation</option>
                            <option value="Cyber Crime">Cyber Crime</option>
                            <option value="Public Safety">Public Safety</option>
                            <option value="Other">Other</option>
                        </select>
                        <select id="map-status-filter" style="padding: 0.4rem 0.8rem; border-radius: 5px; border: 1px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.12); color:#fff; font-size: 0.8rem; cursor: pointer;">
                            <option value="">All Statuses</option>
                            <option value="pending"><i class="la la-hourglass-half"></i> Pending</option>
                            <option value="progress"><i class="la la-sync-alt"></i> In Progress</option>
                            <option value="resolved"><i class="la la-check-circle"></i> Resolved</option>
                        </select>
                        <button onclick="resetMapFilters()" style="padding: 0.4rem 0.8rem; border-radius: 5px; border: 1px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.1); color:#fff; font-size: 0.8rem; cursor: pointer;">
                            ↺ Reset
                        </button>
                    </div>
                </div>

                <!-- Stats Bar -->
                <div style="display: flex; flex-wrap: wrap; gap: 0; border-bottom: 1px solid var(--border);">
                    <div style="flex: 1; min-width: 120px; padding: 0.9rem 1.5rem; display: flex; align-items: center; gap: 10px; border-right: 1px solid var(--border);">
                        <span style="width: 12px; height: 12px; border-radius: 50%; background: #f59e0b; display: inline-block; flex-shrink: 0;"></span>
                        <div><div style="font-size: 1.4rem; font-weight: 800; color: #f59e0b;" id="stat-pending">—</div><div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Pending</div></div>
                    </div>
                    <div style="flex: 1; min-width: 120px; padding: 0.9rem 1.5rem; display: flex; align-items: center; gap: 10px; border-right: 1px solid var(--border);">
                        <span style="width: 12px; height: 12px; border-radius: 50%; background: #3b82f6; display: inline-block; flex-shrink: 0;"></span>
                        <div><div style="font-size: 1.4rem; font-weight: 800; color: #3b82f6;" id="stat-progress">—</div><div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">In Progress</div></div>
                    </div>
                    <div style="flex: 1; min-width: 120px; padding: 0.9rem 1.5rem; display: flex; align-items: center; gap: 10px; border-right: 1px solid var(--border);">
                        <span style="width: 12px; height: 12px; border-radius: 50%; background: #10b981; display: inline-block; flex-shrink: 0;"></span>
                        <div><div style="font-size: 1.4rem; font-weight: 800; color: #10b981;" id="stat-resolved">—</div><div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Resolved</div></div>
                    </div>
                    <div style="flex: 1; min-width: 120px; padding: 0.9rem 1.5rem; display: flex; align-items: center; gap: 10px;">
                        <span style="width: 12px; height: 12px; border-radius: 50%; background: var(--gov-gold); display: inline-block; flex-shrink: 0;"></span>
                        <div><div style="font-size: 1.4rem; font-weight: 800; color: var(--gov-navy);" id="stat-total">—</div><div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Total on Map</div></div>
                    </div>
                    <div style="padding: 0.9rem 1.5rem; display: flex; align-items: center;">
                        <div id="map-loading" style="font-size: 0.8rem; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
                            <i class="la la-spinner la-spin"></i> Loading map data...
                        </div>
                    </div>
                </div>

                <!-- Map -->
                <div id="map-container" style="height: 580px; width: 100%;"></div>

                <!-- Legend -->
                <div style="padding: 0.75rem 1.5rem; background: var(--bg-body); border-top: 1px solid var(--border); display: flex; flex-wrap: wrap; gap: 1.2rem; align-items: center; font-size: 0.78rem; color: var(--text-muted);">
                    <strong style="color: var(--text-primary);">Legend:</strong>
                    <span><span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#f59e0b; margin-right:4px;"></span>Pending / Submitted / Assigned</span>
                    <span><span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#3b82f6; margin-right:4px;"></span>In Progress / Escalated</span>
                    <span><span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#10b981; margin-right:4px;"></span>Resolved / Closed</span>
                    <span style="margin-left: auto; font-size: 0.72rem;"><i class="la la-map-marker"></i> Click any marker to view complaint details</span>
                </div>
            </div>

            <!-- JS for Map -->
            <script>
            (function() {
                let allMarkers = [];
                let allPointsData = [];
                let mapObj = null;

                function getStatusGroup(status) {
                    const s = (status || '').toLowerCase();
                    if (['resolved','closed','officer completed'].includes(s)) return 'resolved';
                    if (['in progress','escalated'].includes(s)) return 'progress';
                    return 'pending';
                }

                function getColor(group) {
                    if (group === 'resolved') return '#10b981';
                    if (group === 'progress') return '#3b82f6';
                    return '#f59e0b';
                }

                function makeIcon(color) {
                    return L.divIcon({
                        className: 'custom-div-icon',
                        html: `<div style="background-color:${color}; width:14px; height:14px; border-radius:50%; border:2px solid #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.35);"></div>`,
                        iconSize: [14, 14],
                        iconAnchor: [7, 7]
                    });
                }

                function initMap() {
                    mapObj = L.map('map-container', { zoomControl: true }).setView([22.5937, 80.9629], 5);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 18,
                        attribution: '© OpenStreetMap'
                    }).addTo(mapObj);

                    fetch('../api/get_heatmap_data.php?format=detailed')
                        .then(r => r.json())
                        .then(res => {
                            document.getElementById('map-loading').style.display = 'none';
                            if (!res.success || !res.data || !res.data.length) return;

                            allPointsData = res.data;
                            renderMarkers(allPointsData);
                        })
                        .catch(() => {
                            document.getElementById('map-loading').innerHTML = '⚠️ Could not load map data.';
                        });
                }

                function renderMarkers(data) {
                    // Remove existing markers
                    allMarkers.forEach(m => mapObj.removeLayer(m));
                    allMarkers = [];

                    let counts = { pending: 0, progress: 0, resolved: 0 };

                    data.forEach(point => {
                        const [lat, lng, status, title, category] = point;
                        const group = getStatusGroup(status);
                        const color = getColor(group);
                        counts[group]++;

                        const marker = L.marker([lat, lng], { icon: makeIcon(color) })
                            .bindPopup(`
                                <div style="font-family:'Inter',sans-serif; min-width:180px; padding:2px;">
                                    <div style="font-weight:700; color:#0a2558; font-size:0.9rem; margin-bottom:4px;">${title}</div>
                                    <div style="font-size:0.75rem; color:#6b7d9f; margin-bottom:6px;">${category}</div>
                                    <hr style="margin: 4px 0; border-color:#e2e8f0;">
                                    <span style="display:inline-block; padding:2px 8px; border-radius:12px; background:${color}; color:#fff; font-size:0.7rem; font-weight:600;">${status}</span>
                                </div>
                            `);
                        marker.addTo(mapObj);
                        allMarkers.push(marker);
                    });

                    document.getElementById('stat-pending').textContent = counts.pending;
                    document.getElementById('stat-progress').textContent = counts.progress;
                    document.getElementById('stat-resolved').textContent = counts.resolved;
                    document.getElementById('stat-total').textContent = data.length;
                }

                function applyMapFilters() {
                    const cat = document.getElementById('map-category-filter').value.toLowerCase();
                    const st  = document.getElementById('map-status-filter').value;

                    const filtered = allPointsData.filter(([lat, lng, status, title, category]) => {
                        const matchCat = !cat || (category || '').toLowerCase().includes(cat);
                        const matchSt  = !st  || getStatusGroup(status) === st;
                        return matchCat && matchSt;
                    });
                    renderMarkers(filtered);
                }

                window.resetMapFilters = function() {
                    document.getElementById('map-category-filter').value = '';
                    document.getElementById('map-status-filter').value = '';
                    renderMarkers(allPointsData);
                };

                document.getElementById('map-category-filter').addEventListener('change', applyMapFilters);
                document.getElementById('map-status-filter').addEventListener('change', applyMapFilters);

                // Init after Leaflet loads
                if (typeof L !== 'undefined') {
                    initMap();
                } else {
                    document.addEventListener('DOMContentLoaded', initMap);
                }
            })();
            </script>

            <!-- Officer Performance / Contributions (NEW) -->
            <div class="card" style="margin-top: 2rem;">
                <div class="card-header">
                    <h3><i class="la la-trophy"></i> Officer Excellence: Resolution Contributions</h3>
                    <span style="font-size: 0.8rem; color: var(--text-muted);">Top Performers Nationwide</span>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Officer Name</th>
                                <th>Department</th>
                                <th>State Jurisdiction</th>
                                <th>Resolved Issues</th>
                                <th>Performance Index</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topOfficers as $idx => $off): ?>
                            <tr>
                                <td style="font-weight: 600; display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 28px; height: 28px; border-radius: 50%; background: <?php echo $idx < 3 ? 'var(--warning)' : 'var(--bg-input)'; ?>; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; color: <?php echo $idx < 3 ? '#fff' : 'var(--text-muted)'; ?>;">
                                        <?php echo $idx + 1; ?>
                                    </div>
                                    <?php echo htmlspecialchars($off['name']); ?>
                                </td>
                                <td><span class="badge" style="background: var(--primary-light); color: #fff;"><?php echo htmlspecialchars($off['dept']); ?></span></td>
                                <td><?php echo htmlspecialchars($off['state']); ?></td>
                                <td style="font-weight: 700; color: var(--success);"><?php echo $off['res']; ?></td>
                                <td>
                                    <div style="width: 100px; height: 6px; background: var(--bg-input); border-radius: 3px; overflow: hidden;">
                                        <div style="width: <?php echo min(100, $off['res'] * 10); ?>%; height: 100%; background: var(--success);"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>


        </main>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        function applyDashFilters() {
            const s = document.getElementById('state_filter').value;
            const d = document.getElementById('district_filter').value;
            let url = 'dashboard.php';
            if (s) {
                url += '?state_filter=' + encodeURIComponent(s);
                if (d) url += '&district_filter=' + encodeURIComponent(d);
            }
            location.href = url;
        }

        document.addEventListener('DOMContentLoaded', function() {
            new Chart(document.getElementById('userDistChart').getContext('2d'), {
                type: 'pie',
                data: {
                    labels: ['Admins', 'Officers', 'Citizens'],
                    datasets: [{
                        data: [<?php echo $totalStateAdmins; ?>, <?php echo $totalOfficers; ?>, <?php echo $totalUsers; ?>],
                        backgroundColor: ['#6366f1', '#06b6d4', '#f43f5e']
                    }]
                },
                options: { plugins: { legend: { position: 'bottom' } } }
            });
        });
    </script>
</body>
</html>
