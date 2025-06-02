<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Require controller
require_once '../controllers/AccountController.php';

// Xử lý request OPTIONS cho CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Lấy token từ header Authorization
function getBearerToken() {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }
    return null;
}

// Kiểm tra token (logic đơn giản, không dùng JWT)
function validateToken($token) {
    // Giả lập kiểm tra token: chỉ kiểm tra xem token có tồn tại không
    return !empty($token); // Nếu token rỗng, trả về false
}

// Kiểm tra token
$token = getBearerToken();
if (!$token || !validateToken($token)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Phiên đăng nhập không hợp lệ. Vui lòng đăng nhập lại.'
    ]);
    exit;
}

$controller = new AccountController();

// Đảm bảo chỉ xử lý request POST cho các hành động add, update, delete
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $action !== 'status') {
    echo json_encode([
        'success' => false,
        'message' => 'Phương thức không hợp lệ. Chỉ hỗ trợ POST cho hành động này.'
    ]);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'status':
        $result = $controller->getUserStatus();
        echo json_encode($result);
        break;

    case 'add':
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $administrator_rights = $_POST['administrator_rights'] ?? 0;
        $full_name = $_POST['full_name'] ?? '';
        $img_user = $_FILES['img_user'] ?? null;

        // Kiểm tra lỗi upload ảnh
        if ($img_user && $img_user['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($img_user['error'] !== UPLOAD_ERR_OK) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Lỗi tải lên ảnh: Mã lỗi ' . $img_user['error']
                ]);
                exit;
            }
            // Kiểm tra loại tệp
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
        $id = $_POST['id'] ?? '';
        $username = $_POST['username'] ?? null;
        $email = $_POST['email'] ?? null;
        $password = $_POST['password'] ?? null;
        $administrator_rights = $_POST['administrator_rights'] ?? null;
        $full_name = $_POST['full_name'] ?? null;
        $img_user = $_FILES['img_user'] ?? null;

        // Kiểm tra lỗi upload ảnh
        if ($img_user && $img_user['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($img_user['error'] !== UPLOAD_ERR_OK) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Lỗi tải lên ảnh: Mã lỗi ' . $img_user['error']
                ]);
                exit;
            }
            // Kiểm tra loại tệp
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
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? '';
        $result = $controller->deleteUser($id);
        echo json_encode($result);
        break;

    default:
        echo json_encode([
            'success' => false,
            'message' => 'Hành động không hợp lệ.'
        ]);
        break;
}
?>