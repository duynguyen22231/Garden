<?php
require_once __DIR__ . '/../models/GardenModel.php';

class GardenController {
    private $model;

    public function __construct($db) {
        $this->model = new GardenModel($db);
    }

    public function getGardens($search = '') {
        $data = $this->model->getAllGardens($search);
        echo json_encode(['success' => true, 'data' => $data]);
    }

    public function getStatusOptions() {
        $statuses = $this->model->getStatusOptions();
        echo json_encode(['success' => true, 'data' => $statuses]);
    }

    public function getUsers() {
        $users = $this->model->getAllUsers();
        echo json_encode(['success' => true, 'data' => $users]);
    }
    
    public function getGardenById($id) {
        $data = $this->model->getGardenById($id);
        if ($data) {
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy']);
        }
    }

    public function saveGarden($postData, $files) {
        if ($this->model->saveGarden($postData, $files)) {
            echo json_encode(['success' => true, 'message' => 'Lưu thành công']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Lưu thất bại']);
        }
    }

    public function updateGarden($id, $postData, $files) {
        if ($this->model->updateGarden($id, $postData, $files)) {
            echo json_encode(['success' => true, 'message' => 'Cập nhật thành công']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Cập nhật thất bại']);
        }
    }

    public function deleteGarden($id) {
        if ($this->model->deleteGarden($id)) {
            echo json_encode(['success' => true, 'message' => 'Xóa thành công']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Xóa thất bại']);
        }
    }
}
?>