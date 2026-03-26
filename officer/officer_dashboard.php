<?php
/**
 * ReportMyCity — Officer Dashboard
 */
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['officer', 'local_officer'])) {
    header('Location: officer_login.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$complaintsCol = $db->getCollection('complaints');
$officerId = $_SESSION['user_id'];

// Stats for this officer
$assignedTotal   = $complaintsCol->countDocuments(['assigned_officer_id' => $officerId]);
$assignedPending = $complaintsCol->countDocuments(['assigned_officer_id' => $officerId, 'status' => 'Pending']);
$assignedProgress = $complaintsCol->countDocuments(['assigned_officer_id' => $officerId, 'status' => 'In Progress']);
$assignedResolved = $complaintsCol->countDocuments(['assigned_officer_id' => $officerId, 'status' => 'Resolved']);

// Recent assigned complaints
$recentComplaints = $complaintsCol->find(
    ['assigned_officer_id' => $officerId],
    ['sort' => ['created_at' => -1], 'limit' => 8]
);
$recentArr = iterator_to_array($recentComplaints);

$officerName = $_SESSION['user_name'] ?? 'Officer';
$officerEmail = $_SESSION['user_email'] ?? '';
$initials = strtoupper(substr($officerName, 0, 1));
$officerDoc = $db->getCollection('officers')->findOne(['_id' => new MongoDB\BSON\ObjectId($officerId)]);
$officerPhoto = $officerDoc['photo'] ?? '';

// Data for Bar Graph: Workflow over last 7 days
$days = [];
$counts = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $days[] = date('D', strtotime($date));
    
    // We count assignments created on each day
    // Note: In a production app, you might track 'resolution_date' for workflow
    $count = $complaintsCol->countDocuments([
        'assigned_officer_id' => $officerId,
        'created_at' => ['$regex' => "^$date"]
    ]);
    $counts[] = $count;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Dashboard — ReportMyCity Field Operations</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <span>Field Officer Portal</span>
                    </div>
                </div>
            </div>
            <div class="sidebar-gold-stripe"></div>
            <nav class="sidebar-nav">
                <div class="sidebar-section-label">Navigation</div>
                <a href="officer_dashboard.php" class="active">
                    <span class="nav-icon"><i class="la la-bar-chart-o"></i></span> Dashboard
                </a>
                <a href="my_assignments.php">
                    <span class="nav-icon"><i class="la la-list-alt"></i></span> My Assignments
                </a>
                <a href="profile.php">
                    <span class="nav-icon"><i class="la la-user-o"></i></span> My Profile
                </a>
                <div class="sidebar-section-label" style="margin-top:1.5rem; color:#ef4444;"><i class="la la-shield"></i> Oversight</div>
                <a href="my_assignments.php" style="color:#ef4444; background: rgba(239, 68, 68, 0.05); border: 1px dashed rgba(239, 68, 68, 0.2);">
                    <span class="nav-icon"><i class="la la-flag-o"></i></span> Flag Improper User
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
                        <div class="sidebar-user-role">Field Officer</div>
                    </div>
                </div>
                <a href="../logout.php">
                    <i class="la la-sign-out"></i> Logout
                </a>
            </div>
        </aside>

        <!-- Main -->
        <main class="main-content">
            <div class="page-header">
                                <div class="header-left">
                    <button class="sidebar-toggle"><i class="la la-bars"></i></button>
                    <div class="header-logo-group">
                        <img src="../assets/images/govt_emblem.png" alt="Emblem">
                        <span>ReportMyCity India</span>
                    </div>
                    <div>
                        <h1><i class="la la-bar-chart-o"></i> Officer Dashboard</h1>
                        <div class="breadcrumb">
                            <a href="officer_dashboard.php">Home</a>
                            <span>›</span>
                            <span>Dashboard</span>
                        </div>
                    </div>
                </div>
                <div class="user-info">
                    <span style="font-size:0.82rem; color:var(--text-muted);"><?php echo date('d M Y'); ?></span>
                    <span><?php echo htmlspecialchars($officerName); ?></span>
                    <!-- Profile Dropdown -->
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
                                <span><?php echo htmlspecialchars($officerEmail); ?></span>
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
            <div class="page-body">

            <!-- Profile Card -->
            <div class="profile-card">
                <div class="profile-avatar">
                    <?php if ($officerPhoto): ?>
                        <img src="../<?php echo htmlspecialchars($officerPhoto); ?>" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                    <?php else: ?>
                        <?php echo $initials; ?>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <h3><?php echo htmlspecialchars($officerName); ?></h3>
                    <p><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></p>
                    <div class="profile-meta">
                        <span><i class="la la-list-alt"></i> <?php echo $assignedTotal; ?> assigned</span>
                        <span><i class="la la-check-square-o"></i> <?php echo $assignedResolved; ?> resolved</span>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <!-- Charts Grid replaces Stats -->
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); margin-bottom: 2rem;">
                <!-- Workflow Bar Chart -->
                <div class="card" style="padding: 1.5rem; display: flex; flex-direction: column; align-items: center;">
                    <h3 style="margin-bottom: 1rem; font-size: 1rem; color: var(--text-primary);"><i class="la la-line-chart"></i> Weekly Workflow</h3>
                    <div style="width: 100%; height: 220px;">
                        <canvas id="workflowBarChart"></canvas>
                    </div>
                </div>

                <!-- Status Distribution Pie Chart -->
                <div class="card" style="padding: 1.5rem; display: flex; flex-direction: column; align-items: center;">
                    <h3 style="margin-bottom: 1rem; font-size: 1rem; color: var(--text-primary);"><i class="la la-bar-chart-o"></i> Complaint Status</h3>
                    <div style="width: 100%; height: 220px;">
                        <canvas id="statusPieChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="dashboard-widgets" style="margin-bottom: 2rem;">
                <div class="quick-actions" style="margin-bottom: 0;">
                    <a href="my_assignments.php" class="quick-action-card">
                        <span class="action-icon"><i class="la la-list-alt"></i></span>
                        <span class="action-label">All Assignments</span>
                    </a>
                    <a href="my_assignments.php?status=Pending" class="quick-action-card">
                        <span class="action-icon"><i class="la la-clock-o"></i></span>
                        <span class="action-label">Pending (<?php echo $assignedPending; ?>)</span>
                    </a>
                    <a href="my_assignments.php?status=In Progress" class="quick-action-card">
                        <span class="action-icon"><i class="la la-refresh"></i></span>
                        <span class="action-label">In Progress (<?php echo $assignedProgress; ?>)</span>
                    </a>
                    <a href="my_assignments.php?status=Resolved" class="quick-action-card">
                        <span class="action-icon"><i class="la la-check-square-o"></i></span>
                        <span class="action-label">Resolved (<?php echo $assignedResolved; ?>)</span>
                    </a>
                </div>
            </div>

            <!-- Recent Assignments -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="la la-list-alt"></i> Recent Assignments</h3>
                    <a href="my_assignments.php" class="btn btn-outline btn-sm">View All →</a>
                </div>
                <?php if (empty($recentArr)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="la la-folder-open-o"></i></div>
                        <p>No complaints assigned to you yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Location</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentArr as $c): ?>
                                <tr>
                                    <td style="color: var(--text-primary); font-weight: 500;"><?php echo htmlspecialchars($c['title']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($c['category']); ?>
                                        <?php if (!empty($c['subcategory'])): ?>
                                            <br><small style="color:var(--primary); font-weight: 500; font-size: 0.72rem;"><?php echo htmlspecialchars($c['subcategory']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($c['location'] ?? ''); ?></td>
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
            </div><!-- /.page-body -->
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        // Sidebar toggle and profile dropdown are now handled by main.js standard listeners.
        document.addEventListener('DOMContentLoaded', function() {
            const chartOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            boxWidth: 10,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: { size: 10 }
                        }
                    }
                }
            };

            // Workflow Bar Chart
            new Chart(document.getElementById('workflowBarChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($days); ?>,
                    datasets: [{
                        label: 'New Assignments',
                        data: <?php echo json_encode($counts); ?>,
                        backgroundColor: '#8b5cf6',
                        borderRadius: 5,
                        maxBarThickness: 30
                    }]
                },
                options: {
                    ...chartOptions,
                    plugins: { ...chartOptions.plugins, legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });

            // Status Distribution Pie Chart
            new Chart(document.getElementById('statusPieChart').getContext('2d'), {
                type: 'pie',
                data: {
                    labels: ['Pending', 'In Progress', 'Resolved'],
                    datasets: [{
                        data: [<?php echo $assignedPending; ?>, <?php echo $assignedProgress; ?>, <?php echo $assignedResolved; ?>],
                        backgroundColor: ['#f59e0b', '#0ea5e9', '#10b981'],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: chartOptions
            });
        });
    </script>
</body>
</html>
