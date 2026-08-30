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
<!-- FontAwesome Icons & Custom Stylesheet -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/styles.css">

<div class="container-fluid header-container-padding py-4" style="background-color: #f8faf9;">
  <!-- Page Header & Toolbar Controls -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
      <h1 class="section-title mb-1"><i class="fas fa-file-contract me-2" style="color: var(--brand-dark);"></i>ProfilePro CV Builder</h1>
      <p class="section-subtitle mb-0">Multi-Template Interactive CV Builder with Real-Time Live Preview</p>
    </div>
    
    <div class="d-flex flex-wrap align-items-center gap-2">
      <!-- Template Selector -->
      <div style="display:flex; align-items:center; gap:0.5rem;" class="me-2">
        <label style="margin:0; font-size:0.85rem; font-weight: 700; color:var(--brand-dark);">Template:</label>
        <select id="templateSelect" class="form-select form-select-sm rounded-8px" style="width: 190px;">
          <option value="modern">1. Modern Executive</option>
          <option value="tech">2. Tech Minimalist</option>
          <option value="creative">3. Creative Gradient</option>
          <option value="classic">4. Classic Elegant</option>
          <option value="compact">5. Compact ATS</option>
          <option value="infographic">6. Infographic Modern</option>
          <option value="split_dark">7. Dark Mode Luxe</option>
          <option value="emerald">8. Emerald Corporate</option>
          <option value="nordic">9. Nordic Minimal</option>
          <option value="bold_sidebar">10. Bold Left Column</option>
        </select>
      </div>

      <!-- JSON Data Actions -->
      <button class="btn btn-outline-brand btn-sm rounded-8px" onclick="exportJSON()" title="Export CV Data as JSON">
        <i class="fas fa-download"></i> Export
      </button>
      <button class="btn btn-outline-brand btn-sm rounded-8px" onclick="triggerImportJSON()" title="Import CV Data from JSON">
        <i class="fas fa-upload"></i> Import
      </button>
      <input type="file" id="jsonFileInput" style="display:none;" accept=".json" onchange="handleJSONImport(event)">

      <button class="btn btn-danger btn-sm rounded-8px" onclick="clearAllData()" title="Reset All Data">
        <i class="fas fa-rotate-left"></i> Reset
      </button>

      <!-- Print / Export PDF -->
      <button class="btn btn-brand rounded-8px" onclick="exportToPDF()">
        <i class="fas fa-file-pdf me-1"></i> Save Resume
      </button>
    </div>
  </div>

  <!-- APP CONTAINER -->
  <div class="app-container" style="border-radius: var(--radius-lg); border: 2px solid var(--border-color); overflow: hidden;">

    <!-- EDITOR SIDEBAR -->
    <aside class="editor-sidebar">
      <!-- Tab Buttons -->
      <div class="editor-tabs">
        <button class="tab-btn active" data-tab="styling"><i class="fas fa-palette"></i> Design</button>
        <button class="tab-btn" data-tab="personal"><i class="fas fa-user"></i> Personal</button>
        <button class="tab-btn" data-tab="experience"><i class="fas fa-briefcase"></i> Experience</button>
        <button class="tab-btn" data-tab="education"><i class="fas fa-graduation-cap"></i> Education</button>
        <button class="tab-btn" data-tab="skills"><i class="fas fa-tags"></i> Skills</button>
        <button class="tab-btn" data-tab="projects"><i class="fas fa-code-branch"></i> Projects</button>
        <button class="tab-btn" data-tab="custom"><i class="fas fa-sliders-h"></i> Custom</button>
      </div>

      <!-- Tab Contents -->
      <div class="editor-content">

        <!-- 1. STYLING & FONTS PANE -->
        <div class="tab-pane active" id="pane-styling">
          <div class="form-group">
            <label><i class="fas fa-font"></i> Typography / Font Family</label>
            <select id="fontFamily">
              <option value="'Inter', sans-serif">Inter (Modern Clean)</option>
              <option value="'Poppins', sans-serif">Poppins (Geometric Sans)</option>
              <option value="'Roboto', sans-serif">Roboto (Standard Sans)</option>
              <option value="'Playfair Display', serif">Playfair Display (Elegant Serif)</option>
              <option value="'Montserrat', sans-serif">Montserrat (Bold Header)</option>
              <option value="'Fira Code', monospace">Fira Code (Developer Mono)</option>
            </select>
          </div>

          <div class="form-group">
            <label><i class="fas fa-text-height"></i> Font Size Sizing</label>
            <div class="range-slider">
              <input type="range" id="fontSize" min="11" max="18" value="14" step="1">
              <span id="fontSizeVal">14px</span>
            </div>
          </div>

          <div class="form-group">
            <label><i class="fas fa-palette"></i> Color Theme Palette</label>
            <div class="color-pickers">
              <div>
                <label>Primary</label>
                <div class="color-input-wrapper">
                  <input type="color" id="primaryColor" value="#2563eb">
                </div>
              </div>
              <div>
                <label>Secondary</label>
                <div class="color-input-wrapper">
                  <input type="color" id="secondaryColor" value="#475569">
                </div>
              </div>
              <div>
                <label>Text Color</label>
                <div class="color-input-wrapper">
                  <input type="color" id="textColor" value="#1e293b">
                </div>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label><i class="fas fa-fill-drip"></i> Paper Background Color</label>
            <div class="color-input-wrapper" style="width: 140px;">
              <input type="color" id="bgColor" value="#ffffff">
              <span style="font-size:0.8rem; color:var(--text-muted);">Paper Color</span>
            </div>
          </div>
        </div>

        <!-- 2. PERSONAL INFO PANE -->
        <div class="tab-pane" id="pane-personal">
          <div class="form-group">
            <label><i class="fas fa-camera"></i> Profile Photo / Avatar</label>
            <div class="avatar-upload-box">
              <div class="avatar-preview">
                <i class="fas fa-user-circle" id="avatarIcon"></i>
                <img id="avatarImg" src="" style="display:none;" alt="Avatar">
              </div>
              <div class="avatar-controls">
                <input type="file" id="avatarInput" accept="image/*" style="font-size:0.8rem;">
                <div style="display:flex; gap:0.5rem;">
                  <select id="photoShape" style="width:110px; padding:0.3rem;">
                    <option value="circle">Circle</option>
                    <option value="square">Rounded</option>
                  </select>
                  <button class="btn btn-outline btn-sm" id="removeAvatarBtn" type="button"><i class="fas fa-trash"></i></button>
                </div>
              </div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Full Name</label>
              <input type="text" id="fullName" placeholder="e.g. Alexander Vance">
            </div>
            <div class="form-group">
              <label>Job Title</label>
              <input type="text" id="jobTitle" placeholder="e.g. Senior Full Stack Engineer">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Email Address</label>
              <input type="email" id="email" placeholder="e.g. alex@example.com">
            </div>
            <div class="form-group">
              <label>Phone Number</label>
              <input type="tel" id="phone" placeholder="e.g. +1 555-0192">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Location / City</label>
              <input type="text" id="location" placeholder="e.g. San Francisco, CA">
            </div>
            <div class="form-group">
              <label>Portfolio / Website</label>
              <input type="text" id="website" placeholder="e.g. alexvance.dev">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>LinkedIn</label>
              <input type="text" id="linkedin" placeholder="e.g. linkedin.com/in/alex">
            </div>
            <div class="form-group">
              <label>GitHub</label>
              <input type="text" id="github" placeholder="e.g. github.com/alex">
            </div>
          </div>

          <div class="form-group">
            <label>Professional Summary</label>
            <textarea id="summary" placeholder="Write a short summary about your professional background and achievements..."></textarea>
          </div>
        </div>

        <!-- 3. WORK EXPERIENCE PANE -->
        <div class="tab-pane" id="pane-experience">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <h4 style="font-size:0.9rem; color:var(--text-muted);">Work History</h4>
            <button class="btn btn-primary btn-sm" onclick="addExperience()"><i class="fas fa-plus"></i> Add Position</button>
          </div>
          <div id="experienceList"></div>
        </div>

        <!-- 4. EDUCATION PANE -->
        <div class="tab-pane" id="pane-education">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <h4 style="font-size:0.9rem; color:var(--text-muted);">Education & Degrees</h4>
            <button class="btn btn-primary btn-sm" onclick="addEducation()"><i class="fas fa-plus"></i> Add Education</button>
          </div>
          <div id="educationList"></div>
        </div>

        <!-- 5. SKILLS PANE -->
        <div class="tab-pane" id="pane-skills">
          <div class="form-group">
            <label>Skills (Comma Separated)</label>
            <textarea id="skillsInput" placeholder="e.g. JavaScript, PHP, React, Node.js, PostgreSQL, Docker, AWS"></textarea>
            <small style="color:var(--text-muted); font-size:0.75rem; display:block; margin-top:0.3rem;">Separate skills with commas to format them into sleek skill tags.</small>
          </div>
        </div>

        <!-- 6. PROJECTS PANE -->
        <div class="tab-pane" id="pane-projects">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <h4 style="font-size:0.9rem; color:var(--text-muted);">Key Projects & Portfolios</h4>
            <button class="btn btn-primary btn-sm" onclick="addProject()"><i class="fas fa-plus"></i> Add Project</button>
          </div>
          <div id="projectsList"></div>
        </div>

        <!-- 7. CUSTOM SECTIONS PANE -->
        <div class="tab-pane" id="pane-custom">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <h4 style="font-size:0.9rem; color:var(--text-muted);">Custom Additional Sections</h4>
            <button class="btn btn-primary btn-sm" onclick="addCustom()"><i class="fas fa-plus"></i> Add Custom Section</button>
          </div>
          <div id="customList"></div>
        </div>

      </div>
    </aside>

    <!-- PREVIEW CANVAS WORKSPACE -->
    <main class="preview-workspace">
      <!-- Zoom Toolbar -->
      <div class="preview-toolbar">
        <label style="margin:0; font-size:0.8rem; font-weight:700; white-space:nowrap; color:#ffffff;"><i class="fas fa-search-plus me-1"></i> ZOOM CANVAS:</label>
        <div class="range-slider" style="display:flex; align-items:center; gap:0.75rem; width: auto;">
          <input type="range" id="zoomSlider" min="30" max="120" value="50" step="5" style="width: 130px; accent-color: #ACFF78;">
          <span id="zoomVal" style="font-weight: 700; font-size: 0.85rem; color: #ACFF78; min-width: 40px;">50%</span>
        </div>
      </div>

      <!-- CV A4 Printable Page -->
      <div class="cv-page-container" id="cvPageContainer" style="transform: scale(0.5); transform-origin: top center;">
        <div class="cv-page" id="cvPage">
          <!-- Dynamic CV HTML rendered here by JS -->
        </div>
      </div>
    </main>

  </div> <!-- End App Container -->
</div> <!-- End container-fluid -->

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
          <input type="text" class="form-control rounded-8px" id="saveResumeTitleInput" placeholder="Enter resume name (e.g. John Doe Resume)">
        </div>
      </div>
      <div class="modal-footer bg-white rounded-bottom-12px justify-content-center gap-2">
        <button type="button" class="btn btn-secondary rounded-4px btn-sm px-4" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-brand rounded-4px btn-sm font-weight-bold px-4" id="confirmSaveResumeBtn"><i class="fas fa-check-circle me-1"></i> Yes, Save</button>
      </div>
    </div>
  </div>
</div>

<!-- JavaScript Libraries -->
<script src="../assets/js/toast.js"></script>
<script src="../assets/js/validation.js"></script>
<script src="js/templates.js"></script>
<script src="js/app.js"></script>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
