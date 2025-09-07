<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../models/Transaction.php';
require_once '../models/User.php';
require_once '../utils/validator.php';
require_once '../utils/helpers.php';

class TransactionController
{
    private $db;
    private $transaction;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->transaction = new Transaction($this->db);
    }

    // Get all transactions for current user
    public function index()
    {
        setCorsHeaders();

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            ResponseHelper::methodNotAllowed();
        }

        $userId = AuthMiddleware::getCurrentUser();

        // Get pagination parameters
        $page = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = min(MAX_PAGE_SIZE, max(1, (int)($_GET['page_size'] ?? DEFAULT_PAGE_SIZE)));

        // Get filters
        $filters = [
            'type' => $_GET['type'] ?? null,
            'category_id' => $_GET['category_id'] ?? null,
            'account_id' => $_GET['account_id'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null,
            'search' => $_GET['search'] ?? null
        ];

        // Remove empty filters
        $filters = array_filter($filters);

        try {
            $transactions = $this->transaction->getUserTransactions($userId, $page, $pageSize, $filters);
            $total = $this->transaction->getTotalCount($userId, $filters);

            ResponseHelper::paginated($transactions, $total, $page, $pageSize);
        } catch (Exception $e) {
            ResponseHelper::serverError("Failed to fetch transactions: " . $e->getMessage());
        }
    }

    // Create new transaction
    public function create()
    {
        setCorsHeaders();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ResponseHelper::methodNotAllowed();
        }

        $userId = AuthMiddleware::getCurrentUser();

        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            ResponseHelper::error("Invalid JSON data");
        }

        // Validate input
        $errors = Validator::validateTransaction($data);
        if (!empty($errors)) {
            ResponseHelper::validationError($errors);
        }

        // Verify account and category belong to user
        if (!$this->verifyUserOwnership($userId, 'accounts', $data['account_id'])) {
            ResponseHelper::error("Invalid account", 403);
        }

        if (!$this->verifyUserOwnership($userId, 'categories', $data['category_id'])) {
            ResponseHelper::error("Invalid category", 403);
        }

        // Set transaction properties
        $this->transaction->user_id = $userId;
        $this->transaction->account_id = $data['account_id'];
        $this->transaction->category_id = $data['category_id'];
        $this->transaction->type = $data['type'];
        $this->transaction->amount = $data['amount'];
        $this->transaction->description = $data['description'];
        $this->transaction->transaction_date = $data['transaction_date'];
        $this->transaction->payment_method = $data['payment_method'] ?? 'other';
        $this->transaction->reference_number = $data['reference_number'] ?? null;
        $this->transaction->notes = $data['notes'] ?? null;
        $this->transaction->receipt_path = $data['receipt_path'] ?? null;
        $this->transaction->tags = isset($data['tags']) ? json_encode($data['tags']) : null;

        try {
            if ($this->transaction->create()) {
                $createdTransaction = $this->transaction->findById($this->transaction->id, $userId);
                ResponseHelper::success($createdTransaction, "Transaction created successfully", 201);
            } else {
                ResponseHelper::serverError("Failed to create transaction");
            }
        } catch (Exception $e) {
            ResponseHelper::serverError("Transaction creation failed: " . $e->getMessage());
        }
    }

    // Get single transaction
    public function show($id)
    {
        setCorsHeaders();

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            ResponseHelper::methodNotAllowed();
        }

        $userId = AuthMiddleware::getCurrentUser();

        try {
            $transaction = $this->transaction->findById($id, $userId);

            if ($transaction) {
                ResponseHelper::success($transaction);
            } else {
                ResponseHelper::notFound("Transaction not found");
            }
        } catch (Exception $e) {
            ResponseHelper::serverError("Failed to fetch transaction: " . $e->getMessage());
        }
    }

    // Update transaction
    public function update($id)
    {
        setCorsHeaders();

        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            ResponseHelper::methodNotAllowed();
        }

        $userId = AuthMiddleware::getCurrentUser();

        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            ResponseHelper::error("Invalid JSON data");
        }

        // Check if transaction exists and belongs to user
        if (!$this->transaction->findById($id, $userId)) {
            ResponseHelper::notFound("Transaction not found");
        }

        // Validate input
        $errors = Validator::validateTransaction($data);
        if (!empty($errors)) {
            ResponseHelper::validationError($errors);
        }

        // Verify account and category belong to user
        if (!$this->verifyUserOwnership($userId, 'accounts', $data['account_id'])) {
            ResponseHelper::error("Invalid account", 403);
        }

        if (!$this->verifyUserOwnership($userId, 'categories', $data['category_id'])) {
            ResponseHelper::error("Invalid category", 403);
        }

        // Update transaction properties
        $this->transaction->id = $id;
        $this->transaction->user_id = $userId;
        $this->transaction->account_id = $data['account_id'];
        $this->transaction->category_id = $data['category_id'];
        $this->transaction->type = $data['type'];
        $this->transaction->amount = $data['amount'];
        $this->transaction->description = $data['description'];
        $this->transaction->transaction_date = $data['transaction_date'];
        $this->transaction->payment_method = $data['payment_method'] ?? 'other';
        $this->transaction->reference_number = $data['reference_number'] ?? null;
        $this->transaction->notes = $data['notes'] ?? null;
        $this->transaction->receipt_path = $data['receipt_path'] ?? null;
        $this->transaction->tags = isset($data['tags']) ? json_encode($data['tags']) : null;

        try {
            if ($this->transaction->update()) {
                $updatedTransaction = $this->transaction->findById($id, $userId);
                ResponseHelper::success($updatedTransaction, "Transaction updated successfully");
            } else {
                ResponseHelper::serverError("Failed to update transaction");
            }
        } catch (Exception $e) {
            ResponseHelper::serverError("Transaction update failed: " . $e->getMessage());
        }
    }

    // Delete transaction
    public function delete($id)
    {
        setCorsHeaders();

        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            ResponseHelper::methodNotAllowed();
        }

        $userId = AuthMiddleware::getCurrentUser();

        try {
            if ($this->transaction->delete($id, $userId)) {
                ResponseHelper::success(null, "Transaction deleted successfully");
            } else {
                ResponseHelper::notFound("Transaction not found");
            }
        } catch (Exception $e) {
            ResponseHelper::serverError("Transaction deletion failed: " . $e->getMessage());
        }
    }

    // Get spending analytics
    public function analytics()
    {
        setCorsHeaders();

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            ResponseHelper::methodNotAllowed();
        }

        $userId = AuthMiddleware::getCurrentUser();

        // Get date range parameters
        $period = $_GET['period'] ?? 'month'; // month, year, custom
        $dateRange = null;

        switch ($period) {
            case 'week':
                $dateRange = DateHelper::getWeekRange();
                break;
            case 'month':
                $dateRange = DateHelper::getMonthRange();
                break;
            case 'year':
                $dateRange = DateHelper::getYearRange();
                break;
            case 'custom':
                if (isset($_GET['date_from']) && isset($_GET['date_to'])) {
                    $dateRange = [
                        'start' => $_GET['date_from'],
                        'end' => $_GET['date_to']
                    ];
                }
                break;
        }

        try {
            $analytics = $this->transaction->getSpendingAnalytics($userId, $dateRange);
            ResponseHelper::success($analytics);
        } catch (Exception $e) {
            ResponseHelper::serverError("Failed to fetch analytics: " . $e->getMessage());
        }
    }

    // Upload receipt
    public function uploadReceipt()
    {
        setCorsHeaders();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ResponseHelper::methodNotAllowed();
        }

        $userId = AuthMiddleware::getCurrentUser();

        if (!isset($_FILES['receipt'])) {
            ResponseHelper::error("No file uploaded");
        }

        $uploadResult = FileUploader::uploadFile($_FILES['receipt'], ['jpg', 'jpeg', 'png', 'pdf'], 'uploads/receipts/');

        if ($uploadResult['success']) {
            ResponseHelper::success([
                'filename' => $uploadResult['filename'],
                'path' => $uploadResult['path']
            ], "Receipt uploaded successfully");
        } else {
            ResponseHelper::error($uploadResult['message']);
        }
    }

    // Verify user ownership of resource
    private function verifyUserOwnership($userId, $table, $resourceId)
    {
        $query = "SELECT id FROM $table WHERE id = :id AND user_id = :user_id LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":id", $resourceId);
        $stmt->bindParam(":user_id", $userId);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }
}

// Route handling
$controller = new TransactionController();

$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove base path to get the endpoint
$basePath = '/StashSmart/backend/api/transactions';
$endpoint = str_replace($basePath, '', $path);

// Handle different endpoints
if ($endpoint === '' || $endpoint === '/') {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller->index();
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->create();
    }
} elseif ($endpoint === '/analytics') {
    $controller->analytics();
} elseif ($endpoint === '/upload-receipt') {
    $controller->uploadReceipt();
} elseif (preg_match('/^\/(\d+)$/', $endpoint, $matches)) {
    $id = $matches[1];
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller->show($id);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $controller->update($id);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $controller->delete($id);
    }
} else {
    ResponseHelper::notFound("Endpoint not found");
}
