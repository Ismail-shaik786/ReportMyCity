<?php
/**
 * ReportMyCity — Manage User Reports (Admin)
 * Filed by Officers for fake/improper complaints
 */
session_start();
$allowedAdminRoles = ['admin', 'national_admin', 'state_admin', 'district_admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowedAdminRoles)) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance();
$reportsCol = $db->getCollection('user_reports');
$usersCol = $db->getCollection('users');

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

// Lookup user names
$userIds = array_unique(array_map(fn($r) => $r['reported_user_id'] ?? '', $reportsArr));
$userLookup = [];
if (!empty($userIds)) {
    $validIds = array_filter($userIds, fn($id) => !empty($id) && preg_match('/^[a-f\d]{24}$/i', $id));
    if (!empty($validIds)) {
        $foundUsers = $usersCol->find(['_id' => ['$in' => array_map(fn($id) => new \MongoDB\BSON\ObjectId($id), array_values($validIds)) ] ]);
        foreach ($foundUsers as $u) {
            $userLookup[(string)$u['_id']] = $u['name'];
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
    <title>Citizen Misconduct Reports | Admin</title>
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
                        <h1><i class="la la-flag-o"></i> Audit: Fake Complaints</h1>
                        <div class="breadcrumb">
                            <a href="admin_dashboard.php">Home</a>
                            <span>›</span>
                            <span>Fake Complaints</span>
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
                    <h3>Reports against Citizens (Fake Complaints)</h3>
                </div>
                <?php if (empty($reportsArr)): ?>
                    <div class="card-body">
                        <div class="empty-state">
                            <div class="empty-icon"><i class="la la-flag-o"></i></div>
                            <p>No reports against citizens found.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Reporting Officer</th>
                                    <th>Target Citizen</th>
                                    <th>Evidence Comparison</th>
                                    <th>Officer Findings</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportsArr as $r): ?>
                                <tr>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:0.5rem;">
                                            <div style="width:30px;height:30px;border-radius:50%;background:var(--warning);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.8rem;color:var(--gov-navy);">
                                                <?php echo strtoupper(substr($r['officer_name'], 0, 1)); ?>
                                            </div>
                                            <strong><?php echo htmlspecialchars($r['officer_name']); ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="manage_users.php?search=<?php echo urlencode($userLookup[(string)$r['reported_user_id']] ?? ''); ?>" 
                                           title="Manage this citizen"
                                           style="color: var(--gov-navy); font-weight: 700; text-decoration: underline;">
                                            <?php echo htmlspecialchars($userLookup[(string)$r['reported_user_id']] ?? 'Unknown Citizen'); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:0.5rem; align-items:center;">
                                            <div style="text-align:center;">
                                                <div style="font-size:0.65rem; color:var(--text-muted); margin-bottom:2px;">Citizen</div>
                                                <?php if (!empty($r['original_photo'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($r['original_photo']); ?>" alt="User Evidence" style="width:50px; height:50px; border-radius:4px; object-fit:cover; border:1px solid var(--border); transition:transform 0.2s;" onmouseover="this.style.transform='scale(3)'" onmouseout="this.style.transform='scale(1)'">
                                                <?php else: ?>
                                                    <div style="width:50px; height:50px; background:#f0f0f0; border-radius:4px; display:flex; align-items:center; justify-content:center; font-size:0.6rem; color:#999;">No Image</div>
                                                <?php endif; ?>
                                            </div>
                                            <div style="color:var(--primary); font-weight:bold;">VS</div>
                                            <div style="text-align:center;">
                                                <div style="font-size:0.65rem; color:var(--warning); margin-bottom:2px;">Officer</div>
                                                <?php if (!empty($r['proof_photo'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($r['proof_photo']); ?>" alt="Officer Proof" style="width:50px; height:50px; border-radius:4px; object-fit:cover; border:1px solid var(--warning); transition:transform 0.2s;" onmouseover="this.style.transform='scale(3)'" onmouseout="this.style.transform='scale(1)'">
                                                <?php else: ?>
                                                    <div style="width:50px; height:50px; background:#f0f0f0; border-radius:4px; display:flex; align-items:center; justify-content:center; font-size:0.6rem; color:#999;">No Image</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="max-width: 250px; font-size: 0.85rem; color: var(--text-secondary);">
                                        <strong style="display:block; color:var(--text-primary); margin-bottom:0.25rem;">
                                            <?php echo htmlspecialchars($r['complaint_title'] ?? 'Complaint'); ?>
                                        </strong>
                                        <?php echo htmlspecialchars($r['report_reason']); ?>
                                    </td>
                                    <td style="font-size: 0.85rem; white-space: nowrap;">
                                        <?php echo date('d M Y', strtotime($r['created_at'])); ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $s = $r['status'] ?? 'Audit Requested'; 
                                        $badgeStyle = "background: var(--warning); color: var(--gov-navy);";
                                        if ($s === 'Appealed to State Admin') {
                                            $badgeStyle = "background: #ef4444; color: white; border: 1px solid #991b1b;";
                                        } elseif ($s === 'Dismissed' || $s === 'Resolved & Dismissed') {
                                            $badgeStyle = "background: #f3f4f6; color: #6b7280;";
                                        } elseif ($s === 'Block User') {
                                            $badgeStyle = "background: #111; color: #fff;";
                                        }
                                        ?>
                                        <span class="badge" style="<?php echo $badgeStyle; ?>">
                                            <?php echo htmlspecialchars($s); ?>
                                        </span>
                                        <?php if (!empty($r['appeal_by_head'])): ?>
                                            <div style="font-size:0.65rem; color:#991b1b; margin-top:4px; font-weight:700;">
                                                <i class="la la-exclamation-triangle"></i> Escalated by <?php echo htmlspecialchars($r['appeal_by_head']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                     <td>
                                        <?php if ($s === 'Audit Requested' || $s === 'Appealed to State Admin'): ?>
                                            <?php if ($role !== 'district_admin'): ?>
                                                <div style="display: flex; gap: 5px;">
                                                    <button class="btn btn-sm" style="background: #10b981; color: white;" onclick="updateReportStatus('<?php echo (string)$r['_id']; ?>', 'Resolved & Dismissed')"><i class="la la-check-square-o"></i> Dismiss</button>
                                                    <button class="btn btn-sm" style="background: #ef4444; color: white;" onclick="updateReportStatus('<?php echo (string)$r['_id']; ?>', 'Block User')">🚫 Block Citizen</button>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge" style="background:var(--bg-input); color:var(--text-muted);">Awaiting State Action</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 0.8rem;">Reviewed</span>
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
            if (!confirm(`Are you sure you want to mark this audit report as ${status}?`)) return;

            const formData = new FormData();
            formData.append('report_id', reportId);
            formData.append('type', 'user');
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
            if (!confirm(`Are you sure you want to mark this audit report as ${status}?`)) return;

            const formData = new FormData();
            formData.append('report_id', reportId);
            formData.append('type', 'user');
            formData.append('status', status);

            fetch('../api/update_report_status.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) { showToast(data.message, 'success'); setTimeout(() => location.reload(), 1000); }
                    else { showToast('Error: ' + data.message, 'error'); }
                })
                .catch(() => showToast('A network error occurred.', 'error'));
        }
    </script>
</body>
</html>
