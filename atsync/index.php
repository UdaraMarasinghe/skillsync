<?php
require_once __DIR__ . '/../config/config.php';

// Handle Save Resume POST Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_resume') {
    header('Content-Type: application/json');
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        $stmtFirstUser = $pdo->query("SELECT userid FROM user ORDER BY userid ASC LIMIT 1");
        $userId = $stmtFirstUser->fetchColumn() ?: 1;
    }
    $resumeContent = $_POST['resume_content'] ?? '';
    $resumeTitle = trim($_POST['resume_title'] ?? 'Resume');

    if (empty($resumeContent)) {
        echo json_encode(['success' => false, 'message' => 'No resume content provided.']);
        exit;
    }

    $uploadDir = __DIR__ . '/../uploads/resumes/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $safeTitle = preg_replace('/[^a-zA-Z0-9_-]/', '_', $resumeTitle);
    $fileName = 'resume_' . $userId . '_' . time() . '_' . $safeTitle . '.html';
    $filePath = $uploadDir . $fileName;
    $dbRelativePath = 'uploads/resumes/' . $fileName;

    if (file_put_contents($filePath, $resumeContent) !== false) {
        try {
            $stmt = $pdo->prepare("INSERT INTO resume (userid, resumes) VALUES (?, ?)");
            $stmt->execute([$userId, $dbRelativePath]);
            $resumeId = $pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'message' => 'Resume saved successfully to database!',
                'resumeid' => $resumeId,
                'path' => $dbRelativePath
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save resume document file.']);
    }
    exit;
}

include_once __DIR__ . '/../includes/header.php';
?>
<!-- Font Awesome Icons & Custom CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/style.css">

<div class="container-fluid header-container-padding py-4" style="background-color: #f8faf9;">
    
    <!-- Page Title & Header -->
    <div class="mb-4 no-print">
        <h1 class="section-title mb-1"><i class="fas fa-id-card me-2" style="color: var(--brand-dark);"></i>ATSync CV Builder</h1>
    </div>

    <!-- Progress Wizard Header -->
    <div class="wizard-container no-print">
        <div class="wizard-steps">
            <div class="wizard-progress-bar" id="wizardProgressBar"></div>
            
            <div class="step-item active" data-step="1">
                <div class="step-number">1</div>
                <div class="step-label">Upload NIC</div>
            </div>
            
            <div class="step-item" data-step="2">
                <div class="step-number">2</div>
                <div class="step-label">CV Details</div>
            </div>

            <div class="step-item" data-step="3">
                <div class="step-number">3</div>
                <div class="step-label">ATS Preview & Export</div>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <main class="main-wrapper">
        
        <!-- ================= STEP 1: NIC PHOTO UPLOAD & OCR ================= -->
        <section class="step-card active" id="stepCard1">
            <h2 class="card-title"><i class="fas fa-camera"></i> Step 1: Upload National Identity Card (NIC)</h2>
            <p class="card-subtitle">Upload both front and back photos of your NIC. ATSync will automatically scan and extract your Full Name, NIC Number, Date of Birth, Gender, and Address.</p>
            
            <div class="upload-grid">
                <!-- Dropzone Front -->
                <div class="dropzone" id="dropzoneFront">
                    <i class="fas fa-id-card dropzone-icon"></i>
                    <h3>NIC Front Side</h3>
                    <p>Click or drag & drop Front photo of your NIC (JPG, PNG)</p>
                    <input type="file" id="fileNicFront" accept="image/*" style="display: none;">
                    <img id="previewFront" class="nic-preview-img" alt="NIC Front Preview">
                </div>

                <!-- Dropzone Back -->
                <div class="dropzone" id="dropzoneBack">
                    <i class="fas fa-id-card dropzone-icon" style="transform: scaleX(-1);"></i>
                    <h3>NIC Back Side</h3>
                    <p>Click or drag & drop Back photo of your NIC (JPG, PNG)</p>
                    <input type="file" id="fileNicBack" accept="image/*" style="display: none;">
                    <img id="previewBack" class="nic-preview-img" alt="NIC Back Preview">
                </div>
            </div>

            <!-- Extracted NIC Summary Card -->
            <div class="extracted-summary" id="extractedSummaryBox">
                <h4><i class="fas fa-check-circle"></i> Extracted NIC Data Preview</h4>
                <div class="extracted-grid">
                    <div class="extracted-item">
                        <label>Full Name</label>
                        <span id="sumName">-</span>
                    </div>
                    <div class="extracted-item">
                        <label>NIC Number</label>
                        <span id="sumNic">-</span>
                    </div>
                    <div class="extracted-item">
                        <label>Date of Birth</label>
                        <span id="sumDob">-</span>
                    </div>
                    <div class="extracted-item">
                        <label>Gender</label>
                        <span id="sumGender">-</span>
                    </div>
                    <div class="extracted-item" style="grid-column: 1 / -1;">
                        <label>Address</label>
                        <span id="sumAddress">-</span>
                    </div>
                </div>
            </div>

            <div class="scan-action-bar" style="margin-top: 20px;">
                <button type="button" class="btn btn-primary" id="btnScanNic">
                    <i class="fas fa-bolt"></i> Scan & Extract NIC Data
                </button>
            </div>
        </section>

        <!-- ================= STEP 2: CV DATA COLLECTION FORM ================= -->
        <section class="step-card" id="stepCard2">
            <h2 class="card-title"><i class="fas fa-file-alt"></i> Step 2: Complete ATS CV Details</h2>
            <p class="card-subtitle">Review your extracted NIC information and complete your professional resume details optimized for Applicant Tracking Systems.</p>

            <!-- Personal Details Section -->
            <div class="repeater-card">
                <h3 class="repeater-title mb-3">
                    <i class="fas fa-user text-brand"></i> Personal & Contact Information (Pre-populated from NIC)
                </h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="input_fullName">Full Name</label>
                        <input type="text" id="input_fullName" class="form-control" placeholder="Full Name from NIC">
                    </div>
                    <div class="form-group">
                        <label for="input_jobTitle">Target Job Title</label>
                        <input type="text" id="input_jobTitle" class="form-control" placeholder="e.g. Software Engineer">
                    </div>
                    <div class="form-group">
                        <label for="input_email">Email Address</label>
                        <input type="email" id="input_email" class="form-control" placeholder="e.g. candidate@example.com">
                    </div>
                    <div class="form-group">
                        <label for="input_phone">Phone Number</label>
                        <input type="text" id="input_phone" class="form-control" placeholder="e.g. +94 77 123 4567">
                    </div>
                    <div class="form-group">
                        <label for="input_nicNumber">NIC Number</label>
                        <input type="text" id="input_nicNumber" class="form-control" placeholder="NIC Number from ID">
                    </div>
                    <div class="form-group">
                        <label for="input_dob">Date of Birth</label>
                        <input type="date" id="input_dob" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="input_gender">Gender</label>
                        <input type="text" id="input_gender" class="form-control" placeholder="Male / Female">
                    </div>
                    <div class="form-group">
                        <label for="input_linkedin">LinkedIn Profile / Portfolio</label>
                        <input type="text" id="input_linkedin" class="form-control" placeholder="linkedin.com/in/username">
                    </div>
                    <div class="form-group full-width">
                        <label for="input_location">Address / Location</label>
                        <input type="text" id="input_location" class="form-control" placeholder="Address from NIC">
                    </div>
                </div>
            </div>

            <!-- Professional Summary -->
            <div class="repeater-card">
                <h3 class="repeater-title mb-3">
                    <i class="fas fa-align-left text-brand"></i> Professional Summary
                </h3>
                <div class="form-group full-width">
                    <label for="input_summary">Executive Summary (High-impact statement highlighting skills & experience)</label>
                    <textarea id="input_summary" class="form-control" style="min-height: 100px;" placeholder="Write a summary highlighting your experience, key skills, and accomplishments..."></textarea>
                </div>
            </div>

            <!-- Work Experience Section -->
            <div style="margin-bottom: 24px;">
                <h3 class="repeater-title mb-2">
                    <i class="fas fa-briefcase text-brand"></i> Work Experience
                </h3>
                <div id="experienceListContainer"></div>
                <button type="button" class="btn-add" id="btnAddExperience"><i class="fas fa-plus"></i> Add Work Experience</button>
            </div>

            <!-- Education Section -->
            <div style="margin-bottom: 24px;">
                <h3 class="repeater-title mb-2">
                    <i class="fas fa-graduation-cap text-brand"></i> Education
                </h3>
                <div id="educationListContainer"></div>
                <button type="button" class="btn-add" id="btnAddEducation"><i class="fas fa-plus"></i> Add Education</button>
            </div>

            <!-- Skills Section -->
            <div class="repeater-card">
                <h3 class="repeater-title mb-3">
                    <i class="fas fa-cogs text-brand"></i> Skills & Competencies
                </h3>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="input_techSkills">Technical Skills (Comma separated)</label>
                        <input type="text" id="input_techSkills" class="form-control" placeholder="e.g. PHP, MySQL, JavaScript, Python, AWS">
                    </div>
                    <div class="form-group full-width">
                        <label for="input_softSkills">Soft Skills & Leadership (Comma separated)</label>
                        <input type="text" id="input_softSkills" class="form-control" placeholder="e.g. Communication, Team Leadership, Problem Solving">
                    </div>
                </div>
            </div>

            <!-- Projects Section -->
            <div style="margin-bottom: 24px;">
                <h3 class="repeater-title mb-2">
                    <i class="fas fa-project-diagram text-brand"></i> Projects
                </h3>
                <div id="projectListContainer"></div>
                <button type="button" class="btn-add" id="btnAddProject"><i class="fas fa-plus"></i> Add Project</button>
            </div>

            <!-- Certifications & Languages -->
            <div class="repeater-card">
                <h3 class="repeater-title mb-3">
                    <i class="fas fa-certificate text-brand"></i> Certifications & Languages
                </h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="input_certifications">Certifications</label>
                        <input type="text" id="input_certifications" class="form-control" placeholder="e.g. AWS Certified Developer">
                    </div>
                    <div class="form-group">
                        <label for="input_languages">Languages</label>
                        <input type="text" id="input_languages" class="form-control" placeholder="e.g. English, Sinhala, Tamil">
                    </div>
                </div>
            </div>

            <div class="scan-action-bar">
                <button type="button" class="btn btn-secondary" onclick="goToStep(1)"><i class="fas fa-arrow-left"></i> Back to NIC Scan</button>
                <button type="button" class="btn btn-primary" onclick="goToStep(3)"><i class="fas fa-eye"></i> Preview ATS Resume</button>
            </div>
        </section>

        <!-- ================= STEP 3: ATS PREVIEW & EXPORT ================= -->
        <section class="step-card" id="stepCard3">
            <div class="builder-layout">
                
                <!-- Sidebar Controls & ATS Analyzer -->
                <aside class="preview-sidebar no-print">
                    
                    <!-- ATS Score Widget -->
                    <div class="sidebar-widget">
                        <h4>ATS Optimization Score</h4>
                        <div class="ats-score-card">
                            <div class="score-circle">
                                <div class="score-inner" id="atsScoreVal">85%</div>
                            </div>
                            <div class="score-label">Compatibility Index</div>
                            <ul id="atsTipsList" style="text-align: left; font-size: 0.8rem; margin-top: 10px; list-style: none;">
                                <!-- Dynamic tips -->
                            </ul>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="sidebar-widget">
                        <button type="button" class="btn btn-primary mb-2" style="width: 100%; font-weight: 700;" onclick="exportToPDF()">
                            <i class="fas fa-file-pdf me-1"></i> Save Resume
                        </button>
                        <button type="button" class="btn btn-secondary" style="width: 100%;" onclick="goToStep(2)">
                            <i class="fas fa-edit"></i> Edit Resume Data
                        </button>
                    </div>

                </aside>

                <!-- Document Preview Canvas -->
                <div class="document-preview-wrapper">
                    <?php include __DIR__ . '/templates/classic_ats.php'; ?>
                </div>

            </div>
        </section>

    </main>
</div>

<!-- ================= SAVE RESUME CONFIRMATION MODAL ================= -->
<div class="modal fade" id="saveResumeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-12px border-0 shadow-lg">
      <div class="modal-header bg-dark text-white rounded-top-12px">
        <h5 class="modal-title fw-bold"><i class="fas fa-file-pdf text-accent me-2"></i>Save Resume</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 bg-light">
        <div class="text-center mb-3">
          <i class="fas fa-question-circle text-warning display-4"></i>
        </div>
        <h5 class="fw-bold text-dark text-center mb-2">Are you sure?</h5>
        <p class="text-muted small text-center mb-3">Are you sure you want to save this resume?</p>
        <div class="mb-2">
          <label for="saveResumeTitleInput" class="form-label fw-bold text-dark small">Resume Name / Title:</label>
          <input type="text" class="form-control rounded-8px" id="saveResumeTitleInput" placeholder="Enter resume name (e.g. Kavinda Rathnayake Resume)">
        </div>
      </div>
      <div class="modal-footer bg-white rounded-bottom-12px justify-content-center gap-2">
        <button type="button" class="btn btn-secondary rounded-4px btn-sm px-4" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary rounded-4px btn-sm font-weight-bold px-4" id="confirmSaveResumeBtn"><i class="fas fa-check-circle me-1"></i> Yes, Save</button>
      </div>
    </div>
  </div>
</div>

<!-- Free Open Source Tesseract.js OCR Engine -->
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<!-- Core App JS -->
<script src="../assets/js/toast.js"></script>
<script src="../assets/js/validation.js"></script>
<script src="js/builder.js"></script>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
