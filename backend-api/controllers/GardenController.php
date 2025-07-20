<?php
require_once __DIR__ . '/../models/GardenModel.php';

class GardenController {
    private $model;

    public function __construct($db) {
        $this->model = new GardenModel($db);
    }

    public function getGardensByIds($ids) {
        try {
            $data = $this->model->getGardensByIds($ids);
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            error_log("Lỗi getGardensByIds: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Lỗi lấy danh sách vườn: ' . $e->getMessage()]);
        }
    }

    public function getGardens($search = '', $userId, $isAdmin) {
        $data = $this->model->getAllGardens($search, $userId, $isAdmin);
        echo json_encode(['success' => true, 'data' => $data]);
    }

    public function getGardenImage($garden_id, $userId, $isAdmin) {
        try {
            // Kiểm tra quyền truy cập vườn
            if (!$isAdmin) {
                $garden = $this->model->getGardenById($garden_id);
                if (!$garden || $garden['user_id'] != $userId) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Bạn không có quyền truy cập ảnh của vườn này']);
                    exit;
                }
            }

            $image = $this->model->getGardenImage($garden_id);
                if ($image) {
                    header('Content-Type: image/jpeg');
                    echo $image; // Đảm bảo trả về dữ liệu hình ảnh
                } else {
                    error_log("Không tìm thấy ảnh cho vườn ID: $garden_id");
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Không tìm thấy ảnh']);
                    exit;
                }
            } catch (Exception $e) {
                error_log("Lỗi trong getGardenImage: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Lỗi server: ' . $e->getMessage()]);
                exit;
            }
    }
    public function getStatusOptions() {
        $statuses = $this->model->getStatusOptions();
        echo json_encode(['success' => true, 'data' => $statuses]);
    }

    public function getUsers() {
        $users = $this->model->getAllUsers();
        echo json_encode(['success' => true, 'data' => $users]);
    }
    
    public function getGardenById($id, $userId, $isAdmin) {
        $data = $this->model->getGardenById($id);
        if ($data) {
            if (!$isAdmin && $data['user_id'] != $userId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Bạn không có quyền truy cập vườn này']);
                return;
            }
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy']);
        }
    }

    public function saveGarden($postData, $files, $userId, $isAdmin) {
        if (!$isAdmin) {
            $postData['user_id'] = $userId; // Người dùng thường chỉ được thêm vườn cho chính mình
        }
        if ($this->model->saveGarden($postData, $files)) {
            echo json_encode(['success' => true, 'message' => 'Lưu thành công']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Lưu thất bại']);
        }
    }

    public function updateGarden($id, $postData, $files, $userId, $isAdmin) {
        // Kiểm tra quyền truy cập
        $garden = $this->model->getGardenById($id);
        if (!$garden) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy vườn']);
            return;
        }
        if (!$isAdmin && $garden['user_id'] != $userId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền cập nhật vườn này']);
            return;
        }

        if (!$isAdmin) {
            $postData['user_id'] = $userId; // Người dùng thường không được thay đổi user_id
        }

        if ($this->model->updateGarden($id, $postData, $files)) {
            echo json_encode(['success' => true, 'message' => 'Cập nhật thành công']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Cập nhật thất bại']);
        }
    }

    public function deleteGarden($id, $userId, $isAdmin) {
        // Kiểm tra quyền truy cập
        $garden = $this->model->getGardenById($id);
        if (!$garden) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy vườn']);
            return;
        }
        if (!$isAdmin && $garden['user_id'] != $userId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xóa vườn này']);
            return;
        }

        if ($this->model->deleteGarden($id)) {
            echo json_encode(['success' => true, 'message' => 'Xóa thành công']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Xóa thất bại']);
        }
    }
}
?>