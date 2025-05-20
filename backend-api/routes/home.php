<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Bật ghi log lỗi
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

ob_start();
session_start();
require_once '../controllers/HomeController.php';

error_log("Nhận yêu cầu với action: " . ($_POST['action'] ?? 'không có action'));

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(204);
    exit;
}

$controller = new HomeController();
$action = $_POST['action'] ?? '';
$garden_id = isset($_POST['garden_id']) ? (int)$_POST['garden_id'] : (isset($_POST['id']) ? (int)$_POST['id'] : null);

try {
    switch ($action) {
        case 'check_login_status':
            $response = $controller->checkLoginStatus($_POST);
            break;

        case 'logout':
            $response = $controller->logout();
            break;

        case 'get_sensor_data':
            $response = $controller->getSensorData($garden_id);
            break;

        case 'get_chart_data':
            $response = $controller->getChartData($garden_id);
            break;

        case 'get_alerts':
            $response = $controller->getAlerts($garden_id);
            break;

        case 'toggle_irrigation':
            $response = $controller->toggleIrrigation($garden_id);
            break;

        case 'get_users':
            $response = $controller->getAllUsers();
            break;

        case 'get_gardens':
            $response = $controller->getAllGardens();
            break;

        case 'get_garden_image':
            if (!$garden_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Yêu cầu id vườn']);
                exit;
            }
            $controller->getGardenImage($garden_id);
            exit; // getGardenImage đã xử lý phản hồi và thoát

        case 'save_garden':
            $response = $controller->saveGarden($_POST, $_FILES);
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