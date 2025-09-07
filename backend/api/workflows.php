<?php
require_once '../workflows/WorkflowManager.php';
require_once '../config/database.php';
require_once '../utils/helpers.php';

class WorkflowController
{
    private $workflowManager;
    private $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->workflowManager = new WorkflowManager($this->db);
    }

    public function handleRequest()
    {
        setCorsHeaders();
        
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $pathParts = explode('/', trim($path, '/'));
        
        // Extract action from URL
        $action = end($pathParts);
        
        switch ($method) {
            case 'POST':
                $this->handlePost($action);
                break;
            case 'GET':
                $this->handleGet($action);
                break;
            default:
                ResponseHelper::methodNotAllowed();
        }
    }

    private function handlePost($action)
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            ResponseHelper::error("Invalid JSON data");
        }

        switch ($action) {
            case 'register':
                $result = $this->workflowManager->userRegistrationWorkflow($data);
                if ($result['success']) {
                    ResponseHelper::success($result, "User registered successfully");
                } else {
                    ResponseHelper::error($result['error']);
                }
                break;

            case 'transaction':
                // Get user ID from authentication
                $userId = AuthMiddleware::getCurrentUser();
                $data['user_id'] = $userId;
                
                $result = $this->workflowManager->transactionWorkflow($data);
                if ($result['success']) {
                    ResponseHelper::success($result, "Transaction created successfully");
                } else {
                    ResponseHelper::error($result['error']);
                }
                break;

            default:
                ResponseHelper::notFound("Invalid action");
        }
    }

    private function handleGet($action)
    {
        $userId = null;
        
        // For most GET requests, we need authentication
        if ($action !== 'test') {
            $userId = AuthMiddleware::getCurrentUser();
        }

        switch ($action) {
            case 'alerts':
                $alerts = $this->workflowManager->budgetAlertWorkflow($userId);
                ResponseHelper::success(['alerts' => $alerts]);
                break;

            case 'insights':
                $period = $_GET['period'] ?? '30';
                $insights = $this->workflowManager->generateInsightsWorkflow($userId, $period);
                ResponseHelper::success(['insights' => $insights]);
                break;

            case 'dashboard':
                $data = $this->workflowManager->getDashboardData($userId);
                ResponseHelper::success($data);
                break;

            case 'test':
                ResponseHelper::success(['message' => 'Workflow API is working']);
                break;

            default:
                ResponseHelper::notFound("Invalid action");
        }
    }
}

// Handle the request
$controller = new WorkflowController();
$controller->handleRequest();
?>
