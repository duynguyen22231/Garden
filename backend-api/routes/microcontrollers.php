<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Is-Admin, Current-User-Id');

require_once '../controllers/MicrocontrollerController.php';
require_once '../config/database.php';

$controller = new MicrocontrollerController($conn);

// Handle OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $input === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }
    $input = $input ?: [];

    $action = isset($input['action']) ? trim($input['action']) : (isset($_GET['action']) ? trim($_GET['action']) : null);
    if (empty($action)) {
        throw new Exception("Missing action parameter");
    }

    // Lấy isAdmin và userId từ header
    $isAdmin = isset($_SERVER['HTTP_IS_ADMIN']) ? ($_SERVER['HTTP_IS_ADMIN'] === 'true') : false;
    $userId = isset($_SERVER['HTTP_CURRENT_USER_ID']) ? (int)$_SERVER['HTTP_CURRENT_USER_ID'] : null;

    error_log("Processing action: $action, isAdmin: $isAdmin, userId: $userId");

    switch ($action) {
        case 'check_api':
            echo json_encode(['success' => true, 'message' => 'API is running'], JSON_UNESCAPED_UNICODE);
            break;

        case 'get_mcu_id':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                throw new Exception("Action get_mcu_id only supports GET");
            }
            $garden_id = isset($_GET['garden_id']) ? (int)$_GET['garden_id'] : null;
            if (!$garden_id || $garden_id <= 0) {
                throw new Exception("Missing or invalid garden_id");
            }
            $result = $controller->getMcuId($garden_id);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'update_ip':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Action update_ip only supports POST");
            }
            $mcu_id = trim($input['mcu_id'] ?? '');
            $ip_address = trim($input['ip_address'] ?? '');
            if (!$mcu_id || !$ip_address) {
                throw new Exception("Missing mcu_id or ip_address");
            }
            $result = $controller->updateIp($mcu_id, $ip_address);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'addMicrocontroller':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Action addMicrocontroller only supports POST");
            }
            $name = trim($input['name'] ?? '');
            $garden_id = isset($input['garden_id']) ? (int)$input['garden_id'] : null;
            $ip_address = trim($input['ip_address'] ?? '');
            $status = trim($input['status'] ?? '');
            $mcu_id = trim($input['mcu_id'] ?? '');
            if (!$name || !$garden_id || !$ip_address || !$status || !$mcu_id || !$userId) {
                throw new Exception("Missing required fields");
            }
            $result = $controller->addMicrocontroller($name, $garden_id, $ip_address, $status, $isAdmin, $userId, $mcu_id);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'getMicrocontrollerStatus':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Action getMicrocontrollerStatus only supports POST");
            }
            if (!$userId) {
                throw new Exception("Missing userId");
            }
            $result = $controller->getMicrocontrollerStatus($isAdmin, $userId);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'checkMcuId':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Action checkMcuId only supports POST");
            }
            $mcu_id = trim($input['mcu_id'] ?? '');
            if (!$mcu_id) {
                throw new Exception("Missing mcu_id");
            }
            $result = $controller->checkMcuId($mcu_id);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'updateMicrocontroller':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Action updateMicrocontroller only supports POST");
            }
            $mcu_id = trim($input['mcu_id'] ?? '');
            $name = trim($input['name'] ?? '');
            $garden_id = isset($input['garden_id']) ? (int)$input['garden_id'] : null;
            $ip_address = trim($input['ip_address'] ?? '');
            $status = trim($input['status'] ?? '');
            if (!$mcu_id || !$name || !$garden_id || !$ip_address || !$status || !$userId) {
                throw new Exception("Missing required fields");
            }
            $result = $controller->updateMicrocontroller($mcu_id, $name, $garden_id, $ip_address, $status, $isAdmin, $userId);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'deleteMicrocontroller':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Action deleteMicrocontroller only supports POST");
            }
            $mcu_id = trim($input['mcu_id'] ?? '');
            $garden_id = isset($input['garden_id']) ? (int)$input['garden_id'] : null;
            if (!$mcu_id || !$garden_id || !$userId) {
                throw new Exception("Missing required fields");
            }
            $result = $controller->deleteMicrocontroller($mcu_id, $garden_id, $isAdmin, $userId);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'initialize_mcu':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Action initialize_mcu only supports POST");
            }
            $mcu_id = trim($input['mcu_id'] ?? '');
            $ip_address = trim($input['ip_address'] ?? '');
            if (!$mcu_id || !$ip_address) {
                throw new Exception("Missing mcu_id or ip_address");
            }
            $result = $controller->initializeMcu($mcu_id, $ip_address);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        default:
            throw new Exception("Invalid action: " . $action);
    }
} catch (Exception $e) {
    error_log("Error in microcontrollers.php: action=$action, message=" . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>