<?php
require_once __DIR__ . '/../config/database.php';

class StatsModel {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAccessibleGardens($userId, $isAdmin) {
        try {
            $sql = "SELECT 
                        g.id as garden_id, 
                        g.garden_names as garden_name,
                        g.location,
                        g.status,
                        u.id as user_id,
                        u.full_name as user_full_name,
                        u.username
                    FROM gardens g
                    INNER JOIN users u ON g.user_id = u.id
                    WHERE g.status = 'Hoạt động'";
            
            if (!$isAdmin) {
                $sql .= " AND g.user_id = :user_id";
            }
            
            $stmt = $this->conn->prepare($sql);
            
            if (!$isAdmin) {
                $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            $gardens = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Fetched " . count($gardens) . " accessible gardens for user $userId");
            return $gardens;
        } catch (PDOException $e) {
            error_log("Error in getAccessibleGardens for user $userId: " . $e->getMessage());
            throw new Exception('Lỗi khi lấy danh sách vườn: ' . $e->getMessage(), 500);
        }
    }

    public function getGardenStats($gardenIds, $userId, $isAdmin, $startDate = null, $endDate = null) {
        try {
            $stats = [];
            
            // Lấy danh sách vườn mà user có quyền truy cập
            $accessibleGardens = $this->getAccessibleGardens($userId, $isAdmin);
            $accessibleGardenIds = array_column($accessibleGardens, 'garden_id');
            
            // Lọc các gardenIds được phép
            $allowedGardenIds = array_intersect($gardenIds, $accessibleGardenIds);
            
            foreach ($allowedGardenIds as $gardenId) {
                // Lấy thông tin vườn
                $gardenInfo = $this->getGardenInfo($gardenId);
                if (!$gardenInfo) {
                    continue;
                }
                
                // Lấy thống kê cảm biến
                $sensorStats = $this->getSensorStats($gardenId, $startDate, $endDate);
                
                // Lấy thống kê cây trồng
                $plantStats = $this->getPlantStats($gardenId);
                
                // Lấy cảnh báo
                $alerts = $this->getAlerts($gardenId, $startDate, $endDate);
                
                // Lấy thống kê theo thời gian
                $monthlyStats = $this->getMonthlyStats($gardenId, $startDate, $endDate);
                $plantMonthlyStats = $this->getPlantMonthlyStats($gardenId, $startDate, $endDate);
                
                $stats[] = [
                    'id' => $gardenId,
                    'name' => $gardenInfo['garden_names'] ?? 'Không tên',
                    'owner_name' => $gardenInfo['owner_name'] ?? 'Không xác định',
                    'avg_temperature' => $sensorStats['avg_temperature'] ?? 0,
                    'max_temperature' => $sensorStats['max_temperature'] ?? 0,
                    'min_temperature' => $sensorStats['min_temperature'] ?? 0,
                    'avg_humidity' => $sensorStats['avg_humidity'] ?? 0,
                    'max_humidity' => $sensorStats['max_humidity'] ?? 0,
                    'min_humidity' => $sensorStats['min_humidity'] ?? 0,
                    'avg_soil_moisture' => $sensorStats['avg_soil_moisture'] ?? 0,
                    'max_soil_moisture' => $sensorStats['max_soil_moisture'] ?? 0,
                    'min_soil_moisture' => $sensorStats['min_soil_moisture'] ?? 0,
                    'plant_count' => $plantStats['plant_count'] ?? 0,
                    'avg_health' => $plantStats['avg_health'] ?? 0,
                    'species_count' => $plantStats['species_count'] ?? 0,
                    'monthly_stats' => $monthlyStats,
                    'plant_monthly_stats' => $plantMonthlyStats,
                    'alerts' => $alerts
                ];
            }
            
            error_log("Fetched stats for " . count($stats) . " gardens for user $userId");
            return $stats;
        } catch (Exception $e) {
            error_log("Error in getGardenStats for user $userId: " . $e->getMessage());
            throw new Exception('Lỗi khi lấy thống kê vườn: ' . $e->getMessage(), 500);
        }
    }

    private function getSensorStats($gardenId, $startDate, $endDate) {
        try {
            $whereClause = "WHERE garden_id = :garden_id";
            $params = [':garden_id' => $gardenId];
            
            if ($startDate && $endDate) {
                $whereClause .= " AND created_at BETWEEN :start_date AND :end_date";
                $params[':start_date'] = $startDate;
                $params[':end_date'] = $endDate;
            }
            
            $sql = "SELECT 
                        AVG(temperature) as avg_temperature,
                        MAX(temperature) as max_temperature,
                        MIN(temperature) as min_temperature,
                        AVG(humidity) as avg_humidity,
                        MAX(humidity) as max_humidity,
                        MIN(humidity) as min_humidity,
                        AVG(soil_moisture) as avg_soil_moisture,
                        MAX(soil_moisture) as max_soil_moisture,
                        MIN(soil_moisture) as min_soil_moisture
                    FROM sensor_readings
                    $whereClause";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            error_log("Fetched sensor stats for garden ID $gardenId");
            return $result;
        } catch (PDOException $e) {
            error_log("Error in getSensorStats for garden ID $gardenId: " . $e->getMessage());
            return [];
        }
    }

    private function getPlantStats($gardenId) {
        try {
            $sql = "SELECT 
                        COUNT(*) as plant_count,
                        AVG(health_status) as avg_health,
                        COUNT(DISTINCT species) as species_count
                    FROM plants
                    WHERE garden_id = ?";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$gardenId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            error_log("Fetched plant stats for garden ID $gardenId");
            return $result;
        } catch (PDOException $e) {
            error_log("Error in getPlantStats for garden ID $gardenId: " . $e->getMessage());
            return [];
        }
    }

    private function getMonthlyStats($gardenId, $startDate, $endDate) {
        try {
            $whereClause = "WHERE garden_id = :garden_id";
            $params = [':garden_id' => $gardenId];
            
            if ($startDate && $endDate) {
                $whereClause .= " AND created_at BETWEEN :start_date AND :end_date";
                $params[':start_date'] = $startDate;
                $params[':end_date'] = $endDate;
            }
            
            $sql = "SELECT 
                        DATE_FORMAT(created_at, '%Y-%m') as month,
                        AVG(temperature) as avg_temperature,
                        AVG(humidity) as avg_humidity,
                        AVG(soil_moisture) as avg_soil_moisture
                    FROM sensor_readings
                    $whereClause
                    GROUP BY month
                    ORDER BY month";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Fetched monthly stats for garden ID $gardenId: " . count($result) . " records");
            return $result;
        } catch (PDOException $e) {
            error_log("Error in getMonthlyStats for garden ID $gardenId: " . $e->getMessage());
            return [];
        }
    }

    private function getPlantMonthlyStats($gardenId, $startDate, $endDate) {
        try {
            $whereClause = "WHERE garden_id = :garden_id";
            $params = [':garden_id' => $gardenId];
            
            if ($startDate && $endDate) {
                $whereClause .= " AND planting_date BETWEEN :start_date AND :end_date";
                $params[':start_date'] = $startDate;
                $params[':end_date'] = $endDate;
            }
            
            $sql = "SELECT 
                        DATE_FORMAT(planting_date, '%Y-%m') as month,
                        AVG(health_status) as avg_health,
                        COUNT(*) as plant_added,
                        SUM(CASE WHEN health_status < 50 THEN 1 ELSE 0 END) as unhealthy_count
                    FROM plants
                    $whereClause
                    GROUP BY month
                    ORDER BY month";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Fetched plant monthly stats for garden ID $gardenId: " . count($result) . " records");
            return $result;
        } catch (PDOException $e) {
            error_log("Error in getPlantMonthlyStats for garden ID $gardenId: " . $e->getMessage());
            return [];
        }
    }

    private function getGardenInfo($gardenId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT g.*, u.full_name as owner_name 
                FROM gardens g
                JOIN users u ON g.user_id = u.id
                WHERE g.id = ?
            ");
            $stmt->execute([$gardenId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("Fetched garden info for garden ID $gardenId: " . ($result ? 'Found' : 'Not found'));
            return $result;
        } catch (PDOException $e) {
            error_log("Error in getGardenInfo for garden ID $gardenId: " . $e->getMessage());
            return null;
        }
    }

    private function getAlerts($gardenId, $startDate, $endDate) {
        try {
            $whereClause = "WHERE garden_id = :garden_id";
            $params = [':garden_id' => $gardenId];
            
            if ($startDate && $endDate) {
                $whereClause .= " AND timestamp BETWEEN :start_date AND :end_date";
                $params[':start_date'] = $startDate;
                $params[':end_date'] = $endDate;
            }
            
            $sql = "SELECT message, timestamp, alert_type
                    FROM alerts
                    $whereClause
                    ORDER BY timestamp DESC
                    LIMIT 10";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Fetched alerts for garden ID $gardenId: " . count($result) . " alerts");
            return $result;
        } catch (PDOException $e) {
            error_log("Error in getAlerts for garden ID $gardenId: " . $e->getMessage());
            return [];
        }
    }
}
?>