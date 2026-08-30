<?php
require_once __DIR__ . '/../config/config.php';

$current_script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
if (strpos($current_script, '/skillsync/') !== false) {
    $base_url = '/skillsync/';
} else {
    $base_url = '/';
}

// Fetch live notifications strictly for current session (user or company)
$headerNotifs = [];
if (isset($pdo)) {
    $curUId = $_SESSION['user_id'] ?? null;
    $curCId = $_SESSION['company_id'] ?? null;

    if (!empty($curUId)) {
        // Auto-check candidate calendar events (Day Before & Event Day, excluding suspended companies)
        try {
            // 1. Day Before Event Notifications
            $stmtEveTom = $pdo->prepare("
                SELECT c.calendarid, c.activityName, c.activityDate 
                FROM calendar c
                LEFT JOIN company comp ON c.companyid = comp.companyid
                LEFT JOIN (
                    SELECT DISTINCT aj.userid, v.companyid, v.jobTitle, c_sub.accountStatus AS compStatus
                    FROM appliedJobs aj
                    JOIN vacancy v ON aj.vacancyid = v.vacancyid
                    JOIN company c_sub ON v.companyid = c_sub.companyid
                ) app_comp ON c.companyid IS NULL AND c.userid = app_comp.userid AND (c.activityName LIKE CONCAT('%', app_comp.jobTitle, '%'))
                WHERE c.userid = ? AND c.activityDate = CURDATE() + INTERVAL 1 DAY
                  AND (c.companyid IS NULL OR comp.accountStatus != 'Suspended' OR comp.accountStatus IS NULL)
                  AND (app_comp.compStatus IS NULL OR app_comp.compStatus != 'Suspended')
                GROUP BY c.calendarid
            ");
            $stmtEveTom->execute([$curUId]);
            $eventsTomorrow = $stmtEveTom->fetchAll(PDO::FETCH_ASSOC);

            foreach ($eventsTomorrow as $eTom) {
                $actTitle = $eTom['activityName'] ?? 'Scheduled Event';
                $notifDesc = "Upcoming Event Reminder (Tomorrow): " . $actTitle;
                $chkNotif = $pdo->prepare("SELECT notificationid FROM notification WHERE userid = ? AND notificationDescription = ?");
                $chkNotif->execute([$curUId, $notifDesc]);
                if (!$chkNotif->fetch()) {
                    $insNotif = $pdo->prepare("INSERT INTO notification (userid, notificationDescription, notificationDate, notificationTime) VALUES (?, ?, CURDATE(), CURTIME())");
                    $insNotif->execute([$curUId, $notifDesc]);
                }
            }

            // 2. On the Event Day Notifications
            $stmtEveTod = $pdo->prepare("
                SELECT c.calendarid, c.activityName, c.activityDate 
                FROM calendar c
                LEFT JOIN company comp ON c.companyid = comp.companyid
                LEFT JOIN (
                    SELECT DISTINCT aj.userid, v.companyid, v.jobTitle, c_sub.accountStatus AS compStatus
                    FROM appliedJobs aj
                    JOIN vacancy v ON aj.vacancyid = v.vacancyid
                    JOIN company c_sub ON v.companyid = c_sub.companyid
                ) app_comp ON c.companyid IS NULL AND c.userid = app_comp.userid AND (c.activityName LIKE CONCAT('%', app_comp.jobTitle, '%'))
                WHERE c.userid = ? AND c.activityDate = CURDATE()
                  AND (c.companyid IS NULL OR comp.accountStatus != 'Suspended' OR comp.accountStatus IS NULL)
                  AND (app_comp.compStatus IS NULL OR app_comp.compStatus != 'Suspended')
                GROUP BY c.calendarid
            ");
            $stmtEveTod->execute([$curUId]);
            $eventsToday = $stmtEveTod->fetchAll(PDO::FETCH_ASSOC);

            foreach ($eventsToday as $eTod) {
                $actTitle = $eTod['activityName'] ?? 'Scheduled Event';
                $notifDesc = "Event Alert (Today): " . $actTitle;
                $chkNotif = $pdo->prepare("SELECT notificationid FROM notification WHERE userid = ? AND notificationDescription = ?");
                $chkNotif->execute([$curUId, $notifDesc]);
                if (!$chkNotif->fetch()) {
                    $insNotif = $pdo->prepare("INSERT INTO notification (userid, notificationDescription, notificationDate, notificationTime) VALUES (?, ?, CURDATE(), CURTIME())");
                    $insNotif->execute([$curUId, $notifDesc]);
                }
            }
        } catch (PDOException $ex) {
            // Silence calendar check failures
        }

        $stmtHNotif = $pdo->prepare("SELECT * FROM notification WHERE userid = ? ORDER BY notificationid DESC LIMIT 6");
        $stmtHNotif->execute([$curUId]);
        $headerNotifs = $stmtHNotif->fetchAll(PDO::FETCH_ASSOC);
    } elseif (!empty($curCId)) {
        $stmtHNotif = $pdo->prepare("SELECT * FROM notification WHERE companyid = ? ORDER BY notificationid DESC LIMIT 6");
        $stmtHNotif->execute([$curCId]);
        $headerNotifs = $stmtHNotif->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkillSync - Your Future Guidance Platform</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="<?=$base_url?>favicon.webp">
    
    <!-- Google Fonts - Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Custom CSS & Responsive Engine & Toast Engine & Validation -->
    <link rel="stylesheet" href="<?=$base_url?>assets/css/style.css">
    <link rel="stylesheet" href="<?=$base_url?>assets/css/device.css">
    <script src="<?=$base_url?>assets/js/toast.js"></script>
    <script src="<?=$base_url?>assets/js/validation.js"></script>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-skillsync sticky-top">
    <div class="container-fluid header-container-padding">
        <!-- Logo -->
        <a class="navbar-brand d-flex align-items-center me-4 py-1" href="<?=$base_url?>">
            <img src="<?=$base_url?>assets/img/logo-white.webp" alt="SkillSync Logo" height="42" class="d-block brand-logo-img">
        </a>

        <!-- Mobile Toggle Button -->
        <button class="navbar-toggler rounded-4px border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMainContent" aria-controls="navbarMainContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navbar Menu & Actions -->
        <div class="collapse navbar-collapse" id="navbarMainContent">
            <!-- Navigation Links -->
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?= (basename($current_script) == 'index.php' && (strpos($current_script, '/skillsync/index.php') !== false || $current_script == '/index.php')) ? 'active' : '' ?>" href="<?=$base_url?>">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?= (strpos($current_script, '/career-path/') !== false) ? 'active' : '' ?>" href="<?=$base_url?>career-path/">Career Paths</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?= (strpos($current_script, '/job-scout/') !== false) ? 'active' : '' ?>" href="<?=$base_url?>job-scout/">Job Scout</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?= (strpos($current_script, '/Intervia/') !== false) ? 'active' : '' ?>" href="<?=$base_url?>Intervia/">Intervia</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?= (strpos($current_script, '/atsync/') !== false) ? 'active' : '' ?>" href="<?=$base_url?>atsync/">ATSync</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?= (strpos($current_script, '/profile-pro/') !== false) ? 'active' : '' ?>" href="<?=$base_url?>profile-pro/">ProfilePro</a>
                </li>
            </ul>

            <!-- Right Nav Icons & Button -->
            <div class="d-flex align-items-center gap-3">
                <!-- Notification Dropdown -->
                <div class="dropdown">
                    <button class="header-icon-btn" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                        <i class="bi bi-bell"></i>
                        <?php if (!empty($headerNotifs)): ?>
                            <span class="notification-badge"><?= count($headerNotifs) ?></span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-8px p-2 mt-2" aria-labelledby="notificationDropdown" style="width: 320px; max-height: 380px; overflow-y: auto;">
                        <li class="dropdown-header font-weight-bold text-dark border-bottom pb-2 d-flex justify-content-between align-items-center">
                            <span>Notifications</span>
                            <span class="badge bg-dark text-accent font-monospace" style="font-size: 0.7rem;"><?= count($headerNotifs) ?> New</span>
                        </li>
                        <?php if (!empty($headerNotifs)): ?>
                            <?php foreach ($headerNotifs as $n): ?>
                                <?php 
                                    $desc = $n['notificationDescription'] ?? '';
                                    $iconClass = 'bi-bell-fill text-info';
                                    if (strpos($desc, 'Accepted') !== false || strpos($desc, 'Confirmed') !== false) {
                                        $iconClass = 'bi-check-circle-fill text-success';
                                    } elseif (strpos($desc, 'Rejected') !== false) {
                                        $iconClass = 'bi-x-circle-fill text-danger';
                                    } elseif (strpos($desc, 'New Job') !== false || strpos($desc, 'Opening') !== false) {
                                        $iconClass = 'bi-briefcase-fill text-primary';
                                    } elseif (strpos($desc, 'Scheduled') !== false || strpos($desc, 'Interview') !== false) {
                                        $iconClass = 'bi-calendar-check-fill text-warning';
                                    }
                                    $dateStr = !empty($n['notificationDate']) ? date('M d', strtotime($n['notificationDate'])) : 'Today';
                                ?>
                                <li>
                                    <div class="dropdown-item rounded-4px small py-2 mt-1 border-bottom text-wrap">
                                        <div class="d-flex align-items-start gap-2">
                                            <i class="bi <?= $iconClass ?> fs-6 mt-1"></i>
                                            <div class="flex-grow-1">
                                                <div class="text-dark fw-semibold" style="line-height: 1.3; font-size: 0.82rem;"><?= htmlspecialchars($desc) ?></div>
                                                <small class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($dateStr) ?> <?= htmlspecialchars($n['notificationTime'] ?? '') ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="p-3 text-center text-muted small">No notifications available.</li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Profile Icon Link -->
                <a href="<?=$base_url?>user-profile/" class="header-icon-btn d-flex align-items-center justify-content-center text-white text-decoration-none" title="My Profile">
                    <i class="bi bi-person-circle fs-5"></i>
                </a>

                <!-- Get Started / Sign Out Button -->
                <?php if (!empty($_SESSION['user_type'])): ?>
                    <a href="<?=$base_url?>includes/auth.php" class="btn btn-accent rounded-8px"><i class="bi bi-box-arrow-right me-1"></i> Sign Out</a>
                <?php else: ?>
                    <a href="<?=$base_url?>login/" class="btn btn-accent rounded-8px"> Get started</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
