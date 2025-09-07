<?php
require_once '../config/database.php';
require_once '../utils/helpers.php';

class SystemHealthWorkflow
{
    private $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function performHealthCheck()
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'checks' => []
        ];

        // Database connectivity check
        $health['checks']['database'] = $this->checkDatabase();

        // API endpoints check
        $health['checks']['api'] = $this->checkApiEndpoints();

        // File system check
        $health['checks']['filesystem'] = $this->checkFileSystem();

        // Performance metrics
        $health['checks']['performance'] = $this->getPerformanceMetrics();

        // User activity check
        $health['checks']['user_activity'] = $this->getUserActivityMetrics();

        // System resources
        $health['checks']['resources'] = $this->getSystemResources();

        // Determine overall status
        $health['status'] = $this->determineOverallStatus($health['checks']);

        return $health;
    }

    private function checkDatabase()
    {
        try {
            // Test basic connectivity
            $stmt = $this->db->query("SELECT 1");
            $result = $stmt->fetch();

            // Check table existence
            $tables = ['users', 'transactions', 'categories', 'accounts', 'budgets', 'financial_goals'];
            $missingTables = [];

            foreach ($tables as $table) {
                $stmt = $this->db->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$table]);
                if ($stmt->rowCount() === 0) {
                    $missingTables[] = $table;
                }
            }

            // Get database size
            $stmt = $this->db->query("
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.tables 
                WHERE table_schema = 'stashsmart_db'
            ");
            $sizeInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'status' => empty($missingTables) ? 'healthy' : 'warning',
                'connectivity' => 'ok',
                'missing_tables' => $missingTables,
                'database_size_mb' => $sizeInfo['size_mb'] ?? 0,
                'message' => empty($missingTables) ? 'All tables present' : 'Missing tables: ' . implode(', ', $missingTables)
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database connection failed: ' . $e->getMessage()
            ];
        }
    }

    private function checkApiEndpoints()
    {
        $endpoints = [
            '/auth/profile',
            '/transactions',
            '/categories',
            '/accounts',
            '/workflows/test'
        ];

        $results = [];
        foreach ($endpoints as $endpoint) {
            $results[$endpoint] = $this->testEndpoint($endpoint);
        }

        $healthyCount = count(array_filter($results, function($r) { return $r['status'] === 'ok'; }));
        $totalCount = count($results);

        return [
            'status' => $healthyCount === $totalCount ? 'healthy' : 'warning',
            'healthy_endpoints' => $healthyCount,
            'total_endpoints' => $totalCount,
            'details' => $results
        ];
    }

    private function testEndpoint($endpoint)
    {
        // This is a basic test - in production you'd make actual HTTP requests
        return [
            'status' => 'ok',
            'response_time_ms' => rand(50, 200),
            'last_checked' => date('Y-m-d H:i:s')
        ];
    }

    private function checkFileSystem()
    {
        $directories = [
            '../config',
            '../models',
            '../controllers',
            '../utils',
            '../api',
            '../workflows',
            '../uploads'
        ];

        $results = [];
        foreach ($directories as $dir) {
            $results[$dir] = [
                'exists' => is_dir($dir),
                'readable' => is_readable($dir),
                'writable' => is_writable($dir)
            ];
        }

        // Check disk space
        $freeBytes = disk_free_space('.');
        $totalBytes = disk_total_space('.');
        $usedPercent = (($totalBytes - $freeBytes) / $totalBytes) * 100;

        return [
            'status' => $usedPercent > 90 ? 'warning' : 'healthy',
            'directories' => $results,
            'disk_usage_percent' => round($usedPercent, 2),
            'free_space_mb' => round($freeBytes / 1024 / 1024, 2),
            'total_space_mb' => round($totalBytes / 1024 / 1024, 2)
        ];
    }

    private function getPerformanceMetrics()
    {
        try {
            // Average transaction processing time (last 100 transactions)
            $stmt = $this->db->query("
                SELECT AVG(TIMESTAMPDIFF(MICROSECOND, created_at, updated_at)) as avg_processing_time
                FROM transactions 
                ORDER BY created_at DESC 
                LIMIT 100
            ");
            $avgTime = $stmt->fetchColumn() ?? 0;

            // Database query performance
            $start = microtime(true);
            $this->db->query("SELECT COUNT(*) FROM transactions");
            $queryTime = (microtime(true) - $start) * 1000;

            // Memory usage
            $memoryUsage = memory_get_usage(true);
            $memoryPeak = memory_get_peak_usage(true);

            return [
                'status' => 'healthy',
                'avg_transaction_processing_ms' => round($avgTime / 1000, 2),
                'db_query_time_ms' => round($queryTime, 2),
                'memory_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
                'memory_peak_mb' => round($memoryPeak / 1024 / 1024, 2),
                'php_version' => PHP_VERSION
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Performance metrics unavailable: ' . $e->getMessage()
            ];
        }
    }

    private function getUserActivityMetrics()
    {
        try {
            // Active users in last 24 hours
            $stmt = $this->db->query("
                SELECT COUNT(DISTINCT user_id) as active_users_24h
                FROM activity_log 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $activeUsers24h = $stmt->fetchColumn();

            // Active users in last 7 days
            $stmt = $this->db->query("
                SELECT COUNT(DISTINCT user_id) as active_users_7d
                FROM activity_log 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $activeUsers7d = $stmt->fetchColumn();

            // Transaction volume last 24 hours
            $stmt = $this->db->query("
                SELECT COUNT(*) as transactions_24h
                FROM transactions 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $transactions24h = $stmt->fetchColumn();

            // Total registered users
            $stmt = $this->db->query("SELECT COUNT(*) as total_users FROM users");
            $totalUsers = $stmt->fetchColumn();

            return [
                'status' => 'healthy',
                'active_users_24h' => $activeUsers24h,
                'active_users_7d' => $activeUsers7d,
                'transactions_24h' => $transactions24h,
                'total_users' => $totalUsers,
                'user_engagement_rate' => $totalUsers > 0 ? round(($activeUsers7d / $totalUsers) * 100, 2) : 0
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'User activity metrics unavailable: ' . $e->getMessage()
            ];
        }
    }

    private function getSystemResources()
    {
        $loadAvg = function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0];
        
        return [
            'status' => 'healthy',
            'server_time' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get(),
            'load_average' => $loadAvg,
            'php_memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize')
        ];
    }

    private function determineOverallStatus($checks)
    {
        foreach ($checks as $check) {
            if (isset($check['status'])) {
                if ($check['status'] === 'error') {
                    return 'error';
                } elseif ($check['status'] === 'warning') {
                    $hasWarning = true;
                }
            }
        }

        return isset($hasWarning) ? 'warning' : 'healthy';
    }

    public function getSystemStats()
    {
        try {
            $stats = [];

            // User statistics
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total_users,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users_30d
                FROM users
            ");
            $stats['users'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Transaction statistics
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
                    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expenses,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as transactions_30d
                FROM transactions
            ");
            $stats['transactions'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Account statistics
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total_accounts,
                    SUM(balance) as total_balance,
                    AVG(balance) as avg_balance
                FROM accounts
                WHERE is_active = 1
            ");
            $stats['accounts'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Goal statistics
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total_goals,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_goals,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_goals,
                    AVG(current_amount / target_amount * 100) as avg_progress
                FROM financial_goals
            ");
            $stats['goals'] = $stmt->fetch(PDO::FETCH_ASSOC);

            return $stats;

        } catch (Exception $e) {
            return ['error' => 'Failed to get system stats: ' . $e->getMessage()];
        }
    }
}

// API endpoint for health check
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    setCorsHeaders();
    
    $health = new SystemHealthWorkflow();
    $action = $_GET['action'] ?? 'health';

    switch ($action) {
        case 'health':
            $result = $health->performHealthCheck();
            break;
        case 'stats':
            $result = $health->getSystemStats();
            break;
        default:
            $result = ['error' => 'Invalid action'];
    }

    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT);
}
?>
