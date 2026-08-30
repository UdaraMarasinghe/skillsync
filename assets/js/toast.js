/**
 * SkillSync Universal Toast Notification Engine
 */
function showToast(message, type = 'info') {
  if (!message) return;

  let toastContainer = document.getElementById('toastContainer');
  if (!toastContainer) {
    toastContainer = document.createElement('div');
    toastContainer.id = 'toastContainer';
    toastContainer.style.cssText = 'position: fixed; bottom: 24px; right: 24px; z-index: 99999; display: flex; flex-direction: column; gap: 10px; pointer-events: none;';
    document.body.appendChild(toastContainer);
  }

  const toast = document.createElement('div');
  toast.style.pointerEvents = 'auto';

  let bgClass = '#004743'; // Default brand dark
  let textClass = '#ACFF78'; // Brand accent
  let iconClass = 'bi-info-circle-fill';

  if (type === 'success') {
    bgClass = '#004743';
    textClass = '#ACFF78';
    iconClass = 'bi-check-circle-fill';
  } else if (type === 'danger' || type === 'error') {
    bgClass = '#dc2626';
    textClass = '#ffffff';
    iconClass = 'bi-exclamation-octagon-fill';
  } else if (type === 'warning') {
    bgClass = '#d97706';
    textClass = '#ffffff';
    iconClass = 'bi-exclamation-triangle-fill';
  }

  toast.style.cssText = `background-color: ${bgClass}; color: ${textClass}; padding: 14px 20px; border-radius: 10px; font-weight: 600; font-size: 0.88rem; box-shadow: 0 8px 24px rgba(0,0,0,0.28); opacity: 0; transform: translateY(20px); transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1); display: flex; align-items: center; gap: 10px; max-width: 400px; word-break: break-word; font-family: 'Poppins', sans-serif;`;

  const escMsg = String(message).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  toast.innerHTML = `<i class="bi ${iconClass} fs-5"></i> <span>${escMsg}</span>`;

  toastContainer.appendChild(toast);

  requestAnimationFrame(() => {
    toast.style.opacity = '1';
    toast.style.transform = 'translateY(0)';
  });

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(20px)';
    setTimeout(() => toast.remove(), 350);
  }, 4000);
}

// Global alert alias for smooth backward compatibility
window.alertToast = function(msg, type = 'info') {
  showToast(msg, type);
};

// BFCache (Browser Back/Forward Cache) Guard to prevent unauthorized access after logout
window.addEventListener('pageshow', function(event) {
  if (event.persisted || (window.performance && window.performance.navigation && window.performance.navigation.type === 2)) {
    window.location.reload();
  }
});
