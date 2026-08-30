<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/activity_logger.php';
require_once __DIR__ . '/../includes/industries.php';

// Prevent browser caching on company portal
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['company_id']) && (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'company')) {
    header("Location: ../login/");
    exit;
}

$companyId = $_SESSION['company_id'] ?? null;
if (!$companyId) {
    $stmtCompApp = $pdo->query("
        SELECT v.companyid 
        FROM appliedJobs aj 
        JOIN vacancy v ON aj.vacancyid = v.vacancyid 
        ORDER BY aj.applicationid DESC 
        LIMIT 1
    ");
    $companyId = $stmtCompApp->fetchColumn();
    if (!$companyId) {
        $stmtFirstComp = $pdo->query("SELECT companyid FROM company ORDER BY companyid ASC LIMIT 1");
        $companyId = $stmtFirstComp->fetchColumn() ?: 1;
    }
}
$success_msg = '';
$error_msg = '';

if (isset($_SESSION['success_msg'])) {
    $success_msg = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
if (isset($_SESSION['error_msg'])) {
    $error_msg = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

// Handle Company Profile Information Update
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['update_company_profile_submit'])) {
    $compName = trim($_POST['companyName'] ?? '');
    $regNo = trim($_POST['registrationNo'] ?? '');
    $indSector = trim($_POST['industry'] ?? '');
    $compEmail = trim($_POST['companyEmail'] ?? '');
    $compContact = trim($_POST['companyContact'] ?? '');
    $compCity = trim($_POST['city'] ?? '');
    $compDesc = trim($_POST['companyDescription'] ?? '');
    $contactName = trim($_POST['contactName'] ?? '');
    $contactPos = trim($_POST['contactPosition'] ?? '');

    // Fetch existing company logo
    $stmtCurLogo = $pdo->prepare("SELECT companyLogo FROM company WHERE companyid = ?");
    $stmtCurLogo->execute([$companyId]);
    $companyLogo = $stmtCurLogo->fetchColumn() ?: '';

    // Handle logo file upload
    if (isset($_FILES['companyLogoFile']) && $_FILES['companyLogoFile']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['companyLogoFile']['tmp_name'];
        $fileName = $_FILES['companyLogoFile']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        if (in_array($fileExtension, $allowedExtensions)) {
            $uploadDir = __DIR__ . '/../assets/img/logos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $newFileName = 'logo_' . $companyId . '_' . time() . '.' . $fileExtension;
            $destPath = $uploadDir . $newFileName;
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $companyLogo = 'assets/img/logos/' . $newFileName;
            }
        }
    }

    if (!empty($compName) && !empty($compEmail)) {
        try {
            // Update company table with logo
            $stmtUpComp = $pdo->prepare("
                UPDATE company 
                SET companyName = ?, registrationNo = ?, industry = ?, companyEmail = ?, companyContact = ?, companyDescription = ?, companyLogo = ?
                WHERE companyid = ?
            ");
            $stmtUpComp->execute([$compName, $regNo, $indSector, $compEmail, $compContact, $compDesc, $companyLogo, $companyId]);

            // Upsert companyLocation table
            $stmtCheckLoc = $pdo->prepare("SELECT companyid FROM companyLocation WHERE companyid = ?");
            $stmtCheckLoc->execute([$companyId]);
            if ($stmtCheckLoc->fetch()) {
                $stmtUpLoc = $pdo->prepare("UPDATE companyLocation SET city = ? WHERE companyid = ?");
                $stmtUpLoc->execute([$compCity, $companyId]);
            } else {
                $stmtInsLoc = $pdo->prepare("INSERT INTO companyLocation (companyid, city) VALUES (?, ?)");
                $stmtInsLoc->execute([$companyId, $compCity]);
            }

            // Upsert contactPerson table
            $nameParts = explode(' ', $contactName, 2);
            $cFirstName = $nameParts[0] ?? '';
            $cLastName = $nameParts[1] ?? '';

            $stmtCheckCP = $pdo->prepare("SELECT contactPersonId FROM contactPerson WHERE companyid = ?");
            $stmtCheckCP->execute([$companyId]);
            if ($stmtCheckCP->fetch()) {
                $stmtUpCP = $pdo->prepare("UPDATE contactPerson SET firstName = ?, lastName = ?, position = ? WHERE companyid = ?");
                $stmtUpCP->execute([$cFirstName, $cLastName, $contactPos, $companyId]);
            } else {
                $stmtInsCP = $pdo->prepare("INSERT INTO contactPerson (companyid, firstName, lastName, position) VALUES (?, ?, ?, ?)");
                $stmtInsCP->execute([$companyId, $cFirstName, $cLastName, $contactPos]);
            }

            $_SESSION['success_msg'] = "Company Enterprise Profile updated successfully!";
            logActivity($pdo, null, $companyId, "Updated corporate company profile details");
            header("Location: ./?tab=profile");
            exit;
        } catch (PDOException $e) {
            $error_msg = "Database error: " . $e->getMessage();
        }
    } else {
        $error_msg = "Company Name and Company Email fields are required.";
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // 1. Handle New Vacancy Submission
    if (isset($_POST['post_vacancy_submit'])) {
        // Check if company is verified before allowing vacancy posting
        $stmtCheckVer = $pdo->prepare("SELECT verificationStatus FROM company WHERE companyid = ?");
        $stmtCheckVer->execute([$companyId]);
        $compVerStatus = $stmtCheckVer->fetchColumn();

        if ($compVerStatus !== 'Verified') {
            $_SESSION['error_msg'] = "Posting job vacancies is restricted to Verified corporate accounts. Your account verification status is currently Pending Review.";
            header("Location: ./?tab=vacancies");
            exit;
        }

        $jobTitle = trim($_POST['jobTitle'] ?? '');
        $industry = trim($_POST['industry'] ?? '');
        $jobLocation = trim($_POST['jobLocation'] ?? '');
        $salary = trim($_POST['salary'] ?? '');
        $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
        $requirements = trim($_POST['requirements'] ?? '');
        $jobDescription = trim($_POST['jobDescription'] ?? '');
        $vacancyImage = '';

        if (!empty($jobTitle) && !empty($industry) && !empty($jobLocation) && !empty($salary) && !empty($deadline)) {
            // Check for duplicate vacancy posted in the last 1 minute
            $stmtDupCheck = $pdo->prepare("
                SELECT vacancyid 
                FROM vacancy 
                WHERE companyid = ? AND LOWER(jobTitle) = LOWER(?) AND LOWER(jobLocation) = LOWER(?) AND createdAt > (NOW() - INTERVAL 1 MINUTE)
            ");
            $stmtDupCheck->execute([$companyId, $jobTitle, $jobLocation]);

            if ($stmtDupCheck->fetch()) {
                $_SESSION['success_msg'] = "Vacancy '{$jobTitle}' posted successfully!";
                header("Location: ./");
                exit;
            }

            if (isset($_FILES['vacancyImageFile']) && $_FILES['vacancyImageFile']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['vacancyImageFile']['tmp_name'];
                $fileName = $_FILES['vacancyImageFile']['name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (in_array($fileExtension, $allowedExtensions)) {
                    $uploadDir = __DIR__ . '/../assets/img/vacancies/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $newFileName = 'vac_' . time() . '_' . rand(100, 999) . '.' . $fileExtension;
                    $destPath = $uploadDir . $newFileName;
                    if (move_uploaded_file($fileTmpPath, $destPath)) {
                        $vacancyImage = 'assets/img/vacancies/' . $newFileName;
                    }
                }
            }

            try {
                $stmtVac = $pdo->prepare("
                    INSERT INTO vacancy (companyid, jobTitle, industry, jobDescription, requirements, jobLocation, salary, deadline, jobstatus, vacancyImage, createdAt)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Open', ?, NOW())
                ");
                $stmtVac->execute([$companyId, $jobTitle, $industry, $jobDescription, $requirements, $jobLocation, $salary, $deadline, $vacancyImage]);
                
                // Insert notification for company
                $stmtCompNotif = $pdo->prepare("INSERT INTO notification (companyid, notificationDescription, notificationDate, notificationTime) VALUES (?, ?, CURDATE(), CURTIME())");
                $stmtCompNotif->execute([$companyId, "New Job Vacancy Posted: " . $jobTitle]);

                logActivity($pdo, null, $companyId, "Published new job vacancy: " . $jobTitle);

                $_SESSION['success_msg'] = "Vacancy '{$jobTitle}' posted successfully!";
                header("Location: ./");
                exit;
            } catch (PDOException $e) {
                $error_msg = "Database error: " . $e->getMessage();
            }
        } else {
            $error_msg = "Please fill in all required fields including Industry Sector.";
        }
    }

    // 2. Handle Edit Vacancy Submission
    if (isset($_POST['update_vacancy_submit'])) {
        $vacancyId = intval($_POST['vacancyid'] ?? 0);
        $jobTitle = trim($_POST['jobTitle'] ?? '');
        $industry = trim($_POST['industry'] ?? '');
        $jobLocation = trim($_POST['jobLocation'] ?? '');
        $salary = trim($_POST['salary'] ?? '');
        $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
        $requirements = trim($_POST['requirements'] ?? '');
        $jobDescription = trim($_POST['jobDescription'] ?? '');

        // Check image update
        $stmtCurImg = $pdo->prepare("SELECT vacancyImage FROM vacancy WHERE vacancyid = ? AND companyid = ?");
        $stmtCurImg->execute([$vacancyId, $companyId]);
        $curImg = $stmtCurImg->fetchColumn();
        $vacancyImage = $curImg ?: '';

        if (isset($_FILES['vacancyImageFile']) && $_FILES['vacancyImageFile']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['vacancyImageFile']['tmp_name'];
            $fileName = $_FILES['vacancyImageFile']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($fileExtension, $allowedExtensions)) {
                $uploadDir = __DIR__ . '/../assets/img/vacancies/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $newFileName = 'vac_' . time() . '_' . rand(100, 999) . '.' . $fileExtension;
                $destPath = $uploadDir . $newFileName;
                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $vacancyImage = 'assets/img/vacancies/' . $newFileName;
                }
            }
        }

        if ($vacancyId > 0 && !empty($jobTitle) && !empty($industry) && !empty($jobLocation) && !empty($salary) && !empty($deadline)) {
            try {
                $stmtUpVac = $pdo->prepare("
                    UPDATE vacancy 
                    SET jobTitle = ?, industry = ?, jobDescription = ?, requirements = ?, jobLocation = ?, salary = ?, deadline = ?, vacancyImage = ?
                    WHERE vacancyid = ? AND companyid = ?
                ");
                $stmtUpVac->execute([$jobTitle, $industry, $jobDescription, $requirements, $jobLocation, $salary, $deadline, $vacancyImage, $vacancyId, $companyId]);
                logActivity($pdo, null, $companyId, "Updated job vacancy: " . $jobTitle);
                $_SESSION['success_msg'] = "Vacancy '{$jobTitle}' updated successfully!";
                header("Location: ./");
                exit;
            } catch (PDOException $e) {
                $error_msg = "Database error: " . $e->getMessage();
            }
        }
    }

    // 3. Handle Close Vacancy Request
    if (isset($_POST['close_vacancy_submit'])) {
        $vacancyId = intval($_POST['vacancyid'] ?? 0);
        if ($vacancyId > 0) {
            try {
                $stmtCloseVac = $pdo->prepare("UPDATE vacancy SET jobstatus = 'Closed' WHERE vacancyid = ? AND companyid = ?");
                $stmtCloseVac->execute([$vacancyId, $companyId]);
                logActivity($pdo, null, $companyId, "Closed job vacancy (ID: {$vacancyId})");
                $_SESSION['success_msg'] = "Vacancy marked as Closed.";
                header("Location: ./");
                exit;
            } catch (PDOException $e) {
                $error_msg = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Handle Application Status Update POST Request
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_app_status') {
    header('Content-Type: application/json');
    $appId = (int)($_POST['applicationid'] ?? 0);
    $status = trim($_POST['status'] ?? '');

    if ($appId > 0 && in_array($status, ['Accepted', 'Rejected', 'Scheduled', 'Pending'])) {
        try {
            $stmtUp = $pdo->prepare("UPDATE appliedJobs SET status = ? WHERE applicationid = ?");
            $stmtUp->execute([$status, $appId]);
            logActivity($pdo, null, $companyId, "Updated candidate application #{$appId} status to {$status}");

            // If status is Rejected, remove scheduled calendar events for this candidate & position
            if ($status === 'Rejected') {
                $stmtGetAppInfo = $pdo->prepare("
                    SELECT aj.userid, v.jobTitle 
                    FROM appliedJobs aj 
                    JOIN vacancy v ON aj.vacancyid = v.vacancyid 
                    WHERE aj.applicationid = ?
                ");
                $stmtGetAppInfo->execute([$appId]);
                $appInfo = $stmtGetAppInfo->fetch(PDO::FETCH_ASSOC);

                if ($appInfo) {
                    $candUserId = $appInfo['userid'];
                    $jobTitle = $appInfo['jobTitle'];

                    $stmtDelCal = $pdo->prepare("
                        DELETE FROM calendar 
                        WHERE userid = ? AND (activityName LIKE ? OR activityStatus = 'Scheduled')
                    ");
                    $stmtDelCal->execute([$candUserId, "%{$jobTitle}%"]);
                }
            }

            echo json_encode(['success' => true, 'message' => "Application status updated to {$status}."]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    }
    exit;
}

// Handle Schedule Interview & Sync to User Calendar POST Request
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'schedule_interview') {
    header('Content-Type: application/json');
    $appId = (int)($_POST['applicationid'] ?? 0);
    $actName = trim($_POST['activityName'] ?? '');
    $actDate = trim($_POST['activityDate'] ?? '');
    $actTime = trim($_POST['activityTime'] ?? '');

    if ($appId > 0 && !empty($actDate)) {
        try {
            // Retrieve candidate user ID, vacancy ID and job post name from application
            $stmtGetAppDetails = $pdo->prepare("
                SELECT aj.userid, aj.vacancyid, v.jobTitle 
                FROM appliedJobs aj 
                JOIN vacancy v ON aj.vacancyid = v.vacancyid 
                WHERE aj.applicationid = ?
            ");
            $stmtGetAppDetails->execute([$appId]);
            $appDetails = $stmtGetAppDetails->fetch(PDO::FETCH_ASSOC);

            if ($appDetails) {
                $candUserId = $appDetails['userid'];
                $vacId = $appDetails['vacancyid'];
                $jobPostName = $appDetails['jobTitle'];

                // Format activity title with job post name
                $baseTitle = !empty($actName) ? $actName : "{$jobPostName} Interview";
                $fullActTitle = $baseTitle . ($actTime ? " at " . date("g:i A", strtotime($actTime)) : "");

                // Insert into calendar table for candidate user with company & vacancy association
                $stmtCal = $pdo->prepare("INSERT INTO calendar (userid, companyid, vacancyid, activityDate, activityName, activityStatus) VALUES (?, ?, ?, ?, ?, 'Scheduled')");
                $stmtCal->execute([$candUserId, $companyId, $vacId, $actDate, $fullActTitle]);

                // Update appliedJobs status to Scheduled
                $stmtUp = $pdo->prepare("UPDATE appliedJobs SET status = 'Scheduled' WHERE applicationid = ?");
                $stmtUp->execute([$appId]);

                // Insert notifications for user and company
                $stmtUserNotif = $pdo->prepare("INSERT INTO notification (userid, notificationDescription, notificationDate, notificationTime) VALUES (?, ?, CURDATE(), CURTIME())");
                $stmtUserNotif->execute([$candUserId, "Interview Scheduled for " . $fullActTitle]);

                $stmtCompNotif = $pdo->prepare("INSERT INTO notification (companyid, notificationDescription, notificationDate, notificationTime) VALUES (?, ?, CURDATE(), CURTIME())");
                $stmtCompNotif->execute([$companyId, "Scheduled interview for " . $jobPostName]);

                logActivity($pdo, null, $companyId, "Scheduled interview for position " . $jobPostName . " on " . $actDate);
                logActivity($pdo, $candUserId, null, "Interview scheduled for " . $jobPostName . " on " . $actDate);

                echo json_encode(['success' => true, 'message' => 'Interview scheduled and synced to candidate calendar!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Candidate application record not found.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid scheduling details.']);
    }
    exit;
}

// Fetch active vacancies from DB for this company
$stmtCompVac = $pdo->prepare("
    SELECT v.*, (SELECT COUNT(*) FROM appliedJobs aj WHERE aj.vacancyid = v.vacancyid) AS applicantCount 
    FROM vacancy v 
    WHERE v.companyid = ? AND v.jobstatus = 'Open' 
    ORDER BY v.createdAt DESC
");
$stmtCompVac->execute([$companyId]);
$companyVacancies = $stmtCompVac->fetchAll();

// Fetch candidate applications for this company's vacancies
$stmtCompApps = $pdo->prepare("
    SELECT aj.*, u.firstName, u.lastName, u.username, u.email, u.mobileNo, u.profTitle, u.skills,
           v.jobTitle, r.resumes AS resumePath
    FROM appliedJobs aj
    JOIN vacancy v ON aj.vacancyid = v.vacancyid
    JOIN user u ON aj.userid = u.userid
    LEFT JOIN resume r ON aj.resumeid = r.resumeid
    WHERE v.companyid = ?
    ORDER BY aj.appliedDate DESC
");
$stmtCompApps->execute([$companyId]);
$companyApplicants = $stmtCompApps->fetchAll();

// Fetch scheduled interview events for this company
$stmtSchedList = $pdo->prepare("
    SELECT c.*, u.firstName, u.lastName, u.username, v.jobTitle, aj.status AS appStatus
    FROM calendar c
    JOIN user u ON c.userid = u.userid
    JOIN appliedJobs aj ON aj.userid = u.userid
    JOIN vacancy v ON aj.vacancyid = v.vacancyid
    WHERE v.companyid = ? AND (c.activityStatus = 'Scheduled' OR c.activityStatus = 'Accepted' OR c.activityStatus = 'Candidate Accepted')
    GROUP BY c.calendarid
    ORDER BY c.activityDate DESC
");
$stmtSchedList->execute([$companyId]);
$scheduledInterviews = $stmtSchedList->fetchAll();

// Compute Overview Metrics from Database
$stmtVacCount = $pdo->prepare("SELECT COUNT(*) FROM vacancy WHERE companyid = ? AND jobstatus = 'Open'");
$stmtVacCount->execute([$companyId]);
$activeVacanciesCount = (int)$stmtVacCount->fetchColumn();

$stmtPendingCvCount = $pdo->prepare("
    SELECT COUNT(*) 
    FROM appliedJobs aj 
    JOIN vacancy v ON aj.vacancyid = v.vacancyid 
    WHERE v.companyid = ? AND aj.status = 'Pending'
");
$stmtPendingCvCount->execute([$companyId]);
$pendingCvsCount = (int)$stmtPendingCvCount->fetchColumn();

$stmtSchedCount = $pdo->prepare("
    SELECT COUNT(DISTINCT c.calendarid) 
    FROM calendar c 
    JOIN user u ON c.userid = u.userid 
    JOIN appliedJobs aj ON aj.userid = u.userid 
    JOIN vacancy v ON aj.vacancyid = v.vacancyid 
    WHERE v.companyid = ? AND (c.activityStatus = 'Scheduled' OR c.activityStatus = 'Accepted' OR c.activityStatus = 'Candidate Accepted')
");
$stmtSchedCount->execute([$companyId]);
$scheduledInterviewsCount = (int)$stmtSchedCount->fetchColumn();

$stmtAcceptedCvCount = $pdo->prepare("
    SELECT COUNT(*) 
    FROM appliedJobs aj 
    JOIN vacancy v ON aj.vacancyid = v.vacancyid 
    WHERE v.companyid = ? AND (aj.status = 'Accepted' OR aj.status = 'Candidate Accepted')
");
$stmtAcceptedCvCount->execute([$companyId]);
$acceptedCvsCount = (int)$stmtAcceptedCvCount->fetchColumn();

// Fetch company enterprise profile details
$stmtCompData = $pdo->prepare("SELECT * FROM company WHERE companyid = ?");
$stmtCompData->execute([$companyId]);
$companyData = $stmtCompData->fetch(PDO::FETCH_ASSOC) ?: [];

// Fetch company activity history log
$stmtCompAct = $pdo->prepare("
    SELECT * FROM activityHistory 
    WHERE companyid = ? 
    ORDER BY activityDate DESC, activityTime DESC
");
$stmtCompAct->execute([$companyId]);
$companyActivityList = $stmtCompAct->fetchAll();

$stmtCompLoc = $pdo->prepare("SELECT * FROM companyLocation WHERE companyid = ?");
$stmtCompLoc->execute([$companyId]);
$companyLoc = $stmtCompLoc->fetch(PDO::FETCH_ASSOC) ?: [];

$stmtContact = $pdo->prepare("SELECT * FROM contactPerson WHERE companyid = ? LIMIT 1");
$stmtContact->execute([$companyId]);
$contactPersonData = $stmtContact->fetch(PDO::FETCH_ASSOC) ?: [];

$activeTab = $_GET['tab'] ?? 'overview';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkillSync - Company Enterprise Portal</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="../favicon.webp">
    
    <!-- Google Fonts Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Company Dashboard Custom CSS & Responsive Framework -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="../assets/css/device.css">
</head>
<body style="background-color: #f8faf9; font-family: 'Poppins', sans-serif;">

<!-- Standalone Company Header Navbar -->
<header class="navbar navbar-expand-lg sticky-top" style="background-color: var(--brand-dark, #004743); border-bottom: 3px solid var(--brand-accent, #ACFF78); padding: 12px 24px;">
    <div class="container-fluid">
        <!-- Brand Logo & Title -->
        <a class="navbar-brand d-flex align-items-center gap-3 text-white" href="../">
            <img src="../assets/img/logo-white.webp" alt="SkillSync Logo" height="38" class="d-block">
            <span class="border-start border-light ps-3 fw-bold fs-5 text-white" style="letter-spacing: 0.5px;">
                Corporate Portal
            </span>
        </a>

        <!-- Header Controls -->
        <div class="d-flex align-items-center gap-3 ms-auto">
            <span class="badge bg-white text-dark p-2 rounded-4px d-none d-md-inline-flex align-items-center gap-1 fw-bold">
                <i class="bi bi-patch-check-fill text-success"></i> <?= htmlspecialchars($companyData['companyName'] ?? 'Company Portal') ?>
            </span>
            <a href="../includes/auth.php" class="btn btn-sm btn-company-accent rounded-4px">
                <i class="bi bi-box-arrow-right me-1"></i> Exit Portal
            </a>
        </div>
    </div>
</header>

<div class="container-fluid px-4 py-4" style="min-height: 88vh;">

    <?php if (!empty($success_msg)): ?>
        <script>document.addEventListener('DOMContentLoaded', () => showToast(<?= json_encode($success_msg) ?>, 'success'));</script>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <script>document.addEventListener('DOMContentLoaded', () => showToast(<?= json_encode($error_msg) ?>, 'danger'));</script>
    <?php endif; ?>

    <!-- Navigation Tabs (Overview, Vacancies, Applicants, Profile, Calendar) -->
    <div class="row justify-content-center mb-4">
        <div class="col-12 col-lg-10">
            <ul class="nav nav-pills nav-justified company-tabs-wrapper shadow-sm" id="companyTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'overview' ? 'active' : '' ?>" id="overview-tab" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button" role="tab">
                        <i class="bi bi-speedometer2 me-1"></i>Overview
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'profile' ? 'active' : '' ?>" id="profile-tab" data-bs-toggle="tab" data-bs-target="#tab-profile" type="button" role="tab">
                        <i class="bi bi-building-gear me-1"></i>Company Profile
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'vacancies' ? 'active' : '' ?>" id="vacancies-tab" data-bs-toggle="tab" data-bs-target="#tab-vacancies" type="button" role="tab">
                        <i class="bi bi-briefcase me-1"></i>Job Vacancies
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'applicants' ? 'active' : '' ?>" id="applicants-tab" data-bs-toggle="tab" data-bs-target="#tab-applicants" type="button" role="tab">
                        <i class="bi bi-person-lines-fill me-1"></i>CV Applications
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'calendar' ? 'active' : '' ?>" id="calendar-tab" data-bs-toggle="tab" data-bs-target="#tab-calendar" type="button" role="tab">
                        <i class="bi bi-calendar-check me-1"></i>Interviews Calendar
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <!-- Tab Contents -->
    <div class="tab-content" id="companyTabsContent">

        <!-- ================= TAB 1: OVERVIEW ================= -->
        <div class="tab-pane fade <?= $activeTab === 'overview' ? 'show active' : '' ?>" id="tab-overview" role="tabpanel">
            
            <!-- Company Enterprise Overview Banner -->
            <div class="company-card mb-4 p-4" style="background: linear-gradient(135deg, var(--brand-dark, #004743) 0%, #002e2b 100%); color: white; border-radius: 12px;">
                <div class="row align-items-center g-3">
                    <div class="col-auto">
                        <?php if (!empty($companyData['companyLogo'])): ?>
                            <img src="../<?= htmlspecialchars($companyData['companyLogo']) ?>" alt="Logo" class="rounded-12px bg-white p-2 border border-2 border-accent" style="width: 80px; height: 80px; object-fit: contain;">
                        <?php else: ?>
                            <div class="rounded-12px bg-white text-dark d-flex align-items-center justify-content-center fw-bold fs-2 border border-2 border-accent" style="width: 80px; height: 80px;">
                                <?= strtoupper(substr($companyData['companyName'] ?? 'C', 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <h4 class="fw-bold mb-0 text-white"><?= htmlspecialchars($companyData['companyName'] ?? 'Company Name') ?></h4>
                            <?php if (($companyData['verificationStatus'] ?? 'Pending') === 'Verified'): ?>
                                <span class="badge bg-success text-white"><i class="bi bi-patch-check-fill me-1"></i> Verified Enterprise</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-clock-history me-1"></i> Verification Pending</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-white-50 small mb-2"><?= htmlspecialchars($companyData['companyDescription'] ?? 'No company description provided yet.') ?></p>
                        <div class="d-flex flex-wrap gap-3 small text-white-50">
                            <span><i class="bi bi-building me-1 text-accent"></i> <strong>Industry:</strong> <?= htmlspecialchars($companyData['industry'] ?? 'N/A') ?></span>
                            <span><i class="bi bi-file-earmark-text me-1 text-accent"></i> <strong>Reg No:</strong> <?= htmlspecialchars($companyData['registrationNo'] ?? 'N/A') ?></span>
                            <span><i class="bi bi-geo-alt me-1 text-accent"></i> <strong>Location:</strong> <?= htmlspecialchars($companyLoc['city'] ?? 'N/A') ?><?= !empty($companyLoc['province']) ? ', ' . htmlspecialchars($companyLoc['province']) : '' ?></span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-company-accent btn-sm" onclick="document.getElementById('profile-tab').click()">
                            <i class="bi bi-pencil-square me-1"></i> Edit Profile
                        </button>
                    </div>
                </div>
            </div>

            <!-- Metric Cards Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-6 col-xl-3">
                    <div class="metric-card d-flex align-items-center justify-content-between">
                        <div>
                            <div class="metric-label">Active Vacancies</div>
                            <div class="metric-value" id="metric-active-vacancies"><?= $activeVacanciesCount ?></div>
                            <span class="text-muted small"><i class="bi bi-briefcase text-success"></i> Open Positions</span>
                        </div>
                        <div class="icon-box">
                            <i class="bi bi-briefcase-fill"></i>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="metric-card d-flex align-items-center justify-content-between">
                        <div>
                            <div class="metric-label">Pending CVs</div>
                            <div class="metric-value" id="metric-pending-cvs"><?= $pendingCvsCount ?></div>
                            <span class="text-muted small"><i class="bi bi-clock-history text-warning"></i> Needs Review</span>
                        </div>
                        <div class="icon-box">
                            <i class="bi bi-file-earmark-person-fill"></i>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="metric-card d-flex align-items-center justify-content-between">
                        <div>
                            <div class="metric-label">Interviews Scheduled</div>
                            <div class="metric-value" id="metric-scheduled-interviews"><?= $scheduledInterviewsCount ?></div>
                            <span class="text-muted small"><i class="bi bi-calendar-event text-primary"></i> Scheduled Sessions</span>
                        </div>
                        <div class="icon-box">
                            <i class="bi bi-calendar-range-fill"></i>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="metric-card d-flex align-items-center justify-content-between">
                        <div>
                            <div class="metric-label">Accepted CVs</div>
                            <div class="metric-value" id="metric-accepted-cvs"><?= $acceptedCvsCount ?></div>
                            <span class="text-muted small"><i class="bi bi-check-circle-fill text-success"></i> Qualified Applicants</span>
                        </div>
                        <div class="icon-box">
                            <i class="bi bi-person-check-fill"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Overview Main Grid -->
            <div class="row g-4">
                <!-- Recent Applicants Quick Table -->
                <div class="col-lg-8">
                    <div class="company-card h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-stars text-brand me-2"></i>Recent Candidate Applications</h5>
                            <button class="btn btn-company-secondary btn-sm" onclick="document.getElementById('applicants-tab').click()">View All</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-company align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Applicant Name</th>
                                        <th>Applied Vacancy</th>
                                        <th>Match Score</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($companyApplicants)): ?>
                                        <?php foreach (array_slice($companyApplicants, 0, 5) as $app): ?>
                                            <?php 
                                                $appId = $app['applicationid'];
                                                $candName = trim(($app['firstName'] ?? '') . ' ' . ($app['lastName'] ?? '')) ?: ($app['username'] ?? 'Applicant');
                                                $initials = strtoupper(substr($app['firstName'] ?? $candName, 0, 1) . substr($app['lastName'] ?? '', 0, 1));
                                                if (empty(trim($initials))) $initials = 'AP';
                                                $jobTitle = $app['jobTitle'] ?? 'Position';
                                                $status = $app['status'] ?? 'Pending';
                                                $badgeClass = 'badge-pending';
                                                if ($status === 'Accepted') $badgeClass = 'badge-accepted';
                                                elseif ($status === 'Rejected') $badgeClass = 'badge-rejected';
                                                elseif ($status === 'Scheduled') $badgeClass = 'badge-scheduled';
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="rounded-circle bg-dark text-accent d-flex align-items-center justify-content-center fw-bold" style="width:36px; height:36px;"><?= htmlspecialchars($initials) ?></div>
                                                        <div>
                                                            <div class="fw-bold text-dark mb-0"><?= htmlspecialchars($candName) ?></div>
                                                            <small class="text-muted"><?= htmlspecialchars($app['email'] ?? '') ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><span class="fw-semibold"><?= htmlspecialchars($jobTitle) ?></span></td>
                                                <td><span class="badge bg-dark text-accent font-monospace">Verified</span></td>
                                                <td><span class="badge badge-status <?= $badgeClass ?>" id="status-badge-quick-<?= $appId ?>"><?= htmlspecialchars($status) ?></span></td>
                                                <td>
                                                    <div class="d-flex gap-1" id="quick-actions-<?= $appId ?>">
                                                        <?php if ($status === 'Rejected'): ?>
                                                            <span class="badge bg-secondary p-2 text-white"><i class="bi bi-slash-circle me-1"></i> Rejected (No Actions)</span>
                                                        <?php else: ?>
                                                            <button class="btn btn-action-accept" onclick="requestApplicantStatus(<?= $appId ?>, 'Accepted', '<?= htmlspecialchars($candName, ENT_QUOTES) ?>', '<?= htmlspecialchars($jobTitle, ENT_QUOTES) ?>')" title="Accept CV"><i class="bi bi-check-lg"></i></button>
                                                            <button class="btn btn-action-reject" onclick="requestApplicantStatus(<?= $appId ?>, 'Rejected', '<?= htmlspecialchars($candName, ENT_QUOTES) ?>', '<?= htmlspecialchars($jobTitle, ENT_QUOTES) ?>')" title="Reject CV"><i class="bi bi-x-lg"></i></button>
                                                            <button class="btn btn-action-schedule" onclick="openScheduleModal(<?= $appId ?>, '<?= htmlspecialchars($candName, ENT_QUOTES) ?>', '<?= htmlspecialchars($jobTitle, ENT_QUOTES) ?>')" title="Schedule Interview"><i class="bi bi-calendar-plus"></i></button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-center py-4 text-muted">No candidate applications received yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Quick Job Posting Shortcut & Activity Log -->
                <div class="col-lg-4 d-flex flex-column gap-4">
                    <div class="company-card d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <div class="rounded-3 bg-dark text-accent p-2">
                                    <i class="bi bi-plus-circle-fill fs-4"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark mb-0">Post New Vacancy</h5>
                                    <small class="text-muted">Create a company job opening</small>
                                </div>
                            </div>
                            <p class="text-muted small">Post new job openings directly to the SkillSync platform to start receiving ATS-filtered candidate resumes immediately.</p>
                        </div>
                        <button class="btn btn-company-accent w-100 mt-3" onclick="document.getElementById('vacancies-tab').click()">
                            <i class="bi bi-plus-lg me-1"></i> Open Vacancy Builder
                        </button>
                    </div>

                    <!-- Company Activity History Card -->
                    <div class="company-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history text-brand me-2"></i>Company Activity Log History</h5>
                            <button type="button" class="btn btn-company-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#companyActivityHistoryModal" onclick="openCompanyActivityHistoryModal()">
                                View History
                            </button>
                        </div>
                        <?php if (!empty($companyActivityList)): ?>
                            <div class="timeline border-start border-2 border-brand ps-3">
                                <?php foreach (array_slice($companyActivityList, 0, 4) as $idx => $act): ?>
                                    <div class="<?= $idx < 3 ? 'mb-3' : '' ?> position-relative">
                                        <div class="fw-bold text-dark mb-1" style="font-size: 0.88rem;">
                                            <i class="bi bi-activity text-brand me-1"></i> <?= htmlspecialchars($act['activityHistory'] ?? '') ?>
                                        </div>
                                        <small class="text-muted"><i class="bi bi-clock me-1"></i> <?= htmlspecialchars(($act['activityDate'] ?? '') . ' ' . ($act['activityTime'] ?? '')) ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted small mb-0">No company activity logged yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= TAB 2: JOB VACANCIES ================= -->
        <div class="tab-pane fade <?= $activeTab === 'vacancies' ? 'show active' : '' ?>" id="tab-vacancies" role="tabpanel">
            <div class="row g-4">
                
                <!-- Create Vacancy Form -->
                <div class="col-lg-5">
                    <div class="company-card">
                        <h5 class="fw-bold text-dark mb-3">
                            <i class="bi bi-file-earmark-plus text-brand me-2"></i>Create New Job Vacancy
                        </h5>
                        <?php if (($companyData['verificationStatus'] ?? 'Pending') !== 'Verified'): ?>
                            <div class="alert alert-warning py-2 small mb-3 rounded-8px shadow-sm" role="alert">
                                <i class="bi bi-shield-exclamation me-1 fs-6"></i>
                                <strong>Verification Restricted:</strong> Posting job vacancies is restricted to Verified corporate accounts. Your current verification status is <strong><?= htmlspecialchars($companyData['verificationStatus'] ?? 'Pending') ?></strong>.
                            </div>
                        <?php endif; ?>
                        <form method="POST" action="./" enctype="multipart/form-data">
                            <input type="hidden" name="post_vacancy_submit" value="1">
                            <div class="mb-3">
                                <label for="vacTitle" class="form-label fw-bold">Job Title <span class="text-danger">*</span></label>
                                <input type="text" name="jobTitle" class="form-control company-input" id="vacTitle" placeholder="e.g. Senior Software Engineer" <?= ($companyData['verificationStatus'] ?? 'Pending') !== 'Verified' ? 'disabled' : '' ?> required>
                            </div>

                            <div class="mb-3">
                                <label for="vacIndustry" class="form-label fw-bold">Industry Sector <span class="text-danger">*</span></label>
                                <select name="industry" class="form-select company-input" id="vacIndustry" <?= ($companyData['verificationStatus'] ?? 'Pending') !== 'Verified' ? 'disabled' : '' ?> required>
                                    <?= renderIndustryOptions($companyData['industry'] ?? '') ?>
                                </select>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label for="vacLocation" class="form-label fw-bold">Job Location <span class="text-danger">*</span></label>
                                    <input type="text" name="jobLocation" class="form-control company-input" id="vacLocation" placeholder="e.g. Colombo 03 / Remote" <?= ($companyData['verificationStatus'] ?? 'Pending') !== 'Verified' ? 'disabled' : '' ?> required>
                                </div>
                                <div class="col-6">
                                    <label for="vacSalary" class="form-label fw-bold">Salary Range <span class="text-danger">*</span></label>
                                    <input type="text" name="salary" class="form-control company-input" id="vacSalary" placeholder="e.g. LKR 250k - 350k" <?= ($companyData['verificationStatus'] ?? 'Pending') !== 'Verified' ? 'disabled' : '' ?> required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="vacImage" class="form-label fw-bold">Vacancy Banner Image</label>
                                <input type="file" name="vacancyImageFile" class="form-control company-input" id="vacImage" accept="image/*" <?= ($companyData['verificationStatus'] ?? 'Pending') !== 'Verified' ? 'disabled' : '' ?>>
                            </div>

                            <div class="mb-3">
                                <label for="vacDeadline" class="form-label fw-bold">Application Deadline <span class="text-danger">*</span></label>
                                <input type="date" name="deadline" class="form-control company-input" id="vacDeadline" <?= ($companyData['verificationStatus'] ?? 'Pending') !== 'Verified' ? 'disabled' : '' ?> required>
                            </div>

                            <div class="mb-3">
                                <label for="vacReqs" class="form-label fw-bold">Requirements Summary</label>
                                <textarea name="requirements" class="form-control company-input" id="vacReqs" rows="2" placeholder="e.g. 5+ Yrs React, Node.js, AWS, MySQL" <?= ($companyData['verificationStatus'] ?? 'Pending') !== 'Verified' ? 'disabled' : '' ?>></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="vacDesc" class="form-label fw-bold">Job Description</label>
                                <textarea name="jobDescription" class="form-control company-input" id="vacDesc" rows="3" placeholder="Provide detailed role description and expectations..." <?= ($companyData['verificationStatus'] ?? 'Pending') !== 'Verified' ? 'disabled' : '' ?>></textarea>
                            </div>

                            <button type="submit" class="btn btn-company-primary w-100" <?= ($companyData['verificationStatus'] ?? 'Pending') !== 'Verified' ? 'disabled' : '' ?>>
                                <i class="bi bi-send-check me-1"></i> Publish Vacancy Post
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Active Vacancies Table -->
                <div class="col-lg-7">
                    <div class="company-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-list-check text-brand me-2"></i>Active Company Vacancies</h5>
                            <span class="badge bg-dark text-accent"><?= count($companyVacancies) ?> Active Openings</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-company align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Job Title</th>
                                        <th>Industry</th>
                                        <th>Location</th>
                                        <th>Salary</th>
                                        <th>Deadline</th>
                                        <th>Status</th>
                                        <th>Applicants</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="vacanciesTableBody">
                                    <?php if (!empty($companyVacancies)): ?>
                                        <?php foreach ($companyVacancies as $v): ?>
                                            <tr id="vacancy-row-<?= $v['vacancyid'] ?>">
                                                <td class="fw-bold text-dark"><?= htmlspecialchars($v['jobTitle']) ?></td>
                                                <td><span class="badge bg-light text-dark border"><i class="bi bi-building me-1 text-brand"></i><?= htmlspecialchars($v['industry'] ?? $companyData['industry'] ?? 'General') ?></span></td>
                                                <td><i class="bi bi-geo-alt me-1 text-brand"></i><?= htmlspecialchars($v['jobLocation']) ?></td>
                                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($v['salary']) ?></span></td>
                                                <td><?= htmlspecialchars($v['deadline']) ?></td>
                                                <td><span class="badge badge-status badge-accepted"><?= htmlspecialchars($v['jobstatus']) ?></span></td>
                                                <td><span class="badge bg-dark text-accent"><?= intval($v['applicantCount']) ?> Applicants</span></td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <button class="btn btn-sm btn-outline-brand rounded-4px" type="button" data-bs-toggle="modal" data-bs-target="#editVacancyModal<?= $v['vacancyid'] ?>">
                                                            <i class="bi bi-pencil-square"></i> Edit
                                                        </button>
                                                        <form method="POST" action="./" onsubmit="return confirm('Are you sure you want to close this vacancy opening?');" class="d-inline">
                                                            <input type="hidden" name="close_vacancy_submit" value="1">
                                                            <input type="hidden" name="vacancyid" value="<?= $v['vacancyid'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-4px">Close</button>
                                                        </form>
                                                    </div>

                                                    <!-- Edit Vacancy Modal for this item -->
                                                    <div class="modal fade company-modal" id="editVacancyModal<?= $v['vacancyid'] ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                                            <div class="modal-content text-start">
                                                                <div class="modal-header bg-dark text-white">
                                                                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-accent me-2"></i>Edit Vacancy Details</h5>
                                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <form method="POST" action="./" enctype="multipart/form-data" onsubmit="return confirm('Are you sure you want to save changes to this vacancy?');">
                                                                    <input type="hidden" name="update_vacancy_submit" value="1">
                                                                    <input type="hidden" name="vacancyid" value="<?= $v['vacancyid'] ?>">
                                                                    <div class="modal-body p-4 bg-light">
                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-bold">Job Title <span class="text-danger">*</span></label>
                                                                            <input type="text" name="jobTitle" class="form-control company-input" value="<?= htmlspecialchars($v['jobTitle']) ?>" required>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-bold">Industry Sector <span class="text-danger">*</span></label>
                                                                            <select name="industry" class="form-select company-input" required>
                                                                                <?= renderIndustryOptions($v['industry'] ?? $companyData['industry'] ?? '') ?>
                                                                            </select>
                                                                        </div>
                                                                        <div class="row g-2 mb-3">
                                                                            <div class="col-6">
                                                                                <label class="form-label fw-bold">Job Location <span class="text-danger">*</span></label>
                                                                                <input type="text" name="jobLocation" class="form-control company-input" value="<?= htmlspecialchars($v['jobLocation']) ?>" required>
                                                                            </div>
                                                                            <div class="col-6">
                                                                                <label class="form-label fw-bold">Salary Range <span class="text-danger">*</span></label>
                                                                                <input type="text" name="salary" class="form-control company-input" value="<?= htmlspecialchars($v['salary']) ?>" required>
                                                                            </div>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-bold">Vacancy Banner Image</label>
                                                                            <input type="file" name="vacancyImageFile" class="form-control company-input" accept="image/*">
                                                                            <?php if (!empty($v['vacancyImage'])): ?>
                                                                                <small class="text-muted d-block mt-1">Current Image: <?= htmlspecialchars(basename($v['vacancyImage'])) ?></small>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-bold">Application Deadline <span class="text-danger">*</span></label>
                                                                            <input type="date" name="deadline" class="form-control company-input" value="<?= htmlspecialchars($v['deadline']) ?>" required>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-bold">Requirements Summary</label>
                                                                            <textarea name="requirements" class="form-control company-input" rows="2"><?= htmlspecialchars($v['requirements']) ?></textarea>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-bold">Job Description</label>
                                                                            <textarea name="jobDescription" class="form-control company-input" rows="3"><?= htmlspecialchars($v['jobDescription']) ?></textarea>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer bg-white">
                                                                        <button type="button" class="btn btn-secondary btn-sm rounded-4px" data-bs-dismiss="modal">Cancel</button>
                                                                        <button type="submit" class="btn btn-company-accent btn-sm rounded-4px font-weight-bold"><i class="bi bi-check-circle me-1"></i> Save Changes</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-3">No active vacancies posted yet. Fill out the form to publish a new vacancy.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ================= TAB 3: APPLICANT CV MANAGEMENT (ACCEPT/REJECT) ================= -->
        <div class="tab-pane fade <?= $activeTab === 'applicants' ? 'show active' : '' ?>" id="tab-applicants" role="tabpanel">
            <div class="company-card">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                    <div>
                        <h5 class="fw-bold text-dark mb-1"><i class="bi bi-people-fill text-brand me-2"></i>Job Applicant Resumes & Evaluation</h5>
                        <p class="text-muted small mb-0">Review candidate qualifications, preview CVs, accept or reject applications, and schedule interview sessions.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <input type="text" class="form-control company-input" style="width: 220px;" placeholder="Search candidate name...">
                        <select class="form-select company-input" style="width: 150px;">
                            <option value="all">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="accepted">Accepted</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-company align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Candidate Name</th>
                                <th>Applied Vacancy</th>
                                <th>Applied Date</th>
                                <th>Skill Match</th>
                                <th>Resume CV</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($companyApplicants)): ?>
                                <?php foreach ($companyApplicants as $app): ?>
                                    <?php 
                                        $appId = $app['applicationid'];
                                        $candName = trim(($app['firstName'] ?? '') . ' ' . ($app['lastName'] ?? '')) ?: ($app['username'] ?? 'Applicant');
                                        $jobTitle = $app['jobTitle'] ?? 'Position';
                                        $appliedDate = !empty($app['appliedDate']) ? date('Y-m-d', strtotime($app['appliedDate'])) : date('Y-m-d');
                                        $status = $app['status'] ?? 'Pending';
                                        $resPath = $app['resumePath'] ?? '';
                                        $resUrl = !empty($resPath) ? ((strpos($resPath, 'http') === 0 || strpos($resPath, '/') === 0) ? $resPath : '../' . $resPath) : '';
                                        $skills = !empty($app['skills']) ? $app['skills'] : 'General Profile Qualifications';
                                        $phone = !empty($app['mobileNo']) ? $app['mobileNo'] : 'Not provided';

                                        $badgeClass = 'badge-pending';
                                        if ($status === 'Accepted') $badgeClass = 'badge-accepted';
                                        elseif ($status === 'Rejected') $badgeClass = 'badge-rejected';
                                        elseif ($status === 'Scheduled') $badgeClass = 'badge-scheduled';
                                    ?>
                                    <tr id="applicant-row-<?= $appId ?>">
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($candName) ?></div>
                                            <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($phone) ?></small>
                                        </td>
                                        <td><span class="fw-semibold"><?= htmlspecialchars($jobTitle) ?></span></td>
                                        <td><?= htmlspecialchars($appliedDate) ?></td>
                                        <td><span class="badge bg-dark text-accent font-monospace">Verified</span></td>
                                        <td>
                                            <?php if (!empty($resUrl)): ?>
                                                <button class="btn btn-sm btn-company-secondary" onclick="viewCandidateCV('<?= htmlspecialchars($candName, ENT_QUOTES) ?>', '<?= htmlspecialchars($jobTitle, ENT_QUOTES) ?>', '<?= htmlspecialchars($skills, ENT_QUOTES) ?>', '<?= htmlspecialchars($resUrl, ENT_QUOTES) ?>')">
                                                    <i class="bi bi-file-earmark-pdf me-1"></i> View CV
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">No Resume Attached</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge badge-status <?= $badgeClass ?>" id="status-badge-<?= $appId ?>"><?= htmlspecialchars($status) ?></span></td>
                                        <td>
                                            <div class="d-flex gap-1" id="actions-<?= $appId ?>">
                                                <?php if ($status === 'Rejected'): ?>
                                                    <span class="badge bg-secondary p-2 text-white"><i class="bi bi-slash-circle me-1"></i> Rejected (No Actions)</span>
                                                <?php else: ?>
                                                    <button class="btn btn-action-accept" onclick="requestApplicantStatus(<?= $appId ?>, 'Accepted', '<?= htmlspecialchars($candName, ENT_QUOTES) ?>', '<?= htmlspecialchars($jobTitle, ENT_QUOTES) ?>')" title="Accept CV"><i class="bi bi-check-circle me-1"></i> Accept</button>
                                                    <button class="btn btn-action-reject" onclick="requestApplicantStatus(<?= $appId ?>, 'Rejected', '<?= htmlspecialchars($candName, ENT_QUOTES) ?>', '<?= htmlspecialchars($jobTitle, ENT_QUOTES) ?>')" title="Reject CV"><i class="bi bi-x-circle me-1"></i> Reject</button>
                                                    <button class="btn btn-action-schedule" onclick="openScheduleModal(<?= $appId ?>, '<?= htmlspecialchars($candName, ENT_QUOTES) ?>', '<?= htmlspecialchars($jobTitle, ENT_QUOTES) ?>')" title="Schedule Interview"><i class="bi bi-calendar-event me-1"></i> Schedule</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-people display-4 d-block mb-2 text-muted"></i>
                                        <p class="mb-0 fw-semibold">No candidate job applications received yet.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ================= TAB 4: COMPANY PROFILE ================= -->
        <div class="tab-pane fade <?= $activeTab === 'profile' ? 'show active' : '' ?>" id="tab-profile" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="company-card">
                        <h5 class="fw-bold text-dark mb-4">
                            <i class="bi bi-building text-brand me-2"></i>Company Enterprise Profile Information
                        </h5>
                        <form id="companyProfileForm" method="POST" action="./" enctype="multipart/form-data">
                            <input type="hidden" name="update_company_profile_submit" value="1">
                            
                            <div class="row g-3 mb-4 align-items-center">
                                <div class="col-auto">
                                    <?php if (!empty($companyData['companyLogo'])): ?>
                                        <img src="../<?= htmlspecialchars($companyData['companyLogo']) ?>" alt="Company Logo" class="rounded-circle border shadow-sm" style="width: 72px; height: 72px; object-fit: cover;" id="logoPreviewImg">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-dark text-accent d-inline-flex align-items-center justify-content-center border shadow-sm" style="width: 72px; height: 72px;" id="logoPreviewContainer">
                                            <i class="bi bi-building fs-2"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col">
                                    <label class="form-label fw-bold mb-1"><i class="bi bi-image text-brand me-1"></i>Company Logo</label>
                                    <input type="file" name="companyLogoFile" id="companyLogoFile" class="form-control company-input" accept="image/*">
                                    <div class="form-text text-muted small">Upload company logo (PNG, JPG, WEBP, SVG format).</div>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Company Name</label>
                                    <input type="text" name="companyName" class="form-control company-input" value="<?= htmlspecialchars($companyData['companyName'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Registration No.</label>
                                    <input type="text" name="registrationNo" class="form-control company-input" value="<?= htmlspecialchars($companyData['registrationNo'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Industry Sector</label>
                                    <select name="industry" class="form-select company-input" required>
                                        <?= renderIndustryOptions($companyData['industry'] ?? '') ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Company Email</label>
                                    <input type="email" name="companyEmail" class="form-control company-input" value="<?= htmlspecialchars($companyData['companyEmail'] ?? '') ?>" required>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Contact Telephone</label>
                                    <input type="text" name="companyContact" class="form-control company-input" value="<?= htmlspecialchars($companyData['companyContact'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">City / Location</label>
                                    <input type="text" name="city" class="form-control company-input" value="<?= htmlspecialchars($companyLoc['city'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Company Overview & Description</label>
                                <textarea name="companyDescription" class="form-control company-input" rows="4"><?= htmlspecialchars($companyData['companyDescription'] ?? '') ?></textarea>
                            </div>

                            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-person-badge text-brand me-2"></i>Primary Contact Person</h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Contact Name</label>
                                    <input type="text" name="contactName" class="form-control company-input" value="<?= htmlspecialchars(trim(($contactPersonData['firstName'] ?? '') . ' ' . ($contactPersonData['lastName'] ?? ''))) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Position / Title</label>
                                    <input type="text" name="contactPosition" class="form-control company-input" value="<?= htmlspecialchars($contactPersonData['position'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-company-primary" onclick="confirmCompanyProfileSave()">
                                    <i class="bi bi-save me-1"></i> Save Profile Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Company Verification Status Card -->
                <div class="col-lg-4">
                    <div class="company-card mb-4 text-center">
                        <?php if (!empty($companyData['companyLogo'])): ?>
                            <img src="../<?= htmlspecialchars($companyData['companyLogo']) ?>" alt="Company Logo" class="rounded-circle border shadow-sm mb-3" style="width:72px; height:72px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-dark text-accent d-inline-flex align-items-center justify-content-center mb-3" style="width:72px; height:72px;">
                                <i class="bi bi-patch-check-fill fs-2"></i>
                            </div>
                        <?php endif; ?>
                        <h5 class="fw-bold text-dark mb-1">Verified Corporate Account</h5>
                        <p class="text-muted small">Account Status: <span class="badge badge-status badge-accepted"><?= htmlspecialchars($companyData['accountStatus'] ?? 'Active') ?></span></p>
                        <hr>
                        <div class="text-start small">
                            <p class="mb-1"><strong>Verification Status:</strong> <?= htmlspecialchars($companyData['verificationStatus'] ?? 'Verified') ?></p>
                            <p class="mb-1"><strong>Last Login:</strong> <?= !empty($companyData['lastLoginTime']) ? date('Y-m-d H:i', strtotime($companyData['lastLoginTime'])) : date('Y-m-d H:i') ?></p>
                            <p class="mb-0"><strong>Location:</strong> <?= htmlspecialchars($companyLoc['city'] ?? 'Colombo, Sri Lanka') ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= TAB 5: INTERVIEW CALENDAR SCHEDULER ================= -->
        <div class="tab-pane fade <?= $activeTab === 'calendar' ? 'show active' : '' ?>" id="tab-calendar" role="tabpanel">
            <div class="company-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="fw-bold text-dark mb-1"><i class="bi bi-calendar-week text-brand me-2"></i>Scheduled Interviews & User Calendar Events</h5>
                        <p class="text-muted small mb-0">Directly syncs scheduled interview dates (`activityDate`) and titles into candidate user calendars.</p>
                    </div>
                    <button class="btn btn-company-primary btn-sm" onclick="openScheduleModal('301', 'Select Candidate...', 'General Candidate')">
                        <i class="bi bi-plus-circle me-1"></i> Add Interview Event
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-company align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Candidate Name</th>
                                <th>Interview Activity / Title</th>
                                <th>Date & Time</th>
                                <th>User Calendar Status</th>
                            </tr>
                        </thead>
                        <tbody id="scheduledInterviewsBody">
                            <?php if (!empty($scheduledInterviews)): ?>
                                <?php foreach ($scheduledInterviews as $item): ?>
                                    <?php 
                                        $cName = trim(($item['firstName'] ?? '') . ' ' . ($item['lastName'] ?? '')) ?: ($item['username'] ?? 'Candidate');
                                        $actTitle = $item['activityName'] ?? 'Interview Session';
                                        $actDate = $item['activityDate'] ?? 'Date not set';
                                        $calStatus = $item['activityStatus'] ?? 'Scheduled';
                                        $appStatus = $item['appStatus'] ?? 'Scheduled';
                                        $isAccepted = in_array($calStatus, ['Accepted', 'Candidate Accepted']) || $appStatus === 'Candidate Accepted';
                                    ?>
                                    <tr>
                                        <td class="fw-bold text-dark"><?= htmlspecialchars($cName) ?></td>
                                        <td><?= htmlspecialchars($actTitle) ?></td>
                                        <td><span class="badge bg-dark text-accent"><?= htmlspecialchars($actDate) ?></span></td>
                                        <td>
                                            <?php if ($isAccepted): ?>
                                                <span class="badge badge-status badge-accepted"><i class="bi bi-check-circle-fill me-1"></i> Candidate Accepted</span>
                                            <?php else: ?>
                                                <span class="badge badge-status badge-scheduled"><i class="bi bi-clock-history me-1"></i> Synced (Awaiting Candidate Acceptance)</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No interviews scheduled yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ================= MODAL 1: SCHEDULE INTERVIEW MODAL ================= -->
<div class="modal fade company-modal" id="scheduleInterviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-event me-2"></i>Schedule Candidate Interview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="scheduleInterviewForm">
                <div class="modal-body">
                    <input type="hidden" id="modalApplicantId">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Candidate Name</label>
                        <input type="text" class="form-control company-input bg-light" id="modalCandidateName" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Applied Vacancy</label>
                        <input type="text" class="form-control company-input bg-light" id="modalPosition" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="interviewTitle" class="form-label fw-bold">Interview Title / Activity Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control company-input" id="interviewTitle" value="Technical Round 1 Interview" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="interviewDate" class="form-label fw-bold">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control company-input" id="interviewDate" required>
                        </div>
                        <div class="col-6">
                            <label for="interviewTime" class="form-label fw-bold">Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control company-input" id="interviewTime" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-company-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-company-primary btn-sm"><i class="bi bi-calendar-plus me-1"></i> Save & Sync to Calendar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= MODAL 2: VIEW CV PREVIEW MODAL ================= -->
<div class="modal fade company-modal" id="viewCVModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-file-earmark-person me-2"></i>Candidate Resume CV Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" style="background-color: #f8faf9;">
                <div class="company-card mb-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h4 class="fw-bold text-dark mb-1" id="cvModalName">Candidate Name</h4>
                            <p class="text-brand fw-semibold mb-2" id="cvModalPosition">Position</p>
                        </div>
                        <span class="badge badge-status badge-accepted"><i class="bi bi-shield-check me-1"></i> Verified CV</span>
                    </div>
                    <hr>
                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-tools text-brand me-1"></i>Skills Summary:</h6>
                    <p class="text-dark bg-white p-3 rounded-8px border" id="cvModalSkills">Skills list...</p>

                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-file-text text-brand me-1"></i>Resume Document:</h6>
                    <div class="p-3 bg-white rounded-8px border" id="cvModalDocBox">
                        <iframe id="cvModalIframeFrame" src="" style="width: 100%; height: 400px; border: none; display: none;"></iframe>
                        <div class="text-center py-2">
                            <a id="cvModalDownloadBtn" href="#" class="btn btn-company-secondary btn-sm" download style="display: none;"><i class="bi bi-download me-1"></i> Download File</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-company-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ================= CONFIRM APPLICANT ACTION MODAL ================= -->
<div class="modal fade company-modal" id="confirmApplicantActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-12px border-0 shadow-lg">
            <div class="modal-header bg-dark text-white rounded-top-12px">
                <h5 class="modal-title fw-bold"><i class="bi bi-question-circle text-accent me-2"></i>Confirm Action</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light text-center">
                <div class="mb-3">
                    <i class="bi bi-exclamation-circle-fill text-warning display-4"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2" id="confirmApplicantActionTitle">Are you sure?</h5>
                <p class="text-muted small mb-0" id="confirmApplicantActionMessage">Are you sure you want to perform this action?</p>
            </div>
            <div class="modal-footer bg-white rounded-bottom-12px justify-content-center gap-2">
                <button type="button" class="btn btn-secondary rounded-4px btn-sm px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-brand rounded-4px btn-sm font-weight-bold px-4" id="confirmApplicantActionProceedBtn"><i class="bi bi-check-circle me-1"></i> Yes, Proceed</button>
            </div>
        </div>
    </div>
</div>

<!-- ================= CONFIRM COMPANY PROFILE SAVE MODAL ================= -->
<div class="modal fade company-modal" id="confirmCompanyProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-12px border-0 shadow-lg">
            <div class="modal-header bg-dark text-white rounded-top-12px">
                <h5 class="modal-title fw-bold"><i class="bi bi-question-circle text-accent me-2"></i>Confirm Profile Update</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light text-center">
                <div class="mb-3">
                    <i class="bi bi-exclamation-circle-fill text-warning display-4"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Are you sure?</h5>
                <p class="text-muted small mb-0">Are you sure you want to save your company profile changes?</p>
            </div>
            <div class="modal-footer bg-white rounded-bottom-12px justify-content-center gap-2">
                <button type="button" class="btn btn-secondary rounded-4px btn-sm px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-brand rounded-4px btn-sm font-weight-bold px-4" id="confirmCompanyProfileProceedBtn"><i class="bi bi-check-circle me-1"></i> Yes, Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL: COMPANY ACTIVITY HISTORY LOG ================= -->
<div class="modal fade company-modal" id="companyActivityHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-12px border-0 shadow-lg">
            <div class="modal-header bg-dark text-white rounded-top-12px">
                <h5 class="modal-title fw-bold"><i class="bi bi-clock-history text-accent me-2"></i>Company Activity History Log</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <?php if (!empty($companyActivityList)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle small mb-0 bg-white rounded-8px shadow-sm">
                            <thead class="table-dark">
                                <tr>
                                    <th>Activity Description</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($companyActivityList as $act): ?>
                                    <tr>
                                        <td class="fw-semibold text-dark"><i class="bi bi-check2-circle text-success me-2"></i><?= htmlspecialchars($act['activityHistory'] ?? '') ?></td>
                                        <td><?= !empty($act['activityDate']) ? date('M d, Y', strtotime($act['activityDate'])) : 'N/A' ?></td>
                                        <td class="text-muted"><?= htmlspecialchars($act['activityTime'] ?? 'N/A') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0 text-center py-4">No company activity history recorded yet.</p>
                <?php endif; ?>
            </div>
            <div class="modal-footer bg-white rounded-bottom-12px">
                <button type="button" class="btn btn-secondary btn-sm rounded-4px" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/toast.js"></script>
<script src="../assets/js/validation.js"></script>
<!-- Company Dashboard JS -->
<script src="js/company.js"></script>
</body>
</html>
