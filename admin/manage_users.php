<?php
/**
 * ReportMyCity — Admin: Manage Users
 */
session_start();
$allowedAdminRoles = ['admin', 'national_admin', 'state_admin', 'district_admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowedAdminRoles)) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';
use MongoDB\BSON\ObjectId;

$db = Database::getInstance();
$usersCol = $db->getCollection('users');
$complaintsCol = $db->getCollection('complaints');

// Regional Filtering Logic
$role = $_SESSION['role'];
$filter = [];
if ($role === 'state_admin') {
    $filter = ['state' => $_SESSION['state']];
} elseif ($role === 'district_admin') {
    $filter = ['state' => $_SESSION['state'], 'district' => $_SESSION['district']];
}

// Additional Filters from GET
if ($role === 'national_admin' || $role === 'admin') {
    if (!empty($_GET['state_filter'])) {
        $filter['state'] = $_GET['state_filter'];
    }
    if (!empty($_GET['district_filter'])) {
        $filter['district'] = $_GET['district_filter'];
    }
}

// Get all users
$allUsers = $usersCol->find($filter, ['sort' => ['created_at' => -1]]);
$usersList = iterator_to_array($allUsers);

// Pre-fetch distinct states/districts for filters (only for National Admin)
$statesList = [];
$districtsList = [];
if ($role === 'national_admin' || $role === 'admin') {
    $statesList = $usersCol->distinct('state');
    if (!empty($filter['state'])) {
        $districtsList = $usersCol->distinct('district', ['state' => $filter['state']]);
    } else {
        $districtsList = $usersCol->distinct('district');
    }
}

// Get complaint counts per user
$userComplaintCounts = [];
$counts = $complaintsCol->aggregate([
    ['$group' => ['_id' => '$user_id', 'count' => ['$sum' => 1]]]
]);
foreach ($counts as $c) {
    if ($c['_id']) $userComplaintCounts[(string)$c['_id']] = $c['count'];
}

$adminName = $_SESSION['user_name'] ?? 'Admin';
$adminEmail = $_SESSION['user_email'] ?? 'admin@reportmycity.gov';
$initials = strtoupper(substr($adminName, 0, 1));

// Fetch pending counts for sidebar notification badges
$officerReportsCount = $db->getCollection('officer_reports')->countDocuments(['status' => 'Pending Admin Review']);
$userReportsCount = $db->getCollection('user_reports')->countDocuments(['status' => 'Audit Requested']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | ReportMyCity Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-theme">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main -->
        <main class="main-content">

            <div class="page-header">
                                <div class="header-left">
                    <button class="sidebar-toggle"><i class="la la-bars"></i></button>
                    <div class="header-logo-group">
                        <img src="../assets/images/govt_emblem.png" alt="Emblem">
                        <span>ReportMyCity</span>
                    </div>
                    <h1>Manage Users</h1>
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

            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-success"><i class="la la-check-square-o"></i> User deleted successfully.</div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error"><i class="la la-times"></i> <?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3><i class="la la-users"></i> Registered Users (<?php echo count($usersList); ?>)</h3>
                </div>

                <!-- Toolbar -->
                <div class="toolbar" style="display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap;">
                    <div class="search-box" style="flex:1; min-width:250px;">
                        <input type="text" id="search-input" placeholder="Search citizens by name, email or phone...">
                    </div>
                    
                    <?php if ($role === 'national_admin' || $role === 'admin'): ?>
                    <form method="GET" style="display:flex; gap:0.5rem; align-items:center;">
                        <select name="state_filter" onchange="this.form.submit()" class="filter-select" style="padding:0.6rem; border-radius:5px; border:1px solid var(--border);">
                            <option value="">All States</option>
                            <?php foreach ($statesList as $s): ?>
                                <option value="<?php echo htmlspecialchars($s); ?>" <?php echo ($_GET['state_filter'] ?? '') === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="district_filter" onchange="this.form.submit()" class="filter-select" style="padding:0.6rem; border-radius:5px; border:1px solid var(--border);">
                            <option value="">All Districts</option>
                            <?php foreach ($districtsList as $d): ?>
                                <option value="<?php echo htmlspecialchars($d); ?>" <?php echo ($_GET['district_filter'] ?? '') === $d ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <a href="manage_users.php" class="btn btn-outline btn-sm" style="padding:0.6rem;">Reset</a>
                    </form>
                    <?php endif; ?>
                </div>

                <?php if (empty($usersList)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="la la-user-o"></i></div>
                        <p>No users registered yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table id="users-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Complaints</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usersList as $u):
                                    $uid = (string) $u['_id'];
                                    $complaintCount = $userComplaintCounts[$uid] ?? 0;
                                ?>
                                <tr id="user-row-<?php echo $uid; ?>">
                                    <td style="color: var(--text-primary); font-weight: 500;">
                                        <div style="display:flex;align-items:center;gap:0.65rem;">
                                            <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:0.82rem;flex-shrink:0;">
                                                <?php echo strtoupper(substr($u['name'], 0, 1)); ?>
                                            </div>
                                            <?php echo htmlspecialchars($u['name']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td><?php echo htmlspecialchars($u['phone'] ?? '—'); ?></td>
                                    <td>
                                        <?php if ($complaintCount > 0): ?>
                                            <a href="manage_complaints.php?user_id=<?php echo $uid; ?>" style="color:var(--primary-light);font-weight:600;"><?php echo $complaintCount; ?> complaints</a>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($u['created_at'] ?? '—'); ?></td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn btn-outline btn-sm" onclick="viewUserComplaints('<?php echo $uid; ?>')"><i class="la la-list-alt"></i> Complaints</button>
                                            <?php if ($role !== 'district_admin'): ?>
                                                <button type="button" class="btn btn-danger btn-sm" onclick="deleteUser('<?php echo $uid; ?>', '<?php echo htmlspecialchars($u['name']); ?>')"><i class="la la-trash-o"></i> Delete</button>
                                            <?php else: ?>
                                                <span class="badge" style="background:var(--bg-input); color:var(--text-muted);">View Only</span>
                                            <?php endif; ?>
                                        </div>
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

    <!-- User Complaints Modal -->
    <div class="modal-overlay" id="userComplaintsModal">
        <div class="modal" style="max-width: 700px;">
            <div class="modal-header">
                <h3>User Complaints</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div id="userComplaintsContent">
                <div class="empty-state">
                    <div class="empty-icon"><i class="la la-clock-o"></i></div>
                    <p>Fetching complaints...</p>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        initTableSearch('search-input', 'users-table');

        async function deleteUser(id, name) {
            if (!confirm(`Are you sure you want to delete user "${name}"? This will NOT delete their complaints, but they will be orphaned.`)) return;
            
            const result = await postJSON('../api/delete_user.php', { user_id: id });
            if (result.success) {
                showToast(result.message, 'success');
                const row = document.getElementById('user-row-' + id);
                if (row) row.remove();
            } else {
                showToast(result.message, 'error');
            }
        }

        async function viewUserComplaints(userId) {
            const container = document.getElementById('userComplaintsContent');
            container.innerHTML = '<div class="empty-state"><div class="empty-icon"><i class="la la-clock-o"></i></div><p>Fetching complaints...</p></div>';
            openModal('userComplaintsModal');

            try {
                const res = await fetch(`../api/get_complaints.php?user_id=${userId}`);
                const data = await res.json();

                if (data.success && data.complaints.length > 0) {
                    let html = '<div class="table-wrapper"><table><thead><tr><th>Title</th><th>Status</th><th>Date</th></tr></thead><tbody>';
                    data.complaints.forEach(c => {
                        html += `<tr>
                            <td>${c.title}</td>
                            <td><span class="badge badge-pending">${c.status}</span></td>
                            <td>${c.date}</td>
                        </tr>`;
                    });
                    html += '</tbody></table></div>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<div class="empty-state"><div class="empty-icon"><i class="la la-folder-open"></i></div><p>No complaints found for this user.</p></div>';
                }
            } catch (e) {
                container.innerHTML = '<div class="alert alert-error">Failed to load complaints.</div>';
            }
        }
    </script>
    <script>
        // Profile Dropdown
        const pdw = document.getElementById('profileDropdownWrapper');
        if (pdw) {
            pdw.addEventListener('click', function(e) { e.stopPropagation(); this.classList.toggle('open'); });
            document.addEventListener('click', () => pdw.classList.remove('open'));
        }
    </script>
</body>
</html>
