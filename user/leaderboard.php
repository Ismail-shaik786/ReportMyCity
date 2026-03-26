<?php
/**
 * ReportMyCity — User Leaderboard
 */
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance();
$usersCol = $db->getCollection('users');

$userId = $_SESSION['user_id'];
$userDoc = $usersCol->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
$currentState = $userDoc['state'] ?? 'National';
$currentDistrict = $userDoc['district'] ?? 'All';

// Top 20 National
$nationalTop = $usersCol->find(
    ['role' => 'user'],
    ['sort' => ['points' => -1], 'limit' => 20]
);

// Top 20 State
$stateTop = $usersCol->find(
    ['role' => 'user', 'state' => $currentState],
    ['sort' => ['points' => -1], 'limit' => 20]
);

// Top 20 District
$districtTop = $usersCol->find(
    ['role' => 'user', 'district' => $currentDistrict],
    ['sort' => ['points' => -1], 'limit' => 20]
);

$userName = $_SESSION['user_name'] ?? 'User';
$userPhoto = $userDoc['photo'] ?? '';
$initials = strtoupper(substr($userName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Civic Leaderboard — ReportMyCity India</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .leaderboard-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 1rem;
        }
        .tab-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            background: transparent;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .tab-btn.active {
            background: var(--gov-navy);
            color: white;
            box-shadow: var(--shadow-md);
        }
        .rank-medal {
            font-size: 1.5rem;
            width: 40px;
            text-align: center;
        }
        .leader-row {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding: 1.25rem;
            background: white;
            border-radius: 12px;
            margin-bottom: 1rem;
            border: 1px solid var(--border);
            transition: transform 0.3s;
        }
        .leader-row:hover { transform: translateX(5px); }
        .leader-row.me {
            border: 2px solid var(--gov-gold);
            background: var(--gov-gold-glow);
        }
    </style>
</head>
<body>
    <div class="gov-animation-layer"><div class="gov-grid"></div></div>

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
                <a href="dashboard.php"><span class="nav-icon"><i class="la la-th-large"></i></span> Dashboard</a>
                <a href="leaderboard.php" class="active"><span class="nav-icon"><i class="la la-star-o"></i></span> Leaderboard</a>
                <a href="submit_complaint.php"><span class="nav-icon"><i class="la la-pencil-square-o"></i></span> Submit Complaint</a>
                <a href="my_complaints.php"><span class="nav-icon"><i class="la la-list-alt"></i></span> My Complaints</a>
                <a href="profile.php"><span class="nav-icon"><i class="la la-user-o"></i></span> My Profile</a>
            </nav>
            <div class="sidebar-footer">
                <a href="../logout.php"><i class="la la-sign-out"></i> Logout</a>
            </div>
        </aside>

        <!-- Main -->
        <main class="main-content">
            <div class="page-header">
                <div class="header-left">
                    <button class="sidebar-toggle" id="menu-toggle"><i class="la la-bars"></i></button>
                    <div class="header-logo-group">
                        <img src="../assets/images/govt_emblem.png" alt="Emblem">
                        <span>ReportMyCity</span>
                    </div>
                    <h1>Civic Hero Leaderboard</h1>
                </div>
                <div class="user-info">
                   <span>Welcome, <?php echo htmlspecialchars($userName); ?></span>
                </div>
            </div>

            <div class="page-body" style="padding: 2rem;">
                <div class="leaderboard-tabs">
                    <button class="tab-btn active" onclick="showLeaderboard('national', this)">National</button>
                    <button class="tab-btn" onclick="showLeaderboard('state', this)">State: <?php echo $currentState; ?></button>
                    <button class="tab-btn" onclick="showLeaderboard('district', this)">District: <?php echo $currentDistrict; ?></button>
                </div>

                <div id="national" class="leader-list">
                    <?php renderLeaderboard($nationalTop, $userId); ?>
                </div>

                <div id="state" class="leader-list" style="display: none;">
                    <?php renderLeaderboard($stateTop, $userId); ?>
                </div>

                <div id="district" class="leader-list" style="display: none;">
                    <?php renderLeaderboard($districtTop, $userId); ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function showLeaderboard(id, btn) {
            document.querySelectorAll('.leader-list').forEach(l => l.style.display = 'none');
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(id).style.display = 'block';
            btn.classList.add('active');
        }

        document.querySelector('.sidebar-toggle').addEventListener('click', () => {
             document.querySelector('.sidebar').classList.toggle('open');
        });
    </script>
</body>
</html>

<?php
function renderLeaderboard($cursor, $currentUserId) {
    if (!$cursor) {
        echo "<div class='empty-state'><p>No heroes found in this category yet.</p></div>";
        return;
    }
    $index = 1;
    foreach ($cursor as $doc) {
        $isMe = (string)$doc['_id'] === $currentUserId;
        $rankIcon = $index;
        if ($index === 1) $rankIcon = '🥇';
        if ($index === 2) $rankIcon = '🥈';
        if ($index === 3) $rankIcon = '🥉';
        
        echo '<div class="leader-row ' . ($isMe ? 'me' : '') . '">';
        echo '<div class="rank-medal">' . $rankIcon . '</div>';
        echo '<div class="hero-avatar" style="width: 34px; height: 34px; border-radius: 50%; background: #fff; overflow: hidden; border: 2px solid var(--gov-gold); flex-shrink: 0; box-shadow: 0 2px 5px rgba(0,0,0,0.08);">';
        if (!empty($doc['photo'])) {
            echo '<img src="../' . htmlspecialchars($doc['photo']) . '" style="width:100%; height:100%; object-fit:cover;">';
        } else {
            echo '<div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-weight:800; color:#999;">' . strtoupper(substr($doc['name'], 0, 1)) . '</div>';
        }
        echo '</div>';
        echo '<div class="hero-info" style="flex:1;">';
        echo '<div style="font-size:1.1rem; font-weight:800; color:var(--gov-navy);">' . htmlspecialchars($doc['name']) . ($isMe ? ' <small>(You)</small>' : '') . '</div>';
        echo '<div style="font-size:0.8rem; color:var(--text-muted); font-weight:600;">' . ($doc['level'] ?? 'Beginner') . '</div>';
        echo '</div>';
        echo '<div class="hero-points" style="text-align:right;">';
        echo '<div style="font-size:1.4rem; font-weight:900; color:var(--gov-gold);">' . ($doc['points'] ?? 0) . '</div>';
        echo '<div style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase;">CIVIC POINTS</div>';
        echo '</div>';
        echo '</div>';
        $index++;
    }
}
?>
