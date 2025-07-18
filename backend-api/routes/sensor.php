<?php
require_once '../config/database.php'; 
require_once '../controllers/SensorController.php';

// Enable CORS
header('Access-Control-Allow-Origin: *'); 
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$controller = new SensorController($conn); 
$input = json_decode(file_get_contents('php://input'), true);
$action = isset($_GET['action']) ? $_GET['action'] : (isset($input['action']) ? $input['action'] : '');

try {
    switch ($action) {
        case 'get_readings':
            $garden_id = isset($input['garden_id']) ? (int)$input['garden_id'] : 0;
            if ($garden_id <= 0) {
                throw new Exception('ID vườn không hợp lệ');
            }
            $controller->getReadings($input);
            break;

        case 'save_sensor_data':
            if (!$input || !isset($input['garden_id']) || !isset($input['mcu_id']) || !isset($input['temperature']) || !isset($input['humidity'])) {
                throw new Exception('Dữ liệu JSON không hợp lệ hoặc thiếu trường bắt buộc');
            }
            $controller->saveSensorData($input);
            break;

        case 'update_auto_mode':
            if (!$input || !isset($input['garden_id']) || !isset($input['mcu_id']) || !isset($input['auto_mode'])) {
                throw new Exception('Dữ liệu chế độ tự động không hợp lệ');
            }
            $controller->updateAutoMode($input);
            break;

        case 'update_relay':
            if (!$input || !isset($input['garden_id']) || !isset($input['device_name']) || !isset($input['status']) || !isset($input['mcu_id'])) {
                throw new Exception('Thiếu hoặc giá trị không hợp lệ cho trường: status');
            }
            $controller->updateRelay($input);
            break;

        case 'get_schedules':
            $garden_id = isset($input['garden_id']) ? (int)$input['garden_id'] : 0;
            if ($garden_id <= 0) {
                throw new Exception('ID vườn không hợp lệ');
            }
            $controller->getSchedules($input);
            break;

        case 'update_arduino_ip':
            $mcu_id = isset($input['mcu_id']) ? trim($input['mcu_id']) : '';
            $ip_address = isset($input['ip_address']) ? trim($input['ip_address']) : '';
            if (empty($mcu_id) || empty($ip_address)) {
                throw new Exception('Thiếu mcu_id hoặc ip_address');
            }
            $controller->updateArduinoIp($input);
            break;

        case 'save_schedule':
            if (!$input || !isset($input['garden_id']) || !isset($input['device_name']) || !isset($input['action']) || !isset($input['time']) || !isset($input['days']) || !isset($input['mcu_id'])) {
                throw new Exception('Dữ liệu lịch trình không hợp lệ');
            }
            $controller->saveSchedule($input);
            break;

        case 'get_garden_number':
            $garden_id = isset($input['garden_id']) ? (int)$input['garden_id'] : 0;
            if ($garden_id <= 0) {
                throw new Exception('ID vườn không hợp lệ');
            }
            $controller->getGardenNumber($input);
            break;

        case 'get_garden_assignment':
            $garden_id = isset($input['garden_id']) ? (int)$input['garden_id'] : 0;
            if ($garden_id <= 0) {
                throw new Exception('ID vườn không hợp lệ');
            }
            $controller->getGardenAssignment($input);
            break;

        case 'save_garden_assignment':
            if (!$input || !isset($input['garden_id']) || !isset($input['garden_number'])) {
                throw new Exception('Dữ liệu gán vườn không hợp lệ');
            }
            $controller->saveGardenAssignment($input);
            break;

        case 'get_alerts':
            $garden_id = isset($input['garden_id']) ? (int)$input['garden_id'] : 0;
            if ($garden_id <= 0) {
                throw new Exception('ID vườn không hợp lệ');
            }
            $controller->getAlerts($input);
            break;

        case 'get_microcontrollers':
            $garden_id = isset($input['garden_id']) ? (int)$input['garden_id'] : 0;
            if ($garden_id <= 0) {
                throw new Exception('ID vườn không hợp lệ');
            }
            $controller->getMicrocontrollers($input);
            break;

        case 'initialize_mcu_and_devices':
            $mcu_id = isset($input['mcu_id']) ? trim($input['mcu_id']) : '';
            if (empty($mcu_id)) {
                throw new Exception('Thiếu mcu_id');
            }
            $controller->initializeMcuAndDevices($input);
            break;

        case 'get_status':
            $garden_id = isset($input['garden_id']) ? (int)$input['garden_id'] : 0;
            $mcu_id = isset($input['mcu_id']) ? trim($input['mcu_id']) : '';
            if ($garden_id <= 0 || empty($mcu_id)) {
                throw new Exception('ID vườn hoặc mcu_id không hợp lệ');
            }
            $controller->getStatus($input);
            break;

        case 'get_garden_by_id':
            $garden_id = isset($input['id']) ? (int)$input['id'] : 0;
            if ($garden_id <= 0) {
                throw new Exception('ID vườn không hợp lệ');
            }
            $controller->getGardenById($input);
            break;

        case 'get_control_command':
            $mcu_id = isset($input['mcu_id']) ? trim($input['mcu_id']) : (isset($_GET['mcu_id']) ? trim($_GET['mcu_id']) : '');
            $garden_id = isset($input['garden_id']) ? (int)$input['garden_id'] : (isset($_GET['garden_id']) ? (int)$_GET['garden_id'] : null);
            if (empty($mcu_id)) {
                throw new Exception('Thiếu mcu_id');
            }
            $controller->getControlCommand(['mcu_id' => $mcu_id, 'garden_id' => $garden_id]);
            break;

        case 'store_alerts':
            if (!$input) {
                throw new Exception('Dữ liệu JSON không hợp lệ');
            }
            $controller->storeAlerts($input);
            break;

        case 'get_initial_states':
            $mcu_id = isset($input['mcu_id']) ? trim($input['mcu_id']) : (isset($_GET['mcu_id']) ? trim($_GET['mcu_id']) : '');
            $garden_id = isset($input['garden_id']) ? (int)$input['garden_id'] : (isset($_GET['garden_id']) ? (int)$_GET['garden_id'] : 0);
            if (empty($mcu_id) || $garden_id <= 0) {
                throw new Exception('Thiếu mcu_id hoặc garden_id không hợp lệ');
            }
            $controller->getInitialStates(['mcu_id' => $mcu_id, 'garden_id' => $garden_id]);
            break;

        default:
            throw new Exception('Hành động không hợp lệ: ' . $action);
    }
} catch (Exception $e) {
    $logMessage = date('Y-m-d H:i:s') . ' - Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() . PHP_EOL;
    file_put_contents(__DIR__ . '/../logs/sensor_errors.log', $logMessage, FILE_APPEND);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>