<?php
class ResponseHelper
{

    public static function success($data = null, $message = "Success", $statusCode = 200)
    {
        http_response_code($statusCode);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

    public static function error($message = "An error occurred", $statusCode = 400, $errors = null)
    {
        http_response_code($statusCode);
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        echo json_encode($response);
        exit;
    }

    public static function unauthorized($message = "Unauthorized access")
    {
        self::error($message, 401);
    }

    public static function forbidden($message = "Access forbidden")
    {
        self::error($message, 403);
    }

    public static function notFound($message = "Resource not found")
    {
        self::error($message, 404);
    }

    public static function methodNotAllowed($message = "Method not allowed")
    {
        self::error($message, 405);
    }

    public static function validationError($errors)
    {
        self::error("Validation failed", 422, $errors);
    }

    public static function serverError($message = "Internal server error")
    {
        self::error($message, 500);
    }

    public static function paginated($data, $total, $page, $pageSize, $message = "Success")
    {
        $totalPages = ceil($total / $pageSize);

        self::success([
            'items' => $data,
            'pagination' => [
                'total' => (int)$total,
                'page' => (int)$page,
                'page_size' => (int)$pageSize,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ], $message);
    }
}

class AuthMiddleware
{

    public static function authenticate()
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

        if (!$authHeader) {
            ResponseHelper::unauthorized("Authorization header missing");
        }

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            ResponseHelper::unauthorized("Invalid authorization header format");
        }

        $token = $matches[1];
        $payload = JWTHelper::verifyToken($token);

        if (!$payload) {
            ResponseHelper::unauthorized("Invalid or expired token");
        }

        return $payload;
    }

    public static function getCurrentUser()
    {
        $payload = self::authenticate();
        return $payload['user_id'];
    }
}

class FileUploader
{

    public static function uploadFile($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'], $uploadDir = 'uploads/')
    {
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload error'];
        }

        if ($file['size'] > MAX_FILE_SIZE) {
            return ['success' => false, 'message' => 'File too large'];
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedTypes)) {
            return ['success' => false, 'message' => 'Invalid file type'];
        }

        $filename = uniqid() . '_' . time() . '.' . $extension;
        $uploadPath = $uploadDir . $filename;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            return ['success' => true, 'filename' => $filename, 'path' => $uploadPath];
        }

        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }
}

class DateHelper
{

    public static function getCurrentMonth()
    {
        return date('Y-m');
    }

    public static function getCurrentYear()
    {
        return date('Y');
    }

    public static function getMonthRange($month = null)
    {
        if (!$month) {
            $month = self::getCurrentMonth();
        }

        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));

        return ['start' => $start, 'end' => $end];
    }

    public static function getYearRange($year = null)
    {
        if (!$year) {
            $year = self::getCurrentYear();
        }

        return ['start' => $year . '-01-01', 'end' => $year . '-12-31'];
    }

    public static function getWeekRange($date = null)
    {
        if (!$date) {
            $date = date('Y-m-d');
        }

        $timestamp = strtotime($date);
        $start = date('Y-m-d', strtotime('monday this week', $timestamp));
        $end = date('Y-m-d', strtotime('sunday this week', $timestamp));

        return ['start' => $start, 'end' => $end];
    }
}
