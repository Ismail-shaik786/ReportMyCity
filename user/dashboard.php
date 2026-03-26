<?php
/**
 * ReportMyCity — User Dashboard
 */
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$complaints = $db->getCollection('complaints');
$userId = $_SESSION['user_id'];

// Stats
$totalComplaints   = $complaints->countDocuments(['user_id' => $userId]);
$pendingComplaints = $complaints->countDocuments(['user_id' => $userId, 'status' => 'Pending']);
$progressComplaints = $complaints->countDocuments(['user_id' => $userId, 'status' => 'In Progress']);
$resolvedComplaints = $complaints->countDocuments(['user_id' => $userId, 'status' => 'Resolved']);

// Recent complaints
$recentComplaints = $complaints->find(
    ['user_id' => $userId],
    ['sort' => ['created_at' => -1], 'limit' => 5]
);

$userName = $_SESSION['user_name'] ?? 'User';
$userEmail = $_SESSION['user_email'] ?? '';
$userDoc = $db->getCollection('users')->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
$userPhoto = $userDoc['photo'] ?? '';
$initials = strtoupper(substr($userName, 0, 1));

// Leaderboard Logic (Calculate Civic Points)
try {
    $pipeline = [
        ['$group' => [
            '_id' => '$user_id',
            'total' => ['$sum' => 1],
            'resolved' => [
                '$sum' => ['$cond' => [['$eq' => ['$status', 'Resolved']], 1, 0]]
            ]
        ]],
        ['$addFields' => [
            'points' => ['$add' => [['$multiply' => ['$total', 10]], ['$multiply' => ['$resolved', 15]]]]
        ]],
        ['$sort' => ['points' => -1]],
        ['$limit' => 5]
    ];
    $leaderboardStats = $complaints->aggregate($pipeline);
    $leaderboard = [];
    $userPoints = 0;
    
    foreach ($leaderboardStats as $stat) {
        if (!$stat['_id']) continue;
        $u = $db->getCollection('users')->findOne(['_id' => new MongoDB\BSON\ObjectId($stat['_id'])]);
        if ($u) {
            $leaderboard[] = [
                'name' => $u['name'],
                'photo' => $u['photo'] ?? '',
                'points' => $stat['points'],
                'resolved' => $stat['resolved']
            ];
        }
        if ((string)$stat['_id'] === $userId) {
            $userPoints = $stat['points'];
        }
    }
    
    // Fallback if current user is not in top 5
    if ($userPoints === 0) {
        $userPoints = ($totalComplaints * 10) + ($resolvedComplaints * 15);
    }
} catch (Exception $e) {
    $leaderboard = [];
    $userPoints = ($totalComplaints * 10) + ($resolvedComplaints * 15);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Dashboard — ReportMyCity India National Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .hover-row:hover {
            background-color: rgba(0, 0, 0, 0.02);
            transition: background-color 0.2s ease;
        }
        .hover-row:hover .btn-outline {
            background-color: var(--primary);
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="gov-animation-layer">
        <div class="gov-blob blob-navy"></div>
        <div class="gov-blob blob-gold"></div>
        <div class="gov-grid"></div>
    </div>

    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="sidebar-brand-inner">
                    <img src="../assets/images/govt_emblem.png" alt="Emblem" class="sidebar-emblem">
                    <div class="sidebar-brand-text">
                        <h2>ReportMyCity India</h2>
                        <span>Citizen Portal</span>
                    </div>
                </div>
            </div>
            <div class="sidebar-gold-stripe"></div>
            <nav class="sidebar-nav">
                <div class="sidebar-section-label">Navigation</div>
                <a href="dashboard.php" class="active">
                    <span class="nav-icon"><i class="la la-bar-chart-o"></i></span> Dashboard
                </a>
                <a href="leaderboard.php">
                    <span class="nav-icon"><i class="la la-trophy"></i></span> Civic Leaderboard
                </a>
                <a href="submit_complaint.php">
                    <span class="nav-icon"><i class="la la-edit"></i></span> Submit Complaint
                </a>
                <a href="my_complaints.php">
                    <span class="nav-icon"><i class="la la-list-alt"></i></span> My Complaints
                </a>
                <a href="profile.php">
                    <span class="nav-icon"><i class="la la-user-o"></i></span> My Profile
                </a>
                <div class="sidebar-section-label" style="margin-top:1.5rem; color:#ef4444;"><i class="la la-shield"></i> Oversight</div>
                <a href="my_complaints.php" style="color:#ef4444; background: rgba(239, 68, 68, 0.05); border: 1px dashed rgba(239, 68, 68, 0.2);">
                    <span class="nav-icon"><i class="la la-flag-o"></i></span> Report Officer Conduct
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="sidebar-user-info">
                    <div class="sidebar-user-avatar">
                        <?php if ($userPhoto): ?>
                            <img src="../<?php echo htmlspecialchars($userPhoto); ?>" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                        <?php else: ?>
                            <?php echo $initials; ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="sidebar-user-name"><?php echo htmlspecialchars($userName); ?></div>
                        <div class="sidebar-user-role">Citizen</div>
                    </div>
                </div>
                <a href="../logout.php">
                    <i class="la la-sign-out"></i> Logout
                </a>
            </div>
        </aside>

        <!-- Main -->
        <main class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                                <div class="header-left">
                    <button class="sidebar-toggle" id="menu-toggle"><i class="la la-bars"></i></button>
                    <div class="header-logo-group">
                        <img src="../assets/images/govt_emblem.png" alt="Emblem">
                        <span>ReportMyCity India</span>
                    </div>
                    <div>
                        <h1><i class="la la-th-large"></i> My Dashboard</h1>
                        <div class="breadcrumb">
                            <a href="dashboard.php">Home</a>
                            <span>›</span>
                            <span>Citizen Dashboard</span>
                        </div>
                    </div>
                </div>

                <div class="user-info">
                    <span style="font-size:0.82rem; color:var(--text-muted);"><?php echo date('d M Y'); ?></span>
                    <span>Welcome, <?php echo htmlspecialchars($userName); ?></span>
                    <!-- Profile Dropdown -->
                    <div class="profile-dropdown-wrapper" id="profileDropdownWrapper">
                        <div class="user-avatar">
                            <?php if ($userPhoto): ?>
                                <img src="../<?php echo htmlspecialchars($userPhoto); ?>" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                            <?php else: ?>
                                <?php echo $initials; ?>
                            <?php endif; ?>
                        </div>
                        <div class="profile-dropdown-menu">
                            <div class="profile-dropdown-header">
                                <strong><?php echo htmlspecialchars($userName); ?></strong>
                                <span><?php echo htmlspecialchars($userEmail); ?></span>
                            </div>
                            <a href="profile.php">
                                <div class="dropdown-icon"><i class="la la-cog"></i></div> Profile Settings
                            </a>
                            <a href="../logout.php" class="dropdown-logout">
                                <div class="dropdown-icon"><i class="la la-sign-out"></i></div> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
                <?php if (isset($_SESSION['needs_password_notification'])): ?>
                <div class="alert alert-warning animate-slide-down" style="margin-bottom: 2rem; border-left: 4px solid #f59e0b; background: #fffbeb; display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <span style="font-size: 1.5rem;">⚠️</span>
                        <div>
                            <strong style="color: #92400e;">Security Recommendation</strong>
                            <p style="margin: 0; color: #b45309; font-size: 0.9rem;">You logged in via Google. Please <a href="profile.php" style="font-weight: 600; text-decoration: underline;">create a password</a> in your profile to enable direct login later.</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            <!-- Profile Card -->
            <div class="profile-card">
                <div class="profile-avatar">
                    <?php if ($userPhoto): ?>
                        <img src="../<?php echo htmlspecialchars($userPhoto); ?>" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                    <?php else: ?>
                        <?php echo $initials; ?>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <h3><?php echo htmlspecialchars($userName); ?></h3>
                    <p><?php echo htmlspecialchars($userEmail); ?></p>
                    <div class="profile-meta">
                        <span><i class="la la-list-alt"></i> <?php echo $totalComplaints; ?> complaints filed</span>
                        <span><i class="la la-check-square-o"></i> <?php echo $resolvedComplaints; ?> resolved</span>
                        <span style="background: var(--gov-gold-glow); color: var(--gov-gold); padding: 4px 10px; border-radius: 20px; font-weight: 700; border: 1px solid var(--gov-gold);">🎖️ <?php echo $userPoints; ?> Civic Points</span>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card purple animate-card">
                    <div class="stat-icon"><i class="la la-file-alt"></i></div>
                    <div class="stat-value"><?php echo $totalComplaints; ?></div>
                    <div class="stat-label">Total Complaints</div>
                </div>
                <div class="stat-card orange animate-card">
                    <div class="stat-icon"><i class="la la-clock"></i></div>
                    <div class="stat-value"><?php echo $pendingComplaints; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card cyan animate-card">
                    <div class="stat-icon"><i class="la la-sync"></i></div>
                    <div class="stat-value"><?php echo $progressComplaints; ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
                <div class="stat-card green animate-card">
                    <div class="stat-icon"><i class="la la-check-square"></i></div>
                    <div class="stat-value"><?php echo $resolvedComplaints; ?></div>
                    <div class="stat-label">Resolved</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="dashboard-widgets" style="margin-bottom: 2rem;">
                <div class="quick-actions" style="margin-bottom: 0;">
                    <a href="submit_complaint.php" class="quick-action-card">
                        <span class="action-icon"><i class="la la-edit"></i></span>
                        <span class="action-label">New Complaint</span>
                    </a>
                    <a href="my_complaints.php" class="quick-action-card">
                        <span class="action-icon"><i class="la la-list-alt"></i></span>
                        <span class="action-label">View All Complaints</span>
                    </a>
                    <a href="my_complaints.php?status=Pending" class="quick-action-card">
                        <span class="action-icon"><i class="la la-clock"></i></span>
                        <span class="action-label">Pending Issues</span>
                    </a>
                    <a href="my_complaints.php?status=Resolved" class="quick-action-card">
                        <span class="action-icon"><i class="la la-check-square"></i></span>
                        <span class="action-label">Resolved Issues</span>
                    </a>
                </div>
            </div>

            <!-- Recent Complaints & Leaderboard -->
            <div class="dashboard-main-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-top: 2rem;">
                <!-- Left: Recent Complaints -->
                <div class="card" style="margin-top: 0;">
                    <div class="card-header">
                        <h3>Recent Complaints</h3>
                        <a href="my_complaints.php" class="btn btn-outline btn-sm">View All →</a>
                    </div>
                <?php
                $recentArr = iterator_to_array($recentComplaints);
                if (empty($recentArr)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="la la-folder-open"></i></div>
                        <p>You haven't filed any complaints yet.<br><a href="submit_complaint.php">Submit your first complaint →</a></p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentArr as $c): 
                                    $cId = (string)$c['_id'];
                                ?>
                                <tr onclick="viewComplaint('<?php echo $cId; ?>')" style="cursor: pointer;" class="hover-row">
                                    <td style="color: var(--text-primary); font-weight: 500;">
                                        <?php echo htmlspecialchars($c['title']); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($c['category']); ?>
                                        <?php if (!empty($c['subcategory'])): ?>
                                            <br><small style="color:var(--primary); font-weight: 500; font-size: 0.72rem;"><?php echo htmlspecialchars($c['subcategory']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($c['date'] ?? $c['created_at']); ?></td>
                                    <td>
                                        <?php
                                        $status = $c['status'] ?? 'Pending';
                                        $badgeClass = 'badge-pending';
                                        if ($status === 'In Progress' || $status === 'Officer Completed') $badgeClass = 'badge-progress';
                                        elseif ($status === 'Resolved') $badgeClass = 'badge-resolved';
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                                    </td>
                                    <td>
                                        <button class="btn btn-outline btn-sm" onclick="event.stopPropagation(); viewComplaint('<?php echo $cId; ?>')"><i class="la la-eye"></i> View</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                </div>

                <!-- Right: Leaderboard -->
                <div class="card leaderboard-card" style="margin-top: 0; background: linear-gradient(to bottom, #ffffff, #f8faff); border: 1px solid var(--gov-gold); box-shadow: var(--shadow-gold);">
                    <div class="card-header" style="border-bottom: 2px solid var(--gov-gold-pale);">
                        <h3 style="color: var(--gov-gold); display: flex; align-items: center; gap: 10px;"><i class="la la-trophy"></i> Civic Heroes</h3>
                        <span style="font-size: 0.75rem; background: var(--gov-gold); color: white; padding: 2px 8px; border-radius: 10px;">TOP 5</span>
                    </div>
                    <div class="leaderboard-list">
                        <?php if (empty($leaderboard)): ?>
                            <p style="text-align: center; color: var(--text-muted); padding: 2rem;">Participate to become the first hero!</p>
                        <?php else: 
                            $ranks = ['🥇', '🥈', '🥉', '4.', '5.'];
                            foreach ($leaderboard as $index => $hero): ?>
                            <div class="leaderboard-item" style="display: flex; align-items: center; gap: 15px; padding: 1rem; border-bottom: 1px solid rgba(0,0,0,0.05); <?php echo ($hero['name'] === $userName) ? 'background: var(--gov-gold-glow);' : ''; ?>">
                                <div class="hero-rank" style="font-size: 1.2rem; min-width: 25px;"><?php echo $ranks[$index]; ?></div>
                                <div class="hero-avatar" style="width: 40px; height: 40px; border-radius: 50%; background: var(--border); overflow: hidden; border: 2px solid var(--gov-gold-pale);">
                                    <?php if ($hero['photo']): ?>
                                        <img src="../<?php echo htmlspecialchars($hero['photo']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-weight: 700; background: #e2e8f0; color: #475569;"><?php echo strtoupper(substr($hero['name'], 0, 1)); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="hero-info" style="flex: 1;">
                                    <div class="hero-name" style="font-weight: 700; font-size: 0.9rem; color: var(--gov-navy);"><?php echo htmlspecialchars($hero['name']); ?></div>
                                    <div class="hero-stat" style="font-size: 0.72rem; color: var(--text-muted);"><?php echo $hero['resolved']; ?> verified solutions</div>
                                </div>
                                <div class="hero-score" style="text-align: right;">
                                    <div style="font-weight: 800; color: var(--gov-gold); font-size: 1rem;"><?php echo $hero['points']; ?></div>
                                    <div style="font-size: 0.6rem; color: var(--text-muted); text-transform: uppercase;">pts</div>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                    <div class="leaderboard-footer" style="padding: 1rem; text-align: center; font-size: 0.78rem; border-top: 1px solid var(--gov-gold-pale);">
                        <p style="margin: 0; color: var(--text-muted);">Points: <i class="la la-lightbulb"></i> 10 per Report | <i class="la la-check-square"></i> 15 per Solution</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- View Complaint Modal -->
    <div class="modal-overlay" id="viewModal">
        <div class="modal" style="width: 100%; max-width: 750px; padding: 2.5rem; border-radius: var(--radius-lg);">
            <div class="modal-header" style="border-bottom: 2px solid var(--border); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.6rem; font-weight: 800; color: var(--gov-navy);"><i class="la la-clipboard-list"></i> Task & Issue Details</h3>
                <button class="modal-close" style="font-size: 1.8rem;" onclick="closeModal('viewModal')">&times;</button>
            </div>
            <div class="modal-body" id="viewContent"></div>
        </div>
    </div>

    <!-- Report Officer Modal -->
    <div class="modal-overlay" id="reportOfficerModal">
        <div class="modal" style="max-width: 550px;">
            <div class="modal-header">
                <h3><i class="la la-flag"></i> Report Officer Conduct</h3>
                <button class="modal-close" onclick="closeModal('reportOfficerModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 1.5rem; color: var(--text-secondary); line-height: 1.5;">If you feel that the assigned officer is not handling your complaint properly, you can report them to the higher administration.</p>
                <form id="reportOfficerForm">
                    <input type="hidden" name="complaint_id" id="report-complaint-id">
                    <div style="background: rgba(10,37,88,0.05); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <span id="report-officer-name" style="font-weight: 700; color: var(--gov-navy);"><i class="la la-shield-alt"></i> Officer Name</span>
                    </div>

                    <div class="form-group">
                        <label for="report_description" style="font-weight: 700; color: var(--gov-navy);">Reason for Report</label>
                        <textarea name="report_description" id="report_description" required placeholder="Describe the issue with the officer's handling..." style="min-height: 120px; width: 100%; border: 1px solid #ddd; padding: 10px; border-radius: 6px;"></textarea>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 2rem;">
                        <button type="button" class="btn btn-outline btn-block" onclick="closeModal('reportOfficerModal')">Cancel</button>
                        <button type="submit" class="btn btn-block" style="background: #dc2626; color: white;">Submit Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        // Data for recent complaints (using safer JSON encoding)
        const complaintsData = <?php echo json_encode(array_map(function($c) {
            return [
                '_id'                   => (string) $c['_id'],
                'title'                 => (string) $c['title'],
                'category'              => (string) $c['category'],
                'subcategory'           => (string) ($c['subcategory'] ?? ''),
                'description'           => (string) ($c['description'] ?? ''),
                'location'              => (string) ($c['location'] ?? ''),
                'image'                 => (string) ($c['image'] ?? ''),
                'officer_proof_image'   => (string) ($c['officer_proof_image'] ?? ''),
                'date'                  => (string) ($c['date'] ?? $c['created_at']),
                'risk_type'             => (string) ($c['risk_type'] ?? 'Medium'),
                'status'                => (string) $c['status'],
                'admin_reply'           => (string) ($c['admin_reply'] ?? ''),
                'officer_notes'         => (string) ($c['officer_notes'] ?? ''),
                'assigned_officer_name' => (string) ($c['assigned_officer_name'] ?? ''),
                'assigned_timestamp'    => (string) ($c['assigned_timestamp'] ?? ''),
                'in_progress_timestamp' => (string) ($c['in_progress_timestamp'] ?? ''),
                'resolved_timestamp'    => (string) ($c['resolved_timestamp'] ?? ''),
            ];
        }, $recentArr), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

        // Sidebar toggle and profile dropdown are now handled by main.js standard listeners.

        function viewComplaint(id) {
            const c = complaintsData.find(item => item._id === id);
            if (!c) return;

            let progress = 0;
            let step1 = 'active', step2 = '', step3 = '', step4 = '';
            
            if (c.status === 'Resolved') {
                progress = 100;
                step1 = 'completed'; step2 = 'completed'; step3 = 'completed'; step4 = 'completed';
            } else if (c.status === 'In Progress' || c.status === 'Officer Completed') {
                progress = 66;
                step1 = 'completed'; step2 = 'completed'; step3 = 'active';
            } else if (c.assigned_officer_name) {
                progress = 33;
                step1 = 'completed'; step2 = 'active';
            } else {
                progress = 0;
                step1 = 'active';
            }

            let html = `
                <div class="tracking-wrapper">
                    <div class="stepper">
                        <div class="stepper-progress" id="tracker-bar"></div>
                        <div class="step-item ${step1}">
                            <div class="step-circle"><i class="la la-file-alt"></i></div>
                            <div class="step-label" style="font-size:0.75rem;">Submitted</div>
                            <div style="font-size:0.65rem; color:var(--text-muted); margin-top:0.4rem;">${c.date}</div>
                        </div>
                        <div class="step-item ${step2}">
                            <div class="step-circle"><i class="la la-user-circle"></i></div>
                            <div class="step-label" style="font-size:0.75rem;">Assigned</div>
                            <div style="font-size:0.65rem; color:var(--text-muted); margin-top:0.4rem;">${c.assigned_timestamp || '—'}</div>
                        </div>
                        <div class="step-item ${step3}">
                            <div class="step-circle"><i class="la la-cogs"></i></div>
                            <div class="step-label" style="font-size:0.75rem;">In Progress</div>
                            <div style="font-size:0.65rem; color:var(--text-muted); margin-top:0.4rem;">${c.in_progress_timestamp || '—'}</div>
                        </div>
                        <div class="step-item ${step4}">
                            <div class="step-circle"><i class="la la-check-square"></i></div>
                            <div class="step-label" style="font-size:0.75rem;">Resolved</div>
                            <div style="font-size:0.65rem; color:var(--text-muted); margin-top:0.4rem;">${c.resolved_timestamp || '—'}</div>
                        </div>
                    </div>
                </div>

                <div class="complaint-detail-grid" style="margin-top: 2rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; background: var(--bg-card); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border);">
                    <div class="detail-item"><label style="color:var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">Tracking ID</label><p style="font-size: 1.1rem; font-weight: 600; color: var(--text-primary); margin-top: 0.2rem;">#${c._id.substr(-6)}</p></div>
                    <div class="detail-item"><label style="color:var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">Category</label><p style="font-size: 1rem; font-weight: 500; color: var(--text-primary); margin-top: 0.2rem;">${c.category} ${c.subcategory ? '<br><small style="color:var(--primary);">' + c.subcategory + '</small>' : ''}</p></div>
                    <div class="detail-item"><label style="color:var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">Status</label><p style="font-size: 1rem; font-weight: 500; color: var(--text-primary); margin-top: 0.2rem;">${c.status}</p></div>
                    <div class="detail-item"><label style="color:var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">Date Reported</label><p style="font-size: 1rem; font-weight: 500; color: var(--text-primary); margin-top: 0.2rem;">${c.date}</p></div>
                </div>
                
                <div style="margin-top:1.5rem; background: var(--bg-card); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border); border-left: 4px solid var(--gov-navy);">
                    <label style="display:block;font-size:0.8rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;margin-bottom:0.5rem;">Your Original Report</label>
                    <p style="color:var(--text-primary);font-size:1rem; line-height: 1.6;">${c.description}</p>
                 </div>
            `;

            if (c.officer_notes) {
                html += `<div style="margin-top:1rem; background: #fffbeb; padding: 1.2rem; border-radius: var(--radius-md); border: 1px solid #fef3c7;">
                            <label style="display:block;font-size:0.8rem;color: #92400e;font-weight:700;text-transform:uppercase;margin-bottom:0.4rem;">Officer Field Remarks</label>
                            <p style="color:#b45309;font-size:1rem;">${c.officer_notes}</p>
                         </div>`;
            }

            if (c.admin_reply) {
                html += `<div style="margin-top:1rem; background: rgba(10, 37, 88, 0.03); padding: 1.2rem; border-radius: var(--radius-md); border: 1px solid var(--border);">
                            <label style="display:block;font-size:0.8rem;color:var(--gov-navy);font-weight:700;text-transform:uppercase;margin-bottom:0.4rem;">Official Response</label>
                            <p style="color:var(--text-primary);font-size:1rem;">${c.admin_reply}</p>
                         </div>`;
            }

            if (c.image || c.officer_proof_image) {
                html += `<div style="margin-top:1.5rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">`;
                if (c.image) {
                    html += `<div><label style="display:block;font-size:0.75rem;color:var(--text-muted);margin-bottom:0.5rem;">Your Submission</label><img src="../${c.image}" style="width:100%; border-radius:8px; border:1px solid var(--border);"></div>`;
                }
                if (c.officer_proof_image) {
                    html += `<div><label style="display:block;font-size:0.75rem;color:var(--success);margin-bottom:0.5rem;">Officer's Proof</label><img src="../${c.officer_proof_image}" style="width:100%; border-radius:8px; border:1px solid var(--border);"></div>`;
                }
                html += `</div>`;
            }

            if (c.assigned_officer_name && c.status !== 'Resolved') {
                html += `
                    <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end;">
                        <button class="btn btn-sm" onclick="openReportOfficerModal('${c._id}', '${c.assigned_officer_name}')" style="background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <i class="la la-flag"></i> Report Officer Conduct
                        </button>
                    </div>
                `;
            }

            document.getElementById('viewContent').innerHTML = html;
            openModal('viewModal');

            setTimeout(() => {
                const bar = document.getElementById('tracker-bar');
                if (bar) bar.style.width = progress + '%';
            }, 300);
        }

        // Logic for Reporting Officer
        function openReportOfficerModal(id, officerName) {
            document.getElementById('report-complaint-id').value = id;
            document.getElementById('report-officer-name').innerHTML = '<i class="la la-shield-alt"></i> ' + officerName;
            closeModal('viewModal');
            openModal('reportOfficerModal');
        }

        const reportForm = document.getElementById('reportOfficerForm');
        if (reportForm) {
            reportForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                fetch('../api/submit_officer_complaint.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            alert('Your report has been submitted to the administration.');
                            closeModal('reportOfficerModal');
                        } else {
                            alert('Error: ' + data.message);
                        }
                    });
            });
        }
    </script>
    <!-- jQuery for Animations -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Background Parallax Animation (Blobs)
            $(document).mousemove(function(e) {
                const x = (e.clientX / window.innerWidth - 0.5) * 40;
                const y = (e.clientY / window.innerHeight - 0.5) * 40;
                
                // Move Blobs in opposite directions for depth
                $('.blob-navy').css('transform', `translate(${x}px, ${y}px)`);
                $('.blob-gold').css('transform', `translate(${-x}px, ${-y}px)`);
                
                // Tilt effect disabled to avoid click conflicts
                // const tiltX = (e.clientY / window.innerHeight - 0.5) * 2;
                // const tiltY = (e.clientX / window.innerWidth - 0.5) * -2;
                // $('.dashboard-layout').css('transform', `perspective(1000px) rotateX(${tiltX}deg) rotateY(${tiltY}deg)`);
            });
        });
    </script>
</body>
</html>