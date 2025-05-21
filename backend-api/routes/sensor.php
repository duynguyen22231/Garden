<?php
require_once __DIR__ . '/../controllers/SensorController.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$controller = new SensorController($conn);

$action = $_GET['action'] ?? '';
$garden_id = $_GET['garden_id'] ?? $_POST['garden_id'] ?? '';

switch ($action) {
    case 'latest':
        $controller->latest($garden_id);
        break;
    case 'update':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->update($garden_id);
        } else {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
        }
        break;
    case 'get_status':
        $controller->getStatus($garden_id);
        break;
    case 'save_schedule':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->saveSchedule($garden_id);
        } else {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
        }
        break;
    case 'get_schedules':
        $controller->getSchedules($garden_id);
        break;
    case 'get_alerts':
        $controller->getAlerts($garden_id);
        break;
    case 'update_relay':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->updateRelay($garden_id);
        } else {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
        }
        break;
    case 'get_microcontrollers':
        $controller->getMicrocontrollers($garden_id);
        break;
    default:
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->store($garden_id);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Route không tồn tại']);
        }
        break;
}
?>