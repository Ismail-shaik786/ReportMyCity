<?php
/**
 * ReportMyCity — Submit Complaint Page
 */
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance();
$userDoc = $db->getCollection('users')->findOne(['_id' => new MongoDB\BSON\ObjectId($_SESSION['user_id'])]);
$userPhoto = $userDoc['photo'] ?? '';
$userState = $userDoc['state'] ?? '';
$userName = $_SESSION['user_name'] ?? 'User';
$initials = strtoupper(substr($userName, 0, 1));

// Fetch departments for this user's state
$deptsCol = $db->getCollection('departments');
$activeDepts = iterator_to_array($deptsCol->find(['state' => $userState]));

$standardCategories = [
    "Corruption & Bribery", "Roads & Infrastructure", "Water Supply Issues", 
    "Electricity Problems", "Sanitation & Garbage", "Public Transport Issues", 
    "Healthcare Complaints", "Education System Issues", "Police Misconduct / Law & Order", 
    "Government Scheme Issues", "Land & Property Disputes", "Cybercrime / Online Fraud", 
    "Environmental Issues", "Women & Child Safety", "Municipal Services", "Tax / Revenue Issues", "Other"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Complaint — ReportMyCity</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        #map {
            height: 250px;
            width: 100%;
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            margin-bottom: 0.5rem;
            z-index: 10;
        }
        /* Pulsing Blue Dot for Live Location */
        .live-dot {
            width: 14px;
            height: 14px;
            background: #2563eb;
            border: 2px solid #fff;
            border-radius: 50%;
            box-shadow: 0 0 10px rgba(37, 99, 235, 0.5);
        }
        .live-dot-pulse {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 40px;
            height: 40px;
            background: rgba(37, 99, 235, 0.2);
            border-radius: 50%;
            animation: map_pulse 2s infinite ease-out;
        }
        @keyframes map_pulse {
            0% { transform: translate(-50%, -50%) scale(0.5); opacity: 0.8; }
            100% { transform: translate(-50%, -50%) scale(2.5); opacity: 0; }
        }
    </style>
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
                        <span>Citizen Portal</span>
                    </div>
                </div>
            </div>
            <div class="sidebar-gold-stripe"></div>
            <nav class="sidebar-nav">
                <div class="sidebar-section-label">Navigation</div>
                <a href="dashboard.php">
                    <span class="nav-icon"><i class="la la-bar-chart-o"></i></span> Dashboard
                </a>
                <a href="leaderboard.php">
                    <span class="nav-icon"><i class="la la-trophy"></i></span> Civic Leaderboard
                </a>
                <a href="submit_complaint.php" class="active">
                    <span class="nav-icon"><i class="la la-pencil-square-o"></i></span> Submit Complaint
                </a>
                <a href="my_complaints.php">
                    <span class="nav-icon"><i class="la la-list-alt"></i></span> My Complaints
                </a>
                <a href="profile.php">
                    <span class="nav-icon"><i class="la la-user-o"></i></span> My Profile
                </a>
                <div class="sidebar-section-label" style="margin-top:1.5rem; color:#ef4444;"><i class="la la-shield"></i> Oversight</div>
                <a href="my_complaints.php" style="color:#ef4444; background: rgba(239, 68, 68, 0.05); border: 1px dashed rgba(239, 68, 68, 0.2);">
                    <span class="nav-icon"><i class="la la-shield"></i></span> Report Officer Conduct
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="sidebar-user-info">
                    <div class="sidebar-user-avatar">
                        <?php if ($userPhoto): ?>
                            <img src="../<?php echo htmlspecialchars($userPhoto); ?>" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                        <?php else: ?>
                            <?php echo $initials; ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="sidebar-user-name"><?php echo htmlspecialchars($userName); ?></div>
                        <div class="sidebar-user-role">Citizen</div>
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
                        <span>ReportMyCity</span>
                    </div>
                    <h1>Submit a Complaint</h1>
                </div>

                <div class="user-info">
                    <span><?php echo htmlspecialchars($userName); ?></span>
                    <div class="profile-dropdown-wrapper" id="profileDropdownWrapper">
                        <div class="user-avatar">
                            <?php if ($userPhoto): ?>
                                <img src="../<?php echo htmlspecialchars($userPhoto); ?>" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                            <?php else: ?>
                                <?php echo $initials; ?>
                            <?php endif; ?>
                        </div>
                        <div class="profile-dropdown-menu">
                            <div class="profile-dropdown-header">
                                <strong><?php echo htmlspecialchars($userName); ?></strong>
                                <span>Citizen Account</span>
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
                <div class="card">
                    <div class="card-header">
                        <h3><i class="la la-pencil-square-o"></i> New Civic Complaint</h3>
                    </div>

                    <div class="card-body">
                        <form id="complaintForm" enctype="multipart/form-data">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="title">Complaint Title</label>
                                    <input type="text" id="title" name="title" placeholder="e.g. Broken road near main market" required>
                                </div>
<div class="form-group">
                                    <label for="category">Category / Target Department</label>
                                    <select id="category" name="category" required onchange="updateSubcategories()">
                                        <option value="">Select category...</option>
                                        <?php 
                                        // 1. Show State-Specific Departments First
                                        if (!empty($activeDepts)): ?>
                                            <optgroup label="State Specific Departments">
                                                <?php foreach ($activeDepts as $dept): ?>
                                                    <option value="<?php echo htmlspecialchars($dept['name']); ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>

                                        <?php 
                                        // 2. Show Standard Categories
                                        ?>
                                        <optgroup label="Standard Categories">
                                            <?php foreach ($standardCategories as $cat): ?>
                                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    </select>
                                </div>
                                <div class="form-group" id="subcategory-group" style="display: none;">
                                    <label for="subcategory">Sub-category</label>
                                    <select id="subcategory" name="subcategory">
                                        <option value="">Select sub-category...</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" placeholder="Describe the issue in detail..." required></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label>Evidence & Location (Pinpoint on Map)</label>
                                    
                                    <div style="position: relative;">
                                        <div id="map"></div>
                                        <!-- Address Search Overlay -->
                                        <div style="position: absolute; top: 12px; left: 50px; right: 12px; z-index: 50;">
                                            <div style="display: flex; gap: 5px; background: white; padding: 4px; border-radius: 25px; box-shadow: var(--shadow-md); border: 1px solid var(--border);">
                                                <input type="text" id="map-search-input" placeholder="Search for address, street or landmark..." 
                                                    style="flex: 1; border: none; padding: 6px 15px; border-radius: 20px; font-size: 0.85rem; outline: none; background: transparent;">
                                                <button type="button" id="btn-search-go" class="btn btn-primary" 
                                                    style="padding: 0 15px; height: 32px; border-radius: 20px; font-size: 0.75rem; white-space: nowrap;">Search</button>
                                            </div>
                                            <div id="search-results" style="display: none; background: white; border: 1px solid var(--border); border-radius: 8px; margin-top: 5px; max-height: 200px; overflow-y: auto; box-shadow: var(--shadow-lg);"></div>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; gap: 10px; align-items: center; margin-top: 10px;">
                                        <input type="text" id="location" name="location" placeholder="Select location on map or permit GPS access." required readonly style="background: var(--bg-body); cursor: not-allowed; flex: 2;">
                                        <input type="text" id="pincode" name="pincode" placeholder="Pincode" required style="flex: 1; min-width: 100px;">
                                        <button type="button" id="btn-get-location" class="btn btn-info btn-sm"><i class="la la-map-marker"></i> Locate Me</button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="date">Incident Date</label>
                                    <input type="date" id="date" name="date" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="priority">Priority Level</label>
                                    <select id="priority" name="priority" required>
                                        <option value="Low">Low</option>
                                        <option value="Medium" selected>Medium</option>
                                        <option value="High">High</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row" style="margin-bottom: 1rem;">
                                <div class="form-group" style="grid-column: 1 / -1; background: rgba(59, 130, 246, 0.05); padding: 15px; border-radius: var(--radius-sm); border: 1px dashed rgba(59, 130, 246, 0.2);">
                                    <label for="accused_role" style="color: var(--gov-navy); font-weight: 700;">Is this complaint against a specific official? (Conflict-Aware Routing)</label>
                                    <select id="accused_role" name="accused_role" style="margin-top: 0.5rem;">
                                        <option value="">No, this is a general complaint</option>
                                        <option value="Local Officer">Yes, against a Local Field Officer</option>
                                        <option value="District Admin">Yes, against a District Administrator (e.g. MRO, RDO)</option>
                                        <option value="State Admin">Yes, against a State Administrator</option>
                                    </select>
                                    <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 5px;">If selected, the system will bypass the standard chain of command and securely route this case directly to their superiors.</div>
                                </div>
                            </div>

                            <div class="form-group" style="background: rgba(239, 68, 68, 0.05); padding: 15px; border-radius: var(--radius-sm); border: 1px dashed rgba(239, 68, 68, 0.2);">
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0; font-weight: 700;">
                                    <input type="checkbox" name="anonymous" id="anonymous" value="true" style="width: auto; margin:0; transform: scale(1.2);">
                                    Submit Anonymously (Identity hidden from assigned officers)
                                </label>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 5px; padding-left: 28px;">Recommended for sensitive issues like corruption. Your details remain confidential to higher authorities only.</div>
                            </div>

                            <div class="form-group" style="margin-top: 2rem; padding-top: 2rem; border-top: 1.5px solid var(--border);">
                                <label style="display: block; text-align: left; margin-bottom: 1.5rem; font-weight: 800; color: var(--gov-navy); font-size: 1.35rem; letter-spacing: -0.01em;"><i class="la la-camera"></i> Evidence Selection</label>
                                
                                <div class="evidence-source-picker">
                                    <label class="source-card" for="complaint-image">
                                        <div class="source-icon-wrapper blue">
                                            <span><i class="la la-folder-open"></i></span>
                                        </div>
                                        <div class="source-content">
                                            <strong>Upload Image</strong>
                                            <span>From your device gallery</span>
                                        </div>
                                    </label>
                                    <input type="file" id="complaint-image" name="image" accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">
                                    
                                    <div class="source-card" id="open-camera-btn">
                                        <div class="source-icon-wrapper gold">
                                            <span><i class="la la-camera"></i></span>
                                        </div>
                                        <div class="source-content">
                                            <strong>Open Camera</strong>
                                            <span>Take a live snapshot</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Camera Interface -->
                                <div id="camera-interface" style="display: none; flex-direction: column; align-items: center; gap: 10px; background: var(--bg-input); padding: 15px; border-radius: var(--radius-md); border: 1px solid var(--border); margin-bottom: 10px;">
                                    <video id="camera-stream" autoplay playsinline style="width: 100%; max-width: 400px; border-radius: var(--radius-sm); background: #000;"></video>
                                    <div style="display: flex; gap: 10px; width: 100%; max-width: 400px;">
                                        <button type="button" class="btn btn-success" id="capture-btn" style="flex: 1;"><i class="la la-mobile"></i> Snap Photo</button>
                                        <button type="button" class="btn btn-danger" id="close-camera-btn" style="flex: 1;"><i class="la la-times"></i> Close</button>
                                    </div>
                                </div>

                                <canvas id="camera-canvas" style="display: none;"></canvas>
                                
                                <div id="preview-container" style="display: none; position: relative; max-width: 400px; margin: 0 auto;">
                                    <img id="image-preview" class="image-preview" src="" style="width: 100%; border-radius: var(--radius-md); border: 1px solid var(--border);" alt="Preview">
                                    <button type="button" class="btn btn-danger btn-sm" id="clear-image-btn" style="position: absolute; top: 10px; right: 10px; border-radius: 50%; width: 30px; height: 30px; padding: 0; display: flex; align-items: center; justify-content: center;">✕</button>
                                </div>
                            </div>

                            <div style="display: flex; justify-content: flex-end; margin-top: 2rem; margin-bottom: 2rem;  " >
                                <button type="submit" class="btn btn-primary" style="padding: 0.8rem 2.5rem; font-size: 1.05rem; border-radius: 30px;">
                                    Submit Complaint
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        // --- MAP LOGIC ---
        // Start with a neutral world view, then immediately zoom to the user's real GPS location
        let map = L.map('map').setView([20, 0], 2);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        const locationInput = document.getElementById('location');
        let marker = null;

        // Pulsing Live Icon
        const liveIcon = L.divIcon({
            className: 'live-marker',
            html: '<div class="live-dot-pulse"></div><div class="live-dot"></div>',
            iconSize: [14, 14],
            iconAnchor: [7, 7]
        });

        // Accuracy circle layer
        let accuracyCircle = null;
        let watchId = null;
        let bestAccuracy = Infinity;
        let hasUserInteracted = false;

        // Set map to a lat/lng — creates/moves marker and accuracy circle
        function setMapLocation(lat, lng, accuracy, zoomLevel) {
            locationInput.value = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
            
            // Auto-center only if user hasn't scrolled away manually
            if (!hasUserInteracted) {
                map.setView([lat, lng], zoomLevel || map.getZoom() || 18);
            }

            // Create or move the draggable pin
            if (marker) {
                marker.setLatLng([lat, lng]);
            } else {
                marker = L.marker([lat, lng], { draggable: true, icon: liveIcon }).addTo(map);
                marker.on('dragstart', () => { hasUserInteracted = true; });
                marker.on('dragend', function() {
                    const pos = marker.getLatLng();
                    locationInput.value = `${pos.lat.toFixed(6)}, ${pos.lng.toFixed(6)}`;
                    if (accuracyCircle) { accuracyCircle.remove(); accuracyCircle = null; }
                });
            }

            // Draw/update accuracy radius circle
            if (accuracy) {
                if (accuracyCircle) {
                    accuracyCircle.setLatLng([lat, lng]).setRadius(accuracy);
                } else {
                    accuracyCircle = L.circle([lat, lng], {
                        radius: accuracy,
                        color: '#2563eb',
                        fillColor: '#2563eb',
                        fillOpacity: 0.1,
                        weight: 1,
                        dashArray: '4'
                    }).addTo(map);
                }
            }
        }

        // Detect user interaction with map
        map.on('mousedown dragstart zoomstart', function() {
            hasUserInteracted = true;
        });

        // When map is clicked — move the pin, stop any GPS watch
        map.on('click', function(e) {
            hasUserInteracted = true;
            if (watchId !== null) { navigator.geolocation.clearWatch(watchId); watchId = null; }
            if (accuracyCircle) { accuracyCircle.remove(); accuracyCircle = null; }
            setMapLocation(e.latlng.lat, e.latlng.lng, null, map.getZoom());
        });

        // Core locate using watchPosition — keeps refining until accuracy <= 50m
        function autoLocate(showFeedback) {
            const btn = document.getElementById('btn-get-location');
            if (!('geolocation' in navigator)) {
                if (showFeedback) showToast('Geolocation is not supported by your browser.', 'error');
                return;
            }

            // Reset interaction on explicit button click
            if (showFeedback) hasUserInteracted = false;

            // Stop any previous watch
            if (watchId !== null) { navigator.geolocation.clearWatch(watchId); watchId = null; }
            bestAccuracy = Infinity;

            if (showFeedback) {
                btn.innerHTML = '<i class="la la-clock-o"></i> Acquiring GPS...';
                btn.disabled = true;
            }

            // High Precision Options
            const geoOptions = {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            };

            watchId = navigator.geolocation.watchPosition(
                (position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    const accuracy = position.coords.accuracy;

                    // Update if this is better or at least acceptable
                    if (accuracy < bestAccuracy || bestAccuracy === Infinity) {
                        bestAccuracy = accuracy;
                        // For extremely high accuracy, zoom in more
                        const zoom = accuracy < 20 ? 19 : accuracy < 50 ? 18 : accuracy < 150 ? 17 : 15;
                        setMapLocation(lat, lng, accuracy, zoom);
                        if (showFeedback) btn.innerHTML = `📡 Locating (±${Math.round(accuracy)}m)`;
                    }

                    // Perfect fix
                    if (accuracy <= 15) {
                        if (showFeedback) {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="la la-map-marker"></i> Locate Me';
                            showToast('High-accuracy location locked!', 'success');
                        }
                    }
                },
                (error) => {
                    if (showFeedback) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="la la-map-marker"></i> Locate Me';
                        showToast('Unable to get precise location. Pin manually.', 'warning');
                    }
                },
                geoOptions
            );

            // Timeout after 20s if no good fix
            setTimeout(() => {
                if (showFeedback && btn.disabled) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="la la-map-marker"></i> Locate Me';
                }
            }, 20000);
        }

        // --- SEARCH / GEOCODING LOGIC ---
        const searchInput = document.getElementById('map-search-input');
        const searchBtn = document.getElementById('btn-search-go');
        const resultsDiv = document.getElementById('search-results');

        async function performSearch() {
            const query = searchInput.value.trim();
            if (query.length < 3) return;
            
            searchBtn.innerHTML = '...';
            try {
                const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5`);
                const data = await response.json();
                
                if (data.length > 0) {
                    resultsDiv.innerHTML = '';
                    resultsDiv.style.display = 'block';
                    data.forEach(place => {
                        const item = document.createElement('div');
                        item.style.padding = '10px 15px';
                        item.style.cursor = 'pointer';
                        item.style.fontSize = '0.85rem';
                        item.style.borderBottom = '1px solid #f0f0f0';
                        item.innerText = place.display_name;
                        item.onmouseover = () => item.style.background = '#f8faff';
                        item.onmouseout = () => item.style.background = 'transparent';
                        item.onclick = () => {
                            const lat = parseFloat(place.lat);
                            const lon = parseFloat(place.lon);
                            hasUserInteracted = false; // allow auto-center for this
                            setMapLocation(lat, lon, null, 17);
                            resultsDiv.style.display = 'none';
                            searchBtn.innerHTML = 'Search';
                        };
                        resultsDiv.appendChild(item);
                    });
                } else {
                    showToast('Location not found.', 'warning');
                    searchBtn.innerHTML = 'Search';
                }
            } catch (e) {
                console.error(e);
                searchBtn.innerHTML = 'Search';
            }
        }

        searchBtn.addEventListener('click', performSearch);
        searchInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') { e.preventDefault(); performSearch(); } });
        document.addEventListener('click', (e) => { if (!e.target.closest('#map-search-input')) resultsDiv.style.display = 'none'; });

        // Auto-detect silently on page load
        autoLocate(false);

        // Locate Me button — re-trigger with feedback
        document.getElementById('btn-get-location').addEventListener('click', () => {
            autoLocate(true);
        });

        // --- CAMERA LOGIC ---
        let capturedDataURL = null;
        let stream = null;

        const cameraInterface = document.getElementById('camera-interface');
        const cameraStream = document.getElementById('camera-stream');
        const cameraCanvas = document.getElementById('camera-canvas');
        const imagePreview = document.getElementById('image-preview');
        const previewContainer = document.getElementById('preview-container');
        const openCameraBtn = document.getElementById('open-camera-btn');
        const captureBtn = document.getElementById('capture-btn');
        const closeCameraBtn = document.getElementById('close-camera-btn');
        const clearImageBtn = document.getElementById('clear-image-btn');
        const fileInput = document.getElementById('complaint-image');

        // Normal File Upload Preview
        fileInput.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    previewContainer.style.display = 'block';
                    capturedDataURL = null; // Clear camera capture if file uploaded
                }
                reader.readAsDataURL(this.files[0]);
            }
        });

        // Open Camera
        openCameraBtn.addEventListener('click', async () => {
            try {
                const constraints = { video: { facingMode: 'environment' } };
                stream = await navigator.mediaDevices.getUserMedia(constraints);
                cameraStream.srcObject = stream;
                cameraInterface.style.display = 'flex';
                openCameraBtn.style.display = 'none';
                previewContainer.style.display = 'none'; // hide preview while camera is open
            } catch (err) {
                console.error(err);
                showToast('Camera access denied or unavailable.', 'error');
            }
        });

        // Close Camera
        closeCameraBtn.addEventListener('click', () => {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
            cameraInterface.style.display = 'none';
            openCameraBtn.style.display = 'inline-block';
        });

        // Capture Frame
        captureBtn.addEventListener('click', () => {
            cameraCanvas.width = cameraStream.videoWidth;
            cameraCanvas.height = cameraStream.videoHeight;
            cameraCanvas.getContext('2d').drawImage(cameraStream, 0, 0);
            
            capturedDataURL = cameraCanvas.toDataURL('image/jpeg');
            imagePreview.src = capturedDataURL;
            previewContainer.style.display = 'block';
            
            // Close camera after capture
            closeCameraBtn.click();
            
            // Clear file input so it doesn't conflict
            fileInput.value = '';
        });

        // Clear Image
        clearImageBtn.addEventListener('click', () => {
            capturedDataURL = null;
            fileInput.value = '';
            imagePreview.src = '';
            previewContainer.style.display = 'none';
        });

        document.getElementById('complaintForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            if (!validateComplaintForm(this)) return;

            const formData = new FormData(this);
            
            // If camera was used, convert base64 to Blob and override the image field
            if (capturedDataURL) {
                formData.delete('image');
                try {
                    const res = await fetch(capturedDataURL);
                    const blob = await res.blob();
                    formData.append('image', blob, 'camera_capture.jpg');
                } catch (e) {
                    console.error("Error converting camera data to blob", e);
                }
            }

            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';

            const result = await postForm('../api/submit_complaint.php', formData);

            if (result.success) {
                showToast(result.message, 'success');
                setTimeout(() => window.location.href = 'my_complaints.php', 1500);
            } else {
                showToast(result.message, 'error');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Complaint';
            }
        });

        // --- SUBCATEGORY LOGIC ---
        const subCategories = {
            "Corruption & Bribery": ["Demand for bribe", "Misuse of official position", "Favoritism/Nepotism", "Embezzlement of funds", "Other"],
            "Roads & Infrastructure": ["Potholes", "Road damage/Crack", "Incomplete road work", "Poor quality of road construction", "Illegal road blockage/Encroachment", "Other"],
            "Water Supply Issues": ["No water supply", "Leakage in pipeline", "Contaminated/Dirty water", "Low water pressure", "Unauthorized water connection", "Tanker related issues", "Other"],
            "Electricity Problems": ["Frequent power outage", "Fluctuating voltage", "Hanging/Dangling wires", "Faulty electricity meter", "Street light not working", "New connection delay", "Other"],
            "Sanitation & Garbage": ["Garbage not collected", "Overflowing dustbins", "Open drains/Sewage overflow", "Public toilet cleanliness", "Dead animal disposal", "Illegal dumping", "Other"],
            "Public Transport Issues": ["Bus/Train delay", "Rough driving", "Rude behavior of staff", "Overcrowding", "Lack of proper bus stops", "Auto/Taxi refusal or overcharging", "Other"],
            "Healthcare Complaints": ["Lack of medicines/facilities", "Rude behavior of medical staff", "Overcharging in hospitals", "Poor cleanliness in health centers", "Long waiting hours", "Ambulance availability", "Other"],
            "Education System Issues": ["Poor school infrastructure", "Teacher absenteeism", "High/Hidden fees", "Mid-day meal quality", "Harassment/Bullying issues", "Delay in scholarship/certificates", "Other"],
            "Police Misconduct / Law & Order": ["Refusal to file FIR", "Demand for bribe by police", "Harassment/Threats", "Inaction on complaints", "Traffic police issues", "Lack of patrolling in area", "Other"],
            "Government Scheme Issues": ["Delay in scheme benefits", "Incorrect eligibility assessment", "Missing names in beneficiary list", "Middlemen interference", "Technical issues in online portal", "Other"],
            "Land & Property Disputes": ["Illegal encroachment on land", "Property registration issues", "Incorrect land records", "Delay in mutation process", "Property tax dispute", "Other"],
            "Cybercrime / Online Fraud": ["Online financial fraud", "Identity theft/Phishing", "Hacking of accounts", "Cyber bullying/Harassment", "Fake social media profile", "Other"],
            "Environmental Issues": ["Illegal tree cutting", "Air/Noise pollution", "Water body pollution", "Plastic waste violation", "Encroachment on forest/park land", "Other"],
            "Women & Child Safety": ["Harassment in public places", "Domestic violence assistance", "Child labor reporting", "Missing child help", "Safety concerns in specific areas", "Other"],
            "Municipal Services": ["Birth/Death certificate delay", "Property tax assessment", "Building plan approval issues", "Trade license delay", "Park maintenance", "Animal nuisance (stray dogs/cattle)", "Other"],
            "Tax / Revenue Issues": ["Income tax related queries", "GST related problems", "Service tax issues", "Customs/Excise delays", "Revenue department inaction", "Other"],
            "Other": ["General inquiry", "Uncategorized issue", "Miscellaneous"]
        };

        function updateSubcategories() {
            const categorySelect = document.getElementById('category');
            const subcategorySelect = document.getElementById('subcategory');
            const subcategoryGroup = document.getElementById('subcategory-group');
            const selectedCategory = categorySelect.value;

            // Clear previous options
            subcategorySelect.innerHTML = '<option value="">Select sub-category...</option>';

            if (selectedCategory && subCategories[selectedCategory]) {
                subCategories[selectedCategory].forEach(sub => {
                    const option = document.createElement('option');
                    option.value = sub;
                    option.textContent = sub;
                    subcategorySelect.appendChild(option);
                });
                subcategoryGroup.style.display = 'block';
                subcategorySelect.required = true;
            } else {
                subcategoryGroup.style.display = 'none';
                subcategorySelect.required = false;
            }
        }
    </script>
</body>
</html>
