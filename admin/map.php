<?php
/**
 * ReportMyCity — Admin: Interactive National Map
 */
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'national_admin', 'district_admin', 'state_admin', 'senior_officer'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance();

$adminName  = $_SESSION['user_name'] ?? 'Admin';
$adminEmail = $_SESSION['user_email'] ?? 'admin@reportmycity.gov';
$adminRole  = $_SESSION['role'] ?? 'admin';
$adminDistrict = $_SESSION['district'] ?? '';
$adminState    = $_SESSION['state'] ?? '';
$adminDept     = $_SESSION['department'] ?? '';
$initials   = strtoupper(substr($adminName, 0, 1));

// Fetch pending counts for sidebar notification badges
$officerReportsCount = $db->getCollection('officer_reports')->countDocuments(['status' => 'Pending Admin Review']);
$userReportsCount = $db->getCollection('user_reports')->countDocuments(['status' => 'Audit Requested']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interactive Map — ReportMyCity Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Serif:wght@400;700&display=swap" rel="stylesheet">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        #map-container {
            height: 650px; 
            width: 100%; 
            border-radius: 0 0 var(--radius-md) var(--radius-md);
            z-index: 10;
        }
        .map-stats-bar {
            padding: 1rem 1.5rem; 
            border-bottom: 1px solid var(--border); 
            display: flex; 
            flex-wrap: wrap;
            gap: 20px;
            background: var(--bg-card);
            align-items: center;
        }
        .marker-pin {
            width: 30px;
            height: 30px;
            border-radius: 50% 50% 50% 0;
            background: #c30b82;
            position: absolute;
            transform: rotate(-45deg);
            left: 50%;
            top: 50%;
            margin: -15px 0 0 -15px;
        }
        .custom-div-icon i {
            color: #fff;
            position: absolute;
            width: 22px;
            font-size: 14px;
            left: 4px;
            top: 4px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main -->
        <main class="main-content">
            <nav class="breadcrumb-nav">
                <div class="header-left">
                    <button class="sidebar-toggle"><i class="la la-bars"></i></button>
                    <div class="header-logo-group">
                        <img src="../assets/images/govt_emblem.png" alt="Emblem">
                        <span>Republic of India</span>
                    </div>
                    <h1>Interactive National Map</h1>
                </div>
                <div class="user-info">
                    <span style="font-size:0.82rem; color:var(--text-muted);"><?php echo date('d M Y'); ?></span>
                    <!-- Profile Dropdown -->
                    <div class="profile-dropdown-wrapper" id="profileDropdownWrapper">
                        <div class="user-avatar"><?php echo $initials; ?></div>
                        <div class="profile-dropdown-menu">
                            <div class="profile-dropdown-header">
                                <strong><?php echo htmlspecialchars($adminName); ?></strong>
                                <span><?php echo htmlspecialchars($adminEmail); ?></span>
                            </div>
                            <a href="../logout.php" class="dropdown-logout">
                                <i class="la la-sign-out"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="card" style="margin-top: 1rem;">
                <div class="map-stats-bar">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="color: #ef4444;"><i class="la la-circle"></i></span>
                        <strong id="stat-pending">0</strong> Pending
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="color: #3b82f6;"><i class="la la-circle"></i></span>
                        <strong id="stat-progress">0</strong> In Progress
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="color: #10b981;"><i class="la la-circle"></i></span>
                        <strong id="stat-resolved">0</strong> Resolved
                    </div>
                    <div style="margin-left: auto; font-size: 0.85rem; color: var(--text-muted);">
                        <i class="la la-info-circle"></i> Click pins for details
                    </div>
                </div>
                
                <!-- Map Container -->
                <div id="map-container"></div>
            </div>
        </main>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="../assets/js/main.js"></script>
    <script>
        const adminRole = <?php echo json_encode($adminRole); ?>;
        const adminDistrict = <?php echo json_encode($adminDistrict); ?>;
        const adminState = <?php echo json_encode($adminState); ?>;

        let defaultCenter = [20.5937, 78.9629];
        let defaultZoom = 5;

        const map = L.map('map-container').setView(defaultCenter, defaultZoom);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Fetch complaint points
        fetch('../api/get_heatmap_data.php?format=detailed')
            .then(res => res.json())
            .then(res => {
                if(res.success && res.data && res.data.length > 0) {
                    let counts = { Pending: 0, 'In Progress': 0, Resolved: 0 };
                    
                    res.data.forEach(point => {
                        const [lat, lng, status, title, category] = point;
                        
                        // Status color
                        let color = '#f59e0b'; // Pending
                        if (status === 'In Progress') color = '#3b82f6';
                        else if (status === 'Resolved' || status === 'Closed' || status === 'Officer Completed') {
                            color = '#10b981';
                            counts.Resolved++;
                        } else if (status === 'Pending' || status === 'Submitted') {
                            counts.Pending++;
                        } else {
                            counts['In Progress']++;
                        }

                        // Icon
                        const icon = L.divIcon({
                            className: 'custom-div-icon',
                            html: `<div style='background-color:${color};' class='marker-pin'></div><i class='la la-exclamation'></i>`,
                            iconSize: [30, 42],
                            iconAnchor: [15, 42]
                        });

                        L.marker([lat, lng], { icon: icon })
                            .bindPopup(`
                                <div style="font-family: 'Inter', sans-serif;">
                                    <strong style="color:var(--gov-navy);">${title}</strong><br>
                                    <span style="font-size:0.75rem; color:var(--text-muted);">${category}</span><hr style="margin:5px 0;">
                                    <span class="badge" style="background:${color}; color:#fff; font-size:0.7rem;">${status}</span>
                                </div>
                            `)
                            .addTo(map);
                    });

                    // Update stats
                    document.getElementById('stat-pending').innerText = counts.Pending;
                    document.getElementById('stat-progress').innerText = counts['In Progress'];
                    document.getElementById('stat-resolved').innerText = counts.Resolved;

                    // Auto fit
                    const bounds = L.latLngBounds(res.data.map(p => [p[0], p[1]]));
                    map.fitBounds(bounds, { padding: [50, 50] });
                }
            })
            .catch(err => console.error('Map points fetch error:', err));
    </script>
</body>
</html>
