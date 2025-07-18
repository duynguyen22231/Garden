<?php
require_once '../models/SensorModel.php';
require_once '../config/database.php';

class SensorController {
    private $model;
    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->model = new SensorModel($this->db);
    }

    public function getReadings($input) {
        try {
            $garden_id = $input['garden_id'] ?? 0;
            if ($garden_id <= 0) {
                throw new Exception('ID vườn không hợp lệ');
            }
            $readings = $this->model->getSensorReadings($garden_id);
            $this->sendResponse(['success' => true, 'data' => $readings ?: ['soil_moisture' => '--', 'temperature' => '--', 'humidity' => '--', 'light' => '--', 'water_level_cm' => '--', 'is_raining' => 0]]);
        } catch (Exception $e) {
            $this->handleError($e, 500, 'Lỗi lấy dữ liệu cảm biến');
        }
    }

    public function saveSensorData($input) {
        try {
            if (!$input || !isset($input['garden_id']) || !isset($input['mcu_id']) || !isset($input['temperature']) || !isset($input['humidity'])) {
                throw new Exception('Dữ liệu JSON không hợp lệ hoặc thiếu trường bắt buộc');
            }
            $this->model->saveSensorData($input);
            $this->sendResponse(['success' => true, 'message' => 'Lưu dữ liệu thành công']);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Lỗi lưu dữ liệu cảm biến');
        }
    }

    public function updateAutoMode($input) {
        try {
            $garden_id = $input['garden_id'] ?? null;
            $mcu_id = $input['mcu_id'] ?? null;
            $auto_mode = $input['auto_mode'] ?? 0;

            if (!$garden_id || !$mcu_id) {
                throw new Exception('Thiếu tham số bắt buộc');
            }

            $this->model->updateAutoMode($garden_id, $mcu_id, $auto_mode);
            $this->sendResponse(['success' => true, 'message' => 'Cập nhật chế độ tự động thành công']);
        } catch (Exception $e) {
            $this->handleError($e, 500, 'Lỗi cập nhật chế độ tự động');
        }
    }

    public function updateRelay($input) {
        try {
            $garden_id = $input['garden_id'] ?? null;
            $device_name = $input['device_name'] ?? null;
            $status = $input['status'] ?? 0;
            $mcu_id = $input['mcu_id'] ?? null;

            if (!$garden_id || !$device_name || !$mcu_id) {
                throw new Exception('Thiếu tham số bắt buộc');
            }

            $arduino_ip = $this->model->getArduinoIp($mcu_id);
            if (!$arduino_ip) {
                throw new Exception("Không tìm thấy IP của vi điều khiển {$mcu_id}");
            }

            $garden_number_result = $this->model->getGardenNumber($garden_id);
            if (!$garden_number_result['success']) {
                throw new Exception($garden_number_result['message']);
            }
            $garden_number = $garden_number_result['garden_number'];
            $valid_devices = $garden_number == 1 ? ['den1', 'quat1', 'van_tren'] : ['den2', 'quat2', 'van_duoi'];
            if (!in_array($device_name, $valid_devices)) {
                throw new Exception("Thiết bị không hợp lệ: {$device_name} cho vườn {$garden_number}");
            }

            $this->model->updateRelay($garden_id, $device_name, $status, $mcu_id);

            $setGardenUrl = "http://{$arduino_ip}/set_garden?garden_id={$garden_id}";
            $attempts = 3;
            $success = false;
            $last_error = '';

            for ($i = 0; $i < $attempts; $i++) {
                error_log("Thử gửi set_garden lần " . ($i + 1) . " đến {$arduino_ip} cho garden_id {$garden_id}");
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $setGardenUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                $setResponse = curl_exec($ch);
                $setHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $setError = curl_error($ch);
                curl_close($ch);

                if (!$setError && $setHttpCode == 200) {
                    $success = true;
                    break;
                } else {
                    $last_error = "Lỗi set_garden lần " . ($i + 1) . " (Mã HTTP: {$setHttpCode}, Lỗi cURL: {$setError}, Phản hồi: {$setResponse})";
                    error_log($last_error);
                    sleep(1);
                }
            }

            if (!$success) {
                throw new Exception("Không thể đặt vườn trên Arduino: {$last_error}");
            }

            $controlUrl = "http://{$arduino_ip}/control?device={$device_name}&status={$status}";
            $success = false;
            $last_error = '';

            for ($i = 0; $i < $attempts; $i++) {
                error_log("Thử gửi control lần " . ($i + 1) . " đến {$arduino_ip} cho device {$device_name} với status {$status}");
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $controlUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);

                if (!$curl_error && $http_code == 200) {
                    $success = true;
                    break;
                } else {
                    $last_error = "Lỗi control lần " . ($i + 1) . " (Mã HTTP: {$http_code}, Lỗi cURL: {$curl_error}, Phản hồi: {$response})";
                    error_log($last_error);
                    sleep(1);
                }
            }

            if (!$success) {
                throw new Exception("Không thể gửi lệnh đến Arduino: {$last_error}");
            }

            $this->sendResponse(['success' => true, 'message' => 'Cập nhật relay thành công']);
        } catch (Exception $e) {
            $this->handleError($e, 500, 'Lỗi cập nhật relay: ' . $e->getMessage());
        }
    }

    public function getSchedules($input) {
        try {
            $garden_id = $input['garden_id'] ?? 0;
            if ($garden_id <= 0) {
                throw new Exception('ID vườn không hợp lệ');
            }
            $schedules = $this->model->getSchedules($garden_id);
            $this->sendResponse(['success' => true, 'data' => $schedules]);
        } catch (Exception $e) {
            $this->handleError($e, 500, 'Lỗi lấy lịch trình');
        }
    }

    public function saveSchedule($input) {
        try {
            $garden_id = $input['garden_id'] ?? null;
            $device_name = $input['device_name'] ?? null;
            $action = $input['action'] ?? null;
            $time = $input['time'] ?? null;
            $days = $input['days'] ?? null;
            $mcu_id = $input['mcu_id'] ?? null;

            if (!$garden_id || !$device_name || !$action || !$time || !$days || !$mcu_id) {
                throw new Exception('Thiếu tham số bắt buộc');
            }

            $this->model->saveSchedule($garden_id, $device_name, $action, $time, $days, $mcu_id);
            $this->sendResponse(['success' => true, 'message' => 'Lưu lịch trình thành công']);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Lỗi lưu lịch trình: ' . $e->getMessage());
        }
    }

    public function getGardenNumber($input) {
        try {
            $garden_id = $input['garden_id'] ?? 0;
            if ($garden_id <= 0) {
                throw new Exception('ID vườn không hợp lệ');
            }
            $result = $this->model->getGardenNumber($garden_id);
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            $this->sendResponse(['success' => true, 'garden_number' => $result['garden_number']]);
        } catch (Exception $e) {
            $this->handleError($e, 500, 'Lỗi lấy số vườn');
        }
    }

    public function getGardenAssignment($input) {
        try {
            $garden_id = $input['garden_id'] ?? 0;
            if ($garden_id <= 0) {
                throw new Exception('ID vườn không hợp lệ');
            }
            $result = $this->model->getGardenNumber($garden_id);
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            $this->sendResponse(['success' => true, 'garden_number' => $result['garden_number']]);
        } catch (Exception $e) {
            $this->handleError($e, 500, 'Lỗi lấy gán vườn');
        }
    }

    public function saveGardenAssignment($input) {
        try {
            $garden_id = $input['garden_id'] ?? 0;
            $garden_number = $input['garden_number'] ?? 0;
            if ($garden_id <= 0 || !in_array($garden_number, [1, 2])) {
                throw new Exception('ID vườn hoặc số vườn không hợp lệ');
            }
            $this->model->saveGardenAssignment($garden_id, $garden_number);
            $this->sendResponse(['success' => true, 'message' => 'Lưu gán vườn thành công']);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Lỗi lưu gán vườn');
        }
    }

    public function getAlerts($input) {
        try {
            $garden_id = $input['garden_id'] ?? 0;
            if ($garden_id <= 0) {
                throw new Exception('ID vườn không hợp lệ');
            }
            $alerts = $this->model->getAlerts($garden_id);
            $this->sendResponse(['success' => true, 'data' => $alerts]);
        } catch (Exception $e) {
            $this->handleError($e, 500, 'Lỗi lấy cảnh báo');
        }
    }

    public function getMicrocontrollers($input) {
        try {
            $garden_id = $input['garden_id'] ?? 0;
            if ($garden_id <= 0) {
                throw new Exception('ID vườn không hợp lệ');
            }
            $microcontrollers = $this->model->getMicrocontrollers($garden_id);
            $this->sendResponse(['success' => true, 'data' => $microcontrollers]);
        } catch (Exception $e) {
            $this->handleError($e, 500, 'Lỗi lấy vi điều khiển');
        }
    }

    public function initializeMcuAndDevices($input) {
        try {
            $mcu_id = $input['mcu_id'] ?? '';
            if (empty($mcu_id)) {
                throw new Exception('Thiếu mcu_id');
            }
            $this->model->initializeMcuAndDevices($mcu_id);
            $this->sendResponse(['success' => true, 'data' => ['message' => 'Khởi tạo thành công']]);
        } catch (Exception $e) {
            $this->handleError($e, 500, 'Lỗi khởi tạo MCU và thiết bị');
        }
    }

    public function getStatus($input) {
        try {
            $garden_id = $input['garden_id'] ?? 0;
            $mcu_id = $input['mcu_id'] ?? '';
            if ($garden_id <= 0 || empty($mcu_id)) {
                throw new Exception('ID vườn hoặc mcu_id không hợp lệ');
            }
            $status = $this->model->getStatus($garden_id, $mcu_id);
            $this->sendResponse(['success' => true, 'data' => $status]);
        } catch (Exception $e) {
            $this->handleError($e, 500, 'Lỗi lấy trạng thái');
        }
    }

    public function getGardenById($input) {
        try {
            $garden_id = $input['id'] ?? 0;
            if ($garden_id <= 0) {
                throw new Exception('ID vườn không hợp lệ');
            }
            $garden = $this->model->getGardenById($garden_id);
            $this->sendResponse(['success' => true, 'data' => $garden]);
        } catch (Exception $e) {
            $this->handleError($e, 500, 'Lỗi lấy thông tin vườn');
        }
    }

    public function getControlCommand($input) {
        try {
            $mcu_id = $input['mcu_id'] ?? null;
            $garden_id = $input['garden_id'] ?? null;

            if (!$mcu_id) {
                throw new Exception('Thiếu mcu_id');
            }

            $data = $this->model->getControlCommands($mcu_id, $garden_id);
            $this->sendResponse(['success' => true, 'data' => $data ?: []]);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Lỗi lấy lệnh điều khiển');
        }
    }

    public function storeAlerts($input) {
        try {
            $this->model->storeAlerts($input);
            $this->sendResponse(['success' => true, 'message' => 'Lưu cảnh báo thành công']);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Lỗi lưu cảnh báo');
        }
    }

    public function getInitialStates($input) {
        try {
            $mcu_id = $input['mcu_id'] ?? '';
            $garden_id = $input['garden_id'] ?? 0;
            if (empty($mcu_id) || $garden_id <= 0) {
                throw new Exception('Thiếu mcu_id hoặc garden_id không hợp lệ');
            }
            $states = $this->model->getInitialRelayStates($mcu_id, $garden_id);
            $this->sendResponse(['success' => true, 'data' => $states ?: []]);
        } catch (Exception $e) {
            $this->handleError($e, 500, 'Lỗi lấy trạng thái ban đầu');
        }
    }

    public function updateArduinoIp($input) {
        try {
            $mcu_id = $input['mcu_id'] ?? null;
            $ip_address = $input['ip_address'] ?? null;

            if (!$mcu_id || !$ip_address) {
                throw new Exception('Thiếu mcu_id hoặc ip_address');
            }

            $stmt = $this->db->prepare("
                UPDATE microcontrollers 
                SET ip_address = :ip_address, updated_at = NOW()
                WHERE mcu_id = :mcu_id
            ");
            $stmt->execute([
                ':ip_address' => trim($ip_address),
                ':mcu_id' => trim($mcu_id)
            ]);

            $this->sendResponse(['success' => true, 'message' => 'Cập nhật IP thành công']);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Lỗi cập nhật IP');
        }
    }

    protected function sendResponse($data) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
    }

    protected function handleError($exception, $statusCode = 500, $message = 'Lỗi máy chủ') {
        http_response_code($statusCode);
        $this->sendResponse(['success' => false, 'message' => $message]);
        error_log(date('[Y-m-d H:i:s] ') . $message . ': ' . $exception->getMessage() . PHP_EOL, 3, '../logs/sensor_errors.log');
    }
}
?>