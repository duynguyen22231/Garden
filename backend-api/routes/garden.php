<?php
require_once __DIR__ . '/../controllers/GardenController.php';
require_once __DIR__ . '/../models/AuthModel.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

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

$controller = new GardenController($conn);
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'search_gardens':
        $searchQuery = $_POST['search'] ?? '';
        if ($searchQuery) {
            $controller->getGardens($searchQuery, $userId, $isAdmin);
        } else {
            echo json_encode(['success' => false, 'message' => 'Từ khóa tìm kiếm không hợp lệ']);
        }
        break;

    case 'get_gardens':
        $controller->getGardens(null, $userId, $isAdmin);
        break;

    case 'get_garden_image':
        $id = $_POST['id'] ?? null;
        if ($id) {
            $controller->getGardenImage($id, $userId, $isAdmin);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        }
        break;

    case 'get_garden_by_id':
        $id = $_POST['id'] ?? null;
        if ($id) {
            $controller->getGardenById($id, $userId, $isAdmin);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        }
        break;

    case 'save_garden':
        try {
            $controller->saveGarden($_POST, $_FILES, $userId, $isAdmin);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;

    case 'update_garden':
        $id = $_POST['id'] ?? null;
        if ($id) {
            try {
                $controller->updateGarden($id, $_POST, $_FILES, $userId, $isAdmin);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        }
        break;

    case 'delete_garden':
        $id = $_POST['id'] ?? null;
        if ($id) {
            try {
                $controller->deleteGarden($id, $userId, $isAdmin);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Lỗi xóa: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        }
        break;

    case 'get_status_options':
        try {
            $controller->getStatusOptions();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;

    case 'get_users':
        // Chỉ admin mới được phép lấy danh sách người dùng
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền truy cập danh sách người dùng']);
        } else {
            try {
                $controller->getUsers();
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
            }
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ']);
        break;
}
?>