<?php
require_once '../config/database.php';
require_once '../utils/helpers.php';

class ActivityLogger
{
    private $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function logActivity($userId, $action, $details = [], $ipAddress = null)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_log (user_id, action, details, ip_address, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");

            $detailsJson = json_encode($details);
            $ipAddress = $ipAddress ?: $this->getClientIpAddress();

            return $stmt->execute([$userId, $action, $detailsJson, $ipAddress]);
        } catch (Exception $e) {
            error_log("Activity logging failed: " . $e->getMessage());
            return false;
        }
    }

    public function logUserLogin($userId, $success = true, $method = 'email')
    {
        $action = $success ? 'user_login_success' : 'user_login_failed';
        $details = [
            'method' => $method,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        return $this->logActivity($userId, $action, $details);
    }

    public function logUserLogout($userId)
    {
        return $this->logActivity($userId, 'user_logout', [
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    public function logTransactionCreated($userId, $transactionId, $amount, $type)
    {
        return $this->logActivity($userId, 'transaction_created', [
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'type' => $type,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    public function logTransactionUpdated($userId, $transactionId, $changes)
    {
        return $this->logActivity($userId, 'transaction_updated', [
            'transaction_id' => $transactionId,
            'changes' => $changes,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    public function logTransactionDeleted($userId, $transactionId)
    {
        return $this->logActivity($userId, 'transaction_deleted', [
            'transaction_id' => $transactionId,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    public function logBudgetAlert($userId, $budgetId, $alertType, $threshold)
    {
        return $this->logActivity($userId, 'budget_alert_triggered', [
            'budget_id' => $budgetId,
            'alert_type' => $alertType,
            'threshold' => $threshold,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    public function logGoalCreated($userId, $goalId, $targetAmount)
    {
        return $this->logActivity($userId, 'goal_created', [
            'goal_id' => $goalId,
            'target_amount' => $targetAmount,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    public function logGoalAchieved($userId, $goalId)
    {
        return $this->logActivity($userId, 'goal_achieved', [
            'goal_id' => $goalId,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    public function logSecurityEvent($userId, $eventType, $details = [])
    {
        return $this->logActivity($userId, 'security_event', array_merge([
            'event_type' => $eventType,
            'timestamp' => date('Y-m-d H:i:s')
        ], $details));
    }

    public function logDataExport($userId, $exportType, $recordCount)
    {
        return $this->logActivity($userId, 'data_exported', [
            'export_type' => $exportType,
            'record_count' => $recordCount,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    public function logSettingsChanged($userId, $settingType, $oldValue, $newValue)
    {
        return $this->logActivity($userId, 'settings_changed', [
            'setting_type' => $settingType,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    public function getUserActivityHistory($userId, $limit = 50, $offset = 0)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    action,
                    details,
                    ip_address,
                    created_at
                FROM activity_log 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ");

            $stmt->execute([$userId, $limit, $offset]);
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decode JSON details
            foreach ($activities as &$activity) {
                $activity['details'] = json_decode($activity['details'], true) ?? [];
                $activity['formatted_action'] = $this->formatActionForDisplay($activity['action']);
            }

            return $activities;
        } catch (Exception $e) {
            error_log("Failed to get user activity history: " . $e->getMessage());
            return [];
        }
    }

    public function getSystemActivitySummary($days = 7)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    action,
                    COUNT(*) as count,
                    COUNT(DISTINCT user_id) as unique_users
                FROM activity_log 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY action
                ORDER BY count DESC
            ");

            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to get system activity summary: " . $e->getMessage());
            return [];
        }
    }

    public function getActiveUsers($timeframe = '24h')
    {
        try {
            $interval = match ($timeframe) {
                '1h' => 'INTERVAL 1 HOUR',
                '24h' => 'INTERVAL 24 HOUR',
                '7d' => 'INTERVAL 7 DAY',
                '30d' => 'INTERVAL 30 DAY',
                default => 'INTERVAL 24 HOUR'
            };

            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(DISTINCT user_id) as active_users,
                    COUNT(*) as total_activities
                FROM activity_log 
                WHERE created_at >= DATE_SUB(NOW(), {$interval})
            ");

            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to get active users: " . $e->getMessage());
            return ['active_users' => 0, 'total_activities' => 0];
        }
    }

    public function cleanupOldLogs($daysToKeep = 90)
    {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM activity_log 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");

            $stmt->execute([$daysToKeep]);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Failed to cleanup old logs: " . $e->getMessage());
            return 0;
        }
    }

    private function getClientIpAddress()
    {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                return trim($ips[0]);
            }
        }

        return 'unknown';
    }

    private function formatActionForDisplay($action)
    {
        $actionMap = [
            'user_login_success' => 'User logged in successfully',
            'user_login_failed' => 'Failed login attempt',
            'user_logout' => 'User logged out',
            'transaction_created' => 'Transaction created',
            'transaction_updated' => 'Transaction updated',
            'transaction_deleted' => 'Transaction deleted',
            'budget_alert_triggered' => 'Budget alert triggered',
            'goal_created' => 'Financial goal created',
            'goal_achieved' => 'Financial goal achieved',
            'security_event' => 'Security event',
            'data_exported' => 'Data exported',
            'settings_changed' => 'Settings changed'
        ];

        return $actionMap[$action] ?? ucfirst(str_replace('_', ' ', $action));
    }
}

// Global function for easy logging
function logUserActivity($userId, $action, $details = [])
{
    $logger = new ActivityLogger();
    return $logger->logActivity($userId, $action, $details);
}

// Activity tracking workflow integration
class ActivityTrackingWorkflow
{
    private $logger;

    public function __construct()
    {
        $this->logger = new ActivityLogger();
    }

    public function trackUserSession($userId, $sessionData = [])
    {
        // Log session start
        $this->logger->logActivity($userId, 'session_started', array_merge([
            'session_id' => session_id(),
            'start_time' => date('Y-m-d H:i:s')
        ], $sessionData));

        // Set up session tracking
        $_SESSION['activity_tracking'] = [
            'user_id' => $userId,
            'start_time' => time(),
            'last_activity' => time()
        ];
    }

    public function trackPageView($userId, $page, $referrer = null)
    {
        $this->logger->logActivity($userId, 'page_view', [
            'page' => $page,
            'referrer' => $referrer,
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        // Update last activity
        if (isset($_SESSION['activity_tracking'])) {
            $_SESSION['activity_tracking']['last_activity'] = time();
        }
    }

    public function trackFeatureUsage($userId, $feature, $context = [])
    {
        $this->logger->logActivity($userId, 'feature_used', array_merge([
            'feature' => $feature,
            'timestamp' => date('Y-m-d H:i:s')
        ], $context));
    }

    public function trackError($userId, $errorType, $errorDetails)
    {
        $this->logger->logActivity($userId, 'error_encountered', [
            'error_type' => $errorType,
            'error_details' => $errorDetails,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    public function endUserSession($userId)
    {
        $sessionDuration = 0;
        if (isset($_SESSION['activity_tracking'])) {
            $sessionDuration = time() - $_SESSION['activity_tracking']['start_time'];
        }

        $this->logger->logActivity($userId, 'session_ended', [
            'session_id' => session_id(),
            'duration_seconds' => $sessionDuration,
            'end_time' => date('Y-m-d H:i:s')
        ]);

        unset($_SESSION['activity_tracking']);
    }

    public function generateUserReport($userId, $dateFrom, $dateTo)
    {
        // Implementation for generating comprehensive user activity reports
        return $this->logger->getUserActivityHistory($userId, 1000);
    }
}
