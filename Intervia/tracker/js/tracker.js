/**
 * Eye Tracker & Body Language Confidence Meter Engine
 * Powered by MediaPipe Face Mesh & MediaPipe Pose
 */

class AIConfidenceTracker {
    constructor() {
        this.videoElement = document.getElementById('webcam');
        this.canvasElement = document.getElementById('hud-canvas');
        this.canvasCtx = this.canvasElement ? this.canvasElement.getContext('2d') : null;

        this.showEyeMesh = true;
        this.showPostureAxis = true;

        this.eyeScore = 75;
        this.postureScore = 75;
        this.overallScore = 75;

        this.isTracking = false;

        this.shoulderTilt = 0;
        this.gazeDirection = "Centered";
        this.postureStatus = "Aligned";

        this.notifBanner = document.getElementById('top-notification-banner');
        this.notifIcon = document.getElementById('notification-icon');
        this.notifText = document.getElementById('notification-text');

        this.expressionValEl = document.getElementById('expression-val');
        this.expressionIconEl = document.getElementById('expression-icon');
        this.expressionBadgeEl = document.getElementById('expression-badge');

        this.latestFaceLandmarks = null;
        this.latestPoseLandmarks = null;
    }

    async initMediaPipe() {
        if (this.isTracking) return;

        this.videoElement = document.getElementById('webcam');
        this.canvasElement = document.getElementById('hud-canvas');
        if (this.canvasElement) {
            this.canvasCtx = this.canvasElement.getContext('2d');
        }

        // Face Mesh Setup (Offline local asset paths)
        if (typeof FaceMesh !== 'undefined') {
            this.faceMesh = new FaceMesh({
                locateFile: (file) => `assets/mediapipe/face_mesh/${file}`
            });

            this.faceMesh.setOptions({
                maxNumFaces: 1,
                refineLandmarks: true,
                minDetectionConfidence: 0.5,
                minTrackingConfidence: 0.5
            });

            this.faceMesh.onResults((results) => {
                if (results.multiFaceLandmarks && results.multiFaceLandmarks.length > 0) {
                    this.latestFaceLandmarks = results.multiFaceLandmarks[0];
                } else {
                    this.latestFaceLandmarks = null;
                }
            });
        }

        // Pose Setup (for Shoulders - Offline local asset paths)
        if (typeof Pose !== 'undefined') {
            this.pose = new Pose({
                locateFile: (file) => `assets/mediapipe/pose/${file}`
            });

            this.pose.setOptions({
                modelComplexity: 1,
                smoothLandmarks: true,
                enableSegmentation: false,
                minDetectionConfidence: 0.5,
                minTrackingConfidence: 0.5
            });

            this.pose.onResults((results) => {
                if (results.poseLandmarks) {
                    this.latestPoseLandmarks = results.poseLandmarks;
                } else {
                    this.latestPoseLandmarks = null;
                }
            });
        }

        // Initialize Camera utils
        if (typeof Camera !== 'undefined' && this.videoElement) {
            this.camera = new Camera(this.videoElement, {
                onFrame: async () => {
                    if (this.videoElement && this.videoElement.readyState >= 2) {
                        try {
                            if (this.faceMesh) await this.faceMesh.send({ image: this.videoElement });
                            if (this.pose) await this.pose.send({ image: this.videoElement });
                            this.processFrame();
                        } catch (err) {
                            console.warn("MediaPipe frame send warning:", err);
                        }
                    }
                },
                width: 640,
                height: 480
            });

            try {
                await this.camera.start();
                this.isTracking = true;
                const preloader = document.getElementById('cam-preloader');
                if (preloader) preloader.style.display = 'none';
            } catch (err) {
                console.error("Camera init error: ", err);
                const preloader = document.getElementById('cam-preloader');
                if (preloader) {
                    preloader.innerHTML = `
                        <div class="text-danger fw-bold mb-1" style="font-size: 0.8rem;"><i class="fa-solid fa-triangle-exclamation me-1"></i> Camera Unavailable</div>
                        <div class="small text-white-50" style="font-size: 0.7rem;">Please grant camera permissions to enable AI eye & posture tracking.</div>
                    `;
                }
            }
        }
    }

    processFrame() {
        if (!this.canvasElement || !this.videoElement) return;

        const width = this.canvasElement.width = this.videoElement.videoWidth || 320;
        const height = this.canvasElement.height = this.videoElement.videoHeight || 240;

        if (this.canvasCtx) {
            this.canvasCtx.clearRect(0, 0, width, height);
        }

        const preloader = document.getElementById('cam-preloader');
        if (preloader && preloader.style.display !== 'none') {
            preloader.style.display = 'none';
        }

        // 1. Evaluate Eye Gaze
        this.evaluateGaze(this.latestFaceLandmarks, width, height);

        // 2. Evaluate Body Language (Shoulder Posture)
        this.evaluatePosture(this.latestPoseLandmarks, width, height);

        // 3. Compute Composite Confidence Score
        let targetOverall = Math.round((this.eyeScore * 0.50) + (this.postureScore * 0.50));
        this.overallScore = Math.round(this.overallScore * 0.70 + targetOverall * 0.30);

        // 4. Update UI Badges
        this.updateBadges();
    }

    evaluateGaze(landmarks, width, height) {
        if (!landmarks) {
            this.eyeScore = Math.max(30, this.eyeScore - 2);
            this.gazeDirection = "Away / Unfocused";
            return;
        }

        const leftIris = landmarks[468];
        const rightIris = landmarks[473];

        if (leftIris && rightIris) {
            const dx = Math.abs((leftIris.x + rightIris.x) / 2 - 0.5);
            const dy = Math.abs((leftIris.y + rightIris.y) / 2 - 0.5);

            if (dx < 0.12 && dy < 0.12) {
                this.eyeScore = Math.min(98, this.eyeScore + 2);
                this.gazeDirection = "Centered";
            } else {
                this.eyeScore = Math.max(40, this.eyeScore - 1);
                this.gazeDirection = "Off-center";
            }
        } else {
            this.eyeScore = Math.min(90, this.eyeScore + 1);
            this.gazeDirection = "Centered";
        }
    }

    evaluatePosture(landmarks, width, height) {
        if (!landmarks) {
            this.postureScore = Math.max(40, this.postureScore - 1);
            this.postureStatus = "Aligned";
            return;
        }

        const leftShoulder = landmarks[11];
        const rightShoulder = landmarks[12];

        if (leftShoulder && rightShoulder) {
            const tilt = Math.abs(leftShoulder.y - rightShoulder.y);
            if (tilt < 0.08) {
                this.postureScore = Math.min(98, this.postureScore + 2);
                this.postureStatus = "Aligned";
            } else {
                this.postureScore = Math.max(45, this.postureScore - 2);
                this.postureStatus = "Slouched / Tilted";
            }
        } else {
            this.postureScore = Math.min(90, this.postureScore + 1);
            this.postureStatus = "Aligned";
        }
    }

    updateBadges() {
        const expVal = document.getElementById('expression-val');
        const expIcon = document.getElementById('expression-icon');
        if (expVal) expVal.textContent = this.overallScore >= 70 ? "Focused" : "Unfocused";
        if (expIcon) expIcon.textContent = this.overallScore >= 70 ? "😊" : "😐";

        const notifText = document.getElementById('notification-text');
        const notifIcon = document.getElementById('notification-icon');
        if (notifText) {
            if (this.overallScore >= 80) {
                notifText.textContent = "High Confidence";
                if (notifIcon) notifIcon.textContent = "✨";
            } else if (this.overallScore >= 60) {
                notifText.textContent = "Good Presence";
                if (notifIcon) notifIcon.textContent = "👍";
            } else {
                notifText.textContent = "Maintain Focus";
                if (notifIcon) notifIcon.textContent = "👁️";
            }
        }
    }
}

// Global initialization
if (typeof window !== 'undefined') {
    window.AIConfidenceTracker = AIConfidenceTracker;
    document.addEventListener('DOMContentLoaded', () => {
        if (!window.trackerInstance) {
            window.trackerInstance = new AIConfidenceTracker();
        }
    });
}
