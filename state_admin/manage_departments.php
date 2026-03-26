<?php
/**
 * ReportMyCity India — State Admin: Manage Departments
 */
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'state_admin') {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$departmentsCol = $db->getCollection('departments');
$headOfficersCol = $db->getCollection('head_officers');
$state = $_SESSION['state'];

$standardDepartments = [
    "Vigilance", "Cyber Cell", "Public Works (PWD)", "Water Department", 
    "Electricity Board", "Health Department", "Police Department", 
    "Municipal Services", "Education Department", "Transport Department", 
    "Revenue Dept", "Environment Board", "Women & Child Dev", 
    "Social Welfare", "General Administration"
];

// Handle Add Department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    if ($name) {
        $departmentsCol->insertOne([
            'name' => $name,
            'state' => $state,
            'head_id' => null, // Initially no head
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $_SESSION['msg'] = "Department '$name' created successfully.";
    }
    header("Location: manage_departments.php");
    exit;
}

// Handle Assign Head
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_head') {
    $deptId = $_POST['dept_id'];
    $headId = $_POST['head_id'];
    try {
        $departmentsCol->updateOne(
            ['_id' => new \MongoDB\BSON\ObjectId($deptId)],
            ['$set' => ['head_id' => $headId]]
        );
        $_SESSION['msg'] = "Department Head assigned successfully.";
    } catch (Exception $e) {}
    header("Location: manage_departments.php");
    exit;
}

$departments = iterator_to_array($departmentsCol->find(['state' => $state]));
$seniorOfficers = iterator_to_array($headOfficersCol->find(['state' => $state]));
$officerLookup = [];
foreach ($seniorOfficers as $so) {
    $officerLookup[(string)$so['_id']] = $so['name'];
}

$adminName = $_SESSION['user_name'] ?? 'Admin';
$initials = strtoupper(substr($adminName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments | ReportMyCity India</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
            position: relative;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 1rem;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
        }
    </style>
</head>
<body class="admin-theme">
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="sidebar-brand-inner">
                    <img src="../assets/images/govt_emblem.png" alt="Emblem" class="sidebar-emblem">
                    <div class="sidebar-brand-text">
                        <h2>ReportMyCity India</h2>
                        <span>State Admin Console</span>
                    </div>
                </div>
            </div>
            <div class="sidebar-gold-stripe"></div>
            <nav class="sidebar-nav">
                <div class="sidebar-section-label">State Console</div>
                <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="la la-bar-chart-o"></i></span> State Stats
                </a>
                <a href="manage_complaints.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_complaints.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="la la-list-alt"></i></span> Complaints
                </a>
                <a href="manage_departments.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_departments.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="la la-building-o"></i></span> Manage Departments
                </a>
                <a href="manage_officers.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_officers.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="la la-shield"></i></span> Manage Dept Heads
                </a>
                <a href="manage_users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="la la-users"></i></span> Manage Citizens
                </a>
                
                <div class="sidebar-section-label" style="margin-top:1.5rem;">Intelligence</div>
                <a href="heatmap.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'heatmap.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="la la-map"></i></span> State Heatmap
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="sidebar-user-info">
                    <div class="sidebar-user-avatar"><?php echo $initials; ?></div>
                    <div>
                        <div class="sidebar-user-name"><?php echo htmlspecialchars($adminName); ?></div>
                        <div class="sidebar-user-role">State Administrator</div>
                    </div>
                </div>
                <a href="../logout.php">
                    <i class="la la-sign-out"></i> Logout
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <div class="header-left">
                    <button class="sidebar-toggle"><i class="la la-bars"></i></button>
                    <div class="header-logo-group">
                        <img src="../assets/images/govt_emblem.png" alt="Emblem">
                        <span>ReportMyCity India</span>
                    </div>
                    <h1><i class="la la-building-o"></i> Manage Departments (<?php echo htmlspecialchars($state); ?>)</h1>
                </div>
                <div class="user-info">
                    <span style="font-size:0.82rem; color:var(--text-muted);"><?php echo date('d M Y'); ?></span>
                    <span>Welcome, <?php echo htmlspecialchars($adminName); ?></span>
                </div>
            </div>

            <?php if (isset($_SESSION['msg'])): ?>
                <div class="alert alert-success" style="margin-bottom: 2rem; padding: 1rem; background: #d1fae5; color: #065f46; border-radius: 8px;">
                    <?php echo $_SESSION['msg']; unset($_SESSION['msg']); ?>
                </div>
            <?php endif; ?>

            <div class="grid" style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">
                <!-- Add Department -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="la la-plus"></i> Create New Department</h3>
                    </div>
                    <form method="POST" style="padding: 1.5rem;">
                        <input type="hidden" name="action" value="add">
                        <div class="form-group">
                            <label>Select Department Category</label>
                            <select name="name" required style="width: 100%; padding: 0.8rem; border: 1px solid var(--border); border-radius: 8px; background: white;">
                                <option value="">-- Choose Department --</option>
                                <?php foreach ($standardDepartments as $deptName): ?>
                                    <option value="<?php echo htmlspecialchars($deptName); ?>"><?php echo htmlspecialchars($deptName); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Create Department</button>
                    </form>
                </div>

                <!-- Department List -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="la la-list"></i> Active Departments</h3>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Department Name</th>
                                    <th>Department Head</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($dept['name']); ?></td>
                                    <td>
                                        <?php if ($dept['head_id']): ?>
                                            <span class="badge badge-resolved"><?php echo htmlspecialchars($officerLookup[$dept['head_id']] ?? 'Unknown'); ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-pending">No Head Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                     <td>
                                        <button class="btn btn-primary btn-sm" onclick="showAddHeadModal('<?php echo (string)$dept['_id']; ?>', '<?php echo htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8'); ?>')">
                                            <i class="la la-user-plus"></i> Add Head Officer
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Head Officer Modal -->
    <div id="addHeadModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="la la-user-md"></i> Add Department Head</h3>
                <button class="modal-close" onclick="hideAddHeadModal()">&times;</button>
            </div>
            <p id="modalDeptInfo" style="margin-bottom: 1rem; color: var(--primary); font-weight: 500;"></p>
            <form id="addHeadForm">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="role" value="senior_officer">
                <input type="hidden" name="state" value="<?php echo htmlspecialchars($state); ?>">
                <input type="hidden" id="modalDeptName" name="department">
                <input type="hidden" id="modalDeptId" name="assign_dept_id">
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" required placeholder="e.g. Dr. Rajesh Kumar" style="width: 100%; padding: 0.7rem; margin-bottom: 1rem; border: 1px solid var(--border); border-radius: 8px;">
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="head@reportmycity.gov" style="width: 100%; padding: 0.7rem; margin-bottom: 1rem; border: 1px solid var(--border); border-radius: 8px;">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" placeholder="+91 9876543210" style="width: 100%; padding: 0.7rem; margin-bottom: 1rem; border: 1px solid var(--border); border-radius: 8px;">
                </div>
                <div class="form-group">
                    <label>Login Password</label>
                    <input type="password" name="password" required placeholder="Minimum 6 characters" style="width: 100%; padding: 0.7rem; margin-bottom: 1rem; border: 1px solid var(--border); border-radius: 8px;">
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-outline" style="flex: 1;" onclick="hideAddHeadModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex: 2;">Create & Assign Head</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        function showAddHeadModal(deptId, deptName) {
            document.getElementById('modalDeptId').value = deptId;
            document.getElementById('modalDeptName').value = deptName;
            document.getElementById('modalDeptInfo').innerText = "Assigning to: " + deptName;
            document.getElementById('addHeadModal').classList.add('active');
        }

        function hideAddHeadModal() {
            document.getElementById('addHeadModal').classList.remove('active');
        }

        document.getElementById('addHeadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            try {
                const response = await fetch('../api/manage_officers.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert(result.message || 'Error occurred while adding officer.');
                }
            } catch (err) {
                alert('Network error. Please try again.');
            }
        });

        // Close modal on click outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('addHeadModal');
            if (event.target == modal) {
                hideAddHeadModal();
            }
        });
    </script>
</body>
</html>
