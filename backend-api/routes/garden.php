<?php
require_once __DIR__ . '/../controllers/GardenController.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$controller = new GardenController($conn);
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'search_gardens':
        $searchQuery = $_POST['search'] ?? '';
        if ($searchQuery) {
            $controller->getGardens($searchQuery);
        } else {
            echo json_encode(['success' => false, 'message' => 'Từ khóa tìm kiếm không hợp lệ']);
        }
        break;

    case 'get_gardens':
        $controller->getGardens();
        break;

    case 'get_garden_by_id':
        $id = $_POST['id'] ?? null;
        if ($id) {
            $controller->getGardenById($id);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        }
        break;

    case 'save_garden':
        try {
            $controller->saveGarden($_POST, $_FILES);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;

    case 'update_garden':
        $id = $_POST['id'] ?? null;
        if ($id) {
            try {
                $controller->updateGarden($id, $_POST, $_FILES);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        }
        break;

    case 'delete_garden':
        $id = $_POST['id'] ?? null;
        if ($id) {
            try {
                $controller->deleteGarden($id);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Lỗi xóa: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        }
        break;

    case 'get_status_options':
        try {
            $controller->getStatusOptions();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;

    case 'get_users':
        try {
            $controller->getUsers();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ']);
        break;
}
?>