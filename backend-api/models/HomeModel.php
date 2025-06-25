<?php
require_once __DIR__ . '/../config/database.php';

class HomeModel {
    private $conn;

    public function __construct() {
        global $conn;
        if (!$conn instanceof PDO) {
            error_log("HomeModel: Database connection is not a PDO instance");
            throw new Exception("Không thể kết nối đến cơ sở dữ liệu.");
        }
        $this->conn = $conn;
    }

    public function userExists($userId) {
        try {
            $stmt = $this->conn->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $exists = $stmt->fetchColumn() !== false;
            error_log("userExists: userId=$userId, exists=" . ($exists ? 'true' : 'false'));
            return $exists;
        } catch (PDOException $e) {
            error_log("userExists: Error: " . $e->getMessage());
            throw new Exception("Lỗi khi kiểm tra người dùng: " . $e->getMessage());
        }
    }

    public function getAll($userId, $isAdmin) {
        try {
            error_log("getAll: Fetching gardens for userId=$userId, isAdmin=$isAdmin");
            $sql = "SELECT g.id, g.garden_names, g.location, g.longitude, g.latitude, g.note, g.area, 
                           g.created_at, g.status, g.user_id, g.img, u.full_name AS owner_name
                    FROM gardens g
                    LEFT JOIN users u ON g.user_id = u.id
                    WHERE g.status = 'Hoạt động'";
            $params = [];
            if (!$isAdmin) {
                $sql .= " AND g.user_id = :user_id";
                $params[':user_id'] = $userId;
            }
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $gardens = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($gardens as &$garden) {
                $garden['img_url'] = !empty($garden['img']) 
                    ? 'data:image/jpeg;base64,' . base64_encode($garden['img'])
                    : '';
                $garden['img_id'] = $garden['id'];
                unset($garden['img']);
            }
            error_log("getAll: Found " . count($gardens) . " gardens");
            return $gardens;
        } catch (PDOException $e) {
            error_log("getAll: Error: " . $e->getMessage());
            throw new Exception("Lỗi khi lấy danh sách vườn: " . $e->getMessage());
        }
    }

    public function getGardenById($gardenId) {
        try {
            $sql = "SELECT id, user_id, garden_names, location, area, latitude, longitude, note, status 
                    FROM gardens WHERE id = :id AND status = 'Hoạt động'";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $gardenId]);
            $garden = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("getGardenById: gardenId=$gardenId, found=" . ($garden ? 'true' : 'false'));
            if (!$garden) {
                throw new Exception("Không tìm thấy vườn với ID: $gardenId");
            }
            return $garden;
        } catch (PDOException $e) {
            error_log("getGardenById: Error: " . $e->getMessage());
            throw new Exception("Lỗi khi lấy thông tin vườn: " . $e->getMessage());
        }
    }

    public function getGardenImage($gardenId) {
        try {
            $sql = "SELECT img FROM gardens WHERE id = :id AND status = 'Hoạt động'";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $gardenId]);
            $image = $stmt->fetchColumn();
            error_log("getGardenImage: gardenId=$gardenId, found=" . ($image !== false ? 'true' : 'false'));
            if ($image === false) {
                throw new Exception("Không tìm thấy ảnh cho vườn ID: $gardenId");
            }
            return $image;
        } catch (PDOException $e) {
            error_log("getGardenImage: Error: " . $e->getMessage());
            throw new Exception("Lỗi khi lấy ảnh vườn: " . $e->getMessage());
        }
    }

    public function getAllUsers() {
        try {
            $sql = "SELECT id, username, email, full_name, administrator_rights 
                    FROM users";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("getAllUsers: Found " . count($users) . " users");
            return $users;
        } catch (PDOException $e) {
            error_log("getAllUsers: Error: " . $e->getMessage());
            throw new Exception("Lỗi khi lấy danh sách người dùng: " . $e->getMessage());
        }
    }

    public function saveGarden($data) {
        try {
            $this->conn->beginTransaction();
            $sql = "INSERT INTO gardens (user_id, garden_names, location, area, note, latitude, longitude, img, status, created_at)
                    VALUES (:user_id, :garden_names, :location, :area, :note, :latitude, :longitude, :img, 'Hoạt động', NOW())";
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([
                ':user_id' => $data['user_id'],
                ':garden_names' => $data['name'],
                ':location' => $data['location'] ?? null,
                ':area' => $data['area'] ?? null,
                ':note' => $data['note'] ?? 'Không có ghi chú',
                ':latitude' => $data['latitude'],
                ':longitude' => $data['longitude'],
                ':img' => $data['img'] ?? null
            ]);
            error_log("saveGarden: user_id={$data['user_id']}, garden_names={$data['name']}, success=" . ($result ? 'true' : 'false'));
            $this->conn->commit();
            return $result;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("saveGarden: Error: " . $e->getMessage());
            throw new Exception("Lỗi khi lưu vườn: " . $e->getMessage());
        }
    }

    public function getSensorData($gardenId, $userId, $isAdmin) {
        try {
            $gardenIds = $gardenId ? [$gardenId] : array_column($this->getAll($userId, $isAdmin), 'id');
            if (empty($gardenIds)) {
                error_log("getSensorData: No gardens found for userId=$userId, gardenId=" . ($gardenId ?? 'null'));
                return [];
            }

            $sql = "SELECT garden_id, soil_moisture, temperature, humidity
                    FROM sensor_readings
                    WHERE garden_id IN (" . implode(',', array_fill(0, count($gardenIds), '?')) . ")
                    AND created_at = (
                        SELECT MAX(created_at)
                        FROM sensor_readings
                        WHERE garden_id = sensor_readings.garden_id
                    )";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($gardenIds);
            $sensorData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sql_relay = "SELECT garden_id, status
                          FROM relay_controls
                          WHERE garden_id IN (" . implode(',', array_fill(0, count($gardenIds), '?')) . ")
                          AND updated_at = (
                              SELECT MAX(updated_at)
                              FROM relay_controls
                              WHERE garden_id = relay_controls.garden_id
                          )";
            $stmt_relay = $this->conn->prepare($sql_relay);
            $stmt_relay->execute($gardenIds);
            $relayData = $stmt_relay->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($gardenIds as $gid) {
                $sensor = array_filter($sensorData, fn($row) => $row['garden_id'] == $gid);
                $sensor = reset($sensor) ?: [];
                $relay = array_filter($relayData, fn($row) => $row['garden_id'] == $gid);
                $relay = reset($relay) ?: [];

                $result[$gid] = [
                    'temperature' => $sensor['temperature'] ?? 0,
                    'soil_moisture' => $sensor['soil_moisture'] ?? 0,
                    'humidity' => $sensor['humidity'] ?? 0,
                    'irrigation' => $relay['status'] ?? 0
                ];
            }

            error_log("getSensorData: gardenId=" . ($gardenId ?? 'null') . ", userId=$userId, found=" . count($result));
            return $result;
        } catch (PDOException $e) {
            error_log("getSensorData: Error: " . $e->getMessage());
            throw new Exception("Lỗi khi lấy dữ liệu cảm biến: " . $e->getMessage());
        }
    }

    public function getChartData($gardenId, $userId, $isAdmin) {
        try {
            $gardenIds = $gardenId ? [$gardenId] : array_column($this->getAll($userId, $isAdmin), 'id');
            if (empty($gardenIds)) {
                error_log("getChartData: No gardens found for userId=$userId, gardenId=" . ($gardenId ?? 'null'));
                return [];
            }

            $sql = "SELECT garden_id, soil_moisture, temperature, humidity, created_at
                    FROM sensor_readings
                    WHERE garden_id IN (" . implode(',', array_fill(0, count($gardenIds), '?')) . ")
                    AND created_at >= NOW() - INTERVAL 24 HOUR
                    ORDER BY garden_id, created_at ASC";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($gardenIds);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($gardenIds as $gid) {
                $data = array_filter($rows, fn($row) => $row['garden_id'] == $gid);
                $labels = [];
                $temperature = [];
                $soil_moisture = [];
                $humidity = [];

                foreach ($data as $row) {
                    $labels[] = date("H:i", strtotime($row['created_at']));
                    $temperature[] = $row['temperature'] ?? 0;
                    $soil_moisture[] = $row['soil_moisture'] ?? 0;
                    $humidity[] = $row['humidity'] ?? 0;
                }

                $result[$gid] = [
                    'labels' => $labels,
                    'temperature' => $temperature,
                    'soil_moisture' => $soil_moisture,
                    'humidity' => $humidity
                ];
            }

            error_log("getChartData: gardenId=" . ($gardenId ?? 'null') . ", userId=$userId, found=" . count($result));
            return $result;
        } catch (PDOException $e) {
            error_log("getChartData: Error: " . $e->getMessage());
            throw new Exception("Lỗi khi lấy dữ liệu biểu đồ: " . $e->getMessage());
        }
    }

    public function getAlerts($gardenId, $userId, $isAdmin) {
        try {
            $gardenIds = $gardenId ? [$gardenId] : array_column($this->getAll($userId, $isAdmin), 'id');
            if (empty($gardenIds)) {
                error_log("getAlerts: No gardens found for userId=$userId, gardenId=" . ($gardenId ?? 'null'));
                return [];
            }

            $sql = "SELECT a.message, 
                           CASE 
                               WHEN a.message LIKE '%thiếu nước%' THEN 'danger'
                               WHEN a.message LIKE '%lỗi%' THEN 'warning'
                               ELSE 'info'
                           END AS severity,
                           a.timestamp,
                           m.garden_id
                    FROM messages a
                    JOIN microcontrollers m ON a.mcu_id = m.mcu_id
                    WHERE m.garden_id IN (" . implode(',', array_fill(0, count($gardenIds), '?')) . ")
                    AND a.timestamp >= NOW() - INTERVAL 24 HOUR
                    ORDER BY a.timestamp DESC";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($gardenIds);
            $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($gardenIds as $gid) {
                $gardenAlerts = array_filter($alerts, fn($row) => $row['garden_id'] == $gid);
                $result[$gid] = array_slice(array_values($gardenAlerts), 0, 5);
            }

            error_log("getAlerts: gardenId=" . ($gardenId ?? 'null') . ", userId=$userId, found=" . count($alerts));
            return $result;
        } catch (PDOException $e) {
            error_log("getAlerts: Error: " . $e->getMessage());
            throw new Exception("Lỗi khi lấy cảnh báo: " . $e->getMessage());
        }
    }

    public function toggleIrrigation($gardenId) {
        try {
            $this->conn->beginTransaction();
            $sql = "SELECT status FROM relay_controls 
                    WHERE garden_id = ? 
                    ORDER BY updated_at DESC 
                    LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$gardenId]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            $new_status = $current ? ($current['status'] ? 0 : 1) : 1;

            $sql_update = "INSERT INTO relay_controls (garden_id, device_name, status, updated_at)
                           VALUES (?, 'Irrigation', ?, NOW())";
            $stmt_update = $this->conn->prepare($sql_update);
            $result = $stmt_update->execute([$gardenId, $new_status]);
            error_log("toggleIrrigation: gardenId=$gardenId, new_status=$new_status, success=" . ($result ? 'true' : 'false'));
            $this->conn->commit();
            return $result;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("toggleIrrigation: Error: " . $e->getMessage());
            throw new Exception("Lỗi khi điều khiển tưới: " . $e->getMessage());
        }
    }
}
?>