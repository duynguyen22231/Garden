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

    public function checkLoginStatus($token) {
        error_log("checkLoginStatus called with token: " . substr($token, 0, 20) . "...");
        if (empty($token)) {
            error_log("checkLoginStatus: No token provided");
            return ['success' => false, 'message' => 'Token không được cung cấp.'];
        }

        try {
            $tokenData = $this->authModel->findByToken($token);
            if (!$tokenData) {
                error_log("checkLoginStatus: Invalid token " . substr($token, 0, 20) . "...");
                return ['success' => false, 'message' => 'Token không hợp lệ hoặc đã hết hạn.'];
            }

            $userId = $tokenData['user_id'];
            $user = $this->authModel->findById($userId);
            if (!$user) {
                error_log("checkLoginStatus: User not found for user_id $userId");
                return ['success' => false, 'message' => 'Không tìm thấy user.'];
            }

            error_log("checkLoginStatus: Valid token for user_id $userId, username={$user['username']}");
            return [
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'full_name' => $user['full_name'],
                        'administrator_rights' => $user['administrator_rights']
                    ]
                ],
                'message' => 'Đăng nhập hợp lệ.'
            ];
        } catch (Exception $e) {
            error_log("checkLoginStatus: Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi server: ' . $e->getMessage()];
        }
    }

    public function getUserById($userId) {
        try {
            if (!is_numeric($userId) || $userId <= 0) {
                error_log("getUserById: Invalid user_id $userId");
                return ['success' => false, 'message' => 'ID người dùng không hợp lệ'];
            }
            $user = $this->authModel->findById($userId);
            if (!$user) {
                error_log("getUserById: User not found for user_id $userId");
                return [
                    'success' => false,
                    'message' => 'Không tìm thấy người dùng với ID: ' . $userId,
                    'user' => null
                ];
            }
            error_log("getUserById: Success for user_id $userId");
            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name'],
                    'administrator_rights' => $user['administrator_rights']
                ],
                'message' => 'Lấy thông tin người dùng thành công'
            ];
        } catch (Exception $e) {
            error_log("getUserById: Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Lỗi khi lấy thông tin người dùng: ' . $e->getMessage(),
                'user' => null
            ];
        }
    }

    public function getAllUsers() {
        try {
            $users = $this->model->getAllUsers();
            error_log("getAllUsers: Found " . count($users) . " users");
            return [
                'success' => true,
                'users' => $users,
                'message' => count($users) ? 'Lấy danh sách người dùng thành công' : 'Không tìm thấy người dùng nào'
            ];
        } catch (Exception $e) {
            error_log("getAllUsers: Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi khi lấy danh sách người dùng: ' . $e->getMessage()];
        }
    }

    public function getAllGardens($userId, $isAdmin) {
        try {
            $gardens = $this->model->getAll($userId, $isAdmin);
            error_log("getAllGardens: userId=$userId, isAdmin=$isAdmin, found=" . count($gardens));
            return [
                'success' => true,
                'gardens' => $gardens,
                'message' => empty($gardens) ? 'Không có vườn hoạt động nào được tìm thấy' : 'Lấy danh sách vườn thành công'
            ];
        } catch (Exception $e) {
            error_log("getAllGardens: Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi khi lấy danh sách vườn: ' . $e->getMessage()];
        }
    }

    public function getGardenImage($gardenId, $userId, $isAdmin) {
        try {
            if (!$isAdmin) {
                $garden = $this->model->getGardenById($gardenId);
                if (!$garden || $garden['user_id'] != $userId) {
                    error_log("getGardenImage: Access denied for userId=$userId, gardenId=$gardenId");
                    return ['success' => false, 'message' => 'Bạn không có quyền truy cập ảnh của vườn này'];
                }
            }

            $image = $this->model->getGardenImage($gardenId);
            error_log("getGardenImage: Success for gardenId=$gardenId");
            return ['success' => true, 'image' => base64_encode($image)];
        } catch (Exception $e) {
            error_log("getGardenImage: Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi server: ' . $e->getMessage()];
        }
    }

    public function saveGarden($data, $userId, $isAdmin) {
        try {
            $imageBlob = null;
            if (!empty($data['img'])) {
                $imageBlob = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data['img']));
                if ($imageBlob === false) {
                    error_log("saveGarden: Failed to decode base64 image");
                    return ['success' => false, 'message' => 'Không thể giải mã hình ảnh'];
                }
            } elseif (!empty($data['image'])) {
                // Hỗ trợ upload file từ web
                $imageBlob = file_get_contents($data['image']['tmp_name']);
                if ($imageBlob === false) {
                    error_log("saveGarden: Failed to read uploaded image");
                    return ['success' => false, 'message' => 'Không thể đọc file hình ảnh'];
                }
            }

            $ownerId = $isAdmin && isset($data['user_id']) ? (int)$data['user_id'] : $userId;
            if (!$this->model->userExists($ownerId)) {
                error_log("saveGarden: Invalid or non-existent user_id $ownerId");
                return ['success' => false, 'message' => 'Chủ vườn không hợp lệ'];
            }

            $gardenData = [
                'name' => htmlspecialchars($data['name'] ?? ''),
                'user_id' => $ownerId,
                'location' => htmlspecialchars($data['location'] ?? null),
                'area' => (float)($data['area'] ?? null),
                'note' => htmlspecialchars($data['note'] ?? null),
                'latitude' => (float)($data['latitude'] ?? 0),
                'longitude' => (float)($data['longitude'] ?? 0),
                'img' => $imageBlob
            ];

            $success = $this->model->saveGarden($gardenData);
            error_log("saveGarden: name={$gardenData['name']}, user_id=$ownerId, success=" . ($success ? 'true' : 'false'));
            return [
                'success' => $success,
                'message' => $success ? 'Thêm vườn thành công' : 'Không thể thêm vườn vào cơ sở dữ liệu'
            ];
        } catch (Exception $e) {
            error_log("saveGarden: Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi khi lưu vườn: ' . $e->getMessage()];
        }
    }

    public function logout($token) {
        try {
            if ($token) {
                $success = $this->authModel->deleteToken($token);
                error_log("logout: Token " . substr($token, 0, 20) . "... deleted, success=" . ($success ? 'true' : 'false'));
            }
            return ['success' => true, 'message' => 'Đăng xuất thành công'];
        } catch (Exception $e) {
            error_log("logout: Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi khi đăng xuất: ' . $e->getMessage()];
        }
    }

    public function getSensorData($gardenId, $userId, $isAdmin) {
        try {
            if ($gardenId && !$isAdmin) {
                $garden = $this->model->getGardenById($gardenId);
                if (!$garden || $garden['user_id'] != $userId) {
                    error_log("getSensorData: Access denied for userId=$userId, gardenId=$gardenId");
                    return ['success' => false, 'message' => 'Bạn không có quyền truy cập vườn này'];
                }
            }
            $data = $this->model->getSensorData($gardenId, $userId, $isAdmin);
            error_log("getSensorData: gardenId=" . ($gardenId ?? 'null') . ", userId=$userId, found=" . count($data));
            return [
                'success' => !empty($data),
                'data' => $data,
                'message' => empty($data) && !$gardenId ? 'Không có vườn hoạt động nào được tìm thấy' : 'Lấy dữ liệu cảm biến thành công'
            ];
        } catch (Exception $e) {
            error_log("getSensorData: Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi khi lấy dữ liệu cảm biến: ' . $e->getMessage()];
        }
    }

    public function getChartData($gardenId, $userId, $isAdmin) {
        try {
            if ($gardenId && !$isAdmin) {
                $garden = $this->model->getGardenById($gardenId);
                if (!$garden || $garden['user_id'] != $userId) {
                    error_log("getChartData: Access denied for userId=$userId, gardenId=$gardenId");
                    return ['success' => false, 'message' => 'Bạn không có quyền truy cập vườn này'];
                }
            }
            $data = $this->model->getChartData($gardenId, $userId, $isAdmin);
            error_log("getChartData: gardenId=" . ($gardenId ?? 'null') . ", userId=$userId, found=" . count($data));
            return [
                'success' => !empty($data),
                'data' => $data,
                'message' => empty($data) && !$gardenId ? 'Không có vườn hoạt động nào được tìm thấy' : 'Lấy dữ liệu biểu đồ thành công'
            ];
        } catch (Exception $e) {
            error_log("getChartData: Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi khi lấy dữ liệu biểu đồ: ' . $e->getMessage()];
        }
    }

    public function getAlerts($gardenId, $userId, $isAdmin) {
        try {
            if ($gardenId && !$isAdmin) {
                $garden = $this->model->getGardenById($gardenId);
                if (!$garden || $garden['user_id'] != $userId) {
                    error_log("getAlerts: Access denied for userId=$userId, gardenId=$gardenId");
                    return ['success' => false, 'message' => 'Bạn không có quyền truy cập vườn này'];
                }
            }
            $alerts = $this->model->getAlerts($gardenId, $userId, $isAdmin);
            error_log("getAlerts: gardenId=" . ($gardenId ?? 'null') . ", userId=$userId, found=" . count($alerts));
            return [
                'success' => !empty($alerts),
                'data' => $alerts,
                'message' => empty($alerts) && !$gardenId ? 'Không có vườn hoạt động nào được tìm thấy' : 'Lấy cảnh báo thành công'
            ];
        } catch (Exception $e) {
            error_log("getAlerts: Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi khi lấy cảnh báo: ' . $e->getMessage()];
        }
    }

    public function toggleIrrigation($gardenId, $userId, $isAdmin) {
        try {
            if (!$gardenId) {
                error_log("toggleIrrigation: No garden_id provided");
                return ['success' => false, 'message' => 'Yêu cầu garden_id để điều khiển tưới'];
            }
            if (!$isAdmin) {
                $garden = $this->model->getGardenById($gardenId);
                if (!$garden || $garden['user_id'] != $userId) {
                    error_log("toggleIrrigation: Access denied for userId=$userId, gardenId=$gardenId");
                    return ['success' => false, 'message' => 'Bạn không có quyền điều khiển tưới vườn này'];
                }
            }
            $success = $this->model->toggleIrrigation($gardenId);
            error_log("toggleIrrigation: gardenId=$gardenId, userId=$userId, success=" . ($success ? 'true' : 'false'));
            return [
                'success' => $success,
                'message' => $success ? 'Điều khiển tưới thành công' : 'Lỗi khi điều khiển tưới'
            ];
        } catch (Exception $e) {
            error_log("toggleIrrigation: Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi khi điều khiển tưới: ' . $e->getMessage()];
        }
    }
}
?>