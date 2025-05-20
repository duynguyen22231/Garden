<?php
require_once __DIR__ . '/../controllers/SensorController.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$controller = new SensorController($conn);

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'latest':
        $controller->latest();
        break;
    case 'update':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->update();
        } else {
            http_response_code(405);
            echo json_encode(['message' => 'Method Not Allowed']);
        }
        break;
    case 'get_status':
        $controller->getStatus();
        break;
    case 'save_schedule':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->saveSchedule();
        } else {
            http_response_code(405);
            echo json_encode(['message' => 'Method Not Allowed']);
        }
        break;
    case 'get_schedules':
        $controller->getSchedules();
        break;
    case 'get_alerts':
        $controller->getAlerts();
        break;
    case 'update_relay':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->updateRelay();
        } else {
            http_response_code(405);
            echo json_encode(['message' => 'Method Not Allowed']);
        }
        break;
    case 'get_microcontrollers':
        $controller->getMicrocontrollers();
        break;
    default:
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->store();
        } else {
            http_response_code(404);
            echo json_encode(['message' => 'Route không tồn tại']);
        }
        break;
}
?>