<?php
/**
 * ReportMyCity — Admin: Generate Complaint Report (Printable)
 */
session_start();
$allowedAdminRoles = ['admin', 'national_admin', 'state_admin', 'district_admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowedAdminRoles)) {
    exit('Unauthorized');
}
require_once __DIR__ . '/../config/database.php';

$id = $_GET['id'] ?? '';
if (!$id) exit('Invalid ID');

$db = Database::getInstance();
$complaintsCol = $db->getCollection('complaints');
$usersCol = $db->getCollection('users');

try {
    $c = $complaintsCol->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
} catch (Exception $e) { exit('Error fetching complaint'); }

if (!$c) exit('Complaint not found');

// Fetch user name
$userName = 'Unknown';
if (!empty($c['user_id'])) {
    try {
        $u = $usersCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$c['user_id'])]);
        if ($u) $userName = $u['name'];
    } catch (Exception $e) {}
}

$status = $c['status'] ?? 'Pending';
$cIdShort = substr((string)$c['_id'], -6);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Complaint Report #<?php echo $cIdShort; ?></title>
    <link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; color: #1e293b; line-height: 1.6; margin: 0; padding: 40px; background: #fff; }
        .report-container { max-width: 800px; margin: 0 auto; border: 1px solid #e2e8f0; padding: 40px; border-radius: 8px; }
        .header { text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { margin: 0; color: #0f172a; font-size: 24px; text-transform: uppercase; letter-spacing: 1px; }
        .header p { margin: 5px 0 0; color: #64748b; font-size: 14px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .item label { display: block; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px; }
        .item p { margin: 0; font-weight: 500; font-size: 15px; }
        .full-width { grid-column: 1 / -1; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; background: #f1f5f9; color: #475569; }
        .description-box { background: #f8fafc; padding: 20px; border-radius: 6px; border-left: 4px solid #3b82f6; margin-bottom: 30px; }
        .photo-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        .photo-box img { width: 100%; height: auto; border-radius: 4px; border: 1px solid #e2e8f0; }
        .footer { margin-top: 50px; padding-top: 20px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #94a3b8; text-align: center; }
        @media print {
            body { padding: 0; }
            .report-container { border: none; }
            .no-print { display: none; }
        }
        .btn-print { position: fixed; top: 20px; right: 20px; padding: 10px 20px; background: #2563eb; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
    </style>
</head>
<body>
    <button class="btn-print no-print" onclick="window.print()"><i class="la la-print"></i> Print Report</button>

    <div class="report-container">
        <div class="header">
            <img src="../assets/images/govt_emblem.png" alt="Emblem">
            <h1>ReportMyCity — Official Complaint Dossier</h1>
            <p>Government of India • Citizen Oversight Initiative</p>
        </div>

        <div class="grid">
            <div class="item">
                <label>Tracking ID</label>
                <p>#<?php echo $cIdShort; ?></p>
            </div>
            <div class="item">
                <label>Current Status</label>
                <p><span class="status-badge"><?php echo $status; ?></span></p>
            </div>
            <div class="item">
                <label>Issue Title</label>
                <p><?php echo htmlspecialchars($c['title'] ?? 'N/A'); ?></p>
            </div>
            <div class="item">
                <label>Category / Dept</label>
                <p><?php echo htmlspecialchars($c['category'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($c['target_department'] ?? 'General'); ?>)</p>
            </div>
            <div class="item">
                <label>Reported By</label>
                <p><?php echo htmlspecialchars($userName); ?></p>
            </div>
            <div class="item">
                <label>Date & Time</label>
                <p><?php echo htmlspecialchars($c['date'] ?? $c['created_at'] ?? 'N/A'); ?></p>
            </div>
            <div class="item full-width">
                <label>Location</label>
                <p><i class="la la-map-marker"></i> <?php echo htmlspecialchars($c['location'] ?? 'N/A'); ?> (Pincode: <?php echo htmlspecialchars($c['pincode'] ?? 'N/A'); ?>)</p>
            </div>
        </div>

        <div class="description-box">
            <label style="color:#2563eb;">Incident Description</label>
            <p><?php echo nl2br(htmlspecialchars($c['description'] ?? '')); ?></p>
        </div>

        <?php if (!empty($c['admin_reply'])): ?>
        <div class="description-box" style="border-left-color: #0f172a; background: #fdfdfd;">
            <label style="color:#0f172a;">Official Action / Reply</label>
            <p><?php echo nl2br(htmlspecialchars($c['admin_reply'])); ?></p>
        </div>
        <?php endif; ?>

        <div class="grid">
             <div class="item">
                <label>Assigned Officer</label>
                <p><?php echo htmlspecialchars($c['assigned_officer_name'] ?? 'Unassigned'); ?></p>
            </div>
            <div class="item">
                <label>Resolution Rating</label>
                <p><?php echo isset($c['rating']) ? $c['rating'] . " / 5 Stars" : 'Not Rated'; ?></p>
            </div>
        </div>

        <?php if (!empty($c['image']) || !empty($c['officer_proof_image'])): ?>
        <h3 style="font-size: 14px; text-transform: uppercase; color: #94a3b8; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">Evidence & Documentation</h3>
        <div class="photo-grid">
            <?php if (!empty($c['image'])): ?>
            <div class="photo-box">
                <label style="font-size: 10px; color: #64748b; display: block; margin-bottom: 5px;">Citizen Submission</label>
                <img src="../<?php echo $c['image']; ?>" alt="Original Issue">
            </div>
            <?php endif; ?>
            <?php if (!empty($c['officer_proof_image'])): ?>
            <div class="photo-box">
                <label style="font-size: 10px; color: #64748b; display: block; margin-bottom: 5px;">Officer Resolution Proof</label>
                <img src="../<?php echo $c['officer_proof_image']; ?>" alt="Resolution Proof">
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="footer">
            <p>This is an electronically generated report from the ReportMyCity Portal.</p>
            <p>Generated on <?php echo date('d M Y, h:i A'); ?></p>
        </div>
    </div>
</body>
</html>
