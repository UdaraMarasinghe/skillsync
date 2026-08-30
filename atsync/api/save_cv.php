<?php
require_once __DIR__ . '/../../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        $stmtFirstUser = $pdo->query("SELECT userid FROM user ORDER BY userid ASC LIMIT 1");
        $userId = $stmtFirstUser->fetchColumn() ?: 1;
    }

    // Check if JSON body or FormData
    $input = file_get_contents('php://input');
    $jsonData = json_decode($input, true);

    $resumeContent = $_POST['resume_content'] ?? ($jsonData['resume_content'] ?? '');
    $resumeTitle = trim($_POST['resume_title'] ?? ($jsonData['resume_title'] ?? 'ATS Resume'));

    if (!empty($jsonData)) {
        $_SESSION['cv_data'] = $jsonData;
    }

    if (!empty($resumeContent)) {
        $uploadDir = __DIR__ . '/../../uploads/resumes/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $safeTitle = preg_replace('/[^a-zA-Z0-9_-]/', '_', $resumeTitle);
        $fileName = 'resume_' . $userId . '_' . time() . '_' . $safeTitle . '.html';
        $filePath = $uploadDir . $fileName;
        $dbRelativePath = 'uploads/resumes/' . $fileName;

        if (file_put_contents($filePath, $resumeContent) !== false) {
            try {
                $stmt = $pdo->prepare("INSERT INTO resume (userid, resumes) VALUES (?, ?)");
                $stmt->execute([$userId, $dbRelativePath]);
                $resumeId = $pdo->lastInsertId();

                echo json_encode([
                    'success' => true,
                    'message' => 'Resume saved successfully to database!',
                    'resumeid' => $resumeId,
                    'path' => $dbRelativePath
                ]);
                exit;
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save resume document file.']);
            exit;
        }
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'CV data saved successfully',
            'saved_at' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'success' => true,
        'data' => $_SESSION['cv_data'] ?? null
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
