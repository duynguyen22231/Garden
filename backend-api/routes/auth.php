<?php
require_once __DIR__ . '/../controllers/AuthController.php';

$authController = new AuthController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';
    $input = json_decode(file_get_contents("php://input"), true);

    switch ($action) {
        case 'register':
            $authController->register($input);
            break;
        case 'login':
            $authController->login($input);
            break;
        default:
            http_response_code(404);
            echo json_encode(['message' => 'Không tìm thấy hành động.']);
            break;
    }
} else {
    http_response_code(405);
    echo json_encode(['message' => 'Phương thức không được hỗ trợ.']);
}
