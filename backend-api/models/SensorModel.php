<?php
require_once __DIR__ . '/../config/database.php';

class SensorModel {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function insertSensorData($data, $garden_id) {
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
            ':rain' => $data['is_raining'] ?? 0
        ]);
    }

    public function getLatestSensorData($garden_id) {
        $query = "SELECT sensor_id, garden_id, soil_moisture, temperature, humidity, light, water_level_cm, is_raining, created_at 
                  FROM sensor_readings";
        if ($garden_id) {
            $query .= " WHERE garden_id = :garden_id";
        }
        $query .= " ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        if ($garden_id) {
            $stmt->bindParam(':garden_id', $garden_id, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getDeviceStatus($garden_id) {
        $query = "SELECT device_name, status, last_updated FROM device_status";
        if ($garden_id) {
            $query .= " WHERE garden_id = :garden_id";
        }
        $stmt = $this->conn->prepare($query);
        if ($garden_id) {
            $stmt->bindParam(':garden_id', $garden_id, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateDeviceStatus($device, $status, $garden_id) {
        $stmt = $this->conn->prepare("
            INSERT INTO device_status (device_name, status, garden_id, last_updated) 
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE status = ?, last_updated = NOW()
        ");
        return $stmt->execute([$device, $status, $garden_id, $status]);
    }

    public function saveSchedule($data, $garden_id) {
        $stmt = $this->conn->prepare("
            INSERT INTO schedules (device_name, action, time, days, garden_id, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([
            $data['device'],
            $data['action'],
            $data['time'],
            implode(',', $data['days']),
            $garden_id
        ]);
    }

    public function getSchedules($garden_id) {
        $query = "SELECT id, device_name, action, time, days, created_at FROM schedules";
        if ($garden_id) {
            $query .= " WHERE garden_id = :garden_id";
        }
        $query .= " ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        if ($garden_id) {
            $stmt->bindParam(':garden_id', $garden_id, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateRelayControl($garden_id, $device_name, $status) {
        $stmt = $this->conn->prepare("
            INSERT INTO relay_controls (garden_id, device_name, status, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE status = ?, updated_at = NOW()
        ");
        return $stmt->execute([$garden_id, $device_name, $status, $status]);
    }

    public function getAlerts($garden_id) {
        $query = "SELECT id, mcu_id, message, timestamp FROM alerts";
        if ($garden_id) {
            $query .= " WHERE garden_id = :garden_id";
        }
        $query .= " ORDER BY timestamp DESC LIMIT 10";
        $stmt = $this->conn->prepare($query);
        if ($garden_id) {
            $stmt->bindParam(':garden_id', $garden_id, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertAlert($mcu_id, $message, $garden_id) {
        $stmt = $this->conn->prepare("
            INSERT INTO alerts (mcu_id, message, garden_id, timestamp)
            VALUES (?, ?, ?, NOW())
        ");
        return $stmt->execute([$mcu_id, $message, $garden_id]);
    }

    public function getMicrocontrollerStatus($garden_id) {
        $query = "SELECT mcu_id, name, garden_id, ip_address, status, last_seen FROM microcontrollers";
        if ($garden_id) {
            $query .= " WHERE garden_id = :garden_id";
        }
        $stmt = $this->conn->prepare($query);
        if ($garden_id) {
            $stmt->bindParam(':garden_id', $garden_id, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>