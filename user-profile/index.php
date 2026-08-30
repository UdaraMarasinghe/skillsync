<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/activity_logger.php';
require_once __DIR__ . '/../includes/industries.php';

// Prevent browser caching on user profile pages
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Session verification: Redirect to login if user is not logged in
if (empty($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'user') {
    header("Location: ../login/");
    exit;
}

$currentUserId = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// 1. Handle Profile Section Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_personal']) || isset($_POST['update_profile'])) {
        $firstName = trim($_POST['firstName'] ?? '');
        $lastName = trim($_POST['lastName'] ?? '');
        $mobileNo = trim($_POST['mobileNo'] ?? '');
        $profTitle = trim($_POST['profTitle'] ?? '');
        $industry = trim($_POST['industry'] ?? '');
        $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
        $gender = trim($_POST['gender'] ?? '');
        $jobStatus = trim($_POST['jobStatus'] ?? '');
        $jobType = trim($_POST['jobType'] ?? '');

        // Fetch existing user data for fallback
        $stmtCur = $pdo->prepare("SELECT profilePhoto FROM user WHERE userid = ?");
        $stmtCur->execute([$currentUserId]);
        $curData = $stmtCur->fetch();
        $profilePhoto = trim($_POST['profilePhoto'] ?? '');

        // Handle Profile Photo File Upload
        if (isset($_FILES['profilePhotoFile']) && $_FILES['profilePhotoFile']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['profilePhotoFile']['tmp_name'];
            $fileName = $_FILES['profilePhotoFile']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($fileExtension, $allowedExtensions)) {
                $uploadDir = __DIR__ . '/../assets/img/profiles/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $newFileName = 'user_' . $currentUserId . '_' . time() . '.' . $fileExtension;
                $destPath = $uploadDir . $newFileName;
                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $profilePhoto = '../assets/img/profiles/' . $newFileName;
                }
            }
        }
        if (empty($profilePhoto)) {
            $profilePhoto = $curData['profilePhoto'] ?? '';
        }

        if (empty($firstName) || empty($lastName)) {
            $error_message = "First name and Last name are required.";
        } else {
            $cleanMobile = preg_replace('/[^0-9]/', '', $mobileNo);
            $stmtUpUser = $pdo->prepare("
                UPDATE user 
                SET firstName = ?, lastName = ?, mobileNo = ?, profTitle = ?, industry = ?, dob = ?, gender = ?, jobStatus = ?, jobType = ?, profilePhoto = ?
                WHERE userid = ?
            ");
            $stmtUpUser->execute([$firstName, $lastName, $cleanMobile, $profTitle, $industry, $dob, $gender, $jobStatus, $jobType, $profilePhoto, $currentUserId]);
            $_SESSION['name'] = $firstName . ' ' . $lastName;
            logActivity($pdo, $currentUserId, null, "Updated personal profile details");
            $success_message = "Personal details updated successfully!";
        }
    }

    if (isset($_POST['update_location'])) {
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $country = trim($_POST['country'] ?? '');
        
        $stmtLocCheck = $pdo->prepare("SELECT userid FROM userLocation WHERE userid = ?");
        $stmtLocCheck->execute([$currentUserId]);
        if ($stmtLocCheck->fetch()) {
            $stmtUpLoc = $pdo->prepare("UPDATE userLocation SET address = ?, city = ?, district = ?, province = ?, country = ? WHERE userid = ?");
            $stmtUpLoc->execute([$address, $city, $district, $province, $country, $currentUserId]);
        } else {
            $stmtInsLoc = $pdo->prepare("INSERT INTO userLocation (userid, address, city, district, province, country) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtInsLoc->execute([$currentUserId, $address, $city, $district, $province, $country]);
        }
        logActivity($pdo, $currentUserId, null, "Updated location details");
        $success_message = "Location details updated successfully!";
    }

    if (isset($_POST['update_summary_skills'])) {
        $skills = trim($_POST['skills'] ?? '');
        $userDescription = trim($_POST['userDescription'] ?? '');

        $stmtUpSum = $pdo->prepare("UPDATE user SET skills = ?, userDescription = ? WHERE userid = ?");
        $stmtUpSum->execute([$skills, $userDescription, $currentUserId]);
        logActivity($pdo, $currentUserId, null, "Updated professional summary & skills");
        $success_message = "Professional summary & skills updated successfully!";
    }
}

// 2. Handle Add Education POST Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_education'])) {
    $institution = trim($_POST['institution'] ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    $startDate = !empty($_POST['startDate']) ? $_POST['startDate'] : null;
    $endDate = !empty($_POST['endDate']) ? $_POST['endDate'] : null;
    $grade = trim($_POST['grade'] ?? '');
    
    if (!empty($institution) && !empty($qualification)) {
        $stmtInsEdu = $pdo->prepare("INSERT INTO education (userid, institution, qualification, startDate, endDate, grade) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtInsEdu->execute([$currentUserId, $institution, $qualification, $startDate, $endDate, $grade]);
        logActivity($pdo, $currentUserId, null, "Added education qualification: {$qualification} at {$institution}");
        $success_message = "Education record added successfully!";
    } else {
        $error_message = "Institution and Qualification fields are required.";
    }
}

// 3. Handle PDF Resume/CV Upload POST Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_resume'])) {
    if (isset($_FILES['resumeFile']) && $_FILES['resumeFile']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['resumeFile']['tmp_name'];
        $fileName = $_FILES['resumeFile']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'doc', 'docx'];

        if (in_array($fileExtension, $allowedExtensions)) {
            $uploadDir = __DIR__ . '/../uploads/resumes/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $cleanName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
            $newFileName = 'resume_' . $currentUserId . '_' . time() . '_' . $cleanName . '.' . $fileExtension;
            $destPath = $uploadDir . $newFileName;
            $relPath = 'uploads/resumes/' . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $stmtInsRes = $pdo->prepare("INSERT INTO resume (userid, resumes) VALUES (?, ?)");
                $stmtInsRes->execute([$currentUserId, $relPath]);
                logActivity($pdo, $currentUserId, null, "Uploaded new PDF resume: " . $fileName);
                $success_message = "PDF Resume uploaded successfully!";
            } else {
                $error_message = "Failed to save uploaded resume file.";
            }
        } else {
            $error_message = "Invalid file type. Please upload a PDF or Word document (.pdf, .doc, .docx).";
        }
    } else {
        $error_message = "Please select a valid PDF file to upload.";
    }
}

// Fetch user data from database
$stmt = $pdo->prepare("SELECT * FROM user WHERE userid = ?");
$stmt->execute([$currentUserId]);
$userData = $stmt->fetch();

if (!$userData) {
    header("Location: ../login/");
    exit;
}

// Fetch user location
$stmtLoc = $pdo->prepare("SELECT * FROM userLocation WHERE userid = ?");
$stmtLoc->execute([$currentUserId]);
$userLocation = $stmtLoc->fetch() ?: [];

// Fetch user education
$stmtEdu = $pdo->prepare("SELECT * FROM education WHERE userid = ? ORDER BY startDate DESC");
$stmtEdu->execute([$currentUserId]);
$educationList = $stmtEdu->fetchAll();

// Fetch user resumes
$stmtRes = $pdo->prepare("SELECT * FROM resume WHERE userid = ?");
$stmtRes->execute([$currentUserId]);
$resumeList = $stmtRes->fetchAll();

// Fetch applied jobs with vacancy and company details
$stmtJobs = $pdo->prepare("
    SELECT aj.*, v.jobTitle, c.companyName 
    FROM appliedJobs aj 
    JOIN vacancy v ON aj.vacancyid = v.vacancyid 
    JOIN company c ON v.companyid = c.companyid 
    WHERE aj.userid = ? 
    ORDER BY aj.appliedDate DESC
");
$stmtJobs->execute([$currentUserId]);
$appliedJobsList = $stmtJobs->fetchAll();

// Fetch user activity history
$stmtAct = $pdo->prepare("SELECT * FROM activityHistory WHERE userid = ? ORDER BY activityDate DESC, activityTime DESC");
$stmtAct->execute([$currentUserId]);
$activityList = $stmtAct->fetchAll();

// Fetch candidate interview performance reports
$stmtRep = $pdo->prepare("SELECT * FROM interviewreport WHERE userid = ? ORDER BY sessionDate DESC");
$stmtRep->execute([$currentUserId]);
$interviewReports = $stmtRep->fetchAll();

include_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid header-container-padding py-5" style="background-color: #f8faf9; min-height: 80vh;">
    
    <script src="../assets/js/toast.js"></script>
    <?php if (!empty($success_message)): ?>
        <script>document.addEventListener('DOMContentLoaded', () => showToast(<?= json_encode($success_message) ?>, 'success'));</script>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <script>document.addEventListener('DOMContentLoaded', () => showToast(<?= json_encode($error_message) ?>, 'danger'));</script>
    <?php endif; ?>

    <!-- Profile Header Banner -->
    <div class="card-custom p-4 mb-4" style="background: linear-gradient(135deg, var(--brand-dark) 0%, #003633 100%); color: white;">
        <div class="row align-items-center g-4">
            <div class="col-auto">
                <?php 
                $userPhotoSrc = !empty($userData['profilePhoto']) ? $userData['profilePhoto'] : '../assets/img/demo-profile.jpg';
                if (strpos($userPhotoSrc, 'http') !== 0 && strpos($userPhotoSrc, '../') !== 0) {
                    $userPhotoSrc = '../' . $userPhotoSrc;
                }
                ?>
                <img src="<?= htmlspecialchars($userPhotoSrc) ?>" alt="Profile Photo" class="rounded-12px border border-3 border-accent" style="width: 110px; height: 110px; object-fit: cover;">
            </div>
            <div class="col">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h2 class="fw-bold mb-0 text-white"><?= htmlspecialchars(($userData['firstName'] ?? '') . ' ' . ($userData['lastName'] ?? '')) ?></h2>
                    <span class="badge bg-success text-white rounded-4px px-3 py-2 fw-semibold"><i class="bi bi-check-circle-fill me-1"></i><?= htmlspecialchars($userData['accStatus'] ?? 'Active') ?></span>
                </div>
                <p class="text-accent mb-2 font-weight-bold"><i class="bi bi-briefcase me-1"></i> <?= htmlspecialchars(!empty($userData['profTitle']) ? $userData['profTitle'] : 'Job Seeker Candidate') ?></p>
                <div class="d-flex flex-wrap gap-3 text-white-50 small">
                    <span>
                        <i class="bi bi-geo-alt text-accent me-1"></i> 
                        <?php 
                        $locArray = array_filter([$userLocation['city'] ?? '', $userLocation['province'] ?? '', $userLocation['country'] ?? '']);
                        echo htmlspecialchars(!empty($locArray) ? implode(', ', $locArray) : 'Location not specified');
                        ?>
                    </span>
                    <span><i class="bi bi-envelope text-accent me-1"></i> <?= htmlspecialchars($userData['email'] ?? '') ?></span>
                    <span><i class="bi bi-telephone text-accent me-1"></i> <?= htmlspecialchars(!empty($userData['mobileNo']) ? $userData['mobileNo'] : 'Not provided') ?></span>
                </div>
            </div>
            <div class="col-md-auto d-flex gap-2">
                <button class="btn btn-outline-light rounded-8px" data-bs-toggle="modal" data-bs-target="#activityHistoryModal">
                    <i class="bi bi-clock-history me-1"></i> Activity History
                </button>
                <a href="../includes/auth.php" class="btn btn-outline-light rounded-8px">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <!-- Main Profile Content -->
    <div class="row g-4">
        <!-- Left Column: Personal Info, Location & Resumes -->
        <div class="col-lg-4">
            <!-- Personal Details Card -->
            <div class="card-custom p-4 mb-4 position-relative">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-lines-fill text-brand me-2"></i>Personal Details</h5>
                    <button class="btn btn-sm btn-outline-brand rounded-4px" type="button" data-bs-toggle="collapse" data-bs-target="#editPersonalDetailsCollapse" aria-expanded="false" aria-controls="editPersonalDetailsCollapse">
                        <i class="bi bi-pencil-square me-1"></i> Edit
                    </button>
                </div>
                
                <ul class="list-unstyled mb-0 small">
                    <li class="mb-2"><strong class="text-dark">Username:</strong> <span class="text-muted"><?= htmlspecialchars($userData['username'] ?? '') ?></span></li>
                    <li class="mb-2"><strong class="text-dark">Industry Sector:</strong> <span class="badge bg-light text-dark border"><?= htmlspecialchars(!empty($userData['industry']) ? $userData['industry'] : 'Not specified') ?></span></li>
                    <li class="mb-2"><strong class="text-dark">Date of Birth:</strong> <span class="text-muted"><?= !empty($userData['dob']) ? date('F j, Y', strtotime($userData['dob'])) : 'Not provided' ?></span></li>
                    <li class="mb-2"><strong class="text-dark">Gender:</strong> <span class="text-muted"><?= htmlspecialchars(!empty($userData['gender']) ? $userData['gender'] : 'Not specified') ?></span></li>
                    <li class="mb-2"><strong class="text-dark">Job Status:</strong> <span class="badge bg-success rounded-4px"><?= htmlspecialchars(!empty($userData['jobStatus']) ? $userData['jobStatus'] : 'Actively Seeking') ?></span></li>
                    <li class="mb-2"><strong class="text-dark">Preferred Job Type:</strong> <span class="text-muted"><?= htmlspecialchars(!empty($userData['jobType']) ? $userData['jobType'] : 'Full-Time') ?></span></li>
                    <li class="mb-0"><strong class="text-dark">Last Login:</strong> <span class="text-muted"><?= !empty($userData['lastLoginTime']) ? date('M j, Y \a\t h:i A', strtotime($userData['lastLoginTime'])) : 'Recently' ?></span></li>
                </ul>

                <!-- Inline Edit Form Collapse -->
                <div class="collapse mt-3 pt-3 border-top" id="editPersonalDetailsCollapse">
                    <form method="POST" action="./" enctype="multipart/form-data">
                        <input type="hidden" name="update_personal" value="1">
                        <div class="mb-2">
                            <label class="form-label fw-bold small text-muted">First Name *</label>
                            <input type="text" name="firstName" class="form-control form-control-sm" value="<?= htmlspecialchars($userData['firstName'] ?? '') ?>" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold small text-muted">Last Name *</label>
                            <input type="text" name="lastName" class="form-control form-control-sm" value="<?= htmlspecialchars($userData['lastName'] ?? '') ?>" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold small text-muted">Mobile Contact</label>
                            <input type="tel" name="mobileNo" id="profileMobileNo" class="form-control form-control-sm" data-numeric="true" maxlength="15" placeholder="e.g. 0771234567" value="<?= htmlspecialchars($userData['mobileNo'] ?? '') ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold small text-muted">Professional Title</label>
                            <input type="text" name="profTitle" class="form-control form-control-sm" value="<?= htmlspecialchars($userData['profTitle'] ?? '') ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold small text-muted">Industry Sector</label>
                            <select name="industry" class="form-select form-select-sm">
                                <?= renderIndustryOptions($userData['industry'] ?? '', '-- Select Your Industry Sector --') ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold small text-muted">Profile Photo</label>
                            <input type="file" name="profilePhotoFile" class="form-control form-control-sm" accept="image/*">
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold small text-muted">Date of Birth</label>
                            <input type="date" name="dob" class="form-control form-control-sm" value="<?= htmlspecialchars($userData['dob'] ?? '') ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold small text-muted">Gender</label>
                            <select name="gender" class="form-select form-select-sm">
                                <option value="Male" <?= ($userData['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= ($userData['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                <option value="Other" <?= ($userData['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold small text-muted">Job Status</label>
                            <select name="jobStatus" class="form-select form-select-sm">
                                <option value="Actively Seeking" <?= ($userData['jobStatus'] ?? '') === 'Actively Seeking' ? 'selected' : '' ?>>Actively Seeking</option>
                                <option value="Open to Offers" <?= ($userData['jobStatus'] ?? '') === 'Open to Offers' ? 'selected' : '' ?>>Open to Offers</option>
                                <option value="Employed" <?= ($userData['jobStatus'] ?? '') === 'Employed' ? 'selected' : '' ?>>Employed</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Preferred Job Type</label>
                            <select name="jobType" class="form-select form-select-sm">
                                <option value="Full-Time" <?= ($userData['jobType'] ?? '') === 'Full-Time' ? 'selected' : '' ?>>Full-Time</option>
                                <option value="Part-Time" <?= ($userData['jobType'] ?? '') === 'Part-Time' ? 'selected' : '' ?>>Part-Time</option>
                                <option value="Remote" <?= ($userData['jobType'] ?? '') === 'Remote' ? 'selected' : '' ?>>Remote</option>
                                <option value="Contract / Freelance" <?= ($userData['jobType'] ?? '') === 'Contract / Freelance' ? 'selected' : '' ?>>Contract / Freelance</option>
                                <option value="Internship / Trainee" <?= ($userData['jobType'] ?? '') === 'Internship / Trainee' ? 'selected' : '' ?>>Internship / Trainee</option>
                                <option value="Hybrid" <?= ($userData['jobType'] ?? '') === 'Hybrid' ? 'selected' : '' ?>>Hybrid</option>
                            </select>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-sm btn-secondary rounded-4px" data-bs-toggle="collapse" data-bs-target="#editPersonalDetailsCollapse">Cancel</button>
                            <button type="button" class="btn btn-sm btn-accent rounded-4px font-weight-bold" onclick="confirmFormSubmit(this.form)">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Location Card -->
            <div class="card-custom p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-pin-map text-brand me-2"></i>User Location</h5>
                    <button class="btn btn-sm btn-outline-brand rounded-4px" type="button" data-bs-toggle="collapse" data-bs-target="#editLocationCollapse" aria-expanded="false" aria-controls="editLocationCollapse">
                        <i class="bi bi-pencil-square me-1"></i> Edit
                    </button>
                </div>

                <ul class="list-unstyled mb-0 small">
                    <li class="mb-2"><strong class="text-dark">Address:</strong> <span class="text-muted"><?= htmlspecialchars(!empty($userLocation['address']) ? $userLocation['address'] : 'Not provided') ?></span></li>
                    <li class="mb-2"><strong class="text-dark">City:</strong> <span class="text-muted"><?= htmlspecialchars(!empty($userLocation['city']) ? $userLocation['city'] : 'Not provided') ?></span></li>
                    <li class="mb-2"><strong class="text-dark">District:</strong> <span class="text-muted"><?= htmlspecialchars(!empty($userLocation['district']) ? $userLocation['district'] : 'Not provided') ?></span></li>
                    <li class="mb-2"><strong class="text-dark">Province:</strong> <span class="text-muted"><?= htmlspecialchars(!empty($userLocation['province']) ? $userLocation['province'] : 'Not provided') ?></span></li>
                    <li class="mb-0"><strong class="text-dark">Country:</strong> <span class="text-muted"><?= htmlspecialchars(!empty($userLocation['country']) ? $userLocation['country'] : 'Not provided') ?></span></li>
                </ul>

                <!-- Inline Edit Location Form Collapse -->
                <div class="collapse mt-3 pt-3 border-top" id="editLocationCollapse">
                    <form method="POST" action="./">
                        <input type="hidden" name="update_location" value="1">
                        <div class="mb-2">
                            <label class="form-label fw-bold small text-muted">Street Address</label>
                            <input type="text" name="address" class="form-control form-control-sm" value="<?= htmlspecialchars($userLocation['address'] ?? '') ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold small text-muted">City</label>
                            <input type="text" name="city" class="form-control form-control-sm" value="<?= htmlspecialchars($userLocation['city'] ?? '') ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold small text-muted">District</label>
                            <input type="text" name="district" class="form-control form-control-sm" value="<?= htmlspecialchars($userLocation['district'] ?? '') ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold small text-muted">Province</label>
                            <input type="text" name="province" class="form-control form-control-sm" value="<?= htmlspecialchars($userLocation['province'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Country</label>
                            <input type="text" name="country" class="form-control form-control-sm" value="<?= htmlspecialchars($userLocation['country'] ?? '') ?>">
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-sm btn-secondary rounded-4px" data-bs-toggle="collapse" data-bs-target="#editLocationCollapse">Cancel</button>
                            <button type="button" class="btn btn-sm btn-accent rounded-4px font-weight-bold" onclick="confirmFormSubmit(this.form)">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Resumes Card -->
            <div class="card-custom p-4">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-file-earmark-pdf text-brand me-2"></i>Uploaded Resumes</h5>
                    <button class="btn btn-sm btn-outline-brand rounded-4px" type="button" data-bs-toggle="collapse" data-bs-target="#uploadResumeCollapse" aria-expanded="false">
                        <i class="bi bi-cloud-upload me-1"></i> Upload PDF
                    </button>
                </div>

                <!-- Inline Upload PDF Resume Form Collapse -->
                <div class="collapse mb-3 pb-3 border-bottom" id="uploadResumeCollapse">
                    <form method="POST" action="./" enctype="multipart/form-data">
                        <input type="hidden" name="upload_resume" value="1">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-dark"><i class="bi bi-file-earmark-arrow-up text-brand me-1"></i> Select Existing CV / Resume File (PDF)</label>
                            <input type="file" name="resumeFile" class="form-control form-control-sm" accept=".pdf,.doc,.docx" required>
                            <div class="form-text small">Accepted formats: .pdf, .doc, .docx (Max 10MB)</div>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-sm btn-secondary rounded-4px" data-bs-toggle="collapse" data-bs-target="#uploadResumeCollapse">Cancel</button>
                            <button type="button" class="btn btn-sm btn-accent rounded-4px font-weight-bold" onclick="confirmFormSubmit(this.form)">
                                <i class="bi bi-upload me-1"></i> Upload Resume
                            </button>
                        </div>
                    </form>
                </div>

                <?php if (!empty($resumeList)): ?>
                    <?php foreach ($resumeList as $res): ?>
                        <?php 
                            $resPath = $res['resumes'];
                            $resUrl = (strpos($resPath, 'http') === 0 || strpos($resPath, '/') === 0) ? $resPath : $base_url . $resPath;
                            $rawFileName = basename($resPath);
                            $cleanTitle = preg_replace('/^resume_\d+_\d+_/', '', $rawFileName);
                            $cleanTitle = pathinfo($cleanTitle, PATHINFO_FILENAME);
                            $cleanTitle = str_replace(['_', '-'], ' ', $cleanTitle);
                            $displayName = !empty(trim($cleanTitle)) ? ucwords(trim($cleanTitle)) : $rawFileName;
                        ?>
                        <div class="d-flex align-items-center justify-content-between p-2 bg-light rounded-4px border mb-2">
                            <div class="d-flex align-items-center gap-2 overflow-hidden">
                                <i class="bi bi-file-pdftype text-danger fs-4"></i>
                                <div class="d-flex flex-column overflow-hidden">
                                    <span class="small fw-bold text-dark text-truncate" title="<?= htmlspecialchars($displayName) ?>"><?= htmlspecialchars($displayName) ?></span>
                                </div>
                            </div>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-brand rounded-4px py-0" onclick="previewResume('<?= htmlspecialchars($resUrl, ENT_QUOTES) ?>', '<?= htmlspecialchars($displayName, ENT_QUOTES) ?>')" title="Preview Resume">
                                    <i class="bi bi-eye me-1"></i>Preview
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted small mb-0">No uploaded resumes found. Click <strong>Upload PDF</strong> above to add your CV.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column: Professional Summary, Skills, Education & Applied Jobs -->
        <div class="col-lg-8">
            <!-- Summary & Skills Card -->
            <div class="card-custom p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-card-text text-brand me-2"></i>Professional Summary & Skills</h5>
                    <button class="btn btn-sm btn-outline-brand rounded-4px" type="button" data-bs-toggle="collapse" data-bs-target="#editSummarySkillsCollapse" aria-expanded="false" aria-controls="editSummarySkillsCollapse">
                        <i class="bi bi-pencil-square me-1"></i> Edit
                    </button>
                </div>

                <p class="text-muted small leading-relaxed mb-4">
                    <?= !empty($userData['userDescription']) ? nl2br(htmlspecialchars($userData['userDescription'])) : 'No professional summary provided yet.' ?>
                </p>

                <h6 class="fw-bold text-dark mb-2">Technical Skills & Expertise</h6>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <?php 
                    if (!empty($userData['skills'])) {
                        $skillsArray = array_map('trim', explode(',', $userData['skills']));
                        foreach ($skillsArray as $skill) {
                            if (!empty($skill)) {
                                echo '<span class="badge bg-dark text-accent p-2 rounded-4px">' . htmlspecialchars($skill) . '</span> ';
                            }
                        }
                    } else {
                        echo '<span class="text-muted small">No technical skills listed yet.</span>';
                    }
                    ?>
                </div>

                <!-- Inline Edit Summary & Skills Form Collapse -->
                <div class="collapse mt-3 pt-3 border-top" id="editSummarySkillsCollapse">
                    <form method="POST" action="./">
                        <input type="hidden" name="update_summary_skills" value="1">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Professional Summary</label>
                            <textarea name="userDescription" class="form-control form-control-sm" rows="4"><?= htmlspecialchars($userData['userDescription'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Technical Skills (comma separated)</label>
                            <input type="text" name="skills" class="form-control form-control-sm" value="<?= htmlspecialchars($userData['skills'] ?? '') ?>" placeholder="PHP, React, MySQL">
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-sm btn-secondary rounded-4px" data-bs-toggle="collapse" data-bs-target="#editSummarySkillsCollapse">Cancel</button>
                            <button type="button" class="btn btn-sm btn-accent rounded-4px font-weight-bold" onclick="confirmFormSubmit(this.form)">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Education History Card -->
            <div class="card-custom p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-mortarboard text-brand me-2"></i>Education & Qualifications</h5>
                    <button class="btn btn-sm btn-outline-brand rounded-4px" type="button" data-bs-toggle="collapse" data-bs-target="#addEducationCollapse" aria-expanded="false" aria-controls="addEducationCollapse">
                        <i class="bi bi-pencil-square me-1"></i> Add / Edit
                    </button>
                </div>

                <!-- Inline Add Education Form Collapse -->
                <div class="collapse mb-4 p-3 bg-light rounded-8px border" id="addEducationCollapse">
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-plus-circle text-brand me-1"></i>Add New Education Record</h6>
                    <form method="POST" action="./">
                        <input type="hidden" name="add_education" value="1">
                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Institution *</label>
                                <input type="text" name="institution" class="form-control form-control-sm" placeholder="e.g. University of Westminster" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Qualification *</label>
                                <input type="text" name="qualification" class="form-control form-control-sm" placeholder="e.g. BSc (Hons) Computer Science" required>
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">Start Date</label>
                                <input type="date" name="startDate" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">End Date</label>
                                <input type="date" name="endDate" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">Grade / Result</label>
                                <input type="text" name="grade" class="form-control form-control-sm" placeholder="e.g. First Class">
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button type="button" class="btn btn-sm btn-secondary rounded-4px" data-bs-toggle="collapse" data-bs-target="#addEducationCollapse">Cancel</button>
                            <button type="button" class="btn btn-sm btn-accent rounded-4px font-weight-bold" onclick="confirmFormSubmit(this.form)">Save Education</button>
                        </div>
                    </form>
                </div>
                
                <?php if (!empty($educationList)): ?>
                    <?php foreach ($educationList as $idx => $edu): ?>
                        <div class="<?= $idx < count($educationList) - 1 ? 'mb-3' : '' ?> border-start border-3 border-brand ps-3">
                            <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($edu['qualification'] ?? '') ?></h6>
                            <p class="text-brand small fw-bold mb-1"><?= htmlspecialchars($edu['institution'] ?? '') ?></p>
                            <div class="d-flex justify-content-between text-muted small">
                                <span><i class="bi bi-calendar me-1"></i> <?= !empty($edu['startDate']) ? date('Y', strtotime($edu['startDate'])) : '' ?> - <?= !empty($edu['endDate']) ? date('Y', strtotime($edu['endDate'])) : 'Present' ?></span>
                                <?php if (!empty($edu['grade'])): ?>
                                    <span class="badge bg-light text-dark border rounded-4px"><?= htmlspecialchars($edu['grade']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted small mb-0">No education history recorded yet.</p>
                <?php endif; ?>
            </div>

            <!-- Applied Jobs Status Card -->
            <div class="card-custom p-4">
                <h5 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-briefcase-fill text-brand me-2"></i>Recent Job Applications</h5>
                <?php if (!empty($appliedJobsList)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle small mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Job Title</th>
                                    <th>Company</th>
                                    <th>Applied Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appliedJobsList as $job): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($job['jobTitle'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($job['companyName'] ?? '') ?></td>
                                        <td><?= !empty($job['appliedDate']) ? date('M d, Y', strtotime($job['appliedDate'])) : '' ?></td>
                                        <td><span class="badge bg-info text-dark rounded-4px"><?= htmlspecialchars($job['status'] ?? 'Pending') ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">No job applications submitted yet.</p>
                <?php endif; ?>
            </div>

            <!-- Intervia AI Interview Performance Reports Card -->
            <div class="card-custom p-4 mt-4">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-robot text-brand me-2"></i>Intervia AI Interview Performance Reports</h5>
                    <a href="../Intervia/" class="btn btn-sm btn-brand rounded-6px">
                        <i class="bi bi-play-circle me-1"></i> Start New AI Practice
                    </a>
                </div>

                <?php if (!empty($interviewReports)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle small mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Subject Field</th>
                                    <th>Tier</th>
                                    <th>Overall Score</th>
                                    <th>Tech / Camera</th>
                                    <th>Session Date</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($interviewReports as $rep): ?>
                                    <tr>
                                        <td class="fw-bold text-dark"><i class="bi bi-file-earmark-bar-graph text-brand me-1"></i><?= htmlspecialchars($rep['category'] ?? 'Software Engineering') ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars(ucfirst($rep['tier'] ?? 'Mid-Level')) ?></span></td>
                                        <td>
                                            <?php 
                                            $score = intval($rep['overallScore'] ?? 0);
                                            $badgeClass = $score >= 80 ? 'bg-success' : ($score >= 60 ? 'bg-warning text-dark' : 'bg-danger');
                                            ?>
                                            <span class="badge <?= $badgeClass ?> px-3 py-2 rounded-4px font-weight-bold fs-6"><?= $score ?>%</span>
                                        </td>
                                        <td class="text-muted"><?= intval($rep['techScore'] ?? 0) ?>% / <?= intval($rep['confidenceScore'] ?? 0) ?>%</td>
                                        <td class="text-muted"><?= !empty($rep['sessionDate']) ? date('M d, Y - h:i A', strtotime($rep['sessionDate'])) : 'N/A' ?></td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-outline-brand btn-sm rounded-6px" onclick='viewSavedProfileReport(<?= json_encode($rep, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                                <i class="bi bi-eye me-1"></i> View Report
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 bg-light rounded-8px border">
                        <i class="bi bi-robot text-muted display-5"></i>
                        <p class="text-muted small mt-2 mb-3">No AI interview reports recorded yet. Take an Intervia practice session to generate your first diagnostic report!</p>
                        <a href="../Intervia/" class="btn btn-outline-brand btn-sm rounded-8px"><i class="bi bi-rocket-takeoff me-1"></i> Launch Intervia AI Bot</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Activity History Card -->
            <div class="card-custom p-4 mt-4">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history text-brand me-2"></i>Recent Activity Log History</h5>
                    <button class="btn btn-sm btn-outline-brand rounded-4px" data-bs-toggle="modal" data-bs-target="#activityHistoryModal">
                        View Full History
                    </button>
                </div>
                <?php if (!empty($activityList)): ?>
                    <div class="timeline border-start border-2 border-brand ps-3">
                        <?php foreach (array_slice($activityList, 0, 3) as $idx => $act): ?>
                            <div class="<?= $idx < 2 ? 'mb-3' : '' ?> position-relative">
                                <div class="fw-bold text-dark mb-1"><i class="bi bi-clock-history text-primary me-1"></i> <?= htmlspecialchars(substr($act['activityHistory'] ?? '', 0, 50)) ?></div>
                                <p class="text-muted small mb-0"><?= htmlspecialchars($act['activityHistory'] ?? '') ?></p>
                                <small class="text-muted"><i class="bi bi-clock me-1"></i> <?= htmlspecialchars(($act['activityDate'] ?? '') . ' ' . ($act['activityTime'] ?? '')) ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">No activity history logged yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ================= ARE YOU SURE CONFIRMATION MODAL ================= -->
<div class="modal fade" id="confirmSaveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-12px border-0 shadow-lg">
            <div class="modal-header bg-dark text-white rounded-top-12px">
                <h5 class="modal-title fw-bold"><i class="bi bi-question-circle text-accent me-2"></i>Confirm Changes</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light text-center">
                <div class="mb-3">
                    <i class="bi bi-exclamation-circle-fill text-warning display-4"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Are you sure?</h5>
                <p class="text-muted small mb-0">Are you sure you want to save these changes to your profile?</p>
            </div>
            <div class="modal-footer bg-white rounded-bottom-12px justify-content-center gap-2">
                <button type="button" class="btn btn-secondary rounded-4px btn-sm px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-accent rounded-4px btn-sm font-weight-bold px-4" id="confirmSaveProceedBtn"><i class="bi bi-check-circle me-1"></i> Yes, Save</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript to handle form validation & confirmation modal submit -->
<script>
let targetFormToSubmit = null;

function confirmFormSubmit(form) {
    if (!form) return;
    if (typeof validateFormFields === 'function') {
        if (!validateFormFields(form)) {
            return;
        }
    } else if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    targetFormToSubmit = form;
    const confirmModal = new bootstrap.Modal(document.getElementById('confirmSaveModal'));
    confirmModal.show();
}

document.getElementById('confirmSaveProceedBtn').addEventListener('click', function() {
    if (targetFormToSubmit) {
        targetFormToSubmit.submit();
    }
});
</script>

<!-- ================= MODAL 3: ACTIVITY HISTORY MODAL ================= -->
<div class="modal fade" id="activityHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-12px border-0 shadow-lg">
            <div class="modal-header bg-dark text-white rounded-top-12px">
                <h5 class="modal-title fw-bold"><i class="bi bi-clock-history text-accent me-2"></i>Complete User Activity History Log</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <?php if (!empty($activityList)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle small mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Activity Event Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activityList as $act): ?>
                                    <tr>
                                        <td><?= htmlspecialchars(($act['activityDate'] ?? '') . ' ' . ($act['activityTime'] ?? '')) ?></td>
                                        <td class="fw-bold"><?= htmlspecialchars($act['activityHistory'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0 text-center py-3">No activity history recorded yet.</p>
                <?php endif; ?>
            </div>
            <div class="modal-footer bg-white rounded-bottom-12px">
                <button type="button" class="btn btn-secondary rounded-4px btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL 4: RESUME PREVIEW MODAL ================= -->
<div class="modal fade" id="previewResumeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content rounded-12px border-0 shadow-lg">
            <div class="modal-header bg-dark text-white rounded-top-12px">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-text text-accent me-2"></i><span id="previewResumeTitle">Resume Preview</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 bg-light">
                <iframe id="resumePreviewFrame" src="" style="width: 100%; height: 75vh; border: none; border-bottom-left-radius: 0; border-bottom-right-radius: 0;"></iframe>
            </div>
            <div class="modal-footer bg-white rounded-bottom-12px justify-content-end">
                <button type="button" class="btn btn-secondary rounded-4px btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL 5: VIEW SAVED INTERVIEW PERFORMANCE REPORT ================= -->
<div class="modal fade" id="viewInterviewReportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-12px border-0 shadow-lg">
            <div class="modal-header bg-dark text-white rounded-top-12px">
                <h5 class="modal-title fw-bold" id="savedReportTitle"><i class="bi bi-file-earmark-bar-graph me-2 text-accent"></i>Interview Performance Report</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Scores Row -->
                <div class="row g-3 text-center mb-4">
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-8px border">
                            <small class="text-muted fw-bold d-block mb-1">OVERALL SCORE</small>
                            <h2 class="fw-bold text-brand mb-0" id="savedReportOverallScore">0%</h2>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-8px border">
                            <small class="text-muted fw-bold d-block mb-1">TECHNICAL ACCURACY</small>
                            <h2 class="fw-bold text-dark mb-0" id="savedReportTechScore">0%</h2>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-8px border">
                            <small class="text-muted fw-bold d-block mb-1">CAMERA CONFIDENCE</small>
                            <h2 class="fw-bold text-dark mb-0" id="savedReportConfScore">0%</h2>
                        </div>
                    </div>
                </div>

                <!-- Weak Areas Section -->
                <div class="mb-4">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>Identified Weak Areas</h6>
                    <ul class="list-group list-group-flush rounded-8px border" id="savedReportWeakList"></ul>
                </div>

                <!-- Actionable Recommendations Section -->
                <div class="mb-4">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-lightbulb-fill me-2 text-warning"></i>Common Recommendations for Improvement</h6>
                    <ul class="list-group list-group-flush rounded-8px border" id="savedReportRecList"></ul>
                </div>

                <!-- Recommended Career Paths Section -->
                <div class="mb-2">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-compass-fill me-2 text-success"></i>Recommended Career Paths (Subject Area Matched)</h6>
                    <div class="row g-3" id="savedReportCareerGrid"></div>
                </div>
            </div>
            <div class="modal-footer bg-light rounded-bottom-12px justify-content-between">
                <small class="text-muted" id="savedReportMetaDate">Session Date: -</small>
                <button type="button" class="btn btn-secondary rounded-8px px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewSavedProfileReport(rep) {
    if (!rep) return;

    document.getElementById('savedReportTitle').innerHTML = '<i class="bi bi-file-earmark-bar-graph me-2 text-accent"></i>Report: ' + escapeHtmlProfile(rep.category || 'Software Engineering') + ' (' + escapeHtmlProfile(rep.tier || 'Mid-Level') + ')';
    document.getElementById('savedReportOverallScore').textContent = (rep.overallScore || 0) + '%';
    document.getElementById('savedReportTechScore').textContent = (rep.techScore || 0) + '%';
    document.getElementById('savedReportConfScore').textContent = (rep.confidenceScore || 0) + '%';
    document.getElementById('savedReportMetaDate').textContent = 'Session Date: ' + (rep.sessionDate || 'N/A');

    // Parse JSON or Array for weakAreas
    let weakArr = [];
    try {
        weakArr = typeof rep.weakAreas === 'string' ? JSON.parse(rep.weakAreas) : rep.weakAreas;
    } catch(e) { weakArr = [rep.weakAreas]; }
    const weakList = document.getElementById('savedReportWeakList');
    if (weakList) {
        weakList.innerHTML = '';
        (weakArr || []).forEach(item => {
            const li = document.createElement('li');
            li.className = 'list-group-item bg-light text-dark small py-2';
            li.innerHTML = '<i class="bi bi-x-circle-fill text-danger me-2"></i>' + escapeHtmlProfile(item);
            weakList.appendChild(li);
        });
    }

    // Recommendations
    let recArr = [];
    try {
        recArr = typeof rep.recommendations === 'string' ? JSON.parse(rep.recommendations) : rep.recommendations;
    } catch(e) { recArr = [rep.recommendations]; }
    const recList = document.getElementById('savedReportRecList');
    if (recList) {
        recList.innerHTML = '';
        (recArr || []).forEach(item => {
            const li = document.createElement('li');
            li.className = 'list-group-item bg-light text-dark small py-2';
            li.innerHTML = '<i class="bi bi-check-circle-fill text-success me-2"></i>' + escapeHtmlProfile(item);
            recList.appendChild(li);
        });
    }

    // Recommended Careers
    let cpArr = [];
    try {
        cpArr = typeof rep.recommendedCareers === 'string' ? JSON.parse(rep.recommendedCareers) : rep.recommendedCareers;
    } catch(e) { cpArr = []; }
    const grid = document.getElementById('savedReportCareerGrid');
    if (grid) {
        grid.innerHTML = '';
        (cpArr || []).forEach(cp => {
            const col = document.createElement('div');
            col.className = 'col-md-4';
            col.innerHTML = `
                <div class="p-3 bg-white rounded-8px border h-100 shadow-sm">
                    <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle mb-2">Match ${escapeHtmlProfile(cp.match || '90%')}</span>
                    <h6 class="fw-bold text-dark mb-1">${escapeHtmlProfile(cp.title)}</h6>
                    <p class="text-muted small mb-0">${escapeHtmlProfile(cp.desc)}</p>
                </div>
            `;
            grid.appendChild(col);
        });
    }

    const modal = new bootstrap.Modal(document.getElementById('viewInterviewReportModal'));
    modal.show();
}

function escapeHtmlProfile(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

<script>
function previewResume(fileUrl, fileName) {
    const previewModalEl = document.getElementById('previewResumeModal');
    const previewFrame = document.getElementById('resumePreviewFrame');
    const titleEl = document.getElementById('previewResumeTitle');

    if (titleEl) titleEl.textContent = fileName || 'Resume Preview';

    if (previewFrame) {
        previewFrame.onload = function() {
            try {
                const iframeDoc = previewFrame.contentDocument || previewFrame.contentWindow.document;
                if (iframeDoc && iframeDoc.head) {
                    const baseUrl = window.location.origin + '<?= $base_url ?>';
                    
                    if (!iframeDoc.querySelector('link[href*="styles.css"]')) {
                        const styleLink = iframeDoc.createElement('link');
                        styleLink.rel = 'stylesheet';
                        styleLink.href = baseUrl + 'profile-pro/css/styles.css';
                        iframeDoc.head.appendChild(styleLink);
                    }
                    
                    if (!iframeDoc.querySelector('link[href*="font-awesome"]')) {
                        const faLink = iframeDoc.createElement('link');
                        faLink.rel = 'stylesheet';
                        faLink.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
                        iframeDoc.head.appendChild(faLink);
                    }

                    if (!iframeDoc.querySelector('link[href*="bootstrap"]')) {
                        const bsLink = iframeDoc.createElement('link');
                        bsLink.rel = 'stylesheet';
                        bsLink.href = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css';
                        iframeDoc.head.appendChild(bsLink);
                    }
                }
            } catch (e) {
                console.warn('Could not inject styles into iframe:', e);
            }
        };
        previewFrame.src = fileUrl;
    }

    const modal = new bootstrap.Modal(previewModalEl);
    modal.show();
}
</script>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
