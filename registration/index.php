<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/activity_logger.php';
require_once __DIR__ . '/../includes/industries.php';

$error_message = '';
$success_message = '';
$active_tab = 'user';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'register_candidate') {
        $active_tab = 'user';
        $firstName = trim($_POST['firstName'] ?? '');
        $lastName = trim($_POST['lastName'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobileNo = trim($_POST['mobileNo'] ?? '');
        $profTitle = trim($_POST['profTitle'] ?? '');
        $industry = trim($_POST['industry'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirmPassword'] ?? '';
        
        if (empty($firstName) || empty($lastName) || empty($username) || empty($email) || empty($password)) {
            $error_message = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Please provide a valid email address.';
        } elseif (!empty($mobileNo) && strlen(preg_replace('/[^0-9]/', '', $mobileNo)) < 9) {
            $error_message = 'Mobile contact number must contain at least 9 numeric digits.';
        } elseif (strlen($password) < 8) {
            $error_message = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirmPassword) {
            $error_message = 'Passwords do not match.';
        } else {
            $stmt = $pdo->prepare("SELECT userid FROM user WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error_message = 'Username or Email is already registered.';
            } else {
                $cleanMobile = preg_replace('/[^0-9]/', '', $mobileNo);
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmtInsert = $pdo->prepare("INSERT INTO user (username, password, firstName, lastName, email, mobileNo, profTitle, industry, profilePhoto, accStatus, lastLoginTime) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'assets/img/demo-profile.jpg', 'Active', NOW())");
                if ($stmtInsert->execute([$username, $hashedPassword, $firstName, $lastName, $email, $cleanMobile, $profTitle, $industry])) {
                    $userId = $pdo->lastInsertId();
                    
                    $stmtLoc = $pdo->prepare("INSERT INTO userLocation (userid) VALUES (?)");
                    $stmtLoc->execute([$userId]);
                    
                    $_SESSION['user_type'] = 'user';
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['username'] = $username;
                    $_SESSION['name'] = $firstName . ' ' . $lastName;
                    $_SESSION['email'] = $email;
                    
                    logActivity($pdo, $userId, null, "Created candidate user account");
                    
                    header("Location: ../user-profile/");
                    exit;
                } else {
                    $error_message = 'Registration failed. Please try again.';
                }
            }
        }
    } elseif ($action === 'register_company') {
        $active_tab = 'company';
        $companyName = trim($_POST['companyName'] ?? '');
        $registrationNo = trim($_POST['registrationNo'] ?? '');
        $companyUsername = trim($_POST['companyUsername'] ?? '');
        $companyEmail = trim($_POST['companyEmail'] ?? '');
        $industry = trim($_POST['industry'] ?? '');
        $companyContact = trim($_POST['companyContact'] ?? '');
        $contactPerson = trim($_POST['contactPerson'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirmPassword'] ?? '';
        
        if (empty($companyName) || empty($registrationNo) || empty($companyUsername) || empty($companyEmail) || empty($industry) || empty($password)) {
            $error_message = 'Please fill in all required fields.';
        } elseif (!filter_var($companyEmail, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Please provide a valid corporate email address.';
        } elseif (!empty($companyContact) && strlen(preg_replace('/[^0-9]/', '', $companyContact)) < 9) {
            $error_message = 'Company contact phone must contain at least 9 numeric digits.';
        } elseif (strlen($password) < 8) {
            $error_message = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirmPassword) {
            $error_message = 'Passwords do not match.';
        } else {
            $stmt = $pdo->prepare("SELECT companyid FROM company WHERE companyUsername = ? OR companyEmail = ?");
            $stmt->execute([$companyUsername, $companyEmail]);
            if ($stmt->fetch()) {
                $error_message = 'Company Username or Corporate Email is already registered.';
            } else {
                $cleanContact = preg_replace('/[^0-9]/', '', $companyContact);
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmtInsert = $pdo->prepare("INSERT INTO company (companyUsername, password, companyName, companyEmail, industry, registrationNo, companyContact, accountStatus, lastLoginTime, verificationStatus) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', NOW(), 'Pending')");
                if ($stmtInsert->execute([$companyUsername, $hashedPassword, $companyName, $companyEmail, $industry, $registrationNo, $cleanContact])) {
                    $companyId = $pdo->lastInsertId();
                    
                    if (!empty($city)) {
                        $stmtLoc = $pdo->prepare("INSERT INTO companyLocation (companyid, city) VALUES (?, ?)");
                        $stmtLoc->execute([$companyId, $city]);
                    }
                    
                    if (!empty($contactPerson)) {
                        $nameParts = explode(' ', $contactPerson, 2);
                        $fn = $nameParts[0] ?? '';
                        $ln = $nameParts[1] ?? '';
                        $stmtContact = $pdo->prepare("INSERT INTO contactPerson (companyid, firstName, lastName, email, mobileNo) VALUES (?, ?, ?, ?, ?)");
                        $stmtContact->execute([$companyId, $fn, $ln, $companyEmail, $cleanContact]);
                    }
                    
                    $_SESSION['user_type'] = 'company';
                    $_SESSION['company_id'] = $companyId;
                    $_SESSION['username'] = $companyUsername;
                    $_SESSION['company_name'] = $companyName;
                    $_SESSION['email'] = $companyEmail;
                    
                    logActivity($pdo, null, $companyId, "Created corporate company account");
                    
                    header("Location: ../company/");
                    exit;
                } else {
                    $error_message = 'Company registration failed. Please try again.';
                }
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
    <title>SkillSync - Create Account</title>
    
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
        .reg-card {
            background-color: #ffffff;
            border: 2px solid var(--brand-dark);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 71, 67, 0.15);
            max-height: calc(100vh - 32px);
            overflow-y: auto;
            width: 100%;
            max-width: 620px;
            padding: 24px 28px;
        }
        .reg-tabs-wrapper {
            background-color: #ffffff;
            border: 1.5px solid var(--brand-dark);
            border-radius: 12px;
            padding: 3px;
        }
        .reg-tabs-wrapper .nav-link {
            color: var(--brand-dark) !important;
            font-weight: 700 !important;
            border-radius: 8px !important;
            padding: 8px 14px !important;
            font-size: 0.85rem;
            transition: all 0.25s ease-in-out !important;
            background-color: transparent;
            border: none !important;
        }
        .reg-tabs-wrapper .nav-link.active {
            background-color: var(--brand-dark) !important;
            color: var(--brand-accent) !important;
        }
        .reg-input {
            border: 1.5px solid var(--brand-dark) !important;
            border-radius: 8px !important;
            padding: 7px 12px !important;
            font-size: 0.85rem !important;
        }
        .reg-input:focus {
            border-color: var(--brand-dark) !important;
            box-shadow: 0 0 0 3px rgba(0, 71, 67, 0.15) !important;
        }
        .btn-brand {
            background-color: var(--brand-dark);
            color: var(--brand-accent) !important;
            font-weight: 700;
            border: 1.5px solid var(--brand-dark);
            border-radius: 4px;
            padding: 8px 18px;
            transition: all 0.2s ease-in-out;
        }
        .btn-brand:hover {
            background-color: #003633;
            color: var(--brand-accent) !important;
        }
    </style>
</head>
<body>

<div class="reg-card">
    <!-- Brand Header -->
    <div class="text-center mb-3">
        <a href="../" class="d-inline-block mb-1">
            <img src="../assets/img/logo.webp" alt="SkillSync Logo" height="34">
        </a>
        <h3 class="fw-bold text-dark mb-0 fs-5">Create SkillSync Account</h3>
    </div>

    <script src="../assets/js/toast.js"></script>
    <?php if (!empty($error_message)): ?>
        <script>document.addEventListener('DOMContentLoaded', () => showToast(<?= json_encode($error_message) ?>, 'danger'));</script>
    <?php endif; ?>
    <?php if (!empty($success_message)): ?>
        <script>document.addEventListener('DOMContentLoaded', () => showToast(<?= json_encode($success_message) ?>, 'success'));</script>
    <?php endif; ?>

    <!-- Registration Type Selector Tabs (Candidate vs Company) -->
    <div class="mb-3">
        <ul class="nav nav-pills nav-justified reg-tabs-wrapper" id="regTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $active_tab === 'user' ? 'active' : '' ?>" id="user-reg-tab" data-bs-toggle="tab" data-bs-target="#user-reg-panel" type="button" role="tab">
                    <i class="bi bi-person-badge me-1"></i>Job Seeker Candidate
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $active_tab === 'company' ? 'active' : '' ?>" id="company-reg-tab" data-bs-toggle="tab" data-bs-target="#company-reg-panel" type="button" role="tab">
                    <i class="bi bi-building-check me-1"></i>Corporate Company
                </button>
            </li>
        </ul>
    </div>

    <!-- Tab Contents -->
    <div class="tab-content" id="regTabsContent">

        <!-- ================= TAB 1: JOB SEEKER REGISTRATION ================= -->
        <div class="tab-pane fade <?= $active_tab === 'user' ? 'show active' : '' ?>" id="user-reg-panel" role="tabpanel">
            <form id="candidateRegForm" method="POST" action="./" onsubmit="return validateFormFields(this);">
                <input type="hidden" name="action" value="register_candidate">
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted mb-1">First Name <span class="text-danger">*</span></label>
                        <input type="text" name="firstName" class="form-control reg-input" value="<?= htmlspecialchars($_POST['firstName'] ?? '') ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted mb-1">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="lastName" class="form-control reg-input" value="<?= htmlspecialchars($_POST['lastName'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted mb-1">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control reg-input" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted mb-1">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control reg-input" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted mb-1">Mobile Contact</label>
                        <input type="tel" name="mobileNo" id="userMobileNo" class="form-control reg-input" data-numeric="true" maxlength="15" placeholder="e.g. 0771234567" value="<?= htmlspecialchars($_POST['mobileNo'] ?? '') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted mb-1">Professional Title</label>
                        <input type="text" name="profTitle" class="form-control reg-input" placeholder="e.g. Software Engineer" value="<?= htmlspecialchars($_POST['profTitle'] ?? '') ?>">
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label fw-bold small text-muted mb-1">Target Industry Sector</label>
                    <select name="industry" class="form-select reg-input">
                        <?= renderIndustryOptions($_POST['industry'] ?? '', '-- Select Target Industry Sector (Optional) --') ?>
                    </select>
                </div>

                <div class="row g-2 mb-1">
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted mb-1">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" id="userPassword" class="form-control reg-input" required minlength="8" placeholder="Min. 8 characters">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted mb-1">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirmPassword" id="userConfirmPassword" class="form-control reg-input" required minlength="8" placeholder="Repeat password">
                    </div>
                </div>

                <!-- Password Strength Level Indicator for Candidate -->
                <div id="candidatePasswordIndicator"></div>

                <div class="mb-3 form-check mt-2">
                    <input type="checkbox" class="form-check-input" id="termsUser" required>
                    <label class="form-check-label small text-muted" for="termsUser">I agree to SkillSync Terms & Privacy Policy</label>
                </div>

                <button type="submit" class="btn btn-brand w-100 rounded-4px py-2 fw-bold text-uppercase">
                    <i class="bi bi-person-plus-fill me-1"></i> Register Candidate Account
                </button>
            </form>
        </div>

        <!-- ================= TAB 2: CORPORATE COMPANY REGISTRATION ================= -->
        <div class="tab-pane fade <?= $active_tab === 'company' ? 'show active' : '' ?>" id="company-reg-panel" role="tabpanel">
            <form id="companyRegForm" method="POST" action="./" onsubmit="return validateFormFields(this);">
                <input type="hidden" name="action" value="register_company">
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted mb-1">Company Name <span class="text-danger">*</span></label>
                        <input type="text" name="companyName" class="form-control reg-input" value="<?= htmlspecialchars($_POST['companyName'] ?? '') ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted mb-1">Business Reg No. <span class="text-danger">*</span></label>
                        <input type="text" name="registrationNo" class="form-control reg-input" value="<?= htmlspecialchars($_POST['registrationNo'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted mb-1">Company Username <span class="text-danger">*</span></label>
                        <input type="text" name="companyUsername" class="form-control reg-input" value="<?= htmlspecialchars($_POST['companyUsername'] ?? '') ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted mb-1">Corporate Email <span class="text-danger">*</span></label>
                        <input type="email" name="companyEmail" class="form-control reg-input" value="<?= htmlspecialchars($_POST['companyEmail'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted mb-1">Industry Sector <span class="text-danger">*</span></label>
                        <select name="industry" class="form-select reg-input" required>
                            <?= renderIndustryOptions($_POST['industry'] ?? '') ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted mb-1">Contact Phone</label>
                        <input type="tel" name="companyContact" id="compContact" class="form-control reg-input" data-numeric="true" maxlength="15" placeholder="e.g. 0112345678" value="<?= htmlspecialchars($_POST['companyContact'] ?? '') ?>">
                    </div>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted mb-1">Contact Person</label>
                        <input type="text" name="contactPerson" class="form-control reg-input" placeholder="e.g. Jane Doe" value="<?= htmlspecialchars($_POST['contactPerson'] ?? '') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted mb-1">City / Location</label>
                        <input type="text" name="city" class="form-control reg-input" placeholder="e.g. Colombo" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                    </div>
                </div>

                <div class="row g-2 mb-1">
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted mb-1">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" id="compPassword" class="form-control reg-input" required minlength="8" placeholder="Min. 8 characters">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted mb-1">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirmPassword" id="compConfirmPassword" class="form-control reg-input" required minlength="8" placeholder="Repeat password">
                    </div>
                </div>

                <!-- Password Strength Level Indicator for Company -->
                <div id="compPasswordIndicator"></div>

                <div class="mb-3 form-check mt-2">
                    <input type="checkbox" class="form-check-input" id="termsCompany" required>
                    <label class="form-check-label small text-muted" for="termsCompany">Authorized representative of company entity</label>
                </div>

                <button type="submit" class="btn btn-brand w-100 rounded-4px py-2 fw-bold text-uppercase">
                    <i class="bi bi-building-check me-1"></i> Register Company Account
                </button>
            </form>
        </div>

    </div>

    <!-- Shortcut to Login -->
    <div class="text-center mt-3 pt-2 border-top">
        <p class="small text-muted mb-0">
            Already have an account? 
            <a href="../login/" class="fw-bold text-decoration-none" style="color: var(--brand-dark);">
                Sign In Here <i class="bi bi-box-arrow-in-right me-1"></i>
            </a>
        </p>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/validation.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Attach Password Strength Level Indicators
    if (typeof attachPasswordStrengthIndicator === 'function') {
        attachPasswordStrengthIndicator('userPassword', 'userConfirmPassword', 'candidatePasswordIndicator');
        attachPasswordStrengthIndicator('compPassword', 'compConfirmPassword', 'companyPasswordIndicator');
    }
});
</script>
</body>
</html>
