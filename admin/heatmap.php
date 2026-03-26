<?php
/**
 * ReportMyCity — Admin: Heatmap
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
    <title>Complaint Heatmap — ReportMyCity Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Serif:wght@400;700&display=swap" rel="stylesheet">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        #heatmap-container {
            height: 600px; 
            width: 100%; 
            border-radius: 0 0 var(--radius-md) var(--radius-md);
            z-index: 10;
        }
        .legend-container {
            padding: 1rem; 
            border-bottom: 1px solid var(--border); 
            display: flex; 
            flex-wrap: wrap;
            gap: 20px;
            background: var(--bg-card);
            align-items: center;
        }
    </style>
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
                        <span>ReportMyCity</span>
                    </div>
                    <h1>
                        <?php echo ($adminDept ? $adminDept . ' - ' : ''); ?>
                        <?php echo $adminDistrict ? $adminDistrict . ' ' : ($adminState ? $adminState . ' ' : ''); ?>
                        Complaint Heatmap
                    </h1>
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
                    <h3>🔥 Live Complaint Activity Heatmap</h3>
                </div>
                
                <!-- Legend -->
                <div class="legend-container">
                    <strong>Intensity Legend:</strong>
                    <span style="color: #ff0000; font-weight: bold; display: flex; align-items: center; gap: 5px;"><div style="width: 15px; height: 15px; background: #ff0000; border-radius: 50%;"></div> High Density (Red Zone)</span>
                    <span style="color: #ff8c00; font-weight: bold; display: flex; align-items: center; gap: 5px;"><div style="width: 15px; height: 15px; background: #ff8c00; border-radius: 50%;"></div> Medium Density (Orange)</span>
                    <span style="color: #ffd700; font-weight: bold; display: flex; align-items: center; gap: 5px;"><div style="width: 15px; height: 15px; background: #ffd700; border-radius: 50%;"></div> Low Density (Yellow)</span>
                    <span style="color: #008000; font-weight: bold; display: flex; align-items: center; gap: 5px;"><div style="width: 15px; height: 15px; background: #00ff00; border-radius: 50%;"></div> Sparse (Green)</span>
                </div>
                
                <!-- Map Container -->
                <div id="heatmap-container"></div>
            </div>

        </main>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <!-- Leaflet.heat JS -->
    <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        // Admin Region Data
        const adminRole = <?php echo json_encode($adminRole); ?>;
        const adminDistrict = <?php echo json_encode($adminDistrict); ?>;
        const adminState = <?php echo json_encode($adminState); ?>;

        // Default: India
        let defaultCenter = [20.5937, 78.9629];
        let defaultZoom = 5;

        const map = L.map('heatmap-container').setView(defaultCenter, defaultZoom);
        
        // Function to center map on region and restrict bounds
        async function centerMapOnRegion() {
            let searchQuery = '';
            // If they are not national_admin and have a region, center and restrict it.
            if (adminRole !== 'national_admin') {
                if (adminDistrict && adminState) {
                    searchQuery = `${adminDistrict}, ${adminState}, India`;
                    defaultZoom = 12;
                } else if (adminState) {
                    searchQuery = `${adminState}, India`;
                    defaultZoom = 7;
                } else {
                    return; // No region set
                }
            } else {
                return; // National admin sees everything, default center
            }

            try {
                const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(searchQuery)}&limit=1`);
                const data = await response.json();
                if (data && data.length > 0) {
                    const lat = parseFloat(data[0].lat);
                    const lon = parseFloat(data[0].lon);
                    map.setView([lat, lon], defaultZoom);
                    
                    // Restrict bounds so they can't pan away from their state/district
                    if (data[0].boundingbox) {
                        const bbox = data[0].boundingbox;
                        const southWest = L.latLng(bbox[0], bbox[2]);
                        const northEast = L.latLng(bbox[1], bbox[3]);
                        const bounds = L.latLngBounds(southWest, northEast);
                        
                        map.setMaxBounds(bounds.pad(0.5)); // Add padding for better UX
                        map.setMinZoom(defaultZoom - 2);
                    }
                }
            } catch (error) {
                console.error('Error geocoding/restricting region:', error);
            }
        }

        // Run centering
        centerMapOnRegion();
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Fetch location data
        fetch('../api/get_heatmap_data.php')
            .then(res => res.json())
            .then(res => {
                if(res.success && res.data && res.data.length > 0) {
                    // Extract data in [lat, lng, intensity] format
                    // leaflet-heat takes array of [lat, lng, intensity] or just [lat, lng]
                    const heatData = res.data;
                    
                    // Create heat layer
                    const heat = L.heatLayer(heatData, {
                        radius: 25,
                        blur: 15,
                        maxZoom: 16,
                        gradient: {
                            0.2: '#00ff00', // Green
                            0.4: '#ffd700', // Yellow
                            0.6: '#ff8c00', // Orange
                            0.9: '#ff0000'  // Red
                        }
                    }).addTo(map);

                    // Auto fit map bounds to the data
                    // Res.data is array of [lat, lng, intensity]
                    const bounds = L.latLngBounds(heatData.map(p => [p[0], p[1]]));
                    map.fitBounds(bounds, { padding: [50, 50] });
                } else {
                    showToast('No location data available for heatmap.', 'info');
                }
            })
            .catch(err => {
                console.error('Heatmap fetch error:', err);
                showToast('Failed to load heatmap data.', 'error');
            });
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
