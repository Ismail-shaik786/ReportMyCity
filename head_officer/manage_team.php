<?php
/**
 * ReportMyCity — Admin: Manage Officers
 */
session_start();
$allowedAdminRoles = ['admin', 'national_admin', 'state_admin', 'district_admin', 'senior_officer'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowedAdminRoles)) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$headOfficersCol  = $db->getCollection('head_officers');
$fieldOfficersCol = $db->getCollection('field_officers');
$complaintsCol    = $db->getCollection('complaints');

$indianStates = [
    "Andhra Pradesh", "Arunachal Pradesh", "Assam", "Bihar", "Chhattisgarh", "Goa", "Gujarat", "Haryana",
    "Himachal Pradesh", "Jharkhand", "Karnataka", "Kerala", "Madhya Pradesh", "Maharashtra", "Manipur",
    "Meghalaya", "Mizoram", "Nagaland", "Odisha", "Punjab", "Rajasthan", "Sikkim", "Tamil Nadu",
    "Telangana", "Tripura", "Uttar Pradesh", "Uttarakhand", "West Bengal",
    "Andaman & Nicobar", "Chandigarh", "Dadra & Nagar Haveli and Daman & Diu", "Delhi",
    "Jammu & Kashmir", "Ladakh", "Lakshadweep", "Puducherry"
];

// Regional Filtering Logic
$role = $_SESSION['role'];
$filter = [];
if ($role === 'state_admin') {
    $targetCol = $headOfficersCol;
    $filter = ['state' => $_SESSION['state'], 'role' => 'senior_officer'];
} elseif ($role === 'district_admin') {
    $targetCol = $headOfficersCol;
    $filter = ['state' => $_SESSION['state'], 'district' => $_SESSION['district'], 'role' => 'senior_officer'];
} elseif ($role === 'senior_officer') {
    $targetCol = $fieldOfficersCol;
    $officerId = $_SESSION['user_id'];
    $officerDoc = $headOfficersCol->findOne(['_id' => new MongoDB\BSON\ObjectId($officerId)]);
    $_SESSION['department'] = $officerDoc['department'] ?? 'General';
    $_SESSION['state']      = $officerDoc['state'] ?? 'National';
    $_SESSION['district']   = $officerDoc['district'] ?? 'All Districts';
    
    // Only show field officers created by this Head Officer
    $filter = ['created_by_id' => $officerId];
} else {
    // National Admin etc. can see all for now, default to field
    $targetCol = $fieldOfficersCol;
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

$allOfficers = $targetCol->find($filter, ['sort' => ['created_at' => -1]]);
$officersList = iterator_to_array($allOfficers);

$statesList = [];
$districtsList = [];
if ($role === 'national_admin' || $role === 'admin') {
    $statesList = $targetCol->distinct('state');
    if (!empty($filter['state'])) {
        $districtsList = $targetCol->distinct('district', ['state' => $filter['state']]);
    } else {
        $districtsList = $targetCol->distinct('district');
    }
}

// Get assignment counts per officer
$officerAssignmentCounts = [];
foreach ($officersList as $o) {
    $oid = (string) $o['_id'];
    $officerAssignmentCounts[$oid] = $complaintsCol->countDocuments(['assigned_officer_id' => $oid]);
}

// Get correctly tiered officer document for profile details
$officerDoc = null;
$oid = new MongoDB\BSON\ObjectId($_SESSION['user_id']);
if ($_SESSION['role'] === 'senior_officer') {
    $officerDoc = $headOfficersCol->findOne(['_id' => $oid]);
} else {
    // Other admins might be viewing this page (e.g. National Admin)
    $officerDoc = $db->getCollection('admins')->findOne(['_id' => $oid]) ?? $db->getCollection('state_admins')->findOne(['_id' => $oid]);
}

$adminName = $officerDoc['name'] ?? $_SESSION['user_name'] ?? 'Officer';
$adminEmail = $officerDoc['email'] ?? $_SESSION['user_email'] ?? 'officer@example.com';
$officerPhoto = $officerDoc['photo'] ?? $officerDoc['profile_photo'] ?? null;
$initials = strtoupper(substr($adminName, 0, 1));
$department = $officerDoc['department'] ?? 'General';

// Fetch pending counts for sidebar notification badges
$officerReportsCount = $db->getCollection('officer_reports')->countDocuments(['status' => 'Pending Admin Review']);
$userReportsCount = $db->getCollection('user_reports')->countDocuments(['status' => 'Audit Requested']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Officials | ReportMyCity India</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/main.js"></script>
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
                <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="la la-bar-chart-o"></i></span> Team Overview
                </a>
                <a href="my_department.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'my_department.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="la la-list-alt"></i></span> Dept. Complaints
                </a>
                <a href="manage_team.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_team.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="la la-users"></i></span> Manage Team
                </a>
                <a href="workforce_tree.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'workforce_tree.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="la la-sitemap"></i></span> Workforce Tree
                </a>
                <a href="heatmap.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'heatmap.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="la la-map-marker"></i></span> Heatmap
                </a>
                <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="la la-user-circle"></i></span> My Profile
                </a>
                <div class="sidebar-section-label" style="margin-top:1.5rem; color:#ef4444;"><i class="la la-shield"></i> Oversight</div>
                <a href="my_department.php?status=Audit%20Requested" style="color:#ef4444;">
                    <span class="nav-icon"><i class="la la-flag"></i></span> Conduct Audits
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
                        <div class="sidebar-user-name" id="sidebar-name"><?php echo htmlspecialchars($adminName); ?></div>
                        <div class="sidebar-user-role"><?php echo htmlspecialchars($department); ?> Head</div>
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
                    <h1>Manage Officials</h1>
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


                <!-- Add Officer Card (Only for National, State Admins, and Senior Officers) -->
                <?php if ($role === 'national_admin' || $role === 'admin' || $role === 'state_admin' || $role === 'senior_officer'): ?>
                <div class="card">
                <div class="card-header">
                    <h3><i class="la la-plus-square-o"></i> Add New Official</h3>
                </div>
                <form id="addOfficerForm" method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="officer-name">Full Name</label>
                            <input type="text" id="officer-name" name="name" placeholder="Officer Name" required>
                        </div>
                        <div class="form-group">
                            <label for="officer-email">Email</label>
                            <input type="email" id="officer-email" name="email" placeholder="officer@example.com" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="officer-phone">Phone</label>
                            <input type="tel" id="officer-phone" name="phone" placeholder="+91 9876543210">
                        </div>
                        <?php if ($role === 'senior_officer'): ?>
                        <div class="form-group">
                            <label for="officer-department">Your Department</label>
                            <input type="text" value="<?php echo htmlspecialchars($_SESSION['department'] ?? 'General'); ?>" readonly class="form-control-readonly">
                            <input type="hidden" id="officer-department" name="department" value="<?php echo htmlspecialchars($_SESSION['department'] ?? 'General'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="officer-subcategory">Specialization (Sub-category)</label>
                            <select id="officer-subcategory" name="subcategory" required>
                                <option value="">Select Specialization</option>
                            </select>
                        </div>
                        <?php elseif ($role === 'state_admin' || $role === 'district_admin'): ?>
                        <div class="form-group">
                            <label for="officer-department">Assign to Department</label>
                            <select id="officer-department" name="department" onchange="updateSubCategories()" required>
                                <option value="">Select Department</option>
                                <option value="Cyber Cell">Cyber Cell</option>
                                <option value="Vigilance">Vigilance</option>
                                <option value="Public Works (PWD)">Public Works (PWD)</option>
                                <option value="Water Board">Water Board</option>
                                <option value="Electricity Board">Electricity Board</option>
                                <option value="Municipal Services">Municipal Services</option>
                                <option value="Transport Department">Transport Department</option>
                                <option value="Health Department">Health Department</option>
                                <option value="Education Department">Education Department</option>
                                <option value="Police Department">Police Department</option>
                                <option value="Internal Affairs">Internal Affairs</option>
                                <option value="Social Welfare">Social Welfare</option>
                                <option value="Revenue Department">Revenue Department</option>
                                <option value="Environment Board">Environment Board</option>
                                <option value="Women & Child Dev">Women & Child Dev</option>
                                <option value="General Administration">General Administration</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="officer-subcategory">Specialization (Sub-category)</label>
                            <select id="officer-subcategory" name="subcategory" required>
                                <option value="">Select Specialization</option>
                            </select>
                        </div>
                        <?php else: ?>
                            <input type="hidden" id="officer-department" name="department" value="Administration">
                            <input type="hidden" id="officer-subcategory" name="subcategory" value="General">
                        <?php endif; ?>
                        <div class="form-group" <?php echo ($role !== 'national_admin' && $role !== 'admin') ? 'style="display:none;"' : ''; ?>>
                            <label for="officer-state">State</label>
                            <?php if ($role === 'national_admin' || $role === 'admin'): ?>
                                <select id="officer-state" name="state" required>
                                    <option value="">Select State</option>
                                    <?php foreach ($indianStates as $st): ?>
                                        <option value="<?php echo $st; ?>"><?php echo $st; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" id="officer-state" name="state" value="<?php echo htmlspecialchars($_SESSION['state'] ?? ''); ?>" readonly required>
                            <?php endif; ?>
                        </div>
                        <?php if ($role === 'state_admin' || $role === 'district_admin'): ?>
                        <div class="form-group">
                            <label for="officer-district">District</label>
                            <input type="text" id="officer-district" name="district" placeholder="e.g. Mumbai" value="<?php echo htmlspecialchars($_SESSION['district'] ?? ''); ?>" <?php echo $role === 'district_admin' ? 'readonly' : ''; ?> required>
                        </div>
                        <?php else: ?>
                        <?php if ($role === 'senior_officer'): ?>
                             <input type="hidden" id="officer-district" name="district" value="<?php echo htmlspecialchars($_SESSION['district'] ?? 'All Districts'); ?>">
                        <?php else: ?>
                             <input type="hidden" id="officer-district" name="district" value="All Districts">
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="officer-password">Password</label>
                            <input type="password" id="officer-password" name="password" placeholder="Min. 6 characters" required>
                        </div>
                        <div class="form-group">
                            <label for="officer-role">Hierarchy Level</label>
                            <select id="officer-role" name="role" required>
                                <?php if ($role === 'national_admin' || $role === 'admin'): ?>
                                    <option value="state_admin">State Admin</option>
                                <?php elseif ($role === 'state_admin' || $role === 'district_admin'): ?>
                                    <option value="senior_officer">Department Head (Senior)</option>
                                <?php elseif ($role === 'senior_officer'): ?>
                                    <option value="local_officer">Team Member (Officer)</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Officer</button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Officers Table -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="la la-shield"></i> All Officers (<?php echo count($officersList); ?>)</h3>
                </div>

                <div class="toolbar" style="display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap;">
                    <div class="search-box" style="flex:1; min-width:250px;">
                        <input type="text" id="search-input" placeholder="Search officers...">
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
                        <a href="manage_team.php" class="btn btn-outline btn-sm" style="padding:0.6rem;">Reset</a>
                    </form>
                    <?php endif; ?>
                </div>

                <?php if (empty($officersList)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="la la-shield"></i></div>
                        <p>No officers registered yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table id="officers-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Assignments</th>
                                    <th>Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($officersList as $o):
                                    $oid = (string) $o['_id'];
                                    $assignCount = $officerAssignmentCounts[$oid] ?? 0;
                                ?>
                                <tr id="officer-row-<?php echo $oid; ?>">
                                    <td style="color: var(--text-primary); font-weight: 500;">
                                        <div style="display:flex;align-items:center;gap:0.65rem;">
                                            <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--warning),var(--danger));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:0.82rem;flex-shrink:0;">
                                                <?php echo strtoupper(substr($o['name'], 0, 1)); ?>
                                            </div>
                                            <?php echo htmlspecialchars($o['name']); ?>
                                        </div>
                                    </td>
                                    <td><span class="badge" style="background:var(--primary-dark);"><?php echo htmlspecialchars($o['department'] ?? 'General'); ?></span>
                                        <div style="font-size: 0.70rem; color: var(--text-muted); margin-top: 2px;">
                                            <?php echo ($o['role'] ?? 'officer') === 'senior_officer' ? '<i class="la la-star-o"></i> Senior Officer' : 'Field Officer'; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($o['email']); ?></td>
                                    <td><?php echo htmlspecialchars($o['phone'] ?? '—'); ?></td>
                                    <td>
                                        <?php if ($assignCount > 0): ?>
                                            <span style="color:var(--primary-light);font-weight:600;"><?php echo $assignCount; ?> assigned</span>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($o['created_at'] ?? '—'); ?></td>
                                     <td>
                                        <div class="action-btns">
                                            <?php if ($role !== 'district_admin'): ?>
                                                <button class="btn btn-danger btn-sm" onclick="deleteOfficer('<?php echo $oid; ?>')"><i class="la la-trash-o"></i> Delete</button>
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

    <script>
        // Use global listeners from main.js, but explicitly initialize just in case
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof initSidebar === 'function') initSidebar();
            if (typeof initProfileDropdown === 'function') initProfileDropdown();
        });
        
        const subCategoryMap = {
            "Cyber Cell": ["Forensic Analysis", "Network Security", "Ethical Hacking", "Case Investigation", "Financial Fraud"],
            "Vigilance": ["Anti-Corruption", "Internal Audit", "Policy Compliance", "Complaint Monitoring"],
            "Public Works (PWD)": ["Road Construction", "Building Maintenance", "Bridge Engineering", "Drainage Systems", "Urban Planning"],
            "Water Board": ["Supply Quality", "Pipeline Leakage", "Sewage Treatment", "Rainwater Harvesting", "Billing & Metering"],
            "Water Department": ["Supply Quality", "Pipeline Leakage", "Sewage Treatment", "Rainwater Harvesting", "Billing & Metering"],
            "Electricity Board": ["Power Outage", "High Voltage Issues", "New Connection", "Meter Problems", "Street Lighting"],
            "Municipal Corp": ["Sanitation & Waste", "Parks & Recreation", "Trade Licenses", "Property Tax", "Birth/Death Certificates"],
            "Municipal Services": ["Sanitation & Waste", "Parks & Recreation", "Trade Licenses", "Property Tax", "Birth/Death Certificates"],
            "Transport Dept": ["License Services", "Vehicle Registration", "Public Transport", "Traffic Safety", "Pollution Control"],
            "Transport Department": ["License Services", "Vehicle Registration", "Public Transport", "Traffic Safety", "Pollution Control"],
            "Health Dept": ["Hospital Management", "Vaccination", "Disease Control", "Food Safety", "Medical Supplies"],
            "Health Department": ["Hospital Management", "Vaccination", "Disease Control", "Food Safety", "Medical Supplies"],
            "Education Dept": ["School Infrastructure", "Teacher Recruitment", "Scholarship", "Mid-day Meals", "Vocational Training"],
            "Education Department": ["School Infrastructure", "Teacher Recruitment", "Scholarship", "Mid-day Meals", "Vocational Training"],
            "Police Department": ["Cybercrime Investigation", "Forensic Lab", "Intelligence Unit", "Anti-Terrorism", "Public Order"],
            "Internal Affairs": ["Personnel Conduct", "Security Clearance", "Asset Declaration", "Disciplinary Actions"],
            "Social Welfare": ["Disability Support", "Women Empowerment", "Elderly Care", "Tribal Welfare", "Child Protection"],
            "Revenue Dept": ["Land Records", "Stamp Duty", "Tax Collection", "Disaster Relief", "Citizen Certificates"],
            "Revenue Department": ["Land Records", "Stamp Duty", "Tax Collection", "Disaster Relief", "Citizen Certificates"],
            "Environment Board": ["Pollution Monitoring", "Wildlife Conservation", "Forest Fire", "Waste Management", "Noise Control"],
            "Women & Child Dev": ["Child Help Line", "Anganwadi Services", "Legal Support", "Health Tracking", "Nutrition"],
            "General Administration": ["Records Management", "Public Relations", "IT Services", "Legal Cell", "Human Resources"],
            "Administration": ["Records Management", "Public Relations", "IT Services", "Legal Cell", "Human Resources"],
            "Others": ["General Inquiry", "Support Staff", "Miscellaneous"]
        };

        function updateSubCategories() {
            const dept = document.getElementById('officer-department').value;
            const subSelect = document.getElementById('officer-subcategory');
            subSelect.innerHTML = '<option value="">Select Specialization</option>';
            
            if (dept && subCategoryMap[dept]) {
                subCategoryMap[dept].forEach(sub => {
                    const opt = document.createElement('option');
                    opt.value = sub;
                    opt.textContent = sub;
                    subSelect.appendChild(opt);
                });
            } else {
                // Fallback for missing departments
                const opt = document.createElement('option');
                opt.value = "General";
                opt.textContent = "General";
                subSelect.appendChild(opt);
            }
        }

        // Initialize if role is senior_officer
        window.addEventListener('DOMContentLoaded', () => {
            const deptEl = document.getElementById('officer-department');
            if (deptEl && deptEl.value) {
                console.log("Auto-populating subcategories for department: " + deptEl.value);
                updateSubCategories();
            }
        });

        initTableSearch('search-input', 'officers-table');
        
        const addForm = document.getElementById('addOfficerForm');
        if (addForm) {
            addForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const data = Object.fromEntries(formData.entries());
                data.action = 'add'; // Ensure action is included

                // Basic Validation
                if (!data.name || !data.email || !data.password || !data.role || !data.state) {
                    showToast('Name, Email, Password, Role, and State are strictly required.', 'error');
                    return;
                }
                if (data.password.length < 6) {
                    showToast('Password must be at least 6 characters.', 'error');
                    return;
                }

                const result = await postJSON('../api/manage_officers.php', data);

                if (result.success) {
                    showToast(result.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(result.message, 'error');
                }
            });
        }

        async function deleteOfficer(id) {
            if (!confirmDelete('Delete this officer? Their assignments will be unassigned.')) return;

            const result = await postJSON('../api/manage_officers.php', {
                action: 'delete',
                officer_id: id
            });

            if (result.success) {
                showToast(result.message, 'success');
                const row = document.getElementById('officer-row-' + id);
                if (row) row.remove();
            } else {
                showToast(result.message, 'error');
            }
        }
    </script>
    <script>
        // Sidebar toggle and profile dropdown are now handled by main.js standard listeners.
    </script>
</body>
</html>
