<?php
/**
 * SkillSync Activity History Helper Functions
 */

if (!function_exists('logActivity')) {
    /**
     * Logs an activity to the activityHistory table.
     *
     * @param PDO $pdo
     * @param int|null $userId
     * @param int|null $companyId
     * @param string $activityDescription
     * @return bool
     */
    function logActivity($pdo, $userId, $companyId, $activityDescription) {
        if (!$pdo || (empty($userId) && empty($companyId))) {
            return false;
        }
        try {
            $stmt = $pdo->prepare("
                INSERT INTO activityHistory (userid, companyid, activityHistory, activityDate, activityTime) 
                VALUES (?, ?, ?, CURDATE(), CURTIME())
            ");
            return $stmt->execute([
                !empty($userId) ? $userId : null,
                !empty($companyId) ? $companyId : null,
                $activityDescription
            ]);
        } catch (Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getActivityHistory')) {
    /**
     * Retrieves activity history for a user or company.
     *
     * @param PDO $pdo
     * @param int|null $userId
     * @param int|null $companyId
     * @param int $limit
     * @return array
     */
    function getActivityHistory($pdo, $userId = null, $companyId = null, $limit = 50) {
        if (!$pdo || (empty($userId) && empty($companyId))) {
            return [];
        }
        try {
            $limit = (int)$limit;
            if (!empty($userId)) {
                $stmt = $pdo->prepare("SELECT * FROM activityHistory WHERE userid = ? ORDER BY activityDate DESC, activityTime DESC, activityHistoryId DESC LIMIT {$limit}");
                $stmt->execute([$userId]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM activityHistory WHERE companyid = ? ORDER BY activityDate DESC, activityTime DESC, activityHistoryId DESC LIMIT {$limit}");
                $stmt->execute([$companyId]);
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch activity history: " . $e->getMessage());
            return [];
        }
    }
}
