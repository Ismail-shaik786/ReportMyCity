<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-inner">
            <img src="../assets/images/govt_emblem.png" class="sidebar-emblem" alt="Gov Emblem">
            <div class="sidebar-brand-text">
                <span>Republic of India</span>
                <h2>ReportMyCity India</h2>
            </div>
        </div>
        <div class="sidebar-gold-stripe"></div>
    </div>

    <div class="sidebar-nav">
        <div class="sidebar-section-label">Main Console</div>
        <a href="<?php echo ($_SESSION['role'] === 'national_admin') ? 'dashboard.php' : 'admin_dashboard.php'; ?>" class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['dashboard.php', 'admin_dashboard.php']) ? 'active' : ''; ?>">
            <i class="la la-bar-chart-o"></i> Dashboard
        </a>
        <a href="manage_complaints.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_complaints.php' ? 'active' : ''; ?>">
            <i class="la la-list-alt"></i> Complaints
        </a>
        <a href="manage_users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'active' : ''; ?>">
            <i class="la la-users"></i> Citizens
        </a>
        <?php if ($_SESSION['role'] === 'state_admin' || $_SESSION['role'] === 'national_admin'): ?>
            <a href="manage_departments.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_departments.php' ? 'active' : ''; ?>">
                <i class="la la-building-o"></i> Departments
            </a>
        <?php endif; ?>
        <a href="manage_officers.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_officers.php' ? 'active' : ''; ?>">
            <i class="la la-shield"></i> Officials
        </a>
        <a href="workforce_tree.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'workforce_tree.php' ? 'active' : ''; ?>">
            <i class="la la-sitemap"></i> Workforce Tree
        </a>

        <div class="sidebar-section-label">Audits & Reports</div>
        <?php 
            $officerRCount = isset($officerReportsCount) ? $officerReportsCount : 0; 
            $userRCount = isset($userReportsCount) ? $userReportsCount : 0;
        ?>
        <a href="manage_officer_reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_officer_reports.php' ? 'active' : ''; ?>">
            <i class="la la-shield"></i> Officer Audits 
            <?php if($officerRCount > 0): ?>
                <span style="background:var(--danger); color:white; padding: 2px 6px; border-radius: 10px; font-size: 0.65rem; margin-left: 5px;"><?php echo $officerRCount; ?></span>
            <?php endif; ?>
        </a>
        <a href="manage_user_reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_user_reports.php' ? 'active' : ''; ?>">
            <i class="la la-flag-o"></i> Fake Audits 
            <?php if($userRCount > 0): ?>
                <span style="background:var(--warning); color:var(--gov-navy); padding: 2px 6px; border-radius: 10px; font-size: 0.65rem; margin-left: 5px;"><?php echo $userRCount; ?></span>
            <?php endif; ?>
        </a>
        
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar"><?php echo isset($initials) ? $initials : strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)); ?></div>
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
                <span class="sidebar-user-role"><i class="la la-globe"></i> <?php echo ucwords(str_replace('_', ' ', $_SESSION['role'] ?? 'admin')); ?></span>
            </div>
        </div>
        <a href="../logout.php" class="logout-link" style="color:var(--danger); display:block; text-align:center; margin-top:0.8rem; font-size:0.85rem; text-decoration:none;"><i class="la la-sign-out"></i> Logout</a>
    </div>
</aside>
