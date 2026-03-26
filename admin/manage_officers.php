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
$adminsCol      = $db->getCollection('admins');
$stateAdminsCol = $db->getCollection('state_admins');
$headOfficersCol = $db->getCollection('head_officers');
$fieldOfficersCol = $db->getCollection('field_officers');
$complaintsCol   = $db->getCollection('complaints');

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
    $filter = ['state' => $_SESSION['state']];
} elseif ($role === 'district_admin') {
    $filter = ['state' => $_SESSION['state'], 'district' => $_SESSION['district']];
} elseif ($role === 'senior_officer') {
    $filter = ['state' => $_SESSION['state'], 'department' => $_SESSION['department']];
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

// Aggregate from all 4 tiers
// Aggregate from all 4 tiers (Sectional Lists)
$stateAdminsList = iterator_to_array($stateAdminsCol->find($filter, ['sort' => ['created_at' => -1]]));
$headList        = iterator_to_array($headOfficersCol->find($filter, ['sort' => ['created_at' => -1]]));
$fieldList       = iterator_to_array($fieldOfficersCol->find($filter, ['sort' => ['created_at' => -1]]));

// Combined list for general searching/sorting if needed, but we will show them separately now
$allOfficersList = array_merge($stateAdminsList, $headList, $fieldList);

// Pre-fetch distinct states/districts for filters (only for National Admin)
$statesList = [];
$districtsList = [];
if ($role === 'national_admin' || $role === 'admin') {
    $statesList = array_unique(array_merge(
        $stateAdminsCol->distinct('state'),
        $headOfficersCol->distinct('state'),
        $fieldOfficersCol->distinct('state')
    ));
    if (!empty($filter['state'])) {
        $districtsList = array_unique(array_merge(
            $stateAdminsCol->distinct('district', ['state' => $filter['state']]),
            $headOfficersCol->distinct('district', ['state' => $filter['state']]),
            $fieldOfficersCol->distinct('district', ['state' => $filter['state']])
        ));
    } else {
        $districtsList = array_unique(array_merge(
            $stateAdminsCol->distinct('district'),
            $headOfficersCol->distinct('district'),
            $fieldOfficersCol->distinct('district')
        ));
    }
}

// Get assignment counts for all officers
$officerAssignmentCounts = [];
foreach ($allOfficersList as $o) {
    $oid = (string) $o['_id'];
    $officerAssignmentCounts[$oid] = $complaintsCol->countDocuments(['assigned_officer_id' => $oid]);
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
    <title>Manage Officials | ReportMyCity India</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

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
                <form id="addOfficerForm">
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
                        <?php if ($role !== 'national_admin' && $role !== 'admin'): ?>
                        <div class="form-group">
                            <label for="officer-department">Department (Category)</label>
                            <select id="officer-department" name="department" required onchange="updateSubCategories()">
                                <option value="">Select Department</option>
                                <?php if ($role === 'senior_officer'): ?>
                                    <option value="<?php echo htmlspecialchars($_SESSION['department']); ?>" selected><?php echo htmlspecialchars($_SESSION['department']); ?></option>
                                <?php else: ?>
                                    <option value="Cyber Cell">Cyber Cell</option>
                                    <option value="Transport Department">Transport Department</option>
                                    <option value="Water Department">Water Department</option>
                                    <option value="Electricity Board">Electricity Board</option>
                                    <option value="Health Department">Health Department</option>
                                    <option value="Police Department">Police Department</option>
                                    <option value="Municipal Services">Municipal Services</option>
                                    <option value="Education Department">Education Department</option>
                                    <option value="General Administration">General Administration</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="officer-subcategory">Specialization (Sub-category)</label>
                            <select id="officer-subcategory" name="subcategory" required>
                                <option value="">Select Specialization</option>
                            </select>
                        </div>
                        <?php else: ?>
                            <input type="hidden" name="department" value="Administration">
                            <input type="hidden" name="subcategory" value="General">
                        <?php endif; ?>
                        <div class="form-group">
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
                            <input type="hidden" id="officer-district" name="district" value="All Districts">
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
                                <option value="state_admin">State Admin</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Create State Admin Account</button>
                </form>
            </div>
            <?php endif; ?>
            <!-- State Administrators Table -->
            <div class="card" style="border-top: 4px solid var(--primary);">
                <div class="card-header">
                    <h3><i class="la la-university"></i> Regional State Administrators (<?php echo count($stateAdminsList); ?>)</h3>
                </div>
                <?php if (empty($stateAdminsList)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="la la-university"></i></div>
                        <p>No State Administrators registered yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table id="state-admins-table">
                            <thead>
                                <tr>
                                    <th>Admin Name</th>
                                    <th>Assigned State</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Hierarchy</th>
                                    <th>Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stateAdminsList as $o): $oid = (string)$o['_id']; ?>
                                 <tr id="officer-row-<?php echo $oid; ?>">
                                    <td style="font-weight: 600;">
                                        <div style="display:flex;align-items:center;gap:0.65rem;">
                                            <div style="width:32px;height:32px;border-radius:50%;background:var(--gov-navy);display:flex;align-items:center;justify-content:center;color:#fff;font-size:0.82rem;flex-shrink:0;">
                                                <?php echo strtoupper(substr($o['name'], 0, 1)); ?>
                                            </div>
                                            <?php echo htmlspecialchars($o['name']); ?>
                                        </div>
                                    </td>
                                    <td><span class="badge" style="background:#059669;"><?php echo htmlspecialchars($o['state']); ?></span></td>
                                    <td><?php echo htmlspecialchars($o['email']); ?></td>
                                    <td><?php echo htmlspecialchars($o['phone'] ?? '—'); ?></td>
                                    <td><span style="font-size: 0.75rem;"><i class="la la-shield"></i> State Level</span></td>
                                    <td><?php echo htmlspecialchars($o['created_at'] ?? '—'); ?></td>
                                     <td>
                                        <button class="btn btn-danger btn-sm" onclick="deleteOfficer('<?php echo $oid; ?>')"><i class="la la-trash-o"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Officers Table (Field Workforce) -->
            <div class="card" style="margin-top: 2rem;">
                <div class="card-header">
                    <h3><i class="la la-shield"></i> Regional Field Officers (Head & Local)</h3>
                </div>

                <div class="toolbar" style="display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap;">
                    <div class="search-box" style="flex:1; min-width:250px;">
                        <input type="text" id="search-input" placeholder="Search field workforce...">
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
                        <a href="manage_officers.php" class="btn btn-outline btn-sm" style="padding:0.6rem;">Reset</a>
                    </form>
                    <?php endif; ?>
                </div>

                <?php 
                $workforceList = array_merge($headList, $fieldList);
                if (empty($workforceList)): 
                ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="la la-users"></i></div>
                        <p>No departmental officers or field staff registered yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table id="officers-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>State & Dept</th>
                                    <th>Contact</th>
                                    <th>Tasks</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($workforceList as $o):
                                    $oid = (string) $o['_id'];
                                    $assignCount = $officerAssignmentCounts[$oid] ?? 0;
                                ?>
                                 <tr id="officer-row-<?php echo $oid; ?>">
                                    <td style="color: var(--text-primary); font-weight: 500;">
                                        <?php echo htmlspecialchars($o['name']); ?>
                                        <div style="font-size: 0.70rem; color: var(--text-muted);">
                                            <?php echo ucwords(str_replace('_', ' ', $o['role'] ?? 'officer')); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.85rem;"><?php echo htmlspecialchars($o['state']); ?></div>
                                        <span class="badge" style="background:var(--primary-dark);"><?php echo htmlspecialchars($o['department'] ?? 'General'); ?></span>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.82rem;"><?php echo htmlspecialchars($o['email']); ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($o['phone'] ?? ''); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($assignCount > 0): ?>
                                            <span style="color:var(--primary-light);font-weight:600;"><?php echo $assignCount; ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);">0</span>
                                        <?php endif; ?>
                                    </td>
                                     <td>
                                        <button class="btn btn-danger btn-sm" onclick="deleteOfficer('<?php echo $oid; ?>')"><i class="la la-trash-o"></i></button>
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
        const subCategoryMap = {
            "Cyber Cell": ["Forensic Analysis", "Network Security", "Ethical Hacking", "Case Investigation", "Financial Fraud"],
            "Vigilance": ["Anti-Corruption", "Internal Audit", "Policy Compliance", "Complaint Monitoring"],
            "Public Works (PWD)": ["Road Construction", "Building Maintenance", "Bridge Engineering", "Drainage Systems", "Urban Planning"],
            "Water Board": ["Supply Quality", "Pipeline Leakage", "Sewage Treatment", "Rainwater Harvesting", "Billing & Metering"],
            "Electricity Board": ["Power Outage", "High Voltage Issues", "New Connection", "Meter Problems", "Street Lighting"],
            "Municipal Corp": ["Sanitation & Waste", "Parks & Recreation", "Trade Licenses", "Property Tax", "Birth/Death Certificates"],
            "Transport Dept": ["License Services", "Vehicle Registration", "Public Transport", "Traffic Safety", "Pollution Control"],
            "Health Dept": ["Hospital Management", "Vaccination", "Disease Control", "Food Safety", "Medical Supplies"],
            "Education Dept": ["School Infrastructure", "Teacher Recruitment", "Scholarship", "Mid-day Meals", "Vocational Training"],
            "Internal Affairs": ["Personnel Conduct", "Security Clearance", "Asset Declaration", "Disciplinary Actions"],
            "Social Welfare": ["Disability Support", "Women Empowerment", "Elderly Care", "Tribal Welfare", "Child Protection"],
            "Revenue Dept": ["Land Records", "Stamp Duty", "Tax Collection", "Disaster Relief", "Citizen Certificates"],
            "Environment Board": ["Pollution Monitoring", "Wildlife Conservation", "Forest Fire", "Waste Management", "Noise Control"],
            "Women & Child Dev": ["Child Help Line", "Anganwadi Services", "Legal Support", "Health Tracking", "Nutrition"],
            "General Administration": ["Records Management", "Public Relations", "IT Services", "Legal Cell", "Human Resources"],
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
            }
        }

        // Initialize if role is senior_officer
        window.addEventListener('load', () => {
            if (document.getElementById('officer-department').value) {
                updateSubCategories();
            }
        });

        initTableSearch('search-input', 'officers-table');

        document.getElementById('addOfficerForm').addEventListener('submit', async function(e) {
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
        // Profile Dropdown
        const pdw = document.getElementById('profileDropdownWrapper');
        if (pdw) {
            pdw.addEventListener('click', function(e) { e.stopPropagation(); this.classList.toggle('open'); });
            document.addEventListener('click', () => pdw.classList.remove('open'));
        }
    </script>
</body>
</html>
