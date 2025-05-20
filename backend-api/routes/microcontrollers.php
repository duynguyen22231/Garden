<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../controllers/MicrocontrollerController.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';
$controller = new MicrocontrollerController();

try {
    switch ($action) {
        case 'status':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                throw new Exception("Phương thức không được phép");
            }
            $result = $controller->getMicrocontrollerStatus();
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
            $result = $controller->addMicrocontroller($name, $garden_id, $ip_address, $status);
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
            $result = $controller->updateMicrocontroller($mcu_id, $name, $garden_id, $ip_address, $status);
            echo json_encode($result);
            break;

        case 'delete':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Phương thức không được phép");
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $mcu_id = $data['mcu_id'] ?? '';
            $result = $controller->deleteMicrocontroller($mcu_id);
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