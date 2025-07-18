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

    public function saveGarden($data, $userId, $isAdmin) {
        try {
            $imageBlob = null;
            if (!empty($data['image'])) {
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
}
?>