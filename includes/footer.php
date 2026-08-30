<?php
// Include Calendar Component
include_once __DIR__ . '/calender.php';
if (!isset($base_url)) {
    $current_script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $base_url = (strpos($current_script, '/skillsync/') !== false) ? '/skillsync/' : '/';
}
?>

<footer class="footer-skillsync">
    <div class="container">
        <div class="row g-4 mb-5">
            <!-- Col 1: Brand Info -->
            <div class="col-lg-4 col-md-6">
                <a class="navbar-brand d-inline-flex align-items-center mb-3 p-2 bg-white rounded-8px" href="<?=$base_url?>">
                    <img src="<?=$base_url?>assets/img/logo.webp" alt="SkillSync Logo" height="42">
                </a>
                <p class="text-white-50 small mb-4">
                    SkillSync is your all-in-one AI career hub. Match with top career paths, build recruiter-ready resumes, and schedule 1-on-1 expert mentorship sessions seamlessly.
                </p>
                <div class="d-flex gap-2">
                    <a href="#" class="social-btn"><i class="bi bi-linkedin"></i></a>
                    <a href="#" class="social-btn"><i class="bi bi-github"></i></a>
                    <a href="#" class="social-btn"><i class="bi bi-twitter-x"></i></a>
                    <a href="#" class="social-btn"><i class="bi bi-youtube"></i></a>
                </div>
            </div>

            <!-- Col 2: Platform Links -->
            <div class="col-lg-2 col-md-6">
                <h6 class="text-white fw-bold mb-3">Platform</h6>
                <a href="<?=$base_url?>" class="footer-link">Home</a>
                <a href="<?=$base_url?>career-path/" class="footer-link">Career Paths</a>
                <a href="<?=$base_url?>job-scout/" class="footer-link">Job Scout</a>
                <a href="<?=$base_url?>atsync/" class="footer-link">ATSync CV</a>
                <a href="<?=$base_url?>Intervia/" class="footer-link">Intervia Bot</a>
                <a href="<?=$base_url?>profile-pro/" class="footer-link">ProfilePro</a>
            </div>

            <!-- Col 3: Career Resources -->
            <div class="col-lg-3 col-md-6">
                <h6 class="text-white fw-bold mb-3">Career Resources</h6>
                <a href="<?=$base_url?>profile-pro/" class="footer-link">AI Resume Generator</a>
                <a href="<?=$base_url?>Intervia/" class="footer-link">Interview Practice Hub</a>
                <a href="<?=$base_url?>atsync/" class="footer-link">ATS Resume Scanner</a>
                <a href="<?=$base_url?>job-scout/" class="footer-link">Job Scout Scraper</a>
                <a href="<?=$base_url?>user-profile/" class="footer-link">User Profile Hub</a>
            </div>

            <!-- Col 4: Contact Us -->
            <div class="col-lg-3 col-md-6">
                <h6 class="text-white fw-bold mb-3">Contact Us</h6>
                <div class="small text-white-50 d-flex flex-column gap-2">
                    <div>
                        <i class="bi bi-geo-alt-fill text-accent me-2"></i>
                        <span>SkillSync HQ, Level 8, One Galle Face Tower, Colombo 02, Sri Lanka</span>
                    </div>
                    <div>
                        <i class="bi bi-envelope-fill text-accent me-2"></i>
                        <a href="mailto:support@skillsync.lk" class="text-white-50 text-decoration-none">support@skillsync.lk</a>
                    </div>
                    <div>
                        <i class="bi bi-telephone-fill text-accent me-2"></i>
                        <span>+94 11 234 5678 / +94 77 987 6543</span>
                    </div>
                    <div>
                        <i class="bi bi-clock-fill text-accent me-2"></i>
                        <span>Mon - Fri: 8:30 AM - 5:30 PM</span>
                    </div>
                </div>
            </div>
        </div>

        <hr class="border-secondary my-4">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center small text-white-50">
            <p class="mb-2 mb-md-0">&copy; 2026 SkillSync. All rights reserved. Designed for Excellence.</p>
            <div class="d-flex gap-3">
                <a href="#" class="text-white-50 text-decoration-none">Privacy Policy</a>
                <a href="#" class="text-white-50 text-decoration-none">Terms of Service</a>
                <a href="#" class="text-white-50 text-decoration-none">Security</a>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JS -->
<script src="<?=$base_url?>assets/js/main.js"></script>
</body>
</html>
