<?php
require_once __DIR__ . '/../config/database.php';

class GardenModel {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getGardensByIds($ids) {
        if (empty($ids) || !is_array($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $query = "SELECT id, garden_names, user_id FROM gardens WHERE id IN ($placeholders)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute($ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllGardens($search = '', $userId, $isAdmin) {
    try {
        $sql = "
            SELECT gardens.id, gardens.garden_names, gardens.location, gardens.latitude, gardens.longitude, 
                   gardens.area, gardens.note, gardens.status, gardens.user_id, users.full_name AS owner_name
            FROM gardens
            JOIN users ON gardens.user_id = users.id
        ";
        $params = [];

        if (!$isAdmin) {
            $sql .= " WHERE gardens.user_id = :user_id";
            $params[':user_id'] = $userId;
        }

        if (!empty($search)) {
            if ($isAdmin) {
                $sql .= " WHERE (";
            } else {
                $sql .= " AND (";
            }

            if (is_numeric($search) && (int)$search == $search) {
                $sql .= "gardens.id = :search_id";
                $params[':search_id'] = (int)$search;
            } else {
                $sql .= "gardens.garden_names LIKE :search OR gardens.location LIKE :search OR users.full_name LIKE :search";
                $params[':search'] = '%' . $search . '%';
            }
            $sql .= ")";
        }

        $sql .= " ORDER BY gardens.id ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Thêm img_url để lấy ảnh sau
        foreach ($results as &$garden) {
            $garden['img_url'] = "http://localhost/SmartGarden/backend-api/routes/garden.php"; 
            $garden['img_id'] = $garden['id']; // Lưu ID để lấy ảnh
        }
        return $results;

        } catch (PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }   

    public function getGardenImage($garden_id) {
        try {
            $sql = "SELECT img FROM gardens WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $garden_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result && !empty($result['img']) ? $result['img'] : null;
        } catch (PDOException $e) {
            error_log("Lỗi trong getGardenImage: " . $e->getMessage());
            return null;
        }
    }
    
    public function getStatusOptions() {
        $stmt = $this->conn->prepare("SHOW COLUMNS FROM gardens LIKE 'status'");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if ($row && isset($row['Type'])) {
            preg_match("/^enum\((.*)\)$/", $row['Type'], $matches);
            $enum = explode(",", $matches[1]);
            $enum = array_map(function ($value) {
                return trim($value, " '");
            }, $enum);
    
            return array_map(function ($value) {
                return ['id' => $value, 'name' => $value];
            }, $enum);
        }
    
        return [];
    }

    public function getAllUsers() {
        try {
            $stmt = $this->conn->prepare("SELECT id, full_name FROM users WHERE administrator_rights = 0");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function getGardenById($id) {
        $stmt = $this->conn->prepare("
            SELECT gardens.*, users.full_name AS owner_name
            FROM gardens
            JOIN users ON gardens.user_id = users.id
            WHERE gardens.id = ?
        ");
        $stmt->execute([$id]);
        $garden = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($garden && $garden['img']) {
            $garden['img'] = base64_encode($garden['img']);
        }
        return $garden;
    }

    public function saveGarden($data, $files) {
        $imageData = $this->uploadImage($files['img']);
        $stmt = $this->conn->prepare("INSERT INTO gardens (garden_names, location, latitude, longitude, area, note, status, user_id, img) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([ 
            $data['garden_names'], $data['location'], $data['latitude'], $data['longitude'],
            $data['area'], $data['note'], $data['status'], $data['user_id'], $imageData 
        ]);
    }

    public function updateGarden($id, $data, $files) {
        $imageData = null;
        if (isset($files['img']) && $files['img']['tmp_name']) {
            $imageData = $this->uploadImage($files['img']);
        }

        $sql = "UPDATE gardens SET garden_names = ?, location = ?, latitude = ?, longitude = ?, area = ?, note = ?, status = ?, user_id = ?";
        $params = [
            $data['garden_names'], $data['location'], $data['latitude'], $data['longitude'],
            $data['area'], $data['note'], $data['status'], $data['user_id']
        ];

        if ($imageData !== null) {
            $sql .= ", img = ?";
            $params[] = $imageData;
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteGarden($id) {
        $stmt = $this->conn->prepare("DELETE FROM gardens WHERE id = ?");
        return $stmt->execute([$id]);
    }

    private function uploadImage($file) {
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            error_log("Lỗi upload file: " . $file['error']);
            return null;
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = mime_content_type($file['tmp_name']);
        if (!in_array($fileType, $allowedTypes)) {
            error_log("Loại file không hợp lệ: " . $fileType);
            return null;
        }

        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            error_log("File quá lớn: " . $file['size'] . " bytes");
            return null;
        }

        $imageData = file_get_contents($file['tmp_name']);
        return $imageData;
    }
}
?>