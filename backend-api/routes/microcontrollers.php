<?php
require_once '../controllers/MicrocontrollerController.php';
require_once '../models/AuthModel.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Is-Admin, Current-User-Id');

// Xử lý yêu cầu OPTIONS cho CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// Kiểm tra token
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$token = '';
if ($authHeader && preg_match('/Bearer (.+)/', $authHeader, $matches)) {
    $token = $matches[1];
}

$authModel = new AuthModel();
if (!$token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập hoặc token không được cung cấp']);
    exit;
}

$tokenData = $authModel->findByToken($token);
if (!$tokenData) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token không hợp lệ']);
    exit;
}

// Lấy thông tin người dùng để kiểm tra quyền
$userId = $tokenData['user_id'];
$user = $authModel->findById($userId);
if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy người dùng']);
    exit;
}
$isAdmin = $user['administrator_rights'] == 1;

// Sử dụng header nếu có
$isAdmin = isset($headers['Is-Admin']) ? ($headers['Is-Admin'] === 'true') : $isAdmin;
$userId = $headers['Current-User-Id'] ?? $userId;

$controller = new MicrocontrollerController();
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        case 'status':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                throw new Exception("Phương thức không được phép");
            }
            $result = $controller->getMicrocontrollerStatus($isAdmin, $userId);
            echo json_encode($result);
            break;

        case 'add':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Phương thức không được phép");
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $name = $data['name'] ?? '';
            $garden_id = $data['garden_id'] ?? '';
            $ip_address = $data['ip_address'] ?? '';
            $status = $data['status'] ?? '';
            $result = $controller->addMicrocontroller($name, $garden_id, $ip_address, $status, $isAdmin, $userId);
            echo json_encode($result);
            break;

        case 'update':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Phương thức không được phép");
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $mcu_id = $data['mcu_id'] ?? '';
            $name = $data['name'] ?? '';
            $garden_id = $data['garden_id'] ?? '';
            $ip_address = $data['ip_address'] ?? '';
            $status = $data['status'] ?? '';
            $result = $controller->updateMicrocontroller($mcu_id, $name, $garden_id, $ip_address, $status, $isAdmin, $userId);
            echo json_encode($result);
            break;

        case 'delete':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Phương thức không được phép");
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $mcu_id = $data['mcu_id'] ?? '';
            $garden_id = $data['garden_id'] ?? '';
            $result = $controller->deleteMicrocontroller($mcu_id, $garden_id, $isAdmin, $userId);
            echo json_encode($result);
            break;

        default:
            throw new Exception("Hành động không hợp lệ");
    }
} catch (Exception $e) {
    error_log("Lỗi trong routes: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>