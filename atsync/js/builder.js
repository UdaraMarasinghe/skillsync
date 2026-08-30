/**
 * ATSync - Core ATS CV Builder Client Script
 */

document.addEventListener('DOMContentLoaded', () => {
    initStepWizard();
    initNicUploader();
    initRepeaterFields();
    initLivePreviewSync();
    initTemplateSwitcher();

    // Trigger initial calculation
    calculateAtsScore();
});

// Step Wizard State Management
let currentStep = 1;

function initStepWizard() {
    const steps = document.querySelectorAll('.step-item');
    
    steps.forEach(step => {
        step.addEventListener('click', () => {
            const targetStep = parseInt(step.dataset.step);
            // Allow backward step or if NIC uploaded
            goToStep(targetStep);
        });
    });
}

function goToStep(stepNumber) {
    if (stepNumber < 1 || stepNumber > 3) return;

    currentStep = stepNumber;

    // Update Step Indicators
    document.querySelectorAll('.step-item').forEach(item => {
        const itemStep = parseInt(item.dataset.step);
        item.classList.remove('active', 'completed');
        if (itemStep === currentStep) {
            item.classList.add('active');
        } else if (itemStep < currentStep) {
            item.classList.add('completed');
        }
    });

    // Update Progress Bar
    const progressPct = ((currentStep - 1) / 2) * 100;
    const progressBar = document.getElementById('wizardProgressBar');
    if (progressBar) progressBar.style.width = progressPct + '%';

    // Show Active Card
    document.querySelectorAll('.step-card').forEach(card => {
        card.classList.remove('active');
    });
    const targetCard = document.getElementById(`stepCard${currentStep}`);
    if (targetCard) targetCard.classList.add('active');

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// NIC Upload & Processing System
let nicFrontFile = null;
let nicBackFile = null;

function initNicUploader() {
    setupDropzone('dropzoneFront', 'fileNicFront', 'previewFront', file => nicFrontFile = file);
    setupDropzone('dropzoneBack', 'fileNicBack', 'previewBack', file => nicBackFile = file);

    const btnScan = document.getElementById('btnScanNic');
    if (btnScan) {
        btnScan.addEventListener('click', performNicScan);
    }
}

function setupDropzone(dropzoneId, inputId, previewId, setFileCallback) {
    const dropzone = document.getElementById(dropzoneId);
    const fileInput = document.getElementById(inputId);
    const previewImg = document.getElementById(previewId);

    if (!dropzone || !fileInput) return;

    dropzone.addEventListener('click', () => fileInput.click());

    dropzone.addEventListener('dragover', e => {
        e.preventDefault();
        dropzone.classList.add('dragover');
    });

    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));

    dropzone.addEventListener('drop', e => {
        e.preventDefault();
        dropzone.classList.remove('dragover');
        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            handleFileSelect(e.dataTransfer.files[0], fileInput, previewImg, setFileCallback);
        }
    });

    fileInput.addEventListener('change', e => {
        if (e.target.files && e.target.files[0]) {
            handleFileSelect(e.target.files[0], fileInput, previewImg, setFileCallback);
        }
    });
}

function handleFileSelect(file, fileInput, previewImg, setFileCallback) {
    setFileCallback(file);
    const reader = new FileReader();
    reader.onload = e => {
        if (previewImg) {
            previewImg.src = e.target.result;
            previewImg.style.display = 'block';
        }
    };
    reader.readAsDataURL(file);
}

async function performNicScan() {
    if (!nicFrontFile || !nicBackFile) {
        showToast('Please select or drag-and-drop both NIC Front and Back images.', 'warning');
        return;
    }

    const btnScan = document.getElementById('btnScanNic');
    const originalText = btnScan.innerHTML;
    btnScan.disabled = true;

    let ocrFrontText = '';
    let ocrBackText = '';

    if (window.Tesseract) {
        try {
            btnScan.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running Tesseract OCR Scan...';
            const workerFront = await Tesseract.createWorker('eng');
            const resFront = await workerFront.recognize(nicFrontFile);
            ocrFrontText = resFront.data.text || '';
            await workerFront.terminate();

            const workerBack = await Tesseract.createWorker('eng');
            const resBack = await workerBack.recognize(nicBackFile);
            ocrBackText = resBack.data.text || '';
            await workerBack.terminate();
        } catch (ocrErr) {
            console.warn('Client-side Tesseract OCR failed, falling back to server parser:', ocrErr);
        }
    }

    btnScan.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Extracting Structured NIC Data...';

    const formData = new FormData();
    formData.append('nic_front', nicFrontFile);
    formData.append('nic_back', nicBackFile);
    formData.append('ocr_front_text', ocrFrontText);
    formData.append('ocr_back_text', ocrBackText);

    fetch('api/scan_nic.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        btnScan.innerHTML = originalText;
        btnScan.disabled = false;

        if (res.success && res.data) {
            populateExtractedNicData(res.data);
            showToast('Sri Lankan NIC details extracted successfully!', 'success');
            
            // Show extracted summary banner
            const summaryBox = document.getElementById('extractedSummaryBox');
            if (summaryBox) {
                document.getElementById('sumName').textContent = res.data.full_name;
                document.getElementById('sumNic').textContent = res.data.nic_number;
                document.getElementById('sumDob').textContent = res.data.dob;
                document.getElementById('sumGender').textContent = res.data.gender;
                document.getElementById('sumAddress').textContent = res.data.address;
                summaryBox.style.display = 'block';
            }

            // Auto advance to Step 2 after 1.2s
            setTimeout(() => {
                goToStep(2);
            }, 1200);
        } else {
            showToast(res.message || 'Failed to extract NIC data.', 'danger');
        }
    })
    .catch(err => {
        btnScan.innerHTML = originalText;
        btnScan.disabled = false;
        console.error(err);
        showToast('An error occurred while uploading NIC images.', 'danger');
    });
}

function populateExtractedNicData(data) {
    if (data.full_name) setValue('input_fullName', data.full_name);
    if (data.nic_number) setValue('input_nicNumber', data.nic_number);
    if (data.dob) setValue('input_dob', data.dob);
    if (data.gender) setValue('input_gender', data.gender);
    if (data.address) setValue('input_location', data.address);

    // Sync live preview
    updatePreviewText('prev_fullName', data.full_name);
    updatePreviewText('prev_location', data.address);
    updatePreviewText('prev_nic', data.nic_number);
}

function setValue(id, val) {
    const el = document.getElementById(id);
    if (el) {
        el.value = val;
        el.dispatchEvent(new Event('input'));
    }
}

function updatePreviewText(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
}

// Repeater Fields Management (Experience, Education, Projects)
function initRepeaterFields() {
    // Add Experience
    const btnAddExp = document.getElementById('btnAddExperience');
    if (btnAddExp) btnAddExp.addEventListener('click', addExperienceItem);

    // Add Education
    const btnAddEdu = document.getElementById('btnAddEducation');
    if (btnAddEdu) btnAddEdu.addEventListener('click', addEducationItem);

    // Add Project
    const btnAddProj = document.getElementById('btnAddProject');
    if (btnAddProj) btnAddProj.addEventListener('click', addProjectItem);

    // Start with clean empty repeater containers
}

function addExperienceItem(role = '', company = '', location = '', dates = '', desc = '') {
    const container = document.getElementById('experienceListContainer');
    if (!container) return;

    const id = 'exp_' + Date.now();
    const item = document.createElement('div');
    item.className = 'repeater-card';
    item.id = id;
    item.innerHTML = `
        <div class="repeater-header">
            <span class="repeater-title"><i class="fas fa-briefcase"></i> Work Experience</span>
            <button type="button" class="btn-remove" onclick="removeRepeaterItem('${id}')"><i class="fas fa-trash"></i> Remove</button>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label>Job Title / Role</label>
                <input type="text" class="form-control exp-role" value="${role}" placeholder="e.g. Senior Software Engineer" oninput="syncCvPreview()">
            </div>
            <div class="form-group">
                <label>Company / Organization</label>
                <input type="text" class="form-control exp-company" value="${company}" placeholder="e.g. Tech Solutions PLC" oninput="syncCvPreview()">
            </div>
            <div class="form-group">
                <label>Location</label>
                <input type="text" class="form-control exp-location" value="${location}" placeholder="e.g. Colombo, Sri Lanka" oninput="syncCvPreview()">
            </div>
            <div class="form-group">
                <label>Dates / Period</label>
                <input type="text" class="form-control exp-dates" value="${dates}" placeholder="e.g. 2021 - Present" oninput="syncCvPreview()">
            </div>
            <div class="form-group full-width">
                <label>Key Accomplishments & Responsibilities (One per line)</label>
                <textarea class="form-control exp-desc" placeholder="• Achieved X by doing Y resulting in Z..." oninput="syncCvPreview()">${desc}</textarea>
            </div>
        </div>
    `;
    container.appendChild(item);
    syncCvPreview();
}

function addEducationItem(degree = '', school = '', dates = '', details = '') {
    const container = document.getElementById('educationListContainer');
    if (!container) return;

    const id = 'edu_' + Date.now();
    const item = document.createElement('div');
    item.className = 'repeater-card';
    item.id = id;
    item.innerHTML = `
        <div class="repeater-header">
            <span class="repeater-title"><i class="fas fa-graduation-cap"></i> Education</span>
            <button type="button" class="btn-remove" onclick="removeRepeaterItem('${id}')"><i class="fas fa-trash"></i> Remove</button>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label>Degree / Qualification</label>
                <input type="text" class="form-control edu-degree" value="${degree}" placeholder="e.g. B.Sc. in Computer Science" oninput="syncCvPreview()">
            </div>
            <div class="form-group">
                <label>University / School</label>
                <input type="text" class="form-control edu-school" value="${school}" placeholder="e.g. University of Colombo" oninput="syncCvPreview()">
            </div>
            <div class="form-group">
                <label>Dates / Graduation Year</label>
                <input type="text" class="form-control edu-dates" value="${dates}" placeholder="e.g. 2016 - 2020" oninput="syncCvPreview()">
            </div>
            <div class="form-group">
                <label>Honors / Highlights</label>
                <input type="text" class="form-control edu-details" value="${details}" placeholder="e.g. First Class Honors, GPA 3.8" oninput="syncCvPreview()">
            </div>
        </div>
    `;
    container.appendChild(item);
    syncCvPreview();
}

function addProjectItem(title = '', tech = '', desc = '') {
    const container = document.getElementById('projectListContainer');
    if (!container) return;

    const id = 'proj_' + Date.now();
    const item = document.createElement('div');
    item.className = 'repeater-card';
    item.id = id;
    item.innerHTML = `
        <div class="repeater-header">
            <span class="repeater-title"><i class="fas fa-project-diagram"></i> Key Project</span>
            <button type="button" class="btn-remove" onclick="removeRepeaterItem('${id}')"><i class="fas fa-trash"></i> Remove</button>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label>Project Title</label>
                <input type="text" class="form-control proj-title" value="${title}" placeholder="e.g. ATS Resume Parser" oninput="syncCvPreview()">
            </div>
            <div class="form-group">
                <label>Technologies Used</label>
                <input type="text" class="form-control proj-tech" value="${tech}" placeholder="e.g. PHP, MySQL, Docker" oninput="syncCvPreview()">
            </div>
            <div class="form-group full-width">
                <label>Description & Results</label>
                <textarea class="form-control proj-desc" placeholder="Describe project scope and key results..." oninput="syncCvPreview()">${desc}</textarea>
            </div>
        </div>
    `;
    container.appendChild(item);
    syncCvPreview();
}

function removeRepeaterItem(id) {
    const el = document.getElementById(id);
    if (el) el.remove();
    syncCvPreview();
}

// Synchronize inputs with preview document
function initLivePreviewSync() {
    const textInputs = [
        ['input_fullName', 'prev_fullName'],
        ['input_jobTitle', 'prev_jobTitle'],
        ['input_email', 'prev_email'],
        ['input_phone', 'prev_phone'],
        ['input_location', 'prev_location'],
        ['input_linkedin', 'prev_linkedin'],
        ['input_summary', 'prev_summary'],
        ['input_techSkills', 'prev_tech_skills'],
        ['input_softSkills', 'prev_soft_skills'],
        ['input_certifications', 'prev_certifications'],
        ['input_languages', 'prev_languages'],
        ['input_nicNumber', 'prev_nic']
    ];

    textInputs.forEach(([inputId, prevId]) => {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('input', () => {
                const prev = document.getElementById(prevId);
                if (prev) prev.textContent = input.value;
                calculateAtsScore();
            });
        }
    });
}

function syncCvPreview() {
    // Sync Experience List
    const expListContainer = document.getElementById('prev_experience_list');
    if (expListContainer) {
        expListContainer.innerHTML = '';
        const expCards = document.querySelectorAll('#experienceListContainer .repeater-card');
        expCards.forEach(card => {
            const role = card.querySelector('.exp-role').value;
            const company = card.querySelector('.exp-company').value;
            const location = card.querySelector('.exp-location').value;
            const dates = card.querySelector('.exp-dates').value;
            const desc = card.querySelector('.exp-desc').value;

            if (role || company) {
                const itemDiv = document.createElement('div');
                itemDiv.className = 'cv-item';
                
                const bulletsHtml = desc.split('\n')
                    .filter(line => line.trim().length > 0)
                    .map(line => `<li>${line.replace(/^•\s*/, '')}</li>`)
                    .join('');

                itemDiv.innerHTML = `
                    <div class="cv-item-header">
                        <span>${role}</span>
                        <span>${company}</span>
                    </div>
                    <div class="cv-item-subheader">
                        <span>${location}</span>
                        <span>${dates}</span>
                    </div>
                    <ul class="cv-bullets">${bulletsHtml}</ul>
                `;
                expListContainer.appendChild(itemDiv);
            }
        });
    }

    // Sync Education List
    const eduListContainer = document.getElementById('prev_education_list');
    if (eduListContainer) {
        eduListContainer.innerHTML = '';
        const eduCards = document.querySelectorAll('#educationListContainer .repeater-card');
        eduCards.forEach(card => {
            const degree = card.querySelector('.edu-degree').value;
            const school = card.querySelector('.edu-school').value;
            const dates = card.querySelector('.edu-dates').value;
            const details = card.querySelector('.edu-details').value;

            if (degree || school) {
                const itemDiv = document.createElement('div');
                itemDiv.className = 'cv-item';
                itemDiv.innerHTML = `
                    <div class="cv-item-header">
                        <span>${degree}</span>
                        <span>${school}</span>
                    </div>
                    <div class="cv-item-subheader">
                        <span>${details}</span>
                        <span>${dates}</span>
                    </div>
                `;
                eduListContainer.appendChild(itemDiv);
            }
        });
    }

    // Sync Projects List
    const projListContainer = document.getElementById('prev_projects_list');
    if (projListContainer) {
        projListContainer.innerHTML = '';
        const projCards = document.querySelectorAll('#projectListContainer .repeater-card');
        projCards.forEach(card => {
            const title = card.querySelector('.proj-title').value;
            const tech = card.querySelector('.proj-tech').value;
            const desc = card.querySelector('.proj-desc').value;

            if (title) {
                const itemDiv = document.createElement('div');
                itemDiv.className = 'cv-item';
                itemDiv.innerHTML = `
                    <div class="cv-item-header">
                        <span>${title}</span>
                        <span>${tech}</span>
                    </div>
                    <p style="margin-top: 3px;">${desc}</p>
                `;
                projListContainer.appendChild(itemDiv);
            }
        });
    }

    calculateAtsScore();
}

// Calculate ATS Compliance Score
function calculateAtsScore() {
    let score = 0;
    const tips = [];

    const name = getValue('input_fullName');
    const email = getValue('input_email');
    const phone = getValue('input_phone');
    const summary = getValue('input_summary');
    const techSkills = getValue('input_techSkills');
    const nicNumber = getValue('input_nicNumber');
    
    const expCount = document.querySelectorAll('#experienceListContainer .repeater-card').length;
    const eduCount = document.querySelectorAll('#educationListContainer .repeater-card').length;

    if (name) score += 15; else tips.push('Add full name');
    if (email && email.includes('@')) score += 15; else tips.push('Add valid email');
    if (phone) score += 10; else tips.push('Add contact phone');
    if (summary && summary.length > 50) score += 20; else tips.push('Expand professional summary (>50 chars)');
    if (expCount >= 1) score += 20; else tips.push('Add work experience');
    if (eduCount >= 1) score += 10; else tips.push('Add education details');
    if (techSkills) score += 10; else tips.push('Add technical skills');

    const scoreElem = document.getElementById('atsScoreVal');
    const tipsElem = document.getElementById('atsTipsList');

    if (scoreElem) scoreElem.textContent = score + '%';
    if (tipsElem) {
        tipsElem.innerHTML = tips.length === 0 
            ? '<li style="color:#10b981;"><i class="fas fa-check-circle"></i> Perfect ATS compliance! Ready for submission.</li>'
            : tips.map(t => `<li style="color:#f59e0b;"><i class="fas fa-exclamation-triangle"></i> ${t}</li>`).join('');
    }
}

function getValue(id) {
    const el = document.getElementById(id);
    return el ? el.value.trim() : '';
}

// Template Switcher
function initTemplateSwitcher() {
    document.querySelectorAll('.template-opt').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.template-opt').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const templateName = btn.dataset.template;
            const doc = document.getElementById('atsCvPrintArea');
            if (doc) {
                doc.className = 'ats-cv-document template-' + templateName;
            }
        });
    });
}

// PDF Export, Save Document & DB Record Trigger
function exportToPDF() {
  const modalEl = document.getElementById('saveResumeModal');
  const titleInput = document.getElementById('saveResumeTitleInput');
  const fullName = document.getElementById('input_fullName') ? document.getElementById('input_fullName').value.trim() : '';

  if (titleInput) {
    titleInput.value = fullName ? `${fullName} Resume` : 'Resume';
  }

  if (modalEl) {
    const saveModal = new bootstrap.Modal(modalEl);
    const confirmBtn = document.getElementById('confirmSaveResumeBtn');

    if (confirmBtn) {
      confirmBtn.onclick = async function() {
        const resumeTitle = (titleInput && titleInput.value.trim()) ? titleInput.value.trim() : (fullName || 'Resume');
        saveModal.hide();
        await performSaveAndExport(resumeTitle);
      };
    }

    saveModal.show();
  } else {
    performSaveAndExport(fullName || 'Resume');
  }
}

async function performSaveAndExport(resumeTitle) {
  const printArea = document.getElementById('atsCvPrintArea');
  if (!printArea) return;

  const currentPath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
  const stylesUrl = window.location.origin + currentPath + 'css/style.css';

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
    body { margin: 0; padding: 20px; font-family: 'Inter', sans-serif; background: #fff; }
    .ats-cv-document { width: 100%; max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; }
  </style>
</head>
<body>
  <div class="ats-cv-document">
    ${printArea.innerHTML}
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
