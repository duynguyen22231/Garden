<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/AuthModel.php';
require_once __DIR__ . '/../controllers/StatsController.php';

// Tắt hiển thị lỗi trực tiếp, ghi log vào file
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');
error_reporting(E_ALL);

try {
    $authModel = new AuthModel();
    
    // Xác thực token
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
    
    if (!$token) {
        throw new Exception('Token không được cung cấp', 401);
    }
    
    $tokenData = $authModel->findByToken($token);
    if (!$tokenData) {
        throw new Exception('Token không hợp lệ hoặc đã hết hạn', 401);
    }
    
    $userId = $tokenData['user_id'];
    $user = $authModel->findById($userId);
    $isAdmin = $user['administrator_rights'] == 1;
    
    error_log("User ID: $userId, isAdmin: " . ($isAdmin ? 'true' : 'false'));
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON decode error: ' . json_last_error_msg());
        throw new Exception('Dữ liệu đầu vào không hợp lệ: ' . json_last_error_msg(), 400);
    }
    
    $action = $input['action'] ?? '';
    error_log("Action received: $action");
    
    $statsController = new StatsController($conn);
    $response = [];
    
    switch ($action) {
        case 'get_accessible_gardens':
            $response = $statsController->getAccessibleGardens($userId, $isAdmin);
            error_log("Accessible gardens fetched: " . count($response['data'] ?? []) . " gardens for user $userId");
            break;
            
        case 'get_stats':
            $gardenIds = $input['garden_ids'] ?? [];
            if (empty($gardenIds)) {
                throw new Exception('Vui lòng chọn ít nhất một vườn', 400);
            }
            
            $timeRange = $input['time_range'] ?? '7d';
            error_log("Fetching stats for garden IDs: " . implode(',', $gardenIds) . ", timeRange: $timeRange");
            
            $result = $statsController->getGardenStats($gardenIds, $userId, $isAdmin, $timeRange);
            if ($result['success']) {
                $response = [
                    'success' => true,
                    'data' => $result['data']
                ];
            } else {
                throw new Exception($result['message'], 403);
            }
            break;
            
        default:
            throw new Exception('Hành động không hợp lệ: ' . $action, 400);
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    $errorCode = $e->getCode() ?: 500;
    error_log("Error in stats.php: $errorMessage, Code: $errorCode");
    http_response_code($errorCode);
    echo json_encode([
        'success' => false,
        'message' => $errorMessage,
        'error_code' => $errorCode
    ]);
    exit;
}
?>