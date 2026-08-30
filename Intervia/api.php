<?php
// api.php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/activity_logger.php';
require_once __DIR__ . '/helpers/Scorer.php';

// Initialize session data if needed
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

if (!isset($_SESSION['questions'])) {
    $_SESSION['questions'] = [];
}

if (!isset($_SESSION['current_index'])) {
    $_SESSION['current_index'] = 0;
}

if (!isset($_SESSION['scores'])) {
    $_SESSION['scores'] = [];
}

if (!isset($_SESSION['quiz_active'])) {
    $_SESSION['quiz_active'] = false;
}

// Array of general introductory questions (personal details, company background, motivations, etc.)
$GENERAL_QUESTIONS = [
    "Could you please introduce yourself and share a brief overview of your background?",
    "Why are you interested in joining our company and what do you know about our work?",
    "What are your greatest professional strengths and key areas you are looking to grow in?",
    "Where do you see yourself professionally in the next 3 to 5 years?",
    "Can you share a challenging situation you faced in your previous work/academic role and how you handled it?"
];

// Helper function to load dataset questions randomly or by tier/category
function loadSessionQuestions($category = 'all', $tier = 'all') {
    $filePath = __DIR__ . '/dataset/Mock_interview_questions.json';
    if (!file_exists($filePath)) {
        return false;
    }
    
    $jsonData = json_decode(file_get_contents($filePath), true);
    if (!$jsonData) {
        return false;
    }

    $questions = $jsonData['questions'] ?? (is_array($jsonData) ? $jsonData : []);
    if (empty($questions)) {
        return false;
    }

    // Filter by tier and field/category if specified
    $filtered = array_filter($questions, function($q) use ($category, $tier) {
        $qField = strtolower($q['field'] ?? $q['category'] ?? $q['Category'] ?? '');
        $qTier = strtolower($q['tier'] ?? $q['Tier'] ?? '');
        
        $matchCategory = ($category === 'all' || str_contains($qField, strtolower($category)));
        $matchTier = ($tier === 'all' || $qTier === strtolower($tier));
        return $matchCategory && $matchTier;
    });

    if (empty($filtered)) {
        $filtered = $questions; // Fallback to all questions if filter returns empty
    }

    shuffle($filtered);
    return array_slice($filtered, 0, 10); // Pick 10 dataset technical questions
}

// Get available categories/fields from dataset
function getDatasetFields() {
    $filePath = __DIR__ . '/dataset/Mock_interview_questions.json';
    if (!file_exists($filePath)) {
        return [];
    }
    $jsonData = json_decode(file_get_contents($filePath), true);
    if (!$jsonData) {
        return [];
    }
    $questions = $jsonData['questions'] ?? (is_array($jsonData) ? $jsonData : []);
    $fields = [];
    foreach ($questions as $q) {
        $fieldName = $q['field'] ?? $q['category'] ?? '';
        if (!empty($fieldName)) {
            $fields[$fieldName] = true;
        }
    }
    $result = array_keys($fields);
    sort($result);
    return $result;
}

// Helper function to query existing corporate vacancies from database as recommended career paths
function getCareerRecommendationsForField($pdo, $field) {
    if (!isset($pdo)) {
        return [];
    }

    try {
        $search = '%' . trim($field) . '%';
        $stmt = $pdo->prepare("
            SELECT v.vacancyid, v.jobTitle, v.jobDescription, v.jobLocation, c.companyName
            FROM vacancy v
            JOIN company c ON v.companyid = c.companyid
            WHERE (v.jobstatus = 'Open' OR v.jobstatus = 'Active' OR v.jobstatus IS NULL)
              AND (c.accountStatus != 'Suspended' OR c.accountStatus IS NULL)
              AND (v.jobTitle LIKE ? OR v.jobDescription LIKE ? OR v.requirements LIKE ? OR c.companyName LIKE ?)
            ORDER BY v.createdAt DESC
            LIMIT 3
        ");
        $stmt->execute([$search, $search, $search, $search]);
        $vacancies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fallback: If no keyword match, fetch any active corporate vacancies from database
        if (empty($vacancies)) {
            $stmtFallback = $pdo->query("
                SELECT v.vacancyid, v.jobTitle, v.jobDescription, v.jobLocation, c.companyName
                FROM vacancy v
                JOIN company c ON v.companyid = c.companyid
                WHERE (v.jobstatus = 'Open' OR v.jobstatus = 'Active' OR v.jobstatus IS NULL)
                  AND (c.accountStatus != 'Suspended' OR c.accountStatus IS NULL)
                ORDER BY v.createdAt DESC
                LIMIT 3
            ");
            $vacancies = $stmtFallback->fetchAll(PDO::FETCH_ASSOC);
        }

        $results = [];
        foreach ($vacancies as $idx => $vac) {
            $matchPct = (95 - ($idx * 4)) . '%';
            $desc = !empty($vac['jobDescription']) ? (substr($vac['jobDescription'], 0, 90) . '...') : ('Verified opening at ' . $vac['companyName']);
            $results[] = [
                'vacancyid' => $vac['vacancyid'],
                'title' => $vac['jobTitle'] . ' (' . $vac['companyName'] . ')',
                'company' => $vac['companyName'],
                'location' => $vac['jobLocation'] ?? 'Sri Lanka',
                'match' => $matchPct,
                'desc' => $desc
            ];
        }

        return $results;
    } catch (Exception $e) {
        return [];
    }
}

// Function to generate comprehensive performance report & persist to database
function generateAndSaveInterviewReport($pdo) {
    $userId = $_SESSION['user_id'] ?? $_SESSION['userid'] ?? null;

    $questions = $_SESSION['questions'] ?? [];
    $scores = $_SESSION['scores'] ?? [];
    $confScores = $_SESSION['confidence_scores'] ?? [];
    $category = $_SESSION['session_category'] ?? 'General Software Engineering';
    $tier = $_SESSION['session_tier'] ?? 'Mid-Level';

    $avgTech = !empty($scores) ? round(array_sum($scores) / count($scores)) : 70;
    $avgConf = !empty($confScores) ? round(array_sum($confScores) / count($confScores)) : 75;
    $overallScore = round(($avgTech * 0.70) + ($avgConf * 0.30));

    // Identify Weak Areas
    $weakAreasList = [];
    foreach ($questions as $idx => $q) {
        $qScore = $scores[$idx] ?? 70;
        if ($qScore < 70 && ($q['type'] ?? '') === 'technical') {
            $snippet = substr($q['question'] ?? 'Technical Question', 0, 75);
            $weakAreasList[] = "Technical Depth: " . $snippet . "...";
        }
    }
    if (empty($weakAreasList)) {
        $weakAreasList = [
            "Technical answer depth & industry terminology precision",
            "Real-world architecture trade-off explanations",
            "Sustained camera eye-contact consistency under pressure"
        ];
    }

    // Common Recommendations
    $recommendationsList = [
        "Structure responses using the STAR method (Situation, Task, Action, Result) for clarity.",
        "Incorporate specific technical terms, data structures, and design patterns in explanations.",
        "Maintain upright posture and direct camera eye contact to boost non-verbal confidence scores.",
        "Practice articulating edge-case handling and system performance tradeoffs."
    ];

    // Recommended Careers (Queried directly from active DB vacancies)
    $recommendedCareersList = getCareerRecommendationsForField($pdo, $category);

    $weakJson = json_encode($weakAreasList);
    $recomJson = json_encode($recommendationsList);
    $careersJson = json_encode($recommendedCareersList);
    $totalQ = count($questions);

    $reportData = [
        'category' => $category,
        'tier' => $tier,
        'overallScore' => $overallScore,
        'techScore' => $avgTech,
        'confidenceScore' => $avgConf,
        'weakAreas' => $weakAreasList,
        'recommendations' => $recommendationsList,
        'recommendedCareers' => $recommendedCareersList,
        'totalQuestions' => $totalQ,
        'sessionDate' => date('Y-m-d H:i:s')
    ];

    if ($userId && isset($pdo)) {
        try {
            $stmtIns = $pdo->prepare("
                INSERT INTO interviewreport 
                (userid, category, tier, overallScore, techScore, confidenceScore, weakAreas, recommendations, recommendedCareers, totalQuestions, sessionDate)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmtIns->execute([
                $userId, $category, $tier, $overallScore, $avgTech, $avgConf, $weakJson, $recomJson, $careersJson, $totalQ
            ]);
            $reportData['reportid'] = $pdo->lastInsertId();

            // Log Activity History
            logActivity($pdo, $userId, null, "Completed Intervia AI Practice Interview (" . ucfirst($category) . ") with score " . $overallScore . "%");
        } catch (Exception $e) {
            // DB log fallback
        }
    }

    return $reportData;
}

$action = $_GET['action'] ?? '';

if (!empty($action)) {
    switch ($action) {

    case 'get_options':
        $fields = getDatasetFields();
        echo json_encode([
            'status' => 'success',
            'fields' => $fields
        ]);
        exit;

    case 'get_status':
        echo json_encode([
            'status' => 'success',
            'quiz_active' => $_SESSION['quiz_active'],
            'chat_history' => $_SESSION['chat_history'],
            'current_question_no' => $_SESSION['current_index'] + 1,
            'total_questions' => count($_SESSION['questions'])
        ]);
        exit;

    case 'start_quiz':
        $tier = $_POST['tier'] ?? 'all';
        $category = $_POST['category'] ?? 'all';

        $_SESSION['session_category'] = $category !== 'all' ? $category : 'General Software Engineering';
        $_SESSION['session_tier'] = $tier !== 'all' ? $tier : 'Mid-Level';

        global $GENERAL_QUESTIONS;
        $introQuestions = $GENERAL_QUESTIONS;
        shuffle($introQuestions);
        $selectedIntro = array_slice($introQuestions, 0, 5);

        $techQuestions = loadSessionQuestions($category, $tier);

        if (!$techQuestions) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to load interview dataset questions.']);
            exit;
        }

        $sessionQuestions = [];
        foreach ($selectedIntro as $idx => $introText) {
            $sessionQuestions[] = [
                'type' => 'intro',
                'question' => $introText,
                'expected' => ''
            ];
        }

        foreach ($techQuestions as $tq) {
            $qText = $tq['question'] ?? $tq['Question'] ?? '';
            $ansText = $tq['answer'] ?? $tq['Answer'] ?? '';
            $catText = $tq['field'] ?? $tq['category'] ?? '';
            $tierText = $tq['tier'] ?? '';

            $sessionQuestions[] = [
                'type' => 'technical',
                'question' => $qText,
                'expected' => $ansText,
                'category' => $catText,
                'tier' => $tierText
            ];
        }

        $_SESSION['questions'] = $sessionQuestions;
        $_SESSION['current_index'] = 0;
        $_SESSION['scores'] = [];
        $_SESSION['confidence_scores'] = [];
        $_SESSION['chat_history'] = [];
        $_SESSION['quiz_active'] = true;

        $firstQ = $_SESSION['questions'][0];
        $welcomeMsg = "Welcome to your AI Mock Interview Session!\n\nWe will start with **5 general background questions** (incorporating real-time AI eye & posture confidence tracking), followed by **10 technical domain questions**.\n\n📌 **Question 1 of " . count($sessionQuestions) . ":**\n" . $firstQ['question'];

        $_SESSION['chat_history'][] = [
            'sender' => 'bot',
            'text' => $welcomeMsg,
            'timestamp' => date('h:i A')
        ];

        // Log Activity History
        if (isset($pdo)) {
            $userId = $_SESSION['userid'] ?? null;
            $companyId = $_SESSION['companyid'] ?? null;
            logActivity($pdo, $userId, $companyId, "Started Intervia AI Mock Practice Session (Tier: " . ucfirst($tier) . ", Field: " . ucfirst($category) . ")");
        }

        echo json_encode([
            'status' => 'success',
            'chat_history' => $_SESSION['chat_history'],
            'total_questions' => count($_SESSION['questions']),
            'current_question_no' => 1
        ]);
        exit;

    case 'submit_answer':
        if (!$_SESSION['quiz_active'] || empty($_SESSION['questions'])) {
            echo json_encode(['status' => 'error', 'message' => 'No active interview session.']);
            exit;
        }

        $userAnswer = trim($_POST['answer'] ?? '');
        $confidenceScore = isset($_POST['confidence_score']) ? intval($_POST['confidence_score']) : 75;

        if (empty($userAnswer)) {
            echo json_encode(['status' => 'error', 'message' => 'Please type an answer before submitting.']);
            exit;
        }

        $currentIndex = $_SESSION['current_index'];
        $currentQ = $_SESSION['questions'][$currentIndex];

        // Record User Answer
        $_SESSION['chat_history'][] = [
            'sender' => 'user',
            'text' => $userAnswer,
            'timestamp' => date('h:i A')
        ];

        // Evaluate Answer based on question type
        if ($currentQ['type'] === 'intro') {
            $score = min(100, max(50, strlen($userAnswer) > 20 ? 85 : 60));
            $_SESSION['scores'][] = $score;
            $_SESSION['confidence_scores'][] = $confidenceScore;
        } else {
            $eval = Scorer::evaluateAnswer($currentQ['expected'], $userAnswer);
            $_SESSION['scores'][] = $eval['score'];
        }

        $_SESSION['current_index']++;
        $nextIndex = $_SESSION['current_index'];
        $totalQ = count($_SESSION['questions']);

        if ($nextIndex < $totalQ) {
            $nextQ = $_SESSION['questions'][$nextIndex];
            $qNum = $nextIndex + 1;
            
            $nextMsg = "📌 **Question {$qNum} of {$totalQ}:**\n" . $nextQ['question'];
            $_SESSION['chat_history'][] = [
                'sender' => 'bot',
                'text' => $nextMsg,
                'timestamp' => date('h:i A')
            ];

            echo json_encode([
                'status' => 'success',
                'chat_history' => $_SESSION['chat_history'],
                'current_question_no' => $qNum,
                'total_questions' => $totalQ,
                'is_finished' => false
            ]);
            exit;
        } else {
            // Session Complete - Generate & Save Performance Report
            $_SESSION['quiz_active'] = false;
            $reportData = generateAndSaveInterviewReport($pdo);

            $finalCombinedScore = $reportData['overallScore'] ?? 75;
            $avgScore = $reportData['techScore'] ?? 70;
            $avgConf = $reportData['confidenceScore'] ?? 75;

            $summaryMsg = "🎉 **Interview Session Complete!**\n\n" .
                "**Performance Overview:**\n" .
                "• Technical Accuracy Score: **" . $avgScore . "%**\n" .
                "• Non-Verbal Eye & Posture Score: **" . $avgConf . "%**\n" .
                "• **Final Combined Score: " . $finalCombinedScore . "%**\n\n" .
                "📊 Your detailed performance report including **Weak Areas**, **Actionable Recommendations**, and **Recommended Career Paths** has been saved to your profile!";

            $_SESSION['chat_history'][] = [
                'sender' => 'bot',
                'text' => $summaryMsg,
                'timestamp' => date('h:i A'),
                'is_summary' => true
            ];

            echo json_encode([
                'status' => 'success',
                'chat_history' => $_SESSION['chat_history'],
                'current_question_no' => $totalQ,
                'total_questions' => $totalQ,
                'is_finished' => true,
                'final_score' => $finalCombinedScore,
                'report' => $reportData
            ]);
            exit;
        }

    case 'end_session':
        if (!empty($_SESSION['questions'])) {
            $_SESSION['quiz_active'] = false;
            $reportData = generateAndSaveInterviewReport($pdo);
            echo json_encode([
                'status' => 'success',
                'message' => 'Session ended and performance report generated.',
                'report' => $reportData
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'No active interview session to end.'
            ]);
        }
        exit;

    case 'reset':
        $_SESSION['chat_history'] = [];
        $_SESSION['questions'] = [];
        $_SESSION['current_index'] = 0;
        $_SESSION['scores'] = [];
        $_SESSION['confidence_scores'] = [];
        $_SESSION['quiz_active'] = false;

        echo json_encode([
            'status' => 'success',
            'message' => 'Session reset successfully.'
        ]);
        exit;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
        exit;
}
}
