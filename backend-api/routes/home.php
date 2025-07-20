<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/php/logs/php_errors.log');
error_reporting(E_ALL);

ob_start();
try {
    if (!file_exists(__DIR__ . '/../controllers/HomeController.php') || !file_exists(__DIR__ . '/../models/AuthModel.php')) {
        throw new Exception('File HomeController.php hoặc AuthModel.php không tồn tại.');
    }
    require_once '../controllers/HomeController.php';
    require_once '../models/AuthModel.php';

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    $action = $input['action'] ?? $_GET['action'] ?? '';
    error_log("home.php: Nhận yêu cầu với action: '$action', input: " . json_encode($input, JSON_UNESCAPED_UNICODE));

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        ob_end_clean();
        http_response_code(204);
        exit;
    }

    $authModel = new AuthModel();
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    $token = '';
    if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
        error_log("home.php: Extracted token: " . substr($token, 0, 20) . "...");
    } else {
        error_log("home.php: No valid Authorization header: " . $authHeader);
    }

    if ($action !== 'logout') {
        if (!$token) {
            ob_end_clean();
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập hoặc token không được cung cấp']);
            exit;
        }

        $tokenData = $authModel->findByToken($token);
        if (!$tokenData) {
            ob_end_clean();
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Token không hợp lệ hoặc đã hết hạn']);
            exit;
        }

        $userId = $tokenData['user_id'];
        $user = $authModel->findById($userId);
        if (!$user) {
            ob_end_clean();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy người dùng']);
            exit;
        }
        $isAdmin = $user['administrator_rights'] == 1;
        error_log("home.php: User verified: user_id=$userId, is_admin=$isAdmin");
    }

    $controller = new HomeController();
    $garden_id = isset($input['garden_id']) ? (int)$input['garden_id'] : (isset($input['id']) ? (int)$input['id'] : null);

    switch ($action) {
        case 'check_login_status':
            $response = $controller->checkLoginStatus($input['token'] ?? $token);
            break;

        case 'logout':
            $response = $controller->logout($token);
            break;

        case 'get_users':
            if (isset($input['user_id'])) {
                $response = $controller->getUserById((int)$input['user_id']);
                if (!$response['success']) {
                    http_response_code(404);
                }
            } elseif ($isAdmin) {
                $response = $controller->getAllUsers();
            } else {
                http_response_code(403);
                $response = ['success' => false, 'message' => 'Bạn không có quyền truy cập danh sách người dùng'];
            }
            break;

        case 'get_gardens':
            $response = $controller->getAllGardens($userId, $isAdmin);
        break;

        case 'save_garden':
            error_log("save_garden request data: " . print_r($input, true));
            error_log("save_garden files: " . print_r($_FILES, true));
            $response = $controller->saveGarden($input, $_FILES, $userId, $isAdmin);
        break;

        default:
            http_response_code(400);
            $response = ['success' => false, 'message' => 'Hành động không hợp lệ: ' . $action];
            error_log("home.php: Invalid action: $action");
            break;
    }
} catch (Exception $e) {
    error_log("home.php: Fatal error: " . $e->getMessage());
    http_response_code(500);
    $response = ['success' => false, 'message' => 'Lỗi server: ' . $e->getMessage()];
}

ob_end_clean();
echo json_encode($response ?? ['success' => false, 'message' => 'Không có phản hồi từ server'], JSON_UNESCAPED_UNICODE);
exit;
?>