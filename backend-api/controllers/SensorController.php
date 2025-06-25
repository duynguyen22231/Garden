<?php
require_once __DIR__ . '/../models/SensorModel.php';

class SensorController {
    private $model;

    public function __construct($db) {
        $this->model = new SensorModel($db);
    }

    public function store($garden_id) {
        $input = file_get_contents("php://input");
        $data = json_decode($input, true);

        if (!$data || !isset($data['sensor_id']) || !$garden_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ hoặc thiếu sensor_id/garden_id']);
            return;
        }

        $required_fields = ['soil_moisture', 'temperature', 'humidity', 'light', 'water_level_cm', 'is_raining'];
        foreach ($required_fields as $field) {
            if (isset($data[$field]) && !is_numeric($data[$field]) && $field !== 'is_raining') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Giá trị $field không hợp lệ"]);
                return;
            }
        }

        try {
            $success = $this->model->insertSensorData($data, $garden_id);
            if ($success) {
                $this->checkAndInsertAlerts($data, $garden_id);
                echo json_encode(['success' => true, 'message' => 'Gửi dữ liệu thành công']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Gửi dữ liệu thất bại']);
            }
        } catch (Exception $e) {
            error_log("Lỗi trong store: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
        }
    }

    public function latest($garden_id) {
        try {
            $data = $this->model->getLatestSensorData($garden_id);
            if ($data) {
                echo json_encode(['success' => true, 'data' => $data]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Không có dữ liệu cảm biến']);
            }
        } catch (Exception $e) {
            error_log("Lỗi trong latest: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Lỗi lấy dữ liệu: ' . $e->getMessage()]);
        }
    }

    public function update($garden_id) {
        if (!isset($_POST['name']) || !isset($_POST['status']) || !$garden_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Thiếu tham số name, status hoặc garden_id']);
            return;
        }

        $device = $_POST['name'];
        $status = (int)$_POST['status'];

        if (!in_array($status, [0, 1])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Giá trị status không hợp lệ']);
            return;
        }

        try {
            $success = $this->model->updateDeviceStatus($device, $status, $garden_id);
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Cập nhật trạng thái thiết bị thành công']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Cập nhật trạng thái thiết bị thất bại']);
            }
        } catch (Exception $e) {
            error_log("Lỗi trong update: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
        }
    }

    public function getStatus($garden_id) {
        try {
            $data = $this->model->getDeviceStatus($garden_id);
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            error_log("Lỗi trong getStatus: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Lỗi lấy trạng thái: ' . $e->getMessage()]);
        }
    }

    public function saveSchedule($garden_id) {
        if (!isset($_POST['device']) || !isset($_POST['action']) || !isset($_POST['time']) || !isset($_POST['days']) || !$garden_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Thiếu tham số hoặc garden_id']);
            return;
        }

        $data = [
            'device' => $_POST['device'],
            'action' => (int)$_POST['action'],
            'time' => $_POST['time'],
            'days' => explode(',', $_POST['days'])
        ];

        if (!preg_match("/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/", $data['time'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Định dạng thời gian không hợp lệ']);
            return;
        }

        if (empty($data['days'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Phải chọn ít nhất một ngày']);
            return;
        }

        try {
            $success = $this->model->saveSchedule($data, $garden_id);
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Lưu lịch thành công']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Lưu lịch thất bại']);
            }
        } catch (Exception $e) {
            error_log("Lỗi trong saveSchedule: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
        }
    }

    public function getSchedules($garden_id) {
        try {
            $data = $this->model->getSchedules($garden_id);
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            error_log("Lỗi trong getSchedules: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Lỗi lấy lịch trình: ' . $e->getMessage()]);
        }
    }

    public function getAlerts($garden_id) {
        try {
            $data = $this->model->getAlerts($garden_id);
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            error_log("Lỗi trong getAlerts: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Lỗi lấy cảnh báo: ' . $e->getMessage()]);
        }
    }

    public function updateRelay($garden_id) {
        if (!isset($_POST['name']) || !isset($_POST['status']) || !$garden_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Thiếu tham số name, status hoặc garden_id']);
            return;
        }

        $device = $_POST['name'];
        $status = (int)$_POST['status'];

        if (!in_array($status, [0, 1])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Giá trị status không hợp lệ']);
            return;
        }

        try {
            $success = $this->model->updateRelayControl($garden_id, $device, $status);
            if ($success) {
                $this->model->updateDeviceStatus($device, $status, $garden_id);
                echo json_encode(['success' => true, 'message' => 'Cập nhật trạng thái thiết bị thành công']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Cập nhật trạng thái thiết bị thất bại']);
            }
        } catch (Exception $e) {
            error_log("Lỗi trong updateRelay: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
        }
    }

    public function getMicrocontrollers($garden_id) {
        try {
            $data = $this->model->getMicrocontrollerStatus($garden_id);
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            error_log("Lỗi trong getMicrocontrollers: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Lỗi lấy trạng thái vi điều khiển: ' . $e->getMessage()]);
        }
    }

    private function checkAndInsertAlerts($data, $garden_id) {
        if (isset($data['soil_moisture']) && $data['soil_moisture'] < 20) {
            $message = "Độ ẩm đất thấp: {$data['soil_moisture']}% tại cảm biến {$data['sensor_id']}";
            $this->model->insertAlert($data['sensor_id'], $message, $garden_id);
        }
        if (isset($data['temperature']) && $data['temperature'] > 35) {
            $message = "Nhiệt độ cao: {$data['temperature']}°C tại cảm biến {$data['sensor_id']}";
            $this->model->insertAlert($data['sensor_id'], $message, $garden_id);
        }
    }
}
?>