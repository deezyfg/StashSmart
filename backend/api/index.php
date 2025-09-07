<?php
require_once '../config/config.php';

setCorsHeaders();

// API Router - Routes requests to appropriate controllers
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove the base path
$basePath = '/StashSmart/backend/api';
$route = str_replace($basePath, '', $path);

// Route to appropriate controller
if (strpos($route, '/auth') === 0) {
    require_once '../controllers/AuthController.php';
} elseif (strpos($route, '/transactions') === 0) {
    require_once 'transactions.php';
} elseif (strpos($route, '/categories') === 0) {
    require_once 'categories.php';
} elseif (strpos($route, '/accounts') === 0) {
    require_once 'accounts.php';
} elseif (strpos($route, '/goals') === 0) {
    require_once 'goals.php';
} elseif (strpos($route, '/budgets') === 0) {
    require_once 'budgets.php';
} elseif (strpos($route, '/dashboard') === 0) {
    require_once 'dashboard.php';
} else {
    ResponseHelper::notFound("API endpoint not found");
}
