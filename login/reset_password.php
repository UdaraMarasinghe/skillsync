<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/activity_logger.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$action = trim($_POST['action'] ?? '');

if ($action === 'check_approved') {
    $identity = trim($_POST['identity'] ?? '');
    if (empty($identity)) {
        echo json_encode(['success' => false, 'has_approved' => false]);
        exit;
    }

    $accountType = null;
    $accountId = null;
    $accountName = '';
    $accountEmail = '';

    // 1. Check User table
    $stmtUser = $pdo->prepare("SELECT userid, username, firstName, lastName, email, accStatus FROM user WHERE username = ? OR email = ? LIMIT 1");
    $stmtUser->execute([$identity, $identity]);
    $user = $stmtUser->fetch();

    if ($user) {
        if ($user['accStatus'] !== 'Suspended') {
            $accountType = 'user';
            $accountId = (int)$user['userid'];
            $accountName = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')) ?: $user['username'];
            $accountEmail = $user['email'];
        }
    } else {
        // 2. Check Company table
        $stmtComp = $pdo->prepare("SELECT companyid, companyUsername, companyName, companyEmail, accountStatus FROM company WHERE companyUsername = ? OR companyEmail = ? LIMIT 1");
        $stmtComp->execute([$identity, $identity]);
        $comp = $stmtComp->fetch();

        if ($comp) {
            if ($comp['accountStatus'] !== 'Suspended') {
                $accountType = 'company';
                $accountId = (int)$comp['companyid'];
                $accountName = $comp['companyName'];
                $accountEmail = $comp['companyEmail'];
            }
        }
    }

    if (!$accountType) {
        echo json_encode(['success' => false, 'has_approved' => false]);
        exit;
    }

    $stmtReq = $pdo->prepare("SELECT * FROM password_resets WHERE user_type = ? AND account_id = ? AND status = 'Approved' ORDER BY id DESC LIMIT 1");
    $stmtReq->execute([$accountType, $accountId]);
    $approvedReq = $stmtReq->fetch();

    if ($approvedReq) {
        echo json_encode([
            'success' => true,
            'has_approved' => true,
            'status' => 'Approved',
            'identity' => $identity,
            'account_name' => $accountName,
            'account_email' => $accountEmail,
            'reset_token' => $approvedReq['reset_token'],
            'message' => "Your password reset request has been approved by the administrator! Please configure your new password below."
        ]);
        exit;
    }

    echo json_encode(['success' => true, 'has_approved' => false]);
    exit;
}

if ($action === 'request_or_check') {
    $identity = trim($_POST['identity'] ?? '');
    $forceNew = isset($_POST['force_new']) && $_POST['force_new'] === '1';

    if (empty($identity)) {
        echo json_encode(['success' => false, 'message' => 'Please enter your email or username.']);
        exit;
    }

    $accountType = null;
    $accountId = null;
    $accountName = '';
    $accountEmail = '';

    // 1. Check User table
    $stmtUser = $pdo->prepare("SELECT userid, username, firstName, lastName, email, accStatus FROM user WHERE username = ? OR email = ? LIMIT 1");
    $stmtUser->execute([$identity, $identity]);
    $user = $stmtUser->fetch();

    if ($user) {
        if ($user['accStatus'] === 'Suspended') {
            echo json_encode(['success' => false, 'message' => 'This account has been suspended. Please contact the administrator.']);
            exit;
        }
        $accountType = 'user';
        $accountId = (int)$user['userid'];
        $accountName = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')) ?: $user['username'];
        $accountEmail = $user['email'];
    } else {
        // 2. Check Company table
        $stmtComp = $pdo->prepare("SELECT companyid, companyUsername, companyName, companyEmail, accountStatus FROM company WHERE companyUsername = ? OR companyEmail = ? LIMIT 1");
        $stmtComp->execute([$identity, $identity]);
        $comp = $stmtComp->fetch();

        if ($comp) {
            if ($comp['accountStatus'] === 'Suspended') {
                echo json_encode(['success' => false, 'message' => 'This company account has been suspended. Please contact the administrator.']);
                exit;
            }
            $accountType = 'company';
            $accountId = (int)$comp['companyid'];
            $accountName = $comp['companyName'];
            $accountEmail = $comp['companyEmail'];
        }
    }

    if (!$accountType) {
        echo json_encode(['success' => false, 'message' => 'No registered account found matching that email or username.']);
        exit;
    }

    // Check latest active password reset request
    $stmtReq = $pdo->prepare("SELECT * FROM password_resets WHERE user_type = ? AND account_id = ? ORDER BY id DESC LIMIT 1");
    $stmtReq->execute([$accountType, $accountId]);
    $existingReq = $stmtReq->fetch();

    if ($existingReq && !$forceNew) {
        if ($existingReq['status'] === 'Pending') {
            $reqDate = date('M d, Y h:i A', strtotime($existingReq['requested_at']));
            echo json_encode([
                'success' => true,
                'status' => 'Pending',
                'account_name' => $accountName,
                'account_email' => $accountEmail,
                'requested_at' => $reqDate,
                'message' => "Your password reset request submitted on {$reqDate} is awaiting administrator approval."
            ]);
            exit;
        } elseif ($existingReq['status'] === 'Approved') {
            echo json_encode([
                'success' => true,
                'status' => 'Approved',
                'account_name' => $accountName,
                'account_email' => $accountEmail,
                'reset_token' => $existingReq['reset_token'],
                'message' => "Your password reset request has been approved by the administrator! You can now set your new password."
            ]);
            exit;
        }
    }

    // Create a new reset request
    $token = bin2hex(random_bytes(24));
    $stmtInsert = $pdo->prepare("INSERT INTO password_resets (user_type, account_id, identity, account_name, account_email, status, reset_token, requested_at) VALUES (?, ?, ?, ?, ?, 'Pending', ?, NOW())");
    $stmtInsert->execute([$accountType, $accountId, $identity, $accountName, $accountEmail, $token]);

    if ($accountType === 'user') {
        logActivity($pdo, $accountId, null, "Submitted password reset request");
    } else {
        logActivity($pdo, null, $accountId, "Submitted password reset request");
    }

    echo json_encode([
        'success' => true,
        'status' => 'Pending',
        'account_name' => $accountName,
        'account_email' => $accountEmail,
        'message' => "Your password reset request has been submitted successfully. Please wait for administrator approval."
    ]);
    exit;
}

if ($action === 'complete_reset') {
    $identity = trim($_POST['identity'] ?? '');
    $resetToken = trim($_POST['reset_token'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($identity) || empty($resetToken) || empty($newPassword)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }

    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match. Please re-enter.']);
        exit;
    }

    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long.']);
        exit;
    }

    // Fetch approved reset request matching token
    $stmtReq = $pdo->prepare("SELECT * FROM password_resets WHERE reset_token = ? AND status = 'Approved' ORDER BY id DESC LIMIT 1");
    $stmtReq->execute([$resetToken]);
    $resetRecord = $stmtReq->fetch();

    if (!$resetRecord) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired password reset authorization.']);
        exit;
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    $userType = $resetRecord['user_type'];
    $accountId = (int)$resetRecord['account_id'];

    if ($userType === 'user') {
        $stmtUp = $pdo->prepare("UPDATE user SET password = ? WHERE userid = ?");
        $stmtUp->execute([$hashedPassword, $accountId]);
        logActivity($pdo, $accountId, null, "Password was reset and updated successfully");
    } else {
        $stmtUp = $pdo->prepare("UPDATE company SET password = ? WHERE companyid = ?");
        $stmtUp->execute([$hashedPassword, $accountId]);
        logActivity($pdo, null, $accountId, "Password was reset and updated successfully");
    }

    // Mark reset record as Completed
    $stmtMark = $pdo->prepare("UPDATE password_resets SET status = 'Completed', completed_at = NOW() WHERE id = ?");
    $stmtMark->execute([$resetRecord['id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Your password has been reset successfully! You can now log in with your new password.'
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action requested.']);
