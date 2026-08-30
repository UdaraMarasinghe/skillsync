<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$calUserId = $_SESSION['user_id'] ?? null;
if (!$calUserId && isset($pdo)) {
    $stmtDefaultU = $pdo->query("SELECT userid FROM user ORDER BY userid ASC LIMIT 1");
    $calUserId = $stmtDefaultU->fetchColumn() ?: 1;
}

if (!$calUserId) {
    echo json_encode(['success' => false, 'events' => [], 'message' => 'No active user found']);
    exit;
}

// Handle Candidate Accepting Interview
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'accept_interview') {
    $calId = (int)($_POST['calendarid'] ?? 0);

    if ($calId > 0) {
        try {
            // Update calendar table status for user
            $stmtUpCal = $pdo->prepare("UPDATE calendar SET activityStatus = 'Accepted' WHERE calendarid = ? AND userid = ?");
            $stmtUpCal->execute([$calId, $calUserId]);

            // Update appliedJobs table status for candidate
            $stmtUpApp = $pdo->prepare("UPDATE appliedJobs SET status = 'Candidate Accepted' WHERE userid = ? AND status = 'Scheduled'");
            $stmtUpApp->execute([$calUserId]);

            // Fetch calendar activity title for notification
            $stmtCalTitle = $pdo->prepare("SELECT activityName FROM calendar WHERE calendarid = ?");
            $stmtCalTitle->execute([$calId]);
            $actTitle = $stmtCalTitle->fetchColumn() ?: 'Interview';

            // Insert notification for user
            $stmtUserNotif = $pdo->prepare("INSERT INTO notification (userid, notificationDescription, notificationDate, notificationTime) VALUES (?, ?, CURDATE(), CURTIME())");
            $stmtUserNotif->execute([$calUserId, "Accepted " . $actTitle]);

            echo json_encode(['success' => true, 'message' => 'Interview accepted successfully!']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid calendar event ID.']);
    }
    exit;
}

// Fetch events for GET request (exclude suspended companies)
try {
    $stmtCal = $pdo->prepare("
        SELECT c.* 
        FROM calendar c
        LEFT JOIN company comp ON c.companyid = comp.companyid
        LEFT JOIN (
            SELECT DISTINCT aj.userid, v.companyid, v.jobTitle, c_sub.accountStatus AS compStatus
            FROM appliedJobs aj
            JOIN vacancy v ON aj.vacancyid = v.vacancyid
            JOIN company c_sub ON v.companyid = c_sub.companyid
        ) app_comp ON c.companyid IS NULL AND c.userid = app_comp.userid AND (c.activityName LIKE CONCAT('%', app_comp.jobTitle, '%'))
        WHERE c.userid = ?
          AND (c.companyid IS NULL OR comp.accountStatus != 'Suspended' OR comp.accountStatus IS NULL)
          AND (app_comp.compStatus IS NULL OR app_comp.compStatus != 'Suspended')
        GROUP BY c.calendarid
        ORDER BY c.activityDate ASC
    ");
    $stmtCal->execute([$calUserId]);
    $events = $stmtCal->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'events' => $events]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'events' => [], 'message' => $e->getMessage()]);
}
