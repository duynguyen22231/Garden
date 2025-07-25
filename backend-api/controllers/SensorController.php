<?php
require_once __DIR__ . '/../models/SensorModel.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email_config.php';

class SensorController {
    private $model;
    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->model = new SensorModel($this->db);
    }

    private function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function handleError(Exception $e, $statusCode = 400, $message = 'An error occurred') {
        error_log($message . ': ' . $e->getMessage());
        $this->sendResponse([
            'success' => false,
            'message' => $message . ': ' . $e->getMessage()
        ], $statusCode);
    }

    public function setThreshold($input) {
        try {
            $requiredFields = ['garden_id', 'sensor_type', 'email_enabled'];
            foreach ($requiredFields as $field) {
                if (!isset($input[$field])) {
                    throw new Exception("Thiếu trường bắt buộc: $field");
                }
            }
            $this->model->setThreshold(
                $input['garden_id'],
                $input['sensor_type'],
                $input['min_value'] ?? '',
                $input['max_value'] ?? '',
                $input['email_enabled']
            );
            $this->sendResponse(['success' => true, 'message' => 'Lưu ngưỡng thành công']);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Lỗi lưu ngưỡng');
        }
    }

    public function getThresholds($input) {
        try {
            $garden_id = $input['garden_id'] ?? 0;
            if ($garden_id <= 0) {
                throw new Exception('Invalid garden ID');
            }
            $thresholds = $this->model->getThresholds($garden_id);
            $this->sendResponse(['success' => true, 'data' => $thresholds]);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Lỗi lấy ngưỡng');
        }
    }

    public function getReadings($input) {
        try {
            $garden_number = $input['garden_number'] ?? 0;
            if (!in_array($garden_number, [1, 2])) {
                throw new Exception('Invalid garden number');
            }
            $readings = $this->model->getLatestSensorData($garden_number);
            $this->sendResponse([
                'success' => true,
                'data' => $readings ?: [
                    'soil_moisture' => 0,
                    'temperature' => 0,
                    'humidity' => 0,
                    'water_level_cm' => 0,
                    'is_raining' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ]);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Error retrieving sensor data');
        }
    }

    public function saveSensorData($input) {
        try {
            $garden_number = $input['garden_number'] ?? null;
            $data = [
                'soil_moisture' => $input['soil_moisture'] ?? 0,
                'temperature' => $input['temperature'] ?? 0,
                'humidity' => $input['humidity'] ?? 0,
                'water_level_cm' => $input['water_level_cm'] ?? 0,
                'is_raining' => $input['is_raining'] ?? 0
            ];
            if (!$garden_number || !in_array($garden_number, [1, 2])) {
                throw new Exception('Invalid garden number');
            }
            $this->model->saveSensorData($garden_number, $data);
            $this->sendResponse(['success' => true, 'message' => 'Data saved successfully']);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Error saving sensor data');
        }
    }

    public function updateRelay($input) {
        try {
            $requiredFields = ['garden_number', 'device_name', 'status'];
            foreach ($requiredFields as $field) {
                if (!isset($input[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }
            $garden_number = (int)$input['garden_number'];
            $device_name = trim($input['device_name']);
            $status = (int)$input['status'];

            if (!in_array($garden_number, [1, 2])) {
                throw new Exception("Invalid garden number");
            }
            if (!in_array($status, [0, 1])) {
                throw new Exception("Invalid device status");
            }
            $validDevices = ($garden_number === 1) ? ['den1', 'quat1'] : ['den2', 'quat2'];
            if (!in_array($device_name, $validDevices)) {
                throw new Exception("Invalid device name for garden number $garden_number");
            }

            $this->model->updateRelayStatus($garden_number, $device_name, $status);
            $this->sendResponse(['success' => true, 'message' => 'Relay updated successfully']);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Error updating relay');
        }
    }

    public function getControlCommand($input) {
        try {
            $garden_number = $input['garden_number'] ?? 0;
            if (!in_array($garden_number, [1, 2])) {
                throw new Exception('Invalid garden number');
            }
            $data = $this->model->getControlCommands($garden_number);
            $this->sendResponse(['success' => true, 'data' => $data ?: []]);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Error retrieving control commands');
        }
    }

    public function getGardenNumber($input) {
        try {
            $garden_id = $input['garden_id'] ?? 0;
            if ($garden_id <= 0) {
                throw new Exception('Invalid garden ID');
            }
            $result = $this->model->getGardenNumber($garden_id);
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            $this->sendResponse(['success' => true, 'garden_number' => $result['garden_number']]);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Error retrieving garden number');
        }
    }

    public function saveSchedule($input) {
        try {
            if (!isset($input['schedule']) || !is_array($input['schedule'])) {
                throw new Exception("Dữ liệu lịch trình không hợp lệ");
            }

            $schedule = $input['schedule'];
            $requiredFields = ['device_name', 'action', 'time', 'date', 'garden_id', 'mcu_id'];
            foreach ($requiredFields as $field) {
                if (!isset($schedule[$field])) {
                    throw new Exception("Thiếu trường bắt buộc: $field");
                }
            }

            if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $schedule['time'])) {
                throw new Exception("Định dạng thời gian không hợp lệ (HH:MM:SS)");
            }

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $schedule['date'])) {
                throw new Exception("Định dạng ngày không hợp lệ (YYYY-MM-DD)");
            }

            if (isset($schedule['end_time']) && $schedule['end_time'] !== '') {
                if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $schedule['end_time'])) {
                    throw new Exception("Định dạng thời gian kết thúc không hợp lệ (HH:MM:SS)");
                }
                if (strtotime($schedule['time']) >= strtotime($schedule['end_time'])) {
                    throw new Exception("Thời gian kết thúc phải sau thời gian bắt đầu");
                }
            }

            $this->model->saveSchedule(
                $schedule['device_name'],
                $schedule['action'],
                $schedule['time'],
                $schedule['end_time'] ?? null,
                $schedule['date'],
                $schedule['garden_id'],
                $schedule['mcu_id']
            );

            $this->sendResponse(['success' => true, 'message' => 'Lưu lịch thành công']);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Error saving schedule');
        }
    }

    public function getSchedules($input) {
        try {
            $garden_id = $input['garden_id'] ?? 0;
            if ($garden_id <= 0) {
                throw new Exception('Invalid garden ID');
            }
            $schedules = $this->model->getSchedules($garden_id);
            $this->sendResponse(['success' => true, 'data' => $schedules]);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Error retrieving schedules');
        }
    }

    public function saveGardenAssignment($input) {
        try {
            $garden_id = $input['garden_id'] ?? 0;
            $garden_number = $input['garden_number'] ?? 0;
            if ($garden_id <= 0 || !in_array($garden_number, [1, 2])) {
                throw new Exception('Invalid garden ID or garden number');
            }
            $this->model->saveGardenAssignment($garden_id, $garden_number);
            $this->sendResponse(['success' => true, 'message' => 'Garden assignment saved successfully']);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Error saving garden assignment');
        }
    }

    public function deleteSchedule($input) {
        try {
            $id = $input['id'] ?? 0;
            if ($id <= 0) {
                throw new Exception('Thiếu ID lịch trình');
            }
            $result = $this->model->deleteSchedule($id);
            if (!$result) {
                throw new Exception('Không tìm thấy lịch trình để xóa');
            }
            $this->sendResponse(['success' => true, 'message' => 'Đã xóa lịch trình thành công']);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Lỗi khi xóa lịch trình');
        }
    }

    public function getMcuId($input) {
        try {
            $mac_address = $input['mac_address'] ?? '';
            if (empty($mac_address)) {
                throw new Exception('Missing mac_address');
            }
            $result = $this->model->getMcuId($mac_address);
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            $this->sendResponse(['success' => true, 'mcu_id' => $result['mcu_id']]);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Error retrieving MCU ID');
        }
    }

    public function getGardenAssignments($input) {
        try {
            $mcu_id = $input['mcu_id'] ?? '';
            if (empty($mcu_id)) {
                throw new Exception('Missing mcu_id');
            }
            $assignments = $this->model->getGardenAssignments($mcu_id);
            $this->sendResponse(['success' => true, 'data' => $assignments]);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Error retrieving garden assignments');
        }
    }

    public function getSchedulesByMcu($input) {
        try {
            $mcu_id = $input['mcu_id'] ?? '';
            if (empty($mcu_id)) {
                throw new Exception('Missing mcu_id');
            }
            $schedules = $this->model->getSchedulesByMcu($mcu_id);
            $this->sendResponse(['success' => true, 'data' => $schedules]);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Error retrieving schedules by MCU');
        }
    }

    public function getAlerts($input) {
        try {
            $garden_id = $input['garden_id'] ?? 0;
            if ($garden_id <= 0) {
                throw new Exception('Invalid garden ID');
            }
            $alerts = $this->model->getAlerts($garden_id);
            $this->sendResponse(['success' => true, 'data' => $alerts]);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Lỗi lấy lịch sử cảnh báo');
        }
    }

    public function triggerAlert($input) {
        try {
            $requiredFields = ['garden_id', 'garden_number', 'sensor_type', 'alert_type', 'sensor_value'];
            foreach ($requiredFields as $field) {
                if (!isset($input[$field])) {
                    throw new Exception("Thiếu trường bắt buộc: $field");
                }
            }
            $garden_id = (int)$input['garden_id'];
            $garden_number = (int)$input['garden_number'];
            $sensor_type = $input['sensor_type'];
            $alert_type = $input['alert_type'];
            $sensor_value = floatval($input['sensor_value']);

            if (!in_array($garden_number, [1, 2])) {
                throw new Exception("Invalid garden number");
            }
            $validSensors = ['soil_moisture', 'temperature', 'humidity', 'water_level_cm'];
            if (!in_array($sensor_type, $validSensors)) {
                throw new Exception("Invalid sensor type");
            }

            $sensor_types = [
                'soil_moisture' => 'Độ ẩm đất',
                'temperature' => 'Nhiệt độ',
                'humidity' => 'Độ ẩm không khí',
                'water_level_cm' => 'Mực nước'
            ];
            $translated_alert_type = str_replace(
                ['Low', 'High'],
                ['thấp', 'cao'],
                $alert_type
            );
            $translated_alert_type = "Cảnh báo: {$sensor_types[$sensor_type]} $translated_alert_type ($sensor_value)";

            $this->model->saveAlert($garden_id, $garden_number, $sensor_type, $translated_alert_type, $sensor_value);
            $user_email = $this->model->getUserEmail($garden_id);
            if ($user_email) {
                $stmt = $this->db->prepare("SELECT garden_names FROM gardens WHERE id = :garden_id");
                $stmt->execute([':garden_id' => $garden_id]);
                $garden_name = $stmt->fetchColumn() ?: "Vườn không tên";

                $subject = "Cảnh báo Vườn Cây Thông Minh - $translated_alert_type";
                $body = "
                    <h2>Cảnh báo từ Hệ Thống Vườn Cây Thông Minh</h2>
                    <p><strong>Tên vườn:</strong> $garden_name</p>
                    <p><strong>Vườn số:</strong> $garden_number</p>
                    <p><strong>Loại cảm biến:</strong> {$sensor_types[$sensor_type]}</p>
                    <p><strong>Loại cảnh báo:</strong> $translated_alert_type</p>
                    <p><strong>Giá trị:</strong> $sensor_value</p>
                    <p><strong>Thời gian:</strong> " . date('Y-m-d H:i:s') . "</p>
                    <p>Vui lòng kiểm tra hệ thống để xử lý!</p>
                ";
                $result = sendAlertEmail($user_email, $subject, $body);
                if (!$result) {
                    error_log("Failed to send email to $user_email for alert_type=$translated_alert_type");
                }
            }
            $this->sendResponse(['success' => true, 'message' => 'Cảnh báo đã được kích hoạt và lưu']);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Lỗi kích hoạt cảnh báo');
        }
    }

    public function checkAndSendAlert($input) {
        try {
            $garden_id = $input['garden_id'] ?? 0;
            if ($garden_id <= 0) {
                throw new Exception('Invalid garden ID');
            }
            $garden_number = $this->model->getGardenNumber($garden_id)['garden_number'] ?? 0;
            if (!in_array($garden_number, [1, 2])) {
                throw new Exception('Invalid garden number');
            }
            $data = $this->model->getLatestSensorData($garden_number);
            if (!$data) {
                throw new Exception('No sensor data available');
            }
            $this->model->checkThresholdsAndSendAlerts($garden_id, $garden_number, $data);
            $alerts = $this->model->getAlerts($garden_id);
            $this->sendResponse([
                'success' => true,
                'alert_sent' => !empty($alerts),
                'message' => !empty($alerts) ? 'Cảnh báo đã được gửi' : 'Không có cảnh báo nào được kích hoạt'
            ]);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Lỗi kiểm tra và gửi cảnh báo');
        }
    }
}
?>