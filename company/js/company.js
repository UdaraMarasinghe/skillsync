/**
 * SkillSync Company Dashboard Client-Side Interactive Engine
 */

function confirmCompanyProfileSave() {
  const profileForm = document.getElementById('companyProfileForm');
  if (profileForm && typeof validateFormFields === 'function') {
    if (!validateFormFields(profileForm)) return;
  }
  const modal = new bootstrap.Modal(document.getElementById('confirmCompanyProfileModal'));
  modal.show();
}

function openCompanyActivityHistoryModal() {
  const modalEl = document.getElementById('companyActivityHistoryModal');
  if (modalEl) {
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  }
}

document.addEventListener('DOMContentLoaded', () => {
  initDashboardEvents();

  const profileProceedBtn = document.getElementById('confirmCompanyProfileProceedBtn');
  if (profileProceedBtn) {
    profileProceedBtn.addEventListener('click', () => {
      const modalEl = document.getElementById('confirmCompanyProfileModal');
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();

      const profileForm = document.getElementById('companyProfileForm');
      if (profileForm) {
        profileForm.submit();
      }
    });
  }
});

function initDashboardEvents() {
  // Post Vacancy Form Submission handled via PHP POST in index.php

  // Schedule Interview Form Submission
  const scheduleForm = document.getElementById('scheduleInterviewForm');
  if (scheduleForm) {
    scheduleForm.addEventListener('submit', (e) => {
      e.preventDefault();
      handleScheduleInterview();
    });
  }
}

/**
 * Handle Post Vacancy Form Action
 */
function handlePostVacancy() {
  const title = document.getElementById('vacTitle').value.trim();
  const location = document.getElementById('vacLocation').value.trim();
  const salary = document.getElementById('vacSalary').value.trim();
  const deadline = document.getElementById('vacDeadline').value;
  const desc = document.getElementById('vacDesc').value.trim();

  if (!title || !location || !salary || !deadline) {
    showToast('Please fill in all required vacancy fields.', 'warning');
    return;
  }

  // Append new row to Vacancies Table
  const tableBody = document.getElementById('vacanciesTableBody');
  if (tableBody) {
    const tr = document.createElement('tr');
    const newId = Math.floor(Math.random() * 900) + 100;
    tr.id = `vacancy-row-${newId}`;
    tr.innerHTML = `
      <td class="fw-bold text-dark">${escapeHtml(title)}</td>
      <td><i class="bi bi-geo-alt me-1 text-brand"></i>${escapeHtml(location)}</td>
      <td><span class="badge bg-light text-dark border">${escapeHtml(salary)}</span></td>
      <td>${escapeHtml(deadline)}</td>
      <td><span class="badge badge-status badge-accepted">Open</span></td>
      <td><span class="badge bg-dark text-accent">0 Applicants</span></td>
      <td>
        <button class="btn btn-sm btn-outline-danger rounded-4px" onclick="closeVacancy('${newId}')">Close</button>
      </td>
    `;
    tableBody.prepend(tr);
  }

  // Reset form & Toast
  document.getElementById('postVacancyForm').reset();
  showToast(`Vacancy "${title}" created successfully!`, 'success');

  // Update active metric badge
  const activeCountEl = document.getElementById('metric-active-vacancies');
  if (activeCountEl) {
    let current = parseInt(activeCountEl.innerText) || 0;
    activeCountEl.innerText = current + 1;
  }
}

let pendingApplicantAction = null;

/**
 * Prompt Confirmation for Applicant Action (Accept / Reject)
 */
function requestApplicantStatus(appId, status, candidateName, position) {
  pendingApplicantAction = { appId, status, candidateName, position };
  
  const titleEl = document.getElementById('confirmApplicantActionTitle');
  const msgEl = document.getElementById('confirmApplicantActionMessage');
  const btnEl = document.getElementById('confirmApplicantActionProceedBtn');

  if (status === 'Accepted') {
    if (titleEl) titleEl.innerText = 'Accept Application?';
    if (msgEl) msgEl.innerText = `Are you sure you want to accept ${candidateName}'s application? You will be directed to schedule an interview.`;
    if (btnEl) btnEl.className = 'btn btn-brand rounded-4px btn-sm font-weight-bold px-4';
  } else if (status === 'Rejected') {
    if (titleEl) titleEl.innerText = 'Reject Application?';
    if (msgEl) msgEl.innerText = `Are you sure you want to reject ${candidateName}'s application? Once rejected, no further actions can be taken.`;
    if (btnEl) btnEl.className = 'btn btn-danger rounded-4px btn-sm font-weight-bold px-4';
  }

  const modal = new bootstrap.Modal(document.getElementById('confirmApplicantActionModal'));
  modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
  const proceedBtn = document.getElementById('confirmApplicantActionProceedBtn');
  if (proceedBtn) {
    proceedBtn.addEventListener('click', async function() {
      if (!pendingApplicantAction) return;

      const modalEl = document.getElementById('confirmApplicantActionModal');
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();

      const { appId, status, candidateName, position } = pendingApplicantAction;
      pendingApplicantAction = null;

      await updateApplicantStatus(appId, status, candidateName, position);
    });
  }
});

/**
 * Update Application Status (Accept / Reject)
 */
async function updateApplicantStatus(appId, status, candidateName, position) {
  const badgeEl = document.getElementById(`status-badge-${appId}`);
  const quickBadgeEl = document.getElementById(`status-badge-quick-${appId}`);
  const actionsEl = document.getElementById(`actions-${appId}`);
  const quickActionsEl = document.getElementById(`quick-actions-${appId}`);

  const formData = new FormData();
  formData.append('action', 'update_app_status');
  formData.append('applicationid', appId);
  formData.append('status', status);

  try {
    const res = await fetch('./', { method: 'POST', body: formData });
    const result = await res.json();

    if (result.success) {
      if (status === 'Accepted') {
        const updateBadge = (el) => {
          if (!el) return;
          el.className = 'badge badge-status badge-accepted';
          el.innerHTML = '<i class="bi bi-check-circle-fill"></i> Accepted';
        };
        updateBadge(badgeEl);
        updateBadge(quickBadgeEl);

        showToast(`Applicant status set to ACCEPTED. Directing to interview schedule...`, 'success');

        // Redirect immediately to interview scheduling modal
        setTimeout(() => {
          openScheduleModal(appId, candidateName || 'Candidate', position || 'Position');
        }, 400);

      } else if (status === 'Rejected') {
        const updateBadge = (el) => {
          if (!el) return;
          el.className = 'badge badge-status badge-rejected';
          el.innerHTML = '<i class="bi bi-x-circle-fill"></i> Rejected';
        };
        updateBadge(badgeEl);
        updateBadge(quickBadgeEl);

        // Lock out actions completely for this rejected candidate
        const disableActions = (container) => {
          if (container) {
            container.innerHTML = '<span class="badge bg-secondary p-2 text-white"><i class="bi bi-slash-circle me-1"></i> Rejected (No Actions)</span>';
          }
        };
        disableActions(actionsEl);
        disableActions(quickActionsEl);

        if (window.reloadUserCalendarEvents) window.reloadUserCalendarEvents();
        showToast(`Applicant status set to REJECTED. Removed from candidate calendar.`, 'danger');
      }
    } else {
      showToast(result.message || 'Failed to update application status.', 'warning');
    }
  } catch (err) {
    console.error('Error updating status:', err);
    showToast('Failed to update status on server.', 'danger');
  }
}

/**
 * Open Schedule Interview Modal for a candidate
 */
function openScheduleModal(appId, candidateName, position) {
  document.getElementById('modalApplicantId').value = appId;
  document.getElementById('modalCandidateName').value = candidateName;
  document.getElementById('modalPosition').value = position;
  
  const titleInput = document.getElementById('interviewTitle');
  if (titleInput) {
    titleInput.value = position ? `${position} Interview` : 'Job Interview';
  }

  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  document.getElementById('interviewDate').value = tomorrow.toISOString().split('T')[0];
  document.getElementById('interviewTime').value = '10:00';

  const modal = new bootstrap.Modal(document.getElementById('scheduleInterviewModal'));
  modal.show();
}

/**
 * Handle Schedule Interview Action
 */
async function handleScheduleInterview() {
  const appId = document.getElementById('modalApplicantId').value;
  const candidateName = document.getElementById('modalCandidateName').value;
  const date = document.getElementById('interviewDate').value;
  const time = document.getElementById('interviewTime').value;
  const title = document.getElementById('interviewTitle').value;

  if (!date || !time || !title) {
    showToast('Please specify date, time, and interview title.', 'warning');
    return;
  }

  // Rule #2 confirmation dialog
  if (!confirm(`Are you sure you want to schedule this interview for ${candidateName} on ${date} at ${time} and sync to candidate calendar?`)) {
    return;
  }

  const formData = new FormData();
  formData.append('action', 'schedule_interview');
  formData.append('applicationid', appId);
  formData.append('activityName', title);
  formData.append('activityDate', date);
  formData.append('activityTime', time);

  try {
    const res = await fetch('./', { method: 'POST', body: formData });
    const result = await res.json();

    if (result.success) {
      // Update Candidate Status Badges
      const updateBadge = (el) => {
        if (!el) return;
        el.className = 'badge badge-status badge-scheduled';
        el.innerHTML = `<i class="bi bi-calendar-event-fill"></i> Scheduled (${date})`;
      };
      updateBadge(document.getElementById(`status-badge-${appId}`));
      updateBadge(document.getElementById(`status-badge-quick-${appId}`));

      // Append to Scheduled Interviews Table
      const scheduleTable = document.getElementById('scheduledInterviewsBody');
      if (scheduleTable) {
        // Clear empty state message row if present
        const emptyRow = scheduleTable.querySelector('td[colspan="4"]');
        if (emptyRow) scheduleTable.innerHTML = '';

        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td class="fw-bold text-dark">${escapeHtml(candidateName)}</td>
          <td>${escapeHtml(title)}</td>
          <td><span class="badge bg-dark text-accent">${escapeHtml(date)} at ${escapeHtml(time)}</span></td>
          <td><span class="badge badge-status badge-scheduled"><i class="bi bi-check2-circle me-1"></i> Synced to Calendar</span></td>
        `;
        scheduleTable.prepend(tr);
      }

      // Close Modal
      const modalEl = document.getElementById('scheduleInterviewModal');
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();

      if (window.reloadUserCalendarEvents) window.reloadUserCalendarEvents();
      showToast(`Interview scheduled for ${candidateName} on ${date} at ${time}. Added to user's calendar!`, 'success');
    } else {
      showToast(result.message || 'Failed to schedule interview.', 'warning');
    }
  } catch (err) {
    console.error('Error scheduling interview:', err);
    showToast('An error occurred while saving interview schedule.', 'danger');
  }
}

/**
 * Open CV Preview Modal
 */
function viewCandidateCV(candidateName, position, skills, resumeUrl) {
  document.getElementById('cvModalName').innerText = candidateName;
  document.getElementById('cvModalPosition').innerText = position;
  document.getElementById('cvModalSkills').innerText = skills || 'General Profile Qualifications';

  const iframeFrame = document.getElementById('cvModalIframeFrame');
  const downloadBtn = document.getElementById('cvModalDownloadBtn');

  if (resumeUrl) {
    if (iframeFrame) {
      iframeFrame.src = resumeUrl;
      iframeFrame.style.display = 'block';
    }
    if (downloadBtn) {
      downloadBtn.href = resumeUrl;
      downloadBtn.style.display = 'inline-block';
    }
  } else {
    if (iframeFrame) iframeFrame.style.display = 'none';
    if (downloadBtn) downloadBtn.style.display = 'none';
  }

  const modal = new bootstrap.Modal(document.getElementById('viewCVModal'));
  modal.show();
}

/**
 * Close / Deactivate Vacancy
 */
function closeVacancy(vacId) {
  const row = document.getElementById(`vacancy-row-${vacId}`);
  if (row) {
    const badge = row.querySelector('.badge-accepted');
    if (badge) {
      badge.className = 'badge badge-status badge-rejected';
      badge.innerText = 'Closed';
    }
    showToast('Vacancy status updated to Closed.', 'info');
  }
}

/**
 * Custom Toast Notifications
 */
function showToast(message, type = 'info') {
  let toastContainer = document.getElementById('toastContainer');
  if (!toastContainer) {
    toastContainer = document.createElement('div');
    toastContainer.id = 'toastContainer';
    toastContainer.style.cssText = 'position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 10px;';
    document.body.appendChild(toastContainer);
  }

  const toast = document.createElement('div');
  const bgClass = type === 'success' ? '#004743' : type === 'danger' ? '#dc2626' : type === 'warning' ? '#d97706' : '#004743';
  const textClass = type === 'success' ? '#ACFF78' : '#ffffff';

  toast.style.cssText = `background-color: ${bgClass}; color: ${textClass}; padding: 14px 20px; border-radius: 8px; font-weight: 700; font-size: 0.9rem; box-shadow: 0 6px 18px rgba(0,0,0,0.25); opacity: 0; transform: translateY(20px); transition: all 0.3s ease; display: flex; align-items: center; gap: 10px;`;
  toast.innerHTML = `<i class="bi bi-info-circle-fill"></i> ${escapeHtml(message)}`;

  toastContainer.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '1';
    toast.style.transform = 'translateY(0)';
  }, 50);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(20px)';
    setTimeout(() => toast.remove(), 300);
  }, 4000);
}

function escapeHtml(text) {
  if (!text) return '';
  return text.replace(/[&<>"']/g, function(m) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
  });
}
