<?php
include_once __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="styles.css">

<div class="container-fluid header-container-padding py-4" style="background-color: #f8faf9; min-height: 85vh;">
    <!-- Page Header Bar -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h1 class="section-title mb-1"><i class="bi bi-search text-brand me-2"></i>Job Scout Explorer</h1>
            <p class="section-subtitle mb-0">Scrape and Explore Live Vacancies Across Sri Lankan Employers</p>
        </div>
    </div>

    <!-- Main Wrapper -->
    <main class="main-wrapper" style="padding: 0;">
            <!-- Sidebar: Categories -->
            <aside class="sidebar">
                <div class="sidebar-header">
                    <h2 class="sidebar-title">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                        Job Categories
                    </h2>
                    <span class="cat-badge" id="category-total-badge">31</span>
                </div>

                <div class="category-list" id="category-list">
                    <!-- Category Items dynamic HTML -->
                    <div class="skeleton" style="height: 40px; margin-bottom: 8px;"></div>
                    <div class="skeleton" style="height: 40px; margin-bottom: 8px;"></div>
                    <div class="skeleton" style="height: 40px; margin-bottom: 8px;"></div>
                    <div class="skeleton" style="height: 40px; margin-bottom: 8px;"></div>
                </div>
            </aside>

            <!-- Main Content -->
            <section class="content-area">
                <!-- Controls & Search Bar -->
                <div class="controls-bar">
                    <div class="search-box">
                        <svg class="search-icon" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" id="search-input" class="search-input" placeholder="Search by Job Title or Company Name...">
                        <button class="clear-search" id="clear-search">&times;</button>
                    </div>
                </div>

                <!-- Category Title & Results Count -->
                <div class="category-header">
                    <h2 class="current-cat-title" id="current-cat-title">
                        <span>All Job Categories</span>
                    </h2>
                    <span class="results-count" id="results-count">Showing 0 jobs</span>
                </div>

                <!-- Job Cards Grid -->
                <div class="jobs-grid" id="jobs-grid">
                    <!-- Skeleton Cards initially -->
                    <div class="skeleton-card">
                        <div class="skeleton" style="height: 20px; width: 40%;"></div>
                        <div class="skeleton" style="height: 24px; width: 85%;"></div>
                        <div class="skeleton" style="height: 18px; width: 60%;"></div>
                    </div>
                    <div class="skeleton-card">
                        <div class="skeleton" style="height: 20px; width: 40%;"></div>
                        <div class="skeleton" style="height: 24px; width: 85%;"></div>
                        <div class="skeleton" style="height: 18px; width: 60%;"></div>
                    </div>
                    <div class="skeleton-card">
                        <div class="skeleton" style="height: 20px; width: 40%;"></div>
                        <div class="skeleton" style="height: 24px; width: 85%;"></div>
                        <div class="skeleton" style="height: 18px; width: 60%;"></div>
                    </div>
                </div>

                <!-- 8 Items Per Page Pagination Controls -->
                <div id="pagination-container" class="mt-4 d-flex justify-content-center"></div>
            </section>
        </main>
    </div>

    <!-- Application Script -->
    <script>
        let allCategories = [];
        let loadedJobsMap = {}; // { 'SDQ': [...], 'ACA': [...] }
        let currentSelectedFA = 'ALL';
        let searchQuery = '';
        let currentPage = 1;
        const itemsPerPage = 8;

        document.addEventListener('DOMContentLoaded', () => {
            initApp();
            setupEventListeners();
        });

        async function initApp() {
            try {
                const response = await fetch('api.php?action=get_categories');
                const data = await response.json();

                if (data.status === 'success') {
                    allCategories = data.categories;
                    renderSidebar();
                    // Load top priority categories or current selection
                    loadAllOrInitialCategories();
                }
            } catch (err) {
                console.error('Failed to initialize scraper:', err);
                showErrorState('Failed to connect to PHP scraper backend.');
            }
        }

        function renderSidebar() {
            const listEl = document.getElementById('category-list');
            document.getElementById('category-total-badge').textContent = allCategories.length;

            let html = `
                <a href="#" class="category-item ${currentSelectedFA === 'ALL' ? 'active' : ''}" data-fa="ALL">
                    <span>All Categories</span>
                    <span class="cat-badge" id="badge-all">0</span>
                </a>
            `;

            allCategories.forEach(cat => {
                const countText = cat.job_count !== null ? cat.job_count : '...';
                html += `
                    <a href="#" class="category-item ${currentSelectedFA === cat.fa ? 'active' : ''}" data-fa="${cat.fa}">
                        <span>${escapeHtml(cat.name)}</span>
                        <span class="cat-badge" id="badge-${cat.fa}">${countText}</span>
                    </a>
                `;
            });

            listEl.innerHTML = html;

            // Add click listeners to sidebar links
            listEl.querySelectorAll('.category-item').forEach(item => {
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    const fa = item.getAttribute('data-fa');
                    selectCategory(fa);
                });
            });
        }

        async function loadAllOrInitialCategories(forceRefresh = false) {
            showSkeletons();
            
            // Fetch initial 5 popular categories in parallel for quick preview, then load others
            const priorityFA = ['SDQ', 'ACA', 'SMM', 'BAF', 'HAT'];
            
            // First load priority categories
            const refreshFlag = forceRefresh ? '&refresh=1' : '';
            
            // Update stats
            updateStatsUI();

            // Load priority categories first for instantaneous rendering
            const promises = priorityFA.map(fa => fetchCategory(fa, forceRefresh));
            await Promise.all(promises);

            // Render current view
            renderJobsGrid();

            // Then fetch remaining categories asynchronously in background
            const remainingFA = allCategories.map(c => c.fa).filter(fa => !priorityFA.includes(fa));
            
            // Load remaining in small batches
            for (let i = 0; i < remainingFA.length; i += 3) {
                const batch = remainingFA.slice(i, i + 3);
                await Promise.all(batch.map(fa => fetchCategory(fa, forceRefresh)));
                if (currentSelectedFA === 'ALL') {
                    renderJobsGrid();
                }
            }

            updateStatsUI();
        }

        async function fetchCategory(fa, forceRefresh = false) {
            try {
                const refreshFlag = forceRefresh ? '&refresh=1' : '';
                const res = await fetch(`api.php?action=scrape_category&fa=${fa}${refreshFlag}`);
                const data = await res.json();
                
                if (data.status === 'success') {
                    loadedJobsMap[fa] = data.jobs || [];
                    
                    // Update sidebar badge count
                    const badge = document.getElementById(`badge-${fa}`);
                    if (badge) {
                        badge.textContent = data.jobs.length;
                    }
                    updateStatsUI();
                }
            } catch (e) {
                console.error(`Error loading category ${fa}:`, e);
            }
        }

        function selectCategory(fa) {
            currentSelectedFA = fa;
            currentPage = 1;
            
            // Update sidebar active class
            document.querySelectorAll('.category-list .category-item').forEach(item => {
                if (item.getAttribute('data-fa') === fa) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });

            // Update title
            const catTitleEl = document.getElementById('current-cat-title');
            if (fa === 'ALL') {
                catTitleEl.innerHTML = `<span>All Job Categories</span>`;
            } else {
                const catObj = allCategories.find(c => c.fa === fa);
                const catName = catObj ? catObj.name : fa;
                catTitleEl.innerHTML = `<span>${escapeHtml(catName)}</span>`;
            }

            // Check if jobs for this category are loaded
            if (fa !== 'ALL' && !loadedJobsMap[fa]) {
                showSkeletons();
                fetchCategory(fa).then(() => {
                    renderJobsGrid();
                });
            } else {
                renderJobsGrid();
            }
        }

        function renderJobsGrid() {
            const gridEl = document.getElementById('jobs-grid');
            const resultsCountEl = document.getElementById('results-count');
            const paginationEl = document.getElementById('pagination-container');

            let jobsToDisplay = [];

            if (currentSelectedFA === 'ALL') {
                Object.keys(loadedJobsMap).forEach(fa => {
                    const catObj = allCategories.find(c => c.fa === fa);
                    const catName = catObj ? catObj.name : fa;
                    loadedJobsMap[fa].forEach(job => {
                        jobsToDisplay.push({
                            ...job,
                            categoryFA: fa,
                            categoryName: catName
                        });
                    });
                });
            } else {
                const catObj = allCategories.find(c => c.fa === currentSelectedFA);
                const catName = catObj ? catObj.name : currentSelectedFA;
                const jobs = loadedJobsMap[currentSelectedFA] || [];
                jobsToDisplay = jobs.map(job => ({
                    ...job,
                    categoryFA: currentSelectedFA,
                    categoryName: catName
                }));
            }

            // Apply search query filter
            if (searchQuery.trim() !== '') {
                const q = searchQuery.toLowerCase().trim();
                jobsToDisplay = jobsToDisplay.filter(j => 
                    j.title.toLowerCase().includes(q) || 
                    j.company.toLowerCase().includes(q) ||
                    j.categoryName.toLowerCase().includes(q)
                );
            }

            const totalItems = jobsToDisplay.length;
            const totalPages = Math.ceil(totalItems / itemsPerPage) || 1;

            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            if (totalItems === 0) {
                resultsCountEl.textContent = `Showing 0 jobs`;
                gridEl.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">🔍</div>
                        <h3>No Jobs Found</h3>
                        <p>${searchQuery ? 'No vacancies matching your search query. Try another keyword.' : 'Loading or no active job listings available in this category.'}</p>
                    </div>
                `;
                if (paginationEl) paginationEl.innerHTML = '';
                return;
            }

            const startIndex = (currentPage - 1) * itemsPerPage;
            const pageJobs = jobsToDisplay.slice(startIndex, startIndex + itemsPerPage);

            resultsCountEl.textContent = `Showing ${startIndex + 1}-${Math.min(startIndex + itemsPerPage, totalItems)} of ${totalItems} jobs (Page ${currentPage} of ${totalPages})`;

            let html = '';
            pageJobs.forEach(job => {
                html += `
                    <a href="${escapeHtml(job.url)}" target="_blank" rel="noopener noreferrer" class="job-card">
                        <div class="job-meta-top">
                            <span class="job-category-tag">${escapeHtml(job.categoryName)}</span>
                            <svg class="external-link-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </div>
                        <div class="job-main-info">
                            <h3 class="job-title">${escapeHtml(job.title)}</h3>
                            <div class="company-name">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5"/></svg>
                                <span>${escapeHtml(job.company)}</span>
                            </div>
                        </div>
                        <div class="job-card-footer">
                            <span>View Vacancy Advert</span>
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </div>
                    </a>
                `;
            });

            gridEl.innerHTML = html;
            renderPaginationUI(paginationEl, totalPages);
        }

        function renderPaginationUI(container, totalPages) {
            if (!container) return;
            if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }

            let pagHtml = `<nav aria-label="Job Scout pagination"><ul class="pagination justify-content-center mb-0 gap-1">`;

            pagHtml += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <button class="page-link rounded-4px" ${currentPage === 1 ? 'disabled' : ''} onclick="goToPage(${currentPage - 1})" aria-label="Previous Page">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                </button>
            </li>`;

            const maxVisiblePages = 5;
            let startPage = Math.max(1, currentPage - 2);
            let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

            if (endPage - startPage < maxVisiblePages - 1) {
                startPage = Math.max(1, endPage - maxVisiblePages + 1);
            }

            for (let p = startPage; p <= endPage; p++) {
                pagHtml += `<li class="page-item ${p === currentPage ? 'active' : ''}">
                    <button class="page-link rounded-4px" onclick="goToPage(${p})">${p}</button>
                </li>`;
            }

            pagHtml += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                <button class="page-link rounded-4px" ${currentPage === totalPages ? 'disabled' : ''} onclick="goToPage(${currentPage + 1})" aria-label="Next Page">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                </button>
            </li>`;

            pagHtml += `</ul></nav>`;
            container.innerHTML = pagHtml;
        }

        function goToPage(page) {
            currentPage = page;
            renderJobsGrid();
            document.getElementById('jobs-grid').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function updateStatsUI() {
            let totalJobs = 0;
            const scrapedCount = Object.keys(loadedJobsMap).length;

            Object.values(loadedJobsMap).forEach(arr => {
                totalJobs += arr.length;
            });

            const elTotal = document.getElementById('stat-total-jobs');
            if (elTotal) elTotal.textContent = totalJobs.toLocaleString();

            const elCats = document.getElementById('stat-scraped-cats');
            if (elCats) elCats.textContent = `${scrapedCount} / ${allCategories.length}`;
            
            const badgeAll = document.getElementById('badge-all');
            if (badgeAll) badgeAll.textContent = totalJobs;
        }

        function showSkeletons() {
            const gridEl = document.getElementById('jobs-grid');
            let html = '';
            for (let i = 0; i < 6; i++) {
                html += `
                    <div class="skeleton-card">
                        <div class="skeleton" style="height: 20px; width: 40%;"></div>
                        <div class="skeleton" style="height: 24px; width: 85%;"></div>
                        <div class="skeleton" style="height: 18px; width: 60%;"></div>
                    </div>
                `;
            }
            gridEl.innerHTML = html;
        }

        function setupEventListeners() {
            // Search Input
            const searchInput = document.getElementById('search-input');
            const clearSearchBtn = document.getElementById('clear-search');

            searchInput.addEventListener('input', (e) => {
                searchQuery = e.target.value;
                currentPage = 1;
                clearSearchBtn.style.display = searchQuery ? 'block' : 'none';
                renderJobsGrid();
            });

            clearSearchBtn.addEventListener('click', () => {
                searchInput.value = '';
                searchQuery = '';
                currentPage = 1;
                clearSearchBtn.style.display = 'none';
                renderJobsGrid();
            });
        }

        function exportJSON() {
            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(loadedJobsMap, null, 2));
            const downloadAnchor = document.createElement('a');
            downloadAnchor.setAttribute("href", dataStr);
            downloadAnchor.setAttribute("download", `topjobs_scraped_${new Date().toISOString().slice(0,10)}.json`);
            document.body.appendChild(downloadAnchor);
            downloadAnchor.click();
            downloadAnchor.remove();
        }

        function exportCSV() {
            let csvContent = "data:text/csv;charset=utf-8,Category,Job Title,Company Name,Vacancy URL\n";
            Object.keys(loadedJobsMap).forEach(fa => {
                const catObj = allCategories.find(c => c.fa === fa);
                const catName = catObj ? catObj.name : fa;
                loadedJobsMap[fa].forEach(job => {
                    const row = [
                        `"${catName.replace(/"/g, '""')}"`,
                        `"${job.title.replace(/"/g, '""')}"`,
                        `"${job.company.replace(/"/g, '""')}"`,
                        `"${job.url}"`
                    ].join(",");
                    csvContent += row + "\n";
                });
            });
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", `topjobs_scraped_${new Date().toISOString().slice(0,10)}.csv`);
            document.body.appendChild(link);
            link.click();
            link.remove();
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
    <style>
        @keyframes spin {
            100% { transform: rotate(360deg); }
        }
    </style>
</div>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
