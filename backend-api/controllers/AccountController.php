<?php
require_once '../models/AccountModel.php';

class AccountController {
    private $userModel;

    public function __construct() {
        try {
            $this->userModel = new AccountModel();
        } catch (Exception $e) {
            throw new Exception("Không thể khởi tạo AccountModel: " . $e->getMessage());
        }
    }

    public function getUserStatus() {
        try {
            $users = $this->userModel->getAllUsers();
            return [
                'success' => true,
                'data' => $users
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ];
        }
    }

    public function addUser($username, $email, $password, $administrator_rights, $full_name, $img_user = null) {
        try {
            if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
                throw new Exception("Vui lòng điền đầy đủ thông tin");
            }
            $administrator_rights = (int)$administrator_rights;
            $img_data = $img_user ? file_get_contents($img_user['tmp_name']) : null;
            $result = $this->userModel->addUser($username, $email, $password, $administrator_rights, $full_name, $img_data);
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Thêm người dùng thành công'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Thêm người dùng thất bại'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ];
        }
    }

    public function updateUser($id, $username, $email, $password, $administrator_rights, $full_name, $img_user = null) {
        try {
            if (empty($id)) {
                throw new Exception("ID người dùng không hợp lệ");
            }
            $administrator_rights = (int)$administrator_rights;
            $img_data = $img_user && $img_user['error'] == 0 ? file_get_contents($img_user['tmp_name']) : null;
            $result = $this->userModel->updateUser($id, $username, $email, $password, $administrator_rights, $full_name, $img_data);
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Cập nhật người dùng thành công'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Cập nhật người dùng thất bại'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ];
        }
    }

    public function deleteUser($id) {
        try {
            if (empty($id)) {
                throw new Exception("ID người dùng không hợp lệ");
            }
            $result = $this->userModel->deleteUser($id);
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Xóa người dùng thành công'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Xóa người dùng thất bại'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ];
        }
    }
}
?>