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
$officersCol = $db->getCollection('officers');
$state = $_SESSION['state'];

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
$seniorOfficers = iterator_to_array($officersCol->find(['state' => $state, 'role' => 'senior_officer']));
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
</head>
<body class="admin-theme">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <div class="header-left">
                    <h1><i class="la la-building-o"></i> Manage Departments (<?php echo htmlspecialchars($state); ?>)</h1>
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
                            <label>Department Name</label>
                            <input type="text" name="name" required placeholder="e.g. Cyber Cell, Water Department" style="width: 100%; padding: 0.8rem; border: 1px solid var(--border); border-radius: 8px;">
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
                                        <form method="POST" style="display: flex; gap: 0.5rem; align-items: center;">
                                            <input type="hidden" name="action" value="assign_head">
                                            <input type="hidden" name="dept_id" value="<?php echo (string)$dept['_id']; ?>">
                                            <select name="head_id" class="filter-select" style="padding: 0.4rem; font-size: 0.8rem;">
                                                <option value="">Select Head</option>
                                                <?php foreach ($seniorOfficers as $so): ?>
                                                    <?php if (($so['department'] ?? '') === $dept['name']): ?>
                                                        <option value="<?php echo (string)$so['_id']; ?>" <?php echo ($dept['head_id'] === (string)$so['_id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($so['name']); ?>
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-primary btn-sm">Assign</button>
                                        </form>
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
    <script src="../assets/js/main.js"></script>
</body>
</html>
