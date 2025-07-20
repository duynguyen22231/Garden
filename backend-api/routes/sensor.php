<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../controllers/SensorController.php';
require_once '../config/database.php';

$controller = new SensorController($conn);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $input === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }
    $input = $input ?: [];

    $action = isset($input['action']) ? trim($input['action']) : (isset($_GET['action']) ? trim($_GET['action']) : '');
    if (empty($action)) {
        throw new Exception('Missing action parameter');
    }

    switch ($action) {
        case 'get_readings':
            $garden_number = isset($input['garden_number']) ? (int)$input['garden_number'] : (isset($_GET['garden_number']) ? (int)$_GET['garden_number'] : 0);
            if (!in_array($garden_number, [1, 2])) {
                throw new Exception('Invalid garden number');
            }
            $controller->getReadings(['garden_number' => $garden_number]);
            break;

        case 'save_sensor_data':
            if (empty($input)) {
                throw new Exception('Invalid input data');
            }
            $controller->saveSensorData($input);
            break;

        case 'update_relay':
            if (empty($input) || !isset($input['garden_number']) || !isset($input['device_name']) || !isset($input['status'])) {
                throw new Exception('Missing or invalid fields: garden_number, device_name, status');
            }
            $controller->updateRelay($input);
            break;

        case 'get_control_command':
            $garden_number = isset($input['garden_number']) ? (int)$input['garden_number'] : (isset($_GET['garden_number']) ? (int)$_GET['garden_number'] : 0);
            if (!in_array($garden_number, [1, 2])) {
                throw new Exception('Invalid garden number');
            }
            $controller->getControlCommand(['garden_number' => $garden_number]);
            break;

        case 'get_garden_number':
            $garden_id = isset($input['garden_id']) ? (int)$input['garden_id'] : (isset($_GET['garden_id']) ? (int)$_GET['garden_id'] : 0);
            if ($garden_id <= 0) {
                throw new Exception('Invalid garden ID');
            }
            $controller->getGardenNumber(['garden_id' => $garden_id]);
            break;

        case 'save_garden_assignment':
            if (empty($input) || !isset($input['garden_id']) || !isset($input['garden_number'])) {
                throw new Exception('Missing or invalid fields: garden_id, garden_number');
            }
            $controller->saveGardenAssignment($input);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    error_log('Error in sensor.php: action=' . ($action ?? 'unknown') . ', message=' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}