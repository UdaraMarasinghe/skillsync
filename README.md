# SkillSync — AI-Powered Career Ecosystem & Talent Intelligence Platform

[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![MediaPipe](https://img.shields.io/badge/MediaPipe-Vision%20%26%20Pose-00A67E?logo=google&logoColor=white)](https://developers.google.com/mediapipe)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

---

## 📌 Overview

**SkillSync** is a comprehensive, AI-driven career development and talent recruitment platform designed to bridge the gap between job seekers and hiring companies. It empowers candidates with intelligent career path exploration, automated ATS-friendly resume creation, interactive AI mock interviews with computer vision behavior tracking, personalized portfolio building, and targeted job discovery.

For companies and administrators, SkillSync provides end-to-end recruitment management, candidate talent analytics, vacancy pipelines, and role-based operational dashboards.

---

## 🚀 Key Modules & Features

### 1. 🎯 Career Path (`career-path/`)
- **Interactive Roadmap Visualizer**: Explore tier-based career trajectories (Junior, Mid, Senior, Lead).
- **Skill Gap Diagnostics**: Identify technical & soft skill deficiencies matched against industry standards.
- **Vacancy Alignment**: Connect career goals directly with active company listings and industry pathways.

### 2. 🤖 Intervia — AI Mock Interview & Vision Tracker (`Intervia/`)
- **Dynamic AI Simulation**: Voice and text-driven technical & behavioral interview assessments.
- **Real-Time Video Analytics**: Powered by **MediaPipe (Face Mesh & Pose)** for posture, gaze tracking, eye contact analysis, and head tilt alerts during interview sessions.
- **Instant Performance Scoring & Feedback**: Detailed metrics on relevance, confidence, and keyword matching with comprehensive review summaries.

### 3. 📄 ATSync — ATS Resume Builder & Analyzer (`atsync/`)
- **Automated Resume Generation**: Create high-scoring, ATS-optimized resumes and CV templates.
- **Keyword & Job Match Scoring**: Benchmark uploaded or generated resumes against real vacancy requirements.
- **Instant Export**: Export clean, parsed PDFs styled for modern Applicant Tracking Systems.

### 4. 💼 Job Scout (`job-scout/`)
- **Smart Opportunity Engine**: Search and filter jobs across industries, salary ranges, seniority, and locations.
- **Direct Application Portal**: One-click application submissions with attached ATSync resumes and candidate portfolios.

### 5. 🌐 Profile Pro — Public Portfolio Generator (`profile-pro/` & `user-profile/`)
- **Dynamic Candidate Showcases**: Standout digital profiles with customizable themes and verified skills.
- **Project & Certification Spotlights**: Showcase projects, credentials, and achievements with unique public URLs.

### 6. 🏢 Company Dashboard (`company/`)
- **Vacancy & Job Posting Lifecycle**: Create, edit, feature, and archive job vacancies.
- **Applicant Tracking & Pipeline**: Review applicant resumes, interview scores, and status updates (Applied, Shortlisted, Interviewing, Hired, Rejected).
- **Candidate Analytics**: Search verified talent pools with filtered technical skills.

### 7. 🛡️ Administrative Portal (`admin/`)
- **User & Company Moderation**: Verify new business registrations, monitor user accounts, and manage suspension states.
- **Activity & System Logs**: Centralized logging for authentication events, role transitions, and platform operations.
- **Platform Metrics & Reporting**: Live metrics on system usage, active vacancies, and interview completion rates.

---

## 🛠️ Technology Stack

| Category | Technology |
|---|---|
| **Backend** | PHP 8.x (Object-Oriented & Procedural with PDO) |
| **Database** | MySQL / MariaDB |
| **Frontend** | HTML5, Vanilla JavaScript (ES6+), CSS3 |
| **UI Frameworks** | Bootstrap 5, Bootstrap Icons, FontAwesome |
| **Computer Vision / AI** | MediaPipe (Face Mesh, Pose), JavaScript Web Audio / Speech APIs |
| **Environment / Server** | Apache (XAMPP / WAMP / LAMP) |

---

## 📂 Project Structure

```plaintext
skillsync/
├── Intervia/              # AI Mock Interview Engine & MediaPipe Tracker
├── admin/                 # Administrator portal and oversight dashboard
├── assets/                # Global CSS, JS, vendor libs, fonts, and brand assets
├── atsync/                # ATS resume generator, analyzer, and PDF tools
├── career-path/           # Skill roadmaps and industry gap diagnostics
├── company/               # Corporate recruitment portal & applicant pipeline
├── config/                # Database configuration & global constants (db.php, config.php)
├── includes/              # Shared layouts (header, footer, navigation, activity logger)
├── job-scout/             # Job vacancy discovery and search engine
├── login/                 # User & company authentication routines
├── profile-pro/           # Public portfolio creator & templates
├── registration/          # Multi-role account onboarding
├── uploads/               # Stored resumes, avatars, company logos, and assets
├── user-profile/          # Candidate settings & profile management
└── index.php              # Homepage & portal landing
```

---

## ⚙️ Installation & Setup

### Prerequisites
- [XAMPP](https://www.apachefriends.org/) (or any Apache + PHP 8.x + MySQL server environment)
- Web Browser with WebRTC & Camera permissions enabled (Chrome / Edge recommended for MediaPipe)

### Step-by-Step Instructions

1. **Clone the Repository**
   ```bash
   git clone https://github.com/your-username/skillsync.git
   ```
   *Place the folder inside your web server directory (e.g., `C:/xampp/htdocs/skillsync`).*

2. **Configure the Database**
   - Start Apache and MySQL from your XAMPP Control Panel.
   - Open phpMyAdmin (`http://localhost/phpmyadmin/`).
   - Create a new database named `skillsync_db` (or import your `.sql` schema file).
   - Verify connection credentials in [`config/db.php`](file:///c:/xampp/htdocs/skillsync/config/db.php):
     ```php
     $host = 'localhost';
     $db   = 'skillsync_db';
     $user = 'root';
     $pass = '';
     ```

3. **Configure Platform URL**
   - Adjust `HTTP_PATH` in [`config/config.php`](file:///c:/xampp/htdocs/skillsync/config/config.php) if running on a custom port or domain:
     ```php
     define('HTTP_PATH', 'http://localhost/skillsync/');
     ```

4. **Launch Application**
   - Open your browser and navigate to:
     ```text
     http://localhost/skillsync/
     ```

---

## 🔒 Security & Best Practices

- **Prepared Statements**: Robust SQL injection prevention across all data interactions using PDO.
- **Session Protection**: Role-based access control (Admin, Candidate, Company) with active session guards.
- **Input Sanitization**: XSS defense via `htmlspecialchars` output encoding.
- **Media Permissions**: Browser-level client-side camera processing ensuring video streams remain private on the user's device without unauthorized server transmission.

---

## 👥 Contributing

Contributions, issues, and feature requests are welcome!
1. Fork the repository
2. Create your branch (`git checkout -b feature/NewFeature`)
3. Commit changes (`git commit -m 'Add NewFeature'`)
4. Push to branch (`git push origin feature/NewFeature`)
5. Open a Pull Request

---

## 📄 License

This project is licensed under the [MIT License](LICENSE).
