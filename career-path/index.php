<?php
require_once __DIR__ . '/../config/config.php';
include_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/activity_logger.php';
require_once __DIR__ . '/../includes/industries.php';

$successMsg = '';
$errorMsg = '';

// Handle Job Application Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply_job') {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['userid'])) {
        $errorMsg = "Please log in as a candidate to apply for jobs.";
    } else {
        $userId = $_SESSION['user_id'] ?? $_SESSION['userid'];
        $vacancyId = intval($_POST['job_id'] ?? 0);
        $resumeId = intval($_POST['cv_id'] ?? 0);

        if ($vacancyId <= 0) {
            $errorMsg = "Invalid job vacancy selected.";
        } else {
            try {
                // Check if already applied in appliedjobs table
                $chkStmt = $pdo->prepare("SELECT applicationid FROM appliedjobs WHERE vacancyid = ? AND userid = ?");
                $chkStmt->execute([$vacancyId, $userId]);
                if ($chkStmt->fetch()) {
                    $errorMsg = "You have already submitted an application for this job opening.";
                } else {
                    $insStmt = $pdo->prepare("
                        INSERT INTO appliedjobs (userid, vacancyid, resumeid, appliedDate, status)
                        VALUES (?, ?, ?, NOW(), 'Pending')
                    ");
                    $insStmt->execute([$userId, $vacancyId, $resumeId > 0 ? $resumeId : null]);

                    // Fetch Job Title for activity log
                    $jStmt = $pdo->prepare("SELECT jobTitle FROM vacancy WHERE vacancyid = ?");
                    $jStmt->execute([$vacancyId]);
                    $jobInfo = $jStmt->fetch();
                    $jobTitle = $jobInfo['jobTitle'] ?? "Vacancy #$vacancyId";

                    // Log Activity History
                    logActivity($pdo, $userId, null, "Applied for job opening: $jobTitle");

                    $successMsg = "Application submitted successfully! The hiring company has been notified.";
                }
            } catch (Exception $e) {
                $errorMsg = "Failed to submit application: " . $e->getMessage();
            }
        }
    }
}

// Fetch Direct Company Vacancies from DB (vacancy & company tables, excluding suspended companies)
$localVacancies = [];
try {
    $locStmt = $pdo->query("
        SELECT v.*, c.companyName, c.companyLogo,
               COALESCE(NULLIF(v.industry, ''), c.industry, 'General') AS vacancyIndustry
        FROM vacancy v
        JOIN company c ON v.companyid = c.companyid
        WHERE (v.jobstatus = 'Open' OR v.jobstatus = 'Active' OR v.jobstatus IS NULL)
          AND (c.accountStatus != 'Suspended' OR c.accountStatus IS NULL)
        ORDER BY v.createdAt DESC
    ");
    $localVacancies = $locStmt->fetchAll();
} catch (Exception $e) {
    $localVacancies = [];
}

// Helper to clean resume filenames for display
function getCleanResumeName($filepath) {
    if (empty($filepath)) return 'Unnamed Resume';
    $basename = basename($filepath);
    $clean = preg_replace('/^resume_\d+_\d+_/', '', $basename);
    $clean = preg_replace('/\.(html|pdf|doc|docx)$/i', '', $clean);
    return ucwords(str_replace('_', ' ', $clean));
}

// Fetch Logged-in Candidate Profile & Resumes for Application Modal
$userCvs = [];
$currentUser = null;
$currentUserId = $_SESSION['user_id'] ?? $_SESSION['userid'] ?? null;
if ($currentUserId) {
    try {
        $uStmt = $pdo->prepare("SELECT * FROM user WHERE userid = ?");
        $uStmt->execute([$currentUserId]);
        $currentUser = $uStmt->fetch();

        $cvStmt = $pdo->prepare("SELECT resumeid, resumes FROM resume WHERE userid = ? ORDER BY resumeid DESC");
        $cvStmt->execute([$currentUserId]);
        $userCvs = $cvStmt->fetchAll();
    } catch (Exception $e) {
        $userCvs = [];
        $currentUser = null;
    }
}
?>

<style>
    .vacancy-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        transition: all 0.25s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    .vacancy-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0, 71, 67, 0.1);
        border-color: var(--brand-dark, #004743);
    }
    .career-card .btn,
    .local-vacancy-item .btn {
        white-space: nowrap;
    }
</style>

<div class="container py-4" style="min-height: 85vh;">

    <?php if ($successMsg): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-8px mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($successMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-8px mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($errorMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Page Header & Search/Filter Controls -->
    <div class="row align-items-center mb-4 g-2">
        <div class="col-lg-5 col-md-12">
            <h1 class="section-title text-start mb-0"><i class="bi bi-briefcase-fill me-2" style="color: var(--brand-dark);"></i>Career Paths & Vacancies</h1>
        </div>
        <div class="col-lg-3 col-md-5">
            <select id="careerIndustryFilter" class="form-select shadow-sm rounded-8px border py-2" style="font-size: 0.88rem;">
                <?= renderIndustryOptions('', 'All Industry Sectors') ?>
            </select>
        </div>
        <div class="col-lg-4 col-md-7">
            <div class="input-group shadow-sm rounded-8px border overflow-hidden">
                <span class="input-group-text bg-white border-0 text-muted ps-3"><i class="bi bi-search"></i></span>
                <input type="text" id="careerSearchInput" class="form-control border-0 py-2 shadow-none" placeholder="Search by Job Title, Company, Location...">
                <button class="btn btn-white border-0 text-muted px-3" type="button" id="clearCareerSearch" style="display: none;"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>
    </div>

    <!-- Verified Corporate Openings Section -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0 text-dark">Verified Corporate Openings</h5>
        <span class="badge bg-brand text-accent px-3 py-2 rounded-8px" id="local-count-badge">Showing <?= count($localVacancies) ?> Openings</span>
    </div>

    <?php if (empty($localVacancies)): ?>
        <div class="text-center py-5 bg-white rounded-12px border shadow-sm">
            <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
            <h5 class="mt-3 text-muted">No Open Vacancies Right Now</h5>
            <p class="text-muted small">Check back soon as top companies post new career opportunities daily.</p>
        </div>
    <?php else: ?>
        <div class="row g-4" id="local-vacancies-grid">
            <?php foreach ($localVacancies as $vac): ?>
                <?php 
                $imgSrc = 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&w=600&q=80';
                if (!empty($vac['vacancyImage'])) {
                    $imgSrc = (strpos($vac['vacancyImage'], 'http') === 0) ? $vac['vacancyImage'] : '/skillsync/' . $vac['vacancyImage'];
                }
                ?>
                <div class="col-md-6 col-lg-4 local-vacancy-item" data-industry="<?= htmlspecialchars(strtolower($vac['vacancyIndustry'] ?? '')) ?>">
                    <div class="card-custom career-card d-flex flex-column h-100">
                        <div class="career-img-wrapper">
                            <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($vac['jobTitle']) ?>">
                            <span class="career-tag"><?= htmlspecialchars($vac['companyName']) ?></span>
                        </div>
                        <div class="card-body p-4 d-flex flex-column justify-content-between flex-grow-1">
                            <div>
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($vac['jobTitle']) ?></h5>
                                </div>
                                <span class="badge bg-light text-brand rounded-4px border mb-2 d-inline-block"><i class="bi bi-building me-1"></i><?= htmlspecialchars($vac['vacancyIndustry'] ?? 'General') ?></span>
                                <p class="text-muted small mb-3"><?= htmlspecialchars(!empty($vac['requirements']) ? substr($vac['requirements'], 0, 100) : (!empty($vac['jobDescription']) ? substr($vac['jobDescription'], 0, 100) . '...' : 'Verified company opportunity.')) ?></p>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pt-2 border-top mt-auto">
                                <span class="badge bg-light text-dark rounded-4px border"><i class="bi bi-geo-alt me-1 text-brand"></i><?= htmlspecialchars($vac['jobLocation'] ?? 'Sri Lanka') ?></span>
                                <button class="btn btn-outline-brand btn-sm rounded-8px" style="white-space: nowrap;" onclick="openApplyModal(<?= $vac['vacancyid'] ?>, '<?= htmlspecialchars(addslashes($vac['jobTitle'])) ?>', '<?= htmlspecialchars(addslashes($vac['companyName'])) ?>')">
                                    Apply Now <i class="bi bi-chevron-right ms-1"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination for Local Vacancies -->
        <nav class="mt-4" aria-label="Local Vacancies Pagination">
            <ul class="pagination justify-content-center" id="local-pagination"></ul>
        </nav>
    <?php endif; ?>
</div>

<!-- ================= APPLY FOR JOB MODAL ================= -->
<div class="modal fade" id="applyJobModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-12px border-0 shadow-lg">
            <div class="modal-header bg-dark text-white rounded-top-12px">
                <h5 class="modal-title fw-bold" id="modalJobTitle">Apply for Job</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="./" method="POST" id="applyJobForm" onsubmit="return confirmSaveApplication(event)">
                <input type="hidden" name="action" value="apply_job">
                <input type="hidden" name="job_id" id="modalJobId" value="0">

                <div class="modal-body p-4">
                    <div class="alert alert-light border rounded-8px p-3 mb-4 d-flex align-items-center gap-3">
                        <div class="bg-brand text-accent rounded-circle d-flex align-items-center justify-content-center fw-bold fs-4" style="width:48px; height:48px; flex-shrink:0;">
                            <?= (!empty($currentUser['firstName'])) ? strtoupper(substr($currentUser['firstName'], 0, 1)) : '<i class="bi bi-person"></i>' ?>
                        </div>
                        <div>
                            <div class="fw-bold text-dark fs-6" id="modalCompanyName">Submitting application to company...</div>
                            <div class="text-muted small">Your profile info will be attached automatically to this application.</div>
                        </div>
                    </div>

                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-person-vcard me-2 text-brand"></i>Applicant Profile Information (Auto-Filled)</h6>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1">Full Name</label>
                            <input type="text" class="form-control rounded-8px bg-light" value="<?= htmlspecialchars(trim(($currentUser['firstName'] ?? '') . ' ' . ($currentUser['lastName'] ?? ''))) ?: 'Guest Candidate' ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1">Email Address</label>
                            <input type="email" class="form-control rounded-8px bg-light" value="<?= htmlspecialchars($currentUser['email'] ?? 'Not Logged In') ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1">Contact Phone</label>
                            <input type="text" class="form-control rounded-8px bg-light" value="<?= htmlspecialchars($currentUser['mobileNo'] ?? 'Not provided') ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1">Professional Title</label>
                            <input type="text" class="form-control rounded-8px bg-light" value="<?= htmlspecialchars($currentUser['profTitle'] ?? 'Software Professional') ?>" readonly>
                        </div>
                        <?php if (!empty($currentUser['skills'])): ?>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted mb-1">Skills Profile</label>
                                <input type="text" class="form-control rounded-8px bg-light" value="<?= htmlspecialchars($currentUser['skills']) ?>" readonly>
                            </div>
                        <?php endif; ?>
                    </div>

                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-paperclip me-2 text-brand"></i>Application Attachment & Cover Letter</h6>

                    <div class="mb-3">
                        <label for="cv_id" class="form-label fw-bold">Select Attached Resume / CV</label>
                        <select name="cv_id" id="cv_id" class="form-select rounded-8px">
                            <?php if (empty($userCvs)): ?>
                                <option value="0">No uploaded resume found (Profile details will be sent directly)</option>
                            <?php else: ?>
                                <?php foreach ($userCvs as $cv): ?>
                                    <option value="<?= $cv['resumeid'] ?>">📄 <?= htmlspecialchars(getCleanResumeName($cv['resumes'])) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label for="cover_letter" class="form-label fw-bold">Cover Letter / Note to Hiring Manager (Optional)</label>
                        <textarea name="cover_letter" id="cover_letter" rows="3" class="form-control rounded-8px" placeholder="Write a brief note introducing yourself to the hiring team..."></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light rounded-bottom-12px">
                    <button type="button" class="btn btn-secondary rounded-8px" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand rounded-8px px-4" style="white-space: nowrap;" <?= !$currentUserId ? 'disabled' : '' ?>>
                        <i class="bi bi-send me-1"></i> Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Dynamic 9 Items Per Page Pagination & Live Search/Filter Controller -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const localItems = Array.from(document.querySelectorAll('.local-vacancy-item'));
    const searchInput = document.getElementById('careerSearchInput');
    const industryFilter = document.getElementById('careerIndustryFilter');
    const clearSearchBtn = document.getElementById('clearCareerSearch');

    let activeLocalItems = [...localItems];

    const localItemsPerPage = 9;
    let currentLocalPage = 1;

    function renderLocalPage(page) {
        currentLocalPage = page;
        const totalPages = Math.ceil(activeLocalItems.length / localItemsPerPage) || 1;
        const start = (page - 1) * localItemsPerPage;
        const end = start + localItemsPerPage;

        localItems.forEach(item => item.style.display = 'none');
        activeLocalItems.forEach((item, index) => {
            if (index >= start && index < end) {
                item.style.display = 'block';
            }
        });

        const badge = document.getElementById('local-count-badge');
        if (badge) {
            badge.textContent = `Showing ${Math.min(end, activeLocalItems.length)} of ${activeLocalItems.length} Openings`;
        }

        const pagContainer = document.getElementById('local-pagination');
        if (pagContainer) {
            if (activeLocalItems.length === 0) {
                pagContainer.innerHTML = '<li class="text-muted small py-2">No matching vacancies found for the selected filter criteria.</li>';
                return;
            }
            let html = `<li class="page-item ${page === 1 ? 'disabled' : ''}">
                <button class="page-link rounded-4px" ${page === 1 ? 'disabled' : ''} onclick="changeLocalPage(${page - 1})" aria-label="Previous"><i class="bi bi-chevron-left"></i></button>
            </li>`;
            for (let i = 1; i <= totalPages; i++) {
                html += `<li class="page-item ${i === page ? 'active' : ''}">
                    <button class="page-link rounded-4px" onclick="changeLocalPage(${i})">${i}</button>
                </li>`;
            }
            html += `<li class="page-item ${page === totalPages ? 'disabled' : ''}">
                <button class="page-link rounded-4px" ${page === totalPages ? 'disabled' : ''} onclick="changeLocalPage(${page + 1})" aria-label="Next"><i class="bi bi-chevron-right"></i></button>
            </li>`;
            pagContainer.innerHTML = html;
        }
    }

    window.changeLocalPage = function(page) {
        renderLocalPage(page);
    };

    function applyFilterAndSearch() {
        const q = (searchInput?.value || '').toLowerCase().trim();
        const selectedInd = (industryFilter?.value || '').toLowerCase().trim();

        if (clearSearchBtn) {
            clearSearchBtn.style.display = q ? 'inline-block' : 'none';
        }

        activeLocalItems = localItems.filter(item => {
            const itemText = item.textContent.toLowerCase();
            const itemIndustry = (item.getAttribute('data-industry') || '').toLowerCase();

            const matchesQuery = !q || itemText.includes(q);
            const matchesIndustry = !selectedInd || itemIndustry.includes(selectedInd);

            return matchesQuery && matchesIndustry;
        });

        renderLocalPage(1);
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyFilterAndSearch);
    }
    if (industryFilter) {
        industryFilter.addEventListener('change', applyFilterAndSearch);
    }
    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            applyFilterAndSearch();
        });
    }

    renderLocalPage(1);
});

function openApplyModal(jobId, jobTitle, companyName) {
    document.getElementById('modalJobId').value = jobId;
    document.getElementById('modalJobTitle').textContent = 'Apply for: ' + jobTitle;
    document.getElementById('modalCompanyName').textContent = 'Submitting application to ' + companyName;

    const modalEl = document.getElementById('applyJobModal');
    if (modalEl) {
        const bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();
    }
}

function confirmSaveApplication(event) {
    if (!confirm('Are you sure you want to submit this job application?')) {
        event.preventDefault();
        return false;
    }
    return true;
}
</script>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
