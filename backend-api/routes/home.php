<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Bật ghi log lỗi
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

ob_start();
require_once '../controllers/HomeController.php';
require_once '../models/AuthModel.php';

error_log("Nhận yêu cầu với action: " . ($_POST['action'] ?? 'không có action'));

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(204);
    exit;
}

// Kiểm tra token cho tất cả các hành động (trừ logout)
$action = $_POST['action'] ?? '';
$authModel = new AuthModel();
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$token = '';
if ($authHeader && preg_match('/Bearer (.+)/', $authHeader, $matches)) {
    $token = $matches[1];
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
        echo json_encode(['success' => false, 'message' => 'Token không hợp lệ']);
        exit;
    }

    // Lấy thông tin người dùng để kiểm tra quyền
    $userId = $tokenData['user_id'];
    $user = $authModel->findById($userId);
    if (!$user) {
        ob_end_clean();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy người dùng']);
        exit;
    }
    $isAdmin = $user['administrator_rights'] == 1;
}

$controller = new HomeController();
$garden_id = isset($_POST['garden_id']) ? (int)$_POST['garden_id'] : (isset($_POST['id']) ? (int)$_POST['id'] : null);

try {
    switch ($action) {
        case 'check_login_status':
            $response = $controller->checkLoginStatus(['token' => $token]);
            break;

        case 'logout':
            $response = $controller->logout($token);
            break;

        case 'get_sensor_data':
            $response = $controller->getSensorData($garden_id, $userId, $isAdmin);
            break;

        case 'get_chart_data':
            $response = $controller->getChartData($garden_id, $userId, $isAdmin);
            break;

        case 'get_alerts':
            $response = $controller->getAlerts($garden_id, $userId, $isAdmin);
            break;

        case 'toggle_irrigation':
            $response = $controller->toggleIrrigation($garden_id, $userId, $isAdmin);
            break;

        case 'get_users':
            // Chỉ admin mới được phép lấy danh sách người dùng
            if (!$isAdmin) {
                $response = ['success' => false, 'message' => 'Bạn không có quyền truy cập danh sách người dùng'];
            } else {
                $response = $controller->getAllUsers();
            }
            break;

        case 'get_gardens':
            $response = $controller->getAllGardens($userId, $isAdmin);
            break;

        case 'get_garden_image':
            if (!$garden_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Yêu cầu id vườn']);
                exit;
            }
            $controller->getGardenImage($garden_id, $userId, $isAdmin);
            exit; // getGardenImage đã xử lý phản hồi và thoát

        case 'save_garden':
            $response = $controller->saveGarden($_POST, $_FILES, $userId, $isAdmin);
            break;

        default:
            $response = [
                'success' => false,
                'message' => 'Hành động không hợp lệ: ' . $action
            ];
            http_response_code(400);
            break;
    }
} catch (Exception $e) {
    error_log("Lỗi trong routes/home.php: " . $e->getMessage());
    $response = [
        'success' => false,
        'message' => 'Lỗi server: ' . $e->getMessage()
    ];
    http_response_code(500);
}

ob_end_clean();
if (isset($response)) {
    error_log("Phản hồi API: " . json_encode($response, JSON_UNESCAPED_UNICODE));
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} else {
    error_log("Lỗi: Không có phản hồi được tạo");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Không có phản hồi từ server']);
}
exit;
?>