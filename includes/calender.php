<?php
// Fetch calendar events for current user from database
$calUserId = $_SESSION['user_id'] ?? null;
if (!$calUserId && isset($pdo)) {
    $stmtDefaultU = $pdo->query("SELECT userid FROM user ORDER BY userid ASC LIMIT 1");
    $calUserId = $stmtDefaultU->fetchColumn() ?: 1;
}

$userCalendarEvents = [];
if (isset($pdo) && $calUserId) {
    try {
        $stmtCal = $pdo->prepare("
            SELECT c.* 
            FROM calendar c
            LEFT JOIN company comp ON c.companyid = comp.companyid
            LEFT JOIN (
                SELECT DISTINCT aj.userid, v.companyid, v.jobTitle, c_sub.accountStatus AS compStatus
                FROM appliedJobs aj
                JOIN vacancy v ON aj.vacancyid = v.vacancyid
                JOIN company c_sub ON v.companyid = c_sub.companyid
            ) app_comp ON c.companyid IS NULL AND c.userid = app_comp.userid AND (c.activityName LIKE CONCAT('%', app_comp.jobTitle, '%'))
            WHERE c.userid = ?
              AND (c.companyid IS NULL OR comp.accountStatus != 'Suspended' OR comp.accountStatus IS NULL)
              AND (app_comp.compStatus IS NULL OR app_comp.compStatus != 'Suspended')
            GROUP BY c.calendarid
            ORDER BY c.activityDate ASC
        ");
        $stmtCal->execute([$calUserId]);
        $userCalendarEvents = $stmtCal->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $userCalendarEvents = [];
    }
}
?>

<!-- Scroll to Top Floating Button (Positioned above Calendar FAB) -->
<a href="#" onclick="scrollToTop(); return false;" class="scroll-top-fab" id="scrollTopFab" title="Scroll to Top">
    <i class="bi bi-arrow-up"></i>
</a>

<!-- Floating Calendar Icon Button (Positioned at bottom right) -->
<div class="calendar-fab" id="calendarFab" title="Open Event Calendar">
    <i class="bi bi-calendar3"></i>
</div>

<!-- Calendar Popup Window -->
<div class="calendar-popup" id="calendarPopup">
    <div class="calendar-popup-header">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-calendar-check fs-5 text-accent"></i>
            <h6 class="mb-0 text-white">Event Calendar</h6>
        </div>
        <button type="button" class="btn-close btn-close-white btn-sm" id="closeCalendarBtn" aria-label="Close"></button>
    </div>
    
    <div class="calendar-popup-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0 text-dark" id="calendarMonthTitle">August 2026</h6>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary rounded-4px py-0 px-2" id="calPrevMonthBtn"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary rounded-4px py-0 px-2" id="calNextMonthBtn"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>

        <!-- Mini Calendar Grid -->
        <table class="mini-calendar">
            <thead>
                <tr>
                    <th>Su</th>
                    <th>Mo</th>
                    <th>Tu</th>
                    <th>We</th>
                    <th>Th</th>
                    <th>Fr</th>
                    <th>Sa</th>
                </tr>
            </thead>
            <tbody id="miniCalendarTbody">
                <!-- Dynamically populated via JS -->
            </tbody>
        </table>

        <!-- Event List for Selected Date -->
        <div class="event-list-mini">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-bold text-dark" style="font-size:0.8rem;" id="selectedDateStr">Select a date</span>
                <span class="badge bg-success rounded-4px" style="font-size:0.65rem;" id="selectedDateCountBadge">0 Events</span>
            </div>
            <div id="eventListContainer">
                <div class="text-muted small py-2 text-center">No events scheduled for this date.</div>
            </div>
        </div>
    </div>
</div>

<!-- ================= CANDIDATE ACCEPT INTERVIEW CONFIRMATION MODAL ================= -->
<div class="modal fade" id="confirmCandidateAcceptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-12px border-0 shadow-lg">
            <div class="modal-header bg-dark text-white rounded-top-12px">
                <h5 class="modal-title fw-bold"><i class="bi bi-question-circle text-accent me-2"></i>Confirm Interview Acceptance</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light text-center">
                <div class="mb-3">
                    <i class="bi bi-check-circle-fill text-success display-4"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Are you sure?</h5>
                <p class="text-muted small mb-0" id="confirmCandidateAcceptMsg">Are you sure you want to accept this interview schedule?</p>
            </div>
            <div class="modal-footer bg-white rounded-bottom-12px justify-content-center gap-2">
                <button type="button" class="btn btn-secondary rounded-4px btn-sm px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success rounded-4px btn-sm font-weight-bold px-4" id="confirmCandidateAcceptProceedBtn"><i class="bi bi-check-circle me-1"></i> Yes, Accept Interview</button>
            </div>
        </div>
    </div>
</div>

<script>
window.userCalendarEvents = <?= json_encode($userCalendarEvents) ?> || [];

document.addEventListener('DOMContentLoaded', function() {
    let currentCalDate = new Date();
    let selectedDateISO = currentCalDate.toISOString().split('T')[0];

    const monthTitle = document.getElementById('calendarMonthTitle');
    const tbody = document.getElementById('miniCalendarTbody');
    const prevBtn = document.getElementById('calPrevMonthBtn');
    const nextBtn = document.getElementById('calNextMonthBtn');
    const selectedDateStrEl = document.getElementById('selectedDateStr');
    const countBadgeEl = document.getElementById('selectedDateCountBadge');
    const eventContainer = document.getElementById('eventListContainer');

    function renderCalendarGrid() {
        if (!tbody || !monthTitle) return;

        const year = currentCalDate.getFullYear();
        const month = currentCalDate.getMonth();

        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        monthTitle.textContent = `${monthNames[month]} ${year}`;

        const firstDayIndex = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        // Collect dates that have events
        const eventDatesMap = {};
        window.userCalendarEvents.forEach(evt => {
            if (evt.activityDate) {
                const dateOnly = evt.activityDate.split(' ')[0];
                if (!eventDatesMap[dateOnly]) eventDatesMap[dateOnly] = [];
                eventDatesMap[dateOnly].push(evt);
            }
        });

        tbody.innerHTML = '';
        let row = document.createElement('tr');

        // Fill preceding empty cells
        for (let i = 0; i < firstDayIndex; i++) {
            const td = document.createElement('td');
            td.className = 'empty';
            row.appendChild(td);
        }

        let dayOfWeek = firstDayIndex;
        for (let day = 1; day <= daysInMonth; day++) {
            if (dayOfWeek === 7) {
                tbody.appendChild(row);
                row = document.createElement('tr');
                dayOfWeek = 0;
            }

            const td = document.createElement('td');
            td.textContent = day;

            const mStr = String(month + 1).padStart(2, '0');
            const dStr = String(day).padStart(2, '0');
            const isoDate = `${year}-${mStr}-${dStr}`;

            if (eventDatesMap[isoDate]) {
                td.classList.add('has-event');
            }

            if (isoDate === selectedDateISO) {
                td.classList.add('active-day');
            }

            td.addEventListener('click', function() {
                const prevActive = tbody.querySelector('.active-day');
                if (prevActive) prevActive.classList.remove('active-day');
                td.classList.add('active-day');
                selectedDateISO = isoDate;
                showEventsForDate(isoDate, eventDatesMap[isoDate] || []);
            });

            row.appendChild(td);
            dayOfWeek++;
        }

        // Fill remaining empty cells
        if (dayOfWeek > 0 && dayOfWeek < 7) {
            for (let i = dayOfWeek; i < 7; i++) {
                const td = document.createElement('td');
                td.className = 'empty';
                row.appendChild(td);
            }
        }
        tbody.appendChild(row);

        // Show events for current selected date
        showEventsForDate(selectedDateISO, eventDatesMap[selectedDateISO] || []);
    }

    function showEventsForDate(isoDate, events) {
        if (!selectedDateStrEl || !eventContainer) return;

        const dObj = new Date(isoDate + 'T00:00:00');
        const options = { month: 'long', day: 'numeric', year: 'numeric' };
        selectedDateStrEl.textContent = dObj.toLocaleDateString('en-US', options);

        if (countBadgeEl) {
            countBadgeEl.textContent = `${events.length} Event${events.length !== 1 ? 's' : ''}`;
        }

        if (events.length === 0) {
            eventContainer.innerHTML = '<div class="text-muted small py-2 text-center">No events scheduled for this date.</div>';
        } else {
            let html = '';
            events.forEach(evt => {
                const title = evt.activityName || 'Scheduled Activity';
                const status = evt.activityStatus || 'Scheduled';
                const isAccepted = (status === 'Accepted' || status === 'Candidate Accepted');

                html += `
                    <div class="event-item mb-2 p-2 rounded-8px border bg-white shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong class="text-dark small">${escapeHtmlCal(title)}</strong>
                            <span class="badge ${isAccepted ? 'bg-success text-white' : 'bg-dark text-accent'} rounded-4px" style="font-size:0.65rem;">${escapeHtmlCal(status)}</span>
                        </div>
                        ${!isAccepted ? `
                            <div class="mt-2 text-end">
                                <button type="button" class="btn btn-sm btn-success rounded-4px py-0 px-2" style="font-size:0.7rem;" onclick="acceptCandidateInterview(${evt.calendarid}, '${escapeHtmlCal(title)}')">
                                    <i class="bi bi-check-circle me-1"></i> Accept Interview
                                </button>
                            </div>
                        ` : `
                            <div class="text-success small mt-1" style="font-size:0.7rem;">
                                <i class="bi bi-check-circle-fill me-1"></i> You accepted this interview
                            </div>
                        `}
                    </div>
                `;
            });
            eventContainer.innerHTML = html;
        }
    }

    function escapeHtmlCal(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            currentCalDate.setMonth(currentCalDate.getMonth() - 1);
            renderCalendarGrid();
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            currentCalDate.setMonth(currentCalDate.getMonth() + 1);
            renderCalendarGrid();
        });
    }

    window.reloadUserCalendarEvents = async function() {
        try {
            const rootPath = '<?= defined("BASE_URL") ? BASE_URL : "/skillsync/" ?>';
            const res = await fetch(rootPath + 'includes/api_calendar.php');
            const data = await res.json();
            if (data.success) {
                window.userCalendarEvents = data.events || [];
                renderCalendarGrid();
            }
        } catch(err) {
            console.error('Error reloading calendar events:', err);
        }
    };

    document.getElementById('calendarFab')?.addEventListener('click', function() {
        window.reloadUserCalendarEvents();
    });

    let pendingCandidateAcceptCalId = null;

    window.acceptCandidateInterview = function(calId, title) {
        pendingCandidateAcceptCalId = calId;
        const msgEl = document.getElementById('confirmCandidateAcceptMsg');
        if (msgEl) {
            msgEl.textContent = `Are you sure you want to accept the interview schedule for "${title}"?`;
        }
        const modal = new bootstrap.Modal(document.getElementById('confirmCandidateAcceptModal'));
        modal.show();
    };

    document.getElementById('confirmCandidateAcceptProceedBtn')?.addEventListener('click', async function() {
        if (!pendingCandidateAcceptCalId) return;

        const modalEl = document.getElementById('confirmCandidateAcceptModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();

        const calId = pendingCandidateAcceptCalId;
        pendingCandidateAcceptCalId = null;

        const rootPath = '<?= defined("BASE_URL") ? BASE_URL : "/skillsync/" ?>';
        const formData = new FormData();
        formData.append('action', 'accept_interview');
        formData.append('calendarid', calId);

        try {
            const res = await fetch(rootPath + 'includes/api_calendar.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                if (window.reloadUserCalendarEvents) {
                    window.reloadUserCalendarEvents();
                }
                showToast(data.message, 'success');
            } else {
                showToast(data.message || 'Failed to accept interview.', 'danger');
            }
        } catch(err) {
            console.error('Error accepting interview:', err);
            showToast('An error occurred while accepting the interview.', 'danger');
        }
    });

    renderCalendarGrid();
});
</script>
