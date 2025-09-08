<?php
require_once '../config/database.php';

class Transaction
{
    private $conn;
    private $table_name = "transactions";

    public $id;
    public $user_id;
    public $account_id;
    public $category_id;
    public $type;
    public $amount;
    public $description;
    public $transaction_date;
    public $payment_method;
    public $reference_number;
    public $notes;
    public $receipt_path;
    public $tags;
    public $created_at;
    public $updated_at;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Create new transaction
    public function create()
    {
        $query = "INSERT INTO " . $this->table_name . " 
                  (user_id, account_id, category_id, type, amount, description, 
                   transaction_date, payment_method, reference_number, notes, receipt_path, tags) 
                  VALUES (:user_id, :account_id, :category_id, :type, :amount, :description, 
                          :transaction_date, :payment_method, :reference_number, :notes, :receipt_path, :tags)";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->notes = htmlspecialchars(strip_tags($this->notes));
        $this->reference_number = htmlspecialchars(strip_tags($this->reference_number));

        // Bind parameters
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":account_id", $this->account_id);
        $stmt->bindParam(":category_id", $this->category_id);
        $stmt->bindParam(":type", $this->type);
        $stmt->bindParam(":amount", $this->amount);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":transaction_date", $this->transaction_date);
        $stmt->bindParam(":payment_method", $this->payment_method);
        $stmt->bindParam(":reference_number", $this->reference_number);
        $stmt->bindParam(":notes", $this->notes);
        $stmt->bindParam(":receipt_path", $this->receipt_path);
        $stmt->bindParam(":tags", $this->tags);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            $this->updateAccountBalance();
            return true;
        }

        return false;
    }

    // Get user transactions with pagination
    public function getUserTransactions($userId, $page = 1, $pageSize = 20, $filters = [])
    {
        $offset = ($page - 1) * $pageSize;

        $whereClause = "WHERE t.user_id = :user_id";
        $params = [':user_id' => $userId];

        // Apply filters
        if (!empty($filters['type'])) {
            $whereClause .= " AND t.type = :type";
            $params[':type'] = $filters['type'];
        }

        if (!empty($filters['category_id'])) {
            $whereClause .= " AND t.category_id = :category_id";
            $params[':category_id'] = $filters['category_id'];
        }

        if (!empty($filters['account_id'])) {
            $whereClause .= " AND t.account_id = :account_id";
            $params[':account_id'] = $filters['account_id'];
        }

        if (!empty($filters['date_from'])) {
            $whereClause .= " AND t.transaction_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereClause .= " AND t.transaction_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $whereClause .= " AND (t.description LIKE :search OR t.notes LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $query = "SELECT t.*, c.name as category_name, c.color as category_color, 
                         c.icon as category_icon, a.name as account_name 
                  FROM " . $this->table_name . " t
                  LEFT JOIN categories c ON t.category_id = c.id
                  LEFT JOIN accounts a ON t.account_id = a.id
                  " . $whereClause . "
                  ORDER BY t.transaction_date DESC, t.created_at DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', (int)$pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get total count for pagination
    public function getTotalCount($userId, $filters = [])
    {
        $whereClause = "WHERE user_id = :user_id";
        $params = [':user_id' => $userId];

        // Apply same filters as in getUserTransactions
        if (!empty($filters['type'])) {
            $whereClause .= " AND type = :type";
            $params[':type'] = $filters['type'];
        }

        if (!empty($filters['category_id'])) {
            $whereClause .= " AND category_id = :category_id";
            $params[':category_id'] = $filters['category_id'];
        }

        if (!empty($filters['account_id'])) {
            $whereClause .= " AND account_id = :account_id";
            $params[':account_id'] = $filters['account_id'];
        }

        if (!empty($filters['date_from'])) {
            $whereClause .= " AND transaction_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereClause .= " AND transaction_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $whereClause .= " AND (description LIKE :search OR notes LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " " . $whereClause;

        $stmt = $this->conn->prepare($query);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['total'];
    }

    // Get transaction by ID
    public function findById($id, $userId)
    {
        $query = "SELECT t.*, c.name as category_name, c.color as category_color, 
                         c.icon as category_icon, a.name as account_name 
                  FROM " . $this->table_name . " t
                  LEFT JOIN categories c ON t.category_id = c.id
                  LEFT JOIN accounts a ON t.account_id = a.id
                  WHERE t.id = :id AND t.user_id = :user_id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":user_id", $userId);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->mapFromArray($row);
            return $row;
        }

        return false;
    }

    // Update transaction
    public function update()
    {
        // Get old amount for account balance adjustment
        $oldQuery = "SELECT amount, type, account_id FROM " . $this->table_name . " WHERE id = :id";
        $oldStmt = $this->conn->prepare($oldQuery);
        $oldStmt->bindParam(":id", $this->id);
        $oldStmt->execute();
        $oldData = $oldStmt->fetch(PDO::FETCH_ASSOC);

        $query = "UPDATE " . $this->table_name . " 
                  SET account_id = :account_id, category_id = :category_id, type = :type, 
                      amount = :amount, description = :description, transaction_date = :transaction_date,
                      payment_method = :payment_method, reference_number = :reference_number, 
                      notes = :notes, receipt_path = :receipt_path, tags = :tags,
                      updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id AND user_id = :user_id";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->notes = htmlspecialchars(strip_tags($this->notes));
        $this->reference_number = htmlspecialchars(strip_tags($this->reference_number));

        // Bind parameters
        $stmt->bindParam(":account_id", $this->account_id);
        $stmt->bindParam(":category_id", $this->category_id);
        $stmt->bindParam(":type", $this->type);
        $stmt->bindParam(":amount", $this->amount);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":transaction_date", $this->transaction_date);
        $stmt->bindParam(":payment_method", $this->payment_method);
        $stmt->bindParam(":reference_number", $this->reference_number);
        $stmt->bindParam(":notes", $this->notes);
        $stmt->bindParam(":receipt_path", $this->receipt_path);
        $stmt->bindParam(":tags", $this->tags);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":user_id", $this->user_id);

        if ($stmt->execute()) {
            // Adjust account balances
            $this->adjustAccountBalance($oldData);
            return true;
        }

        return false;
    }

    // Delete transaction
    public function delete($id, $userId)
    {
        // Get transaction data for account balance adjustment
        $query = "SELECT amount, type, account_id FROM " . $this->table_name . " 
                  WHERE id = :id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":user_id", $userId);
        $stmt->execute();
        $transactionData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transactionData) {
            return false;
        }

        // Delete transaction
        $deleteQuery = "DELETE FROM " . $this->table_name . " WHERE id = :id AND user_id = :user_id";
        $deleteStmt = $this->conn->prepare($deleteQuery);
        $deleteStmt->bindParam(":id", $id);
        $deleteStmt->bindParam(":user_id", $userId);

        if ($deleteStmt->execute()) {
            // Adjust account balance
            $this->reverseAccountBalance($transactionData);
            return true;
        }

        return false;
    }

    // Get spending analytics
    public function getSpendingAnalytics($userId, $dateRange = null)
    {
        $whereClause = "WHERE user_id = :user_id";
        $params = [':user_id' => $userId];

        if ($dateRange) {
            $whereClause .= " AND transaction_date BETWEEN :date_from AND :date_to";
            $params[':date_from'] = $dateRange['start'];
            $params[':date_to'] = $dateRange['end'];
        }

        // Category-wise spending
        $categoryQuery = "SELECT c.name, c.color, c.icon, t.type,
                                 SUM(t.amount) as total_amount,
                                 COUNT(t.id) as transaction_count
                          FROM " . $this->table_name . " t
                          JOIN categories c ON t.category_id = c.id
                          " . $whereClause . "
                          GROUP BY c.id, c.name, c.color, c.icon, t.type
                          ORDER BY total_amount DESC";

        $stmt = $this->conn->prepare($categoryQuery);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $categoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Monthly trends
        $monthlyQuery = "SELECT DATE_FORMAT(transaction_date, '%Y-%m') as month,
                               type,
                               SUM(amount) as total_amount
                        FROM " . $this->table_name . "
                        " . $whereClause . "
                        GROUP BY month, type
                        ORDER BY month DESC";

        $stmt = $this->conn->prepare($monthlyQuery);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'category_breakdown' => $categoryData,
            'monthly_trends' => $monthlyData
        ];
    }

    // Update account balance when transaction is created
    private function updateAccountBalance()
    {
        $multiplier = ($this->type === 'income') ? 1 : -1;
        $balanceChange = $this->amount * $multiplier;

        $query = "UPDATE accounts SET balance = balance + :balance_change 
                  WHERE id = :account_id AND user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":balance_change", $balanceChange);
        $stmt->bindParam(":account_id", $this->account_id);
        $stmt->bindParam(":user_id", $this->user_id);

        return $stmt->execute();
    }

    // Adjust account balance when transaction is updated
    private function adjustAccountBalance($oldData)
    {
        // Reverse old transaction effect
        $oldMultiplier = ($oldData['type'] === 'income') ? -1 : 1;
        $oldBalanceChange = $oldData['amount'] * $oldMultiplier;

        // Apply new transaction effect
        $newMultiplier = ($this->type === 'income') ? 1 : -1;
        $newBalanceChange = $this->amount * $newMultiplier;

        // Update old account if different
        if ($oldData['account_id'] != $this->account_id) {
            $oldAccountQuery = "UPDATE accounts SET balance = balance + :balance_change 
                               WHERE id = :account_id AND user_id = :user_id";
            $oldStmt = $this->conn->prepare($oldAccountQuery);
            $oldStmt->bindParam(":balance_change", $oldBalanceChange);
            $oldStmt->bindParam(":account_id", $oldData['account_id']);
            $oldStmt->bindParam(":user_id", $this->user_id);
            $oldStmt->execute();

            // Update new account
            $newAccountQuery = "UPDATE accounts SET balance = balance + :balance_change 
                               WHERE id = :account_id AND user_id = :user_id";
            $newStmt = $this->conn->prepare($newAccountQuery);
            $newStmt->bindParam(":balance_change", $newBalanceChange);
            $newStmt->bindParam(":account_id", $this->account_id);
            $newStmt->bindParam(":user_id", $this->user_id);
            $newStmt->execute();
        } else {
            // Same account, apply net change
            $netChange = $oldBalanceChange + $newBalanceChange;
            $query = "UPDATE accounts SET balance = balance + :balance_change 
                      WHERE id = :account_id AND user_id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":balance_change", $netChange);
            $stmt->bindParam(":account_id", $this->account_id);
            $stmt->bindParam(":user_id", $this->user_id);
            $stmt->execute();
        }
    }

    // Reverse account balance when transaction is deleted
    private function reverseAccountBalance($transactionData)
    {
        $multiplier = ($transactionData['type'] === 'income') ? -1 : 1;
        $balanceChange = $transactionData['amount'] * $multiplier;

        $query = "UPDATE accounts SET balance = balance + :balance_change 
                  WHERE id = :account_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":balance_change", $balanceChange);
        $stmt->bindParam(":account_id", $transactionData['account_id']);

        return $stmt->execute();
    }

    // Map array data to object properties
    private function mapFromArray($data)
    {
        $this->id = $data['id'];
        $this->user_id = $data['user_id'];
        $this->account_id = $data['account_id'];
        $this->category_id = $data['category_id'];
        $this->type = $data['type'];
        $this->amount = $data['amount'];
        $this->description = $data['description'];
        $this->transaction_date = $data['transaction_date'];
        $this->payment_method = $data['payment_method'];
        $this->reference_number = $data['reference_number'];
        $this->notes = $data['notes'];
        $this->receipt_path = $data['receipt_path'];
        $this->tags = $data['tags'];
        $this->created_at = $data['created_at'];
        $this->updated_at = $data['updated_at'];
    }
}
