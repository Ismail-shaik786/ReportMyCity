<?php
/**
 * ReportMyCity — Department Head Oversight
 */
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'senior_officer') {
    header('Location: ../officer/officer_login.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$officerId = $_SESSION['user_id'];

$headOfficersCol = $db->getCollection('head_officers');
$officerDoc = $headOfficersCol->findOne(['_id' => new MongoDB\BSON\ObjectId($officerId)]);
$department = $officerDoc['department'] ?? 'Department Head';
$officerPhoto = $officerDoc['photo'] ?? '';
$officerName = $_SESSION['user_name'] ?? 'Senior Officer';
$initials = strtoupper(substr($officerName, 0, 1));
$myState = $officerDoc['state'] ?? '';

// 1. Fetch reports AGAINST my team officers
$officerReportsCol = $db->getCollection('officer_reports');
$teamReports = iterator_to_array($officerReportsCol->find([
    'head_officer_id' => $officerId,
    'status' => 'Pending Head Review'
], ['sort' => ['created_at' => -1]]));

// 2. Fetch reports BY my team AGAINST citizens
$userReportsCol = $db->getCollection('user_reports');
$citizenAudits = iterator_to_array($userReportsCol->find([
    'head_officer_id' => $officerId,
    'status' => 'Audit Pending Head Review'
], ['sort' => ['created_at' => -1]]));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Oversight | <?php echo htmlspecialchars($department); ?> Head</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/main.js" defer></script>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="sidebar-brand-inner">
                    <img src="../assets/images/govt_emblem.png" alt="Emblem" class="sidebar-emblem">
                    <div class="sidebar-brand-text">
                        <h2>ReportMyCity</h2>
                        <span>Head Console</span>
                    </div>
                </div>
            </div>
            <div class="sidebar-gold-stripe"></div>
            <nav class="sidebar-nav">
                <div class="sidebar-section-label">Navigation</div>
                <a href="dashboard.php"><span class="nav-icon"><i class="la la-bar-chart-o"></i></span> Overview</a>
                <a href="my_department.php"><span class="nav-icon"><i class="la la-list-alt"></i></span> Complaints</a>
                <a href="manage_team.php"><span class="nav-icon"><i class="la la-users"></i></span> Team</a>
                <div class="sidebar-section-label" style="margin-top:1.5rem; color:#ef4444;"><i class="la la-shield"></i> Oversight</div>
                <a href="manage_oversight.php" class="active" style="color:#ef4444; background: rgba(239, 68, 68, 0.05); border: 1px dashed rgba(239, 68, 68, 0.2);">
                    <span class="nav-icon"><i class="la la-shield"></i></span> Team Oversight
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
                    <h1><span class="bordered-icon"><i class="la la-shield"></i></span> Professional Oversight & Audits</h1>
                </div>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($officerName); ?></span>
                </div>
            </div>

            <div class="page-body">
                <!-- Section 1: Member Conduct -->
                <div class="card" style="margin-bottom: 2rem;">
                    <div class="card-header" style="background: #fdf2f2;">
                        <h3 style="color: #991b1b;"><i class="la la-user-secret"></i> Citizen Reports Against Your Team</h3>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Field Officer</th>
                                    <th>Citizen</th>
                                    <th>Complaint ID</th>
                                    <th>Issue Reported</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($teamReports)): ?>
                                    <tr><td colspan="5" style="text-align:center;">No pending conduct reports.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($teamReports as $r): ?>
                                    <tr>
                                        <td style="font-weight:700; color:var(--text-primary);"><?php echo htmlspecialchars($r['officer_name'] ?? 'Officer'); ?></td>
                                        <td><?php echo htmlspecialchars($r['user_name'] ?? 'Citizen'); ?></td>
                                        <td>#<?php echo substr((string)$r['complaint_id'], -6); ?></td>
                                        <td style="max-width:300px; font-size:0.85rem; color: #666;"><?php echo htmlspecialchars($r['report_description']); ?></td>
                                        <td>
                                            <div style="display:flex; gap:0.5rem;">
                                                <button class="btn btn-warning btn-sm" onclick="oversightAction('officer', '<?php echo (string)$r['_id']; ?>', 'warn')">Warn Officer</button>
                                                <button class="btn btn-outline btn-sm" onclick="oversightAction('officer', '<?php echo (string)$r['_id']; ?>', 'dismiss')">Dismiss</button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Section 2: Citizen Audits -->
                <div class="card">
                    <div class="card-header" style="background: #f0fdf4;">
                        <h3 style="color: #166534;"><i class="la la-flag"></i> Citizen Audits (Reported by Team)</h3>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Reporting Officer</th>
                                    <th>Citizen Name</th>
                                    <th>Reason / Proof</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($citizenAudits)): ?>
                                    <tr><td colspan="5" style="text-align:center;">No pending citizen audit requests.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($citizenAudits as $a): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($a['officer_name']); ?></td>
                                        <td style="font-weight:700; color:var(--text-primary);"><?php echo htmlspecialchars($a['reported_user_id']); ?></td>
                                        <td>
                                            <div style="font-size:0.82rem; margin-bottom:0.4rem;"><?php echo htmlspecialchars($a['report_reason']); ?></div>
                                            <a href="../<?php echo htmlspecialchars($a['proof_photo']); ?>" target="_blank" style="font-size:0.75rem; color:var(--primary);"><i class="la la-image"></i> View Evidence</a>
                                        </td>
                                        <td><span class="badge badge-pending">Reviewing</span></td>
                                        <td>
                                            <button class="btn btn-danger btn-sm" onclick="oversightAction('citizen', '<?php echo (string)$a['_id']; ?>', 'appeal')">
                                                <i class="la la-gavel"></i> Appeal to State Admin to Block
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    async function oversightAction(type, reportId, action) {
        if (!confirm('Are you sure you want to perform this action?')) return;
        
        try {
            const res = await fetch('../api/update_oversight_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type, reportId, action })
            });
            const data = await res.json();
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert(data.error || 'Failed to update status');
            }
        } catch (err) {
            alert('Network error');
        }
    }
    </script>
</body>
</html>
