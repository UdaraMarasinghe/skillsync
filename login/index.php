<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/activity_logger.php';

// Prevent browser caching on login page
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$error_message = '';
$success_message = '';
$approved_reset_token = '';
$approved_reset_identity = '';

if (isset($_GET['logged_out'])) {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    @session_destroy();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $success_message = 'You have been successfully logged out.';
} elseif (isset($_GET['registered'])) {
    $success_message = 'Registration successful! Please log in.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = trim($_POST['login_identity'] ?? '');
    $password = $_POST['login_password'] ?? '';
    
    if (empty($identity) || empty($password)) {
        $error_message = 'Please enter both email/username and password.';
    } else {
        // 1. Check Admin Login via Admin Database Table
        try {
            $stmtAdmin = $pdo->prepare("SELECT * FROM admin WHERE username = ? OR LOWER(username) = LOWER(?) LIMIT 1");
            $stmtAdmin->execute([$identity, $identity]);
            $adminRow = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

            if ($adminRow) {
                if (password_verify($password, $adminRow['password']) || $password === $adminRow['password']) {
                    $_SESSION['user_type'] = 'admin';
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $adminRow['adminid'];
                    $_SESSION['username'] = $adminRow['username'];
                    $_SESSION['name'] = 'System Administrator';

                    header("Location: ../admin/");
                    exit;
                }
            }
        } catch (PDOException $e) {
            // Fallback in case table fails
        }
        
        $authenticated = false;
        
        // 2. Check Candidate User Table
        $stmtUser = $pdo->prepare("SELECT * FROM user WHERE username = ? OR email = ?");
        $stmtUser->execute([$identity, $identity]);
        $user = $stmtUser->fetch();
        
        if ($user) {
            if (password_verify($password, $user['password']) || $password === $user['password']) {
                if ($user['accStatus'] === 'Suspended') {
                    $error_message = 'Your account has been suspended. Please contact administrator.';
                } else {
                    $updateStmt = $pdo->prepare("UPDATE user SET lastLoginTime = NOW() WHERE userid = ?");
                    $updateStmt->execute([$user['userid']]);
                    
                    $_SESSION['user_type'] = 'user';
                    $_SESSION['user_id'] = $user['userid'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['name'] = $user['firstName'] . ' ' . $user['lastName'];
                    $_SESSION['email'] = $user['email'];
                    
                    logActivity($pdo, $user['userid'], null, "User logged in to SkillSync");
                    
                    header("Location: ../user-profile/");
                    exit;
                }
                $authenticated = true;
            }
        }
        
        // 3. Check Corporate Company Table
        if (!$authenticated && empty($error_message)) {
            $stmtCompany = $pdo->prepare("SELECT * FROM company WHERE companyUsername = ? OR companyEmail = ?");
            $stmtCompany->execute([$identity, $identity]);
            $company = $stmtCompany->fetch();
            
            if ($company) {
                if (password_verify($password, $company['password']) || $password === $company['password']) {
                    if ($company['accountStatus'] === 'Suspended') {
                        $error_message = 'Your company account has been suspended. Please contact administrator.';
                    } else {
                        $updateStmt = $pdo->prepare("UPDATE company SET lastLoginTime = NOW() WHERE companyid = ?");
                        $updateStmt->execute([$company['companyid']]);
                        
                        $_SESSION['user_type'] = 'company';
                        $_SESSION['company_id'] = $company['companyid'];
                        $_SESSION['username'] = $company['companyUsername'];
                        $_SESSION['company_name'] = $company['companyName'];
                        $_SESSION['email'] = $company['companyEmail'];
                        
                        logActivity($pdo, null, $company['companyid'], "Company logged in to SkillSync");
                        
                        header("Location: ../company/");
                        exit;
                    }
                    $authenticated = true;
                }
            }
        }
        
        if (!$authenticated && empty($error_message)) {
            // Check if account has an approved password reset
            $approvedReset = null;
            if ($user && $user['accStatus'] !== 'Suspended') {
                $stmtR = $pdo->prepare("SELECT * FROM password_resets WHERE user_type = 'user' AND account_id = ? AND status = 'Approved' ORDER BY id DESC LIMIT 1");
                $stmtR->execute([$user['userid']]);
                $approvedReset = $stmtR->fetch();
            } elseif (!empty($company) && $company['accountStatus'] !== 'Suspended') {
                $stmtR = $pdo->prepare("SELECT * FROM password_resets WHERE user_type = 'company' AND account_id = ? AND status = 'Approved' ORDER BY id DESC LIMIT 1");
                $stmtR->execute([$company['companyid']]);
                $approvedReset = $stmtR->fetch();
            }

            if ($approvedReset) {
                $approved_reset_token = $approvedReset['reset_token'];
                $approved_reset_identity = $identity;
            } else {
                $error_message = 'Invalid email/username or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkillSync - Account Login</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="../favicon.webp">
    
    <!-- Google Fonts Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/device.css">
    
    <style>
        :root {
            --brand-dark: #004743;
            --brand-accent: #ACFF78;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8faf9;
            height: 100vh;
            max-height: 100vh;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 16px;
        }
        .login-card {
            background-color: #ffffff;
            border: 2px solid var(--brand-dark);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 71, 67, 0.15);
            max-height: calc(100vh - 32px);
            overflow-y: auto;
            width: 100%;
            max-width: 440px;
            padding: 28px;
        }
        .btn-brand {
            background-color: var(--brand-dark);
            color: var(--brand-accent) !important;
            font-weight: 700;
            border: 1.5px solid var(--brand-dark);
            border-radius: 4px;
            padding: 10px 18px;
            transition: all 0.2s ease-in-out;
        }
        .btn-brand:hover {
            background-color: #003633;
            color: var(--brand-accent) !important;
        }
    </style>
</head>
<body>

<div class="login-card">
    <!-- Brand Header -->
    <div class="text-center mb-3">
        <a href="../" class="d-inline-block mb-2">
            <img src="../assets/img/logo.webp" alt="SkillSync Logo" height="38">
        </a>
        <h3 class="fw-bold text-dark mb-1 fs-4">Account Login</h3>
        <p class="text-muted small mb-0">Enter your credentials to access SkillSync</p>
    </div>

    <script src="../assets/js/toast.js"></script>
    <script src="../assets/js/validation.js"></script>
    <?php if (!empty($error_message)): ?>
        <script>document.addEventListener('DOMContentLoaded', () => showToast(<?= json_encode($error_message) ?>, 'danger'));</script>
    <?php endif; ?>
    <?php if (!empty($success_message)): ?>
        <script>document.addEventListener('DOMContentLoaded', () => showToast(<?= json_encode($success_message) ?>, 'success'));</script>
    <?php endif; ?>
    <?php if (!empty($approved_reset_token)): ?>
        <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof openApprovedResetModal === 'function') {
                openApprovedResetModal(<?= json_encode($approved_reset_identity) ?>, <?= json_encode($approved_reset_token) ?>, 'Your password reset request has been approved by admin! Please enter your new password.');
            }
        });
        </script>
    <?php endif; ?>

    <!-- Login Form -->
    <form id="loginForm" method="POST" action="./" onsubmit="return validateFormFields(this);">
        
        <!-- Email / Username Input -->
        <div class="mb-3">
            <label for="loginEmail" class="form-label fw-bold small text-uppercase text-muted mb-1">Email or Username <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0" style="border: 1.5px solid var(--brand-dark); border-radius: 8px 0 0 8px; border-right: none;">
                    <i class="bi bi-envelope text-dark"></i>
                </span>
                <input type="text" class="form-control" id="loginEmail" name="login_identity" value="<?= htmlspecialchars($_POST['login_identity'] ?? '') ?>" style="border: 1.5px solid var(--brand-dark); border-radius: 0 8px 8px 0; padding: 8px 12px; font-size: 0.9rem;" required>
            </div>
        </div>

        <!-- Password Input -->
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <label for="loginPassword" class="form-label fw-bold small text-uppercase text-muted mb-0">Password <span class="text-danger">*</span></label>
                <a href="javascript:void(0)" onclick="openResetPasswordModal()" class="small text-decoration-none fw-semibold" style="color: var(--brand-dark); font-size: 0.82rem;">
                    Forgot Password?
                </a>
            </div>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0" style="border: 1.5px solid var(--brand-dark); border-radius: 8px 0 0 8px; border-right: none;">
                    <i class="bi bi-lock text-dark"></i>
                </span>
                <input type="password" class="form-control" id="loginPassword" name="login_password" style="border: 1.5px solid var(--brand-dark); border-left: none; padding: 8px 12px; font-size: 0.9rem;" required>
                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility()" style="border: 1.5px solid var(--brand-dark); border-radius: 0 8px 8px 0;">
                    <i class="bi bi-eye-slash" id="togglePasswordIcon"></i>
                </button>
            </div>
        </div>

        <!-- Remember Me Checkbox -->
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me">
            <label class="form-check-label small fw-semibold text-dark" for="rememberMe">Remember me on this device</label>
        </div>

        <!-- Submit Button -->
        <button type="submit" class="btn btn-brand w-100 rounded-4px py-2 fw-bold text-uppercase">
            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
        </button>
    </form>

    <hr class="my-3">

    <!-- Shortcut to Registration -->
    <div class="text-center">
        <p class="small text-muted mb-0">
            Don't have an account? 
            <a href="../registration/" class="fw-bold text-decoration-none" style="color: var(--brand-dark);">
                Register Here <i class="bi bi-arrow-right me-1"></i>
            </a>
        </p>
    </div>
</div>

<!-- ================= PASSWORD RESET MODAL ================= -->
<div class="modal fade" id="passwordResetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden; border: 2px solid var(--brand-dark);">
            <div class="modal-header text-white" style="background-color: var(--brand-dark);">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-shield-lock me-2" style="color: var(--brand-accent);"></i>Account Password Recovery
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                
                <!-- STEP 1: Enter Username/Email & Submit Request / Check Status -->
                <div id="resetStep1">
                    <div class="text-center mb-3">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 48px; height: 48px; background-color: rgba(0, 71, 67, 0.1); color: var(--brand-dark);">
                            <i class="bi bi-key-fill fs-4"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">Request Password Reset</h6>
                        <p class="text-muted small mb-0">Enter your registered email address or username. Once approved by the administrator, you can configure your new password here.</p>
                    </div>

                    <div id="resetAlertBox" class="alert alert-info d-none small py-2 mb-3"></div>

                    <div class="mb-3">
                        <label for="resetIdentity" class="form-label fw-bold small text-uppercase text-muted mb-1">Email or Username <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0" style="border: 1.5px solid var(--brand-dark); border-radius: 8px 0 0 8px;">
                                <i class="bi bi-person-fill text-dark"></i>
                            </span>
                            <input type="text" class="form-control" id="resetIdentity" placeholder="e.g. user@example.com" style="border: 1.5px solid var(--brand-dark); border-radius: 0 8px 8px 0; padding: 8px 12px; font-size: 0.9rem;">
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-brand fw-bold text-uppercase py-2" id="btnSubmitResetRequest" onclick="promptSubmitResetRequest()">
                            <i class="bi bi-send-fill me-1"></i> Submit / Check Request
                        </button>
                    </div>
                </div>

                <!-- STEP 2: Set New Password (When Approved) -->
                <div id="resetStep2" class="d-none">
                    <div class="text-center mb-3">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 48px; height: 48px; background-color: rgba(172, 255, 120, 0.3); color: var(--brand-dark);">
                            <i class="bi bi-patch-check-fill fs-4 text-success"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">Authorization Approved!</h6>
                        <p class="text-muted small mb-0">Your password reset request has been approved by admin. Please enter your new password below.</p>
                    </div>

                    <input type="hidden" id="resetToken">
                    
                    <div class="mb-3">
                        <label for="newResetPassword" class="form-label fw-bold small text-uppercase text-muted mb-1">New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0" style="border: 1.5px solid var(--brand-dark); border-radius: 8px 0 0 8px;">
                                <i class="bi bi-lock-fill text-dark"></i>
                            </span>
                            <input type="password" class="form-control" id="newResetPassword" placeholder="Minimum 6 characters" style="border: 1.5px solid var(--brand-dark); border-left: none; padding: 8px 12px; font-size: 0.9rem;">
                            <button class="btn btn-outline-secondary" type="button" onclick="toggleResetPasswordVisibility('newResetPassword', 'toggleNewPwdIcon')" style="border: 1.5px solid var(--brand-dark); border-radius: 0 8px 8px 0;">
                                <i class="bi bi-eye-slash" id="toggleNewPwdIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="confirmResetPassword" class="form-label fw-bold small text-uppercase text-muted mb-1">Confirm New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0" style="border: 1.5px solid var(--brand-dark); border-radius: 8px 0 0 8px;">
                                <i class="bi bi-shield-check text-dark"></i>
                            </span>
                            <input type="password" class="form-control" id="confirmResetPassword" placeholder="Re-enter new password" style="border: 1.5px solid var(--brand-dark); border-left: none; padding: 8px 12px; font-size: 0.9rem;">
                            <button class="btn btn-outline-secondary" type="button" onclick="toggleResetPasswordVisibility('confirmResetPassword', 'toggleConfirmPwdIcon')" style="border: 1.5px solid var(--brand-dark); border-radius: 0 8px 8px 0;">
                                <i class="bi bi-eye-slash" id="toggleConfirmPwdIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-brand fw-bold text-uppercase py-2" id="btnSaveNewPassword" onclick="promptSaveNewPassword()">
                            <i class="bi bi-check2-circle me-1"></i> Save New Password
                        </button>
                    </div>
                </div>

            </div>
            <div class="modal-footer bg-white py-2 justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnResetBack" onclick="resetModalToStep1()" style="border-radius: 4px;">Back</button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" style="border-radius: 4px;">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ================= CONFIRMATION MODAL (RULE 2: Are you sure?) ================= -->
<div class="modal fade" id="confirmLoginActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden; border: 2px solid var(--brand-dark);">
            <div class="modal-header text-white" style="background-color: var(--brand-dark);">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-question-circle-fill me-2" style="color: var(--brand-accent);"></i>Confirmation Required
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light text-center">
                <div class="mb-3">
                    <i class="bi bi-exclamation-circle-fill text-warning display-5"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Are you sure?</h5>
                <p class="text-muted small mb-0" id="confirmLoginActionMsg">Are you sure you want to proceed with this action?</p>
            </div>
            <div class="modal-footer bg-white justify-content-center gap-2 py-3">
                <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal" style="border-radius: 4px;">Cancel</button>
                <button type="button" class="btn btn-brand btn-sm px-4 fw-bold" id="confirmLoginActionProceedBtn" style="border-radius: 4px;"><i class="bi bi-check-circle me-1"></i> Yes, Proceed</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
let pendingUserResetTask = null;
let isCheckingApprovedReset = false;
let approvedResetOpenedForIdentity = '';

function openApprovedResetModal(identity, token, message) {
    document.getElementById('resetStep1').classList.add('d-none');
    document.getElementById('resetStep2').classList.remove('d-none');
    document.getElementById('btnResetBack').classList.remove('d-none');
    document.getElementById('resetAlertBox').classList.add('d-none');
    document.getElementById('resetAlertBox').innerHTML = '';
    document.getElementById('resetIdentity').value = identity;
    document.getElementById('resetToken').value = token;
    document.getElementById('newResetPassword').value = '';
    document.getElementById('confirmResetPassword').value = '';

    const pwdModalEl = document.getElementById('passwordResetModal');
    const pwdModal = bootstrap.Modal.getOrCreateInstance(pwdModalEl);
    pwdModal.show();

    if (message) {
        showToast(message, 'info');
    }
}

async function checkForApprovedPasswordReset(identity) {
    if (!identity || isCheckingApprovedReset) return false;
    isCheckingApprovedReset = true;

    try {
        const formData = new FormData();
        formData.append('action', 'check_approved');
        formData.append('identity', identity);

        const res = await fetch('reset_password.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success && data.has_approved && data.reset_token) {
            approvedResetOpenedForIdentity = identity.toLowerCase();
            openApprovedResetModal(identity, data.reset_token, data.message || 'Your password reset request has been approved by administrator! Please configure your new password.');
            return true;
        }
    } catch (err) {
        console.error('Error checking approved reset status:', err);
    } finally {
        isCheckingApprovedReset = false;
    }
    return false;
}

// Live detection on password field input
document.addEventListener('DOMContentLoaded', () => {
    const loginPwd = document.getElementById('loginPassword');
    const loginEmail = document.getElementById('loginEmail');
    const loginForm = document.getElementById('loginForm');
    let resetInputTimer = null;

    if (loginPwd && loginEmail) {
        // Trigger check when typing in password field
        loginPwd.addEventListener('input', function() {
            const identity = loginEmail.value.trim();
            const pwdVal = loginPwd.value;
            if (!identity || !pwdVal) return;

            if (approvedResetOpenedForIdentity === identity.toLowerCase()) return;

            clearTimeout(resetInputTimer);
            resetInputTimer = setTimeout(() => {
                checkForApprovedPasswordReset(identity);
            }, 250);
        });

        // Trigger check on focus if password has characters
        loginPwd.addEventListener('focus', function() {
            const identity = loginEmail.value.trim();
            const pwdVal = loginPwd.value;
            if (identity && pwdVal && approvedResetOpenedForIdentity !== identity.toLowerCase()) {
                checkForApprovedPasswordReset(identity);
            }
        });

        // Reset tracking if identity is changed
        loginEmail.addEventListener('input', function() {
            if (approvedResetOpenedForIdentity && approvedResetOpenedForIdentity !== loginEmail.value.trim().toLowerCase()) {
                approvedResetOpenedForIdentity = '';
            }
        });

        loginEmail.addEventListener('blur', function() {
            const identity = loginEmail.value.trim();
            const pwdVal = loginPwd.value;
            if (identity && pwdVal && approvedResetOpenedForIdentity !== identity.toLowerCase()) {
                checkForApprovedPasswordReset(identity);
            }
        });
    }

    // Intercept form submission if approved reset exists
    if (loginForm && loginEmail && loginPwd) {
        loginForm.addEventListener('submit', async function(e) {
            const identity = loginEmail.value.trim();
            const pwdVal = loginPwd.value;
            if (identity && pwdVal) {
                if (approvedResetOpenedForIdentity === identity.toLowerCase()) {
                    e.preventDefault();
                    return;
                }
                const hasApproved = await checkForApprovedPasswordReset(identity);
                if (hasApproved) {
                    e.preventDefault();
                }
            }
        });
    }
});

function togglePasswordVisibility() {
    const pwd = document.getElementById('loginPassword');
    const icon = document.getElementById('togglePasswordIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.className = 'bi bi-eye';
    } else {
        pwd.type = 'password';
        icon.className = 'bi bi-eye-slash';
    }
}

function toggleResetPasswordVisibility(inputId, iconId) {
    const pwd = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.className = 'bi bi-eye';
    } else {
        pwd.type = 'password';
        icon.className = 'bi bi-eye-slash';
    }
}

function openResetPasswordModal() {
    resetModalToStep1();
    const modal = new bootstrap.Modal(document.getElementById('passwordResetModal'));
    modal.show();
}

function resetModalToStep1() {
    document.getElementById('resetStep1').classList.remove('d-none');
    document.getElementById('resetStep2').classList.add('d-none');
    document.getElementById('btnResetBack').classList.add('d-none');
    document.getElementById('resetAlertBox').classList.add('d-none');
    document.getElementById('resetAlertBox').innerHTML = '';
    document.getElementById('newResetPassword').value = '';
    document.getElementById('confirmResetPassword').value = '';
    document.getElementById('resetToken').value = '';
}

function promptSubmitResetRequest() {
    const identity = document.getElementById('resetIdentity').value.trim();
    if (!identity) {
        showToast('Please enter your email or username.', 'danger');
        return;
    }

    pendingUserResetTask = {
        type: 'submit_reset_request',
        identity: identity
    };

    document.getElementById('confirmLoginActionMsg').textContent = `Are you sure you want to submit a password reset request for "${identity}"?`;
    const confirmModal = new bootstrap.Modal(document.getElementById('confirmLoginActionModal'));
    confirmModal.show();
}

function promptSaveNewPassword() {
    const identity = document.getElementById('resetIdentity').value.trim();
    const token = document.getElementById('resetToken').value.trim();
    const newPwd = document.getElementById('newResetPassword').value;
    const confirmPwd = document.getElementById('confirmResetPassword').value;

    if (!newPwd || !confirmPwd) {
        showToast('Please enter and confirm your new password.', 'danger');
        return;
    }

    if (newPwd !== confirmPwd) {
        showToast('Passwords do not match. Please re-enter.', 'danger');
        return;
    }

    if (newPwd.length < 6) {
        showToast('Password must be at least 6 characters long.', 'danger');
        return;
    }

    pendingUserResetTask = {
        type: 'save_new_password',
        identity: identity,
        token: token,
        newPassword: newPwd,
        confirmPassword: confirmPwd
    };

    document.getElementById('confirmLoginActionMsg').textContent = 'Are you sure you want to save and set this new password?';
    const confirmModal = new bootstrap.Modal(document.getElementById('confirmLoginActionModal'));
    confirmModal.show();
}

document.getElementById('confirmLoginActionProceedBtn')?.addEventListener('click', async function() {
    if (!pendingUserResetTask) return;

    const confirmModalEl = document.getElementById('confirmLoginActionModal');
    const confirmModal = bootstrap.Modal.getInstance(confirmModalEl);
    if (confirmModal) confirmModal.hide();

    const task = pendingUserResetTask;
    pendingUserResetTask = null;

    if (task.type === 'submit_reset_request') {
        const formData = new FormData();
        formData.append('action', 'request_or_check');
        formData.append('identity', task.identity);

        try {
            const res = await fetch('reset_password.php', { method: 'POST', body: formData });
            const data = await res.json();
            
            const alertBox = document.getElementById('resetAlertBox');
            alertBox.classList.remove('d-none', 'alert-info', 'alert-success', 'alert-warning', 'alert-danger');

            if (data.success) {
                if (data.status === 'Approved') {
                    // Switch to Step 2
                    document.getElementById('resetStep1').classList.add('d-none');
                    document.getElementById('resetStep2').classList.remove('d-none');
                    document.getElementById('btnResetBack').classList.remove('d-none');
                    document.getElementById('resetToken').value = data.reset_token;
                    showToast(data.message, 'success');
                } else if (data.status === 'Pending') {
                    alertBox.classList.add('alert-warning');
                    alertBox.innerHTML = `<i class="bi bi-clock-history me-1"></i> ${data.message}`;
                    showToast(data.message, 'info');
                }
            } else {
                alertBox.classList.add('alert-danger');
                alertBox.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i> ${data.message}`;
                showToast(data.message, 'danger');
            }
        } catch (err) {
            console.error('Error submitting reset request:', err);
            showToast('Unable to connect to server. Please try again.', 'danger');
        }
    } else if (task.type === 'save_new_password') {
        const formData = new FormData();
        formData.append('action', 'complete_reset');
        formData.append('identity', task.identity);
        formData.append('reset_token', task.token);
        formData.append('new_password', task.newPassword);
        formData.append('confirm_password', task.confirmPassword);

        try {
            const res = await fetch('reset_password.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                const pwdModalEl = document.getElementById('passwordResetModal');
                const pwdModal = bootstrap.Modal.getInstance(pwdModalEl);
                if (pwdModal) pwdModal.hide();

                showToast(data.message, 'success');
                // Pre-fill identity input on login form
                const loginEmailInput = document.getElementById('loginEmail');
                if (loginEmailInput) loginEmailInput.value = task.identity;
            } else {
                showToast(data.message, 'danger');
            }
        } catch (err) {
            console.error('Error saving new password:', err);
            showToast('Unable to save new password. Please try again.', 'danger');
        }
    }
});
</script>
</body>
</html>

