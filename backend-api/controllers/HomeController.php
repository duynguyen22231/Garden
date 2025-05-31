<?php
require_once '../models/HomeModel.php';
require_once '../models/AuthModel.php';

class HomeController {
    private $model;
    private $authModel;

    public function __construct() {
        $this->model = new HomeModel();
        $this->authModel = new AuthModel();
    }

    public function checkLoginStatus($data) {
        $token = $data['token'] ?? '';
        if (empty($token)) {
            return ['success' => false, 'message' => 'Token không được cung cấp.'];
        }

        $tokenData = $this->authModel->findByToken($token);
        if (!$tokenData) {
            return ['success' => false, 'message' => 'Token không hợp lệ.'];
        }

        // Lấy thông tin user từ database
        $userId = $tokenData['user_id'];
        $user = $this->authModel->findById($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'Không tìm thấy user.'];
        }

        return [
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name'],
                    'is_admin' => $user['administrator_rights'] == 1 // Trả về thông tin quyền admin
                ]
            ],
            'message' => 'Đăng nhập hợp lệ.'
        ];
    }

    public function logout($token) {
        if ($token) {
            $this->authModel->deleteToken($token);
        }
        return ['success' => true, 'message' => 'Đăng xuất thành công.'];
    }

    public function getAllGardens($userId, $isAdmin) {
        try {
            $gardens = $this->model->getAll($userId, $isAdmin);
            if (empty($gardens)) {
                return ['success' => true, 'gardens' => [], 'message' => 'Không có vườn hoạt động'];
            }
            return ['success' => true, 'gardens' => $gardens];
        } catch (Exception $e) {
            error_log("Lỗi trong getAllGardens: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi khi lấy danh sách vườn: ' . $e->getMessage()];
        }
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
                header('Cache-Control: max-age=86400');
                echo $image;
                exit;
            } else {
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

    public function getAllUsers() {
        try {
            $users = $this->model->getAllUsers();
            return ['success' => true, 'users' => $users];
        } catch (Exception $e) {
            error_log("Lỗi trong getAllUsers: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi khi lấy danh sách người dùng: ' . $e->getMessage()];
        }
    }

    public function saveGarden($data, $files, $userId, $isAdmin) {
        try {
            $imageBlob = null;
            if (!empty($files['image']['tmp_name']) && is_uploaded_file($files['image']['tmp_name'])) {
                $imageBlob = file_get_contents($files['image']['tmp_name']);
                if ($imageBlob === false) {
                    throw new Exception('Không thể đọc file hình ảnh.');
                }
            }

            $gardenData = [
                'name' => htmlspecialchars($data['name']),
                'user_id' => (int)$data['owner_name'],
                'location' => htmlspecialchars($data['location']),
                'area' => (float)$data['area'],
                'note' => htmlspecialchars($data['note'] ?? ''),
                'latitude' => (float)$data['latitude'],
                'longitude' => (float)$data['longitude'],
                'img' => $imageBlob
            ];

            // Nếu không phải admin, chỉ được thêm vườn cho chính mình
            if (!$isAdmin) {
                $gardenData['user_id'] = $userId;
            }

            $success = $this->model->saveGarden($gardenData);
            return $success
                ? ['success' => true, 'message' => 'Thêm vườn thành công']
                : ['success' => false, 'message' => 'Không thể thêm vào cơ sở dữ liệu.'];
        } catch (Exception $e) {
            error_log("Lỗi trong saveGarden: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi khi lưu vườn: ' . $e->getMessage()];
        }
    }

    public function getSensorData($garden_id = null, $userId, $isAdmin) {
        try {
            // Kiểm tra quyền truy cập nếu chỉ định garden_id
            if ($garden_id && !$isAdmin) {
                $garden = $this->model->getGardenById($garden_id);
                if (!$garden || $garden['user_id'] != $userId) {
                    return ['success' => false, 'message' => 'Bạn không có quyền truy cập vườn này'];
                }
            }
            $data = $this->model->getSensorData($garden_id, $userId, $isAdmin);
            if (empty($data) && $garden_id === null) {
                return ['success' => false, 'message' => 'Không có vườn hoạt động nào được tìm thấy'];
            }
            return ['success' => true, 'data' => $data];
        } catch (Exception $e) {
            error_log("Lỗi trong getSensorData: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi khi lấy dữ liệu cảm biến: ' . $e->getMessage()];
        }
    }

    public function getChartData($garden_id = null, $userId, $isAdmin) {
        try {
            // Kiểm tra quyền truy cập nếu chỉ định garden_id
            if ($garden_id && !$isAdmin) {
                $garden = $this->model->getGardenById($garden_id);
                if (!$garden || $garden['user_id'] != $userId) {
                    return ['success' => false, 'message' => 'Bạn không có quyền truy cập vườn này'];
                }
            }
            $data = $this->model->getChartData($garden_id, $userId, $isAdmin);
            if (empty($data) && $garden_id === null) {
                return ['success' => false, 'message' => 'Không có vườn hoạt động nào được tìm thấy'];
            }
            return ['success' => true, 'data' => $data];
        } catch (Exception $e) {
            error_log("Lỗi trong getChartData: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi khi lấy dữ liệu biểu đồ: ' . $e->getMessage()];
        }
    }

    public function getAlerts($garden_id = null, $userId, $isAdmin) {
        try {
            // Kiểm tra quyền truy cập nếu chỉ định garden_id
            if ($garden_id && !$isAdmin) {
                $garden = $this->model->getGardenById($garden_id);
                if (!$garden || $garden['user_id'] != $userId) {
                    return ['success' => false, 'message' => 'Bạn không có quyền truy cập vườn này'];
                }
            }
            $alerts = $this->model->getAlerts($garden_id, $userId, $isAdmin);
            if (empty($alerts) && $garden_id === null) {
                return ['success' => false, 'message' => 'Không có vườn hoạt động nào được tìm thấy'];
            }
            return ['success' => true, 'data' => $alerts];
        } catch (Exception $e) {
            error_log("Lỗi trong getAlerts: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi khi lấy cảnh báo: ' . $e->getMessage()];
        }
    }

    public function toggleIrrigation($garden_id, $userId, $isAdmin) {
        try {
            if (!$garden_id) {
                return ['success' => false, 'message' => 'Yêu cầu garden_id để điều khiển tưới'];
            }
            // Kiểm tra quyền truy cập
            if (!$isAdmin) {
                $garden = $this->model->getGardenById($garden_id);
                if (!$garden || $garden['user_id'] != $userId) {
                    return ['success' => false, 'message' => 'Bạn không có quyền điều khiển tưới vườn này'];
                }
            }
            $success = $this->model->toggleIrrigation($garden_id);
            return ['success' => $success, 'message' => $success ? 'Điều khiển tưới thành công' : 'Lỗi khi điều khiển tưới'];
        } catch (Exception $e) {
            error_log("Lỗi trong toggleIrrigation: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi khi điều khiển tưới: ' . $e->getMessage()];
        }
    }
}
?>