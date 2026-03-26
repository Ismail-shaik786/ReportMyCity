<?php
/**
 * ReportMyCity — User Profile
 */
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['user', 'officer'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$role = $_SESSION['role'];
$collectionName = ($role === 'user') ? 'users' : 'officers';
$collection = $db->getCollection($collectionName);
$userId = $_SESSION['user_id'];

try {
    $userDoc = $collection->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
} catch (Exception $e) {
    echo "Invalid user ID.";
    exit;
}

if (!$userDoc) {
    echo "User not found.";
    exit;
}

$userName = $userDoc['name'] ?? 'User';
$userEmail = $userDoc['email'] ?? '';
$userPhone = $userDoc['phone'] ?? '';
$userPhoto = $userDoc['photo'] ?? '';
$initials = strtoupper(substr($userName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — ReportMyCity</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root {
            --profile-bg: #f8faff;
            --accent-glow: rgba(10, 37, 88, 0.1);
        }

        .profile-wrapper {
            max-width: 900px;
            margin: 1.5rem auto;
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 2rem;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Profile Left - Avatar & Status */
        .profile-sidebar-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            padding: 2.5rem 1.5rem;
            text-align: center;
            box-shadow: var(--shadow-md);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .profile-avatar-container {
            position: relative;
            width: 140px;
            height: 140px;
            margin: 0 auto 1.5rem;
        }

        .profile-avatar-preview {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 0 0 1px var(--border), var(--shadow-md);
            background: var(--gov-navy);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 3rem;
            font-weight: 800;
            overflow: hidden;
            transition: var(--transition);
        }

        .profile-avatar-container:hover .profile-avatar-preview {
            filter: brightness(0.85);
        }

        .avatar-edit-btn {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 36px;
            height: 36px;
            background: var(--gov-gold);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
            border: 3px solid #fff;
            transition: var(--transition);
            z-index: 10;
        }

        .avatar-edit-btn:hover {
            transform: scale(1.1);
            background: var(--gov-gold-light);
        }

        .profile-info-lite h2 {
            margin-bottom: 0.25rem;
            font-size: 1.25rem;
        }

        .profile-info-lite p {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
        }

        .account-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 1rem;
            background: var(--success-bg);
            color: var(--success);
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: 1px solid var(--success-border);
        }

        /* Profile Right - Form */
        .profile-main-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            padding: 2.5rem;
            box-shadow: var(--shadow-md);
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .section-header .icon-box {
            width: 40px;
            height: 40px;
            background: var(--gov-navy-light);
            color: #fff;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .section-header h3 {
            margin: 0;
            font-family: var(--font-serif);
            color: var(--gov-navy);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-full { grid-column: 1 / -1; }

        .profile-container { max-width: none; padding: 0; border: none; box-shadow: none; background: transparent; }

        @media (max-width: 850px) {
            .profile-wrapper { grid-template-columns: 1fr; }
            .profile-sidebar-card { position: static; }
        }

        /* File input hidden */
        #photo-input { display: none; }
        
        /* Premium inputs */
        .form-group input {
            padding: 0.8rem 1rem;
            border-radius: var(--radius-lg);
            border: 2px solid #edf2f7;
            background: #fcfdfe;
        }
        
        .form-group input:focus {
            background: #fff;
            border-color: var(--gov-navy-light);
            box-shadow: 0 4px 12px rgba(10,37,88,0.08);
        }

        .save-btn-container {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
        }
        
        .btn-premium {
            padding: 0.9rem 2.5rem;
            font-size: 1rem;
            border-radius: var(--radius-lg);
            background: linear-gradient(135deg, var(--gov-navy), var(--gov-navy-light));
            box-shadow: 0 4px 15px rgba(10,37,88,0.25);
            transition: all 0.3s ease;
        }
        
        .btn-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(10,37,88,0.35);
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="sidebar-brand-inner">
                    <img src="../assets/images/govt_emblem.png" alt="Emblem" class="sidebar-emblem">
                    <div class="sidebar-brand-text">
                        <h2>ReportMyCity</h2>
                        <span><?php echo ($role === 'user') ? 'Citizen Portal' : 'Officer Portal'; ?></span>
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
                <?php if ($role === 'user'): ?>
                <a href="submit_complaint.php">
                    <span class="nav-icon"><i class="la la-pencil-square-o"></i></span> Submit Complaint
                </a>
                <a href="my_complaints.php">
                    <span class="nav-icon"><i class="la la-list-alt"></i></span> My Complaints
                </a>
                <?php endif; ?>
                <a href="profile.php" class="active">
                    <span class="nav-icon"><i class="la la-user-o"></i></span> My Profile
                </a>
                <?php if ($role === 'user'): ?>
                <div class="sidebar-section-label" style="margin-top:1.5rem; color:#ef4444;"><i class="la la-shield"></i> Oversight</div>
                <a href="my_complaints.php" style="color:#ef4444; background: rgba(239, 68, 68, 0.05); border: 1px dashed rgba(239, 68, 68, 0.2);">
                    <span class="nav-icon"><i class="la la-shield"></i></span> Report Officer Conduct
                </a>
                <?php else: ?>
                <div class="sidebar-section-label" style="margin-top:1.5rem; color:#ef4444;"><i class="la la-shield"></i> Oversight</div>
                <a href="my_assignments.php" style="color:#ef4444; background: rgba(239, 68, 68, 0.05); border: 1px dashed rgba(239, 68, 68, 0.2);">
                    <span class="nav-icon"><i class="la la-flag-o"></i></span> Flag Improper User
                </a>
                <?php endif; ?>
            </nav>
            <div class="sidebar-footer">
                <div class="sidebar-user-info">
                    <div class="sidebar-user-avatar" id="sidebar-avatar">
                        <?php if ($userPhoto): ?>
                            <img src="../<?php echo htmlspecialchars($userPhoto); ?>" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                        <?php else: ?>
                            <?php echo $initials; ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="sidebar-user-name" id="sidebar-name"><?php echo htmlspecialchars($userName); ?></div>
                        <div class="sidebar-user-role"><?php echo ucfirst($role); ?></div>
                    </div>
                </div>
                <a href="../logout.php">
                    <i class="la la-sign-out"></i> Logout
                </a>
            </div>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div class="header-left">
                    <button class="sidebar-toggle"><i class="la la-bars"></i></button>
                    <div class="header-logo-group">
                        <img src="../assets/images/govt_emblem.png" alt="Emblem">
                        <span>ReportMyCity</span>
                    </div>
                    <div>
                        <h1><i class="la la-user-o"></i> Profile Settings</h1>
                        <div class="breadcrumb">
                            <a href="dashboard.php">Dashboard</a>
                            <span>›</span>
                            <span>My Profile</span>
                        </div>
                    </div>
                </div>

                <div class="user-info">
                    <span style="font-size:0.82rem; color:var(--text-muted);"><?php echo date('d M Y'); ?></span>
                    <span id="header-name"><?php echo htmlspecialchars($userName); ?></span>
                <!-- Profile Dropdown -->
                <div class="profile-dropdown-wrapper" id="profileDropdownWrapper">
                    <div class="user-avatar" id="header-avatar">
                        <?php if ($userPhoto): ?>
                            <img src="../<?php echo htmlspecialchars($userPhoto); ?>" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                        <?php else: ?>
                            <?php echo $initials; ?>
                        <?php endif; ?>
                    </div>
                    <div class="profile-dropdown-menu">
                        <div class="profile-dropdown-header">
                            <strong id="dropdown-name"><?php echo htmlspecialchars($userName); ?></strong>
                            <span><?php echo htmlspecialchars($userEmail); ?></span>
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
                <div class="profile-wrapper">
                    <!-- Sidebar Column -->
                    <aside class="profile-sidebar-card">
                        <div class="profile-avatar-container">
                            <div class="profile-avatar-preview" id="profile-preview-box">
                                <?php if ($userPhoto): ?>
                                    <img src="../<?php echo htmlspecialchars($userPhoto); ?>" id="current-photo" style="width:100%; height:100%; object-fit:cover;">
                                <?php else: ?>
                                    <span id="initials-placeholder"><?php echo $initials; ?></span>
                                <?php endif; ?>
                            </div>
                            <label for="photo-input" class="avatar-edit-btn" title="Change Photo">
                                <i class="la la-camera"></i> <i class="la la-picture-o"></i>
                            </label>
                            <input type="file" id="photo-input" accept="image/*">
                        </div>
                        <div class="profile-info-lite">
                            <h2 id="sidebar-display-name"><?php echo htmlspecialchars($userName); ?></h2>
                            <p><?php echo htmlspecialchars($userEmail); ?></p>
                            <div class="account-badge">
                                <i class="la la-shield"></i> Verified <?php echo ucfirst($role); ?>
                            </div>
                        </div>
                        
                        <div style="margin-top: 2rem; text-align: left; padding: 1rem; background: #f8faff; border-radius: 10px; font-size: 0.8rem; color: var(--text-muted);">
                            <div style="margin-bottom: 0.5rem;"><strong>Member Since:</strong> 2024</div>
                            <div><strong>Account Status:</strong> Active</div>
                        </div>
                    </aside>

                    <!-- Main Form Column -->
                    <div class="profile-main-card">
                        <form id="profileForm">
                            <div class="section-header">
                                <div class="icon-box"><i class="la la-user-o"></i></div>
                                <div>
                                    <h3>Personal Details</h3>
                                    <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">Update your public identity and contact info</p>
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="name">Full Name <span class="required">*</span></label>
                                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($userName); ?>" placeholder="e.g. John Doe" required>
                                </div>
                                <div class="form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($userPhone); ?>" placeholder="+91 00000 00000">
                                </div>
                                <div class="form-group form-full">
                                    <label for="email">Email Address <span class="required">*</span></label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userEmail); ?>" placeholder="john@example.com" required>
                                </div>
                            </div>

                            <div class="section-header" style="margin-top: 2.5rem;">
                                <div class="icon-box" style="background: #6b7280;">🔒</div>
                                <div>
                                    <h3>Security</h3>
                                    <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">Manage your access credentials</p>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="password">
                                    <?php echo empty($userDoc['password']) ? 'Set Password' : 'Change Password'; ?>
                                    <small style="font-weight: 400; color:var(--text-muted);">(Leave blank to keep current password)</small>
                                </label>
                                <input type="password" id="password" name="password" placeholder="••••••••" minlength="6">
                            </div>

                            <div class="save-btn-container">
                                <button type="submit" class="btn btn-primary btn-premium">
                                    <span>💾</span> Save All Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        const photoInput = document.getElementById('photo-input');
        const previewBox = document.getElementById('profile-preview-box');
        const sidebarAvatar = document.getElementById('sidebar-avatar');
        const headerAvatar = document.getElementById('header-avatar');
        
        let uploadedPhotoBase64 = null;

        // Photo Preview
        photoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const base64 = event.target.result;
                    uploadedPhotoBase64 = base64;
                    
                    const imgHtml = `<img src="${base64}" style="width:100%; height:100%; object-fit:cover;">`;
                    previewBox.innerHTML = imgHtml;
                    // We don't update sidebar/header yet until saved, or we can for better UX
                };
                reader.readAsDataURL(file);
            }
        });

        document.getElementById('profileForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const password = document.getElementById('password').value;

            if (!name || !email) {
                showToast('Name and email are required.', 'error');
                return;
            }

            const payload = { 
                name, 
                email, 
                phone,
                photo: uploadedPhotoBase64 // Send base64 to API
            };
            if (password) payload.password = password;

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span><i class="la la-clock-o"></i></span> Saving...';

            try {
                const result = await postJSON('../api/update_profile.php', payload);

                if (result.success) {
                    showToast(result.message, 'success');
                    
                    // Update UI text
                    const initials = name.charAt(0).toUpperCase();
                    document.getElementById('sidebar-name').innerText = name;
                    document.getElementById('header-name').innerText = name;
                    document.getElementById('sidebar-display-name').innerText = name;
                    
                    // Update Avatars globally
                    if (uploadedPhotoBase64) {
                        const imgHtml = `<img src="${uploadedPhotoBase64}" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">`;
                        sidebarAvatar.innerHTML = imgHtml;
                        headerAvatar.innerHTML = imgHtml;
                    } else if (!document.querySelector('#sidebar-avatar img')) {
                        sidebarAvatar.innerText = initials;
                        headerAvatar.innerText = initials;
                    }
                } else {
                    showToast(result.message, 'error');
                }
            } catch (err) {
                showToast('An unexpected error occurred.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    </script>
</body>
</html>

