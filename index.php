<?php
// SkillSync Index Page
require_once __DIR__ . '/config/config.php';

// Fetch candidate user chosen industry
$userIndustry = '';
$currentUserId = $_SESSION['user_id'] ?? null;
if (!$currentUserId && isset($pdo)) {
    $stmtDefaultU = $pdo->query("SELECT userid FROM user WHERE accStatus = 'Active' ORDER BY userid ASC LIMIT 1");
    $currentUserId = $stmtDefaultU->fetchColumn();
}

if (!empty($currentUserId) && isset($pdo)) {
    $stmtUserInd = $pdo->prepare("SELECT industry FROM user WHERE userid = ?");
    $stmtUserInd->execute([$currentUserId]);
    $userIndustry = trim($stmtUserInd->fetchColumn() ?: '');
}

if (empty($userIndustry)) {
    $userIndustry = 'Information Technology & Software (ICT)';
}

// Fetch 2 jobs related to the user's chosen industry (excluding suspended companies)
$industryJobs = [];
try {
    $indTerm = '%' . $userIndustry . '%';
    $stmtIndJobs = $pdo->prepare("
        SELECT v.*, c.companyName, c.companyLogo 
        FROM vacancy v 
        JOIN company c ON v.companyid = c.companyid 
        WHERE (v.jobstatus = 'Open' OR v.jobstatus = 'Active' OR v.jobstatus IS NULL)
          AND (c.accountStatus != 'Suspended' OR c.accountStatus IS NULL)
          AND (v.industry = ? OR v.industry LIKE ? OR c.industry = ? OR c.industry LIKE ?)
        ORDER BY v.createdAt DESC
        LIMIT 2
    ");
    $stmtIndJobs->execute([$userIndustry, $indTerm, $userIndustry, $indTerm]);
    $industryJobs = $stmtIndJobs->fetchAll(PDO::FETCH_ASSOC);

    // Fallback: If fewer than 2 jobs found in chosen industry, fill from remaining open vacancies
    if (count($industryJobs) < 2) {
        $excludeIds = array_column($industryJobs, 'vacancyid');
        $placeholders = !empty($excludeIds) ? implode(',', array_fill(0, count($excludeIds), '?')) : '0';
        $stmtFill = $pdo->prepare("
            SELECT v.*, c.companyName, c.companyLogo 
            FROM vacancy v 
            JOIN company c ON v.companyid = c.companyid 
            WHERE (v.jobstatus = 'Open' OR v.jobstatus = 'Active' OR v.jobstatus IS NULL)
              AND (c.accountStatus != 'Suspended' OR c.accountStatus IS NULL)
              AND v.vacancyid NOT IN ($placeholders)
            ORDER BY v.createdAt DESC
            LIMIT " . (2 - count($industryJobs)) . "
        ");
        $stmtFill->execute($excludeIds);
        $fillJobs = $stmtFill->fetchAll(PDO::FETCH_ASSOC);
        $industryJobs = array_merge($industryJobs, $fillJobs);
    }
} catch (Exception $e) {
    $industryJobs = [];
}

// Fetch open company vacancies (exclude suspended companies)
$stmtHomeVac = $pdo->prepare("
    SELECT v.*, c.companyName, c.companyLogo 
    FROM vacancy v 
    JOIN company c ON v.companyid = c.companyid 
    WHERE v.jobstatus = 'Open' 
      AND (c.accountStatus != 'Suspended' OR c.accountStatus IS NULL)
    ORDER BY v.createdAt DESC
");
$stmtHomeVac->execute();
$homeVacancies = $stmtHomeVac->fetchAll();

include_once __DIR__ . '/includes/header.php';
?>

<!-- SECTION 1: HERO SECTION - Fullscreen Slider (4 Slides) -->
<section id="hero" class="hero-slider-section">
    <div id="heroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="5000">
        <!-- Indicators -->
        <div class="carousel-indicators">
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="3" aria-label="Slide 4"></button>
        </div>

        <div class="carousel-inner">
            <!-- Slide 1: Related Job 1 from Chosen Industry -->
            <?php 
                $job1 = $industryJobs[0] ?? null;
                $job1Title = $job1 ? $job1['jobTitle'] : "Sync Your Skills with Future Tech Careers";
                $job1Comp = $job1 ? $job1['companyName'] : "";
                $job1Desc = $job1 ? (substr($job1['jobDescription'] ?: ($job1['requirements'] ?: "Join {$job1Comp} for exciting career growth."), 0, 160) . '...') : "Discover personalized career pathways, bridge skill gaps with AI diagnostics, and get recognized by leading global tech recruiters.";
                $job1Img = ($job1 && !empty($job1['vacancyImage'])) ? ((strpos($job1['vacancyImage'], 'http') === 0) ? $job1['vacancyImage'] : $job1['vacancyImage']) : 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=1920&q=80';
                $job1Link = $job1 ? "career-path/#vac-{$job1['vacancyid']}" : "career-path/";
            ?>
            <div class="carousel-item active">
                <div class="hero-slide-bg" style="background-image: url('<?= htmlspecialchars($job1Img) ?>');">
                    <div class="hero-overlay"></div>
                    <div class="container">
                        <div class="row">
                            <div class="col-lg-8 hero-content">
                                <?php if ($job1Comp): ?>
                                    <span class="badge bg-accent text-dark fw-bold mb-2 px-3 py-2 rounded-pill text-white" style="font-size: 0.82rem;">
                                        <i class="bi bi-briefcase-fill me-1"></i> <?= htmlspecialchars($job1Comp) ?> &bull; <?= htmlspecialchars($job1['industry'] ?? $userIndustry) ?>
                                    </span>
                                <?php endif; ?>
                                <h1 class="hero-title"><?= htmlspecialchars($job1Title) ?></h1>
                                <p class="hero-desc"><?= htmlspecialchars($job1Desc) ?></p>
                                <div>
                                    <a href="<?= htmlspecialchars($job1Link) ?>" class="btn btn-accent btn-lg btn-pill-100px hero-cta-btn"><span>Apply on Career Path</span> <span class="cta-icon-circle"><i class="bi bi-arrow-right-short"></i></span></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Slide 2: Related Job 2 from Chosen Industry -->
            <?php 
                $job2 = $industryJobs[1] ?? null;
                $job2Title = $job2 ? $job2['jobTitle'] : "Craft Recruiter-Approved Resumes in Seconds";
                $job2Comp = $job2 ? $job2['companyName'] : "";
                $job2Desc = $job2 ? (substr($job2['jobDescription'] ?: ($job2['requirements'] ?: "Join {$job2Comp} for exciting career growth."), 0, 160) . '...') : "Generate tailored, ATS-friendly CVs matched against real-time job specifications to double your interview call rate.";
                $job2Img = ($job2 && !empty($job2['vacancyImage'])) ? ((strpos($job2['vacancyImage'], 'http') === 0) ? $job2['vacancyImage'] : $job2['vacancyImage']) : 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=1920&q=80';
                $job2Link = $job2 ? "career-path/#vac-{$job2['vacancyid']}" : "career-path/";
            ?>
            <div class="carousel-item">
                <div class="hero-slide-bg" style="background-image: url('<?= htmlspecialchars($job2Img) ?>');">
                    <div class="hero-overlay"></div>
                    <div class="container">
                        <div class="row">
                            <div class="col-lg-8 hero-content">
                                <?php if ($job2Comp): ?>
                                    <span class="badge bg-accent text-white fw-bold mb-2 px-3 py-2 rounded-pill" style="font-size: 0.82rem;">
                                        <i class="bi bi-briefcase-fill me-1"></i> <?= htmlspecialchars($job2Comp) ?> &bull; <?= htmlspecialchars($job2['industry'] ?? $userIndustry) ?>
                                    </span>
                                <?php endif; ?>
                                <h1 class="hero-title"><?= htmlspecialchars($job2Title) ?></h1>
                                <p class="hero-desc"><?= htmlspecialchars($job2Desc) ?></p>
                                <div>
                                    <a href="<?= htmlspecialchars($job2Link) ?>" class="btn btn-accent btn-lg btn-pill-100px hero-cta-btn"><span>Apply on Career Path</span> <span class="cta-icon-circle"><i class="bi bi-arrow-right-short"></i></span></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Slide 3: 1-on-1 Mentorship & Events -->
            <div class="carousel-item">
                <div class="hero-slide-bg" style="background-image: url('https://images.unsplash.com/photo-1531482615713-2afd69097998?auto=format&fit=crop&w=1920&q=80');">
                    <div class="hero-overlay"></div>
                    <div class="container">
                        <div class="row">
                            <div class="col-lg-8 hero-content">

                                <h1 class="hero-title">Learn Directly from Industry Titans</h1>
                                <p class="hero-desc">Join interactive sessions, mock interview prep, and live coding bootcamps scheduled seamlessly on your personal calendar.</p>
                                <div>
                                    <a href="Intervia/" class="btn btn-accent btn-lg btn-pill-100px hero-cta-btn"><span>Book Mentorship</span> <span class="cta-icon-circle"><i class="bi bi-arrow-right-short"></i></span></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Slide 4: Career Analytics & Growth Track -->
            <div class="carousel-item">
                <div class="hero-slide-bg" style="background-image: url('https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=1920&q=80');">
                    <div class="hero-overlay"></div>
                    <div class="container">
                        <div class="row">
                            <div class="col-lg-8 hero-content">
                                <h1 class="hero-title">Track Your Growth & Salary Potential</h1>
                                <p class="hero-desc">Leverage market analytics to identify high-paying technical skills and position yourself for rapid career progression.</p>
                                <div>
                                    <a href="user-profile/" class="btn btn-accent btn-lg btn-pill-100px hero-cta-btn"><span>Start Growth Track</span> <span class="cta-icon-circle"><i class="bi bi-arrow-right-short"></i></span></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 2: AVAILABLE CAREER PATHS (Dynamic Company Vacancies) -->
<section id="careers" class="py-5">
    <div class="container py-4">
        <div class="text-center mb-5">
            <h2 class="section-title">Available Career Paths</h2>
            <p class="section-subtitle">Real-time company vacancies and verified career opportunities published by leading employers.</p>
        </div>

        <?php if (!empty($homeVacancies)): ?>
            <?php $chunks = array_chunk($homeVacancies, 3); ?>
            <div id="careerCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-inner">
                    <?php foreach ($chunks as $cIndex => $chunk): ?>
                        <div class="carousel-item <?= $cIndex === 0 ? 'active' : '' ?>">
                            <div class="row g-4">
                                <?php foreach ($chunk as $v): ?>
                                    <div class="col-lg-4 col-md-6">
                                        <div class="card-custom career-card">
                                            <div class="career-img-wrapper">
                                                <?php 
                                                $imgSrc = 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&w=600&q=80';
                                                if (!empty($v['vacancyImage'])) {
                                                    $imgSrc = (strpos($v['vacancyImage'], 'http') === 0) ? $v['vacancyImage'] : $base_url . $v['vacancyImage'];
                                                }
                                                ?>
                                                <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($v['jobTitle']) ?>">
                                                <span class="career-tag"><?= htmlspecialchars($v['companyName']) ?></span>
                                            </div>
                                            <div class="card-body p-4">
                                                <h5 class="fw-bold text-dark mb-2"><?= htmlspecialchars($v['jobTitle']) ?></h5>
                                                <p class="text-muted small mb-3"><?= htmlspecialchars(!empty($v['requirements']) ? $v['requirements'] : (!empty($v['jobDescription']) ? substr($v['jobDescription'], 0, 90) . '...' : 'Verified company opportunity.')) ?></p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="badge bg-light text-dark rounded-4px border"><i class="bi bi-geo-alt me-1 text-brand"></i><?= htmlspecialchars($v['jobLocation']) ?></span>
                                                    <a href="career-path/" class="btn btn-outline-brand btn-sm rounded-8px">Explore Path <i class="bi bi-chevron-right"></i></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($chunks) > 1): ?>
                    <div class="d-flex justify-content-center gap-3 mt-4">
                        <button class="btn btn-outline-brand btn-sm rounded-circle p-2" type="button" data-bs-target="#careerCarousel" data-bs-slide="prev" style="width: 40px; height: 40px;">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <button class="btn btn-outline-brand btn-sm rounded-circle p-2" type="button" data-bs-target="#careerCarousel" data-bs-slide="next" style="width: 40px; height: 40px;">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-4 bg-white rounded-12px shadow-sm">
                <i class="bi bi-briefcase text-muted display-4"></i>
                <p class="text-muted mt-2 mb-0">No active vacancies currently posted by companies. Check back soon!</p>
            </div>
        <?php endif; ?>

        <!-- Career Paths CTA -->
        <div class="text-center mt-5">
            <a href="career-path/" class="btn btn-accent btn-lg btn-pill-100px">VIEW ALL CAREER PATHS <i class="bi bi-arrow-right ms-2"></i></a>
        </div>
    </div>
</section>

<!-- SECTION 3: OUR SERVICES SECTION (4 Cards with CTA) -->
<section id="services" class="py-5 bg-white">
    <div class="container py-4">
        <div class="text-center mb-5">
            <h2 class="section-title">Our Core Platform Modules</h2>
            <p class="section-subtitle">Tailored intelligence tools designed to accelerate every stage of your career progression.</p>
        </div>

        <div class="row g-4">
            <!-- Service 1: Intervia AI Interview -->
            <div class="col-lg-3 col-md-6">
                <div class="card-custom p-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="card-icon-wrapper">
                            <i class="bi bi-robot"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">Intervia AI Mock Practice</h5>
                        <p class="text-muted small mb-4">Simulate technical & behavioral interviews with AI-powered questions and real-time Eye & Posture confidence tracking.</p>
                    </div>
                    <a href="Intervia/" class="btn btn-outline-brand w-100 rounded-8px">Start AI Interview <i class="bi bi-chevron-right ms-1"></i></a>
                </div>
            </div>

            <!-- Service 2: ATSync Resume Parser -->
            <div class="col-lg-3 col-md-6">
                <div class="card-custom p-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="card-icon-wrapper" style="background-color: rgba(172, 255, 120, 0.25);">
                            <i class="bi bi-file-earmark-code text-dark"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">ATSync CV & OCR Engine</h5>
                        <p class="text-muted small mb-4">Upload resumes or identity documents for automated OCR parsing, formatting verification, and ATS compatibility.</p>
                    </div>
                    <a href="atsync/" class="btn btn-outline-brand w-100 rounded-8px">Parse CV & NIC <i class="bi bi-chevron-right ms-1"></i></a>
                </div>
            </div>

            <!-- Service 3: Job Scout -->
            <div class="col-lg-3 col-md-6">
                <div class="card-custom p-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="card-icon-wrapper">
                            <i class="bi bi-search"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">Job Scout Vacancy Hub</h5>
                        <p class="text-muted small mb-4">Browse real-time job vacancies categorized by industry field, location, and qualification with instant applications.</p>
                    </div>
                    <a href="job-scout/" class="btn btn-outline-brand w-100 rounded-8px">Explore Vacancies <i class="bi bi-chevron-right ms-1"></i></a>
                </div>
            </div>

            <!-- Service 4: ProfilePro & Calendar -->
            <div class="col-lg-3 col-md-6">
                <div class="card-custom p-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="card-icon-wrapper" style="background-color: rgba(172, 255, 120, 0.25);">
                            <i class="bi bi-person-lines-fill text-dark"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">ProfilePro & Activity History</h5>
                        <p class="text-muted small mb-4">Manage personal achievements, track chronological activity history logs, and schedule interview dates on your interactive calendar.</p>
                    </div>
                    <a href="profile-pro/" class="btn btn-outline-brand w-100 rounded-8px">Manage ProfilePro <i class="bi bi-chevron-right ms-1"></i></a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 4: SPLIT VIEW - Build your CV & Generate your CV -->
<section id="cv-builder" class="py-5" style="background-color: #f1f7f5;">
    <div class="container py-4">
        <div class="text-center mb-5">
            <h2 class="section-title">Powerful CV & Career Tools</h2>
            <p class="section-subtitle">Whether you want visual resume template customization or instant ATS parsing, we have you covered.</p>
        </div>

        <div class="row g-4">
            <!-- Left Split: Build your CV -->
            <div class="col-lg-6">
                <div class="split-card split-card-build shadow-sm">
                    <div>
                        <span class="split-badge"><i class="bi bi-tools me-1"></i>Customization Suite</span>
                        <h3 class="fw-bold mb-3">Build & Manage Your Resumes</h3>
                        <p class="text-white-50 mb-4">Create tailored candidate profiles with custom qualifications, education history, and downloadable ATS resume templates.</p>
                        
                        <ul class="list-unstyled mb-4">
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-accent me-2"></i>Professional ATS-Friendly Layouts</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-accent me-2"></i>Integrated Education & Experience Log</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-accent me-2"></i>Direct Job Vacancy Application Sync</li>
                        </ul>
                    </div>
                    <div>
                        <a href="user-profile/" class="btn btn-accent btn-lg w-100 rounded-8px">Manage Profile & CVs <i class="bi bi-pencil-square ms-2"></i></a>
                    </div>
                </div>
            </div>

            <!-- Right Split: Generate your CV -->
            <div class="col-lg-6">
                <div class="split-card split-card-generate shadow-sm">
                    <div>
                        <span class="split-badge"><i class="bi bi-lightning-charge-fill me-1"></i>Instant OCR Engine</span>
                        <h3 class="fw-bold mb-3">Parse & Analyze CV with ATSync</h3>
                        <p class="text-muted mb-4">Upload existing CV documents or national identity images to extract key candidate attributes and check ATS formatting alignment.</p>

                        <ul class="list-unstyled mb-4 text-secondary">
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-dark me-2"></i>Automatic Document Attribute Extraction</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-dark me-2"></i>Real-time NIC Document Verification</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-dark me-2"></i>Fast ATS Compatibility Scoring</li>
                        </ul>
                    </div>
                    <div>
                        <a href="atsync/" class="btn btn-brand btn-lg w-100 rounded-8px">Launch ATSync Engine <i class="bi bi-magic ms-2"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 5: MATCHING SECTION - Why Choose SkillSync -->
<section id="why-us" class="py-5 bg-white">
    <div class="container py-4">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="badge bg-light text-dark border px-3 py-2 rounded-4px mb-3 font-weight-bold">
                    <i class="bi bi-shield-check text-success me-1"></i> Proven Impact
                </span>
                <h2 class="section-title text-start mb-3">Why Candidates & Recruiters Choose SkillSync</h2>
                <p class="text-muted mb-4">We combine data-driven skill diagnostics with recruiter insights to help you stand out from thousands of applicants.</p>

                <div class="d-flex align-items-start mb-3">
                    <div class="bg-dark text-accent rounded-8px p-3 me-3">
                        <i class="bi bi-rocket-takeoff fs-4"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">3x Higher Callback Rates</h6>
                        <p class="text-muted small mb-0">Resumes created with our AI optimizer match real job keywords, bypassing screening algorithms.</p>
                    </div>
                </div>

                <div class="d-flex align-items-start mb-3">
                    <div class="bg-dark text-accent rounded-8px p-3 me-3">
                        <i class="bi bi-briefcase fs-4"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">500+ Partner Companies</h6>
                        <p class="text-muted small mb-0">Direct access to tech leads and hiring managers actively sourcing verified talent profiles.</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card-custom p-4 bg-dark text-white rounded-12px">
                    <h5 class="fw-bold text-accent mb-4"><i class="bi bi-graph-up me-2"></i>SkillSync Impact Metrics</h5>
                    <div class="row text-center g-3">
                        <div class="col-6">
                            <div class="p-3 bg-white bg-opacity-10 rounded-8px">
                                <h3 class="fw-bold text-white mb-0">94%</h3>
                                <small class="text-white-50">Placement Rate</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 bg-white bg-opacity-10 rounded-8px">
                                <h3 class="fw-bold text-accent mb-0">120k+</h3>
                                <small class="text-white-50">CVs Generated</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 bg-white bg-opacity-10 rounded-8px">
                                <h3 class="fw-bold text-accent mb-0">4.9/5</h3>
                                <small class="text-white-50">User Rating</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 bg-white bg-opacity-10 rounded-8px">
                                <h3 class="fw-bold text-white mb-0">15+</h3>
                                <small class="text-white-50">Career Domains</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// Include Footer Component
include_once __DIR__ . '/includes/footer.php';
?>
