/* ==========================================================================
   PROFILE-PRO CV BUILDER - MAIN APPLICATION SCRIPT
   ========================================================================== */

let cvState = {
  activeTemplate: 'modern',
  styling: {
    fontFamily: "'Inter', sans-serif",
    fontSize: '14px',
    primaryColor: '#2563eb',
    secondaryColor: '#475569',
    textColor: '#1e293b',
    bgColor: '#ffffff'
  },
  personal: {
    fullName: 'Alexander Vance',
    jobTitle: 'Senior Full Stack Engineer',
    email: 'alexander.vance@example.com',
    phone: '+1 (555) 234-5678',
    location: 'San Francisco, CA',
    website: 'alexvance.dev',
    linkedin: 'linkedin.com/in/alexvance',
    github: 'github.com/alexvance',
    photoShape: 'circle',
    avatar: '../assets/img/demo-profile.jpg',
    summary: 'Driven Senior Full Stack Engineer with 7+ years of experience designing scalable web applications, RESTful APIs, and microservice architectures. Proven track record of leading cross-functional tech teams, reducing latency by 40%, and modernizing cloud infrastructure.'
  },
  experience: [
    {
      id: 'exp-1',
      title: 'Senior Full Stack Engineer',
      company: 'TechCorp Solutions',
      location: 'San Francisco, CA',
      startDate: 'Jan 2022',
      endDate: 'Present',
      description: '• Spearheaded frontend re-architecture using React and TypeScript, improving core web vitals by 35%.\n• Designed and deployed scalable Node.js microservices serving 2M+ active users daily.\n• Mentored 6 junior engineers and established automated CI/CD deployment pipelines.'
    },
    {
      id: 'exp-2',
      title: 'Software Engineer',
      company: 'Nexus Innovations',
      location: 'Austin, TX',
      startDate: 'Jun 2019',
      endDate: 'Dec 2021',
      description: '• Developed real-time dashboard analytics utilizing WebSockets and Vue.js.\n• Optimized PostgreSQL queries and reduced database fetch response times by 45%.'
    }
  ],
  education: [
    {
      id: 'edu-1',
      degree: 'B.S. in Computer Science',
      institution: 'University of California, Berkeley',
      location: 'Berkeley, CA',
      year: '2015 - 2019',
      description: 'Graduated with High Honors (GPA 3.85/4.0). Specialization in Distributed Systems and Algorithms.'
    }
  ],
  skills: [
    'JavaScript (ES6+)', 'TypeScript', 'Node.js', 'React.js', 'PHP', 'Python', 'Docker', 'PostgreSQL', 'GraphQL', 'AWS'
  ],
  projects: [
    {
      id: 'proj-1',
      name: 'CloudSync CLI Engine',
      link: 'github.com/alexvance/cloudsync',
      description: 'An open-source high-throughput CLI sync tool built in Go and Docker, downloaded over 50k times.'
    }
  ],
  custom: []
};

// Initialize Application
document.addEventListener('DOMContentLoaded', () => {
  loadFromLocalStorage();
  bindTabEvents();
  bindFormEvents();
  renderAllDynamicForms();
  renderCVPreview();
});

// Save / Load LocalStorage
function saveToLocalStorage() {
  localStorage.setItem('profilepro_cv_data', JSON.stringify(cvState));
}

function loadFromLocalStorage() {
  const saved = localStorage.getItem('profilepro_cv_data');
  if (saved) {
    try {
      cvState = JSON.parse(saved);
      syncFormInputsFromState();
    } catch (e) {
      console.error('Failed to load saved state:', e);
    }
  }
}

// Sync State to Form Inputs
function syncFormInputsFromState() {
  if (cvState.activeTemplate) {
    const tSelect = document.getElementById('templateSelect');
    if (tSelect) tSelect.value = cvState.activeTemplate;
  }

  // Styling inputs
  const st = cvState.styling;
  if (st) {
    setInputValue('fontFamily', st.fontFamily);
    setInputValue('fontSize', parseInt(st.fontSize));
    document.getElementById('fontSizeVal').innerText = st.fontSize;
    setInputValue('primaryColor', st.primaryColor);
    setInputValue('secondaryColor', st.secondaryColor);
    setInputValue('textColor', st.textColor);
    setInputValue('bgColor', st.bgColor);
  }

  // Personal inputs
  const p = cvState.personal;
  if (p) {
    setInputValue('fullName', p.fullName);
    setInputValue('jobTitle', p.jobTitle);
    setInputValue('email', p.email);
    setInputValue('phone', p.phone);
    setInputValue('location', p.location);
    setInputValue('website', p.website);
    setInputValue('linkedin', p.linkedin);
    setInputValue('github', p.github);
    setInputValue('summary', p.summary);
    setInputValue('photoShape', p.photoShape || 'circle');

    if (p.avatar) {
      const avatarImg = document.getElementById('avatarImg');
      const avatarIcon = document.getElementById('avatarIcon');
      if (avatarImg && avatarIcon) {
        avatarImg.src = p.avatar;
        avatarImg.style.display = 'block';
        avatarIcon.style.display = 'none';
      }
    }
  }

  // Skills input string
  setInputValue('skillsInput', (cvState.skills || []).join(', '));
}

function setInputValue(id, value) {
  const el = document.getElementById(id);
  if (el && value !== undefined) el.value = value;
}

// Tab Navigation
function bindTabEvents() {
  const tabs = document.querySelectorAll('.tab-btn');
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));

      tab.classList.add('active');
      const targetPane = document.getElementById(`pane-${tab.dataset.tab}`);
      if (targetPane) targetPane.classList.add('active');
    });
  });
}

// Form Field Listeners
function bindFormEvents() {
  // Template Picker
  const tSelect = document.getElementById('templateSelect');
  if (tSelect) {
    tSelect.addEventListener('change', (e) => {
      cvState.activeTemplate = e.target.value;
      renderCVPreview();
      saveToLocalStorage();
    });
  }

  // Styling
  bindInputChange('fontFamily', (val) => { cvState.styling.fontFamily = val; });
  bindInputChange('fontSize', (val) => { 
    cvState.styling.fontSize = val + 'px';
    document.getElementById('fontSizeVal').innerText = val + 'px';
  });
  bindInputChange('primaryColor', (val) => { cvState.styling.primaryColor = val; });
  bindInputChange('secondaryColor', (val) => { cvState.styling.secondaryColor = val; });
  bindInputChange('textColor', (val) => { cvState.styling.textColor = val; });
  bindInputChange('bgColor', (val) => { cvState.styling.bgColor = val; });

  // Personal Info
  bindInputChange('fullName', (val) => { cvState.personal.fullName = val; });
  bindInputChange('jobTitle', (val) => { cvState.personal.jobTitle = val; });
  bindInputChange('email', (val) => { cvState.personal.email = val; });
  bindInputChange('phone', (val) => { cvState.personal.phone = val; });
  bindInputChange('location', (val) => { cvState.personal.location = val; });
  bindInputChange('website', (val) => { cvState.personal.website = val; });
  bindInputChange('linkedin', (val) => { cvState.personal.linkedin = val; });
  bindInputChange('github', (val) => { cvState.personal.github = val; });
  bindInputChange('summary', (val) => { cvState.personal.summary = val; });
  bindInputChange('photoShape', (val) => { cvState.personal.photoShape = val; });

  // Avatar Upload
  const avatarInput = document.getElementById('avatarInput');
  if (avatarInput) {
    avatarInput.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = (evt) => {
          cvState.personal.avatar = evt.target.result;
          const avatarImg = document.getElementById('avatarImg');
          const avatarIcon = document.getElementById('avatarIcon');
          avatarImg.src = evt.target.result;
          avatarImg.style.display = 'block';
          avatarIcon.style.display = 'none';
          renderCVPreview();
          saveToLocalStorage();
        };
        reader.readAsDataURL(file);
      }
    });
  }

  // Remove Avatar
  const removeAvatarBtn = document.getElementById('removeAvatarBtn');
  if (removeAvatarBtn) {
    removeAvatarBtn.addEventListener('click', () => {
      cvState.personal.avatar = '';
      const avatarImg = document.getElementById('avatarImg');
      const avatarIcon = document.getElementById('avatarIcon');
      if (avatarImg && avatarIcon) {
        avatarImg.src = '';
        avatarImg.style.display = 'none';
        avatarIcon.style.display = 'block';
      }
      renderCVPreview();
      saveToLocalStorage();
    });
  }

  // Skills input
  const skillsInput = document.getElementById('skillsInput');
  if (skillsInput) {
    skillsInput.addEventListener('input', (e) => {
      const list = e.target.value.split(',').map(s => s.trim()).filter(Boolean);
      cvState.skills = list;
      renderCVPreview();
      saveToLocalStorage();
    });
  }

  // Zoom slider
  const zoomSlider = document.getElementById('zoomSlider');
  if (zoomSlider) {
    zoomSlider.addEventListener('input', (e) => {
      const scale = e.target.value / 100;
      const cvContainer = document.getElementById('cvPageContainer');
      if (cvContainer) {
        cvContainer.style.transform = `scale(${scale})`;
        cvContainer.style.transformOrigin = 'top center';
      }
      document.getElementById('zoomVal').innerText = `${e.target.value}%`;
    });
  }
}

function bindInputChange(id, updateFn) {
  const el = document.getElementById(id);
  if (el) {
    el.addEventListener('input', (e) => {
      updateFn(e.target.value);
      renderCVPreview();
      saveToLocalStorage();
    });
  }
}

// Dynamic Item Forms Management
function renderAllDynamicForms() {
  renderExperienceList();
  renderEducationList();
  renderProjectsList();
  renderCustomList();
}

// Work Experience Dynamic List
function renderExperienceList() {
  const container = document.getElementById('experienceList');
  if (!container) return;
  container.innerHTML = '';

  cvState.experience.forEach((exp, index) => {
    const card = document.createElement('div');
    card.className = 'item-card';
    card.innerHTML = `
      <div class="item-card-header">
        <div class="item-card-title">${exp.title || 'Job Title'} ${exp.company ? '@ ' + exp.company : ''}</div>
        <div class="item-actions">
          <button class="btn btn-danger btn-sm" onclick="removeExperience('${exp.id}')"><i class="fas fa-trash"></i></button>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Job Title</label>
          <input type="text" value="${exp.title || ''}" oninput="updateExperience('${exp.id}', 'title', this.value)">
        </div>
        <div class="form-group">
          <label>Company</label>
          <input type="text" value="${exp.company || ''}" oninput="updateExperience('${exp.id}', 'company', this.value)">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Start Date</label>
          <input type="text" placeholder="e.g. Jan 2021" value="${exp.startDate || ''}" oninput="updateExperience('${exp.id}', 'startDate', this.value)">
        </div>
        <div class="form-group">
          <label>End Date</label>
          <input type="text" placeholder="e.g. Present" value="${exp.endDate || ''}" oninput="updateExperience('${exp.id}', 'endDate', this.value)">
        </div>
      </div>
      <div class="form-group">
        <label>Description & Key Achievements</label>
        <textarea oninput="updateExperience('${exp.id}', 'description', this.value)">${exp.description || ''}</textarea>
      </div>
    `;
    container.appendChild(card);
  });
}

function addExperience() {
  const newExp = {
    id: 'exp-' + Date.now(),
    title: '',
    company: '',
    startDate: '',
    endDate: '',
    description: ''
  };
  cvState.experience.push(newExp);
  renderExperienceList();
  renderCVPreview();
  saveToLocalStorage();
}

function updateExperience(id, field, val) {
  const exp = cvState.experience.find(e => e.id === id);
  if (exp) {
    exp[field] = val;
    renderCVPreview();
    saveToLocalStorage();
  }
}

function removeExperience(id) {
  cvState.experience = cvState.experience.filter(e => e.id !== id);
  renderExperienceList();
  renderCVPreview();
  saveToLocalStorage();
}

// Education Dynamic List
function renderEducationList() {
  const container = document.getElementById('educationList');
  if (!container) return;
  container.innerHTML = '';

  cvState.education.forEach((edu) => {
    const card = document.createElement('div');
    card.className = 'item-card';
    card.innerHTML = `
      <div class="item-card-header">
        <div class="item-card-title">${edu.degree || 'Degree'}</div>
        <div class="item-actions">
          <button class="btn btn-danger btn-sm" onclick="removeEducation('${edu.id}')"><i class="fas fa-trash"></i></button>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Degree / Qualification</label>
          <input type="text" value="${edu.degree || ''}" oninput="updateEducation('${edu.id}', 'degree', this.value)">
        </div>
        <div class="form-group">
          <label>Institution</label>
          <input type="text" value="${edu.institution || ''}" oninput="updateEducation('${edu.id}', 'institution', this.value)">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Years</label>
          <input type="text" placeholder="e.g. 2018 - 2022" value="${edu.year || ''}" oninput="updateEducation('${edu.id}', 'year', this.value)">
        </div>
        <div class="form-group">
          <label>Details / Honors</label>
          <input type="text" value="${edu.description || ''}" oninput="updateEducation('${edu.id}', 'description', this.value)">
        </div>
      </div>
    `;
    container.appendChild(card);
  });
}

function addEducation() {
  const newEdu = {
    id: 'edu-' + Date.now(),
    degree: '',
    institution: '',
    year: '',
    description: ''
  };
  cvState.education.push(newEdu);
  renderEducationList();
  renderCVPreview();
  saveToLocalStorage();
}

function updateEducation(id, field, val) {
  const edu = cvState.education.find(e => e.id === id);
  if (edu) {
    edu[field] = val;
    renderCVPreview();
    saveToLocalStorage();
  }
}

function removeEducation(id) {
  cvState.education = cvState.education.filter(e => e.id !== id);
  renderEducationList();
  renderCVPreview();
  saveToLocalStorage();
}

// Projects Dynamic List
function renderProjectsList() {
  const container = document.getElementById('projectsList');
  if (!container) return;
  container.innerHTML = '';

  cvState.projects.forEach((proj) => {
    const card = document.createElement('div');
    card.className = 'item-card';
    card.innerHTML = `
      <div class="item-card-header">
        <div class="item-card-title">${proj.name || 'Project Name'}</div>
        <div class="item-actions">
          <button class="btn btn-danger btn-sm" onclick="removeProject('${proj.id}')"><i class="fas fa-trash"></i></button>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Project Name</label>
          <input type="text" value="${proj.name || ''}" oninput="updateProject('${proj.id}', 'name', this.value)">
        </div>
        <div class="form-group">
          <label>Project Link / URL</label>
          <input type="text" value="${proj.link || ''}" oninput="updateProject('${proj.id}', 'link', this.value)">
        </div>
      </div>
      <div class="form-group">
        <label>Project Description</label>
        <textarea oninput="updateProject('${proj.id}', 'description', this.value)">${proj.description || ''}</textarea>
      </div>
    `;
    container.appendChild(card);
  });
}

function addProject() {
  const newProj = {
    id: 'proj-' + Date.now(),
    name: '',
    link: '',
    description: ''
  };
  cvState.projects.push(newProj);
  renderProjectsList();
  renderCVPreview();
  saveToLocalStorage();
}

function updateProject(id, field, val) {
  const proj = cvState.projects.find(p => p.id === id);
  if (proj) {
    proj[field] = val;
    renderCVPreview();
    saveToLocalStorage();
  }
}

function removeProject(id) {
  cvState.projects = cvState.projects.filter(p => p.id !== id);
  renderProjectsList();
  renderCVPreview();
  saveToLocalStorage();
}

// Custom Sections Dynamic List
function renderCustomList() {
  const container = document.getElementById('customList');
  if (!container) return;
  container.innerHTML = '';

  cvState.custom.forEach((cust) => {
    const card = document.createElement('div');
    card.className = 'item-card';
    card.innerHTML = `
      <div class="item-card-header">
        <div class="item-card-title">${cust.title || 'Custom Section'}</div>
        <div class="item-actions">
          <button class="btn btn-danger btn-sm" onclick="removeCustom('${cust.id}')"><i class="fas fa-trash"></i></button>
        </div>
      </div>
      <div class="form-group">
        <label>Section Title</label>
        <input type="text" value="${cust.title || ''}" oninput="updateCustom('${cust.id}', 'title', this.value)">
      </div>
      <div class="form-group">
        <label>Content</label>
        <textarea oninput="updateCustom('${cust.id}', 'content', this.value)">${cust.content || ''}</textarea>
      </div>
    `;
    container.appendChild(card);
  });
}

function addCustom() {
  const newCust = {
    id: 'cust-' + Date.now(),
    title: '',
    content: ''
  };
  cvState.custom.push(newCust);
  renderCustomList();
  renderCVPreview();
  saveToLocalStorage();
}

function updateCustom(id, field, val) {
  const cust = cvState.custom.find(c => c.id === id);
  if (cust) {
    cust[field] = val;
    renderCVPreview();
    saveToLocalStorage();
  }
}

function removeCustom(id) {
  cvState.custom = cvState.custom.filter(c => c.id !== id);
  renderCustomList();
  renderCVPreview();
  saveToLocalStorage();
}

// Render CV Canvas Preview
function renderCVPreview() {
  const cvPage = document.getElementById('cvPage');
  if (!cvPage) return;

  // Apply Styling Custom Properties
  const st = cvState.styling;
  cvPage.style.setProperty('--cv-font', st.fontFamily || "'Inter', sans-serif");
  cvPage.style.setProperty('--cv-font-size', st.fontSize || '14px');
  cvPage.style.setProperty('--cv-primary-color', st.primaryColor || '#2563eb');
  cvPage.style.setProperty('--cv-secondary-color', st.secondaryColor || '#475569');
  cvPage.style.setProperty('--cv-text-color', st.textColor || '#1e293b');
  cvPage.style.setProperty('--cv-bg-color', st.bgColor || '#ffffff');

  // Select Template Renderer
  const templateFn = Templates[cvState.activeTemplate] || Templates.modern;
  cvPage.innerHTML = templateFn(cvState);
}

// PDF Export, Save Document & DB Record Trigger
function exportToPDF() {
  const modalEl = document.getElementById('saveResumeModal');
  const titleInput = document.getElementById('saveResumeTitleInput');
  
  if (titleInput) {
    titleInput.value = cvState.personal.fullName ? `${cvState.personal.fullName} Resume` : 'Resume';
  }

  if (modalEl) {
    const saveModal = new bootstrap.Modal(modalEl);
    const confirmBtn = document.getElementById('confirmSaveResumeBtn');
    
    if (confirmBtn) {
      confirmBtn.onclick = async function() {
        const resumeTitle = (titleInput && titleInput.value.trim()) ? titleInput.value.trim() : (cvState.personal.fullName || 'Resume');
        saveModal.hide();
        await performSaveAndExport(resumeTitle);
      };
    }
    
    saveModal.show();
  } else {
    performSaveAndExport(cvState.personal.fullName || 'Resume');
  }
}

async function performSaveAndExport(resumeTitle) {
  const cvPage = document.getElementById('cvPage');
  if (!cvPage) return;

  const currentPath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
  const stylesUrl = window.location.origin + currentPath + 'css/styles.css';

  const htmlContent = `<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>${resumeTitle} - SkillSync</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="${stylesUrl}">
  <style>
    body { margin: 0; padding: 20px; font-family: ${cvState.styling.fontFamily || "'Inter', sans-serif"}; background: #fff; }
    .cv-page { width: 100%; max-width: 800px; margin: 0 auto; background: #fff; }
  </style>
</head>
<body>
  <div class="cv-page">
    ${cvPage.innerHTML}
  </div>
</body>
</html>`;

  const formData = new FormData();
  formData.append('action', 'save_resume');
  formData.append('resume_title', resumeTitle);
  formData.append('resume_content', htmlContent);

  try {
    const response = await fetch('./', {
      method: 'POST',
      body: formData
    });
    const result = await response.json();
    if (result.success) {
      showToast(`Resume saved successfully to database! (Resume ID: ${result.resumeid})`, 'success');
    } else {
      console.warn('Database save notice:', result.message);
    }
  } catch (err) {
    console.error('Error saving resume to database:', err);
  }

  // Trigger print dialog for PDF saving
  window.print();
}

// Export / Import JSON Data
function exportJSON() {
  const jsonStr = JSON.stringify(cvState, null, 2);
  const blob = new Blob([jsonStr], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `${(cvState.personal.fullName || 'CV').replace(/\s+/g, '_')}_Resume.json`;
  a.click();
  URL.revokeObjectURL(url);
}

function triggerImportJSON() {
  const fileInput = document.getElementById('jsonFileInput');
  if (fileInput) fileInput.click();
}

function handleJSONImport(event) {
  const file = event.target.files[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = (e) => {
    try {
      const imported = JSON.parse(e.target.result);
      cvState = imported;
      syncFormInputsFromState();
      renderAllDynamicForms();
      renderCVPreview();
      saveToLocalStorage();
      showToast('CV Data successfully imported!', 'success');
    } catch (err) {
      showToast('Invalid JSON file format.', 'danger');
    }
  };
  reader.readAsText(file);
}

function clearAllData() {
  if (confirm('Are you sure you want to reset all fields?')) {
    localStorage.removeItem('profilepro_cv_data');
    location.reload();
  }
}
