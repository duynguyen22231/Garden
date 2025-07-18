<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, Is-Admin, Current-User-Id');

require_once '../controllers/AccountController.php';
require_once '../models/AuthModel.php';
require_once '../config/database.php';

// Handle OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Get token from Authorization header
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

// Get user information to check permissions
$userId = $tokenData['user_id'];
$user = $authModel->findById($userId);
if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy người dùng']);
    exit;
}
$isAdmin = $user['administrator_rights'] == 1;

// Override with headers if provided
$isAdmin = isset($headers['Is-Admin']) ? ($headers['Is-Admin'] === 'true') : $isAdmin;
$userId = $headers['Current-User-Id'] ?? $userId;

$controller = new AccountController();

// Handle actions
$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
    } elseif (strpos($contentType, 'multipart/form-data') !== false || strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
        $action = $_POST['action'] ?? '';
    } else {
        $action = $_GET['action'] ?? '';
    }
}

if (empty($action)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Hành động không được cung cấp']);
    exit;
}

switch ($action) {
    case 'status':
        if (!$isAdmin) {
            // Non-admins only see their own account
            $users = $controller->getUserStatus();
            if ($users['success']) {
                $users['data'] = array_filter($users['data'], fn($u) => $u['id'] == $userId);
                echo json_encode($users);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Bạn không có quyền truy cập danh sách người dùng']);
            }
            exit;
        }
        $result = $controller->getUserStatus();
        echo json_encode($result);
        break;

    case 'add':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
            exit;
        }
        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền thêm người dùng']);
            exit;
        }
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $administrator_rights = $_POST['administrator_rights'] ?? 0;
        $full_name = $_POST['full_name'] ?? '';
        $img_user = $_FILES['img_user'] ?? null;

        // Validate image upload
        if ($img_user && $img_user['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($img_user['error'] !== UPLOAD_ERR_OK) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Lỗi tải lên ảnh: Mã lỗi ' . $img_user['error']
                ]);
                exit;
            }
            $validImageTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($img_user['type'], $validImageTypes)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Định dạng ảnh không được hỗ trợ. Chỉ chấp nhận JPEG, PNG, GIF.'
                ]);
                exit;
            }
        }
        $result = $controller->addUser($username, $email, $password, $administrator_rights, $full_name, $img_user);
        echo json_encode($result);
        break;

    case 'update':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
            exit;
        }
        $id = $_POST['id'] ?? '';
        if (!$isAdmin && $id != $userId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Bạn chỉ có thể chỉnh sửa thông tin của chính mình']);
            exit;
        }
        $username = $_POST['username'] ?? null;
        $email = $_POST['email'] ?? null;
        $password = $_POST['password'] ?? null;
        $administrator_rights = $_POST['administrator_rights'] ?? null;
        $full_name = $_POST['full_name'] ?? null;
        $img_user = $_FILES['img_user'] ?? null;

        if ($img_user && $img_user['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($img_user['error'] !== UPLOAD_ERR_OK) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Lỗi tải lên ảnh: Mã lỗi ' . $img_user['error']
                ]);
                exit;
            }
            $validImageTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($img_user['type'], $validImageTypes)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Định dạng ảnh không được hỗ trợ. Chỉ chấp nhận JPEG, PNG, GIF.'
                ]);
                exit;
            }
        }
        $result = $controller->updateUser($id, $username, $email, $password, $administrator_rights, $full_name, $img_user);
        echo json_encode($result);
        break;

    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
            exit;
        }
        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xóa người dùng']);
            exit;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? '';
        $result = $controller->deleteUser($id);
        echo json_encode($result);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ']);
        break;
}
?>