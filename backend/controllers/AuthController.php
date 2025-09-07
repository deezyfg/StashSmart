<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../models/User.php';
require_once '../utils/validator.php';
require_once '../utils/jwt.php';
require_once '../utils/helpers.php';

class AuthController
{
    private $db;
    private $user;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->user = new User($this->db);
    }

    // User registration
    public function register()
    {
        setCorsHeaders();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ResponseHelper::methodNotAllowed();
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            ResponseHelper::error("Invalid JSON data");
        }

        // Validate input
        $errors = Validator::validateUserRegistration($data);
        if (!empty($errors)) {
            ResponseHelper::validationError($errors);
        }

        // Check if email already exists
        if ($this->user->emailExists($data['email'])) {
            ResponseHelper::error("Email already exists", 409);
        }

        // Check if username already exists (if provided)
        if (!empty($data['username']) && $this->user->usernameExists($data['username'])) {
            ResponseHelper::error("Username already exists", 409);
        }

        // Set user properties
        $this->user->full_name = $data['full_name'];
        $this->user->email = $data['email'];
        $this->user->mobile = $data['mobile'] ?? null;
        $this->user->username = $data['username'] ?? null;
        $this->user->password_hash = User::hashPassword($data['password']);

        try {
            if ($this->user->create()) {
                // Create default categories and account for the user
                $this->user->createDefaultCategories();

                // Generate JWT token
                $token = JWTHelper::generateToken($this->user->id, $this->user->email);

                ResponseHelper::success([
                    'user' => $this->user->getProfile(),
                    'token' => $token
                ], "Registration successful", 201);
            } else {
                ResponseHelper::serverError("Failed to create user");
            }
        } catch (Exception $e) {
            ResponseHelper::serverError("Registration failed: " . $e->getMessage());
        }
    }

    // User login
    public function login()
    {
        setCorsHeaders();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ResponseHelper::methodNotAllowed();
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            ResponseHelper::error("Invalid JSON data");
        }

        $loginField = $data['username'] ?? null; // Can be email or username
        $password = $data['password'] ?? null;

        if (!$loginField || !$password) {
            ResponseHelper::error("Username/Email and password are required");
        }

        // Try to find user by email or username
        $userFound = false;
        if (Validator::validateEmail($loginField)) {
            $userFound = $this->user->findByEmail($loginField);
        } else {
            $userFound = $this->user->findByUsername($loginField);
        }

        if (!$userFound) {
            ResponseHelper::error("Invalid credentials", 401);
        }

        if ($this->user->status !== 'active') {
            ResponseHelper::error("Account is not active", 403);
        }

        if (!$this->user->verifyPassword($password)) {
            ResponseHelper::error("Invalid credentials", 401);
        }

        try {
            // Update last login
            $this->user->updateLastLogin();

            // Generate JWT token
            $token = JWTHelper::generateToken($this->user->id, $this->user->email);

            ResponseHelper::success([
                'user' => $this->user->getProfile(),
                'token' => $token
            ], "Login successful");
        } catch (Exception $e) {
            ResponseHelper::serverError("Login failed: " . $e->getMessage());
        }
    }

    // Get current user profile
    public function getProfile()
    {
        setCorsHeaders();

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            ResponseHelper::methodNotAllowed();
        }

        $userId = AuthMiddleware::getCurrentUser();

        if ($this->user->findById($userId)) {
            ResponseHelper::success($this->user->getProfile());
        } else {
            ResponseHelper::notFound("User not found");
        }
    }

    // Update user profile
    public function updateProfile()
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

        if (!$this->user->findById($userId)) {
            ResponseHelper::notFound("User not found");
        }

        // Validate input
        $errors = [];

        if (isset($data['full_name']) && !Validator::validateRequired($data['full_name'])) {
            $errors[] = "Full name is required";
        }

        if (isset($data['email']) && !Validator::validateEmail($data['email'])) {
            $errors[] = "Valid email is required";
        }

        if (isset($data['mobile']) && !empty($data['mobile']) && !Validator::validatePhone($data['mobile'])) {
            $errors[] = "Valid phone number is required";
        }

        if (!empty($errors)) {
            ResponseHelper::validationError($errors);
        }

        // Check if email is being changed and if it already exists
        if (isset($data['email']) && $data['email'] !== $this->user->email) {
            if ($this->user->emailExists($data['email'])) {
                ResponseHelper::error("Email already exists", 409);
            }
        }

        // Check if username is being changed and if it already exists
        if (isset($data['username']) && $data['username'] !== $this->user->username) {
            if (!empty($data['username']) && $this->user->usernameExists($data['username'])) {
                ResponseHelper::error("Username already exists", 409);
            }
        }

        // Update user properties
        if (isset($data['full_name'])) $this->user->full_name = $data['full_name'];
        if (isset($data['email'])) $this->user->email = $data['email'];
        if (isset($data['mobile'])) $this->user->mobile = $data['mobile'];
        if (isset($data['username'])) $this->user->username = $data['username'];
        if (isset($data['profile_picture'])) $this->user->profile_picture = $data['profile_picture'];

        try {
            if ($this->user->update()) {
                ResponseHelper::success($this->user->getProfile(), "Profile updated successfully");
            } else {
                ResponseHelper::serverError("Failed to update profile");
            }
        } catch (Exception $e) {
            ResponseHelper::serverError("Profile update failed: " . $e->getMessage());
        }
    }

    // Change password
    public function changePassword()
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

        $currentPassword = $data['current_password'] ?? null;
        $newPassword = $data['new_password'] ?? null;
        $confirmPassword = $data['confirm_password'] ?? null;

        if (!$currentPassword || !$newPassword || !$confirmPassword) {
            ResponseHelper::error("All password fields are required");
        }

        if ($newPassword !== $confirmPassword) {
            ResponseHelper::error("New passwords do not match");
        }

        if (!Validator::validatePassword($newPassword)) {
            ResponseHelper::error("New password must be at least " . PASSWORD_MIN_LENGTH . " characters long");
        }

        if (!$this->user->findById($userId)) {
            ResponseHelper::notFound("User not found");
        }

        if (!$this->user->verifyPassword($currentPassword)) {
            ResponseHelper::error("Current password is incorrect", 401);
        }

        try {
            $newPasswordHash = User::hashPassword($newPassword);

            if ($this->user->updatePassword($newPasswordHash)) {
                ResponseHelper::success(null, "Password changed successfully");
            } else {
                ResponseHelper::serverError("Failed to change password");
            }
        } catch (Exception $e) {
            ResponseHelper::serverError("Password change failed: " . $e->getMessage());
        }
    }

    // Verify JWT token
    public function verifyToken()
    {
        setCorsHeaders();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ResponseHelper::methodNotAllowed();
        }

        try {
            $userId = AuthMiddleware::getCurrentUser();

            if ($this->user->findById($userId)) {
                ResponseHelper::success([
                    'user' => $this->user->getProfile(),
                    'valid' => true
                ], "Token is valid");
            } else {
                ResponseHelper::unauthorized("Invalid token");
            }
        } catch (Exception $e) {
            ResponseHelper::unauthorized("Token verification failed");
        }
    }

    // Logout (client-side token removal, but can be used for logging)
    public function logout()
    {
        setCorsHeaders();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ResponseHelper::methodNotAllowed();
        }

        $userId = AuthMiddleware::getCurrentUser();

        // Log the logout activity
        $this->logActivity($userId, 'logout');

        ResponseHelper::success(null, "Logged out successfully");
    }

    // Log user activity
    private function logActivity($userId, $action, $entityType = null, $entityId = null, $details = null)
    {
        $query = "INSERT INTO activity_log 
                  (user_id, action, entity_type, entity_id, details, ip_address, user_agent) 
                  VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip_address, :user_agent)";

        $stmt = $this->db->prepare($query);

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $detailsJson = $details ? json_encode($details) : null;

        $stmt->bindParam(":user_id", $userId);
        $stmt->bindParam(":action", $action);
        $stmt->bindParam(":entity_type", $entityType);
        $stmt->bindParam(":entity_id", $entityId);
        $stmt->bindParam(":details", $detailsJson);
        $stmt->bindParam(":ip_address", $ipAddress);
        $stmt->bindParam(":user_agent", $userAgent);

        $stmt->execute();
    }
}

// Route handling
$controller = new AuthController();

$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove base path to get the endpoint
$endpoint = str_replace('/StashSmart/backend/api/auth/', '', $path);

switch ($endpoint) {
    case 'register':
        $controller->register();
        break;
    case 'login':
        $controller->login();
        break;
    case 'profile':
        $controller->getProfile();
        break;
    case 'profile/update':
        $controller->updateProfile();
        break;
    case 'change-password':
        $controller->changePassword();
        break;
    case 'verify-token':
        $controller->verifyToken();
        break;
    case 'logout':
        $controller->logout();
        break;
    default:
        ResponseHelper::notFound("Endpoint not found");
}
