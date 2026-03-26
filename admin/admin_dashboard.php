<?php
/**
 * ReportMyCity — Admin Dashboard
 */
session_start();
$allowedAdminRoles = ['admin', 'national_admin', 'state_admin', 'district_admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowedAdminRoles)) {
    header('Location: ../login.php');
    exit;
}

// Redirect to specialized dashboards if applicable
if ($_SESSION['role'] === 'national_admin') {
    header('Location: dashboard.php');
    exit;
} elseif ($_SESSION['role'] === 'state_admin') {
    header('Location: ../state_admin/dashboard.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$complaintsCol = $db->getCollection('complaints');
$usersCol = $db->getCollection('users');
$officersCol = $db->getCollection('officers');

// Regional Filtering Logic
$role = $_SESSION['role'];
$baseFilter = [];
if ($role === 'state_admin') {
    $baseFilter = ['state' => $_SESSION['state']];
} elseif ($role === 'district_admin') {
    $baseFilter = ['state' => $_SESSION['state'], 'district' => $_SESSION['district']];
}

// Stats Calculation
$totalComplaints = $complaintsCol->countDocuments($baseFilter);
$pendingCount = $complaintsCol->countDocuments(array_merge($baseFilter, ['status' => ['$in' => ['Pending', 'Submitted', 'Assigned', 'Under Review']]]));
$progressCount = $complaintsCol->countDocuments(array_merge($baseFilter, ['status' => ['$in' => ['In Progress', 'Escalated']]]));
$resolvedCount = $complaintsCol->countDocuments(array_merge($baseFilter, ['status' => ['$in' => ['Resolved', 'Closed', 'Officer Completed']]]));

$totalUsers = $usersCol->countDocuments($baseFilter);
$totalOfficers = $officersCol->countDocuments($baseFilter);
$officerReportsCount = $db->getCollection('officer_reports')->countDocuments($baseFilter); // Assuming region field exists in these
$userReportsCount = $db->getCollection('user_reports')->countDocuments($baseFilter);

// Recent Complaints
$recentComplaints = $complaintsCol->find($baseFilter, ['limit' => 5, 'sort' => ['created_at' => -1]]);
$recentArr = iterator_to_array($recentComplaints);

// User Lookup for recent complaints
$userIds = array_unique(array_map(fn($c) => $c['user_id'] ?? '', $recentArr));
$userLookup = [];
$validUserIds = array_filter($userIds, fn($id) => !empty($id)); // Filter out empty IDs
if (!empty($validUserIds)) {
    $foundUsers = $usersCol->find(['_id' => ['$in' => array_map(fn($id) => new \MongoDB\BSON\ObjectId($id), array_values($validUserIds))]]);
    foreach ($foundUsers as $u) {
        $userLookup[(string)$u['_id']] = $u['name'];
    }
}

$adminName = $_SESSION['user_name'] ?? 'Admin';
$adminEmail = $_SESSION['user_email'] ?? 'admin@reportmycity.gov';
$initials = strtoupper(substr($adminName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | ReportMyCity India</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="admin-theme">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">

            <!-- Page Header -->
            <div class="page-header">
                                <div class="header-left">
                    <button class="sidebar-toggle"><i class="la la-bars"></i></button>
                    <div class="header-logo-group">
                        <img src="../assets/images/govt_emblem.png" alt="Emblem">
                        <span>ReportMyCity India</span>
                    </div>
                    <div>
                        <h1><i class="la la-bar-chart-o"></i> Admin Dashboard</h1>
                        <div class="breadcrumb">
                            <a href="admin_dashboard.php">Home</a>
                            <span>›</span>
                            <span>Dashboard Overview</span>
                        </div>
                    </div>
                </div>
                <div class="user-info">
                    <span style="font-size:0.82rem; color:var(--text-muted);"><?php echo date('d M Y'); ?></span>
                    <span>Welcome, <?php echo htmlspecialchars($adminName); ?></span>
                    <!-- Profile Dropdown -->
                    <div class="profile-dropdown-wrapper" id="profileDropdownWrapper">
                        <div class="user-avatar">
                            <?php echo $initials; ?>
                        </div>
                        <div class="profile-dropdown-menu">
                            <div class="profile-dropdown-header">
                                <strong><?php echo htmlspecialchars($adminName); ?></strong>
                                <span><?php echo htmlspecialchars($adminEmail); ?></span>
                            </div>
                            <a href="../logout.php" class="dropdown-logout">
                                <div class="dropdown-icon"><i class="la la-sign-out"></i></div> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile/Welcome Banner -->
            <div class="profile-card">
                <div class="profile-avatar"><?php echo $initials; ?></div>
                <div class="profile-info">
                    <h3><?php echo htmlspecialchars($adminName); ?> — <?php echo ($role === 'national_admin') ? 'Super Admin' : (($role === 'state_admin') ? 'State Admin' : 'Administrator'); ?></h3>
                    <p>System Management &amp; Oversight · ReportMyCity Administration Console</p>
                    <div class="profile-meta">
                        <span><i class="la la-landmark"></i> Admin Panel</span>
                        <span><i class="la la-calendar"></i> <?php echo date('d M Y, H:i'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <!-- Charts Grid replaces Stats Grid -->
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); margin-bottom: 2rem;">
                <!-- User Distribution Chart -->
                <div class="card" style="padding: 1.5rem; display: flex; flex-direction: column; align-items: center;">
                    <h3 style="margin-bottom: 1rem; font-size: 1rem; color: var(--text-primary);"><i class="la la-users"></i> Workforce Distribution</h3>
                    <div style="width: 100%; height: 220px;">
                        <canvas id="userDistChart"></canvas>
                    </div>
                    <div style="margin-top: 1rem; font-size: 0.85rem; color: var(--text-muted);">
                         Total Participants: <strong><?php echo ($totalUsers + $totalOfficers); ?></strong>
                    </div>
                </div>

                <!-- Complaint Breakdown Chart -->
                <div class="card" style="padding: 1.5rem; display: flex; flex-direction: column; align-items: center;">
                    <h3 style="margin-bottom: 1rem; font-size: 1rem; color: var(--text-primary);"><i class="la la-list-alt"></i> Complaint Lifecycle</h3>
                    <div style="width: 100%; height: 220px;">
                        <canvas id="complaintStatusChart"></canvas>
                    </div>
                    <div style="margin-top: 1rem; font-size: 0.85rem; color: var(--text-muted);">
                         Total Reports: <strong><?php echo $totalComplaints; ?></strong>
                    </div>
                </div>

                <!-- NEW: Audit & Reports Mini-Stats -->
                <div class="card" style="padding: 1.5rem; display: flex; flex-direction: column;">
                    <h3 style="margin-bottom: 1.25rem; font-size: 1rem; color: var(--text-primary);"><i class="la la-shield"></i> Oversight & Audits</h3>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <a href="manage_officer_reports.php" style="text-decoration:none; display:flex; justify-content:space-between; align-items:center; padding: 0.8rem; background: #fef2f2; border-radius: 8px; border: 1px solid #fee2e2;">
                            <div style="display:flex; align-items:center; gap: 0.8rem;">
                                <span style="font-size: 1.2rem;"><i class="la la-shield"></i></span>
                                <div style="display:flex; flex-direction:column;">
                                    <span style="font-size:0.85rem; font-weight:700; color:#991b1b;">Officer Conduct</span>
                                    <span style="font-size:0.7rem; color:#b91c1c;">Pending Review</span>
                                </div>
                            </div>
                            <span style="font-size: 1.2rem; font-weight: 800; color: #991b1b;"><?php echo $officerReportsCount; ?></span>
                        </a>

                        <a href="manage_user_reports.php" style="text-decoration:none; display:flex; justify-content:space-between; align-items:center; padding: 0.8rem; background: #fffbeb; border-radius: 8px; border: 1px solid #fef3c7;">
                            <div style="display:flex; align-items:center; gap: 0.8rem;">
                                <span style="font-size: 1.2rem;"><i class="la la-flag-o"></i></span>
                                <div style="display:flex; flex-direction:column;">
                                    <span style="font-size:0.85rem; font-weight:700; color:#92400e;">Fake Complaints</span>
                                    <span style="font-size:0.7rem; color:#b45309;">System Audits</span>
                                </div>
                            </div>
                            <span style="font-size: 1.2rem; font-weight: 800; color: #92400e;"><?php echo $userReportsCount; ?></span>
                        </a>
                        
                        <div style="margin-top:0.5rem; font-size: 0.75rem; color: var(--text-muted); text-align: center; border-top: 1px solid var(--border); padding-top: 0.8rem;">
                            High-integrity operations active
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions & Illustration -->
            <!-- Quick Actions -->
            <div class="dashboard-widgets" style="margin-bottom: 1.75rem;">
                <div class="gov-section-heading">
                    <h2>Quick Actions</h2>
                </div>
                <div class="quick-actions" style="margin-bottom: 0;">
                    <a href="manage_complaints.php" class="quick-action-card">
                        <span class="action-icon"><i class="la la-list-alt"></i></span>
                        <span class="action-label">All Complaints</span>
                    </a>
                    <a href="manage_complaints.php?status=Pending" class="quick-action-card">
                        <span class="action-icon"><i class="la la-clock-o"></i></span>
                        <span class="action-label">Pending Cases</span>
                    </a>
                    <a href="manage_complaints.php?status=In Progress" class="quick-action-card">
                        <span class="action-icon"><i class="la la-refresh"></i></span>
                        <span class="action-label">In Progress</span>
                    </a>
                    <a href="manage_users.php" class="quick-action-card">
                        <span class="action-icon"><i class="la la-users"></i></span>
                        <span class="action-label">Manage Citizens</span>
                    </a>
                    <a href="manage_officers.php" class="quick-action-card">
                        <span class="action-icon"><i class="la la-shield"></i></span>
                        <span class="action-label">Manage Officers</span>
                    </a>
                    <a href="manage_officer_reports.php" class="quick-action-card">
                        <span class="action-icon"><i class="la la-shield"></i></span>
                        <span class="action-label">Officer Reports</span>
                    </a>
                    <a href="manage_user_reports.php" class="quick-action-card">
                        <span class="action-icon"><i class="la la-flag-o"></i></span>
                        <span class="action-label">Fake Complaints</span>
                    </a>
                    <a href="heatmap.php" class="quick-action-card">
                        <span class="action-icon"><i class="la la-map"></i></span>
                        <span class="action-label">Issue Heatmap</span>
                    </a>
                </div>
            </div>

            <!-- Recent Complaints Table -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="la la-list-alt"></i> Recent Complaints</h3>
                    <a href="manage_complaints.php" class="btn btn-outline btn-sm">View All →</a>
                </div>
                <?php if (empty($recentArr)): ?>
                    <div class="card-body">
                        <div class="empty-state">
                            <div class="empty-icon"><i class="la la-folder-open-o"></i></div>
                            <p>No complaints submitted yet.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Complaint Title</th>
                                    <th>Category</th>
                                    <th>Citizen</th>
                                    <th>Date Filed</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentArr as $c): ?>
                                <tr>
                                    <td style="color: var(--text-primary); font-weight: 600;"><?php echo htmlspecialchars($c['title']); ?></td>
                                    <td><?php echo htmlspecialchars($c['category']); ?></td>
                                    <td><?php echo htmlspecialchars($userLookup[$c['user_id'] ?? ''] ?? 'Unknown'); ?></td>
                                    <td><?php echo htmlspecialchars($c['date'] ?? $c['created_at']); ?></td>
                                    <td>
                                        <?php
                                        $status = $c['status'] ?? 'Pending';
                                        $bc = 'badge-pending';
                                        if ($status === 'In Progress') $bc = 'badge-progress';
                                        elseif ($status === 'Resolved') $bc = 'badge-resolved';
                                        ?>
                                        <span class="badge <?php echo $bc; ?>"><?php echo htmlspecialchars($status); ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chartOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 15,
                            font: { size: 11, family: 'Inter' }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 10,
                        titleFont: { size: 14 },
                        bodyFont: { size: 13 }
                    }
                },
                cutout: '65%'
            };

            // User Distribution Chart
            new Chart(document.getElementById('userDistChart').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Citizens', 'Officers'],
                    datasets: [{
                        data: [<?php echo $totalUsers; ?>, <?php echo $totalOfficers; ?>],
                        backgroundColor: ['#8b5cf6', '#06b6d4'],
                        borderWidth: 0,
                        hoverOffset: 10
                    }]
                },
                options: chartOptions
            });

            // Complaint Lifecycle Chart
            new Chart(document.getElementById('complaintStatusChart').getContext('2d'), {
                type: 'pie',
                data: {
                    labels: ['Pending', 'In Progress', 'Resolved'],
                    datasets: [{
                        data: [<?php echo $pendingCount; ?>, <?php echo $progressCount; ?>, <?php echo $resolvedCount; ?>],
                        backgroundColor: ['#f59e0b', '#0ea5e9', '#10b981'],
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverOffset: 15
                    }]
                },
                options: {
                    ...chartOptions,
                    cutout: '0%' // Full Pie for contrast
                }
            });
        });
    </script>
    <!-- Sidebar toggle and profile dropdown are now handled by main.js -->
</body>
</html>
