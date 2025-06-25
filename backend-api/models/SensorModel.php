<?php
require_once __DIR__ . '/../config/database.php';

class SensorModel {
    public $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function insertSensorData($data, $garden_id) {
        if (!$garden_id) {
            throw new Exception('garden_id là bắt buộc');
        }
        $stmt = $this->conn->prepare("
            INSERT INTO sensor_readings 
            (sensor_id, garden_id, soil_moisture, temperature, humidity, light, water_level_cm, is_raining, created_at) 
            VALUES (:sensor_id, :garden_id, :soil, :temp, :humi, :light, :water, :rain, NOW())
        ");
        return $stmt->execute([
            ':sensor_id' => $data['sensor_id'],
            ':garden_id' => $garden_id,
            ':soil' => $data['soil_moisture'] ?? null,
            ':temp' => $data['temperature'] ?? null,
            ':humi' => $data['humidity'] ?? null,
            ':light' => $data['light'] ?? null,
            ':water' => $data['water_level_cm'] ?? null,
            ':rain' => isset($data['is_raining']) ? (int)$data['is_raining'] : 0
        ]);
    }

    public function getLatestSensorData($garden_id) {
        if (!$garden_id) {
            throw new Exception('garden_id là bắt buộc');
        }
        $stmt = $this->conn->prepare("
            SELECT sensor_id, garden_id, soil_moisture, temperature, humidity, light, water_level_cm, is_raining, created_at 
            FROM sensor_readings 
            WHERE garden_id = :garden_id 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([':garden_id' => $garden_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getDeviceStatus($garden_id) {
        if (!$garden_id) {
            throw new Exception('garden_id là bắt buộc');
        }
        $stmt = $this->conn->prepare("
            SELECT device_name, status, last_updated 
            FROM device_status 
            WHERE garden_id = :garden_id
        ");
        $stmt->execute([':garden_id' => $garden_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateDeviceStatus($device, $status, $garden_id) {
        if (!$garden_id) {
            throw new Exception('garden_id là bắt buộc');
        }
        $stmt = $this->conn->prepare("
            INSERT INTO device_status (device_name, status, garden_id, last_updated) 
            VALUES (:device_name, :status, :garden_id, NOW())
            ON DUPLICATE KEY UPDATE status = :status_update, last_updated = NOW()
        ");
        return $stmt->execute([
            ':device_name' => $device,
            ':status' => $status,
            ':garden_id' => $garden_id,
            ':status_update' => $status
        ]);
    }

    public function saveSchedule($data, $garden_id) {
        if (!$garden_id) {
            throw new Exception('garden_id là bắt buộc');
        }
        $stmt = $this->conn->prepare("
            INSERT INTO schedules (device_name, action, time, days, garden_id, created_at) 
            VALUES (:device_name, :action, :time, :days, :garden_id, NOW())
        ");
        return $stmt->execute([
            ':device_name' => $data['device'],
            ':action' => $data['action'],
            ':time' => $data['time'],
            ':days' => implode(',', $data['days']),
            ':garden_id' => $garden_id
        ]);
    }

    public function getSchedules($garden_id) {
        if (!$garden_id) {
            throw new Exception('garden_id là bắt buộc');
        }
        $stmt = $this->conn->prepare("
            SELECT id, device_name, action, time, days, created_at 
            FROM schedules 
            WHERE garden_id = :garden_id 
            ORDER BY created_at DESC
        ");
        $stmt->execute([':garden_id' => $garden_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateRelayControl($garden_id, $device_name, $status) {
        if (!$garden_id) {
            throw new Exception('garden_id là bắt buộc');
        }
        $stmt = $this->conn->prepare("
            INSERT INTO relay_controls (garden_id, device_name, status, updated_at)
            VALUES (:garden_id, :device_name, :status, NOW())
            ON DUPLICATE KEY UPDATE status = :status_update, updated_at = NOW()
        ");
        return $stmt->execute([
            ':garden_id' => $garden_id,
            ':device_name' => $device_name,
            ':status' => $status,
            ':status_update' => $status
        ]);
    }

    public function getAlerts($garden_id) {
        if (!$garden_id) {
            throw new Exception('garden_id là bắt buộc');
        }
        $stmt = $this->conn->prepare("
            SELECT id, sensor_id, message, timestamp 
            FROM alerts 
            WHERE garden_id = :garden_id 
            ORDER BY timestamp DESC 
            LIMIT 10
        ");
        $stmt->execute([':garden_id' => $garden_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertAlert($sensor_id, $message, $garden_id) {
        if (!$garden_id) {
            throw new Exception('garden_id là bắt buộc');
        }
        $stmt = $this->conn->prepare("SELECT user_id FROM gardens WHERE id = :garden_id");
        $stmt->execute([':garden_id' => $garden_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            error_log("insertAlert: Không tìm thấy user_id cho garden_id: $garden_id");
            return false;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO alerts (user_id, sensor_id, garden_id, message, timestamp)
            VALUES (:user_id, :sensor_id, :garden_id, :message, NOW())
        ");
        return $stmt->execute([
            ':user_id' => $user['user_id'],
            ':sensor_id' => $sensor_id,
            ':garden_id' => $garden_id,
            ':message' => $message
        ]);
    }

    public function getMicrocontrollerStatus($garden_id) {
        if (!$garden_id) {
            throw new Exception('garden_id là bắt buộc');
        }
        $stmt = $this->conn->prepare("
            SELECT mcu_id, name, garden_id, ip_address, status, last_seen 
            FROM microcontrollers 
            WHERE garden_id = :garden_id
        ");
        $stmt->execute([':garden_id' => $garden_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>