/**
 * SkillSync System Admin Panel Client-Side Interactive Engine
 */

let pendingAdminTask = null;

document.addEventListener('DOMContentLoaded', () => {
  initAdminEvents();
});

function initAdminEvents() {
  // User Search Filter
  const userSearch = document.getElementById('userSearchInput');
  if (userSearch) {
    userSearch.addEventListener('input', (e) => filterTable('usersTableBody', e.target.value.toLowerCase()));
  }

  // Company Search Filter
  const companySearch = document.getElementById('companySearchInput');
  if (companySearch) {
    companySearch.addEventListener('input', (e) => filterTable('companiesTableBody', e.target.value.toLowerCase()));
  }

  // Confirm Admin Action Listener
  document.getElementById('confirmAdminActionProceedBtn')?.addEventListener('click', async function() {
    if (!pendingAdminTask) return;

    const modalEl = document.getElementById('confirmAdminActionModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();

    const task = pendingAdminTask;
    pendingAdminTask = null;

    if (task.type === 'user_status') {
      const formData = new FormData();
      formData.append('action', 'toggle_user_status');
      formData.append('userid', task.userId);
      formData.append('status', task.status);

      try {
        const res = await fetch('./', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.success) {
          showToast(result.message, 'success');
          setTimeout(() => location.reload(), 500);
        } else {
          showToast(result.message || 'Failed to update user status.', 'danger');
        }
      } catch(err) {
        console.error('Error updating user status:', err);
        showToast('Error updating user status.', 'danger');
      }
    } else if (task.type === 'company_action') {
      const formData = new FormData();
      formData.append('action', 'update_company_status');
      formData.append('companyid', task.companyId);

      if (task.action === 'Approve') {
        formData.append('verificationStatus', 'Verified');
        formData.append('accountStatus', 'Active');
      } else if (task.action === 'Reject') {
        formData.append('verificationStatus', 'Rejected');
      } else if (task.action === 'Suspend') {
        formData.append('accountStatus', 'Suspended');
      } else if (task.action === 'Activate') {
        formData.append('accountStatus', 'Active');
        formData.append('verificationStatus', 'Verified');
      }

      try {
        const res = await fetch('./', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.success) {
          showToast(result.message, 'success');
          setTimeout(() => location.reload(), 500);
        } else {
          showToast(result.message || 'Failed to update company status.', 'danger');
        }
      } catch(err) {
        console.error('Error updating company status:', err);
        showToast('Error updating company status.', 'danger');
      }
    } else if (task.type === 'password_reset_action') {
      const formData = new FormData();
      formData.append('action', 'handle_password_reset');
      formData.append('reset_id', task.resetId);
      formData.append('status', task.action === 'Approve' ? 'Approved' : 'Rejected');

      try {
        const res = await fetch('./', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.success) {
          showToast(result.message, 'success');
          setTimeout(() => location.reload(), 500);
        } else {
          showToast(result.message || 'Failed to update password reset request.', 'danger');
        }
      } catch(err) {
        console.error('Error handling password reset request:', err);
        showToast('Error updating password reset request.', 'danger');
      }
    }
  });
}

/**
 * Filter Table rows by query string
 */
function filterTable(tableId, query) {
  const tbody = document.getElementById(tableId);
  if (!tbody) return;
  const rows = tbody.getElementsByTagName('tr');
  for (let row of rows) {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(query) ? '' : 'none';
  }
}

/**
 * Confirm Toggle User Account Status (Rule 2 Modal)
 */
function confirmToggleUserStatus(userId, userName, currentStatus) {
  const nextStatus = currentStatus === 'Active' ? 'Suspended' : 'Active';
  pendingAdminTask = {
    type: 'user_status',
    userId: userId,
    userName: userName,
    status: nextStatus
  };
  const msgEl = document.getElementById('confirmAdminActionMsg');
  if (msgEl) {
    msgEl.textContent = `Are you sure you want to change account status for "${userName}" to ${nextStatus.toUpperCase()}?`;
  }
  const modal = new bootstrap.Modal(document.getElementById('confirmAdminActionModal'));
  modal.show();
}

/**
 * Confirm Toggle Company Verification Status (Rule 2 Modal)
 */
function confirmCompanyVerification(companyId, companyName, action) {
  pendingAdminTask = {
    type: 'company_action',
    companyId: companyId,
    companyName: companyName,
    action: action
  };
  const msgEl = document.getElementById('confirmAdminActionMsg');
  if (msgEl) {
    msgEl.textContent = `Are you sure you want to ${action.toUpperCase()} company account for "${companyName}"?`;
  }
  const modal = new bootstrap.Modal(document.getElementById('confirmAdminActionModal'));
  modal.show();
}

/**
 * Confirm Password Reset Request Authorization (Rule 2 Modal)
 */
function confirmPasswordResetAction(resetId, accountName, action) {
  pendingAdminTask = {
    type: 'password_reset_action',
    resetId: resetId,
    accountName: accountName,
    action: action
  };
  const msgEl = document.getElementById('confirmAdminActionMsg');
  if (msgEl) {
    msgEl.textContent = `Are you sure you want to ${action.toUpperCase()} the password reset request for "${accountName}"?`;
  }
  const modal = new bootstrap.Modal(document.getElementById('confirmAdminActionModal'));
  modal.show();
}

/**
 * Open User Details Modal
 */
function viewUserDetails(userId, userName, email, profTitle, accStatus, skills, lastLogin) {
  document.getElementById('modalUserId').innerText = `#USR-${userId}`;
  document.getElementById('modalUserName').innerText = userName;
  document.getElementById('modalUserEmail').innerText = email || 'N/A';
  document.getElementById('modalUserTitle').innerText = profTitle || 'N/A';
  document.getElementById('modalUserSkills').innerText = skills || 'N/A';
  document.getElementById('modalUserStatus').innerText = accStatus || 'Active';
  document.getElementById('modalUserLogin').innerText = lastLogin || 'Never';

  const modal = new bootstrap.Modal(document.getElementById('viewUserModal'));
  modal.show();
}

/**
 * Open Company Details Modal
 */
function viewCompanyDetails(companyId, companyName, email, industry, regNo, city, verStatus, accStatus) {
  document.getElementById('modalCompanyId').innerText = `#COMP-${companyId}`;
  document.getElementById('modalCompanyName').innerText = companyName;
  document.getElementById('modalCompanyEmail').innerText = email || 'N/A';
  document.getElementById('modalCompanyIndustry').innerText = industry || 'N/A';
  document.getElementById('modalCompanyRegNo').innerText = regNo || 'N/A';
  document.getElementById('modalCompanyCity').innerText = city || 'N/A';
  document.getElementById('modalCompanyVerStatus').innerText = verStatus || 'Pending';
  document.getElementById('modalCompanyAccStatus').innerText = accStatus || 'Active';

  const modal = new bootstrap.Modal(document.getElementById('viewCompanyModal'));
  modal.show();
}

// BFCache (Browser Back/Forward Cache) Guard to prevent unauthorized access after logout
window.addEventListener('pageshow', function(event) {
  if (event.persisted || (window.performance && window.performance.navigation && window.performance.navigation.type === 2)) {
    window.location.reload();
  }
});
