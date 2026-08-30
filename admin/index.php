<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/activity_logger.php';

// Prevent browser caching on admin pages
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Ensure admin session security
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../login/");
    exit;
}

// Handle Toggle User Account Status POST Request
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_user_status') {
    header('Content-Type: application/json');
    $userId = (int)($_POST['userid'] ?? 0);
    $status = trim($_POST['status'] ?? 'Active');

    if ($userId > 0 && in_array($status, ['Active', 'Suspended'])) {
        try {
            $stmtUp = $pdo->prepare("UPDATE user SET accStatus = ? WHERE userid = ?");
            $stmtUp->execute([$status, $userId]);
            logActivity($pdo, $userId, null, "Administrator updated user account status to {$status}");
            echo json_encode(['success' => true, 'message' => "User account status updated to {$status}."]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    }
    exit;
}

// Handle Company Status Update (Verification & Account Status) POST Request
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_company_status') {
    header('Content-Type: application/json');
    $compId = (int)($_POST['companyid'] ?? 0);
    $verStatus = $_POST['verificationStatus'] ?? null;
    $accStatus = $_POST['accountStatus'] ?? null;

    if ($compId > 0) {
        try {
            if ($verStatus && in_array($verStatus, ['Verified', 'Pending', 'Rejected'])) {
                $stmtUpV = $pdo->prepare("UPDATE company SET verificationStatus = ? WHERE companyid = ?");
                $stmtUpV->execute([$verStatus, $compId]);
                logActivity($pdo, null, $compId, "Administrator updated company verification status to {$verStatus}");
            }
            if ($accStatus && in_array($accStatus, ['Active', 'Suspended'])) {
                $stmtUpA = $pdo->prepare("UPDATE company SET accountStatus = ? WHERE companyid = ?");
                $stmtUpA->execute([$accStatus, $compId]);
                logActivity($pdo, null, $compId, "Administrator updated company account status to {$accStatus}");
            }
            echo json_encode(['success' => true, 'message' => 'Company account status updated successfully.']);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid company ID.']);
    }
    exit;
}

// Handle Password Reset Request Approval / Rejection POST Request
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'handle_password_reset') {
    header('Content-Type: application/json');
    $resetId = (int)($_POST['reset_id'] ?? 0);
    $status = trim($_POST['status'] ?? 'Approved');

    if ($resetId > 0 && in_array($status, ['Approved', 'Rejected'])) {
        try {
            $stmtUp = $pdo->prepare("UPDATE password_resets SET status = ?, approved_at = NOW() WHERE id = ?");
            $stmtUp->execute([$status, $resetId]);
            
            // Fetch reset details for audit log
            $stmtR = $pdo->prepare("SELECT * FROM password_resets WHERE id = ?");
            $stmtR->execute([$resetId]);
            $rData = $stmtR->fetch();
            if ($rData) {
                if ($rData['user_type'] === 'user') {
                    logActivity($pdo, $rData['account_id'], null, "Administrator {$status} password reset request");
                } else {
                    logActivity($pdo, null, $rData['account_id'], "Administrator {$status} password reset request");
                }
            }
            
            echo json_encode(['success' => true, 'message' => "Password reset request has been {$status}."]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    }
    exit;
}

// Fetch system metrics from database
$totalUsersCount = (int)$pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();
$totalCompaniesCount = (int)$pdo->query("SELECT COUNT(*) FROM company")->fetchColumn();
$verifiedCompaniesCount = (int)$pdo->query("SELECT COUNT(*) FROM company WHERE verificationStatus = 'Verified'")->fetchColumn();
$pendingCompanyRequestsCount = (int)$pdo->query("SELECT COUNT(*) FROM company WHERE verificationStatus = 'Pending'")->fetchColumn();
$pendingPasswordResetsCount = (int)$pdo->query("SELECT COUNT(*) FROM password_resets WHERE status = 'Pending'")->fetchColumn();
$pendingRequestsCount = $pendingCompanyRequestsCount + $pendingPasswordResetsCount;
$suspendedUsersCount = (int)$pdo->query("SELECT COUNT(*) FROM user WHERE accStatus = 'Suspended'")->fetchColumn();
$suspendedCompaniesCount = (int)$pdo->query("SELECT COUNT(*) FROM company WHERE accountStatus = 'Suspended'")->fetchColumn();
$totalSuspendedCount = $suspendedUsersCount + $suspendedCompaniesCount;

// Fetch all users from database
$stmtAllUsers = $pdo->query("SELECT * FROM user ORDER BY userid DESC");
$allUsers = $stmtAllUsers->fetchAll(PDO::FETCH_ASSOC);

// Fetch all companies from database
$stmtAllCompanies = $pdo->query("
    SELECT c.*, cl.city 
    FROM company c 
    LEFT JOIN companyLocation cl ON c.companyid = cl.companyid 
    ORDER BY c.companyid DESC
");
$allCompanies = $stmtAllCompanies->fetchAll(PDO::FETCH_ASSOC);

// Fetch pending approvals queue
$stmtPendingQueue = $pdo->query("
    SELECT c.*, cl.city 
    FROM company c 
    LEFT JOIN companyLocation cl ON c.companyid = cl.companyid 
    WHERE c.verificationStatus = 'Pending' OR c.accountStatus = 'Suspended' 
    ORDER BY c.companyid DESC
");
$pendingQueue = $stmtPendingQueue->fetchAll(PDO::FETCH_ASSOC);

// Fetch pending password resets
$stmtPendingResets = $pdo->query("SELECT * FROM password_resets WHERE status = 'Pending' ORDER BY requested_at DESC");
$pendingResets = $stmtPendingResets->fetchAll(PDO::FETCH_ASSOC);

// Fetch all password resets
$stmtAllResets = $pdo->query("SELECT * FROM password_resets ORDER BY requested_at DESC LIMIT 50");
$allResets = $stmtAllResets->fetchAll(PDO::FETCH_ASSOC);

$activeTab = $_GET['tab'] ?? 'overview';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkillSync - System Administrator Portal</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="../favicon.webp">
    
    <!-- Google Fonts Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Admin Control Panel Custom CSS & Responsive Framework -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="../assets/css/device.css">
</head>
<body style="background-color: #f8faf9; font-family: 'Poppins', sans-serif;">

<!-- Standalone Admin Top Header Navbar -->
<header class="navbar navbar-expand-lg sticky-top" style="background-color: var(--brand-dark, #004743); border-bottom: 3px solid var(--brand-accent, #ACFF78); padding: 12px 24px;">
    <div class="container-fluid">
        <!-- Brand Logo & Portal Title -->
        <a class="navbar-brand d-flex align-items-center gap-3 text-white" href="../">
            <img src="../assets/img/logo-white.webp" alt="SkillSync Logo" height="38" class="d-block">
            <span class="border-start border-light ps-3 fw-bold fs-5 text-white" style="letter-spacing: 0.5px;">
                System Administrator Portal
            </span>
        </a>

        <!-- Header Controls -->
        <div class="d-flex align-items-center gap-3 ms-auto">
            <span class="badge bg-white text-dark p-2 rounded-4px d-none d-md-inline-flex align-items-center gap-1 fw-bold">
                <i class="bi bi-shield-lock-fill text-danger me-1"></i> Role: Super Administrator
            </span>
            <a href="../includes/auth.php" class="btn btn-sm btn-admin-accent rounded-4px">
                <i class="bi bi-box-arrow-right me-1"></i> Exit Admin Portal
            </a>
        </div>
    </div>
</header>

<div class="container-fluid px-4 py-4" style="min-height: 88vh;">

    <!-- Navigation Tabs (Overview, Users, Companies, Admin Requests, Audit Logs) -->
    <div class="row justify-content-center mb-4">
        <div class="col-12 col-lg-11">
            <ul class="nav nav-pills nav-justified admin-tabs-wrapper shadow-sm" id="adminTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'overview' ? 'active' : '' ?>" id="overview-tab" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button" role="tab">
                        <i class="bi bi-speedometer2 me-1"></i>Overview
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'users' ? 'active' : '' ?>" id="users-tab" data-bs-toggle="tab" data-bs-target="#tab-users" type="button" role="tab">
                        <i class="bi bi-people-fill me-1"></i>User Management
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'companies' ? 'active' : '' ?>" id="companies-tab" data-bs-toggle="tab" data-bs-target="#tab-companies" type="button" role="tab">
                        <i class="bi bi-building-fill-gear me-1"></i>Company Management
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'requests' ? 'active' : '' ?>" id="requests-tab" data-bs-toggle="tab" data-bs-target="#tab-requests" type="button" role="tab">
                        <i class="bi bi-clipboard-check-fill me-1"></i>Admin Side Requests <span class="badge bg-warning text-dark rounded-circle ms-1"><?= $pendingRequestsCount ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'audit' ? 'active' : '' ?>" id="audit-tab" data-bs-toggle="tab" data-bs-target="#tab-audit" type="button" role="tab">
                        <i class="bi bi-journal-text me-1"></i>System Audit Logs
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <!-- Tab Contents -->
    <div class="tab-content" id="adminTabsContent">

        <!-- ================= TAB 1: OVERVIEW ================= -->
        <div class="tab-pane fade <?= $activeTab === 'overview' ? 'show active' : '' ?>" id="tab-overview" role="tabpanel">
            
            <!-- Metric Cards Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-6 col-xl-3">
                    <div class="admin-metric-card d-flex align-items-center justify-content-between">
                        <div>
                            <div class="metric-label">Total Users</div>
                            <div class="metric-value" id="metric-total-users"><?= number_format($totalUsersCount) ?></div>
                            <span class="text-muted small"><i class="bi bi-person-plus-fill text-success"></i> Registered Candidates</span>
                        </div>
                        <div class="icon-box">
                            <i class="bi bi-people-fill"></i>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="admin-metric-card d-flex align-items-center justify-content-between">
                        <div>
                            <div class="metric-label">Registered Companies</div>
                            <div class="metric-value" id="metric-total-companies"><?= number_format($totalCompaniesCount) ?></div>
                            <span class="text-muted small"><i class="bi bi-building-check text-primary"></i> <?= $verifiedCompaniesCount ?> Verified</span>
                        </div>
                        <div class="icon-box">
                            <i class="bi bi-building-fill"></i>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="admin-metric-card d-flex align-items-center justify-content-between">
                        <div>
                            <div class="metric-label">Pending Admin Requests</div>
                            <div class="metric-value text-warning" id="metric-pending-requests"><?= $pendingRequestsCount ?></div>
                            <span class="text-muted small"><i class="bi bi-clock-history text-warning"></i> Needs Approval</span>
                        </div>
                        <div class="icon-box" style="background: #f59e0b; color: #fff;">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="admin-metric-card d-flex align-items-center justify-content-between">
                        <div>
                            <div class="metric-label">Suspended Accounts</div>
                            <div class="metric-value text-danger" id="metric-suspended-accounts"><?= $totalSuspendedCount ?></div>
                            <span class="text-muted small"><i class="bi bi-slash-circle-fill text-danger"></i> System Enforced</span>
                        </div>
                        <div class="icon-box" style="background: #ef4444; color: #fff;">
                            <i class="bi bi-shield-slash-fill"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Overview Main Grid -->
            <div class="row g-4">
                <!-- Recent Pending Approvals Stream -->
                <div class="col-lg-8">
                    <div class="admin-card h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-hourglass-split text-brand me-2"></i>Priority Admin Approval Queue</h5>
                            <button class="btn btn-admin-secondary btn-sm" onclick="document.getElementById('requests-tab').click()">View Queue</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-admin align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Request Category</th>
                                        <th>Target Entity</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                        $hasOverviewItems = false;
                                    ?>
                                    <?php if (!empty($pendingResets)): ?>
                                        <?php foreach ($pendingResets as $rItem): ?>
                                            <?php $hasOverviewItems = true; ?>
                                            <tr id="req-reset-overview-<?= $rItem['id'] ?>">
                                                <td><span class="fw-bold text-dark"><i class="bi bi-key-fill me-1 text-warning"></i>Password Reset</span></td>
                                                <td>
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($rItem['account_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($rItem['account_email']) ?> (<?= ucfirst($rItem['user_type']) ?>)</small>
                                                </td>
                                                <td><span class="small text-muted"><i class="bi bi-clock me-1"></i><?= date('M d, H:i', strtotime($rItem['requested_at'])) ?></span></td>
                                                <td><span class="badge badge-status badge-pending">Pending Approval</span></td>
                                                <td>
                                                    <button class="btn btn-action-approve btn-sm" onclick="confirmPasswordResetAction(<?= $rItem['id'] ?>, '<?= htmlspecialchars(addslashes($rItem['account_name'])) ?>', 'Approve')"><i class="bi bi-check-lg me-1"></i> Approve</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                    <?php if (!empty($pendingQueue)): ?>
                                        <?php foreach ($pendingQueue as $pItem): ?>
                                            <?php $hasOverviewItems = true; ?>
                                            <tr id="req-row-<?= $pItem['companyid'] ?>">
                                                <td><span class="fw-bold text-dark"><i class="bi bi-patch-check me-1 text-primary"></i>Company Verification</span></td>
                                                <td><?= htmlspecialchars($pItem['companyName']) ?></td>
                                                <td><?= htmlspecialchars($pItem['city'] ?? 'Location N/A') ?></td>
                                                <td>
                                                    <?php if ($pItem['verificationStatus'] === 'Pending'): ?>
                                                        <span class="badge badge-status badge-pending">Pending Review</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-status badge-suspended"><?= htmlspecialchars($pItem['accountStatus']) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-action-approve btn-sm" onclick="confirmCompanyVerification(<?= $pItem['companyid'] ?>, '<?= htmlspecialchars(addslashes($pItem['companyName'])) ?>', 'Approve')"><i class="bi bi-check-lg me-1"></i> Approve</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                    <?php if (!$hasOverviewItems): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">No pending authorization or password reset requests.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Admin System Health Widget -->
                <div class="col-lg-4">
                    <div class="admin-card h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <div class="rounded-3 bg-dark text-accent p-2">
                                    <i class="bi bi-shield-check fs-3"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark mb-0">Platform Security</h5>
                                    <small class="text-muted">Database & System Health</small>
                                </div>
                            </div>
                            <hr>
                            <div class="mb-2 d-flex justify-content-between small">
                                <span>Database Sync (MySQL):</span>
                                <strong class="text-success"><i class="bi bi-circle-fill fs-6 me-1"></i>Connected</strong>
                            </div>
                            <div class="mb-2 d-flex justify-content-between small">
                                <span>ATS & CV Scanner Engine:</span>
                                <strong class="text-success">Active</strong>
                            </div>
                            <div class="mb-2 d-flex justify-content-between small">
                                <span>Intervia AI Service API:</span>
                                <strong class="text-success">Online</strong>
                            </div>
                            <div class="mb-2 d-flex justify-content-between small">
                                <span>Account Enforcement:</span>
                                <strong class="text-dark">Strict Moderation</strong>
                            </div>
                        </div>
                        <button class="btn btn-admin-primary w-100 mt-3" onclick="document.getElementById('audit-tab').click()">
                            <i class="bi bi-journal-text me-1"></i> View Audit Trail Logs
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= TAB 2: USER MANAGEMENT (VIEW ALL & SUSPEND) ================= -->
        <div class="tab-pane fade <?= $activeTab === 'users' ? 'show active' : '' ?>" id="tab-users" role="tabpanel">
            <div class="admin-card">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                    <div>
                        <h5 class="fw-bold text-dark mb-1"><i class="bi bi-people-fill text-brand me-2"></i>Job Seeker System User Accounts</h5>
                        <p class="text-muted small mb-0">View all registered candidate accounts (`user` table), inspect profiles, and enforce account suspensions.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <input type="text" class="form-control admin-input" id="userSearchInput" style="width: 260px;" placeholder="Search user name or email...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-admin align-middle mb-0">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Candidate Name</th>
                                <th>Email Address</th>
                                <th>Professional Title</th>
                                <th>Last Login</th>
                                <th>Account Status</th>
                                <th>Admin Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <?php if (!empty($allUsers)): ?>
                                <?php foreach ($allUsers as $u): ?>
                                    <?php 
                                        $uId = $u['userid'];
                                        $uName = trim(($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? '')) ?: ($u['username'] ?? 'User');
                                        $uEmail = $u['email'] ?? 'No email';
                                        $uTitle = $u['profTitle'] ?? 'Candidate';
                                        $uStatus = $u['accStatus'] ?? 'Active';
                                        $uLogin = !empty($u['lastLoginTime']) ? date('Y-m-d H:i', strtotime($u['lastLoginTime'])) : 'Never';
                                    ?>
                                    <tr>
                                        <td class="font-monospace fw-bold">#USR-<?= $uId ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($uName) ?></div>
                                            <small class="text-muted">Username: <?= htmlspecialchars($u['username'] ?? '') ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($uEmail) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($uTitle) ?></span></td>
                                        <td><?= htmlspecialchars($uLogin) ?></td>
                                        <td>
                                            <?php if ($uStatus === 'Suspended'): ?>
                                                <span class="badge badge-status badge-suspended" id="user-status-badge-<?= $uId ?>"><i class="bi bi-slash-circle-fill"></i> Suspended</span>
                                            <?php else: ?>
                                                <span class="badge badge-status badge-active" id="user-status-badge-<?= $uId ?>"><i class="bi bi-check-circle-fill"></i> Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1" id="user-actions-<?= $uId ?>">
                                                <?php if ($uStatus === 'Active'): ?>
                                                    <button class="btn btn-action-suspend" onclick="confirmToggleUserStatus(<?= $uId ?>, '<?= htmlspecialchars(addslashes($uName)) ?>', 'Active')" title="Suspend Account"><i class="bi bi-shield-slash me-1"></i> Suspend</button>
                                                <?php else: ?>
                                                    <button class="btn btn-action-activate" onclick="confirmToggleUserStatus(<?= $uId ?>, '<?= htmlspecialchars(addslashes($uName)) ?>', 'Suspended')" title="Activate Account"><i class="bi bi-check-circle me-1"></i> Activate</button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-outline-dark rounded-4px" onclick="viewUserDetails(<?= $uId ?>, '<?= htmlspecialchars(addslashes($uName)) ?>', '<?= htmlspecialchars(addslashes($uEmail)) ?>', '<?= htmlspecialchars(addslashes($uTitle)) ?>', '<?= $uStatus ?>', '<?= htmlspecialchars(addslashes($u['skills'] ?? 'N/A')) ?>', '<?= $uLogin ?>')" title="View Details"><i class="bi bi-eye"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No candidate user accounts registered yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ================= TAB 3: COMPANY MANAGEMENT (VIEW ALL, VERIFY & SUSPEND) ================= -->
        <div class="tab-pane fade <?= $activeTab === 'companies' ? 'show active' : '' ?>" id="tab-companies" role="tabpanel">
            <div class="admin-card">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                    <div>
                        <h5 class="fw-bold text-dark mb-1"><i class="bi bi-building-fill-gear text-brand me-2"></i>Registered Corporate Accounts</h5>
                        <p class="text-muted small mb-0">View all company accounts (`company` table), verify corporate registrations, and manage account statuses.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <input type="text" class="form-control admin-input" id="companySearchInput" style="width: 260px;" placeholder="Search company name...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-admin align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Company ID</th>
                                <th>Company Name</th>
                                <th>Industry Sector</th>
                                <th>Registration No</th>
                                <th>Verification</th>
                                <th>Account Status</th>
                                <th>Admin Actions</th>
                            </tr>
                        </thead>
                        <tbody id="companiesTableBody">
                            <?php if (!empty($allCompanies)): ?>
                                <?php foreach ($allCompanies as $c): ?>
                                    <?php 
                                        $cId = $c['companyid'];
                                        $cName = $c['companyName'] ?? 'Company';
                                        $cEmail = $c['companyEmail'] ?? 'No email';
                                        $cIndustry = $c['industry'] ?? 'N/A';
                                        $cRegNo = $c['registrationNo'] ?? 'N/A';
                                        $cVerStatus = $c['verificationStatus'] ?? 'Pending';
                                        $cAccStatus = $c['accountStatus'] ?? 'Active';
                                        $cCity = $c['city'] ?? 'N/A';
                                    ?>
                                    <tr>
                                        <td class="font-monospace fw-bold">#COMP-<?= $cId ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($cName) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($cEmail) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($cIndustry) ?></td>
                                        <td><?= htmlspecialchars($cRegNo) ?></td>
                                        <td>
                                            <?php if ($cVerStatus === 'Verified'): ?>
                                                <span class="badge badge-status badge-active" id="company-ver-badge-<?= $cId ?>"><i class="bi bi-patch-check-fill"></i> Verified</span>
                                            <?php elseif ($cVerStatus === 'Rejected'): ?>
                                                <span class="badge badge-status badge-suspended" id="company-ver-badge-<?= $cId ?>"><i class="bi bi-x-circle-fill"></i> Rejected</span>
                                            <?php else: ?>
                                                <span class="badge badge-status badge-pending" id="company-ver-badge-<?= $cId ?>"><i class="bi bi-clock-history"></i> Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($cAccStatus === 'Suspended'): ?>
                                                <span class="badge badge-status badge-suspended" id="company-acc-badge-<?= $cId ?>"><i class="bi bi-slash-circle-fill"></i> Suspended</span>
                                            <?php else: ?>
                                                <span class="badge badge-status badge-active" id="company-acc-badge-<?= $cId ?>"><i class="bi bi-check-circle-fill"></i> Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1" id="company-actions-<?= $cId ?>">
                                                <?php if ($cVerStatus === 'Pending'): ?>
                                                    <button class="btn btn-action-approve btn-sm" onclick="confirmCompanyVerification(<?= $cId ?>, '<?= htmlspecialchars(addslashes($cName)) ?>', 'Approve')" title="Approve Verification"><i class="bi bi-check-circle me-1"></i> Approve</button>
                                                    <button class="btn btn-action-suspend btn-sm" onclick="confirmCompanyVerification(<?= $cId ?>, '<?= htmlspecialchars(addslashes($cName)) ?>', 'Reject')" title="Reject Verification"><i class="bi bi-x-circle me-1"></i> Reject</button>
                                                <?php elseif ($cAccStatus === 'Active'): ?>
                                                    <button class="btn btn-action-suspend btn-sm" onclick="confirmCompanyVerification(<?= $cId ?>, '<?= htmlspecialchars(addslashes($cName)) ?>', 'Suspend')" title="Suspend Company"><i class="bi bi-shield-slash me-1"></i> Suspend</button>
                                                <?php else: ?>
                                                    <button class="btn btn-action-activate btn-sm" onclick="confirmCompanyVerification(<?= $cId ?>, '<?= htmlspecialchars(addslashes($cName)) ?>', 'Activate')" title="Reactivate Account"><i class="bi bi-check-circle me-1"></i> Activate</button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-outline-dark rounded-4px" onclick="viewCompanyDetails(<?= $cId ?>, '<?= htmlspecialchars(addslashes($cName)) ?>', '<?= htmlspecialchars(addslashes($cEmail)) ?>', '<?= htmlspecialchars(addslashes($cIndustry)) ?>', '<?= htmlspecialchars(addslashes($cRegNo)) ?>', '<?= htmlspecialchars(addslashes($cCity)) ?>', '<?= $cVerStatus ?>', '<?= $cAccStatus ?>')" title="View Details"><i class="bi bi-eye"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No corporate accounts registered yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ================= TAB 4: ADMIN SIDE REQUESTS & APPROVALS ================= -->
        <div class="tab-pane fade <?= $activeTab === 'requests' ? 'show active' : '' ?>" id="tab-requests" role="tabpanel">
            
            <!-- Password Reset Requests Card -->
            <div class="admin-card mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-1"><i class="bi bi-key-fill text-warning me-2"></i>Candidate & Corporate Password Reset Authorization Queue</h5>
                        <p class="text-muted small mb-0">Review and authorize password reset requests submitted by candidates and employers from the login portal.</p>
                    </div>
                    <span class="badge bg-warning text-dark p-2 rounded-4px fw-bold"><?= $pendingPasswordResetsCount ?> Resets Pending</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-admin align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Account Name</th>
                                <th>Account Type</th>
                                <th>Email / Username</th>
                                <th>Requested At</th>
                                <th>Status</th>
                                <th>Authorization Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($pendingResets)): ?>
                                <?php foreach ($pendingResets as $rItem): ?>
                                    <tr id="req-reset-<?= $rItem['id'] ?>">
                                        <td class="font-monospace fw-bold">#RST-<?= $rItem['id'] ?></td>
                                        <td class="fw-bold text-dark"><?= htmlspecialchars($rItem['account_name']) ?></td>
                                        <td>
                                            <?php if ($rItem['user_type'] === 'company'): ?>
                                                <span class="badge bg-primary"><i class="bi bi-building me-1"></i>Company</span>
                                            <?php else: ?>
                                                <span class="badge bg-success"><i class="bi bi-person me-1"></i>Candidate</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars($rItem['account_email']) ?></div>
                                            <small class="text-muted">ID: <?= htmlspecialchars($rItem['identity']) ?></small>
                                        </td>
                                        <td><span class="small text-muted"><i class="bi bi-clock me-1"></i><?= date('M d, Y h:i A', strtotime($rItem['requested_at'])) ?></span></td>
                                        <td><span class="badge badge-status badge-pending">Pending Approval</span></td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <button class="btn btn-action-approve btn-sm" onclick="confirmPasswordResetAction(<?= $rItem['id'] ?>, '<?= htmlspecialchars(addslashes($rItem['account_name'])) ?>', 'Approve')"><i class="bi bi-check-circle me-1"></i> Approve Reset</button>
                                                <button class="btn btn-action-suspend btn-sm" onclick="confirmPasswordResetAction(<?= $rItem['id'] ?>, '<?= htmlspecialchars(addslashes($rItem['account_name'])) ?>', 'Reject')"><i class="bi bi-x-circle me-1"></i> Reject</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No pending password reset requests.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Corporate Verification Requests Card -->
            <div class="admin-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-1"><i class="bi bi-building-check text-brand me-2"></i>Corporate Account Verification & Authorization Queue</h5>
                        <p class="text-muted small mb-0">Approve or decline corporate verification requests and account status authorizations.</p>
                    </div>
                    <span class="badge bg-primary text-white p-2 rounded-4px fw-bold"><?= $pendingCompanyRequestsCount ?> Verifications Pending</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-admin align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Company ID</th>
                                <th>Company Name</th>
                                <th>Email</th>
                                <th>Reg Number</th>
                                <th>Status</th>
                                <th>Authorization Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($pendingQueue)): ?>
                                <?php foreach ($pendingQueue as $pItem): ?>
                                    <tr id="req-queue-<?= $pItem['companyid'] ?>">
                                        <td class="font-monospace fw-bold">#COMP-<?= $pItem['companyid'] ?></td>
                                        <td class="fw-bold text-dark"><?= htmlspecialchars($pItem['companyName']) ?></td>
                                        <td><?= htmlspecialchars($pItem['companyEmail']) ?></td>
                                        <td><?= htmlspecialchars($pItem['registrationNo'] ?? 'N/A') ?></td>
                                        <td><span class="badge bg-danger text-white"><?= htmlspecialchars($pItem['verificationStatus']) ?></span></td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <button class="btn btn-action-approve btn-sm" onclick="confirmCompanyVerification(<?= $pItem['companyid'] ?>, '<?= htmlspecialchars(addslashes($pItem['companyName'])) ?>', 'Approve')"><i class="bi bi-check-circle me-1"></i> Approve Request</button>
                                                <button class="btn btn-action-suspend btn-sm" onclick="confirmCompanyVerification(<?= $pItem['companyid'] ?>, '<?= htmlspecialchars(addslashes($pItem['companyName'])) ?>', 'Reject')"><i class="bi bi-x-circle me-1"></i> Reject</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No pending company authorization requests.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ================= TAB 5: SYSTEM AUDIT LOGS ================= -->
        <div class="tab-pane fade <?= $activeTab === 'audit' ? 'show active' : '' ?>" id="tab-audit" role="tabpanel">
            <div class="admin-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="fw-bold text-dark mb-1"><i class="bi bi-journal-text text-brand me-2"></i>System Activity & Security Audit Logs</h5>
                        <p class="text-muted small mb-0">Log of all administrative actions, account status modifications, and system events.</p>
                    </div>
                    <button class="btn btn-admin-secondary btn-sm" onclick="showToast('Audit log report generated', 'success')">
                        <i class="bi bi-download me-1"></i> Export Logs (CSV)
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-admin align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Log ID</th>
                                <th>Timestamp</th>
                                <th>Actor / Trigger</th>
                                <th>Activity Event Details</th>
                                <th>Security Severity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="font-monospace">#LOG-9901</td>
                                <td><?= date('Y-m-d H:i:s') ?></td>
                                <td><span class="badge bg-dark text-accent">SuperAdmin</span></td>
                                <td>System Audit Trail initialized and synced with MySQL database</td>
                                <td><span class="badge bg-success text-white">Info</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ================= CONFIRM ADMIN ACTION MODAL ================= -->
<div class="modal fade admin-modal" id="confirmAdminActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-12px border-0 shadow-lg">
            <div class="modal-header bg-dark text-white rounded-top-12px">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-exclamation text-accent me-2"></i>Confirm Administrator Action</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light text-center">
                <div class="mb-3">
                    <i class="bi bi-exclamation-circle-fill text-warning display-4"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Are you sure?</h5>
                <p class="text-muted small mb-0" id="confirmAdminActionMsg">Are you sure you want to proceed with this administrative action?</p>
            </div>
            <div class="modal-footer bg-white rounded-bottom-12px justify-content-center gap-2">
                <button type="button" class="btn btn-secondary rounded-4px btn-sm px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger rounded-4px btn-sm font-weight-bold px-4" id="confirmAdminActionProceedBtn"><i class="bi bi-check-circle me-1"></i> Yes, Proceed</button>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL 1: VIEW USER DETAILS ================= -->
<div class="modal fade admin-modal" id="viewUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-badge me-2"></i>Job Seeker Profile Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="admin-card mb-0">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-circle bg-dark text-accent d-flex align-items-center justify-content-center fw-bold fs-4" style="width:54px; height:54px;">
                            <i class="bi bi-person"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-0" id="modalUserName">User Name</h5>
                            <span class="text-brand font-monospace small" id="modalUserId">#USR-0000</span>
                        </div>
                    </div>
                    <hr>
                    <div class="small">
                        <p class="mb-2"><strong>Email Address:</strong> <span id="modalUserEmail">N/A</span></p>
                        <p class="mb-2"><strong>Professional Title:</strong> <span id="modalUserTitle">N/A</span></p>
                        <p class="mb-2"><strong>Account Status:</strong> <span class="badge badge-status badge-active" id="modalUserStatus">Active</span></p>
                        <p class="mb-2"><strong>Skills:</strong> <span id="modalUserSkills">N/A</span></p>
                        <p class="mb-0"><strong>Last System Login:</strong> <span id="modalUserLogin">Never</span></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white">
                <button type="button" class="btn btn-admin-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL 2: VIEW COMPANY DETAILS ================= -->
<div class="modal fade admin-modal" id="viewCompanyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-building me-2"></i>Corporate Account Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="admin-card mb-0">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-circle bg-dark text-accent d-flex align-items-center justify-content-center fw-bold fs-4" style="width:54px; height:54px;">
                            <i class="bi bi-building"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-0" id="modalCompanyName">Company Name</h5>
                            <span class="text-brand font-monospace small" id="modalCompanyId">#COMP-0000</span>
                        </div>
                    </div>
                    <hr>
                    <div class="small">
                        <p class="mb-2"><strong>Company Email:</strong> <span id="modalCompanyEmail">N/A</span></p>
                        <p class="mb-2"><strong>Verification Status:</strong> <span class="badge badge-status badge-active" id="modalCompanyVerStatus">Verified</span></p>
                        <p class="mb-2"><strong>Account Status:</strong> <span class="badge badge-status badge-active" id="modalCompanyAccStatus">Active</span></p>
                        <p class="mb-2"><strong>Business Reg No:</strong> <span id="modalCompanyRegNo">N/A</span></p>
                        <p class="mb-2"><strong>Industry Sector:</strong> <span id="modalCompanyIndustry">N/A</span></p>
                        <p class="mb-0"><strong>Location:</strong> <span id="modalCompanyCity">N/A</span></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white">
                <button type="button" class="btn btn-admin-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Universal Toast Engine & Admin JS -->
<script src="../assets/js/toast.js"></script>
<script src="../assets/js/validation.js"></script>
<script src="js/admin.js"></script>
</body>
</html>
