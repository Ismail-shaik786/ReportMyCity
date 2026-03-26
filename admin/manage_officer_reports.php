<?php
/**
 * ReportMyCity — Manage Officer Reports (Admin)
 */
session_start();
$allowedAdminRoles = ['admin', 'national_admin', 'state_admin', 'district_admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowedAdminRoles)) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance();
$reportsCol = $db->getCollection('officer_reports');
$officersCol = $db->getCollection('officers');

// Regional Filtering Logic
$role = $_SESSION['role'];
$filter = [];
if ($role === 'state_admin') {
    $filter = ['state' => $_SESSION['state']];
} elseif ($role === 'district_admin') {
    $filter = ['state' => $_SESSION['state'], 'district' => $_SESSION['district']];
}

// Fetch all reports
$reports = $reportsCol->find($filter, ['sort' => ['created_at' => -1]]);
$reportsArr = iterator_to_array($reports);

// Lookup officer names
$officerIds = array_unique(array_map(fn($r) => $r['officer_id'] ?? '', $reportsArr));
$officerLookup = [];
if (!empty($officerIds)) {
    $validIds = array_filter($officerIds, fn($id) => !empty($id) && preg_match('/^[a-f\d]{24}$/i', $id));
    if (!empty($validIds)) {
        $foundOfficers = $officersCol->find(['_id' => ['$in' => array_map(fn($id) => new \MongoDB\BSON\ObjectId($id), array_values($validIds)) ] ]);
        foreach ($foundOfficers as $o) {
            $officerLookup[(string)$o['_id']] = $o['name'];
        }
    }
}

$adminName = $_SESSION['user_name'] ?? 'Admin';
$adminEmail = $_SESSION['user_email'] ?? 'admin@reportmycity.gov';
$initials = strtoupper(substr($adminName, 0, 1));

// Fetch pending counts for sidebar notification badges
$officerReportsCount = $db->getCollection('officer_reports')->countDocuments(['status' => ['$in' => ['Pending Admin Review', 'Appealed to Admin']]]);
$userReportsCount = $db->getCollection('user_reports')->countDocuments(['status' => ['$in' => ['Audit Requested', 'Appealed to State Admin']]]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Conduct Reports | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-theme">
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="page-header">
                <div class="header-left">
                    <button class="sidebar-toggle"><i class="la la-bars"></i></button>
                    <div class="header-logo-group">
                        <img src="../assets/images/govt_emblem.png" alt="Emblem">
                        <span>ReportMyCity</span>
                    </div>
                    <div>
                        <h1><i class="la la-shield"></i> Officer Conduct Reports</h1>
                        <div class="breadcrumb">
                            <a href="admin_dashboard.php">Home</a>
                            <span>›</span>
                            <span>Officer Reports</span>
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

            <div class="card">
                <div class="card-header">
                    <h3>Reports against Officers</h3>
                </div>
                <?php if (empty($reportsArr)): ?>
                    <div class="card-body">
                        <div class="empty-state">
                            <div class="empty-icon"><i class="la la-shield"></i></div>
                            <p>No reports against officers found.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Citizen</th>
                                    <th>Reported Officer</th>
                                    <th>Related Complaint</th>
                                    <th>Reason / Description</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportsArr as $r): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($r['user_name'] ?? 'Citizen'); ?></strong>
                                    </td>
                                    <td>
                                        <span style="color: var(--gov-navy); font-weight: 600;">
                                            <?php echo htmlspecialchars($officerLookup[(string)$r['officer_id']] ?? 'Unknown Officer'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="manage_complaints.php?id=<?php echo $r['complaint_id']; ?>" style="color: var(--gov-navy); text-decoration: underline;">
                                            <?php echo htmlspecialchars($r['original_title'] ?? 'Complaint'); ?>
                                        </a>
                                    </td>
                                    <td style="max-width: 300px; font-size: 0.85rem; color: var(--text-secondary);">
                                        <?php echo htmlspecialchars($r['report_description']); ?>
                                    </td>
                                    <td style="font-size: 0.85rem; white-space: nowrap;">
                                        <?php echo date('d M Y', strtotime($r['created_at'])); ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $s = $r['status'] ?? 'Pending Review'; 
                                        $badgeClass = 'badge-pending';
                                        $extraText = '';
                                        if ($s === 'Pending Head Review') {
                                            $badgeClass = 'badge-progress';
                                            $extraText = '<div style="font-size:0.65rem; color:#991b1b; margin-top:4px;">Awaiting Dept Head</div>';
                                        } elseif ($s === 'Action Taken') {
                                            $badgeClass = 'badge-resolved';
                                        }
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>">
                                            <?php echo htmlspecialchars($s); ?>
                                        </span>
                                        <?php echo $extraText; ?>
                                    </td>
                                     <td>
                                        <?php if ($s === 'Pending Admin Review' || $s === 'Appealed to Admin'): ?>
                                            <?php if ($role !== 'district_admin'): ?>
                                                <div style="display: flex; gap: 5px;">
                                                    <button class="btn btn-sm" style="background: #10b981; color: white;" onclick="updateReportStatus('<?php echo (string)$r['_id']; ?>', 'Action Taken')">✔️ Resolve</button>
                                                    <button class="btn btn-sm" style="background: #ef4444; color: white;" onclick="updateReportStatus('<?php echo (string)$r['_id']; ?>', 'Dismissed')"><i class="la la-times"></i> Dismiss</button>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge" style="background:var(--bg-input); color:var(--text-muted);">Pending State Review</span>
                                            <?php endif; ?>
                                        <?php elseif ($s === 'Pending Head Review'): ?>
                                            <span class="badge" style="background: #fdf2f2; color: #991b1b; border: 1px dashed #991b1b;">Local Head Reviewing</span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 0.8rem;">Processed</span>
                                        <?php endif; ?>
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
    <script>
        function updateReportStatus(reportId, status) {
            if (!confirm(`Are you sure you want to mark this report as ${status}?`)) return;

            const formData = new FormData();
            formData.append('report_id', reportId);
            formData.append('type', 'officer');
            formData.append('status', status);

            fetch('../api/update_report_status.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert('An error occurred.');
            });
        }
    </script>
    <script src="../assets/js/main.js"></script>
    <script>
        // Note: sidebar toggle & profile dropdown handled by main.js
        function updateReportStatus(reportId, status) {
            if (!confirm(`Are you sure you want to set this report to: ${status}?`)) return;

            const formData = new FormData();
            formData.append('report_id', reportId);
            formData.append('type', 'officer');
            formData.append('status', status);

            fetch('../api/update_report_status.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) { showToast(data.message, 'success'); setTimeout(() => location.reload(), 1000); }
                    else { showToast('Error: ' + data.message, 'error'); }
                })
                .catch(() => showToast('A network error occurred.', 'error'));
        }
