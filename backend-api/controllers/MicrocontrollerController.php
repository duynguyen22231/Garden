<?php
require_once '../models/MicrocontrollerModel.php';
require_once '../models/GardenModel.php';

class MicrocontrollerController {
    private $microcontrollerModel;
    private $gardenModel;
    private $db;

    public function __construct($db) {
        try {
            $this->db = $db;
            $this->microcontrollerModel = new MicrocontrollerModel();
            $this->gardenModel = new GardenModel($db);
        } catch (Exception $e) {
            error_log("Error initializing MicrocontrollerController: " . $e->getMessage());
            $this->sendResponse([
                'success' => false,
                'message' => 'Failed to initialize model: ' . $e->getMessage()
            ], 500);
            exit;
        }
    }

    private function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function getMicrocontrollerStatus($isAdmin, $userId) {
        try {
            $microcontrollers = $this->microcontrollerModel->getAllMicrocontrollers();
            error_log("Microcontrollers before filtering: " . json_encode($microcontrollers));

            if (!$isAdmin) {
                $gardenIds = array_map(function($mc) { return $mc['garden_id']; }, $microcontrollers);
                $gardens = $this->gardenModel->getGardensByIds($gardenIds);
                error_log("Gardens fetched: " . json_encode($gardens));
                $userGardens = array_filter($gardens, function($garden) use ($userId) {
                    return $garden['user_id'] == $userId;
                });
                error_log("User gardens: " . json_encode($userGardens));
                $microcontrollers = array_filter($microcontrollers, function($mc) use ($userGardens) {
                    return in_array($mc['garden_id'], array_column($userGardens, 'id'));
                });
            }

            return [
                'success' => true,
                'data' => array_values($microcontrollers)
            ];
        } catch (Exception $e) {
            error_log("Error in getMicrocontrollerStatus: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error retrieving microcontroller status: ' . $e->getMessage()
            ];
        }
    }

    public function addMicrocontroller($name, $garden_id, $ip_address, $status, $isAdmin, $userId, $mcu_id) {
        try {
            if (empty($name) || empty($garden_id) || empty($ip_address) || empty($status) || empty($mcu_id)) {
                throw new Exception("All fields are required, including mcu_id");
            }
            if (!is_numeric($garden_id) || $garden_id <= 0) {
                throw new Exception("Invalid garden ID");
            }
            if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
                throw new Exception("Invalid IP address");
            }
            $validStatuses = ['online', 'offline'];
            if (!in_array($status, $validStatuses)) {
                throw new Exception("Invalid status");
            }

            $garden = $this->gardenModel->getGardenById($garden_id);
            if (!$garden) {
                throw new Exception("Garden does not exist");
            }
            if (!$isAdmin && $garden['user_id'] != $userId) {
                throw new Exception("You do not have permission to add a microcontroller to this garden");
            }

            $result = $this->microcontrollerModel->addMicrocontroller($name, $garden_id, $ip_address, $status, $mcu_id);
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Microcontroller added successfully'
                ];
            } else {
                throw new Exception("Failed to add microcontroller to database");
            }
        } catch (Exception $e) {
            error_log("Error in addMicrocontroller: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    public function getMcuId($garden_id) {
        try {
            if (!is_numeric($garden_id) || $garden_id <= 0) {
                throw new Exception("Invalid garden ID");
            }
            $data = $this->microcontrollerModel->getMicrocontrollerByGarden($garden_id);
            if ($data) {
                return [
                    'success' => true,
                    'data' => [
                        'mcu_id' => $data['mcu_id'] ?? 'mcu_default',
                        'garden_number' => $data['garden_id'] ?? 1,
                        'name' => $data['name'] ?? 'Default Microcontroller',
                        'ip_address' => $data['ip_address'] ?? '0.0.0.0',
                        'status' => $data['status'] ?? 'offline',
                        'devices' => ['maybom', 'vantren', 'vanduoi', 'den1', 'quat1', 'den2', 'quat2']
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'No microcontroller found for garden_id: ' . $garden_id,
                    'data' => [
                        'mcu_id' => 'mcu_default',
                        'garden_number' => 1,
                        'name' => 'Default Microcontroller',
                        'ip_address' => '0.0.0.0',
                        'status' => 'offline',
                        'devices' => ['maybom', 'vantren', 'vanduoi', 'den1', 'quat1', 'den2', 'quat2']
                    ]
                ];
            }
        } catch (Exception $e) {
            error_log("Error in getMcuId: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'System error: ' . $e->getMessage()
            ];
        }
    }

    public function updateIp($mcu_id, $ip_address) {
        try {
            if (!$mcu_id || !$ip_address) {
                throw new Exception('Missing mcu_id or ip_address');
            }
            if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
                throw new Exception("Invalid IP address");
            }

            $result = $this->microcontrollerModel->updateMicrocontrollerIpAllGardens($mcu_id, $ip_address);
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'IP updated successfully'
                ];
            } else {
                throw new Exception("Failed to update IP address in database");
            }
        } catch (Exception $e) {
            error_log("Error in updateIp: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    public function checkMcuId($mcu_id) {
        try {
            if (empty($mcu_id)) {
                throw new Exception("Missing mcu_id");
            }
            $exists = $this->microcontrollerModel->checkMcuId($mcu_id);
            return [
                'success' => true,
                'exists' => $exists
            ];
        } catch (Exception $e) {
            error_log("Error in checkMcuId: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    public function updateMicrocontroller($mcu_id, $name, $garden_id, $ip_address, $status, $isAdmin, $userId) {
        try {
            if (empty($mcu_id) || empty($name) || empty($garden_id) || empty($ip_address) || empty($status)) {
                throw new Exception("All fields are required");
            }
            if (!is_numeric($garden_id) || $garden_id <= 0) {
                throw new Exception("Invalid garden ID");
            }
            if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
                throw new Exception("Invalid IP address");
            }
            $validStatuses = ['online', 'offline'];
            if (!in_array($status, $validStatuses)) {
                throw new Exception("Invalid status");
            }

            $garden = $this->gardenModel->getGardenById($garden_id);
            if (!$garden) {
                throw new Exception("Garden does not exist");
            }
            if (!$isAdmin && $garden['user_id'] != $userId) {
                throw new Exception("You do not have permission to update this microcontroller");
            }

            $result = $this->microcontrollerModel->updateMicrocontroller($mcu_id, $name, $garden_id, $ip_address, $status);
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Microcontroller updated successfully'
                ];
            } else {
                throw new Exception("Failed to update microcontroller in database");
            }
        } catch (Exception $e) {
            error_log("Error in updateMicrocontroller: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    public function deleteMicrocontroller($mcu_id, $garden_id, $isAdmin, $userId) {
        try {
            if (empty($mcu_id)) {
                throw new Exception("Invalid microcontroller ID");
            }
            if (!is_numeric($garden_id) || $garden_id <= 0) {
                throw new Exception("Invalid garden ID");
            }

            $garden = $this->gardenModel->getGardenById($garden_id);
            if (!$garden) {
                throw new Exception("Garden does not exist");
            }
            if (!$isAdmin && $garden['user_id'] != $userId) {
                throw new Exception("You do not have permission to delete this microcontroller");
            }

            $result = $this->microcontrollerModel->deleteMicrocontroller($mcu_id);
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Microcontroller deleted successfully'
                ];
            } else {
                throw new Exception("Failed to delete microcontroller from database");
            }
        } catch (Exception $e) {
            error_log("Error in deleteMicrocontroller: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    public function initializeMcu($mcu_id, $ip_address) {
        try {
            if (!$mcu_id || !$ip_address) {
                throw new Exception('Invalid mcu_id or ip_address');
            }
            if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
                throw new Exception("Invalid IP address");
            }

            $result = $this->microcontrollerModel->initializeMcu($mcu_id, $ip_address);
            return [
                'success' => true,
                'data' => $result
            ];
        } catch (Exception $e) {
            error_log("Error in initializeMcu: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error initializing MCU: ' . $e->getMessage()
            ];
        }
    }
}
?>