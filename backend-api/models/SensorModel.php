<?php
require_once __DIR__ . '/../config/database.php';

class SensorModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getSensorReadings($garden_id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM sensor_readings WHERE garden_id = :garden_id ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([':garden_id' => (int)$garden_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("Lỗi getSensorReadings: " . $e->getMessage());
            throw new Exception("Lỗi lấy dữ liệu cảm biến: " . $e->getMessage());
        }
    }

    public function getGardenById($garden_id) {
        try {
            $stmt = $this->db->prepare("SELECT garden_names FROM gardens WHERE id = :id");
            $stmt->execute([':id' => (int)$garden_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Lỗi getGardenById: " . $e->getMessage());
            throw new Exception("Lỗi lấy thông tin vườn: " . $e->getMessage());
        }
    }

    public function getSchedules($garden_id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM schedules WHERE garden_id = :garden_id");
            $stmt->execute([':garden_id' => (int)$garden_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("Lỗi getSchedules: " . $e->getMessage());
            throw new Exception("Lỗi lấy lịch trình: " . $e->getMessage());
        }
    }

    public function getAlerts($garden_id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM alerts WHERE garden_id = :garden_id ORDER BY created_at DESC");
            $stmt->execute([':garden_id' => (int)$garden_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("Lỗi getAlerts: " . $e->getMessage());
            throw new Exception("Lỗi lấy cảnh báo: " . $e->getMessage());
        }
    }

    public function getArduinoIp($mcu_id) {
        try {
            $stmt = $this->db->prepare("SELECT ip_address FROM microcontrollers WHERE mcu_id = :mcu_id");
            $stmt->execute([':mcu_id' => trim($mcu_id)]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['ip_address'] ?? null;
        } catch (PDOException $e) {
            error_log("Lỗi getArduinoIp: " . $e->getMessage());
            throw new Exception("Lỗi lấy IP vi điều khiển: " . $e->getMessage());
        }
    }

    public function getMicrocontrollers($garden_id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM microcontrollers WHERE garden_id = :garden_id");
            $stmt->execute([':garden_id' => (int)$garden_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("Lỗi getMicrocontrollers: " . $e->getMessage());
            throw new Exception("Lỗi lấy vi điều khiển: " . $e->getMessage());
        }
    }

    public function getGardenNumber($garden_id) {
        try {
            // Kiểm tra xem garden_id đã được gán chưa
            $stmt = $this->db->prepare("SELECT garden_number FROM garden_assignments WHERE garden_id = :garden_id");
            $stmt->execute([':garden_id' => (int)$garden_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                return ['success' => true, 'garden_number' => (int)$result['garden_number']];
            }

            // Đếm tổng số bản ghi trong garden_assignments
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM garden_assignments");
            $stmt->execute();
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Gán garden_number: 1 nếu số bản ghi chẵn, 2 nếu lẻ
            $garden_number = ($count % 2 === 0) ? 1 : 2;

            // Lưu gán vườn mới
            $stmt = $this->db->prepare("
                INSERT INTO garden_assignments (garden_id, garden_number, assigned_at)
                VALUES (:garden_id, :garden_number, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE garden_number = :garden_number, assigned_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                ':garden_id' => (int)$garden_id,
                ':garden_number' => $garden_number
            ]);

            return ['success' => true, 'garden_number' => $garden_number];
        } catch (PDOException $e) {
            error_log("Lỗi getGardenNumber: " . $e->getMessage());
            return ['success' => false, 'message' => "Lỗi lấy hoặc gán số vườn: " . $e->getMessage()];
        }
    }
    public function saveGardenAssignment($garden_id, $garden_number) {
        try {
            if (!in_array($garden_number, [1, 2])) {
                throw new Exception("Số vườn không hợp lệ: $garden_number");
            }

            // Kiểm tra xem garden_number đã được gán cho vườn khác chưa
            $stmt = $this->db->prepare("SELECT garden_id FROM garden_assignments WHERE garden_number = :garden_number AND garden_id != :garden_id");
            $stmt->execute([':garden_number' => (int)$garden_number, ':garden_id' => (int)$garden_id]);
            if ($stmt->fetchColumn()) {
                // Cập nhật vườn khác thành số còn lại
                $new_garden_number = ($garden_number == 1) ? 2 : 1;
                $stmt = $this->db->prepare("
                    UPDATE garden_assignments 
                    SET garden_number = :new_garden_number, assigned_at = CURRENT_TIMESTAMP
                    WHERE garden_number = :garden_number AND garden_id != :garden_id
                ");
                $stmt->execute([
                    ':new_garden_number' => $new_garden_number,
                    ':garden_number' => (int)$garden_number,
                    ':garden_id' => (int)$garden_id
                ]);
            }

            // Lưu hoặc cập nhật gán vườn
            $stmt = $this->db->prepare("
                INSERT INTO garden_assignments (garden_id, garden_number, assigned_at)
                VALUES (:garden_id, :garden_number, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE garden_number = :garden_number, assigned_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                ':garden_id' => (int)$garden_id,
                ':garden_number' => (int)$garden_number
            ]);
            return true;
        } catch (PDOException $e) {
            error_log("Lỗi saveGardenAssignment: " . $e->getMessage());
            throw new Exception("Lỗi lưu gán vườn: " . $e->getMessage());
        }
    }

    public function saveSchedule($garden_id, $device_name, $action, $time, $days, $mcu_id) {
        try {
            $valid_actions = ['on', 'off'];
            $garden_number = $this->getGardenNumber($garden_id);
            $valid_devices = $garden_number == 1 ? ['den1', 'quat1', 'van_tren'] : ['den2', 'quat2', 'van_duoi'];

            if (!in_array($device_name, $valid_devices)) {
                throw new Exception("Thiết bị không hợp lệ: $device_name cho vườn $garden_number");
            }
            if (!in_array($action, $valid_actions)) {
                throw new Exception("Hành động không hợp lệ: $action");
            }

            $stmt = $this->db->prepare("
                INSERT INTO schedules (garden_id, device_name, action, time, days, mcu_id, created_at)
                VALUES (:garden_id, :device_name, :action, :time, :days, :mcu_id, NOW())
            ");
            return $stmt->execute([
                ':garden_id' => (int)$garden_id,
                ':device_name' => trim($device_name),
                ':action' => trim($action),
                ':time' => trim($time),
                ':days' => trim($days),
                ':mcu_id' => trim($mcu_id)
            ]);
        } catch (PDOException $e) {
            error_log("Lỗi saveSchedule: " . $e->getMessage());
            throw new Exception("Lỗi lưu lịch trình: " . $e->getMessage());
        }
    }

    public function updateAutoMode($garden_id, $mcu_id, $auto_mode) {
        try {
            $stmt = $this->db->prepare("
                UPDATE microcontrollers
                SET auto_mode = :auto_mode, updated_at = NOW()
                WHERE garden_id = :garden_id AND mcu_id = :mcu_id
            ");
            return $stmt->execute([
                ':auto_mode' => (int)$auto_mode,
                ':garden_id' => (int)$garden_id,
                ':mcu_id' => trim($mcu_id)
            ]);
        } catch (PDOException $e) {
            error_log("Lỗi updateAutoMode: " . $e->getMessage());
            throw new Exception("Lỗi cập nhật chế độ tự động: " . $e->getMessage());
        }
    }

    public function updateRelay($garden_id, $device_name, $status, $mcu_id) {
        try {
            $garden_number = $this->getGardenNumber($garden_id);
            $valid_devices = $garden_number == 1 ? ['den1', 'quat1', 'van_tren'] : ['den2', 'quat2', 'van_duoi'];
            if (!in_array($device_name, $valid_devices)) {
                throw new Exception("Thiết bị không hợp lệ: $device_name cho vườn $garden_number");
            }

            $stmt_check = $this->db->prepare("SELECT COUNT(*) FROM microcontrollers WHERE mcu_id = :mcu_id");
            $stmt_check->execute([':mcu_id' => trim($mcu_id)]);
            if ($stmt_check->fetchColumn() == 0) {
                throw new Exception("mcu_id $mcu_id không tồn tại");
            }

            $stmt_check = $this->db->prepare("SELECT COUNT(*) FROM gardens WHERE id = :garden_id");
            $stmt_check->execute([':garden_id' => (int)$garden_id]);
            if ($stmt_check->fetchColumn() == 0) {
                throw new Exception("garden_id $garden_id không tồn tại");
            }

            $stmt = $this->db->prepare("
                INSERT INTO device_status (garden_id, device_name, status, mcu_id, last_updated)
                VALUES (:garden_id, :device_name, :status, :mcu_id, NOW())
                ON DUPLICATE KEY UPDATE status = :status, last_updated = NOW()
            ");
            $stmt->execute([
                ':garden_id' => (int)$garden_id,
                ':device_name' => trim($device_name),
                ':status' => (int)$status,
                ':mcu_id' => trim($mcu_id)
            ]);

            return true;
        } catch (PDOException $e) {
            error_log("Lỗi updateRelay: " . $e->getMessage());
            throw new Exception("Lỗi cập nhật relay: " . $e->getMessage());
        }
    }

    public function initializeMcuAndDevices($mcu_id) {
        try {
            $stmt = $this->db->prepare("
                INSERT IGNORE INTO microcontrollers (mcu_id, ip_address, garden_id, created_at)
                VALUES (:mcu_id, NULL, NULL, NOW())
            ");
            $stmt->execute([':mcu_id' => trim($mcu_id)]);
            return true;
        } catch (PDOException $e) {
            error_log("Lỗi initializeMcuAndDevices: " . $e->getMessage());
            throw new Exception("Lỗi khởi tạo MCU và thiết bị: " . $e->getMessage());
        }
    }

    public function saveSensorData($data) {
        try {
            if (!isset($data['garden_id']) || !isset($data['mcu_id']) || !isset($data['soil_moisture']) || !isset($data['temperature']) || !isset($data['humidity']) || !isset($data['light']) || !isset($data['water_level_cm']) || !isset($data['is_raining'])) {
                throw new Exception("Dữ liệu không hợp lệ hoặc thiếu trường bắt buộc");
            }

            $stmt = $this->db->prepare("
                INSERT INTO sensor_readings (garden_id, sensor_id, soil_moisture, temperature, humidity, light, water_level_cm, is_raining, created_at, mcu_id)
                VALUES (:garden_id, :sensor_id, :soil_moisture, :temperature, :humidity, :light, :water_level_cm, :is_raining, NOW(), :mcu_id)
            ");
            return $stmt->execute([
                ':garden_id' => (int)$data['garden_id'],
                ':sensor_id' => 'sensor_' . trim($data['mcu_id']),
                ':soil_moisture' => (float)$data['soil_moisture'],
                ':temperature' => (float)$data['temperature'],
                ':humidity' => (float)$data['humidity'],
                ':light' => (float)$data['light'],
                ':water_level_cm' => (float)$data['water_level_cm'],
                ':is_raining' => (int)$data['is_raining'],
                ':mcu_id' => trim($data['mcu_id'])
            ]);
        } catch (PDOException $e) {
            error_log("Lỗi saveSensorData: " . $e->getMessage());
            throw new Exception("Lỗi lưu dữ liệu cảm biến: " . $e->getMessage());
        }
    }

    public function getStatus($garden_id, $mcu_id) {
        try {
            $stmt = $this->db->prepare("SELECT device_name, status FROM device_status WHERE garden_id = :garden_id AND mcu_id = :mcu_id");
            $stmt->execute([':garden_id' => (int)$garden_id, ':mcu_id' => trim($mcu_id)]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("Lỗi getStatus: " . $e->getMessage());
            throw new Exception("Lỗi lấy trạng thái: " . $e->getMessage());
        }
    }

    public function storeAlerts($data) {
        try {
            if (!isset($data['garden_id']) || !isset($data['mcu_id'])) {
                throw new Exception("Dữ liệu không hợp lệ hoặc thiếu trường bắt buộc");
            }

            $stmt = $this->db->prepare("
                INSERT INTO alerts (garden_id, mcu_id, water_level_alert, soil_moisture_alert, temperature_alert, light_alert, salinity_alert, message, severity, created_at)
                VALUES (:garden_id, :mcu_id, :water_level_alert, :soil_moisture_alert, :temperature_alert, :light_alert, :salinity_alert, :message, :severity, NOW())
            ");
            return $stmt->execute([
                ':garden_id' => (int)$data['garden_id'],
                ':mcu_id' => trim($data['mcu_id']),
                ':water_level_alert' => $data['water_level_alert'] ?? null,
                ':soil_moisture_alert' => $data['soil_moisture_alert'] ?? null,
                ':temperature_alert' => $data['temperature_alert'] ?? null,
                ':light_alert' => $data['light_alert'] ?? null,
                ':salinity_alert' => $data['salinity_alert'] ?? null,
                ':message' => $data['message'] ?? null,
                ':severity' => $data['severity'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Lỗi storeAlerts: " . $e->getMessage());
            throw new Exception("Lỗi lưu cảnh báo: " . $e->getMessage());
        }
    }

    public function getControlCommands($mcu_id, $garden_id = null) {
        try {
            $query = "SELECT device_name, status FROM device_status WHERE mcu_id = :mcu_id";
            if ($garden_id !== null) {
                $query .= " AND garden_id = :garden_id";
            }
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':mcu_id', $mcu_id);
            if ($garden_id !== null) {
                $stmt->bindParam(':garden_id', $garden_id);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lỗi getControlCommands: " . $e->getMessage());
            throw new Exception("Lỗi lấy lệnh điều khiển: " . $e->getMessage());
        }
    }

    public function getInitialRelayStates($mcu_id, $garden_id) {
        try {
            $stmt = $this->db->prepare("SELECT device_name, status FROM device_status WHERE mcu_id = :mcu_id AND garden_id = :garden_id");
            $stmt->execute([':mcu_id' => trim($mcu_id), ':garden_id' => (int)$garden_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("Lỗi getInitialRelayStates: " . $e->getMessage());
            throw new Exception("Lỗi lấy trạng thái relay ban đầu: " . $e->getMessage());
        }
    }
}
?>