<?php
include_once __DIR__ . '/../includes/header.php';
?>
<!-- FontAwesome Icons & Offline MediaPipe Dependencies -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="assets/mediapipe/camera_utils/camera_utils.js"></script>
<script src="assets/mediapipe/face_mesh/face_mesh.js"></script>
<script src="assets/mediapipe/pose/pose.js"></script>
<script src="tracker/js/tracker.js"></script>
<style>
    :root {
        --bg-body: #f8faf9;
        --bg-card: #ffffff;
        --bg-bot-msg: #f1f7f5;
        --bg-user-msg: #004743;
        --text-user-msg: #ACFF78;
        --text-main: #212529;
        --text-muted: #6c757d;
        --border-color: #004743;
        --accent-color: #004743;
        --accent-hover: #003633;
        --accent-secondary: #ACFF78;
        --success-color: #10b981;
        --warning-color: #f59e0b;
        --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --shadow-lg: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        --radius-lg: 12px;
        --radius-md: 8px;
        --radius-sm: 4px;
    }

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
        font-family: 'Poppins', sans-serif;
    }

    .chat-container {
        width: 100%;
        height: 80vh;
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border: 2px solid var(--border-color);
    }

    /* Progress Bar */
    .progress-bar-container {
        padding: 12px 24px;
        background: #f8faf9;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .progress-track {
        flex: 1;
        height: 8px;
        background: #e2e8f0;
        border-radius: 4px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        width: 0%;
        background: linear-gradient(90deg, var(--accent-color), var(--accent-secondary));
        border-radius: 4px;
        transition: width 0.4s ease;
    }

    .progress-badge {
        font-size: 0.82rem;
        font-weight: 700;
        color: var(--accent-secondary);
        background: var(--accent-color);
        padding: 4px 10px;
        border-radius: var(--radius-sm);
        text-transform: uppercase;
    }

    /* Chat Body / Messages Area */
    .chat-messages {
        flex: 1;
        padding: 24px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 18px;
        background: #ffffff;
    }

    .message-wrapper {
        display: flex;
        gap: 12px;
        max-width: 82%;
    }

    .message-wrapper.bot {
        align-self: flex-start;
    }

    .message-wrapper.user {
        align-self: flex-end;
        flex-direction: row-reverse;
    }

    .avatar {
        width: 36px;
        height: 36px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        flex-shrink: 0;
    }

    .bot .avatar {
        background: var(--accent-color);
        color: var(--accent-secondary);
    }

    .user .avatar {
        background: var(--accent-color);
        color: #ffffff;
    }

    .message-content {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .message-bubble {
        padding: 14px 18px;
        border-radius: var(--radius-md);
        font-size: 0.95rem;
        line-height: 1.5;
        white-space: pre-wrap;
        word-break: break-word;
    }

    .bot .message-bubble {
        background: var(--bg-bot-msg);
        color: var(--text-main);
        border-top-left-radius: 4px;
        border: 1px solid #e2e8f0;
    }

    .user .message-bubble {
        background: var(--bg-user-msg);
        color: var(--text-user-msg);
        border-top-right-radius: 4px;
    }

    .message-time {
        font-size: 0.72rem;
        color: var(--text-muted);
        margin-top: 2px;
    }

    .user .message-time {
        text-align: right;
    }

    /* Input Bar */
    .chat-input-area {
        padding: 16px 24px;
        background: #ffffff;
        border-top: 1px solid var(--border-color);
    }

    .input-form {
        display: flex;
        gap: 12px;
        align-items: flex-end;
    }

    .textarea-wrapper {
        flex: 1;
        position: relative;
    }

    textarea {
        width: 100%;
        height: 52px;
        max-height: 150px;
        padding: 14px 16px;
        border: 1.5px solid var(--border-color);
        border-radius: var(--radius-md);
        font-size: 0.95rem;
        resize: none;
        outline: none;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        background: #fafafa;
    }

    textarea:focus {
        border-color: var(--accent-color);
        box-shadow: 0 0 0 3px rgba(0, 71, 67, 0.15);
        background: #ffffff;
    }

    .btn-send {
        height: 52px;
        padding: 0 24px;
        background: var(--accent-color);
        color: white;
        border: none;
        border-radius: var(--radius-md);
        font-weight: 600;
        font-size: 0.95rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: background 0.2s ease, transform 0.1s ease;
        text-transform: uppercase;
    }

    .btn-send:hover {
        background: var(--accent-hover);
        color: var(--accent-secondary);
    }

    .btn-send:disabled {
        background: #94a3b8;
        cursor: not-allowed;
    }

    /* Start Setup Screen overlay inside chat */
    .start-screen {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        text-align: center;
        padding: 30px;
    }

    .start-icon {
        width: 64px;
        height: 64px;
        background: rgba(0, 71, 67, 0.08);
        color: var(--accent-color);
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        margin-bottom: 16px;
    }

    .start-screen h3 {
        font-size: 1.4rem;
        margin-bottom: 8px;
        color: var(--text-main);
        text-transform: uppercase;
        font-weight: 700;
    }

    .start-screen p {
        color: var(--text-muted);
        font-size: 0.92rem;
        max-width: 480px;
        margin-bottom: 24px;
        line-height: 1.5;
    }

    /* Form selectors container */
    .setup-box {
        background: #f8faf9;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 20px;
        width: 100%;
        max-width: 440px;
        margin-bottom: 24px;
        display: flex;
        flex-direction: column;
        gap: 16px;
        text-align: left;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .form-group label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 6px;
        text-transform: uppercase;
    }

    .select-input {
        width: 100%;
        padding: 10px 14px;
        border: 1.5px solid var(--border-color);
        border-radius: var(--radius-sm);
        background: #ffffff;
        font-size: 0.9rem;
        color: var(--text-main);
        outline: none;
        transition: border-color 0.2s ease;
    }

    .select-input:focus {
        border-color: var(--accent-color);
    }

    .btn-start {
        padding: 14px 36px;
        background: var(--accent-color);
        color: white;
        border: none;
        border-radius: var(--radius-md);
        font-size: 1rem;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(0, 71, 67, 0.3);
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        text-transform: uppercase;
    }

    .btn-start:hover {
        background: var(--accent-hover);
        color: var(--accent-secondary);
        transform: translateY(-1px);
    }

    /* Typing indicator animation */
    .typing-indicator {
        display: flex;
        align-items: center;
        gap: 4px;
        padding: 8px 12px;
    }

    .typing-dot {
        width: 8px;
        height: 8px;
        background: #94a3b8;
        border-radius: 50%;
        animation: blink 1.4s infinite ease-in-out both;
    }

    .typing-dot:nth-child(1) { animation-delay: -0.32s; }
    .typing-dot:nth-child(2) { animation-delay: -0.16s; }

    @keyframes blink {
        0%, 80%, 100% { transform: scale(0); }
        40% { transform: scale(1.0); }
    }

    /* Floating Separate Camera Window Widget */
    .floating-cam-window {
        display: none;
        position: fixed;
        top: 85px;
        right: 24px;
        width: 290px;
        z-index: 1050;
        background: var(--accent-color, #004743);
        border: 2px solid var(--accent-secondary, #ACFF78);
        border-radius: var(--radius-lg, 12px);
        box-shadow: 0 12px 32px rgba(0, 71, 67, 0.35);
        overflow: hidden;
        transition: opacity 0.25s ease, transform 0.25s ease;
    }
    .floating-cam-header {
        background: #003633;
        color: #ffffff;
        padding: 8px 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 0.8rem;
        font-weight: 700;
        border-bottom: 1px solid rgba(172, 255, 120, 0.25);
    }
    .floating-cam-body {
        position: relative;
        width: 100%;
        height: 185px;
        background: #090d16;
    }
    .cam-preloader-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: #003633;
        color: #ffffff;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 14px;
        text-align: center;
        z-index: 10;
    }

    @media (max-width: 640px) {
        .chat-container { height: 100vh; border-radius: 0; }
        .message-wrapper { max-width: 90%; }
        .floating-cam-window { right: 10px; top: 70px; width: 240px; }
    }
</style>

<div class="container-fluid header-container-padding py-4" style="background-color: #f8faf9; min-height: 85vh;">

    <!-- Page Header & Actions Bar -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4" style="margin: 0 auto 1.5rem auto;">
        <div>
            <h1 class="section-title mb-1"><i class="fa-solid fa-user-astronaut me-2" style="color: var(--brand-dark);"></i>Intervia AI Practice</h1>
            <p class="section-subtitle mb-0">AI Technical Mock Interviewer with Real-time Eye & Posture Tracking</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-brand rounded-8px" id="camToggleHeaderBtn" onclick="toggleCamPreviewVisibility()">
                <i class="fa-solid fa-video me-1"></i> Preview: ON
            </button>
            <button class="btn btn-danger rounded-8px" id="endSessionBtn" onclick="confirmEndSession()" style="display: none;">
                <i class="fa-solid fa-flag-checkered me-1"></i> End Session & Report
            </button>
            <button class="btn btn-brand rounded-8px" id="restartBtn" onclick="resetToSetupScreen()">
                <i class="fa-solid fa-rotate-right me-1"></i> Reset / Topic
            </button>
        </div>
    </div>

    <!-- Camera Floating Preview Window Widget (Themed & Draggable) -->
    <div id="cam-preview-box" class="floating-cam-window">
        <!-- Window Header Bar (Drag Handle) -->
        <div class="floating-cam-header">
            <span><i class="fa-solid fa-grip-vertical text-white-50 me-2" style="cursor: move;"></i><i class="fa-solid fa-video text-accent me-1"></i> Intervia AI Vision</span>
            <div>
                <button type="button" class="btn btn-sm text-white p-0" onclick="toggleCamPreviewVisibility()" title="Minimize / Hide Preview"><i class="fa-solid fa-minus text-white"></i></button>
            </div>
        </div>

        <!-- Window Body -->
        <div class="floating-cam-body">
            <!-- Preloader Overlay -->
            <div id="cam-preloader" class="cam-preloader-overlay">
                <div class="spinner-border text-accent spinner-border-sm mb-2" role="status"></div>
                <div class="fw-bold text-accent" style="font-size: 0.82rem;"><i class="fa-solid fa-camera me-1"></i> Initializing Camera & AI...</div>
            </div>

            <video id="webcam" autoplay playsinline muted style="width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1);"></video>
            <canvas id="hud-canvas" style="position: absolute; top:0; left:0; width: 100%; height: 100%; transform: scaleX(-1); pointer-events: none;"></canvas>
            
            <div id="expression-badge" style="position: absolute; top: 5px; left: 5px; font-size: 0.65rem; padding: 3px 7px; border-radius: 6px; background: rgba(0, 71, 67, 0.9); color: #ACFF78; display: flex; align-items: center; gap: 4px; font-weight: 600; border: 1px solid #ACFF78; backdrop-filter: blur(4px); z-index: 2;">
                <span id="expression-icon">😊</span>
                <span id="expression-val">Normal</span>
            </div>

            <div id="top-notification-banner" class="floating-toast-notification state-confident" style="position: absolute; bottom: 5px; left: 5px; right: 5px; font-size: 0.7rem; padding: 3px 8px; border-radius: 6px; background: rgba(0, 54, 51, 0.9); color: #ACFF78; display: flex; align-items: center; justify-content: space-between; border: 1px solid rgba(172, 255, 120, 0.4);">
                <span id="notification-text" style="font-weight: 600;">Confident</span>
                <span id="notification-icon" style="font-size: 0.7rem;">✨</span>
            </div>
        </div>
    </div>

    <!-- Hidden tracker compatibility elements -->
    <div style="display: none;">
        <span id="status-dot"></span>
        <span id="status-text"></span>
        <span id="session-timer"></span>
        <span id="eye-score-val"></span>
        <div id="eye-score-bar"></div>
        <span id="posture-score-val"></span>
        <div id="posture-score-bar"></div>
        <span id="overall-score-val"></span>
        <circle id="gauge-fill"></circle>
        <span id="gaze-direction-val"></span>
        <span id="shoulder-tilt-val"></span>
        <span id="posture-status-val"></span>
        <span id="expression-score-val"></span>
    </div>

    <!-- Chat Container -->
    <div class="chat-container mx-auto">

        <!-- Progress Tracker -->
        <div class="progress-bar-container" id="progressContainer" style="display: none;">
            <div class="progress-track">
                <div class="progress-fill" id="progressFill"></div>
            </div>
            <div class="progress-badge" id="progressText">Question 1/10</div>
        </div>

        <!-- Chat Area -->
        <div class="chat-messages" id="chatMessages">
            <!-- Start Screen / Setup -->
            <div class="start-screen" id="startScreen">
                <div class="start-icon">
                    <i class="fa-solid fa-sliders"></i>
                </div>
                <h3>Configure Your Interview Session</h3>
                <p>
                    Select your preferred difficulty tier and subject field before starting. You will be asked <b>10 questions</b> tailored to your choice.
                </p>

                <div class="setup-box">
                    <div class="form-group">
                        <label for="tierSelect">
                            <i class="fa-solid fa-layer-group" style="color: var(--accent-color);"></i>
                            Difficulty Level (Tier)
                        </label>
                        <select id="tierSelect" class="select-input">
                            <option value="all">⚡ All Tiers (Mixed)</option>
                            <option value="beginner">🌱 Beginner</option>
                            <option value="intermediate">🚀 Intermediate</option>
                            <option value="advanced">🔥 Advanced</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fieldSelect">
                            <i class="fa-solid fa-book-open" style="color: var(--accent-color);"></i>
                            Subject Field
                        </label>
                        <select id="fieldSelect" class="select-input">
                            <option value="all">🌐 All Fields (Mixed)</option>
                        </select>
                    </div>
                </div>

                <button class="btn-start" onclick="startNewSession()">
                    <i class="fa-solid fa-play"></i> Start Interview
                </button>
            </div>
        </div>

        <!-- Input Area -->
        <div class="chat-input-area">
            <form class="input-form" id="chatForm" onsubmit="handleSend(event)">
                <div class="textarea-wrapper">
                    <textarea 
                        id="userInput" 
                        placeholder="Type your interview answer here..." 
                        rows="1"
                        disabled
                        onkeydown="handleKeyDown(event)"></textarea>
                </div>
                <button type="submit" class="btn-send" id="sendBtn" disabled>
                    <span>Send</span>
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </form>
        </div>
    </div>

    <script>
        let currentQuestionNo = 0;
        let isSessionActive = false;

        document.addEventListener('DOMContentLoaded', () => {
            loadOptions();
            checkStatus();
            makeCamPreviewDraggable();
        });

        function loadOptions() {
            fetch('api.php?action=get_options')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success' && data.fields) {
                        const fieldSelect = document.getElementById('fieldSelect');
                        fieldSelect.innerHTML = '<option value="all">🌐 All Fields (Mixed)</option>';
                        data.fields.forEach(field => {
                            const opt = document.createElement('option');
                            opt.value = field;
                            opt.textContent = field;
                            fieldSelect.appendChild(opt);
                        });
                    }
                })
                .catch(err => console.error('Failed to load setup options', err));
        }

        function checkStatus() {
            fetch('api.php?action=get_status')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success' && data.quiz_active && data.chat_history.length > 0) {
                        isSessionActive = true;
                        currentQuestionNo = data.current_question_no;
                        renderChatHistory(data.chat_history);
                        updateProgress(currentQuestionNo, data.total_questions || 15);
                        enableInput(true);
                        
                        if (currentQuestionNo <= 5) {
                            startCameraTracker();
                        } else {
                            stopCameraTracker();
                        }
                    }
                })
                .catch(err => console.error('Status check failed', err));
        }

        function startNewSession() {
            const tier = document.getElementById('tierSelect').value;
            const field = document.getElementById('fieldSelect').value;

            showTypingIndicator();

            const formData = new FormData();
            formData.append('tier', tier);
            formData.append('category', field);

            fetch('api.php?action=start_quiz', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                removeTypingIndicator();
                if (data.status === 'success') {
                    isSessionActive = true;
                    currentQuestionNo = 1;
                    renderChatHistory(data.chat_history);
                    updateProgress(1, data.total_questions || 15);
                    enableInput(true);
                    startCameraTracker();
                } else {
                    alert(data.message || 'Failed to start interview.');
                }
            })
            .catch(err => {
                removeTypingIndicator();
                console.error(err);
                alert('Connection error starting interview session.');
            });
        }

        function resetToSetupScreen() {
            stopCameraTracker();
            fetch('api.php?action=reset')
                .then(() => {
                    isSessionActive = false;
                    enableInput(false);
                    document.getElementById('progressContainer').style.display = 'none';
                    const container = document.getElementById('chatMessages');
                    container.innerHTML = `
                        <div class="start-screen" id="startScreen">
                            <div class="start-icon">
                                <i class="fa-solid fa-sliders"></i>
                            </div>
                            <p>
                                Select your preferred difficulty tier and subject field before starting. The session will start with <b>5 general intro questions</b> (with AI eye & posture tracking), followed by <b>10 technical dataset questions</b>.
                            </p>

                            <div class="setup-box">
                                <div class="form-group">
                                    <label for="tierSelect">
                                        <i class="fa-solid fa-layer-group" style="color: var(--accent-color);"></i>
                                        Difficulty Level (Tier)
                                    </label>
                                    <select id="tierSelect" class="select-input">
                                        <option value="all">⚡ All Tiers (Mixed)</option>
                                        <option value="beginner">🌱 Beginner</option>
                                        <option value="intermediate">🚀 Intermediate</option>
                                        <option value="advanced">🔥 Advanced</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="fieldSelect">
                                        <i class="fa-solid fa-book-open" style="color: var(--accent-color);"></i>
                                        Subject Field
                                    </label>
                                    <select id="fieldSelect" class="select-input">
                                        <option value="all">🌐 All Fields (Mixed)</option>
                                    </select>
                                </div>
                            </div>

                            <button class="btn-start" onclick="startNewSession()">
                                <i class="fa-solid fa-play"></i> Start Interview
                            </button>
                        </div>
                    `;
                    loadOptions();
                });
        }

        let isCamPreviewVisible = true;

        function toggleCamPreviewVisibility() {
            const camBox = document.getElementById('cam-preview-box');
            const headerBtn = document.getElementById('camToggleHeaderBtn');
            if (!camBox) return;

            isCamPreviewVisible = !isCamPreviewVisible;
            if (isCamPreviewVisible) {
                camBox.style.display = 'block';
                if (headerBtn) headerBtn.innerHTML = '<i class="fa-solid fa-video me-1"></i> Preview: ON';
            } else {
                camBox.style.display = 'none';
                if (headerBtn) headerBtn.innerHTML = '<i class="fa-solid fa-video-slash me-1"></i> Preview: OFF (Tracking BG)';
            }
        }

        function makeCamPreviewDraggable() {
            const box = document.getElementById('cam-preview-box');
            const header = box ? box.querySelector('.floating-cam-header') : null;
            if (!box || !header) return;

            let isDragging = false;
            let startX, startY, initialLeft, initialTop;

            header.style.cursor = 'move';
            header.style.userSelect = 'none';

            function onPointerDown(e) {
                isDragging = true;
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;

                startX = clientX;
                startY = clientY;

                const rect = box.getBoundingClientRect();
                initialLeft = rect.left;
                initialTop = rect.top;

                box.style.right = 'auto';
                box.style.left = initialLeft + 'px';
                box.style.top = initialTop + 'px';

                document.addEventListener('mousemove', onPointerMove);
                document.addEventListener('mouseup', onPointerUp);
                document.addEventListener('touchmove', onPointerMove, { passive: false });
                document.addEventListener('touchend', onPointerUp);
            }

            function onPointerMove(e) {
                if (!isDragging) return;
                if (e.cancelable) e.preventDefault();

                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;

                const dx = clientX - startX;
                const dy = clientY - startY;

                box.style.left = (initialLeft + dx) + 'px';
                box.style.top = (initialTop + dy) + 'px';
            }

            function onPointerUp() {
                isDragging = false;
                document.removeEventListener('mousemove', onPointerMove);
                document.removeEventListener('mouseup', onPointerUp);
                document.removeEventListener('touchmove', onPointerMove);
                document.removeEventListener('touchend', onPointerUp);
            }

            header.addEventListener('mousedown', onPointerDown);
            header.addEventListener('touchstart', onPointerDown, { passive: false });
        }

        function startCameraTracker() {
            const camBox = document.getElementById('cam-preview-box');
            if (camBox && isCamPreviewVisible) {
                camBox.style.display = 'block';
            }
            const preloader = document.getElementById('cam-preloader');
            if (preloader && (!window.trackerInstance || !window.trackerInstance.isTracking)) {
                preloader.style.display = 'flex';
            }

            if (!window.trackerInstance && typeof AIConfidenceTracker !== 'undefined') {
                window.trackerInstance = new AIConfidenceTracker();
            }

            if (window.trackerInstance && typeof window.trackerInstance.initMediaPipe === 'function') {
                if (!window.trackerInstance.isTracking) {
                    window.trackerInstance.initMediaPipe().catch(e => console.warn('Tracker camera init warning:', e));
                } else if (preloader) {
                    preloader.style.display = 'none';
                }
            }
        }

        function stopCameraTracker() {
            const camBox = document.getElementById('cam-preview-box');
            if (camBox) camBox.style.display = 'none';
        }

        function handleSend(event) {
            event.preventDefault();
            const inputEl = document.getElementById('userInput');
            const text = inputEl.value.trim();

            if (!text || !isSessionActive) return;

            appendMessage('user', text, getCurrentTime());
            inputEl.value = '';
            inputEl.style.height = '52px';
            enableInput(false);

            showTypingIndicator();

            const formData = new FormData();
            formData.append('answer', text);

            if (currentQuestionNo <= 5 && window.trackerInstance) {
                formData.append('confidence_score', window.trackerInstance.overallScore || 75);
            }

            fetch('api.php?action=submit_answer', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                removeTypingIndicator();
                if (data.status === 'success') {
                    renderChatHistory(data.chat_history);
                    currentQuestionNo = data.current_question_no;
                    updateProgress(currentQuestionNo, data.total_questions || 15, data.is_finished);

                    if (currentQuestionNo > 5 || data.is_finished) {
                        stopCameraTracker();
                    }

                    if (data.is_finished) {
                        isSessionActive = false;
                        enableInput(false);
                        const endBtn = document.getElementById('endSessionBtn');
                        if (endBtn) endBtn.style.display = 'none';

                        if (data.report) {
                            setTimeout(() => showPerformanceReportModal(data.report), 800);
                        }
                    } else {
                        enableInput(true);
                        const endBtn = document.getElementById('endSessionBtn');
                        if (endBtn) endBtn.style.display = 'inline-block';
                    }
                } else {
                    alert(data.message || 'Error evaluating answer.');
                    enableInput(true);
                }
            })
            .catch(err => {
                removeTypingIndicator();
                console.error(err);
                alert('Connection error submitting answer.');
                enableInput(true);
            });
        }

        function shouldDisplayInChat(item) {
            if (!item || !item.text) return false;
            
            // Show User Answers
            if (item.sender === 'user') return true;

            const text = item.text;

            // Show Final Score Summary
            if (text.includes('Interview Complete!') || item.is_summary || text.includes('Final Combined Score')) return true;

            // Explicitly hide any evaluation feedback, reference answers, or acknowledgments
            if (text.includes('Score:') || text.includes('Reference Answer:') || text.includes('Thank you for sharing!') || text.includes('Matching Keywords:')) {
                return false;
            }

            // Show Welcome Message
            if (text.includes('Welcome to your AI Mock Interview Session')) return true;
            
            // Show Questions
            if (text.includes('📌') || (text.includes('Question ') && text.includes(' of '))) return true;

            return false;
        }

        function renderChatHistory(history) {
            const container = document.getElementById('chatMessages');
            document.getElementById('startScreen')?.remove();
            container.innerHTML = '';

            if (Array.isArray(history)) {
                history.forEach(item => {
                    if (shouldDisplayInChat(item)) {
                        appendMessage(item.sender, item.text, item.timestamp);
                    }
                });
            }

            scrollToBottom();
        }

        function appendMessage(sender, text, time) {
            const container = document.getElementById('chatMessages');
            document.getElementById('startScreen')?.remove();

            const wrapper = document.createElement('div');
            wrapper.className = `message-wrapper ${sender}`;

            const avatar = document.createElement('div');
            avatar.className = 'avatar';
            avatar.innerHTML = sender === 'bot' 
                ? '<i class="fa-solid fa-robot"></i>' 
                : '<i class="fa-solid fa-user"></i>';

            const content = document.createElement('div');
            content.className = 'message-content';

            const bubble = document.createElement('div');
            bubble.className = 'message-bubble';
            
            let formattedText = escapeHtml(text)
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/`([^`]+)`/g, '<code style="background:#f1f7f5; padding:2px 6px; border-radius:4px; color:#004743;">$1</code>')
                .replace(/\n/g, '<br>');

            bubble.innerHTML = formattedText;

            const timeEl = document.createElement('div');
            timeEl.className = 'message-time';
            timeEl.innerText = time || getCurrentTime();

            content.appendChild(bubble);
            content.appendChild(timeEl);

            wrapper.appendChild(avatar);
            wrapper.appendChild(content);

            container.appendChild(wrapper);
            scrollToBottom();
        }

        function showTypingIndicator() {
            const container = document.getElementById('chatMessages');
            let indicator = document.getElementById('typingIndicator');
            if (indicator) return;

            indicator = document.createElement('div');
            indicator.id = 'typingIndicator';
            indicator.className = 'message-wrapper bot';
            indicator.innerHTML = `
                <div class="avatar"><i class="fa-solid fa-robot"></i></div>
                <div class="message-content">
                    <div class="message-bubble" style="padding: 10px 16px;">
                        <div class="typing-indicator">
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(indicator);
            scrollToBottom();
        }

        function removeTypingIndicator() {
            document.getElementById('typingIndicator')?.remove();
        }

        function updateProgress(qNo, totalQuestions = 15, isFinished = false) {
            const container = document.getElementById('progressContainer');
            const fill = document.getElementById('progressFill');
            const badge = document.getElementById('progressText');

            container.style.display = 'flex';

            if (isFinished) {
                fill.style.width = '100%';
                badge.innerText = 'Completed!';
                badge.style.background = '#d1fae5';
                badge.style.color = '#065f46';
            } else {
                const percent = (qNo / totalQuestions) * 100;
                fill.style.width = `${percent}%`;
                badge.innerText = `Question ${qNo}/${totalQuestions}`;
                badge.style.background = '#eff6ff';
                badge.style.color = '#2563eb';
            }
        }

        function enableInput(enable) {
            const inputEl = document.getElementById('userInput');
            const btnEl = document.getElementById('sendBtn');
            inputEl.disabled = !enable;
            btnEl.disabled = !enable;

            if (enable) {
                inputEl.focus();
            }
        }

        function handleKeyDown(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                handleSend(event);
            }
            
            const el = event.target;
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 120) + 'px';
        }

        function scrollToBottom() {
            const container = document.getElementById('chatMessages');
            container.scrollTop = container.scrollHeight;
        }

        function getCurrentTime() {
            const now = new Date();
            return now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        function confirmEndSession() {
            if (confirm('Are you sure you want to end this interview session and generate your performance report?')) {
                fetch('api.php?action=end_session')
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success' && data.report) {
                            stopCameraTracker();
                            isSessionActive = false;
                            enableInput(false);
                            const endBtn = document.getElementById('endSessionBtn');
                            if (endBtn) endBtn.style.display = 'none';
                            showPerformanceReportModal(data.report);
                        } else {
                            alert(data.message || 'Unable to generate report.');
                        }
                    })
                    .catch(err => console.error('Error ending session:', err));
            }
        }

        function showPerformanceReportModal(report) {
            if (!report) return;

            const overallEl = document.getElementById('reportOverallScore');
            const techEl = document.getElementById('reportTechScore');
            const confEl = document.getElementById('reportConfScore');

            if (overallEl) overallEl.textContent = (report.overallScore || 0) + '%';
            if (techEl) techEl.textContent = (report.techScore || 0) + '%';
            if (confEl) confEl.textContent = (report.confidenceScore || 0) + '%';

            const weakList = document.getElementById('reportWeakAreasList');
            if (weakList) {
                weakList.innerHTML = '';
                (report.weakAreas || []).forEach(item => {
                    const li = document.createElement('li');
                    li.className = 'list-group-item bg-light text-dark small py-2';
                    li.innerHTML = '<i class="bi bi-x-circle-fill text-danger me-2"></i>' + escapeHtml(item);
                    weakList.appendChild(li);
                });
            }

            const recList = document.getElementById('reportRecommendationsList');
            if (recList) {
                recList.innerHTML = '';
                (report.recommendations || []).forEach(item => {
                    const li = document.createElement('li');
                    li.className = 'list-group-item bg-light text-dark small py-2';
                    li.innerHTML = '<i class="bi bi-check-circle-fill text-success me-2"></i>' + escapeHtml(item);
                    recList.appendChild(li);
                });
            }

            const grid = document.getElementById('reportCareerPathsGrid');
            if (grid) {
                grid.innerHTML = '';
                (report.recommendedCareers || []).forEach(cp => {
                    const col = document.createElement('div');
                    col.className = 'col-md-4';
                    col.innerHTML = `
                        <div class="p-3 bg-white rounded-8px border h-100 shadow-sm">
                            <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle mb-2">Match ${escapeHtml(cp.match || '90%')}</span>
                            <h6 class="fw-bold text-dark mb-1">${escapeHtml(cp.title)}</h6>
                            <p class="text-muted small mb-0">${escapeHtml(cp.desc)}</p>
                        </div>
                    `;
                    grid.appendChild(col);
                });
            }

            const modalEl = document.getElementById('interviewReportModal');
            if (modalEl) {
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.innerText = text;
            return div.innerHTML;
        }
    </script>
</div>

<!-- ================= INTERVIEW PERFORMANCE REPORT MODAL ================= -->
<div class="modal fade" id="interviewReportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-12px border-0 shadow-lg">
            <div class="modal-header bg-dark text-white rounded-top-12px">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-bar-graph me-2 text-accent"></i>Intervia Performance Report</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Scores Row -->
                <div class="row g-3 text-center mb-4">
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-8px border">
                            <small class="text-muted fw-bold d-block mb-1">OVERALL SCORE</small>
                            <h2 class="fw-bold text-brand mb-0" id="reportOverallScore">0%</h2>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-8px border">
                            <small class="text-muted fw-bold d-block mb-1">TECHNICAL ACCURACY</small>
                            <h2 class="fw-bold text-dark mb-0" id="reportTechScore">0%</h2>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-8px border">
                            <small class="text-muted fw-bold d-block mb-1">CAMERA CONFIDENCE</small>
                            <h2 class="fw-bold text-dark mb-0" id="reportConfScore">0%</h2>
                        </div>
                    </div>
                </div>

                <!-- Weak Areas Section -->
                <div class="mb-4">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>Identified Weak Areas</h6>
                    <ul class="list-group list-group-flush rounded-8px border" id="reportWeakAreasList"></ul>
                </div>

                <!-- Actionable Recommendations Section -->
                <div class="mb-4">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-lightbulb-fill me-2 text-warning"></i>Common Recommendations for Improvement</h6>
                    <ul class="list-group list-group-flush rounded-8px border" id="reportRecommendationsList"></ul>
                </div>

                <!-- Recommended Career Paths Section -->
                <div class="mb-2">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-compass-fill me-2 text-success"></i>Recommended Career Paths (Subject Area Matched)</h6>
                    <div class="row g-3" id="reportCareerPathsGrid"></div>
                </div>
            </div>
            <div class="modal-footer bg-light rounded-bottom-12px justify-content-between">
                <a href="../user-profile/" class="btn btn-outline-brand rounded-8px"><i class="bi bi-person-circle me-1"></i> View Saved Reports in User Profile</a>
                <button type="button" class="btn btn-brand rounded-8px px-4" data-bs-dismiss="modal">Close & Return</button>
            </div>
        </div>
    </div>
</div>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
