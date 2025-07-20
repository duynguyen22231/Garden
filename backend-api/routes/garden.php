<?php
// Tắt hiển thị lỗi ra phản hồi
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_log("Received request for garden ID: " . $garden_id);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/GardenController.php';
require_once __DIR__ . '/../models/AuthModel.php';

// Đảm bảo thư mục log tồn tại
$logDir = __DIR__ . '/../logs/';
$logFile = $logDir . 'garden_errors.log';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
if (!file_exists($logFile)) {
    touch($logFile);
    chmod($logFile, 0666);
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, Is-Admin, Current-User-Id');

// Handle CORS preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Check database connection
if (!isset($conn) || !$conn instanceof PDO) {
    error_log('Lỗi: Kết nối cơ sở dữ liệu không được khởi tạo trong garden.php', 3, $logFile);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối cơ sở dữ liệu']);
    exit;
}

// Check token
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$token = '';
if ($authHeader && preg_match('/Bearer (.+)/', $authHeader, $matches)) {
    $token = $matches[1];
}

try {
    $authModel = new AuthModel();
    if (!$token) {
        error_log('Lỗi: Thiếu token trong yêu cầu', 3, $logFile);
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập hoặc token không được cung cấp']);
        exit;
    }

    $tokenData = $authModel->findByToken($token);
    if (!$tokenData) {
        error_log('Lỗi: Token không hợp lệ: ' . substr($token, 0, 20) . '...', 3, $logFile);
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token không hợp lệ']);
        exit;
    }

    // Get user info for authorization
    $userId = $tokenData['user_id'];
    $user = $authModel->findById($userId);
    if (!$user) {
        error_log('Lỗi: Không tìm thấy người dùng với user_id: ' . $userId, 3, $logFile);
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy người dùng']);
        exit;
    }
    $isAdmin = $user['administrator_rights'] == 1;
    $isAdmin = isset($headers['Is-Admin']) ? ($headers['Is-Admin'] === 'true') : $isAdmin;
    $userId = $headers['Current-User-Id'] ?? $userId;

    // Get action from POST or JSON
    $action = '';
    if ($_SERVER['CONTENT_TYPE'] === 'application/json') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
    } else {
        $action = $_POST['action'] ?? '';
    }
    error_log("Xử lý hành động: $action, user_id: $userId, isAdmin: " . ($isAdmin ? 'true' : 'false'), 3, $logFile);

    $controller = new GardenController($conn);

    switch ($action) {
        case 'get_all_gardens':
        case 'get_gardens':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Phương thức không được phép']);
                exit;
            }
            $controller->getGardens(null, $userId, $isAdmin);
            break;

        case 'get_gardens_by_ids':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Phương thức không được phép']);
                exit;
            }
            $ids = explode(',', $_POST['ids'] ?? '');
            $controller->getGardensByIds($ids);
            break;

        case 'search_gardens':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Phương thức không được phép']);
                exit;
            }
            $searchQuery = $_POST['search'] ?? '';
            if ($searchQuery) {
                $controller->getGardens($searchQuery, $userId, $isAdmin);
            } else {
                echo json_encode(['success' => false, 'message' => 'Từ khóa tìm kiếm không hợp lệ']);
            }
            break;

        case 'get_garden_image':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Phương thức không được phép']);
                exit;
            }
            $id = $_POST['id'] ?? null;
            if ($id) {
                $controller->getGardenImage($id, $userId, $isAdmin);
            } else {
                echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
            }
            break;

        case 'get_garden_by_id':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Phương thức không được phép']);
                exit;
            }
            $id = $_POST['id'] ?? null;
            if ($id) {
                $controller->getGardenById($id, $userId, $isAdmin);
            } else {
                echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
            }
            break;

        case 'save_garden':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Phương thức không được phép']);
                exit;
            }
            $controller->saveGarden($_POST, $_FILES, $userId, $isAdmin);
            break;

        case 'update_garden':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Phương thức không được phép']);
                exit;
            }
            $id = $_POST['id'] ?? null;
            if ($id) {
                $controller->updateGarden($id, $_POST, $_FILES, $userId, $isAdmin);
            } else {
                echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
            }
            break;

        case 'delete_garden':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Phương thức không được phép']);
                exit;
            }
            $id = $_POST['id'] ?? null;
            if ($id) {
                $controller->deleteGarden($id, $userId, $isAdmin);
            } else {
                echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
            }
            break;

        case 'get_status_options':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Phương thức không được phép']);
                exit;
            }
            $controller->getStatusOptions();
            break;

        case 'get_users':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Phương thức không được phép']);
                exit;
            }
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Bạn không có quyền truy cập danh sách người dùng']);
                exit;
            }
            $controller->getUsers();
            break;

        default:
            error_log("Hành động không hợp lệ: $action", 3, $logFile);
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ: ' . $action]);
            break;
    }
} catch (Exception $e) {
    error_log("Lỗi chung trong routes/garden.php: " . $e->getMessage(), 3, $logFile);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi máy chủ: ' . $e->getMessage()]);
}
?>