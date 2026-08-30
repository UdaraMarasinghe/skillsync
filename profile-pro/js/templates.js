/* ==========================================================================
   PROFILE-PRO CV BUILDER - TEMPLATE RENDERERS (10 UNIQUE TEMPLATES)
   ========================================================================== */

const Templates = {
  // 1. MODERN EXECUTIVE
  modern: function (data) {
    const p = data.personal || {};
    const photoShapeClass = p.photoShape === 'square' ? 'shape-square' : '';
    const avatarHtml = p.avatar 
      ? `<div class="cv-photo-frame ${photoShapeClass}"><img src="${p.avatar}" alt="Profile Photo"></div>` 
      : '';

    const contacts = [
      p.email ? `<div class="contact-list-item"><i class="fas fa-envelope"></i> <span>${p.email}</span></div>` : '',
      p.phone ? `<div class="contact-list-item"><i class="fas fa-phone"></i> <span>${p.phone}</span></div>` : '',
      p.location ? `<div class="contact-list-item"><i class="fas fa-map-marker-alt"></i> <span>${p.location}</span></div>` : '',
      p.website ? `<div class="contact-list-item"><i class="fas fa-globe"></i> <span>${p.website}</span></div>` : '',
      p.linkedin ? `<div class="contact-list-item"><i class="fab fa-linkedin"></i> <span>${p.linkedin}</span></div>` : '',
      p.github ? `<div class="contact-list-item"><i class="fab fa-github"></i> <span>${p.github}</span></div>` : ''
    ].filter(Boolean).join('');

    const skillsHtml = (data.skills || []).map(s => `<span class="skill-badge">${s}</span>`).join('');

    const experienceHtml = (data.experience || []).map(exp => `
      <div class="cv-entry">
        <div class="cv-entry-header">
          <div class="cv-entry-title">${exp.title || ''}</div>
          <div class="cv-entry-date">${exp.startDate || ''} - ${exp.endDate || 'Present'}</div>
        </div>
        <div class="cv-entry-subtitle">${exp.company || ''}${exp.location ? ' | ' + exp.location : ''}</div>
        <div class="cv-entry-description">${exp.description || ''}</div>
      </div>
    `).join('');

    const educationHtml = (data.education || []).map(edu => `
      <div class="cv-entry">
        <div class="cv-entry-header">
          <div class="cv-entry-title">${edu.degree || ''}</div>
          <div class="cv-entry-date">${edu.year || ''}</div>
        </div>
        <div class="cv-entry-subtitle">${edu.institution || ''}${edu.location ? ' | ' + edu.location : ''}</div>
        <div class="cv-entry-description">${edu.description || ''}</div>
      </div>
    `).join('');

    const projectsHtml = (data.projects || []).map(proj => `
      <div class="cv-entry">
        <div class="cv-entry-header">
          <div class="cv-entry-title">${proj.name || ''}</div>
          <div class="cv-entry-date">${proj.link ? `<a href="${proj.link}" target="_blank" style="color:var(--cv-primary-color);">${proj.link}</a>` : ''}</div>
        </div>
        <div class="cv-entry-description">${proj.description || ''}</div>
      </div>
    `).join('');

    const customHtml = (data.custom || []).map(cust => `
      <div class="cv-entry">
        <div class="cv-entry-title">${cust.title || ''}</div>
        <div class="cv-entry-description">${cust.content || ''}</div>
      </div>
    `).join('');

    return `
      <div class="template-modern">
        <div class="cv-sidebar">
          ${avatarHtml}
          <div class="cv-section-title" style="margin-top:0;">Contact</div>
          ${contacts}

          ${skillsHtml ? `
            <div class="cv-section-title">Skills</div>
            <div>${skillsHtml}</div>
          ` : ''}
        </div>

        <div class="cv-main-content">
          <div class="cv-header-name">${p.fullName || 'Your Name'}</div>
          <div class="cv-header-title">${p.jobTitle || 'Professional Title'}</div>

          ${p.summary ? `
            <div class="cv-section-title" style="margin-top:0;">Summary</div>
            <div class="cv-entry-description">${p.summary}</div>
          ` : ''}

          ${experienceHtml ? `
            <div class="cv-section-title">Work Experience</div>
            ${experienceHtml}
          ` : ''}

          ${educationHtml ? `
            <div class="cv-section-title">Education</div>
            ${educationHtml}
          ` : ''}

          ${projectsHtml ? `
            <div class="cv-section-title">Key Projects</div>
            ${projectsHtml}
          ` : ''}

          ${customHtml ? `
            <div class="cv-section-title">Additional Info</div>
            ${customHtml}
          ` : ''}
        </div>
      </div>
    `;
  },

  // 2. TECH MINIMALIST
  tech: function (data) {
    const p = data.personal || {};
    const avatarHtml = p.avatar 
      ? `<div class="cv-photo-frame"><img src="${p.avatar}" alt="Profile Photo"></div>` 
      : '';

    const contacts = [
      p.email ? `<span><i class="fas fa-envelope"></i> ${p.email}</span>` : '',
      p.phone ? `<span><i class="fas fa-phone"></i> ${p.phone}</span>` : '',
      p.location ? `<span><i class="fas fa-map-marker-alt"></i> ${p.location}</span>` : '',
      p.website ? `<span><i class="fas fa-globe"></i> ${p.website}</span>` : '',
      p.github ? `<span><i class="fab fa-github"></i> ${p.github}</span>` : ''
    ].filter(Boolean).join(' • ');

    const skillsHtml = (data.skills || []).map(s => `<span class="skill-badge">${s}</span>`).join('');

    const experienceHtml = (data.experience || []).map(exp => `
      <div class="cv-entry">
        <div class="cv-entry-header">
          <div class="cv-entry-title">${exp.title || ''} <span style="font-weight:400; color:var(--cv-secondary-color)">@ ${exp.company || ''}</span></div>
          <div class="cv-entry-date">${exp.startDate || ''} - ${exp.endDate || 'Present'}</div>
        </div>
        <div class="cv-entry-description">${exp.description || ''}</div>
      </div>
    `).join('');

    const educationHtml = (data.education || []).map(edu => `
      <div class="cv-entry">
        <div class="cv-entry-header">
          <div class="cv-entry-title">${edu.degree || ''} - ${edu.institution || ''}</div>
          <div class="cv-entry-date">${edu.year || ''}</div>
        </div>
        <div class="cv-entry-description">${edu.description || ''}</div>
      </div>
    `).join('');

    const projectsHtml = (data.projects || []).map(proj => `
      <div class="cv-entry">
        <div class="cv-entry-header">
          <div class="cv-entry-title">${proj.name || ''}</div>
          <div class="cv-entry-date">${proj.link || ''}</div>
        </div>
        <div class="cv-entry-description">${proj.description || ''}</div>
      </div>
    `).join('');

    return `
      <div class="template-tech">
        <div class="cv-top-bar">
          <div class="cv-top-left">
            ${avatarHtml}
            <div>
              <div class="cv-header-name" style="font-size:1.8em; font-weight:800; color:var(--cv-primary-color);">${p.fullName || 'Your Name'}</div>
              <div class="cv-header-title" style="font-size:1em; font-weight:600; color:var(--cv-secondary-color);">${p.jobTitle || 'Professional Title'}</div>
            </div>
          </div>
        </div>

        <div class="contact-inline" style="margin-bottom:1.5rem; justify-content:flex-start;">
          ${contacts}
        </div>

        ${p.summary ? `
          <div class="cv-section-title">About</div>
          <div class="cv-entry-description">${p.summary}</div>
        ` : ''}

        ${experienceHtml ? `
          <div class="cv-section-title">Experience</div>
          ${experienceHtml}
        ` : ''}

        ${skillsHtml ? `
          <div class="cv-section-title">Technical Expertise</div>
          <div style="margin-bottom:1rem;">${skillsHtml}</div>
        ` : ''}

        ${educationHtml ? `
          <div class="cv-section-title">Education</div>
          ${educationHtml}
        ` : ''}

        ${projectsHtml ? `
          <div class="cv-section-title">Projects</div>
          ${projectsHtml}
        ` : ''}
      </div>
    `;
  },

  // 3. CREATIVE GRADIENT
  creative: function (data) {
    const p = data.personal || {};
    const avatarHtml = p.avatar 
      ? `<div class="cv-photo-frame" style="width:100px; height:100px; border-radius:50%; border:3px solid #fff; overflow:hidden;"><img src="${p.avatar}" style="width:100%; height:100%; object-fit:cover;"></div>` 
      : '';

    const contacts = [
      p.email ? `<div class="contact-item"><i class="fas fa-envelope"></i> ${p.email}</div>` : '',
      p.phone ? `<div class="contact-item"><i class="fas fa-phone"></i> ${p.phone}</div>` : '',
      p.location ? `<div class="contact-item"><i class="fas fa-map-marker-alt"></i> ${p.location}</div>` : '',
      p.linkedin ? `<div class="contact-item"><i class="fab fa-linkedin"></i> ${p.linkedin}</div>` : ''
    ].filter(Boolean).join('');

    const skillsHtml = (data.skills || []).map(s => `<span class="skill-badge">${s}</span>`).join('');

    const experienceHtml = (data.experience || []).map(exp => `
      <div class="cv-entry">
        <div class="cv-entry-header">
          <div class="cv-entry-title">${exp.title || ''}</div>
          <div class="cv-entry-date">${exp.startDate || ''} - ${exp.endDate || 'Present'}</div>
        </div>
        <div class="cv-entry-subtitle">${exp.company || ''}${exp.location ? ' • ' + exp.location : ''}</div>
        <div class="cv-entry-description">${exp.description || ''}</div>
      </div>
    `).join('');

    const educationHtml = (data.education || []).map(edu => `
      <div class="cv-entry">
        <div class="cv-entry-header">
          <div class="cv-entry-title">${edu.degree || ''}</div>
          <div class="cv-entry-date">${edu.year || ''}</div>
        </div>
        <div class="cv-entry-subtitle">${edu.institution || ''}</div>
        <div class="cv-entry-description">${edu.description || ''}</div>
      </div>
    `).join('');

    return `
      <div class="template-creative">
        <div class="header-banner">
          <div>
            <div class="cv-header-name">${p.fullName || 'Your Name'}</div>
            <div class="cv-header-title">${p.jobTitle || 'Professional Title'}</div>
          </div>
          <div style="display:flex; align-items:center; gap:1.5rem;">
            <div>${contacts}</div>
            ${avatarHtml}
          </div>
        </div>

        ${p.summary ? `
          <div class="cv-section-title"><i class="fas fa-user-circle"></i> Profile</div>
          <div class="cv-entry-description">${p.summary}</div>
        ` : ''}

        ${experienceHtml ? `
          <div class="cv-section-title"><i class="fas fa-briefcase"></i> Work History</div>
          ${experienceHtml}
        ` : ''}

        ${skillsHtml ? `
          <div class="cv-section-title"><i class="fas fa-star"></i> Core Skills</div>
          <div style="margin-bottom:1rem;">${skillsHtml}</div>
        ` : ''}

        ${educationHtml ? `
          <div class="cv-section-title"><i class="fas fa-graduation-cap"></i> Education</div>
          ${educationHtml}
        ` : ''}
      </div>
    `;
  },

  // 4. CLASSIC ELEGANT
  classic: function (data) {
    const p = data.personal || {};

    const contacts = [
      p.email ? `<span>${p.email}</span>` : '',
      p.phone ? `<span>${p.phone}</span>` : '',
      p.location ? `<span>${p.location}</span>` : '',
      p.website ? `<span>${p.website}</span>` : ''
    ].filter(Boolean).join(' | ');

    const skillsHtml = (data.skills || []).join(' • ');

    const experienceHtml = (data.experience || []).map(exp => `
      <div class="cv-entry">
        <div class="cv-entry-header">
          <div class="cv-entry-title">${exp.title || ''}, <i>${exp.company || ''}</i></div>
          <div class="cv-entry-date">${exp.startDate || ''} - ${exp.endDate || 'Present'}</div>
        </div>
        <div class="cv-entry-description">${exp.description || ''}</div>
      </div>
    `).join('');

    const educationHtml = (data.education || []).map(edu => `
      <div class="cv-entry">
        <div class="cv-entry-header">
          <div class="cv-entry-title">${edu.degree || ''}, <i>${edu.institution || ''}</i></div>
          <div class="cv-entry-date">${edu.year || ''}</div>
        </div>
        <div class="cv-entry-description">${edu.description || ''}</div>
      </div>
    `).join('');

    return `
      <div class="template-classic">
        <div class="cv-header-name">${p.fullName || 'Your Name'}</div>
        <div class="cv-header-title">${p.jobTitle || 'Professional Title'}</div>

        <div class="cv-contact-row">
          ${contacts}
        </div>

        ${p.summary ? `
          <div class="cv-section-title">Summary</div>
          <div class="cv-entry-description" style="text-align:left;">${p.summary}</div>
        ` : ''}

        ${experienceHtml ? `
          <div class="cv-section-title">Professional Experience</div>
          ${experienceHtml}
        ` : ''}

        ${educationHtml ? `
          <div class="cv-section-title">Education</div>
          ${educationHtml}
        ` : ''}

        ${skillsHtml ? `
          <div class="cv-section-title">Skills & Expertise</div>
          <div style="text-align:left; font-size:0.9em; line-height:1.6;">${skillsHtml}</div>
        ` : ''}
      </div>
    `;
  },

  // 5. COMPACT SINGLE-COLUMN ATS
  compact: function (data) {
    const p = data.personal || {};
    const contacts = [
      p.email ? `<span>Email: ${p.email}</span>` : '',
      p.phone ? `<span>Phone: ${p.phone}</span>` : '',
      p.location ? `<span>Location: ${p.location}</span>` : '',
      p.linkedin ? `<span>LinkedIn: ${p.linkedin}</span>` : ''
    ].filter(Boolean).join(' | ');

    const skillsHtml = (data.skills || []).join(', ');

    const experienceHtml = (data.experience || []).map(exp => `
      <div class="cv-entry" style="margin-bottom:0.8rem;">
        <div class="cv-entry-header">
          <div class="cv-entry-title" style="font-weight:700;">${exp.title || ''} - ${exp.company || ''}</div>
          <div class="cv-entry-date">${exp.startDate || ''} - ${exp.endDate || 'Present'}</div>
        </div>
        <div class="cv-entry-description">${exp.description || ''}</div>
      </div>
    `).join('');

    const educationHtml = (data.education || []).map(edu => `
      <div class="cv-entry" style="margin-bottom:0.6rem;">
        <div class="cv-entry-header">
          <div class="cv-entry-title" style="font-weight:700;">${edu.degree || ''}</div>
          <div class="cv-entry-date">${edu.year || ''}</div>
        </div>
        <div class="cv-entry-subtitle">${edu.institution || ''}</div>
      </div>
    `).join('');

    return `
      <div class="template-compact">
        <div style="border-bottom: 2px solid #000; padding-bottom: 0.5rem; margin-bottom: 1rem;">
          <h1 style="font-size: 2em; text-transform: uppercase; letter-spacing: 1px; margin: 0; color:#000;">${p.fullName || 'Your Name'}</h1>
          <div style="font-size: 1.1em; font-weight: 600; color: #333; margin-top: 0.2rem;">${p.jobTitle || 'Professional Title'}</div>
          <div style="font-size: 0.85em; color: #444; margin-top: 0.4rem;">${contacts}</div>
        </div>

        ${p.summary ? `
          <div style="font-weight:700; text-transform:uppercase; border-bottom:1px solid #000; margin-bottom:0.4rem; font-size:0.95em;">PROFESSIONAL SUMMARY</div>
          <div class="cv-entry-description" style="margin-bottom:1rem;">${p.summary}</div>
        ` : ''}

        ${experienceHtml ? `
          <div style="font-weight:700; text-transform:uppercase; border-bottom:1px solid #000; margin-bottom:0.6rem; font-size:0.95em;">WORK EXPERIENCE</div>
          ${experienceHtml}
        ` : ''}

        ${educationHtml ? `
          <div style="font-weight:700; text-transform:uppercase; border-bottom:1px solid #000; margin-bottom:0.6rem; font-size:0.95em;">EDUCATION</div>
          ${educationHtml}
        ` : ''}

        ${skillsHtml ? `
          <div style="font-weight:700; text-transform:uppercase; border-bottom:1px solid #000; margin-bottom:0.4rem; font-size:0.95em;">CORE COMPETENCIES & SKILLS</div>
          <div style="font-size:0.9em; line-height:1.5;">${skillsHtml}</div>
        ` : ''}
      </div>
    `;
  },

  // 6. INFOGRAPHIC MODERN
  infographic: function (data) {
    const p = data.personal || {};
    const avatarHtml = p.avatar 
      ? `<div class="cv-photo-frame" style="width:110px; height:110px; border-radius:50%; border:3px solid var(--cv-primary-color); overflow:hidden; margin:0 auto 1rem auto;"><img src="${p.avatar}" style="width:100%; height:100%; object-fit:cover;"></div>` 
      : '';

    const contacts = [
      p.email ? `<div style="margin-bottom:0.4rem; font-size:0.85em;"><i class="fas fa-envelope" style="color:var(--cv-primary-color); width:18px;"></i> ${p.email}</div>` : '',
      p.phone ? `<div style="margin-bottom:0.4rem; font-size:0.85em;"><i class="fas fa-phone" style="color:var(--cv-primary-color); width:18px;"></i> ${p.phone}</div>` : '',
      p.location ? `<div style="margin-bottom:0.4rem; font-size:0.85em;"><i class="fas fa-map-marker-alt" style="color:var(--cv-primary-color); width:18px;"></i> ${p.location}</div>` : '',
      p.website ? `<div style="margin-bottom:0.4rem; font-size:0.85em;"><i class="fas fa-globe" style="color:var(--cv-primary-color); width:18px;"></i> ${p.website}</div>` : ''
    ].filter(Boolean).join('');

    const skillsHtml = (data.skills || []).map(s => `
      <div style="margin-bottom:0.5rem;">
        <div style="display:flex; justify-content:space-between; font-size:0.8em; font-weight:600; margin-bottom:0.2rem;">
          <span>${s}</span>
          <span style="color:var(--cv-primary-color);">Proficient</span>
        </div>
        <div style="width:100%; height:6px; background:#e2e8f0; border-radius:3px; overflow:hidden;">
          <div style="width:85%; height:100%; background:var(--cv-primary-color);"></div>
        </div>
      </div>
    `).join('');

    const experienceHtml = (data.experience || []).map(exp => `
      <div style="position:relative; padding-left:1.5rem; margin-bottom:1.2rem; border-left:2px solid var(--cv-primary-color);">
        <div style="position:absolute; left:-6px; top:2px; width:10px; height:10px; border-radius:50%; background:var(--cv-primary-color);"></div>
        <div class="cv-entry-header">
          <div class="cv-entry-title" style="color:var(--cv-primary-color);">${exp.title || ''}</div>
          <div class="cv-entry-date">${exp.startDate || ''} - ${exp.endDate || 'Present'}</div>
        </div>
        <div class="cv-entry-subtitle">${exp.company || ''}</div>
        <div class="cv-entry-description">${exp.description || ''}</div>
      </div>
    `).join('');

    return `
      <div class="template-infographic" style="display:grid; grid-template-columns: 220px 1fr; gap:1.5rem;">
        <div style="background:#f8fafc; padding:1.2rem; border-radius:12px; border:1px solid #e2e8f0;">
          ${avatarHtml}
          <div style="text-align:center; margin-bottom:1.5rem;">
            <div style="font-size:1.4em; font-weight:800; color:var(--cv-text-color);">${p.fullName || 'Your Name'}</div>
            <div style="font-size:0.9em; color:var(--cv-secondary-color); font-weight:600;">${p.jobTitle || 'Professional Title'}</div>
          </div>
          
          <div style="font-weight:700; font-size:0.85em; text-transform:uppercase; color:var(--cv-primary-color); margin-bottom:0.6rem;">Contact</div>
          ${contacts}

          <div style="font-weight:700; font-size:0.85em; text-transform:uppercase; color:var(--cv-primary-color); margin-top:1.5rem; margin-bottom:0.6rem;">Skills Matrix</div>
          ${skillsHtml}
        </div>

        <div>
          ${p.summary ? `
            <div class="cv-section-title" style="margin-top:0;">Profile Overview</div>
            <div class="cv-entry-description" style="margin-bottom:1.2rem;">${p.summary}</div>
          ` : ''}

          ${experienceHtml ? `
            <div class="cv-section-title">Career Timeline</div>
            ${experienceHtml}
          ` : ''}
        </div>
      </div>
    `;
  },

  // 7. DARK MODE LUXE
  split_dark: function (data) {
    const p = data.personal || {};
    const avatarHtml = p.avatar 
      ? `<div style="width:110px; height:110px; border-radius:50%; border:3px solid #6366f1; overflow:hidden; margin-bottom:1rem;"><img src="${p.avatar}" style="width:100%; height:100%; object-fit:cover;"></div>` 
      : '';

    const contacts = [
      p.email ? `<div style="font-size:0.85em; margin-bottom:0.3rem;"><i class="fas fa-envelope" style="color:#818cf8;"></i> ${p.email}</div>` : '',
      p.phone ? `<div style="font-size:0.85em; margin-bottom:0.3rem;"><i class="fas fa-phone" style="color:#818cf8;"></i> ${p.phone}</div>` : '',
      p.location ? `<div style="font-size:0.85em; margin-bottom:0.3rem;"><i class="fas fa-map-marker-alt" style="color:#818cf8;"></i> ${p.location}</div>` : '',
      p.linkedin ? `<div style="font-size:0.85em; margin-bottom:0.3rem;"><i class="fab fa-linkedin" style="color:#818cf8;"></i> ${p.linkedin}</div>` : ''
    ].filter(Boolean).join('');

    const skillsHtml = (data.skills || []).map(s => `
      <span style="display:inline-block; background:#334155; color:#f8fafc; padding:0.25rem 0.5rem; border-radius:4px; font-size:0.8em; margin:0.2rem;">${s}</span>
    `).join('');

    const experienceHtml = (data.experience || []).map(exp => `
      <div class="cv-entry" style="margin-bottom:1.2rem;">
        <div class="cv-entry-header">
          <div class="cv-entry-title" style="color:#818cf8; font-weight:700;">${exp.title || ''}</div>
          <div class="cv-entry-date" style="color:#94a3b8;">${exp.startDate || ''} - ${exp.endDate || 'Present'}</div>
        </div>
        <div class="cv-entry-subtitle" style="color:#cbd5e1;">${exp.company || ''}</div>
        <div class="cv-entry-description" style="color:#94a3b8;">${exp.description || ''}</div>
      </div>
    `).join('');

    return `
      <div class="template-dark" style="background:#0f172a; color:#f8fafc; margin:-18mm; padding:18mm; min-height:297mm; display:grid; grid-template-columns:230px 1fr; gap:2rem;">
        <div style="border-right:1px solid #334155; padding-right:1.5rem;">
          ${avatarHtml}
          <div style="font-size:1.6em; font-weight:800; color:#fff; line-height:1.2;">${p.fullName || 'Your Name'}</div>
          <div style="font-size:1em; color:#818cf8; font-weight:600; margin-bottom:1.5rem;">${p.jobTitle || 'Professional Title'}</div>

          <div style="font-size:0.85em; font-weight:700; text-transform:uppercase; color:#818cf8; margin-bottom:0.6rem; border-bottom:1px solid #334155; padding-bottom:0.2rem;">Contact</div>
          ${contacts}

          <div style="font-size:0.85em; font-weight:700; text-transform:uppercase; color:#818cf8; margin-top:1.5rem; margin-bottom:0.6rem; border-bottom:1px solid #334155; padding-bottom:0.2rem;">Technologies</div>
          <div>${skillsHtml}</div>
        </div>

        <div>
          ${p.summary ? `
            <div style="font-size:1em; font-weight:700; text-transform:uppercase; color:#818cf8; margin-bottom:0.6rem; border-bottom:1px solid #334155; padding-bottom:0.2rem;">Executive Summary</div>
            <div style="color:#cbd5e1; font-size:0.9em; margin-bottom:1.5rem;">${p.summary}</div>
          ` : ''}

          ${experienceHtml ? `
            <div style="font-size:1em; font-weight:700; text-transform:uppercase; color:#818cf8; margin-bottom:0.8rem; border-bottom:1px solid #334155; padding-bottom:0.2rem;">Experience</div>
            ${experienceHtml}
          ` : ''}
        </div>
      </div>
    `;
  },

  // 8. EMERALD CORPORATE
  emerald: function (data) {
    const p = data.personal || {};

    const contacts = [
      p.email ? `<span><i class="fas fa-envelope"></i> ${p.email}</span>` : '',
      p.phone ? `<span><i class="fas fa-phone"></i> ${p.phone}</span>` : '',
      p.location ? `<span><i class="fas fa-map-marker-alt"></i> ${p.location}</span>` : '',
      p.linkedin ? `<span><i class="fab fa-linkedin"></i> ${p.linkedin}</span>` : ''
    ].filter(Boolean).join(' • ');

    const skillsHtml = (data.skills || []).map(s => `<span class="skill-badge" style="border-left-color:#059669; background:#ecfdf5;">${s}</span>`).join('');

    const experienceHtml = (data.experience || []).map(exp => `
      <div class="cv-entry">
        <div class="cv-entry-header">
          <div class="cv-entry-title" style="color:#047857;">${exp.title || ''}</div>
          <div class="cv-entry-date">${exp.startDate || ''} - ${exp.endDate || 'Present'}</div>
        </div>
        <div class="cv-entry-subtitle">${exp.company || ''}</div>
        <div class="cv-entry-description">${exp.description || ''}</div>
      </div>
    `).join('');

    return `
      <div class="template-emerald">
        <div style="background:#047857; color:#fff; margin:-18mm -18mm 1.5rem -18mm; padding:15mm 18mm;">
          <div style="font-size:2.2em; font-weight:800;">${p.fullName || 'Your Name'}</div>
          <div style="font-size:1.1em; opacity:0.9; font-weight:500;">${p.jobTitle || 'Professional Title'}</div>
          <div style="margin-top:0.8rem; font-size:0.85em; opacity:0.95;">${contacts}</div>
        </div>

        ${p.summary ? `
          <div class="cv-section-title" style="color:#047857; border-bottom:2px solid #047857;">Summary</div>
          <div class="cv-entry-description">${p.summary}</div>
        ` : ''}

        ${experienceHtml ? `
          <div class="cv-section-title" style="color:#047857; border-bottom:2px solid #047857;">Work Experience</div>
          ${experienceHtml}
        ` : ''}

        ${skillsHtml ? `
          <div class="cv-section-title" style="color:#047857; border-bottom:2px solid #047857;">Key Skills</div>
          <div>${skillsHtml}</div>
        ` : ''}
      </div>
    `;
  },

  // 9. NORDIC MINIMAL
  nordic: function (data) {
    const p = data.personal || {};

    const contacts = [
      p.email ? `<div>${p.email}</div>` : '',
      p.phone ? `<div>${p.phone}</div>` : '',
      p.location ? `<div>${p.location}</div>` : ''
    ].filter(Boolean).join('');

    const skillsHtml = (data.skills || []).join(' / ');

    const experienceHtml = (data.experience || []).map(exp => `
      <div class="cv-entry" style="margin-bottom:1.5rem;">
        <div style="font-size:0.8em; text-transform:uppercase; letter-spacing:1px; color:#64748b;">${exp.startDate || ''} — ${exp.endDate || 'Present'}</div>
        <div style="font-size:1.1em; font-weight:700;">${exp.title || ''}</div>
        <div style="font-size:0.9em; color:#475569; font-weight:500;">${exp.company || ''}</div>
        <div class="cv-entry-description" style="margin-top:0.4rem;">${exp.description || ''}</div>
      </div>
    `).join('');

    return `
      <div class="template-nordic" style="letter-spacing:0.2px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:2.5rem;">
          <div>
            <h1 style="font-size:2.5em; font-weight:300; letter-spacing:-1px; margin:0;">${p.fullName || 'Your Name'}</h1>
            <div style="font-size:1.1em; color:#64748b; font-weight:400; margin-top:0.3rem;">${p.jobTitle || 'Professional Title'}</div>
          </div>
          <div style="text-align:right; font-size:0.85em; color:#64748b; line-height:1.6;">
            ${contacts}
          </div>
        </div>

        ${p.summary ? `
          <div style="font-size:0.75em; text-transform:uppercase; letter-spacing:2px; color:#94a3b8; font-weight:700; margin-bottom:0.6rem;">About</div>
          <div style="font-size:0.95em; line-height:1.7; color:#334155; margin-bottom:2rem;">${p.summary}</div>
        ` : ''}

        ${experienceHtml ? `
          <div style="font-size:0.75em; text-transform:uppercase; letter-spacing:2px; color:#94a3b8; font-weight:700; margin-bottom:1rem;">Experience</div>
          ${experienceHtml}
        ` : ''}

        ${skillsHtml ? `
          <div style="font-size:0.75em; text-transform:uppercase; letter-spacing:2px; color:#94a3b8; font-weight:700; margin-bottom:0.6rem;">Capabilities</div>
          <div style="font-size:0.9em; color:#334155; line-height:1.6;">${skillsHtml}</div>
        ` : ''}
      </div>
    `;
  },

  // 10. BOLD LEFT COLUMN
  bold_sidebar: function (data) {
    const p = data.personal || {};
    const avatarHtml = p.avatar 
      ? `<div style="width:120px; height:120px; border-radius:50%; border:4px solid #fff; overflow:hidden; margin:0 auto 1.5rem auto;"><img src="${p.avatar}" style="width:100%; height:100%; object-fit:cover;"></div>` 
      : '';

    const contacts = [
      p.email ? `<div style="margin-bottom:0.5rem; font-size:0.85em;"><i class="fas fa-envelope"></i> ${p.email}</div>` : '',
      p.phone ? `<div style="margin-bottom:0.5rem; font-size:0.85em;"><i class="fas fa-phone"></i> ${p.phone}</div>` : '',
      p.location ? `<div style="margin-bottom:0.5rem; font-size:0.85em;"><i class="fas fa-map-marker-alt"></i> ${p.location}</div>` : ''
    ].filter(Boolean).join('');

    const skillsHtml = (data.skills || []).map(s => `<div style="background:rgba(255,255,255,0.15); padding:0.3rem 0.6rem; border-radius:4px; font-size:0.8em; margin-bottom:0.3rem;">${s}</div>`).join('');

    const experienceHtml = (data.experience || []).map(exp => `
      <div class="cv-entry">
        <div class="cv-entry-header">
          <div class="cv-entry-title" style="font-size:1.1em; color:var(--cv-primary-color);">${exp.title || ''}</div>
          <div class="cv-entry-date">${exp.startDate || ''} - ${exp.endDate || 'Present'}</div>
        </div>
        <div class="cv-entry-subtitle">${exp.company || ''}</div>
        <div class="cv-entry-description">${exp.description || ''}</div>
      </div>
    `).join('');

    return `
      <div class="template-bold" style="margin:-18mm; display:grid; grid-template-columns: 240px 1fr; min-height:297mm;">
        <div style="background:var(--cv-primary-color); color:#fff; padding:18mm 15mm;">
          ${avatarHtml}
          <div style="font-size:1.6em; font-weight:800; text-align:center; margin-bottom:0.2rem;">${p.fullName || 'Your Name'}</div>
          <div style="font-size:0.95em; opacity:0.9; text-align:center; margin-bottom:2rem; font-weight:500;">${p.jobTitle || 'Professional Title'}</div>

          <div style="font-size:0.85em; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin-bottom:0.8rem; border-bottom:1px solid rgba(255,255,255,0.3); padding-bottom:0.2rem;">Contact</div>
          ${contacts}

          <div style="font-size:0.85em; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin-top:2rem; margin-bottom:0.8rem; border-bottom:1px solid rgba(255,255,255,0.3); padding-bottom:0.2rem;">Skills</div>
          ${skillsHtml}
        </div>

        <div style="padding:18mm 18mm;">
          ${p.summary ? `
            <div class="cv-section-title" style="margin-top:0;">Profile Summary</div>
            <div class="cv-entry-description" style="margin-bottom:1.5rem;">${p.summary}</div>
          ` : ''}

          ${experienceHtml ? `
            <div class="cv-section-title">Professional Experience</div>
            ${experienceHtml}
          ` : ''}
        </div>
      </div>
    `;
  }
};
