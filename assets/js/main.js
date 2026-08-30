// SkillSync JavaScript Application Logic

document.addEventListener('DOMContentLoaded', function() {
    // 1. Calendar FAB & Popup Toggle Logic
    const calendarFab = document.getElementById('calendarFab');
    const calendarPopup = document.getElementById('calendarPopup');
    const closeCalendarBtn = document.getElementById('closeCalendarBtn');

    if (calendarFab && calendarPopup) {
        calendarFab.addEventListener('click', function(e) {
            e.stopPropagation();
            if (calendarPopup.style.display === 'block') {
                calendarPopup.style.display = 'none';
            } else {
                calendarPopup.style.display = 'block';
            }
        });

        if (closeCalendarBtn) {
            closeCalendarBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                calendarPopup.style.display = 'none';
            });
        }

        // Close calendar popup on click outside
        document.addEventListener('click', function(e) {
            if (calendarPopup.style.display === 'block' && 
                !calendarPopup.contains(e.target) && 
                !calendarFab.contains(e.target)) {
                calendarPopup.style.display = 'none';
            }
        });
    }

    // 2. Interactive Calendar Date Selection & Event Updates
    const calendarDays = document.querySelectorAll('.mini-calendar td:not(.empty)');
    const selectedDateSpan = document.getElementById('selectedDateStr');
    const eventListContainer = document.getElementById('eventListContainer');

    const sampleEvents = {
        '12': [
            { title: 'Tech Interview Workshop', time: '10:00 AM', tag: 'Live Workshop' },
            { title: 'CV Review Session', time: '02:30 PM', tag: '1-on-1' }
        ],
        '18': [
            { title: 'Data Science Hackathon', time: '09:00 AM', tag: 'Competition' }
        ],
        '24': [
            { title: 'Career Paths Webinar', time: '04:00 PM', tag: 'Webinar' },
            { title: 'Portfolio Feedback', time: '06:00 PM', tag: 'Mentorship' }
        ]
    };

    calendarDays.forEach(dayCell => {
        dayCell.addEventListener('click', function() {
            calendarDays.forEach(d => d.classList.remove('active-day'));
            this.classList.add('active-day');

            const dayNum = this.innerText.trim();
            if (selectedDateSpan) {
                selectedDateSpan.innerText = `August ${dayNum}, 2026`;
            }

            if (eventListContainer) {
                if (sampleEvents[dayNum]) {
                    let html = '';
                    sampleEvents[dayNum].forEach(ev => {
                        html += `
                            <div class="event-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong class="text-dark">${ev.title}</strong>
                                    <span class="badge bg-dark text-accent rounded-4px" style="font-size:0.65rem;">${ev.tag}</span>
                                </div>
                                <div class="text-muted small mt-1"><i class="bi bi-clock me-1"></i>${ev.time}</div>
                            </div>
                        `;
                    });
                    eventListContainer.innerHTML = html;
                } else {
                    eventListContainer.innerHTML = `
                        <div class="text-muted text-center py-2 small">
                            <i class="bi bi-calendar-x d-block fs-5 mb-1"></i>
                            No scheduled events for this day.
                        </div>
                    `;
                }
            }
        });
    });

    // 3. Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId && targetId !== '#') {
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    e.preventDefault();
                    targetElement.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            }
        });
    });

    // 4. Scroll to Top FAB Visibility & Event Listener
    const scrollTopFab = document.getElementById('scrollTopFab') || document.getElementById('scrollTopBtn');
    if (scrollTopFab) {
        scrollTopFab.addEventListener('click', function(e) {
            e.preventDefault();
            scrollToTop();
        });

        window.addEventListener('scroll', function() {
            if (window.scrollY > 250) {
                scrollTopFab.style.opacity = '1';
                scrollTopFab.style.visibility = 'visible';
                scrollTopFab.style.transform = 'translateY(0)';
            } else {
                scrollTopFab.style.opacity = '0';
                scrollTopFab.style.visibility = 'hidden';
                scrollTopFab.style.transform = 'translateY(10px)';
            }
        });
    }
});

/**
 * Global Scroll to Top Function
 */
function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

