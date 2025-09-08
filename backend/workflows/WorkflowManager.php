<?php
require_once '../config/database.php';
require_once '../models/User.php';
require_once '../models/Transaction.php';
require_once '../utils/helpers.php';

class WorkflowManager
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database;
    }

    // User Registration Workflow
    public function userRegistrationWorkflow($userData)
    {
        try {
            $this->db->beginTransaction();

            // Step 1: Create user account
            $user = new User($this->db);
            $user->full_name = $userData['full_name'];
            $user->email = $userData['email'];
            $user->mobile = $userData['mobile'] ?? null;
            $user->username = $userData['username'] ?? null;
            $user->password_hash = User::hashPassword($userData['password']);

            if (!$user->create()) {
                throw new Exception("Failed to create user account");
            }

            $userId = $user->id;

            // Step 2: Create default categories
            $this->createDefaultCategories($userId);

            // Step 3: Create default account
            $this->createDefaultAccount($userId);

            // Step 4: Apply default settings
            $this->applyDefaultSettings($userId);

            // Step 5: Log activity
            $this->logActivity($userId, 'user_registered', 'user', $userId);

            $this->db->commit();
            return ['success' => true, 'user_id' => $userId];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Transaction Creation Workflow
    public function transactionWorkflow($transactionData)
    {
        try {
            $this->db->beginTransaction();

            // Step 1: Create transaction
            $transaction = new Transaction($this->db);
            $transaction->user_id = $transactionData['user_id'];
            $transaction->account_id = $transactionData['account_id'];
            $transaction->category_id = $transactionData['category_id'];
            $transaction->type = $transactionData['type'];
            $transaction->amount = $transactionData['amount'];
            $transaction->description = $transactionData['description'];
            $transaction->transaction_date = $transactionData['transaction_date'];
            $transaction->payment_method = $transactionData['payment_method'] ?? 'other';
            $transaction->reference_number = $transactionData['reference_number'] ?? null;
            $transaction->notes = $transactionData['notes'] ?? null;
            $transaction->receipt_path = $transactionData['receipt_path'] ?? null;
            $transaction->tags = isset($transactionData['tags']) ? json_encode($transactionData['tags']) : null;

            if (!$transaction->create()) {
                throw new Exception("Failed to create transaction");
            }

            $transactionId = $transaction->id;

            // Step 2: Update budget tracking
            $this->updateBudgetTracking($transactionData['user_id'], $transactionData['category_id'], $transactionData['amount'], $transactionData['type']);

            // Step 3: Check goal progress
            $this->updateGoalProgress($transactionData['user_id'], $transactionData['amount'], $transactionData['type']);

            // Step 4: Log activity
            $this->logActivity($transactionData['user_id'], 'transaction_created', 'transaction', $transactionId);

            $this->db->commit();
            return ['success' => true, 'transaction_id' => $transactionId];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Budget Alert Workflow
    public function budgetAlertWorkflow($userId)
    {
        $alerts = [];

        // Check all active budgets for the user
        $stmt = $this->db->prepare("
            SELECT b.*, c.name as category_name 
            FROM budgets b 
            JOIN categories c ON b.category_id = c.id 
            WHERE b.user_id = ? AND b.is_active = 1 
            AND b.end_date >= CURDATE()
        ");
        $stmt->execute([$userId]);
        $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($budgets as $budget) {
            $percentage = ($budget['amount'] > 0) ? ($budget['spent_amount'] / $budget['amount']) * 100 : 0;

            if ($percentage >= 80) { // Alert threshold at 80%
                $alerts[] = [
                    'type' => 'budget_threshold',
                    'budget_id' => $budget['id'],
                    'category' => $budget['category_name'],
                    'percentage' => round($percentage, 2),
                    'spent' => $budget['spent_amount'],
                    'budget' => $budget['amount'],
                    'message' => "You've spent " . round($percentage, 1) . "% of your {$budget['category_name']} budget"
                ];
            }

            if ($percentage >= 100) {
                $alerts[] = [
                    'type' => 'budget_exceeded',
                    'budget_id' => $budget['id'],
                    'category' => $budget['category_name'],
                    'percentage' => round($percentage, 2),
                    'message' => "You've exceeded your {$budget['category_name']} budget by " . round($percentage - 100, 1) . "%"
                ];
            }
        }

        return $alerts;
    }

    // Financial Insights Workflow
    public function generateInsightsWorkflow($userId, $period = '30')
    {
        $insights = [];

        // Calculate date range
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$period} days"));

        // Income vs Expense Analysis
        $insights['income_expense'] = $this->getIncomeExpenseAnalysis($userId, $startDate, $endDate);

        // Category-wise spending
        $insights['category_spending'] = $this->getCategorySpending($userId, $startDate, $endDate);

        // Spending trends
        $insights['trends'] = $this->getSpendingTrends($userId, $startDate, $endDate);

        // Goal progress
        $insights['goal_progress'] = $this->getGoalProgress($userId);

        // Budget performance
        $insights['budget_performance'] = $this->getBudgetPerformance($userId);

        // Recommendations
        $insights['recommendations'] = $this->generateRecommendations($userId, $insights);

        return $insights;
    }

    // Dashboard Data Workflow
    public function getDashboardData($userId)
    {
        $data = [];

        // Account balances
        $stmt = $this->db->prepare("SELECT * FROM accounts WHERE user_id = ? AND is_active = 1");
        $stmt->execute([$userId]);
        $data['accounts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent transactions
        $stmt = $this->db->prepare("
            SELECT t.*, c.name as category_name, c.color as category_color, a.name as account_name
            FROM transactions t
            JOIN categories c ON t.category_id = c.id
            JOIN accounts a ON t.account_id = a.id
            WHERE t.user_id = ?
            ORDER BY t.transaction_date DESC, t.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$userId]);
        $data['recent_transactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Monthly summary
        $currentMonth = date('Y-m');
        $stmt = $this->db->prepare("
            SELECT 
                type,
                SUM(amount) as total,
                COUNT(*) as count
            FROM transactions 
            WHERE user_id = ? AND DATE_FORMAT(transaction_date, '%Y-%m') = ?
            GROUP BY type
        ");
        $stmt->execute([$userId, $currentMonth]);
        $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data['monthly_summary'] = [
            'income' => 0,
            'expense' => 0,
            'income_count' => 0,
            'expense_count' => 0
        ];

        foreach ($monthlyData as $row) {
            $data['monthly_summary'][$row['type']] = $row['total'];
            $data['monthly_summary'][$row['type'] . '_count'] = $row['count'];
        }

        $data['monthly_summary']['net'] = $data['monthly_summary']['income'] - $data['monthly_summary']['expense'];

        // Active goals
        $stmt = $this->db->prepare("
            SELECT * FROM financial_goals 
            WHERE user_id = ? AND status = 'active' 
            ORDER BY target_date ASC
        ");
        $stmt->execute([$userId]);
        $data['goals'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $data;
    }

    private function createDefaultCategories($userId)
    {
        $defaultCategories = [
            // Income categories
            ['name' => 'Salary', 'type' => 'income', 'color' => '#28a745', 'icon' => 'fas fa-money-bill-wave'],
            ['name' => 'Freelance', 'type' => 'income', 'color' => '#17a2b8', 'icon' => 'fas fa-laptop'],
            ['name' => 'Investment', 'type' => 'income', 'color' => '#6f42c1', 'icon' => 'fas fa-chart-line'],
            ['name' => 'Other Income', 'type' => 'income', 'color' => '#6c757d', 'icon' => 'fas fa-plus-circle'],

            // Expense categories
            ['name' => 'Food & Dining', 'type' => 'expense', 'color' => '#dc3545', 'icon' => 'fas fa-utensils'],
            ['name' => 'Transportation', 'type' => 'expense', 'color' => '#fd7e14', 'icon' => 'fas fa-car'],
            ['name' => 'Shopping', 'type' => 'expense', 'color' => '#e83e8c', 'icon' => 'fas fa-shopping-bag'],
            ['name' => 'Entertainment', 'type' => 'expense', 'color' => '#6f42c1', 'icon' => 'fas fa-film'],
            ['name' => 'Bills & Utilities', 'type' => 'expense', 'color' => '#ffc107', 'icon' => 'fas fa-file-invoice-dollar'],
            ['name' => 'Healthcare', 'type' => 'expense', 'color' => '#20c997', 'icon' => 'fas fa-heartbeat'],
            ['name' => 'Education', 'type' => 'expense', 'color' => '#007bff', 'icon' => 'fas fa-graduation-cap'],
            ['name' => 'Other Expenses', 'type' => 'expense', 'color' => '#6c757d', 'icon' => 'fas fa-minus-circle']
        ];

        foreach ($defaultCategories as $category) {
            $stmt = $this->db->prepare("
                INSERT INTO categories (user_id, name, type, color, icon, is_default) 
                VALUES (?, ?, ?, ?, ?, TRUE)
            ");
            $stmt->execute([
                $userId,
                $category['name'],
                $category['type'],
                $category['color'],
                $category['icon']
            ]);
        }
    }

    private function createDefaultAccount($userId)
    {
        $stmt = $this->db->prepare("
            INSERT INTO accounts (user_id, name, type, balance) 
            VALUES (?, 'Primary Account', 'checking', 0.00)
        ");
        $stmt->execute([$userId]);
    }

    private function applyDefaultSettings($userId)
    {
        $defaultSettings = [
            'currency' => 'USD',
            'date_format' => 'Y-m-d',
            'notifications_enabled' => '1',
            'budget_alerts' => '1',
            'theme' => 'light'
        ];

        foreach ($defaultSettings as $key => $value) {
            $stmt = $this->db->prepare("
                INSERT INTO user_settings (user_id, setting_key, setting_value) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$userId, $key, $value]);
        }
    }

    private function updateBudgetTracking($userId, $categoryId, $amount, $type)
    {
        if ($type !== 'expense') return;

        $stmt = $this->db->prepare("
            UPDATE budgets 
            SET spent_amount = spent_amount + ? 
            WHERE user_id = ? AND category_id = ? 
            AND start_date <= CURDATE() AND end_date >= CURDATE()
            AND is_active = 1
        ");
        $stmt->execute([$amount, $userId, $categoryId]);
    }

    private function updateGoalProgress($userId, $amount, $type)
    {
        if ($type === 'income') {
            // Update savings goals when income is added
            $stmt = $this->db->prepare("
                UPDATE financial_goals 
                SET current_amount = current_amount + ? 
                WHERE user_id = ? AND status = 'active'
            ");
            $stmt->execute([$amount * 0.1, $userId]); // Assume 10% of income goes to goals
        }
    }

    private function getIncomeExpenseAnalysis($userId, $startDate, $endDate)
    {
        $stmt = $this->db->prepare("
            SELECT 
                type,
                SUM(amount) as total,
                COUNT(*) as count,
                AVG(amount) as average
            FROM transactions 
            WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
            GROUP BY type
        ");
        $stmt->execute([$userId, $startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getCategorySpending($userId, $startDate, $endDate)
    {
        $stmt = $this->db->prepare("
            SELECT 
                c.name,
                c.color,
                SUM(t.amount) as total,
                COUNT(t.id) as count
            FROM transactions t
            JOIN categories c ON t.category_id = c.id
            WHERE t.user_id = ? AND t.transaction_date BETWEEN ? AND ?
            GROUP BY c.id, c.name, c.color
            ORDER BY total DESC
        ");
        $stmt->execute([$userId, $startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getSpendingTrends($userId, $startDate, $endDate)
    {
        $stmt = $this->db->prepare("
            SELECT 
                DATE(transaction_date) as date,
                type,
                SUM(amount) as total
            FROM transactions 
            WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
            GROUP BY DATE(transaction_date), type
            ORDER BY transaction_date
        ");
        $stmt->execute([$userId, $startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getGoalProgress($userId)
    {
        $stmt = $this->db->prepare("
            SELECT 
                *,
                (current_amount / target_amount * 100) as progress_percentage,
                DATEDIFF(target_date, CURDATE()) as days_remaining
            FROM financial_goals 
            WHERE user_id = ? AND status = 'active'
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getBudgetPerformance($userId)
    {
        $stmt = $this->db->prepare("
            SELECT 
                b.*,
                c.name as category_name,
                (b.spent_amount / b.amount * 100) as usage_percentage
            FROM budgets b
            JOIN categories c ON b.category_id = c.id
            WHERE b.user_id = ? AND b.is_active = 1
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function generateRecommendations($userId, $insights)
    {
        $recommendations = [];

        // Budget recommendations
        if (isset($insights['budget_performance'])) {
            foreach ($insights['budget_performance'] as $budget) {
                if ($budget['usage_percentage'] > 90) {
                    $recommendations[] = [
                        'type' => 'budget_warning',
                        'message' => "Consider reducing spending in {$budget['category_name']} category",
                        'priority' => 'high'
                    ];
                }
            }
        }

        // Savings recommendations
        if (isset($insights['income_expense'])) {
            $income = 0;
            $expenses = 0;

            foreach ($insights['income_expense'] as $item) {
                if ($item['type'] === 'income') $income = $item['total'];
                if ($item['type'] === 'expense') $expenses = $item['total'];
            }

            $savingsRate = $income > 0 ? (($income - $expenses) / $income) * 100 : 0;

            if ($savingsRate < 20) {
                $recommendations[] = [
                    'type' => 'savings_recommendation',
                    'message' => "Try to save at least 20% of your income. Currently saving {$savingsRate}%",
                    'priority' => 'medium'
                ];
            }
        }

        return $recommendations;
    }

    private function logActivity($userId, $action, $entityType = null, $entityId = null, $details = null)
    {
        $stmt = $this->db->prepare("
            INSERT INTO activity_log (user_id, action, entity_type, entity_id, details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            $details ? json_encode($details) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
}
