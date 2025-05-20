<?php
require_once '../models/MicrocontrollerModel.php';

class MicrocontrollerController {
    private $microcontrollerModel;

    public function __construct() {
        try {
            $this->microcontrollerModel = new MicrocontrollerModel();
        } catch (Exception $e) {
            error_log("Lỗi khởi tạo MicrocontrollerModel: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Không thể khởi tạo model: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    public function getMicrocontrollerStatus() {
        try {
            $microcontrollers = $this->microcontrollerModel->getAllMicrocontrollers();
            return [
                'success' => true,
                'data' => $microcontrollers
            ];
        } catch (Exception $e) {
            error_log("Lỗi getMicrocontrollerStatus: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Lỗi lấy trạng thái vi điều khiển: ' . $e->getMessage()
            ];
        }
    }

    public function addMicrocontroller($name, $garden_id, $ip_address, $status) {
        try {
            if (empty($name) || empty($garden_id) || empty($ip_address) || empty($status)) {
                throw new Exception("Vui lòng điền đầy đủ thông tin");
            }
            if (!is_numeric($garden_id) || $garden_id <= 0) {
                throw new Exception("ID vườn không hợp lệ");
            }
            if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
                throw new Exception("Địa chỉ IP không hợp lệ");
            }
            $validStatuses = ['online', 'offline']; // Điều chỉnh nếu status khác
            if (!in_array($status, $validStatuses)) {
                throw new Exception("Trạng thái không hợp lệ");
            }
            $result = $this->microcontrollerModel->addMicrocontroller($name, $garden_id, $ip_address, $status);
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Thêm vi điều khiển thành công'
                ];
            } else {
                throw new Exception("Không thể thêm vi điều khiển vào CSDL");
            }
        } catch (Exception $e) {
            error_log("Lỗi addMicrocontroller: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ];
        }
    }

    public function updateMicrocontroller($mcu_id, $name, $garden_id, $ip_address, $status) {
        try {
            if (empty($mcu_id) || empty($name) || empty($garden_id) || empty($ip_address) || empty($status)) {
                throw new Exception("Vui lòng điền đầy đủ thông tin");
            }
            if (!is_numeric($garden_id) || $garden_id <= 0) {
                throw new Exception("ID vườn không hợp lệ");
            }
            if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
                throw new Exception("Địa chỉ IP không hợp lệ");
            }
            $validStatuses = ['online', 'offline']; // Điều chỉnh nếu status khác
            if (!in_array($status, $validStatuses)) {
                throw new Exception("Trạng thái không hợp lệ");
            }
            $result = $this->microcontrollerModel->updateMicrocontroller($mcu_id, $name, $garden_id, $ip_address, $status);
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Cập nhật vi điều khiển thành công'
                ];
            } else {
                throw new Exception("Không thể cập nhật vi điều khiển trong CSDL");
            }
        } catch (Exception $e) {
            error_log("Lỗi updateMicrocontroller: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ];
        }
    }

    public function deleteMicrocontroller($mcu_id) {
        try {
            if (empty($mcu_id)) {
                throw new Exception("ID vi điều khiển không hợp lệ");
            }
            $result = $this->microcontrollerModel->deleteMicrocontroller($mcu_id);
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Xóa vi điều khiển thành công'
                ];
            } else {
                throw new Exception("Không thể xóa vi điều khiển trong CSDL");
            }
        } catch (Exception $e) {
            error_log("Lỗi deleteMicrocontroller: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ];
        }
    }
}
?>